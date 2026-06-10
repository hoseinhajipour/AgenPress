<?php
/**
 * Agent task runner — executes one step per queue invocation.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskRunner
 */
class TaskRunner {

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Handle Action Scheduler task execution.
	 *
	 * @param int|array{task_id: int} $args Task ID or args array.
	 * @return void
	 */
	public function handle( int|array $args ): void {
		$task_id = is_array( $args ) ? (int) ( $args['task_id'] ?? 0 ) : (int) $args;

		if ( $task_id <= 0 ) {
			return;
		}

		/** @var TaskQueue $queue */
		$queue = $this->container->get( 'task_queue' );
		/** @var TaskStepExecutor $executor */
		$executor = $this->container->get( 'task_step_executor' );

		$task = $queue->find( $task_id );

		if ( ! $task ) {
			return;
		}

		$this->impersonate_task_user( (int) ( $task['user_id'] ?? 0 ) );

		if ( in_array( $task['status'], array( TaskState::PAUSED, TaskState::CANCELLED, TaskState::COMPLETED, TaskState::FAILED ), true ) ) {
			return;
		}

		if ( TaskState::PENDING === $task['status'] ) {
			$queue->update_status( $task_id, TaskState::RUNNING );
		}

		$steps       = $task['steps'];
		$total_steps = count( $steps );
		$step_index  = (int) $task['current_step'];

		if ( $step_index >= $total_steps ) {
			$this->complete_task( $queue, $task_id, $task );
			return;
		}

		$step = $steps[ $step_index ];

		if ( 'completed' === ( $step['status'] ?? '' ) ) {
			$queue->update_progress( $task_id, $step_index + 1, $this->calc_progress( $step_index + 1, $total_steps ) );
			$queue->schedule( $task_id );
			return;
		}

		$runtime = $queue->get_runtime( $task );
		$context = $runtime['context'] ?? array();
		$label   = $step['label'] ?? $step['name'] ?? 'Step ' . ( $step_index + 1 );

		$queue->log( $task_id, $step_index, 'info', 'Starting: ' . $label );

		$result = $executor->execute( $step, (int) $task['user_id'], $task['module'], $context );

		if ( $result['success'] ) {
			$queue->update_step(
				$task_id,
				$step_index,
				array(
					'status' => 'completed',
					'result' => $result['data'],
				)
			);

			if ( ! empty( $result['context_updates'] ) ) {
				$context = array_merge( $context, $result['context_updates'] );
				$runtime['context'] = $context;
				$queue->set_runtime( $task_id, $runtime );
			}

			$log_context = array( 'message' => $result['message'] );

			if ( is_array( $result['data'] ) && ! empty( $result['data']['report'] ) ) {
				$log_context['report'] = $result['data']['report'];
			}

			$queue->log( $task_id, $step_index, 'info', 'Completed: ' . $label, $log_context );

			if ( is_array( $result['data'] ) && ! empty( $result['data']['report']['image_logs'] ) ) {
				foreach ( $result['data']['report']['image_logs'] as $image_log ) {
					if ( ! is_array( $image_log ) ) {
						continue;
					}

					$image_message = (string) ( $image_log['message'] ?? '' );

					if ( '' === $image_message ) {
						continue;
					}

					$queue->log(
						$task_id,
						$step_index,
						! empty( $image_log['success'] ) ? 'info' : 'warning',
						$image_message,
						array( 'image_log' => $image_log )
					);
				}
			}

			$next_step = $step_index + 1;
			$queue->update_progress( $task_id, $next_step, $this->calc_progress( $next_step, $total_steps ) );

			if ( $next_step >= $total_steps ) {
				$task = $queue->find( $task_id );
				$this->complete_task( $queue, $task_id, $task );
			} else {
				$queue->schedule( $task_id );
			}

			return;
		}

		$retries    = (int) ( $step['retries'] ?? 0 );
		$max_retries = (int) ( $step['max_retries'] ?? 2 );

		$failure_context = is_array( $result['data'] ?? null ) ? $result['data'] : array();

		if ( $retries < $max_retries ) {
			$queue->update_step(
				$task_id,
				$step_index,
				array(
					'retries'         => $retries + 1,
					'last_error'      => $result['message'],
					'last_error_data' => $failure_context,
				)
			);
			$queue->log(
				$task_id,
				$step_index,
				'warning',
				sprintf( 'Step failed, retrying (%d/%d): %s', $retries + 1, $max_retries, $result['message'] ),
				$failure_context
			);
			$queue->schedule( $task_id, min( 60, (int) pow( 2, $retries + 1 ) ) );
			return;
		}

		$queue->update_step(
			$task_id,
			$step_index,
			array(
				'status'          => 'failed',
				'last_error'      => $result['message'],
				'last_error_data' => $failure_context,
			)
		);
		$queue->set_result( $task_id, null, $result['message'] );
		$queue->update_status( $task_id, TaskState::FAILED );
		$queue->log( $task_id, $step_index, 'error', 'Step failed: ' . $result['message'], $failure_context );
	}

	/**
	 * Mark task as completed.
	 *
	 * @param TaskQueue              $queue   Queue.
	 * @param int                    $task_id Task ID.
	 * @param array<string, mixed>|null $task    Task row.
	 * @return void
	 */
	private function complete_task( TaskQueue $queue, int $task_id, ?array $task ): void {
		$task    = $task ?? $queue->find( $task_id );
		$runtime = $queue->get_runtime( $task ?? array() );
		$context = $runtime['context'] ?? array();

		$final = array(
			'message'        => sprintf(
				/* translators: %s: task title */
				__( 'Task "%s" completed successfully.', 'agenpress' ),
				$task['title'] ?? ''
			),
			'template'       => $runtime['template'] ?? 'custom',
			'steps_completed' => (int) ( $task['total_steps'] ?? 0 ),
			'created_posts'    => $context['created_posts'] ?? array(),
			'created_products' => $context['created_products'] ?? array(),
			'summary'          => $context['summary'] ?? '',
		);

		$queue->set_result( $task_id, wp_json_encode( $final ) );
		$queue->update_status( $task_id, TaskState::COMPLETED );
		$queue->log( $task_id, (int) ( $task['total_steps'] ?? 0 ), 'info', 'Task completed.' );
	}

	/**
	 * Calculate progress percentage.
	 *
	 * @param int $current Current step.
	 * @param int $total   Total steps.
	 * @return int
	 */
	private function calc_progress( int $current, int $total ): int {
		if ( $total <= 0 ) {
			return 100;
		}

		return (int) round( ( $current / $total ) * 100 );
	}

	/**
	 * Run the task as the user who queued it (Action Scheduler has no login session).
	 *
	 * @param int $user_id Task owner user ID.
	 * @return void
	 */
	private function impersonate_task_user( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		wp_set_current_user( $user_id );
	}
}

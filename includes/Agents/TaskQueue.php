<?php
/**
 * Agent task queue using Action Scheduler.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\Security\AuditLogger;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskQueue
 */
class TaskQueue {

	/**
	 * Action Scheduler hook name.
	 */
	public const HOOK = 'agenpress_run_task';

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit_logger;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger $audit_logger Audit logger.
	 */
	public function __construct( AuditLogger $audit_logger ) {
		$this->audit_logger = $audit_logger;
	}

	/**
	 * Get tasks table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_tasks';
	}

	/**
	 * Get task logs table name.
	 *
	 * @return string
	 */
	private function logs_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_task_logs';
	}

	/**
	 * Create and enqueue a new task.
	 *
	 * @param int                  $user_id     User ID.
	 * @param string               $module      Module slug.
	 * @param string               $title       Task title.
	 * @param string               $description Task description.
	 * @param array<int, mixed>    $steps       Task steps.
	 * @param string               $template    Template ID.
	 * @return array<string, mixed>|null
	 */
	public function create(
		int $user_id,
		string $module,
		string $title,
		string $description = '',
		array $steps = array(),
		string $template = 'custom'
	): ?array {
		global $wpdb;

		$now = current_time( 'mysql', true );

		if ( empty( $steps ) ) {
			$steps = TaskTemplates::build_steps( 'custom', $title, $description );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'user_id'      => $user_id,
				'module'       => sanitize_text_field( $module ),
				'title'        => sanitize_text_field( $title ),
				'description'  => sanitize_textarea_field( $description ),
				'status'       => TaskState::PENDING,
				'progress'     => 0,
				'current_step' => 0,
				'total_steps'  => count( $steps ),
				'steps'        => wp_json_encode( $steps ),
				'result'       => wp_json_encode(
					array(
						'_runtime' => true,
						'template' => $template,
						'context'  => array(),
					)
				),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		$task_id = (int) $wpdb->insert_id;

		$this->log( $task_id, 0, 'info', 'Task created and queued.', array( 'template' => $template ) );
		$this->audit_logger->log( $user_id, 'task_created', $module, array( 'task_id' => $task_id, 'template' => $template ) );
		$this->schedule( $task_id );

		return $this->find( $task_id );
	}

	/**
	 * Schedule task execution via Action Scheduler.
	 *
	 * @param int $task_id   Task ID.
	 * @param int $delay     Delay in seconds.
	 * @return void
	 */
	public function schedule( int $task_id, int $delay = 0 ): void {
		$time = time() + $delay;

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			if ( $delay > 0 && function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $time, self::HOOK, array( 'task_id' => $task_id ), 'agenpress' );
			} else {
				as_enqueue_async_action( self::HOOK, array( 'task_id' => $task_id ), 'agenpress' );
			}
		} else {
			wp_schedule_single_event( $time, self::HOOK, array( array( 'task_id' => $task_id ) ) );
		}
	}

	/**
	 * Find task by ID.
	 *
	 * @param int $id Task ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ? $this->format( $row ) : null;
	}

	/**
	 * List tasks for a user.
	 *
	 * @param int         $user_id User ID.
	 * @param string|null $status  Optional status filter.
	 * @param int         $limit   Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_user( int $user_id, ?string $status = null, int $limit = 20 ): array {
		global $wpdb;

		$table = $this->table();

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d",
					$user_id,
					$status,
					$limit
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
					$user_id,
					$limit
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'format' ), $rows );
	}

	/**
	 * Update task status.
	 *
	 * @param int    $id     Task ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function update_status( int $id, string $status ): bool {
		global $wpdb;

		$task = $this->find( $id );

		if ( ! $task || ! TaskState::can_transition( $task['status'], $status ) ) {
			return false;
		}

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql', true ),
		);

		if ( TaskState::COMPLETED === $status ) {
			$data['completed_at'] = current_time( 'mysql', true );
			$data['progress']     = 100;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$this->table(),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Cancel a task.
	 *
	 * @param int $id Task ID.
	 * @return bool
	 */
	public function cancel( int $id ): bool {
		$updated = $this->update_status( $id, TaskState::CANCELLED );

		if ( $updated ) {
			$this->log( $id, 0, 'info', 'Task cancelled by user.' );
		}

		return $updated;
	}

	/**
	 * Retry a failed task.
	 *
	 * @param int $id Task ID.
	 * @return array<string, mixed>|null
	 */
	public function retry( int $id ): ?array {
		$task = $this->find( $id );

		if ( ! $task || TaskState::FAILED !== $task['status'] ) {
			return null;
		}

		$steps = $task['steps'];

		foreach ( $steps as $index => $step ) {
			if ( 'failed' === ( $step['status'] ?? '' ) ) {
				$steps[ $index ]['status']  = 'pending';
				$steps[ $index ]['retries'] = 0;
			}
		}

		global $wpdb;

		$this->update_steps( $id, $steps );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			array( 'error_message' => null ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		$this->update_status( $id, TaskState::PENDING );
		$this->update_progress( $id, (int) $task['current_step'], (int) $task['progress'] );
		$this->log( $id, 0, 'info', 'Task queued for retry.' );
		$this->schedule( $id );

		return $this->find( $id );
	}

	/**
	 * Re-run a completed task (creates new task with same definition).
	 *
	 * @param int $id     Source task ID.
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	public function rerun( int $id, int $user_id ): ?array {
		$task = $this->find( $id );

		if ( ! $task || (int) $task['user_id'] !== $user_id ) {
			return null;
		}

		$steps = $task['steps'];

		foreach ( $steps as $index => $step ) {
			unset( $steps[ $index ]['result'] );
			$steps[ $index ]['status']  = 'pending';
			$steps[ $index ]['retries'] = 0;
		}

		$runtime = $this->get_runtime( $task );
		$template = $runtime['template'] ?? 'custom';

		return $this->create(
			$user_id,
			$task['module'],
			$task['title'] . ' (' . __( 're-run', 'agenpress' ) . ')',
			$task['description'],
			$steps,
			$template
		);
	}

	/**
	 * Update all steps for a task.
	 *
	 * @param int               $id    Task ID.
	 * @param array<int, mixed> $steps Steps.
	 * @return void
	 */
	public function update_steps( int $id, array $steps ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			array(
				'steps'        => wp_json_encode( $steps ),
				'total_steps'  => count( $steps ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update a single step in the task.
	 *
	 * @param int                  $id         Task ID.
	 * @param int                  $step_index Step index.
	 * @param array<string, mixed> $step_data  Step data to merge.
	 * @return void
	 */
	public function update_step( int $id, int $step_index, array $step_data ): void {
		$task  = $this->find( $id );
		$steps = $task['steps'] ?? array();

		if ( ! isset( $steps[ $step_index ] ) ) {
			return;
		}

		$steps[ $step_index ] = array_merge( $steps[ $step_index ], $step_data );
		$this->update_steps( $id, $steps );
	}

	/**
	 * Get runtime context from task result.
	 *
	 * @param array<string, mixed> $task Task row.
	 * @return array<string, mixed>
	 */
	public function get_runtime( array $task ): array {
		$result = $task['result'];

		if ( is_array( $result ) && ! empty( $result['_runtime'] ) ) {
			return $result;
		}

		return array(
			'_runtime' => true,
			'template' => 'custom',
			'context'  => array(),
		);
	}

	/**
	 * Save runtime context.
	 *
	 * @param int                  $id      Task ID.
	 * @param array<string, mixed> $runtime Runtime data.
	 * @return void
	 */
	public function set_runtime( int $id, array $runtime ): void {
		$runtime['_runtime'] = true;
		$this->set_result( $id, wp_json_encode( $runtime ) );
	}

	/**
	 * Update task progress.
	 *
	 * @param int $id           Task ID.
	 * @param int $current_step Current step index.
	 * @param int $progress     Progress percentage.
	 * @return void
	 */
	public function update_progress( int $id, int $current_step, int $progress ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			array(
				'current_step' => $current_step,
				'progress'     => min( 100, max( 0, $progress ) ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Set task result or error.
	 *
	 * @param int         $id     Task ID.
	 * @param string|null $result Result JSON.
	 * @param string|null $error  Error message.
	 * @return void
	 */
	public function set_result( int $id, ?string $result = null, ?string $error = null ): void {
		global $wpdb;

		$data = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( null !== $result ) {
			$data['result'] = $result;
		}

		if ( null !== $error ) {
			$data['error_message'] = $error;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Log a task step event.
	 *
	 * @param int    $task_id    Task ID.
	 * @param int    $step_index Step index.
	 * @param string $level      Log level.
	 * @param string $message    Log message.
	 * @param array  $context    Additional context.
	 * @return void
	 */
	public function log( int $task_id, int $step_index, string $level, string $message, array $context = array() ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->logs_table(),
			array(
				'task_id'    => $task_id,
				'step_index' => $step_index,
				'level'      => sanitize_text_field( $level ),
				'message'    => sanitize_text_field( $message ),
				'context'    => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get logs for a task.
	 *
	 * @param int $task_id Task ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_logs( int $task_id ): array {
		global $wpdb;

		$table = $this->logs_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE task_id = %d ORDER BY created_at ASC",
				$task_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete a task.
	 *
	 * @param int $id Task ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->logs_table(), array( 'task_id' => $id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete(
			$this->table(),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Format a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function format( array $row ): array {
		$row['id']           = (int) $row['id'];
		$row['user_id']      = (int) $row['user_id'];
		$row['progress']     = (int) $row['progress'];
		$row['current_step'] = (int) $row['current_step'];
		$row['total_steps']  = (int) $row['total_steps'];
		$row['steps']        = json_decode( $row['steps'] ?? '[]', true ) ?: array();
		$row['result']       = json_decode( $row['result'] ?? 'null', true );

		$runtime = is_array( $row['result'] ) && ! empty( $row['result']['_runtime'] ) ? $row['result'] : array();
		$row['template']     = $runtime['template'] ?? 'custom';

		return $row;
	}
}

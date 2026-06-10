<?php
/**
 * Execute automation workflows.
 *
 * @package AgenPress
 */

namespace AgenPress\Workflows;

use AgenPress\Agents\TaskQueue;
use AgenPress\Agents\TaskStepExecutor;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowRunner
 */
class WorkflowRunner {

	/**
	 * Workflow repository.
	 *
	 * @var WorkflowRepository
	 */
	private WorkflowRepository $workflows;

	/**
	 * Task queue.
	 *
	 * @var TaskQueue
	 */
	private TaskQueue $task_queue;

	/**
	 * Step executor.
	 *
	 * @var TaskStepExecutor
	 */
	private TaskStepExecutor $step_executor;

	/**
	 * Constructor.
	 *
	 * @param WorkflowRepository $workflows      Workflows.
	 * @param TaskQueue          $task_queue     Task queue.
	 * @param TaskStepExecutor   $step_executor  Step executor.
	 */
	public function __construct(
		WorkflowRepository $workflows,
		TaskQueue $task_queue,
		TaskStepExecutor $step_executor
	) {
		$this->workflows     = $workflows;
		$this->task_queue    = $task_queue;
		$this->step_executor = $step_executor;
	}

	/**
	 * Run a workflow by creating and queueing a task.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @param int $user_id     User ID.
	 * @return array<string, mixed>|null Created task.
	 */
	public function run( int $workflow_id, int $user_id ): ?array {
		$workflow = $this->workflows->find( $workflow_id );

		if ( ! $workflow || ! $workflow['enabled'] ) {
			return null;
		}

		$steps = $workflow['steps'] ?? array();

		if ( empty( $steps ) ) {
			return null;
		}

		$task = $this->task_queue->create(
			$user_id,
			'admin',
			$workflow['title'],
			$workflow['description'] ?? '',
			$steps,
			'workflow'
		);

		if ( $task ) {
			$this->workflows->touch_run( $workflow_id );
		}

		return $task;
	}
}

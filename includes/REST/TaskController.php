<?php
/**
 * Task REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Agents\AgentEngine;
use AgenPress\Agents\TaskQueue;
use AgenPress\Agents\TaskState;
use AgenPress\Agents\TaskTemplates;
use AgenPress\Core\Container;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskController
 */
class TaskController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/tasks/templates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_templates' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_tasks' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_task' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_task' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_task' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/pause',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_pause' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/cancel',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_task' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/retry',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'retry_task' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)/rerun',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rerun_task' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_access(): bool {
		return current_user_can( Capabilities::RUN_AGENTS );
	}

	/**
	 * GET /tasks/templates
	 *
	 * @return \WP_REST_Response
	 */
	public function list_templates(): \WP_REST_Response {
		return $this->success( TaskTemplates::list() );
	}

	/**
	 * GET /tasks
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_tasks( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue  = $this->container->get( 'task_queue' );
		$status = $request->get_param( 'status' );

		$tasks = $queue->list_for_user(
			get_current_user_id(),
			$status ? sanitize_text_field( $status ) : null,
			(int) ( $request->get_param( 'limit' ) ?? 20 )
		);

		return $this->success( $tasks );
	}

	/**
	 * POST /tasks
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_task( \WP_REST_Request $request ): \WP_REST_Response {
		$title       = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
		$module      = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );
		$template    = sanitize_key( $request->get_param( 'template' ) ?? '' );
		$params      = $request->get_param( 'params' );

		if ( '' === $title ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_task',
					__( 'Task title is required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		/** @var AgentEngine $engine */
		$engine = $this->container->get( 'agent_engine' );
		$task   = $engine->create_task(
			get_current_user_id(),
			$module,
			$title,
			$description,
			array(
				'template' => $template,
				'params'   => is_array( $params ) ? $params : array(),
			)
		);

		if ( ! $task ) {
			return $this->error(
				new \WP_Error(
					'agenpress_create_failed',
					__( 'Failed to create task.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		return $this->success( $task, 201 );
	}

	/**
	 * GET /tasks/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_task( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue = $this->container->get( 'task_queue' );
		$id    = (int) $request->get_param( 'id' );
		$task  = $queue->find( $id );

		if ( ! $task || (int) $task['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Task not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$task['logs'] = array_map(
			static function ( array $log ): array {
				$log['step_index'] = (int) ( $log['step_index'] ?? 0 );
				$decoded           = json_decode( (string) ( $log['context'] ?? '' ), true );
				$log['context']    = is_array( $decoded ) ? $decoded : array();

				return $log;
			},
			$queue->get_logs( $id )
		);

		if ( is_array( $task['result'] ) && ! empty( $task['result']['_runtime'] ) ) {
			$task['runtime_context'] = $task['result']['context'] ?? array();
		} elseif ( is_array( $task['result'] ) && empty( $task['result']['_runtime'] ) ) {
			$task['final_result'] = $task['result'];
		}

		return $this->success( $task );
	}

	/**
	 * DELETE /tasks/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function delete_task( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue = $this->container->get( 'task_queue' );
		$id    = (int) $request->get_param( 'id' );
		$task  = $queue->find( $id );

		if ( ! $task || (int) $task['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Task not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$queue->delete( $id );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * POST /tasks/{id}/pause
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function toggle_pause( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue = $this->container->get( 'task_queue' );
		$id    = (int) $request->get_param( 'id' );
		$task  = $queue->find( $id );

		if ( ! $task || (int) $task['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Task not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		if ( TaskState::RUNNING === $task['status'] ) {
			$queue->update_status( $id, TaskState::PAUSED );
		} elseif ( TaskState::PAUSED === $task['status'] ) {
			$queue->update_status( $id, TaskState::RUNNING );
			$queue->schedule( $id );
		} else {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_state',
					__( 'Task cannot be paused or resumed in its current state.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		return $this->success( $queue->find( $id ) );
	}

	/**
	 * POST /tasks/{id}/cancel
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function cancel_task( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue = $this->container->get( 'task_queue' );
		$id    = (int) $request->get_param( 'id' );
		$task  = $queue->find( $id );

		if ( ! $task || (int) $task['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Task not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		if ( ! in_array( $task['status'], array( TaskState::PENDING, TaskState::RUNNING, TaskState::PAUSED ), true ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_state',
					__( 'Task cannot be cancelled in its current state.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$queue->cancel( $id );

		return $this->success( $queue->find( $id ) );
	}

	/**
	 * POST /tasks/{id}/retry
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function retry_task( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue  = $this->container->get( 'task_queue' );
		$id     = (int) $request->get_param( 'id' );
		$task   = $queue->retry( $id );

		if ( ! $task ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_state',
					__( 'Only failed tasks can be retried.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		return $this->success( $task );
	}

	/**
	 * POST /tasks/{id}/rerun
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rerun_task( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var TaskQueue $queue */
		$queue = $this->container->get( 'task_queue' );
		$id    = (int) $request->get_param( 'id' );
		$task  = $queue->rerun( $id, get_current_user_id() );

		if ( ! $task ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Task not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		return $this->success( $task, 201 );
	}
}

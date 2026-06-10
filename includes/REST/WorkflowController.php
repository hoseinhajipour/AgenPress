<?php
/**
 * Workflow automation REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Core\LicenseGate;
use AgenPress\Security\Capabilities;
use AgenPress\Workflows\WorkflowRepository;
use AgenPress\Workflows\WorkflowRunner;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowController
 */
class WorkflowController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/workflows',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_workflows' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_workflow' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/workflows/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_workflow' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_workflow' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/workflows/(?P<id>\d+)/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_workflow' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::RUN_AGENTS );
	}

	/**
	 * Enterprise gate.
	 *
	 * @return true|\WP_Error
	 */
	private function require_enterprise() {
		/** @var LicenseGate $license */
		$license = $this->container->get( 'license_gate' );

		return $license->require_enterprise();
	}

	/**
	 * GET /workflows
	 *
	 * @return \WP_REST_Response
	 */
	public function list_workflows(): \WP_REST_Response {
		$check = $this->require_enterprise();
		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var WorkflowRepository $repo */
		$repo = $this->container->get( 'workflow_repository' );

		return $this->success( array( 'workflows' => $repo->list( get_current_user_id() ) ) );
	}

	/**
	 * POST /workflows
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		$check = $this->require_enterprise();
		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var WorkflowRepository $repo */
		$repo = $this->container->get( 'workflow_repository' );
		$data = $request->get_json_params();

		$workflow = $repo->create(
			get_current_user_id(),
			sanitize_text_field( $request->get_param( 'title' ) ?? __( 'New Workflow', 'agenpress' ) ),
			is_array( $data ) ? $data : array()
		);

		if ( ! $workflow ) {
			return $this->error(
				new \WP_Error( 'agenpress_create_failed', __( 'Failed to create workflow.', 'agenpress' ), array( 'status' => 500 ) )
			);
		}

		return $this->success( $workflow, 201 );
	}

	/**
	 * PUT /workflows/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		$check = $this->require_enterprise();
		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var WorkflowRepository $repo */
		$repo = $this->container->get( 'workflow_repository' );
		$data = $request->get_json_params();

		return $this->success( $repo->update( (int) $request->get_param( 'id' ), is_array( $data ) ? $data : array() ) );
	}

	/**
	 * DELETE /workflows/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		$check = $this->require_enterprise();
		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var WorkflowRepository $repo */
		$repo = $this->container->get( 'workflow_repository' );
		$repo->delete( (int) $request->get_param( 'id' ) );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * POST /workflows/{id}/run
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function run_workflow( \WP_REST_Request $request ): \WP_REST_Response {
		$check = $this->require_enterprise();
		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var WorkflowRunner $runner */
		$runner = $this->container->get( 'workflow_runner' );
		$task   = $runner->run( (int) $request->get_param( 'id' ), get_current_user_id() );

		if ( ! $task ) {
			return $this->error(
				new \WP_Error( 'agenpress_run_failed', __( 'Failed to run workflow.', 'agenpress' ), array( 'status' => 500 ) )
			);
		}

		return $this->success( $task, 201 );
	}
}

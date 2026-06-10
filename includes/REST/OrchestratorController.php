<?php
/**
 * Multi-agent orchestration REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Agents\MultiAgentOrchestrator;
use AgenPress\Chat\ConversationRepository;
use AgenPress\Core\LicenseGate;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class OrchestratorController
 */
class OrchestratorController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/orchestrate/specialists',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_specialists' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/orchestrate/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'orchestrate_chat' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_use(): bool {
		return current_user_can( Capabilities::USE_ADMIN_AI );
	}

	/**
	 * GET /orchestrate/specialists
	 *
	 * @return \WP_REST_Response
	 */
	public function list_specialists(): \WP_REST_Response {
		/** @var LicenseGate $license */
		$license = $this->container->get( 'license_gate' );
		$check   = $license->require_enterprise();

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var MultiAgentOrchestrator $orchestrator */
		$orchestrator = $this->container->get( 'multi_agent_orchestrator' );

		return $this->success( array( 'specialists' => $orchestrator->list_specialists() ) );
	}

	/**
	 * POST /orchestrate/chat
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function orchestrate_chat( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var LicenseGate $license */
		$license = $this->container->get( 'license_gate' );
		$check   = $license->require_enterprise();

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		$message         = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );
		$conversation_id = (int) ( $request->get_param( 'conversation_id' ) ?? 0 );
		$specialist      = sanitize_text_field( $request->get_param( 'specialist' ) ?? '' );

		if ( '' === $message ) {
			return $this->error(
				new \WP_Error( 'agenpress_empty_message', __( 'Message is required.', 'agenpress' ), array( 'status' => 400 ) )
			);
		}

		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );

		if ( $conversation_id <= 0 ) {
			$conversation = $repo->create( get_current_user_id(), 'admin', substr( $message, 0, 100 ), array( 'orchestrated' => true ) );
			if ( ! $conversation ) {
				return $this->error(
					new \WP_Error( 'agenpress_create_failed', __( 'Failed to create conversation.', 'agenpress' ), array( 'status' => 500 ) )
				);
			}
			$conversation_id = (int) $conversation['id'];
		}

		/** @var MultiAgentOrchestrator $orchestrator */
		$orchestrator = $this->container->get( 'multi_agent_orchestrator' );
		$response     = $orchestrator->orchestrate( $conversation_id, $message, get_current_user_id(), $specialist );

		$repo->touch( $conversation_id );

		return $this->success(
			array(
				'conversation_id' => $conversation_id,
				'message'         => $response['message'],
				'specialist'      => $response['specialist'],
				'module'          => $response['module'],
				'tokens_used'     => $response['tokens_used'],
			)
		);
	}
}

<?php
/**
 * External REST API for enterprise integrations.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Agents\AgentEngine;
use AgenPress\Agents\ToolRegistry;
use AgenPress\Chat\ConversationRepository;
use AgenPress\Modules\ModuleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ExternalApiController
 */
class ExternalApiController extends RestController {

	use ExternalAuth;

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/external/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/external/tools',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_tools' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/external/tools/(?P<name>[a-z_]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'execute_tool' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * POST /external/chat
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function chat( \WP_REST_Request $request ): \WP_REST_Response {
		$key = $this->authenticate_external( $this->container, 'chat' );

		if ( is_wp_error( $key ) ) {
			return $this->error( $key );
		}

		$module  = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );
		$message = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );

		if ( '' === $message ) {
			return $this->error(
				new \WP_Error( 'agenpress_empty_message', __( 'Message is required.', 'agenpress' ), array( 'status' => 400 ) )
			);
		}

		/** @var ConversationRepository $repo */
		$repo         = $this->container->get( 'conversation_repository' );
		/** @var ModuleManager $modules */
		$modules      = $this->container->get( 'module_manager' );
		$user_id      = (int) $key['user_id'];
		$conversation = $repo->create( $user_id, $module, substr( $message, 0, 100 ) );

		if ( ! $conversation ) {
			return $this->error(
				new \WP_Error( 'agenpress_create_failed', __( 'Failed to create conversation.', 'agenpress' ), array( 'status' => 500 ) )
			);
		}

		/** @var AgentEngine $engine */
		$engine   = $this->container->get( 'agent_engine' );
		$response = $engine->chat(
			(int) $conversation['id'],
			$module,
			$message,
			$user_id,
			$modules->get_system_prompt( $module )
		);

		return $this->success(
			array(
				'conversation_id' => $conversation['id'],
				'message'         => $response['message'],
				'tokens_used'     => $response['tokens_used'],
			)
		);
	}

	/**
	 * GET /external/tools
	 *
	 * @return \WP_REST_Response
	 */
	public function list_tools(): \WP_REST_Response {
		$key = $this->authenticate_external( $this->container, 'tools' );

		if ( is_wp_error( $key ) ) {
			return $this->error( $key );
		}

		/** @var ToolRegistry $registry */
		$registry = $this->container->get( 'tool_registry' );

		return $this->success( array( 'tools' => $registry->get_schemas() ) );
	}

	/**
	 * POST /external/tools/{name}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function execute_tool( \WP_REST_Request $request ): \WP_REST_Response {
		$key = $this->authenticate_external( $this->container, 'tools' );

		if ( is_wp_error( $key ) ) {
			return $this->error( $key );
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
		$args = $request->get_param( 'arguments' ) ?? array();
		$module = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		/** @var ToolRegistry $registry */
		$registry = $this->container->get( 'tool_registry' );
		$result   = $registry->execute( $name, $args, (int) $key['user_id'], $module );

		return $this->success( $result );
	}
}

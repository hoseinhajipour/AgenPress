<?php
/**
 * Chat REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Agents\AgentEngine;
use AgenPress\Chat\ConversationRepository;
use AgenPress\Chat\MessageRepository;
use AgenPress\Core\Container;
use AgenPress\Modules\ModuleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatController
 */
class ChatController extends RestController {

	/**
	 * Valid module slugs.
	 *
	 * @var array<string>
	 */
	private array $valid_modules = array( 'admin', 'elementor', 'sales' );

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/conversations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_conversations' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_conversation' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversation' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_conversation' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/chat/(?P<module>[a-z]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/conversations/(?P<id>\d+)/messages/(?P<message_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_message' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/chat/(?P<module>[a-z]+)/confirm',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'confirm_action' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);
	}

	/**
	 * Basic auth check.
	 *
	 * @return bool
	 */
	public function check_auth(): bool {
		return is_user_logged_in();
	}

	/**
	 * GET /conversations
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_conversations( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo   = $this->container->get( 'conversation_repository' );
		$module = $request->get_param( 'module' );

		$conversations = $repo->list_for_user(
			get_current_user_id(),
			$module ? sanitize_text_field( $module ) : null,
			(int) ( $request->get_param( 'limit' ) ?? 20 ),
			(int) ( $request->get_param( 'offset' ) ?? 0 )
		);

		return $this->success( $conversations );
	}

	/**
	 * POST /conversations
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$module = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );

		$check = $this->permissions()->validate_module_access( $module );

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var ConversationRepository $repo */
		$repo         = $this->container->get( 'conversation_repository' );
		$conversation = $repo->create(
			get_current_user_id(),
			$module,
			sanitize_text_field( $request->get_param( 'title' ) ?? '' )
		);

		if ( ! $conversation ) {
			return $this->error(
				new \WP_Error(
					'agenpress_create_failed',
					__( 'Failed to create conversation.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		return $this->success( $conversation, 201 );
	}

	/**
	 * GET /conversations/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );
		/** @var MessageRepository $messages */
		$messages = $this->container->get( 'message_repository' );

		$id           = (int) $request->get_param( 'id' );
		$conversation = $repo->find( $id );

		if ( ! $conversation || (int) $conversation['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Conversation not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$conversation['messages'] = $messages->get_for_conversation( $id );

		return $this->success( $conversation );
	}

	/**
	 * DELETE /conversations/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function delete_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );
		/** @var MessageRepository $messages */
		$messages = $this->container->get( 'message_repository' );

		$id           = (int) $request->get_param( 'id' );
		$conversation = $repo->find( $id );

		if ( ! $conversation || (int) $conversation['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Conversation not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$messages->delete_for_conversation( $id );
		$repo->delete( $id );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * DELETE /conversations/{id}/messages/{message_id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function delete_message( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );
		/** @var MessageRepository $messages */
		$messages = $this->container->get( 'message_repository' );

		$conversation_id = (int) $request->get_param( 'id' );
		$message_id      = (int) $request->get_param( 'message_id' );
		$conversation    = $repo->find( $conversation_id );

		if ( ! $conversation || (int) $conversation['user_id'] !== get_current_user_id() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Conversation not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		if ( ! $messages->belongs_to_user( $message_id, get_current_user_id(), $conversation_id ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Message not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		if ( ! $messages->delete( $message_id ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_delete_failed',
					__( 'Failed to delete message.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * POST /chat/{module}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function send_message( \WP_REST_Request $request ): \WP_REST_Response {
		$module = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );

		if ( ! in_array( $module, $this->valid_modules, true ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_module',
					__( 'Invalid module.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$check = $this->permissions()->validate_module_access( $module );

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		$rate_check = $this->check_rate_limit();

		if ( is_wp_error( $rate_check ) ) {
			return $this->error( $rate_check );
		}

		$content          = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );
		$conversation_id  = (int) ( $request->get_param( 'conversation_id' ) ?? 0 );
		$attachments      = $request->get_param( 'attachments' ) ?? array();
		$context          = $request->get_param( 'context' ) ?? array();

		if ( '' === $content ) {
			return $this->error(
				new \WP_Error(
					'agenpress_empty_message',
					__( 'Message cannot be empty.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );

		if ( $conversation_id <= 0 ) {
			$conversation = $repo->create(
				get_current_user_id(),
				$module,
				substr( $content, 0, 100 )
			);

			if ( ! $conversation ) {
				return $this->error(
					new \WP_Error(
						'agenpress_create_failed',
						__( 'Failed to create conversation.', 'agenpress' ),
						array( 'status' => 500 )
					)
				);
			}

			$conversation_id = (int) $conversation['id'];
		} else {
			$existing = $repo->find( $conversation_id );

			if ( ! $existing || (int) $existing['user_id'] !== get_current_user_id() ) {
				return $this->error(
					new \WP_Error(
						'agenpress_not_found',
						__( 'Conversation not found.', 'agenpress' ),
						array( 'status' => 404 )
					)
				);
			}
		}

		/** @var ModuleManager $module_manager */
		$module_manager = $this->container->get( 'module_manager' );
		$chat_context   = is_array( $context ) ? $context : array();
		$system_prompt  = $module_manager->get_system_prompt( $module, $chat_context );

		/** @var AgentEngine $engine */
		$engine   = $this->container->get( 'agent_engine' );
		$response = $engine->chat(
			$conversation_id,
			$module,
			$content,
			get_current_user_id(),
			$system_prompt,
			is_array( $attachments ) ? $attachments : array(),
			is_array( $context ) ? $context : array()
		);

		$repo->touch( $conversation_id );

		$data = array(
			'conversation_id' => $conversation_id,
			'user_message'    => $response['user_message'] ?? array(),
			'message'         => $response['message'],
			'tokens_used'     => $response['tokens_used'],
			'model'           => $response['model'],
		);

		if ( ! empty( $response['pending_actions'] ) ) {
			$data['pending_actions'] = $response['pending_actions'];
		}

		return $this->success( $data );
	}

	/**
	 * POST /chat/{module}/confirm
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function confirm_action( \WP_REST_Request $request ): \WP_REST_Response {
		$module          = sanitize_text_field( $request->get_param( 'module' ) ?? 'admin' );
		$pending_id      = sanitize_text_field( $request->get_param( 'pending_id' ) ?? '' );
		$conversation_id = (int) ( $request->get_param( 'conversation_id' ) ?? 0 );

		if ( '' === $pending_id || $conversation_id <= 0 ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_confirm',
					__( 'Pending action ID and conversation ID are required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$check = $this->permissions()->validate_module_access( $module );

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var AgentEngine $engine */
		$engine   = $this->container->get( 'agent_engine' );
		$response = $engine->confirm_action( $pending_id, get_current_user_id(), $conversation_id );

		return $this->success(
			array(
				'conversation_id' => $conversation_id,
				'message'         => $response['message'],
				'tool_result'     => $response['tool_result'] ?? null,
			)
		);
	}
}

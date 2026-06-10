<?php
/**
 * Public storefront sales chat REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Agents\AgentEngine;
use AgenPress\Chat\ConversationRepository;
use AgenPress\Chat\MessageRepository;
use AgenPress\Core\Settings;
use AgenPress\Modules\ModuleManager;
use AgenPress\Sales\VisitorSession;
use AgenPress\Security\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * Class SalesChatController
 */
class SalesChatController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/sales/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sales/conversation/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_conversation' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sales/escalate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'escalate' ),
				'permission_callback' => array( $this, 'can_chat' ),
			)
		);
	}

	/**
	 * Check if storefront chat is available.
	 *
	 * @return bool
	 */
	public function can_chat(): bool {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );

		return $settings->is_sales_chat_enabled();
	}

	/**
	 * POST /sales/chat
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function send_message( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var VisitorSession $session */
		$session    = $this->container->get( 'visitor_session' );
		$visitor_id = $session->get_visitor_id();
		$user_id    = get_current_user_id();

		/** @var RateLimiter $limiter */
		$limiter = $this->container->get( 'rate_limiter' );
		/** @var Settings $settings */
		$settings   = $this->container->get( 'settings' );
		$rate_check = $limiter->check( $user_id, $settings, $visitor_id );

		if ( is_wp_error( $rate_check ) ) {
			return $this->error( $rate_check );
		}

		$content         = sanitize_textarea_field( $request->get_param( 'message' ) ?? '' );
		$conversation_id = (int) ( $request->get_param( 'conversation_id' ) ?? 0 );

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

		if ( $conversation_id > 0 ) {
			$existing = $repo->find_for_visitor( $conversation_id, $visitor_id );

			if ( ! $existing ) {
				$existing = $repo->find( $conversation_id );
				$owned    = $existing && (
					( $existing['metadata']['visitor_id'] ?? '' ) === $visitor_id
					|| ( $user_id > 0 && (int) $existing['user_id'] === $user_id )
				);

				if ( ! $owned ) {
					return $this->error(
						new \WP_Error(
							'agenpress_not_found',
							__( 'Conversation not found.', 'agenpress' ),
							array( 'status' => 404 )
						)
					);
				}
			}
		} else {
			$conversation = $repo->create(
				$user_id,
				'sales',
				substr( $content, 0, 100 ),
				array(
					'visitor_id' => $visitor_id,
					'channel'    => 'storefront',
				)
			);

			if ( ! $conversation ) {
				return $this->error(
					new \WP_Error(
						'agenpress_create_failed',
						__( 'Failed to start conversation.', 'agenpress' ),
						array( 'status' => 500 )
					)
				);
			}

			$conversation_id = (int) $conversation['id'];
		}

		/** @var ModuleManager $module_manager */
		$module_manager = $this->container->get( 'module_manager' );
		$system_prompt  = $module_manager->get_system_prompt( 'sales' );

		$context = array(
			'conversation_id' => $conversation_id,
			'visitor_id'      => $visitor_id,
			'user_id'         => $user_id,
		);

		/** @var AgentEngine $engine */
		$engine = $this->container->get( 'agent_engine' );

		/** @var \AgenPress\Security\PermissionValidator $permissions */
		$permissions = $this->container->get( 'permission_validator' );
		$permissions->set_sales_customer_mode( true );

		$response = $engine->chat(
			$conversation_id,
			'sales',
			$content,
			$user_id,
			$system_prompt,
			array(),
			$context
		);

		$permissions->set_sales_customer_mode( false );

		$repo->touch( $conversation_id );

		return $this->success(
			array(
				'conversation_id' => $conversation_id,
				'message'         => $response['message'],
				'tokens_used'     => $response['tokens_used'],
				'model'           => $response['model'],
				'escalated'       => 'escalated' === ( $repo->find( $conversation_id )['status'] ?? '' ),
			)
		);
	}

	/**
	 * GET /sales/conversation/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var VisitorSession $session */
		$session    = $this->container->get( 'visitor_session' );
		$visitor_id = $session->get_visitor_id();
		$id         = (int) $request->get_param( 'id' );

		/** @var ConversationRepository $repo */
		$repo         = $this->container->get( 'conversation_repository' );
		/** @var MessageRepository $messages */
		$messages     = $this->container->get( 'message_repository' );
		$conversation = $repo->find_for_visitor( $id, $visitor_id );

		if ( ! $conversation && get_current_user_id() > 0 ) {
			$conversation = $repo->find( $id );
			if ( $conversation && (int) $conversation['user_id'] !== get_current_user_id() ) {
				$conversation = null;
			}
		}

		if ( ! $conversation ) {
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
	 * POST /sales/escalate
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function escalate( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var VisitorSession $session */
		$session         = $this->container->get( 'visitor_session' );
		$visitor_id      = $session->get_visitor_id();
		$conversation_id = (int) ( $request->get_param( 'conversation_id' ) ?? 0 );

		if ( $conversation_id <= 0 ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_data',
					__( 'Conversation ID is required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		/** @var ConversationRepository $repo */
		$repo         = $this->container->get( 'conversation_repository' );
		$conversation = $repo->find_for_visitor( $conversation_id, $visitor_id );

		if ( ! $conversation ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Conversation not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$repo->update_status(
			$conversation_id,
			'escalated',
			array(
				'escalated_at' => current_time( 'mysql', true ),
				'escalation_reason' => sanitize_text_field( $request->get_param( 'reason' ) ?? __( 'Customer requested human support', 'agenpress' ) ),
			)
		);

		return $this->success(
			array(
				'conversation_id' => $conversation_id,
				'status'          => 'escalated',
			)
		);
	}
}

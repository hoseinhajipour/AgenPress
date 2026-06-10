<?php
/**
 * Admin inbox for escalated sales chats.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Chat\ConversationRepository;
use AgenPress\Chat\MessageRepository;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class InboxController
 */
class InboxController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/inbox',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_inbox' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/inbox/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversation' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/inbox/team',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_team' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/inbox/(?P<id>\d+)/assign',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'assign' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/inbox/(?P<id>\d+)/resolve',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resolve' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Inbox permission.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE_SALES_AI );
	}

	/**
	 * GET /inbox
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_inbox( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo   = $this->container->get( 'conversation_repository' );
		$status = sanitize_key( (string) ( $request->get_param( 'status' ) ?? '' ) );
		$allowed = array( 'active', 'escalated', 'resolved' );
		$filter  = in_array( $status, $allowed, true ) ? $status : null;

		return $this->success(
			array(
				'conversations' => $repo->list_storefront_sales( $filter ),
				'counts'        => $repo->count_storefront_sales_by_status(),
				'status'        => $filter ?? 'all',
			)
		);
	}

	/**
	 * GET /inbox/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );
		/** @var MessageRepository $messages */
		$messages = $this->container->get( 'message_repository' );
		$id       = (int) $request->get_param( 'id' );

		$conversation = $repo->find( $id );

		if ( ! $conversation || 'sales' !== $conversation['module'] ) {
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
	 * GET /inbox/team
	 *
	 * @return \WP_REST_Response
	 */
	public function list_team(): \WP_REST_Response {
		$users = get_users(
			array(
				'capability' => Capabilities::MANAGE_SALES_AI,
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		$team = array_map(
			static function ( $user ) {
				return array(
					'id'    => (int) $user->ID,
					'name'  => $user->display_name,
					'email' => $user->user_email,
				);
			},
			$users
		);

		return $this->success( array( 'team' => $team ) );
	}

	/**
	 * POST /inbox/{id}/assign
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function assign( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );
		$id   = (int) $request->get_param( 'id' );
		$agent_id = (int) ( $request->get_param( 'user_id' ) ?? 0 );

		if ( ! $repo->find( $id ) ) {
			return $this->error(
				new \WP_Error( 'agenpress_not_found', __( 'Conversation not found.', 'agenpress' ), array( 'status' => 404 ) )
			);
		}

		$repo->assign( $id, $agent_id );

		return $this->success( array( 'assigned_to' => $agent_id ) );
	}

	/**
	 * POST /inbox/{id}/resolve
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function resolve( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ConversationRepository $repo */
		$repo = $this->container->get( 'conversation_repository' );
		$id   = (int) $request->get_param( 'id' );

		$conversation = $repo->find( $id );

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
			$id,
			'resolved',
			array(
				'resolved_at' => current_time( 'mysql', true ),
				'resolved_by' => get_current_user_id(),
			)
		);

		return $this->success( array( 'resolved' => true ) );
	}
}

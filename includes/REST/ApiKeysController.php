<?php
/**
 * API key management REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Core\LicenseGate;
use AgenPress\Security\ApiKeyManager;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ApiKeysController
 */
class ApiKeysController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/api-keys',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_keys' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_key' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/api-keys/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_key' ),
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
		return current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * GET /api-keys
	 *
	 * @return \WP_REST_Response
	 */
	public function list_keys(): \WP_REST_Response {
		/** @var LicenseGate $license */
		$license = $this->container->get( 'license_gate' );
		$check   = $license->require_enterprise();

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var ApiKeyManager $keys */
		$keys = $this->container->get( 'api_key_manager' );

		return $this->success( array( 'keys' => $keys->list_for_user( get_current_user_id() ) ) );
	}

	/**
	 * POST /api-keys
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_key( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var LicenseGate $license */
		$license = $this->container->get( 'license_gate' );
		$check   = $license->require_enterprise();

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var ApiKeyManager $keys */
		$keys = $this->container->get( 'api_key_manager' );
		$key  = $keys->create(
			get_current_user_id(),
			sanitize_text_field( $request->get_param( 'name' ) ?? 'API Key' )
		);

		if ( ! $key ) {
			return $this->error(
				new \WP_Error( 'agenpress_create_failed', __( 'Failed to create API key.', 'agenpress' ), array( 'status' => 500 ) )
			);
		}

		return $this->success( $key, 201 );
	}

	/**
	 * DELETE /api-keys/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_key( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var ApiKeyManager $keys */
		$keys = $this->container->get( 'api_key_manager' );
		$keys->delete( (int) $request->get_param( 'id' ), get_current_user_id() );

		return $this->success( array( 'deleted' => true ) );
	}
}

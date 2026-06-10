<?php
/**
 * Settings REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Core\Container;
use AgenPress\Core\Settings;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsController
 */
class SettingsController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Permission check for reading settings.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( Capabilities::USE_ADMIN_AI );
	}

	/**
	 * Permission check for managing settings.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );

		return $this->success( $settings->get_all() );
	}

	/**
	 * PUT /settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$data     = $request->get_json_params();

		if ( ! is_array( $data ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_data',
					__( 'Invalid settings data.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$updated = $settings->update( $data );

		return $this->success( $updated );
	}
}

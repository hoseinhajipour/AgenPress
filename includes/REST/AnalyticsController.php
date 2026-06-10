<?php
/**
 * Analytics REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Analytics\AnalyticsService;
use AgenPress\Core\LicenseGate;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsController
 */
class AnalyticsController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/analytics',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * GET /analytics
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_analytics( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var LicenseGate $license */
		$license = $this->container->get( 'license_gate' );
		$check   = $license->require_enterprise();

		if ( is_wp_error( $check ) ) {
			return $this->error( $check );
		}

		/** @var AnalyticsService $analytics */
		$analytics = $this->container->get( 'analytics_service' );
		$days      = (int) ( $request->get_param( 'days' ) ?? 30 );

		return $this->success( $analytics->get_summary( max( 1, min( 90, $days ) ) ) );
	}
}

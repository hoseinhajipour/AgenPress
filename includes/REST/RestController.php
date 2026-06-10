<?php
/**
 * Base REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Core\Container;
use AgenPress\Core\Settings;
use AgenPress\Security\PermissionValidator;
use AgenPress\Security\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestController
 */
abstract class RestController extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'agenpress/v1';

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Service container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return true|\WP_Error
	 */
	protected function require_auth() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'agenpress_unauthorized',
				__( 'Authentication required.', 'agenpress' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Check rate limit for current user.
	 *
	 * @return true|\WP_Error
	 */
	protected function check_rate_limit() {
		/** @var RateLimiter $limiter */
		$limiter  = $this->container->get( 'rate_limiter' );
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );

		return $limiter->check( get_current_user_id(), $settings );
	}

	/**
	 * Get permission validator.
	 *
	 * @return PermissionValidator
	 */
	protected function permissions(): PermissionValidator {
		return $this->container->get( 'permission_validator' );
	}

	/**
	 * Format success response.
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status.
	 * @return \WP_REST_Response
	 */
	protected function success( mixed $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Format error response.
	 *
	 * @param \WP_Error $error WordPress error.
	 * @return \WP_REST_Response
	 */
	protected function error( \WP_Error $error ): \WP_REST_Response {
		$status = (int) ( $error->get_error_data()['status'] ?? 400 );

		return new \WP_REST_Response(
			array(
				'success' => false,
				'error'   => array(
					'code'    => $error->get_error_code(),
					'message' => $error->get_error_message(),
				),
			),
			$status
		);
	}
}

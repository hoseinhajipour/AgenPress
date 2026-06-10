<?php
/**
 * External API key authentication helper.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Core\Container;
use AgenPress\Core\LicenseGate;
use AgenPress\Security\ApiKeyManager;

defined( 'ABSPATH' ) || exit;

/**
 * Trait ExternalAuth
 */
trait ExternalAuth {

	/**
	 * Authenticate external request.
	 *
	 * @param Container $container Container.
	 * @param string    $scope     Required scope.
	 * @return array<string, mixed>|\WP_Error Key record.
	 */
	protected function authenticate_external( Container $container, string $scope = 'chat' ) {
		/** @var LicenseGate $license */
		$license = $container->get( 'license_gate' );
		$check   = $license->require_enterprise();

		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$token = $this->get_bearer_token();

		if ( '' === $token ) {
			return new \WP_Error(
				'agenpress_missing_api_key',
				__( 'API key required. Use Authorization: Bearer agp_...', 'agenpress' ),
				array( 'status' => 401 )
			);
		}

		/** @var ApiKeyManager $keys */
		$keys = $container->get( 'api_key_manager' );
		$key  = $keys->authenticate( $token );

		if ( ! $key ) {
			return new \WP_Error(
				'agenpress_invalid_api_key',
				__( 'Invalid API key.', 'agenpress' ),
				array( 'status' => 401 )
			);
		}

		if ( ! $keys->has_scope( $key, $scope ) ) {
			return new \WP_Error(
				'agenpress_forbidden_scope',
				__( 'API key does not have permission for this scope.', 'agenpress' ),
				array( 'status' => 403 )
			);
		}

		return $key;
	}

	/**
	 * Extract bearer token from request.
	 *
	 * @return string
	 */
	private function get_bearer_token(): string {
		$header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

		if ( preg_match( '/Bearer\s+(\S+)/i', $header, $matches ) ) {
			return sanitize_text_field( $matches[1] );
		}

		return '';
	}
}

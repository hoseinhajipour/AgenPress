<?php
/**
 * Guest visitor session for storefront chat.
 *
 * @package AgenPress
 */

namespace AgenPress\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Class VisitorSession
 */
class VisitorSession {

	private const COOKIE_NAME = 'agenpress_visitor';

	/**
	 * Get or create a visitor ID from cookie.
	 *
	 * @return string
	 */
	public function get_visitor_id(): string {
		$cookie = isset( $_COOKIE[ self::COOKIE_NAME ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
			: '';

		if ( $cookie && $this->verify_cookie( $cookie ) ) {
			return $this->extract_id( $cookie );
		}

		$visitor_id = wp_generate_uuid4();
		$value      = $this->sign( $visitor_id );

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$value,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ?: '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		$_COOKIE[ self::COOKIE_NAME ] = $value;

		return $visitor_id;
	}

	/**
	 * Validate a visitor cookie value.
	 *
	 * @param string $cookie Cookie value.
	 * @return bool
	 */
	public function verify_cookie( string $cookie ): bool {
		$id = $this->extract_id( $cookie );

		return '' !== $id && hash_equals( $this->sign( $id ), $cookie );
	}

	/**
	 * Extract visitor ID from signed cookie.
	 *
	 * @param string $cookie Cookie value.
	 * @return string
	 */
	private function extract_id( string $cookie ): string {
		$parts = explode( '|', $cookie, 2 );

		return sanitize_text_field( $parts[0] ?? '' );
	}

	/**
	 * Sign a visitor ID.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @return string
	 */
	private function sign( string $visitor_id ): string {
		$hash = hash_hmac( 'sha256', $visitor_id, wp_salt( 'agenpress_visitor' ) );

		return $visitor_id . '|' . $hash;
	}
}

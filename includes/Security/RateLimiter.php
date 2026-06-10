<?php
/**
 * Rate limiter using WordPress transients.
 *
 * @package AgenPress
 */

namespace AgenPress\Security;

use AgenPress\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class RateLimiter
 */
class RateLimiter {

	/**
	 * Check if user is within rate limit.
	 *
	 * @param int         $user_id  User ID.
	 * @param Settings|null $settings Settings instance.
	 * @return true|\WP_Error
	 */
	public function check( int $user_id, ?Settings $settings = null, string $rate_key = '' ) {
		$limit = $settings ? $settings->get_rate_limit() : 60;

		if ( '' !== $rate_key ) {
			$key = 'agenpress_rate_' . md5( $rate_key );
		} elseif ( $user_id > 0 ) {
			$key = 'agenpress_rate_user_' . $user_id;
		} else {
			return new \WP_Error(
				'agenpress_rate_limit',
				__( 'Rate limiting requires a user or visitor key.', 'agenpress' ),
				array( 'status' => 401 )
			);
		}
		$requests = (int) get_transient( $key );

		if ( $requests >= $limit ) {
			return new \WP_Error(
				'agenpress_rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'agenpress' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $requests + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Get remaining requests for a user.
	 *
	 * @param int      $user_id  User ID.
	 * @param Settings $settings Settings instance.
	 * @return int
	 */
	public function remaining( int $user_id, Settings $settings ): int {
		$key      = 'agenpress_rate_' . $user_id;
		$requests = (int) get_transient( $key );
		$limit    = $settings->get_rate_limit();

		return max( 0, $limit - $requests );
	}
}

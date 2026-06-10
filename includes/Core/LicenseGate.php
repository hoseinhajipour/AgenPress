<?php
/**
 * License tier feature gating.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class LicenseGate
 */
class LicenseGate {

	/**
	 * Tier rank map.
	 *
	 * @var array<string, int>
	 */
	private const TIER_RANK = array(
		'basic'      => 1,
		'pro'        => 2,
		'enterprise' => 3,
	);

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get current license tier.
	 *
	 * @return string
	 */
	public function get_tier(): string {
		return $this->settings->get_license_tier();
	}

	/**
	 * Check if current tier meets minimum requirement.
	 *
	 * @param string $minimum_tier Minimum tier slug.
	 * @return bool
	 */
	public function has_tier( string $minimum_tier ): bool {
		$current = self::TIER_RANK[ $this->get_tier() ] ?? 1;
		$minimum = self::TIER_RANK[ $minimum_tier ] ?? 1;

		return $current >= $minimum;
	}

	/**
	 * Check enterprise features.
	 *
	 * @return bool
	 */
	public function is_enterprise(): bool {
		return $this->has_tier( 'enterprise' );
	}

	/**
	 * Check pro-or-higher features.
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return $this->has_tier( 'pro' );
	}

	/**
	 * Return WP_Error when enterprise is required.
	 *
	 * @return true|\WP_Error
	 */
	public function require_enterprise() {
		if ( $this->is_enterprise() ) {
			return true;
		}

		return new \WP_Error(
			'agenpress_enterprise_required',
			__( 'This feature requires an Enterprise license.', 'agenpress' ),
			array( 'status' => 403 )
		);
	}
}

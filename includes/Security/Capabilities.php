<?php
/**
 * Custom capabilities for AgenPress.
 *
 * @package AgenPress
 */

namespace AgenPress\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class Capabilities
 */
class Capabilities {

	public const USE_ADMIN_AI      = 'agenpress_use_admin_ai';
	public const USE_ELEMENTOR_AI  = 'agenpress_use_elementor_ai';
	public const MANAGE_SALES_AI   = 'agenpress_manage_sales_ai';
	public const RUN_AGENTS        = 'agenpress_run_agents';
	public const MANAGE_MEMORY     = 'agenpress_manage_memory';
	public const MANAGE_SETTINGS   = 'agenpress_manage_settings';

	/**
	 * All capability slugs.
	 *
	 * @return array<string>
	 */
	public static function all(): array {
		return array(
			self::USE_ADMIN_AI,
			self::USE_ELEMENTOR_AI,
			self::MANAGE_SALES_AI,
			self::RUN_AGENTS,
			self::MANAGE_MEMORY,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Register capabilities on the administrator role.
	 *
	 * @return void
	 */
	public static function register(): void {
		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( self::all() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Assign capabilities to default WordPress roles.
	 *
	 * @return void
	 */
	public static function assign_to_roles(): void {
		$role_caps = array(
			'editor'       => array( self::USE_ADMIN_AI, self::USE_ELEMENTOR_AI ),
			'shop_manager' => array( self::USE_ADMIN_AI, self::MANAGE_SALES_AI, self::RUN_AGENTS ),
		);

		foreach ( $role_caps as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( $role ) {
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Get required capability for a module.
	 *
	 * @param string $module Module slug.
	 * @return string
	 */
	public static function for_module( string $module ): string {
		return match ( $module ) {
			'elementor' => self::USE_ELEMENTOR_AI,
			'sales'     => self::MANAGE_SALES_AI,
			default     => self::USE_ADMIN_AI,
		};
	}
}

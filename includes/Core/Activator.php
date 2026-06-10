<?php
/**
 * Plugin activation handler.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

use AgenPress\Database\Migrations;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Migrations::run();
		Capabilities::register();
		Capabilities::assign_to_roles();

		if ( ! get_option( 'agenpress_settings' ) ) {
			add_option(
				'agenpress_settings',
				array(
					'default_provider' => 'openai',
					'default_model'    => 'gpt-4o-mini',
					'rate_limit'       => 60,
					'license_tier'     => 'basic',
				)
			);
		}

		flush_rewrite_rules();
	}
}

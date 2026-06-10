<?php
/**
 * Plugin Name:       AgenPress
 * Plugin URI:        https://github.com/agenpress/agenpress
 * Description:       AI Operating System for WordPress — Admin, Elementor, and Sales AI assistants with agentic task execution.
 * Version:           0.7.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            AgenPress
 * Author URI:        https://agenpress.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agenpress
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AGENPRESS_VERSION', '0.7.0' );
define( 'AGENPRESS_PLUGIN_FILE', __FILE__ );
define( 'AGENPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENPRESS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$agenpress_autoload = AGENPRESS_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $agenpress_autoload ) ) {
	require_once $agenpress_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'AgenPress\\';

			if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$file     = AGENPRESS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

$agenpress_action_scheduler = AGENPRESS_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

if ( file_exists( $agenpress_action_scheduler ) ) {
	require_once $agenpress_action_scheduler;
}

/**
 * Returns the main plugin instance.
 *
 * @return \AgenPress\Plugin
 */
function agenpress(): \AgenPress\Plugin {
	return \AgenPress\Plugin::instance();
}

register_activation_hook( __FILE__, array( \AgenPress\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \AgenPress\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( \AgenPress\Plugin::class ) ) {
			agenpress()->run();
		}
	}
);

<?php
/**
 * Plugin deactivation handler.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}

<?php
/**
 * Database migrations.
 *
 * @package AgenPress
 */

namespace AgenPress\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migrations
 */
class Migrations {

	/**
	 * Current database schema version.
	 */
	public const DB_VERSION = '1.1.0';

	/**
	 * Run all pending migrations.
	 *
	 * @return void
	 */
	public static function run(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = Schema::get_tables();

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'agenpress_db_version', self::DB_VERSION );
	}
}

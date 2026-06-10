<?php
/**
 * Uninstall AgenPress.
 *
 * @package AgenPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'agenpress_conversations',
	$wpdb->prefix . 'agenpress_messages',
	$wpdb->prefix . 'agenpress_tasks',
	$wpdb->prefix . 'agenpress_task_logs',
	$wpdb->prefix . 'agenpress_memory',
	$wpdb->prefix . 'agenpress_embeddings',
	$wpdb->prefix . 'agenpress_audit_log',
	$wpdb->prefix . 'agenpress_prompts',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$options = array(
	'agenpress_settings',
	'agenpress_license_tier',
	'agenpress_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

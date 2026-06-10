<?php
/**
 * Database schema definitions.
 *
 * @package AgenPress
 */

namespace AgenPress\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
class Schema {

	/**
	 * Get all table creation SQL statements.
	 *
	 * @return array<string, string> Table name => CREATE TABLE SQL.
	 */
	public static function get_tables(): array {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$conversations = $wpdb->prefix . 'agenpress_conversations';
		$messages      = $wpdb->prefix . 'agenpress_messages';
		$tasks         = $wpdb->prefix . 'agenpress_tasks';
		$task_logs     = $wpdb->prefix . 'agenpress_task_logs';
		$memory        = $wpdb->prefix . 'agenpress_memory';
		$embeddings    = $wpdb->prefix . 'agenpress_embeddings';
		$audit_log     = $wpdb->prefix . 'agenpress_audit_log';
		$prompts       = $wpdb->prefix . 'agenpress_prompts';
		$workflows     = $wpdb->prefix . 'agenpress_workflows';
		$api_keys      = $wpdb->prefix . 'agenpress_api_keys';

		return array(
			$conversations => "CREATE TABLE {$conversations} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				module varchar(50) NOT NULL DEFAULT 'admin',
				title varchar(255) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				metadata longtext NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY module (module),
				KEY status (status)
			) {$charset_collate};",

			$messages => "CREATE TABLE {$messages} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				conversation_id bigint(20) unsigned NOT NULL,
				role varchar(20) NOT NULL DEFAULT 'user',
				content longtext NOT NULL,
				attachments longtext NULL,
				tokens_used int(11) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY conversation_id (conversation_id),
				KEY role (role)
			) {$charset_collate};",

			$tasks => "CREATE TABLE {$tasks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				module varchar(50) NOT NULL DEFAULT 'admin',
				title varchar(255) NOT NULL DEFAULT '',
				description longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				progress tinyint(3) unsigned NOT NULL DEFAULT 0,
				current_step int(11) unsigned NOT NULL DEFAULT 0,
				total_steps int(11) unsigned NOT NULL DEFAULT 0,
				steps longtext NULL,
				result longtext NULL,
				error_message text NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				completed_at datetime NULL,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY status (status),
				KEY module (module)
			) {$charset_collate};",

			$task_logs => "CREATE TABLE {$task_logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				task_id bigint(20) unsigned NOT NULL,
				step_index int(11) unsigned NOT NULL DEFAULT 0,
				level varchar(20) NOT NULL DEFAULT 'info',
				message text NOT NULL,
				context longtext NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY task_id (task_id),
				KEY level (level)
			) {$charset_collate};",

			$memory => "CREATE TABLE {$memory} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				category varchar(50) NOT NULL DEFAULT 'brand',
				key_name varchar(191) NOT NULL DEFAULT '',
				value longtext NOT NULL,
				metadata longtext NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY category (category),
				KEY key_name (key_name)
			) {$charset_collate};",

			$embeddings => "CREATE TABLE {$embeddings} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				memory_id bigint(20) unsigned NOT NULL DEFAULT 0,
				chunk_text longtext NOT NULL,
				vector longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY memory_id (memory_id)
			) {$charset_collate};",

			$audit_log => "CREATE TABLE {$audit_log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(100) NOT NULL DEFAULT '',
				module varchar(50) NOT NULL DEFAULT '',
				details longtext NULL,
				ip_address varchar(45) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY action (action),
				KEY module (module)
			) {$charset_collate};",

			$prompts => "CREATE TABLE {$prompts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				module varchar(50) NOT NULL DEFAULT 'admin',
				title varchar(255) NOT NULL DEFAULT '',
				content longtext NOT NULL,
				is_favorite tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY module (module)
			) {$charset_collate};",

			$workflows => "CREATE TABLE {$workflows} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(255) NOT NULL DEFAULT '',
				description text NULL,
				trigger_type varchar(20) NOT NULL DEFAULT 'manual',
				trigger_config longtext NULL,
				steps longtext NULL,
				enabled tinyint(1) NOT NULL DEFAULT 1,
				last_run_at datetime NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY enabled (enabled),
				KEY trigger_type (trigger_type)
			) {$charset_collate};",

			$api_keys => "CREATE TABLE {$api_keys} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				name varchar(191) NOT NULL DEFAULT '',
				key_hash varchar(64) NOT NULL DEFAULT '',
				key_prefix varchar(12) NOT NULL DEFAULT '',
				scopes longtext NULL,
				last_used_at datetime NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY key_hash (key_hash)
			) {$charset_collate};",
		);
	}
}

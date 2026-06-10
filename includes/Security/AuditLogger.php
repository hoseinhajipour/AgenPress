<?php
/**
 * Security audit logger.
 *
 * @package AgenPress
 */

namespace AgenPress\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuditLogger
 */
class AuditLogger {

	/**
	 * Log an audit event.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $action  Action name.
	 * @param string               $module  Module slug.
	 * @param array<string, mixed> $details Additional details.
	 * @return int|false Insert ID or false on failure.
	 */
	public function log( int $user_id, string $action, string $module, array $details = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'agenpress_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'action'     => sanitize_text_field( $action ),
				'module'     => sanitize_text_field( $module ),
				'details'    => wp_json_encode( $details ),
				'ip_address' => $this->get_client_ip(),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get recent audit log entries.
	 *
	 * @param int $limit  Number of entries.
	 * @param int $offset Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'agenpress_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$headers = array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				$ip = explode( ',', $ip )[0];

				if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ) {
					return trim( $ip );
				}
			}
		}

		return '';
	}
}

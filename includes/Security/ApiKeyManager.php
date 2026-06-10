<?php
/**
 * External API key management.
 *
 * @package AgenPress
 */

namespace AgenPress\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Class ApiKeyManager
 */
class ApiKeyManager {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_api_keys';
	}

	/**
	 * Create a new API key.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Key label.
	 * @param array<string> $scopes Allowed scopes.
	 * @return array{id: int, key: string, name: string, prefix: string}|null
	 */
	public function create( int $user_id, string $name, array $scopes = array( 'chat', 'tools', 'mcp' ) ): ?array {
		global $wpdb;

		$raw    = 'agp_' . wp_generate_password( 32, false, false );
		$prefix = substr( $raw, 0, 12 );
		$hash   = hash( 'sha256', $raw );
		$now    = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'user_id'    => $user_id,
				'name'       => sanitize_text_field( $name ),
				'key_hash'   => $hash,
				'key_prefix' => $prefix,
				'scopes'     => wp_json_encode( array_values( $scopes ) ),
				'created_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		return array(
			'id'     => (int) $wpdb->insert_id,
			'key'    => $raw,
			'name'   => sanitize_text_field( $name ),
			'prefix' => $prefix,
		);
	}

	/**
	 * List API keys for a user (without secrets).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_user( int $user_id ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, user_id, name, key_prefix, scopes, last_used_at, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'format' ), $rows );
	}

	/**
	 * Authenticate a bearer token.
	 *
	 * @param string $token Raw API key.
	 * @return array<string, mixed>|null Key record.
	 */
	public function authenticate( string $token ): ?array {
		if ( ! str_starts_with( $token, 'agp_' ) ) {
			return null;
		}

		global $wpdb;

		$hash  = hash( 'sha256', $token );
		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE key_hash = %s LIMIT 1",
				$hash
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'last_used_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $row['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		return $this->format( $row );
	}

	/**
	 * Check if key has a scope.
	 *
	 * @param array<string, mixed> $key   Key record.
	 * @param string               $scope Scope slug.
	 * @return bool
	 */
	public function has_scope( array $key, string $scope ): bool {
		$scopes = $key['scopes'] ?? array();

		return in_array( $scope, $scopes, true );
	}

	/**
	 * Delete an API key.
	 *
	 * @param int $id      Key ID.
	 * @param int $user_id Owner user ID.
	 * @return bool
	 */
	public function delete( int $id, int $user_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete(
			$this->table(),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Format key row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private function format( array $row ): array {
		$row['id']       = (int) $row['id'];
		$row['user_id']  = (int) $row['user_id'];
		$row['scopes']   = json_decode( $row['scopes'] ?? '[]', true ) ?: array();
		$row['key_hint'] = ( $row['key_prefix'] ?? '' ) . '****';

		return $row;
	}
}

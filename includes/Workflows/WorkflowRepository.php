<?php
/**
 * Workflow persistence.
 *
 * @package AgenPress
 */

namespace AgenPress\Workflows;

defined( 'ABSPATH' ) || exit;

/**
 * Class WorkflowRepository
 */
class WorkflowRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_workflows';
	}

	/**
	 * Create a workflow.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $title   Title.
	 * @param array<string, mixed> $data    Workflow data.
	 * @return array<string, mixed>|null
	 */
	public function create( int $user_id, string $title, array $data ): ?array {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'user_id'        => $user_id,
				'title'          => sanitize_text_field( $title ),
				'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
				'trigger_type'   => sanitize_key( $data['trigger_type'] ?? 'manual' ),
				'trigger_config' => wp_json_encode( $data['trigger_config'] ?? array() ),
				'steps'          => wp_json_encode( $data['steps'] ?? array() ),
				'enabled'        => ! empty( $data['enabled'] ) ? 1 : 0,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? $this->find( (int) $wpdb->insert_id ) : null;
	}

	/**
	 * Find workflow by ID.
	 *
	 * @param int $id Workflow ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? $this->format( $row ) : null;
	}

	/**
	 * List workflows.
	 *
	 * @param int $user_id User ID (0 = all).
	 * @return array<int, array<string, mixed>>
	 */
	public function list( int $user_id = 0 ): array {
		global $wpdb;

		$table = $this->table();

		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC", $user_id ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC", ARRAY_A );
		}

		return is_array( $rows ) ? array_map( array( $this, 'format' ), $rows ) : array();
	}

	/**
	 * Update workflow.
	 *
	 * @param int                  $id   Workflow ID.
	 * @param array<string, mixed> $data Data.
	 * @return array<string, mixed>|null
	 */
	public function update( int $id, array $data ): ?array {
		global $wpdb;

		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );

		foreach ( array( 'title', 'description', 'trigger_type' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = 'description' === $field
					? sanitize_textarea_field( $data[ $field ] )
					: sanitize_text_field( $data[ $field ] );
				$format[]         = '%s';
			}
		}

		if ( isset( $data['steps'] ) ) {
			$update['steps'] = wp_json_encode( $data['steps'] );
			$format[]        = '%s';
		}

		if ( isset( $data['trigger_config'] ) ) {
			$update['trigger_config'] = wp_json_encode( $data['trigger_config'] );
			$format[]                 = '%s';
		}

		if ( isset( $data['enabled'] ) ) {
			$update['enabled'] = $data['enabled'] ? 1 : 0;
			$format[]            = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->table(), $update, array( 'id' => $id ), $format, array( '%d' ) );

		return $this->find( $id );
	}

	/**
	 * Mark workflow as run.
	 *
	 * @param int $id Workflow ID.
	 * @return void
	 */
	public function touch_run( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			array(
				'last_run_at' => current_time( 'mysql', true ),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete workflow.
	 *
	 * @param int $id Workflow ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Format row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private function format( array $row ): array {
		$row['id']             = (int) $row['id'];
		$row['user_id']        = (int) $row['user_id'];
		$row['enabled']        = (bool) $row['enabled'];
		$row['steps']          = json_decode( $row['steps'] ?? '[]', true ) ?: array();
		$row['trigger_config'] = json_decode( $row['trigger_config'] ?? '{}', true ) ?: array();

		return $row;
	}
}

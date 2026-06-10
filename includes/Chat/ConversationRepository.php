<?php
/**
 * Conversation repository.
 *
 * @package AgenPress
 */

namespace AgenPress\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConversationRepository
 */
class ConversationRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_conversations';
	}

	/**
	 * Create a new conversation.
	 *
	 * @param int                  $user_id User ID.
	 * @param string               $module  Module slug.
	 * @param string               $title   Conversation title.
	 * @param array<string, mixed> $metadata Metadata.
	 * @return array<string, mixed>|null
	 */
	public function create( int $user_id, string $module, string $title = '', array $metadata = array(), string $status = 'active' ): ?array {
		global $wpdb;

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'user_id'    => $user_id,
				'module'     => sanitize_text_field( $module ),
				'title'      => sanitize_text_field( $title ),
				'status'     => sanitize_key( $status ),
				'metadata'   => wp_json_encode( $metadata ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Find conversation by ID.
	 *
	 * @param int $id Conversation ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ? $this->format( $row ) : null;
	}

	/**
	 * List conversations for a user.
	 *
	 * @param int         $user_id User ID.
	 * @param string|null $module  Optional module filter.
	 * @param int         $limit   Limit.
	 * @param int         $offset  Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_user( int $user_id, ?string $module = null, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();

		if ( $module ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d AND module = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$user_id,
					$module,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'format' ), $rows );
	}

	/**
	 * Update conversation title.
	 *
	 * @param int    $id    Conversation ID.
	 * @param string $title New title.
	 * @return bool
	 */
	public function update_title( int $id, string $title ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$this->table(),
			array(
				'title'      => sanitize_text_field( $title ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Find conversation owned by a visitor session.
	 *
	 * @param int    $id         Conversation ID.
	 * @param string $visitor_id Visitor ID.
	 * @return array<string, mixed>|null
	 */
	public function find_for_visitor( int $id, string $visitor_id ): ?array {
		$conversation = $this->find( $id );

		if ( ! $conversation ) {
			return null;
		}

		$owner = $conversation['metadata']['visitor_id'] ?? '';

		if ( $owner !== $visitor_id ) {
			return null;
		}

		return $conversation;
	}

	/**
	 * List escalated sales conversations for admin inbox.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_escalated( int $limit = 50 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE module = %s AND status = %s ORDER BY updated_at DESC LIMIT %d",
				'sales',
				'escalated',
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'format' ), $rows );
	}

	/**
	 * Update conversation status and metadata.
	 *
	 * @param int                  $id       Conversation ID.
	 * @param string               $status   New status.
	 * @param array<string, mixed> $metadata Metadata to merge.
	 * @return bool
	 */
	public function update_status( int $id, string $status, array $metadata = array() ): bool {
		global $wpdb;

		$conversation = $this->find( $id );

		if ( ! $conversation ) {
			return false;
		}

		$merged = array_merge( $conversation['metadata'] ?? array(), $metadata );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$this->table(),
			array(
				'status'     => sanitize_key( $status ),
				'metadata'   => wp_json_encode( $merged ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Assign conversation to a team member.
	 *
	 * @param int $id             Conversation ID.
	 * @param int $agent_user_id  Assigned user ID (0 to unassign).
	 * @return bool
	 */
	public function assign( int $id, int $agent_user_id ): bool {
		$conversation = $this->find( $id );

		if ( ! $conversation ) {
			return false;
		}

		$metadata = array_merge(
			$conversation['metadata'] ?? array(),
			array(
				'assigned_to' => $agent_user_id,
				'assigned_at' => current_time( 'mysql', true ),
			)
		);

		return $this->update_status( $id, $conversation['status'], $metadata );
	}

	/**
	 * List conversations assigned to a team member.
	 *
	 * @param int $agent_user_id Agent user ID.
	 * @param int $limit         Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_assigned_to( int $agent_user_id, int $limit = 50 ): array {
		global $wpdb;

		$table = $this->table();
		$like  = '%"assigned_to":' . (int) $agent_user_id . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE metadata LIKE %s ORDER BY updated_at DESC LIMIT %d",
				$like,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_map( array( $this, 'format' ), $rows ) : array();
	}

	/**
	 * Touch conversation updated_at timestamp.
	 *
	 * @param int $id Conversation ID.
	 * @return void
	 */
	public function touch( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			array( 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a conversation.
	 *
	 * @param int $id Conversation ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete(
			$this->table(),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Format a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function format( array $row ): array {
		$row['id']       = (int) $row['id'];
		$row['user_id']  = (int) $row['user_id'];
		$row['metadata'] = json_decode( $row['metadata'] ?? '{}', true ) ?: array();

		return $row;
	}
}

<?php
/**
 * Message repository.
 *
 * @package AgenPress
 */

namespace AgenPress\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Class MessageRepository
 */
class MessageRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_messages';
	}

	/**
	 * Create a message.
	 *
	 * @param int                  $conversation_id Conversation ID.
	 * @param string               $role            Message role.
	 * @param string               $content         Message content.
	 * @param array<string, mixed> $attachments     Attachments.
	 * @param int                  $tokens_used     Tokens used.
	 * @return array<string, mixed>|null
	 */
	public function create(
		int $conversation_id,
		string $role,
		string $content,
		array $attachments = array(),
		int $tokens_used = 0
	): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'conversation_id' => $conversation_id,
				'role'            => sanitize_text_field( $role ),
				'content'         => $content,
				'attachments'     => wp_json_encode( $attachments ),
				'tokens_used'     => $tokens_used,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * Find message by ID.
	 *
	 * @param int $id Message ID.
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
	 * Get messages for a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $limit           Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_conversation( int $conversation_id, int $limit = 100 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC LIMIT %d",
				$conversation_id,
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
	 * Get messages formatted for AI provider.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array<int, array{role: string, content: string}>
	 */
	public function get_ai_messages( int $conversation_id ): array {
		$messages = $this->get_for_conversation( $conversation_id );
		$formatted = array();

		foreach ( $messages as $message ) {
			$formatted[] = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);
		}

		return $formatted;
	}

	/**
	 * Delete all messages for a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return void
	 */
	public function delete_for_conversation( int $conversation_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->table(),
			array( 'conversation_id' => $conversation_id ),
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
		$row['id']              = (int) $row['id'];
		$row['conversation_id'] = (int) $row['conversation_id'];
		$row['tokens_used']     = (int) $row['tokens_used'];
		$row['attachments']     = json_decode( $row['attachments'] ?? '[]', true ) ?: array();

		return $row;
	}
}

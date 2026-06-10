<?php
/**
 * Cross-session memory for logged-in storefront sales chat customers.
 *
 * @package AgenPress
 */

namespace AgenPress\Sales;

use AgenPress\Chat\ConversationRepository;
use AgenPress\Chat\MessageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class CustomerMemory
 */
class CustomerMemory {

	private const MAX_HISTORY_CONVERSATIONS = 5;
	private const MAX_MESSAGES_PER_CONVERSATION = 8;
	private const MAX_HISTORY_CHARS = 6000;

	/**
	 * Conversation repository.
	 *
	 * @var ConversationRepository
	 */
	private ConversationRepository $conversations;

	/**
	 * Message repository.
	 *
	 * @var MessageRepository
	 */
	private MessageRepository $messages;

	/**
	 * Constructor.
	 *
	 * @param ConversationRepository $conversations Conversation repository.
	 * @param MessageRepository      $messages      Message repository.
	 */
	public function __construct( ConversationRepository $conversations, MessageRepository $messages ) {
		$this->conversations = $conversations;
		$this->messages      = $messages;
	}

	/**
	 * Resolve which conversation to use for a storefront chat request.
	 *
	 * @param int    $user_id         WordPress user ID (0 for guests).
	 * @param int    $conversation_id Requested conversation ID.
	 * @param string $visitor_id      Visitor session ID.
	 * @return int Resolved conversation ID, or 0 to create a new one.
	 */
	public function resolve_conversation_id( int $user_id, int $conversation_id, string $visitor_id ): int {
		if ( $conversation_id > 0 ) {
			return $conversation_id;
		}

		if ( $user_id <= 0 ) {
			return 0;
		}

		$latest = $this->find_resumable_conversation( $user_id, $visitor_id );

		return $latest ? (int) $latest['id'] : 0;
	}

	/**
	 * Find the latest active storefront conversation for a customer.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $visitor_id Visitor session ID.
	 * @return array<string, mixed>|null
	 */
	public function find_resumable_conversation( int $user_id, string $visitor_id ): ?array {
		$candidates = $this->list_storefront_conversations( $user_id, $visitor_id, 15 );

		foreach ( $candidates as $conversation ) {
			if ( 'active' !== ( $conversation['status'] ?? '' ) ) {
				continue;
			}

			return $conversation;
		}

		return null;
	}

	/**
	 * Build prompt text from prior storefront conversations for this customer.
	 *
	 * @param int    $user_id                 WordPress user ID.
	 * @param string $visitor_id              Visitor session ID.
	 * @param int    $exclude_conversation_id Current conversation to exclude.
	 * @return string
	 */
	public function build_history_prompt( int $user_id, string $visitor_id, int $exclude_conversation_id = 0 ): string {
		if ( $user_id <= 0 && '' === $visitor_id ) {
			return '';
		}

		$conversations = $this->list_storefront_conversations( $user_id, $visitor_id, 20 );
		$sections      = array();
		$total_chars   = 0;

		foreach ( $conversations as $conversation ) {
			$conversation_id = (int) $conversation['id'];

			if ( $exclude_conversation_id > 0 && $conversation_id === $exclude_conversation_id ) {
				continue;
			}

			if ( count( $sections ) >= self::MAX_HISTORY_CONVERSATIONS ) {
				break;
			}

			$thread = $this->format_conversation_thread( $conversation, self::MAX_MESSAGES_PER_CONVERSATION );

			if ( '' === $thread ) {
				continue;
			}

			$section = sprintf(
				"--- Previous chat (%s) ---\n%s",
				$conversation['updated_at'] ?? $conversation['created_at'] ?? '',
				$thread
			);

			if ( $total_chars + strlen( $section ) > self::MAX_HISTORY_CHARS ) {
				break;
			}

			$sections[]   = $section;
			$total_chars += strlen( $section );
		}

		if ( empty( $sections ) ) {
			return '';
		}

		return implode(
			"\n\n",
			array(
				__( 'This customer has chatted with you before. Use the prior conversations below to personalize replies, remember preferences, and avoid repeating questions they already answered. Do not mention that you are reading stored logs unless the customer asks.', 'agenpress' ),
				implode( "\n\n", $sections ),
			)
		);
	}

	/**
	 * List storefront sales conversations for a customer.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $visitor_id Visitor session ID.
	 * @param int    $limit      Max conversations to scan.
	 * @return array<int, array<string, mixed>>
	 */
	private function list_storefront_conversations( int $user_id, string $visitor_id, int $limit ): array {
		$by_id = array();

		if ( $user_id > 0 ) {
			foreach ( $this->conversations->list_for_user( $user_id, 'sales', $limit ) as $conversation ) {
				if ( $this->is_storefront_conversation( $conversation ) ) {
					$by_id[ (int) $conversation['id'] ] = $conversation;
				}
			}
		}

		if ( '' !== $visitor_id ) {
			foreach ( $this->conversations->list_for_visitor( $visitor_id, 'sales', $limit ) as $conversation ) {
				if ( $this->is_storefront_conversation( $conversation ) ) {
					$by_id[ (int) $conversation['id'] ] = $conversation;
				}
			}
		}

		$conversations = array_values( $by_id );

		usort(
			$conversations,
			static function ( array $a, array $b ): int {
				return strcmp( $b['updated_at'] ?? '', $a['updated_at'] ?? '' );
			}
		);

		return array_slice( $conversations, 0, $limit );
	}

	/**
	 * Check whether a conversation belongs to the storefront channel.
	 *
	 * @param array<string, mixed> $conversation Conversation row.
	 * @return bool
	 */
	private function is_storefront_conversation( array $conversation ): bool {
		return 'storefront' === ( $conversation['metadata']['channel'] ?? '' );
	}

	/**
	 * Format recent messages from a conversation for prompt context.
	 *
	 * @param array<string, mixed> $conversation Conversation row.
	 * @param int                  $limit        Max messages.
	 * @return string
	 */
	private function format_conversation_thread( array $conversation, int $limit ): string {
		$messages = $this->messages->get_for_conversation( (int) $conversation['id'], $limit * 2 );
		$lines    = array();

		foreach ( array_slice( $messages, -$limit ) as $message ) {
			$role = $message['role'] ?? '';

			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
				continue;
			}

			$content = trim( (string) ( $message['content'] ?? '' ) );

			if ( '' === $content ) {
				continue;
			}

			$label   = 'user' === $role ? __( 'Customer', 'agenpress' ) : __( 'Assistant', 'agenpress' );
			$lines[] = sprintf( '%s: %s', $label, $content );
		}

		return implode( "\n", $lines );
	}
}

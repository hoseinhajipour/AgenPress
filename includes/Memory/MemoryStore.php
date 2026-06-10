<?php
/**
 * Memory store for site knowledge.
 *
 * @package AgenPress
 */

namespace AgenPress\Memory;

defined( 'ABSPATH' ) || exit;

/**
 * Class MemoryStore
 */
class MemoryStore {

	/**
	 * Valid memory categories.
	 *
	 * @var array<string>
	 */
	public const CATEGORIES = array( 'brand', 'contact', 'design', 'seo', 'general' );

	/**
	 * Embedding service.
	 *
	 * @var EmbeddingService|null
	 */
	private ?EmbeddingService $embedding_service = null;

	/**
	 * Embedding repository.
	 *
	 * @var EmbeddingRepository|null
	 */
	private ?EmbeddingRepository $embedding_repository = null;

	/**
	 * Inject embedding dependencies.
	 *
	 * @param EmbeddingService    $embedding_service    Embedding service.
	 * @param EmbeddingRepository $embedding_repository Embedding repository.
	 * @return void
	 */
	public function set_embedding_services( EmbeddingService $embedding_service, EmbeddingRepository $embedding_repository ): void {
		$this->embedding_service    = $embedding_service;
		$this->embedding_repository = $embedding_repository;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_memory';
	}

	/**
	 * Create a memory entry.
	 *
	 * @param string               $category Category slug.
	 * @param string               $key_name Key name.
	 * @param string               $value    Value.
	 * @param array<string, mixed> $metadata Metadata.
	 * @return array<string, mixed>|null
	 */
	public function create( string $category, string $key_name, string $value, array $metadata = array() ): ?array {
		global $wpdb;

		if ( ! in_array( $category, self::CATEGORIES, true ) ) {
			$category = 'general';
		}

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'category'   => sanitize_text_field( $category ),
				'key_name'   => sanitize_text_field( $key_name ),
				'value'      => wp_kses_post( $value ),
				'metadata'   => wp_json_encode( $metadata ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		$id    = (int) $wpdb->insert_id;
		$entry = $this->find( $id );

		if ( $entry ) {
			$this->sync_embedding( $entry );
		}

		return $entry;
	}

	/**
	 * Find memory entry by ID.
	 *
	 * @param int $id Entry ID.
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
	 * Find memory entry by category and key.
	 *
	 * @param string $category Category slug.
	 * @param string $key_name Key name.
	 * @return array<string, mixed>|null
	 */
	public function find_by_key( string $category, string $key_name ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE category = %s AND key_name = %s LIMIT 1",
				$category,
				$key_name
			),
			ARRAY_A
		);

		return $row ? $this->format( $row ) : null;
	}

	/**
	 * List memory entries.
	 *
	 * @param string|null $category Optional category filter.
	 * @param string      $search   Optional search term.
	 * @param int         $limit    Limit.
	 * @param int         $offset   Offset.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( ?string $category = null, string $search = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = $this->table();

		if ( $category && $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE category = %s AND (key_name LIKE %s OR value LIKE %s) ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$category,
					$like,
					$like,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} elseif ( $category ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE category = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$category,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} elseif ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE key_name LIKE %s OR value LIKE %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$like,
					$like,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
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
	 * Semantic search over embedded memory.
	 *
	 * @param string             $query      Search query.
	 * @param array<string>|null $categories Optional categories.
	 * @param int                $limit      Max results.
	 * @return array<int, array<string, mixed>>
	 */
	public function semantic_search( string $query, ?array $categories = null, int $limit = 10 ): array {
		if ( ! $this->embedding_service || ! $this->embedding_repository ) {
			return array();
		}

		$query = trim( $query );

		if ( '' === $query ) {
			return array();
		}

		$vector = $this->embedding_service->embed( $query );

		if ( empty( $vector ) ) {
			return array();
		}

		$results = $this->embedding_repository->search( $vector, $limit, $categories );

		/**
		 * Filter semantic search results (e.g. route to external vector DB).
		 *
		 * @param array<int, array<string, mixed>> $results    Search results.
		 * @param string                           $query      Query text.
		 * @param array<string>|null               $categories Category filter.
		 * @param int                              $limit      Result limit.
		 */
		$results = apply_filters( 'agenpress_memory_search_results', $results, $query, $categories, $limit );

		return array_map(
			static function ( array $row ): array {
				return array(
					'id'       => $row['memory_id'],
					'category' => $row['category'],
					'key_name' => $row['key_name'],
					'value'    => $row['value'],
					'score'    => round( $row['score'], 4 ),
				);
			},
			$results
		);
	}

	/**
	 * Re-embed all memory entries.
	 *
	 * @return array{processed: int, embedded: int, skipped: int}
	 */
	public function reindex_all(): array {
		$entries   = $this->list( null, '', 500, 0 );
		$embedded  = 0;
		$skipped   = 0;

		foreach ( $entries as $entry ) {
			if ( $this->sync_embedding( $entry ) ) {
				++$embedded;
			} else {
				++$skipped;
			}
		}

		return array(
			'processed' => count( $entries ),
			'embedded'  => $embedded,
			'skipped'   => $skipped,
		);
	}

	/**
	 * Update a memory entry.
	 *
	 * @param int                  $id       Entry ID.
	 * @param array<string, mixed> $data     Update data.
	 * @return array<string, mixed>|null
	 */
	public function update( int $id, array $data ): ?array {
		global $wpdb;

		$update = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );

		if ( isset( $data['category'] ) && in_array( $data['category'], self::CATEGORIES, true ) ) {
			$update['category'] = sanitize_text_field( $data['category'] );
			$format[]           = '%s';
		}

		if ( isset( $data['key_name'] ) ) {
			$update['key_name'] = sanitize_text_field( $data['key_name'] );
			$format[]           = '%s';
		}

		if ( isset( $data['value'] ) ) {
			$update['value'] = wp_kses_post( $data['value'] );
			$format[]        = '%s';
		}

		if ( isset( $data['metadata'] ) ) {
			$update['metadata'] = wp_json_encode( $data['metadata'] );
			$format[]           = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table(),
			$update,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		$entry = $this->find( $id );

		if ( $entry ) {
			$this->sync_embedding( $entry );
		}

		return $entry;
	}

	/**
	 * Delete a memory entry.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		if ( $this->embedding_repository ) {
			$this->embedding_repository->delete_by_memory_id( $id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete(
			$this->table(),
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get memory context as a string for AI prompts (recent entries fallback).
	 *
	 * @param int                $limit      Max entries.
	 * @param array<string>|null $categories Optional category filter.
	 * @return string
	 */
	public function get_context_string( int $limit = 20, ?array $categories = null ): string {
		$entries = $categories
			? $this->list_by_categories( $categories, $limit )
			: $this->list( null, '', $limit );

		return $this->entries_to_context( $entries );
	}

	/**
	 * Build context string from semantic search results or fallback.
	 *
	 * @param string             $query      User query for RAG.
	 * @param array<string>|null $categories Category filter.
	 * @param int                $limit      Max entries.
	 * @return string
	 */
	public function get_rag_context( string $query, ?array $categories = null, int $limit = 8 ): string {
		$entries = array();

		if ( '' !== trim( $query ) && $this->embedding_repository && $this->embedding_repository->count() > 0 ) {
			$entries = $this->semantic_search( $query, $categories, $limit );
		}

		if ( empty( $entries ) ) {
			$entries = $categories
				? $this->list_by_categories( $categories, $limit )
				: $this->list( null, '', $limit );
		}

		return $this->entries_to_context( $entries );
	}

	/**
	 * List entries across multiple categories.
	 *
	 * @param array<string> $categories Categories.
	 * @param int           $limit      Max entries.
	 * @return array<int, array<string, mixed>>
	 */
	private function list_by_categories( array $categories, int $limit ): array {
		$entries = array();

		foreach ( $categories as $category ) {
			$entries = array_merge( $entries, $this->list( $category, '', $limit ) );
		}

		usort(
			$entries,
			static function ( array $a, array $b ): int {
				return strcmp( $b['updated_at'] ?? '', $a['updated_at'] ?? '' );
			}
		);

		return array_slice( $entries, 0, $limit );
	}

	/**
	 * Convert entries to a prompt context block.
	 *
	 * @param array<int, array<string, mixed>> $entries Memory entries.
	 * @return string
	 */
	private function entries_to_context( array $entries ): string {
		if ( empty( $entries ) ) {
			return '';
		}

		$lines = array( 'Site Memory:' );

		foreach ( $entries as $entry ) {
			$lines[] = sprintf(
				'[%s] %s: %s',
				$entry['category'],
				$entry['key_name'],
				$entry['value']
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Generate and store embedding for a memory entry.
	 *
	 * @param array<string, mixed> $entry Memory entry.
	 * @return bool
	 */
	private function sync_embedding( array $entry ): bool {
		if ( ! $this->embedding_service || ! $this->embedding_repository ) {
			return false;
		}

		$chunk  = $this->chunk_text( $entry );
		$vector = $this->embedding_service->embed( $chunk );

		if ( empty( $vector ) ) {
			return false;
		}

		return $this->embedding_repository->upsert( (int) $entry['id'], $chunk, $vector );
	}

	/**
	 * Build embeddable chunk text for a memory entry.
	 *
	 * @param array<string, mixed> $entry Memory entry.
	 * @return string
	 */
	private function chunk_text( array $entry ): string {
		return sprintf(
			'[%s] %s: %s',
			$entry['category'] ?? 'general',
			$entry['key_name'] ?? '',
			$entry['value'] ?? ''
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
		$row['metadata'] = json_decode( $row['metadata'] ?? '{}', true ) ?: array();

		if ( $this->embedding_repository ) {
			$row['has_embedding'] = $this->embedding_repository->has_embedding( (int) $row['id'] );
		} else {
			$row['has_embedding'] = false;
		}

		return $row;
	}
}

<?php
/**
 * Embedding vector storage and similarity search.
 *
 * @package AgenPress
 */

namespace AgenPress\Memory;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmbeddingRepository
 */
class EmbeddingRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'agenpress_embeddings';
	}

	/**
	 * Store or replace embedding for a memory entry.
	 *
	 * @param int           $memory_id  Memory entry ID.
	 * @param string        $chunk_text Chunk text.
	 * @param array<int, float> $vector Embedding vector.
	 * @return bool
	 */
	public function upsert( int $memory_id, string $chunk_text, array $vector ): bool {
		global $wpdb;

		$this->delete_by_memory_id( $memory_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return false !== $wpdb->insert(
			$this->table(),
			array(
				'memory_id'  => $memory_id,
				'chunk_text' => $chunk_text,
				'vector'     => wp_json_encode( $vector ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Delete embeddings for a memory entry.
	 *
	 * @param int $memory_id Memory entry ID.
	 * @return void
	 */
	public function delete_by_memory_id( int $memory_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->table(),
			array( 'memory_id' => $memory_id ),
			array( '%d' )
		);
	}

	/**
	 * Check if a memory entry has an embedding.
	 *
	 * @param int $memory_id Memory entry ID.
	 * @return bool
	 */
	public function has_embedding( int $memory_id ): bool {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE memory_id = %d",
				$memory_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Count total embeddings.
	 *
	 * @return int
	 */
	public function count(): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Find similar chunks by cosine similarity.
	 *
	 * @param array<int, float>  $query_vector Query embedding.
	 * @param int                $limit        Max results.
	 * @param array<string>|null $categories   Optional category filter.
	 * @return array<int, array{memory_id: int, chunk_text: string, score: float, category: string, key_name: string, value: string}>
	 */
	public function search( array $query_vector, int $limit = 10, ?array $categories = null ): array {
		if ( empty( $query_vector ) ) {
			return array();
		}

		global $wpdb;

		$embed_table  = $this->table();
		$memory_table = $wpdb->prefix . 'agenpress_memory';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT e.memory_id, e.chunk_text, e.vector, m.category, m.key_name, m.value
			FROM {$embed_table} e
			INNER JOIN {$memory_table} m ON m.id = e.memory_id",
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$scored = array();

		foreach ( $rows as $row ) {
			if ( $categories && ! in_array( $row['category'], $categories, true ) ) {
				continue;
			}

			$vector = json_decode( $row['vector'] ?? '[]', true );

			if ( ! is_array( $vector ) || empty( $vector ) ) {
				continue;
			}

			$score = $this->cosine_similarity( $query_vector, $vector );

			if ( $score <= 0 ) {
				continue;
			}

			$scored[] = array(
				'memory_id'  => (int) $row['memory_id'],
				'chunk_text' => $row['chunk_text'],
				'score'      => $score,
				'category'   => $row['category'],
				'key_name'   => $row['key_name'],
				'value'      => $row['value'],
			);
		}

		usort(
			$scored,
			static function ( array $a, array $b ): int {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $scored, 0, $limit );
	}

	/**
	 * Compute cosine similarity between two vectors.
	 *
	 * @param array<int, float> $a First vector.
	 * @param array<int, float> $b Second vector.
	 * @return float
	 */
	private function cosine_similarity( array $a, array $b ): float {
		$dot   = 0.0;
		$norm_a = 0.0;
		$norm_b = 0.0;
		$len    = min( count( $a ), count( $b ) );

		for ( $i = 0; $i < $len; $i++ ) {
			$av = (float) $a[ $i ];
			$bv = (float) $b[ $i ];
			$dot   += $av * $bv;
			$norm_a += $av * $av;
			$norm_b += $bv * $bv;
		}

		if ( $norm_a <= 0 || $norm_b <= 0 ) {
			return 0.0;
		}

		return $dot / ( sqrt( $norm_a ) * sqrt( $norm_b ) );
	}
}

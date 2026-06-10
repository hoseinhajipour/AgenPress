<?php
/**
 * Usage analytics aggregation.
 *
 * @package AgenPress
 */

namespace AgenPress\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Class AnalyticsService
 */
class AnalyticsService {

	/**
	 * Get dashboard analytics summary.
	 *
	 * @param int $days Lookback days.
	 * @return array<string, mixed>
	 */
	public function get_summary( int $days = 30 ): array {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$messages      = $wpdb->prefix . 'agenpress_messages';
		$conversations = $wpdb->prefix . 'agenpress_conversations';
		$tasks         = $wpdb->prefix . 'agenpress_tasks';
		$audit         = $wpdb->prefix . 'agenpress_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tokens = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(tokens_used), 0) FROM {$messages} WHERE created_at >= %s",
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$messages_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$messages} WHERE created_at >= %s",
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conversations_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$conversations} WHERE created_at >= %s",
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tasks_completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tasks} WHERE status = 'completed' AND updated_at >= %s",
				$since
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tool_calls = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$audit} WHERE action = 'tool_executed' AND created_at >= %s",
				$since
			)
		);

		return array(
			'period_days'         => $days,
			'tokens_used'         => $tokens,
			'messages'            => $messages_count,
			'conversations'       => $conversations_count,
			'tasks_completed'     => $tasks_completed,
			'tool_executions'     => $tool_calls,
			'by_module'           => $this->conversations_by_module( $since ),
			'daily_messages'      => $this->daily_counts( $messages, 'created_at', $since ),
		);
	}

	/**
	 * Conversations grouped by module.
	 *
	 * @param string $since Since datetime.
	 * @return array<string, int>
	 */
	private function conversations_by_module( string $since ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'agenpress_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT module, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY module",
				$since
			),
			ARRAY_A
		);

		$result = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$result[ $row['module'] ] = (int) $row['total'];
			}
		}

		return $result;
	}

	/**
	 * Daily message counts for charting.
	 *
	 * @param string $table Table name.
	 * @param string $column Date column.
	 * @param string $since  Since datetime.
	 * @return array<int, array{date: string, count: int}>
	 */
	private function daily_counts( string $table, string $column, string $since ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE({$column}) AS day, COUNT(*) AS total FROM {$table} WHERE {$column} >= %s GROUP BY DATE({$column}) ORDER BY day ASC",
				$since
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'date'  => $row['day'],
					'count' => (int) $row['total'],
				);
			},
			$rows
		);
	}
}

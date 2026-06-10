<?php
/**
 * Sales overview for shop staff.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetSalesOverviewTool
 */
class GetSalesOverviewTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'get_sales_overview';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_sales_overview',
			'description' => 'Get WooCommerce sales summary: order count, revenue, and average order value for a period',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'period' => array(
						'type'        => 'string',
						'description' => 'Time period: week, month, year, or all',
						'enum'        => array( 'week', 'month', 'year', 'all' ),
					),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$denied = $this->require_shop_staff( $user_id );

		if ( null !== $denied ) {
			return $denied;
		}

		$period = sanitize_key( $args['period'] ?? 'month' );
		$query  = array(
			'limit'   => 1000,
			'status'  => wc_get_is_paid_statuses(),
			'return'  => 'objects',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$date_after = $this->period_start( $period );

		if ( $date_after ) {
			$query['date_after'] = $date_after;
		}

		$orders       = wc_get_orders( $query );
		$order_count  = count( $orders );
		$total_revenue = 0.0;

		foreach ( $orders as $order ) {
			$total_revenue += (float) $order->get_total();
		}

		$average = $order_count > 0 ? $total_revenue / $order_count : 0.0;

		return $this->success(
			array(
				'period'            => $period,
				'orders'            => $order_count,
				'revenue'           => wc_format_decimal( $total_revenue ),
				'average_order'     => wc_format_decimal( $average ),
				'currency'          => get_woocommerce_currency(),
				'orders_truncated'  => $order_count >= 1000,
			),
			sprintf(
				/* translators: 1: order count, 2: period */
				__( 'Sales overview: %1$d paid orders (%2$s).', 'agenpress' ),
				$order_count,
				$period
			)
		);
	}

	/**
	 * Get period start date.
	 *
	 * @param string $period Period slug.
	 * @return string
	 */
	private function period_start( string $period ): string {
		return match ( $period ) {
			'week'  => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
			'year'  => gmdate( 'Y-01-01' ),
			'month' => gmdate( 'Y-m-01' ),
			default => '',
		};
	}
}

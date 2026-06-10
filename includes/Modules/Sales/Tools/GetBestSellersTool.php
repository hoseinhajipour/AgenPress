<?php
/**
 * Best-selling products report for shop staff.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetBestSellersTool
 */
class GetBestSellersTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'get_best_sellers';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_best_sellers',
			'description' => 'Get best-selling products by quantity sold for a time period (shop staff analytics)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'period' => array(
						'type'        => 'string',
						'description' => 'Time period: week, month, year, or all',
						'enum'        => array( 'week', 'month', 'year', 'all' ),
					),
					'limit'  => array(
						'type'        => 'integer',
						'description' => 'Max products to return (default 10)',
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
		$limit  = min( 20, max( 1, (int) ( $args['limit'] ?? 10 ) ) );
		$stats  = $this->aggregate_product_sales( $period );

		if ( empty( $stats ) ) {
			return $this->success(
				array(),
				__( 'No paid orders found for this period.', 'agenpress' )
			);
		}

		uasort(
			$stats,
			static function ( array $a, array $b ): int {
				return $b['quantity'] <=> $a['quantity'];
			}
		);

		$data = array();
		$rank = 1;

		foreach ( array_slice( $stats, 0, $limit, true ) as $product_id => $row ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$data[] = array(
				'rank'      => $rank++,
				'id'        => $product_id,
				'name'      => $product->get_name(),
				'sku'       => $product->get_sku(),
				'quantity'  => $row['quantity'],
				'revenue'   => wc_format_decimal( $row['revenue'] ),
				'currency'  => get_woocommerce_currency(),
				'url'       => get_permalink( $product_id ),
				'stock'     => $product->get_stock_quantity(),
				'in_stock'  => $product->is_in_stock(),
			);
		}

		return $this->success(
			array(
				'period'   => $period,
				'products' => $data,
			),
			sprintf(
				/* translators: %d: number of products */
				__( 'Top %d best sellers for the selected period.', 'agenpress' ),
				count( $data )
			)
		);
	}

	/**
	 * Aggregate sold quantities and revenue per product.
	 *
	 * @param string $period Period slug.
	 * @return array<int, array{quantity: int, revenue: float}>
	 */
	private function aggregate_product_sales( string $period ): array {
		$query_args = array(
			'limit'   => 1000,
			'status'  => wc_get_is_paid_statuses(),
			'return'  => 'objects',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$date_after = $this->period_start( $period );

		if ( $date_after ) {
			$query_args['date_after'] = $date_after;
		}

		$orders = wc_get_orders( $query_args );
		$stats  = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = (int) $item->get_product_id();

				if ( $product_id <= 0 ) {
					continue;
				}

				if ( ! isset( $stats[ $product_id ] ) ) {
					$stats[ $product_id ] = array(
						'quantity' => 0,
						'revenue'  => 0.0,
					);
				}

				$stats[ $product_id ]['quantity'] += (int) $item->get_quantity();
				$stats[ $product_id ]['revenue']  += (float) $item->get_total();
			}
		}

		return $stats;
	}

	/**
	 * Get period start date in site timezone.
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

<?php
/**
 * List orders for the logged-in customer.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetMyOrdersTool
 */
class GetMyOrdersTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'get_my_orders';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_my_orders',
			'description' => 'List recent orders for the logged-in customer',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array( 'type' => 'integer', 'description' => 'Max results (default 5)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( $user_id <= 0 ) {
			return $this->fail( __( 'Please log in to view your orders, or provide order number and email.', 'agenpress' ) );
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => min( 10, (int) ( $args['limit'] ?? 5 ) ),
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$data = array_map( array( $this, 'format_order' ), $orders );

		return $this->success( $data, sprintf( __( 'Found %d orders.', 'agenpress' ), count( $data ) ) );
	}

	/**
	 * Format order for output.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	private function format_order( \WC_Order $order ): array {
		return array(
			'id'     => $order->get_id(),
			'number' => $order->get_order_number(),
			'status' => $order->get_status(),
			'total'  => $order->get_total(),
			'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
			'items'  => array_map(
				static function ( $item ) {
					return $item->get_name();
				},
				array_values( $order->get_items() )
			),
		);
	}
}

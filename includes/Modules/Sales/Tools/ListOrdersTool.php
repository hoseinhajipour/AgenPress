<?php
/**
 * List WooCommerce orders for shop staff.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListOrdersTool
 */
class ListOrdersTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'list_orders';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'list_orders',
			'description' => 'List recent WooCommerce orders with status, customer, and totals (shop staff)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'limit'  => array(
						'type'        => 'integer',
						'description' => 'Max orders (default 10)',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Order status filter: any, processing, completed, pending, on-hold, cancelled',
					),
					'period' => array(
						'type'        => 'string',
						'description' => 'Optional period: week, month, year',
						'enum'        => array( 'week', 'month', 'year' ),
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

		$limit  = min( 30, max( 1, (int) ( $args['limit'] ?? 10 ) ) );
		$status = sanitize_key( $args['status'] ?? 'any' );
		$query  = array(
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		);

		if ( 'any' !== $status ) {
			$query['status'] = $status;
		}

		$period = sanitize_key( $args['period'] ?? '' );

		if ( $period ) {
			$date_after = match ( $period ) {
				'week'  => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
				'year'  => gmdate( 'Y-01-01' ),
				default => gmdate( 'Y-m-01' ),
			};
			$query['date_after'] = $date_after;
		}

		$orders = wc_get_orders( $query );
		$data   = array();

		foreach ( $orders as $order ) {
			$data[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'status'   => $order->get_status(),
				'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i' ) : '',
				'customer' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email'    => $order->get_billing_email(),
				'total'    => wc_format_decimal( (float) $order->get_total() ),
				'currency' => $order->get_currency(),
				'items'    => $order->get_item_count(),
				'url'      => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
			);
		}

		return $this->success(
			$data,
			sprintf(
				/* translators: %d: order count */
				__( 'Found %d orders.', 'agenpress' ),
				count( $data )
			)
		);
	}
}

<?php
/**
 * Look up order status by number and email.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetOrderStatusTool
 */
class GetOrderStatusTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'get_order_status';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_order_status',
			'description' => 'Look up order status by order number and billing email (for guests)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'order_number' => array( 'type' => 'string', 'description' => 'Order number' ),
					'email'        => array( 'type' => 'string', 'description' => 'Billing email on the order' ),
				),
				'required'   => array( 'order_number', 'email' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$order_number = sanitize_text_field( $args['order_number'] ?? '' );
		$email        = sanitize_email( $args['email'] ?? '' );

		if ( '' === $order_number || '' === $email ) {
			return $this->fail( __( 'Order number and email are required.', 'agenpress' ) );
		}

		$order_id = wc_get_order_id_by_order_key( $order_number );

		if ( ! $order_id ) {
			$orders = wc_get_orders(
				array(
					'limit'       => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
					'meta_key'    => '_order_number',
					'meta_value'  => $order_number,
				)
			);

			if ( empty( $orders ) ) {
				$order = wc_get_order( (int) $order_number );
			} else {
				$order = $orders[0];
			}
		} else {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( (int) $order_number );
		}

		if ( ! $order ) {
			return $this->fail( __( 'Order not found.', 'agenpress' ) );
		}

		if ( 0 !== strcasecmp( $order->get_billing_email(), $email ) ) {
			return $this->fail( __( 'Order not found for that email.', 'agenpress' ) );
		}

		if ( $user_id > 0 && (int) $order->get_customer_id() !== $user_id ) {
			return $this->fail( __( 'Order not found.', 'agenpress' ) );
		}

		return $this->success(
			array(
				'number' => $order->get_order_number(),
				'status' => $order->get_status(),
				'total'  => $order->get_total(),
				'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
			),
			sprintf(
				/* translators: %s: order status */
				__( 'Order status: %s', 'agenpress' ),
				$order->get_status()
			)
		);
	}
}

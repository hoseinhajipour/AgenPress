<?php
/**
 * Get current WooCommerce cart summary.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetCartSummaryTool
 */
class GetCartSummaryTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'get_cart_summary';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_cart_summary',
			'description' => 'Get the current shopping cart contents and totals for this visitor',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
			return $this->fail( __( 'Cart is not available.', 'agenpress' ) );
		}

		$items = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product instanceof \WC_Product ) {
				$items[] = array(
					'product_id' => $product->get_id(),
					'name'       => $product->get_name(),
					'quantity'   => (int) ( $item['quantity'] ?? 1 ),
					'line_total' => wc_format_decimal( (float) ( $item['line_total'] ?? 0 ) ),
				);
			}
		}

		return $this->success(
			array(
				'items'    => $items,
				'subtotal' => WC()->cart->get_subtotal(),
				'total'    => WC()->cart->get_total( 'edit' ),
				'currency' => get_woocommerce_currency(),
				'count'    => WC()->cart->get_cart_contents_count(),
			),
			empty( $items ) ? __( 'Cart is empty.', 'agenpress' ) : __( 'Cart summary retrieved.', 'agenpress' )
		);
	}
}

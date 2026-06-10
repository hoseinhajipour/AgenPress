<?php
/**
 * Base class for sales module tools.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class SalesAbstractTool
 */
abstract class SalesAbstractTool extends AbstractTool {

	/**
	 * {@inheritdoc}
	 */
	public function get_module(): string {
		return 'sales';
	}

	/**
	 * Format a product for customer-facing output.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	protected function format_product( \WC_Product $product ): array {
		return array(
			'id'          => $product->get_id(),
			'name'        => $product->get_name(),
			'price'       => $product->get_price(),
			'currency'    => get_woocommerce_currency(),
			'on_sale'     => $product->is_on_sale(),
			'in_stock'    => $product->is_in_stock(),
			'url'         => get_permalink( $product->get_id() ),
			'short_description' => wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 30 ),
		);
	}
}

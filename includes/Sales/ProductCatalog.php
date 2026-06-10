<?php
/**
 * Product catalog context for sales AI prompts.
 *
 * @package AgenPress
 */

namespace AgenPress\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductCatalog
 */
class ProductCatalog {

	/**
	 * Build a compact product knowledge string for AI prompts.
	 *
	 * @param int $limit Max products.
	 * @return string
	 */
	public function get_context_string( int $limit = 15 ): string {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$products = wc_get_products(
			array(
				'limit'   => $limit,
				'status'  => 'publish',
				'orderby' => 'popularity',
				'order'   => 'DESC',
			)
		);

		if ( empty( $products ) ) {
			return '';
		}

		$lines = array( 'Product Catalog (bestsellers):' );

		foreach ( $products as $product ) {
			$lines[] = sprintf(
				'- %s (ID: %d, %s %s) — %s',
				$product->get_name(),
				$product->get_id(),
				$product->get_price(),
				get_woocommerce_currency(),
				wp_trim_words( wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ), 20 )
			);
		}

		return implode( "\n", $lines );
	}
}

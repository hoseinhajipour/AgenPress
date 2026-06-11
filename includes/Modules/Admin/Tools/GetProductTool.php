<?php
/**
 * Get a WooCommerce product.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;
use AgenPress\Content\RankMathSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetProductTool
 */
class GetProductTool extends AbstractTool {

	public function get_name(): string {
		return 'get_product';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_product',
			'description' => 'Get full details of a WooCommerce product',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'product_id' => array( 'type' => 'integer', 'description' => 'Product ID' ),
				),
				'required'   => array( 'product_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->fail( __( 'WooCommerce is not active.', 'agenpress' ) );
		}

		if ( ! $this->user_can( $user_id, 'edit_products' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$product = wc_get_product( (int) ( $args['product_id'] ?? 0 ) );

		if ( ! $product ) {
			return $this->fail( __( 'Product not found.', 'agenpress' ) );
		}

		$data = array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'status'            => $product->get_status(),
			'stock'             => $product->get_stock_quantity(),
			'categories'        => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
		);

		if ( RankMathSeo::is_active() ) {
			$data['seo'] = RankMathSeo::get_for_post( $product->get_id() );
		}

		return $this->success( $data, __( 'Product retrieved.', 'agenpress' ) );
	}
}

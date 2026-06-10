<?php
/**
 * List WooCommerce products.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListProductsTool
 */
class ListProductsTool extends AbstractTool {

	public function get_name(): string {
		return 'list_products';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'list_products',
			'description' => 'List WooCommerce products',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'limit'  => array( 'type' => 'integer', 'description' => 'Max results (default 20)' ),
					'search' => array( 'type' => 'string', 'description' => 'Search product name' ),
					'status' => array( 'type' => 'string', 'description' => 'publish, draft, or any' ),
				),
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

		$query = new \WC_Product_Query(
			array(
				'limit'   => min( 50, (int) ( $args['limit'] ?? 20 ) ),
				'status'  => sanitize_key( $args['status'] ?? 'publish' ),
				'search'  => sanitize_text_field( $args['search'] ?? '' ),
				'return'  => 'objects',
			)
		);

		$products = $query->get_products();
		$data     = array();

		foreach ( $products as $product ) {
			$data[] = $this->format_product( $product );
		}

		return $this->success( $data, sprintf( __( 'Found %d products.', 'agenpress' ), count( $data ) ) );
	}

	/**
	 * Format a WooCommerce product.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	private function format_product( \WC_Product $product ): array {
		return array(
			'id'          => $product->get_id(),
			'name'        => $product->get_name(),
			'sku'         => $product->get_sku(),
			'price'       => $product->get_price(),
			'status'      => $product->get_status(),
			'stock'       => $product->get_stock_quantity(),
			'type'        => $product->get_type(),
			'url'         => get_permalink( $product->get_id() ),
		);
	}
}

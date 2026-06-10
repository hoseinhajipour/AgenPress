<?php
/**
 * Get published product details.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetProductDetailsTool
 */
class GetProductDetailsTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'get_product_details';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_product_details',
			'description' => 'Get details for a published WooCommerce product',
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

		$product = wc_get_product( (int) ( $args['product_id'] ?? 0 ) );

		if ( ! $product || 'publish' !== $product->get_status() ) {
			return $this->fail( __( 'Product not found.', 'agenpress' ) );
		}

		$data = $this->format_product( $product );
		$data['description'] = wp_trim_words( wp_strip_all_tags( $product->get_description() ), 80 );
		$data['categories']  = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );

		return $this->success( $data, __( 'Product details retrieved.', 'agenpress' ) );
	}
}

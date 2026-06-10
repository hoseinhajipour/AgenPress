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
			'description' => 'Get full details for a published WooCommerce product including colors, sizes, and other attributes/variations',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'product_id' => array( 'type' => 'integer', 'description' => 'Product ID (optional if search is provided)' ),
					'search'     => array( 'type' => 'string', 'description' => 'Product name, model, or keyword (optional if product_id is provided)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->fail( __( 'WooCommerce is not active.', 'agenpress' ) );
		}

		$product_id = (int) ( $args['product_id'] ?? 0 );
		$search     = sanitize_text_field( $args['search'] ?? '' );
		$product    = $this->find_product( $product_id, $search );

		if ( ! $product ) {
			return $this->fail( __( 'Product not found.', 'agenpress' ) );
		}

		$data = $this->format_product( $product );
		$data['description'] = wp_trim_words( wp_strip_all_tags( $product->get_description() ), 80 );
		$data['categories']  = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );

		return $this->success( $data, __( 'Product details retrieved.', 'agenpress' ) );
	}
}

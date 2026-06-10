<?php
/**
 * Recommend WooCommerce products.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class RecommendProductsTool
 */
class RecommendProductsTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'recommend_products';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'recommend_products',
			'description' => 'Get product recommendations by category, bestsellers, or related to a product',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'product_id' => array( 'type' => 'integer', 'description' => 'Related product ID (optional)' ),
					'category'   => array( 'type' => 'string', 'description' => 'Category slug (optional)' ),
					'limit'      => array( 'type' => 'integer', 'description' => 'Max results (default 5)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->fail( __( 'WooCommerce is not active.', 'agenpress' ) );
		}

		$limit      = min( 10, (int) ( $args['limit'] ?? 5 ) );
		$product_id = (int) ( $args['product_id'] ?? 0 );
		$products   = array();

		if ( $product_id ) {
			$related_ids = wc_get_related_products( $product_id, $limit );
			foreach ( $related_ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product && 'publish' === $product->get_status() ) {
					$products[] = $product;
				}
			}
		} elseif ( ! empty( $args['category'] ) ) {
			$products = wc_get_products(
				array(
					'limit'    => $limit,
					'status'   => 'publish',
					'category' => array( sanitize_title( $args['category'] ) ),
				)
			);
		} else {
			$products = wc_get_products(
				array(
					'limit'   => $limit,
					'status'  => 'publish',
					'orderby' => 'popularity',
					'order'   => 'DESC',
				)
			);
		}

		$data = array_map( array( $this, 'format_product' ), $products );

		return $this->success( $data, sprintf( __( '%d recommendations.', 'agenpress' ), count( $data ) ) );
	}
}

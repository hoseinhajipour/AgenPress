<?php
/**
 * Update a WooCommerce product.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class UpdateProductTool
 */
class UpdateProductTool extends AbstractTool {

	public function get_name(): string {
		return 'update_product';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'update_product',
			'description' => 'Update an existing WooCommerce product',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'product_id'        => array( 'type' => 'integer', 'description' => 'Product ID' ),
					'name'              => array( 'type' => 'string' ),
					'description'       => array( 'type' => 'string' ),
					'short_description' => array( 'type' => 'string' ),
					'regular_price'     => array( 'type' => 'string' ),
					'sale_price'        => array( 'type' => 'string' ),
					'status'            => array( 'type' => 'string' ),
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

		if ( isset( $args['name'] ) ) {
			$product->set_name( sanitize_text_field( $args['name'] ) );
		}
		if ( isset( $args['description'] ) ) {
			$product->set_description( wp_kses_post( $args['description'] ) );
		}
		if ( isset( $args['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $args['short_description'] ) );
		}
		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $args['regular_price'] ) );
		}
		if ( isset( $args['sale_price'] ) ) {
			$product->set_sale_price( sanitize_text_field( $args['sale_price'] ) );
		}
		if ( isset( $args['status'] ) ) {
			$product->set_status( sanitize_key( $args['status'] ) );
		}

		$product->save();

		return $this->success(
			array( 'id' => $product->get_id(), 'name' => $product->get_name() ),
			__( 'Product updated.', 'agenpress' )
		);
	}
}

<?php
/**
 * Create a WooCommerce product.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class CreateProductTool
 */
class CreateProductTool extends AbstractTool {

	public function get_name(): string {
		return 'create_product';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'create_product',
			'description' => 'Create a new WooCommerce simple product',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'name'              => array( 'type' => 'string', 'description' => 'Product name' ),
					'description'       => array( 'type' => 'string', 'description' => 'Full description' ),
					'short_description' => array( 'type' => 'string', 'description' => 'Short description' ),
					'regular_price'     => array( 'type' => 'string', 'description' => 'Regular price' ),
					'sku'               => array( 'type' => 'string', 'description' => 'SKU' ),
					'status'            => array( 'type' => 'string', 'description' => 'draft or publish' ),
				),
				'required'   => array( 'name' ),
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

		$product = new \WC_Product_Simple();
		$product->set_name( sanitize_text_field( $args['name'] ?? '' ) );

		if ( isset( $args['description'] ) ) {
			$product->set_description( wp_kses_post( $args['description'] ) );
		}
		if ( isset( $args['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $args['short_description'] ) );
		}
		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( sanitize_text_field( $args['regular_price'] ) );
		}
		if ( isset( $args['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $args['sku'] ) );
		}

		$product->set_status( sanitize_key( $args['status'] ?? 'draft' ) );
		$product_id = $product->save();

		if ( ! $product_id ) {
			return $this->fail( __( 'Failed to create product.', 'agenpress' ) );
		}

		return $this->success(
			array( 'id' => $product_id, 'name' => $product->get_name() ),
			sprintf( __( 'Created product "%s".', 'agenpress' ), $product->get_name() )
		);
	}
}

<?php
/**
 * Delete a WooCommerce product.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeleteProductTool
 */
class DeleteProductTool extends AbstractTool {

	public function get_name(): string {
		return 'delete_product';
	}

	public function requires_confirmation(): bool {
		return true;
	}

	public function get_confirmation_message( array $args ): string {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return __( 'Delete this product? This cannot be undone.', 'agenpress' );
		}

		$product = wc_get_product( (int) ( $args['product_id'] ?? 0 ) );

		if ( $product ) {
			return sprintf(
				/* translators: %s: product name */
				__( 'Delete product "%s"? This cannot be undone.', 'agenpress' ),
				$product->get_name()
			);
		}

		return __( 'Delete this product? This cannot be undone.', 'agenpress' );
	}

	public function get_schema(): array {
		return array(
			'name'        => 'delete_product',
			'description' => 'Permanently delete a WooCommerce product',
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

		if ( ! $this->user_can( $user_id, 'delete_products' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$product_id = (int) ( $args['product_id'] ?? 0 );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->fail( __( 'Product not found.', 'agenpress' ) );
		}

		$name   = $product->get_name();
		$result = wp_delete_post( $product_id, true );

		if ( ! $result ) {
			return $this->fail( __( 'Failed to delete product.', 'agenpress' ) );
		}

		return $this->success(
			array( 'deleted_id' => $product_id ),
			sprintf( __( 'Deleted product "%s".', 'agenpress' ), $name )
		);
	}
}

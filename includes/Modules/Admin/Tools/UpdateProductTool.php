<?php
/**
 * Update a WooCommerce product.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;
use AgenPress\Content\RankMathSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Class UpdateProductTool
 */
class UpdateProductTool extends AbstractTool {

	public function get_name(): string {
		return 'update_product';
	}

	public function get_schema(): array {
		$properties = array(
			'product_id'        => array( 'type' => 'integer', 'description' => 'Product ID' ),
			'name'              => array( 'type' => 'string', 'description' => 'Product title' ),
			'description'       => array(
				'type'        => 'string',
				'description' => 'Full product description (HTML). This is the main Description tab in WooCommerce — write comprehensive SEO copy here, not just a short blurb.',
			),
			'short_description' => array(
				'type'        => 'string',
				'description' => 'Short excerpt shown near the price (1-2 sentences).',
			),
			'regular_price'     => array( 'type' => 'string' ),
			'sale_price'        => array( 'type' => 'string' ),
			'status'            => array( 'type' => 'string' ),
		);

		if ( RankMathSeo::is_active() ) {
			$properties['focus_keyword']   = array(
				'type'        => 'string',
				'description' => 'Rank Math focus keyword (primary SEO keyword).',
			);
			$properties['seo_title']       = array(
				'type'        => 'string',
				'description' => 'Rank Math SEO title (~60 characters).',
			);
			$properties['seo_description'] = array(
				'type'        => 'string',
				'description' => 'Rank Math meta description (~160 characters).',
			);
		}

		return array(
			'name'        => 'update_product',
			'description' => 'Update an existing WooCommerce product including full description and Rank Math SEO fields when available',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => $properties,
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

		$product_id = $product->get_id();
		$seo_saved  = RankMathSeo::save_for_post(
			$product_id,
			array(
				'focus_keyword'   => $args['focus_keyword'] ?? null,
				'seo_title'       => $args['seo_title'] ?? null,
				'seo_description' => $args['seo_description'] ?? null,
			)
		);

		$data = array(
			'id'   => $product_id,
			'name' => $product->get_name(),
		);

		if ( RankMathSeo::is_active() ) {
			$data['seo'] = RankMathSeo::get_for_post( $product_id );
		}

		$message = __( 'Product updated.', 'agenpress' );

		if ( $seo_saved ) {
			$message = __( 'Product and Rank Math SEO settings updated.', 'agenpress' );
		}

		return $this->success( $data, $message );
	}
}

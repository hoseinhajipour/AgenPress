<?php
/**
 * Base class for sales module tools.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class SalesAbstractTool
 */
abstract class SalesAbstractTool extends AbstractTool {

	/**
	 * {@inheritdoc}
	 */
	public function get_module(): string {
		return 'sales';
	}

	/**
	 * Require shop staff capabilities for analytics tools.
	 *
	 * @param int $user_id User ID.
	 * @return array{success: bool, data: null, message: string}|null
	 */
	protected function require_shop_staff( int $user_id ): ?array {
		if (
			! user_can( $user_id, 'manage_woocommerce' )
			&& ! user_can( $user_id, 'view_woocommerce_reports' )
			&& ! user_can( $user_id, 'edit_shop_orders' )
		) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		return null;
	}

	/**
	 * Format a product for customer-facing output.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	protected function format_product( \WC_Product $product ): array {
		$attributes = $this->format_product_attributes( $product );

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'type'              => $product->get_type(),
			'price'             => $product->get_price(),
			'currency'          => get_woocommerce_currency(),
			'on_sale'           => $product->is_on_sale(),
			'in_stock'          => $product->is_in_stock(),
			'url'               => get_permalink( $product->get_id() ),
			'slug'              => $product->get_slug(),
			'image'             => $this->get_product_image_url( $product ),
			'short_description' => wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 30 ),
			'attributes'        => $attributes,
			'colors'            => $this->extract_attribute_values( $attributes, array( 'color', 'colour', 'رنگ' ) ),
			'sizes'             => $this->extract_attribute_values( $attributes, array( 'size', 'سایز', 'اندازه' ) ),
		);
	}

	/**
	 * Format WooCommerce product attributes and variation options.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<int, array{name: string, slug: string, options: array<int, string>}>
	 */
	protected function format_product_attributes( \WC_Product $product ): array {
		$attributes = array();

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_variation_attributes() as $attribute_name => $values ) {
				$taxonomy = str_replace( 'attribute_', '', $attribute_name );
				$label    = wc_attribute_label( $taxonomy, $product );
				$options  = array();

				foreach ( $values as $value ) {
					$options[] = $this->resolve_attribute_option_label( $taxonomy, (string) $value );
				}

				$attributes[] = array(
					'name'    => $label,
					'slug'    => $taxonomy,
					'options' => array_values( array_unique( array_filter( $options ) ) ),
				);
			}
		}

		foreach ( $product->get_attributes() as $attribute ) {
			$name = $attribute->get_name();
			$slug = $attribute->is_taxonomy() ? $name : sanitize_title( $name );

			if ( $this->attribute_exists( $attributes, $slug ) ) {
				continue;
			}

			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms(
					$product->get_id(),
					$name,
					array(
						'fields' => 'names',
					)
				);
				$options = is_array( $terms ) ? $terms : array();
			} else {
				$options = $attribute->get_options();
			}

			$attributes[] = array(
				'name'    => wc_attribute_label( $name, $product ),
				'slug'    => $slug,
				'options' => array_values( array_unique( array_map( 'strval', $options ) ) ),
			);
		}

		return $attributes;
	}

	/**
	 * Resolve a human-readable attribute option label.
	 *
	 * @param string $taxonomy Taxonomy or attribute slug.
	 * @param string $value    Raw option value.
	 * @return string
	 */
	protected function resolve_attribute_option_label( string $taxonomy, string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );

			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		return $value;
	}

	/**
	 * Extract attribute values by matching common slugs or labels.
	 *
	 * @param array<int, array{name: string, slug: string, options: array<int, string>}> $attributes Attributes.
	 * @param array<int, string>                                                          $needles    Match needles.
	 * @return array<int, string>
	 */
	protected function extract_attribute_values( array $attributes, array $needles ): array {
		$values = array();

		foreach ( $attributes as $attribute ) {
			$slug  = strtolower( (string) ( $attribute['slug'] ?? '' ) );
			$name  = strtolower( (string) ( $attribute['name'] ?? '' ) );
			$match = false;

			foreach ( $needles as $needle ) {
				$needle = strtolower( $needle );
				if ( str_contains( $slug, $needle ) || str_contains( $name, $needle ) ) {
					$match = true;
					break;
				}
			}

			if ( $match && ! empty( $attribute['options'] ) ) {
				$values = array_merge( $values, $attribute['options'] );
			}
		}

		return array_values( array_unique( array_filter( $values ) ) );
	}

	/**
	 * Check if an attribute slug is already included.
	 *
	 * @param array<int, array{name: string, slug: string, options: array<int, string>}> $attributes Attributes.
	 * @param string                                                                       $slug       Attribute slug.
	 * @return bool
	 */
	private function attribute_exists( array $attributes, string $slug ): bool {
		foreach ( $attributes as $attribute ) {
			if ( ( $attribute['slug'] ?? '' ) === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find a published product by ID, search term, or slug.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $search     Search term or slug.
	 * @return \WC_Product|null
	 */
	protected function find_product( int $product_id, string $search = '' ): ?\WC_Product {
		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );

			return ( $product && 'publish' === $product->get_status() ) ? $product : null;
		}

		$search = trim( $search );

		if ( '' === $search ) {
			return null;
		}

		$by_slug = get_page_by_path( sanitize_title( $search ), OBJECT, 'product' );

		if ( $by_slug instanceof \WP_Post ) {
			$product = wc_get_product( $by_slug->ID );

			if ( $product && 'publish' === $product->get_status() ) {
				return $product;
			}
		}

		$query = new \WC_Product_Query(
			array(
				'limit'  => 1,
				'status' => 'publish',
				'search' => $search,
				'return' => 'objects',
			)
		);

		$products = $query->get_products();

		return ! empty( $products ) ? $products[0] : null;
	}

	/**
	 * Get featured image URL for a product.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	protected function get_product_image_url( \WC_Product $product ): string {
		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );

		return $url ? $url : '';
	}
}

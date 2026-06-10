<?php
/**
 * Fix broken or hallucinated WooCommerce product URLs in sales chat replies.
 *
 * @package AgenPress
 */

namespace AgenPress\Sales;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductLinkFixer
 */
class ProductLinkFixer {

	/**
	 * Replace invalid product URLs with canonical permalinks.
	 *
	 * @param string $content Assistant message content.
	 * @return string
	 */
	public function fix( string $content ): string {
		if ( '' === trim( $content ) || ! class_exists( 'WooCommerce' ) ) {
			return $content;
		}

		$pattern = '#https?://[^\s<>"\'\)\]]+#iu';

		return (string) preg_replace_callback(
			$pattern,
			function ( array $match ): string {
				return $this->fix_url( $match[0] );
			},
			$content
		);
	}

	/**
	 * Resolve a single product URL to its canonical permalink.
	 *
	 * @param string $url Product URL candidate.
	 * @return string
	 */
	private function fix_url( string $url ): string {
		$url = rtrim( $url, '.,;:!?)' );

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$slug = $this->extract_product_slug( (string) $parts['path'] );

		if ( '' === $slug ) {
			return $url;
		}

		$product_id = $this->resolve_product_id_by_slug( $slug );

		if ( $product_id <= 0 ) {
			return $url;
		}

		$canonical = get_permalink( $product_id );

		return is_string( $canonical ) && '' !== $canonical ? $canonical : $url;
	}

	/**
	 * Extract product slug from a URL path.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private function extract_product_slug( string $path ): string {
		$path = trim( $path, '/' );

		if ( preg_match( '#/product/([^/]+)/?$#iu', '/' . $path . '/', $match ) ) {
			return rawurldecode( (string) $match[1] );
		}

		if ( str_contains( $path, 'product/' ) ) {
			$segments = explode( '/', $path );
			$index    = array_search( 'product', $segments, true );

			if ( false !== $index && isset( $segments[ $index + 1 ] ) ) {
				return rawurldecode( (string) $segments[ $index + 1 ] );
			}
		}

		return '';
	}

	/**
	 * Find a published product ID for a slug (exact or prefix match).
	 *
	 * @param string $slug Product slug from URL.
	 * @return int
	 */
	private function resolve_product_id_by_slug( string $slug ): int {
		$slug = trim( $slug );

		if ( '' === $slug ) {
			return 0;
		}

		$exact = $this->get_product_id_by_slug( $slug );

		if ( $exact > 0 ) {
			return $exact;
		}

		return $this->get_product_id_by_slug_prefix( $slug );
	}

	/**
	 * Look up product by exact post_name slug.
	 *
	 * @param string $slug Product slug.
	 * @return int
	 */
	private function get_product_id_by_slug( string $slug ): int {
		$post = get_page_by_path( $slug, OBJECT, 'product' );

		if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
			return (int) $post->ID;
		}

		$products = wc_get_products(
			array(
				'limit'  => 1,
				'status' => 'publish',
				'slug'   => $slug,
				'return' => 'ids',
			)
		);

		return ! empty( $products ) ? (int) $products[0] : 0;
	}

	/**
	 * Find product when the URL slug is a corrupted extension of the real slug.
	 *
	 * @param string $slug Slug from a possibly broken URL.
	 * @return int
	 */
	private function get_product_id_by_slug_prefix( string $slug ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product'
				AND post_status = 'publish'
				AND %s LIKE CONCAT(post_name, '%%')
				ORDER BY CHAR_LENGTH(post_name) DESC
				LIMIT 1",
				$slug
			)
		);

		return $product_id ? (int) $product_id : 0;
	}
}

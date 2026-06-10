<?php
/**
 * Convert bare URLs in assistant replies to titled markdown links.
 *
 * @package AgenPress
 */

namespace AgenPress\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class MarkdownLinkFormatter
 */
class MarkdownLinkFormatter {

	/**
	 * Replace standalone URLs with [title](url) when a title can be resolved.
	 *
	 * @param string $content Assistant message content.
	 * @return string
	 */
	public function format( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/(?<!\]\()https?:\/\/[^\s<>"\'\)\]]+/iu',
			function ( array $match ): string {
				$url   = rtrim( $match[0], '.,;:!?)' );
				$title = $this->title_for_url( $url );

				if ( '' === $title ) {
					return $match[0];
				}

				return '[' . $this->sanitize_label( $title ) . '](' . $url . ')';
			},
			$content
		);
	}

	/**
	 * Resolve a human-readable title for a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function title_for_url( string $url ): string {
		$post_id = url_to_postid( $url );

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				return $post->post_title;
			}
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return '';
		}

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );

			if ( ! empty( $query['post'] ) ) {
				$post = get_post( (int) $query['post'] );

				if ( $post instanceof \WP_Post ) {
					return $post->post_title;
				}
			}
		}

		if ( ! empty( $parts['path'] ) && class_exists( 'WooCommerce' ) ) {
			$slug = $this->extract_product_slug( (string) $parts['path'] );

			if ( '' !== $slug ) {
				$post = get_page_by_path( $slug, OBJECT, 'product' );

				if ( $post instanceof \WP_Post ) {
					return $post->post_title;
				}
			}
		}

		return $this->title_from_path( $parts );
	}

	/**
	 * Build a short label from the URL path slug.
	 *
	 * @param array<string, mixed> $parts Parsed URL parts.
	 * @return string
	 */
	private function title_from_path( array $parts ): string {
		$path = trim( (string) ( $parts['path'] ?? '' ), '/' );

		if ( '' === $path ) {
			return '';
		}

		$segments = array_values( array_filter( explode( '/', $path ) ) );
		$slug     = (string) ( $segments[ count( $segments ) - 1 ] ?? '' );

		if ( '' === $slug || is_numeric( $slug ) ) {
			return '';
		}

		$slug = rawurldecode( $slug );
		$slug = str_replace( array( '-', '_' ), ' ', $slug );

		return ucwords( $slug );
	}

	/**
	 * Extract WooCommerce product slug from a path.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private function extract_product_slug( string $path ): string {
		$path = trim( $path, '/' );

		if ( preg_match( '#/product/([^/]+)/?$#iu', '/' . $path . '/', $match ) ) {
			return rawurldecode( (string) $match[1] );
		}

		return '';
	}

	/**
	 * Strip characters that break markdown link labels.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private function sanitize_label( string $title ): string {
		$title = wp_strip_all_tags( $title );
		$title = str_replace( array( '[', ']' ), '', $title );

		return trim( $title );
	}
}

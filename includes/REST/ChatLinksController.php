<?php
/**
 * Internal link search for admin chat attachments.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatLinksController
 */
class ChatLinksController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/chat/links/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_links' ),
				'permission_callback' => array( $this, 'can_search' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'   => array(
						'type'              => 'string',
						'default'           => 'all',
						'sanitize_callback' => 'sanitize_key',
					),
					'limit'  => array(
						'type'              => 'integer',
						'default'           => 15,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_search(): bool {
		return current_user_can( Capabilities::USE_ADMIN_AI );
	}

	/**
	 * GET /chat/links/search
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function search_links( \WP_REST_Request $request ): \WP_REST_Response {
		$search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$type   = sanitize_key( $request->get_param( 'type' ) ?? 'all' );
		$limit  = min( 25, max( 1, (int) ( $request->get_param( 'limit' ) ?? 15 ) ) );

		$post_types = $this->resolve_post_types( $type );

		if ( empty( $post_types ) ) {
			return $this->success( array() );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page'         => $limit,
				's'                      => $search,
				'orderby'                => '' === $search ? 'modified' : 'relevance',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$items[] = $this->format_link_item( $post );
		}

		return $this->success( $items );
	}

	/**
	 * Resolve post types for search filter.
	 *
	 * @param string $type Filter slug.
	 * @return array<int, string>
	 */
	private function resolve_post_types( string $type ): array {
		$allowed = array( 'post', 'page' );

		if ( class_exists( 'WooCommerce' ) ) {
			$allowed[] = 'product';
		}

		if ( 'all' === $type || '' === $type ) {
			return $allowed;
		}

		return in_array( $type, $allowed, true ) ? array( $type ) : array();
	}

	/**
	 * Format a post/product for the link picker.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function format_link_item( \WP_Post $post ): array {
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 );

		$item = array(
			'id'        => $post->ID,
			'post_id'   => $post->ID,
			'title'     => $post->post_title,
			'post_type' => $post->post_type,
			'status'    => $post->post_status,
			'url'       => (string) get_permalink( $post ),
			'excerpt'   => $excerpt,
			'edit_url'  => (string) get_edit_post_link( $post->ID, 'raw' ),
		);

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );

			if ( $product ) {
				$item['price'] = $product->get_price();
				$item['sku']   = $product->get_sku();
			}
		}

		return $item;
	}
}

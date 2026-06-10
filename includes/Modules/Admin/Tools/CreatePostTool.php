<?php
/**
 * Create a post or page.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class CreatePostTool
 */
class CreatePostTool extends AbstractTool {

	public function get_name(): string {
		return 'create_post';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'create_post',
			'description' => 'Create a new WordPress post or page',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'title'      => array( 'type' => 'string', 'description' => 'Post title' ),
					'content'    => array( 'type' => 'string', 'description' => 'Post HTML content' ),
					'post_type'  => array( 'type' => 'string', 'description' => 'post or page', 'enum' => array( 'post', 'page' ) ),
					'status'     => array( 'type' => 'string', 'description' => 'draft, publish, or pending' ),
					'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Category names' ),
					'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Tag names' ),
					'excerpt'    => array( 'type' => 'string', 'description' => 'Post excerpt' ),
				),
				'required'   => array( 'title', 'content' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'edit_posts' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$post_type = sanitize_key( $args['post_type'] ?? 'post' );
		$status    = sanitize_key( $args['status'] ?? 'draft' );

		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			$post_type = 'post';
		}

		if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
			$status = 'draft';
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $args['title'] ?? '' ),
				'post_content' => wp_kses_post( $args['content'] ?? '' ),
				'post_excerpt' => sanitize_textarea_field( $args['excerpt'] ?? '' ),
				'post_type'    => $post_type,
				'post_status'  => $status,
				'post_author'  => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $this->fail( $post_id->get_error_message() );
		}

		if ( 'post' === $post_type && ! empty( $args['categories'] ) && is_array( $args['categories'] ) ) {
			$cat_ids = array();
			foreach ( $args['categories'] as $cat_name ) {
				$term = get_term_by( 'name', $cat_name, 'category' );
				if ( ! $term ) {
					$result = wp_insert_term( $cat_name, 'category' );
					if ( ! is_wp_error( $result ) ) {
						$cat_ids[] = (int) $result['term_id'];
					}
				} else {
					$cat_ids[] = (int) $term->term_id;
				}
			}
			if ( $cat_ids ) {
				wp_set_post_categories( $post_id, $cat_ids );
			}
		}

		if ( 'post' === $post_type && ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $args['tags'] ) );
		}

		$post = get_post( $post_id );

		return $this->success(
			$this->format_post( $post ),
			sprintf(
				/* translators: %s: post title */
				__( 'Created "%s" successfully.', 'agenpress' ),
				$post->post_title
			)
		);
	}
}

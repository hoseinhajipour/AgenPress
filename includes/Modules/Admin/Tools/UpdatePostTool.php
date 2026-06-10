<?php
/**
 * Update a post or page.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class UpdatePostTool
 */
class UpdatePostTool extends AbstractTool {

	public function get_name(): string {
		return 'update_post';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'update_post',
			'description' => 'Update an existing WordPress post or page',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer', 'description' => 'Post ID' ),
					'title'      => array( 'type' => 'string', 'description' => 'New title' ),
					'content'    => array( 'type' => 'string', 'description' => 'New content' ),
					'status'     => array( 'type' => 'string', 'description' => 'draft, publish, pending' ),
					'excerpt'    => array( 'type' => 'string', 'description' => 'Post excerpt' ),
					'categories' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'tags'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
				'required'   => array( 'post_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'edit_posts' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$post_id = (int) ( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->fail( __( 'Post not found.', 'agenpress' ) );
		}

		$update = array( 'ID' => $post_id );

		if ( isset( $args['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$update['post_content'] = wp_kses_post( $args['content'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
		}
		if ( isset( $args['status'] ) ) {
			$update['post_status'] = sanitize_key( $args['status'] );
		}

		$result = wp_update_post( $update, true );

		if ( is_wp_error( $result ) ) {
			return $this->fail( $result->get_error_message() );
		}

		if ( ! empty( $args['categories'] ) && is_array( $args['categories'] ) ) {
			$cat_ids = array();
			foreach ( $args['categories'] as $cat_name ) {
				$term = get_term_by( 'name', $cat_name, 'category' );
				if ( $term ) {
					$cat_ids[] = (int) $term->term_id;
				}
			}
			if ( $cat_ids ) {
				wp_set_post_categories( $post_id, $cat_ids );
			}
		}

		if ( ! empty( $args['tags'] ) && is_array( $args['tags'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $args['tags'] ) );
		}

		return $this->success(
			$this->format_post( get_post( $post_id ) ),
			__( 'Post updated successfully.', 'agenpress' )
		);
	}
}

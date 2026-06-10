<?php
/**
 * Delete a post or page.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeletePostTool
 */
class DeletePostTool extends AbstractTool {

	public function get_name(): string {
		return 'delete_post';
	}

	public function requires_confirmation(): bool {
		return true;
	}

	public function get_confirmation_message( array $args ): string {
		$post = get_post( (int) ( $args['post_id'] ?? 0 ) );

		if ( $post ) {
			return sprintf(
				/* translators: 1: post title, 2: post type */
				__( 'Delete %2$s "%1$s"? This cannot be undone.', 'agenpress' ),
				$post->post_title,
				$post->post_type
			);
		}

		return __( 'Delete this post? This cannot be undone.', 'agenpress' );
	}

	public function get_schema(): array {
		return array(
			'name'        => 'delete_post',
			'description' => 'Permanently delete a WordPress post or page',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Post ID to delete' ),
				),
				'required'   => array( 'post_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'delete_posts' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$post_id = (int) ( $args['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->fail( __( 'Post not found.', 'agenpress' ) );
		}

		$title  = $post->post_title;
		$result = wp_delete_post( $post_id, true );

		if ( ! $result ) {
			return $this->fail( __( 'Failed to delete post.', 'agenpress' ) );
		}

		return $this->success(
			array( 'deleted_id' => $post_id ),
			sprintf(
				/* translators: %s: post title */
				__( 'Deleted "%s".', 'agenpress' ),
				$title
			)
		);
	}
}

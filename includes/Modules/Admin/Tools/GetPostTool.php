<?php
/**
 * Get a single post or page.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetPostTool
 */
class GetPostTool extends AbstractTool {

	public function get_name(): string {
		return 'get_post';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_post',
			'description' => 'Get a single WordPress post or page by ID including full content',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Post or page ID' ),
				),
				'required'   => array( 'post_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'edit_posts' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$post = get_post( (int) ( $args['post_id'] ?? 0 ) );

		if ( ! $post ) {
			return $this->fail( __( 'Post not found.', 'agenpress' ) );
		}

		$data               = $this->format_post( $post );
		$data['content']    = $post->post_content;
		$data['categories'] = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$data['tags']       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		return $this->success( $data, __( 'Post retrieved.', 'agenpress' ) );
	}
}

<?php
/**
 * List posts tool for Admin module.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListPostsTool
 */
class ListPostsTool extends AbstractTool {

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'list_posts';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema(): array {
		return array(
			'name'        => 'list_posts',
			'description' => 'List recent WordPress posts',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'limit'      => array(
						'type'        => 'integer',
						'description' => 'Number of posts to return (max 20)',
					),
					'post_type'  => array(
						'type'        => 'string',
						'description' => 'Post type slug',
					),
					'post_status' => array(
						'type'        => 'string',
						'description' => 'Post status',
					),
				),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'edit_posts' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$posts = get_posts(
			array(
				'numberposts' => min( 20, (int) ( $args['limit'] ?? 10 ) ),
				'post_type'   => sanitize_text_field( $args['post_type'] ?? 'post' ),
				'post_status' => sanitize_text_field( $args['post_status'] ?? 'any' ),
			)
		);

		$data = array_map(
			static function ( $post ) {
				return array(
					'id'      => $post->ID,
					'title'   => $post->post_title,
					'status'  => $post->post_status,
					'date'    => $post->post_date,
					'url'     => get_permalink( $post ),
				);
			},
			$posts
		);

		return $this->success( $data, sprintf( __( 'Found %d posts.', 'agenpress' ), count( $data ) ) );
	}
}

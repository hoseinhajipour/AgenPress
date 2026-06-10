<?php
/**
 * Update media library attachment metadata.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class UpdateMediaTool
 */
class UpdateMediaTool extends AbstractTool {

	public function get_name(): string {
		return 'update_media';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'update_media',
			'description' => 'Update a media library attachment title, alt text, or caption',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
					'title'         => array( 'type' => 'string', 'description' => 'Attachment title' ),
					'alt_text'      => array( 'type' => 'string', 'description' => 'Image alt text for accessibility and SEO' ),
					'caption'       => array( 'type' => 'string', 'description' => 'Image caption' ),
				),
				'required'   => array( 'attachment_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'upload_files' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$attachment_id = (int) ( $args['attachment_id'] ?? 0 );

		if ( ! get_post( $attachment_id ) ) {
			return $this->fail( __( 'Attachment not found.', 'agenpress' ) );
		}

		if ( isset( $args['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $attachment_id,
					'post_title' => sanitize_text_field( $args['title'] ),
				)
			);
		}

		if ( isset( $args['caption'] ) ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => sanitize_textarea_field( $args['caption'] ),
				)
			);
		}

		if ( isset( $args['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
		}

		return $this->success(
			array(
				'id'       => $attachment_id,
				'url'      => wp_get_attachment_url( $attachment_id ),
				'alt_text' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			),
			__( 'Media updated.', 'agenpress' )
		);
	}
}

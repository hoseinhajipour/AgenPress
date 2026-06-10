<?php
/**
 * Apply an uploaded media file to an Elementor element.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class ApplyMediaToElementTool
 */
class ApplyMediaToElementTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'apply_media_to_element';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'apply_media_to_element',
			'description' => 'Set an image widget or section/column background from a media library attachment ID (e.g. from user upload)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'       => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'element_id'    => array( 'type' => 'string', 'description' => 'Target element ID' ),
					'attachment_id' => array( 'type' => 'integer', 'description' => 'WordPress media attachment ID' ),
				),
				'required'   => array( 'post_id', 'element_id', 'attachment_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'upload_files' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$post_id       = (int) ( $args['post_id'] ?? 0 );
		$element_id    = sanitize_text_field( $args['element_id'] ?? '' );
		$attachment_id = (int) ( $args['attachment_id'] ?? 0 );

		if ( ! $this->can_edit_page( $user_id, $post_id ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$result = $this->documents->apply_attachment_to_element( $post_id, $element_id, $attachment_id );

		if ( ! $result['success'] ) {
			return $this->fail( $result['message'] );
		}

		return $this->success(
			array(
				'post_id'       => $post_id,
				'element_id'    => $element_id,
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
			),
			$result['message']
		);
	}
}

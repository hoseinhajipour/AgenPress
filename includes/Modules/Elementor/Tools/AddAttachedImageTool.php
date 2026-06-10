<?php
/**
 * Add an image widget from an uploaded attachment.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class AddAttachedImageTool
 */
class AddAttachedImageTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'add_attached_image_to_page';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'add_attached_image_to_page',
			'description' => 'Add a new Elementor image widget to the page using an uploaded media library attachment (from user file upload). Inserts into the selected column/section or the first available column.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'              => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'attachment_id'        => array( 'type' => 'integer', 'description' => 'Media library attachment ID from the user upload' ),
					'column_id'            => array( 'type' => 'string', 'description' => 'Target column element ID (optional)' ),
					'context_element_id'   => array( 'type' => 'string', 'description' => 'Selected section/column/widget ID for placement context' ),
					'after_element_id'     => array( 'type' => 'string', 'description' => 'Insert after this sibling widget ID (optional)' ),
				),
				'required'   => array( 'post_id', 'attachment_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'upload_files' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$post_id       = (int) ( $args['post_id'] ?? 0 );
		$attachment_id = (int) ( $args['attachment_id'] ?? 0 );

		if ( ! $this->can_edit_page( $user_id, $post_id ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$column_id = isset( $args['column_id'] ) ? sanitize_text_field( $args['column_id'] ) : null;
		$context_id = isset( $args['context_element_id'] ) ? sanitize_text_field( $args['context_element_id'] ) : null;
		$after_id   = isset( $args['after_element_id'] ) ? sanitize_text_field( $args['after_element_id'] ) : null;

		$result = $this->documents->add_attached_image_widget(
			$post_id,
			$attachment_id,
			$column_id,
			$context_id,
			$after_id
		);

		if ( ! $result['success'] ) {
			return $this->fail( $result['message'] );
		}

		$data = $result['data'] ?? array();

		return $this->success(
			array_merge(
				$data,
				array( 'element_id' => $result['element_id'] )
			),
			sprintf(
				"%s\n\n![%s](%s)",
				$result['message'],
				basename( (string) get_attached_file( $attachment_id ) ),
				(string) ( $data['url'] ?? wp_get_attachment_url( $attachment_id ) )
			)
		);
	}
}

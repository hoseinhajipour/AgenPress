<?php
/**
 * Duplicate an Elementor element.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class DuplicateElementTool
 */
class DuplicateElementTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'duplicate_element';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'duplicate_element',
			'description' => 'Duplicate an Elementor section, column, or widget',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'element_id' => array( 'type' => 'string', 'description' => 'Element ID to duplicate' ),
				),
				'required'   => array( 'post_id', 'element_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $args['element_id'] ?? '' );

		if ( ! $this->can_edit_page( $user_id, $post_id ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$result = $this->documents->duplicate_element( $post_id, $element_id );

		if ( ! $result['success'] ) {
			return $this->fail( $result['message'] );
		}

		return $this->success(
			array(
				'post_id'    => $post_id,
				'element_id' => $result['element_id'],
			),
			$result['message']
		);
	}
}

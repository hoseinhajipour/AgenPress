<?php
/**
 * Delete an Elementor element.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeleteElementTool
 */
class DeleteElementTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'delete_element';
	}

	public function requires_confirmation(): bool {
		return true;
	}

	public function get_confirmation_message( array $args ): string {
		return sprintf(
			/* translators: %s: element ID */
			__( 'Delete Elementor element "%s"? This cannot be undone.', 'agenpress' ),
			$args['element_id'] ?? ''
		);
	}

	public function get_schema(): array {
		return array(
			'name'        => 'delete_element',
			'description' => 'Delete an Elementor section, column, or widget',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'element_id' => array( 'type' => 'string', 'description' => 'Element ID to delete' ),
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

		$result = $this->documents->delete_element( $post_id, $element_id );

		if ( ! $result['success'] ) {
			return $this->fail( $result['message'] );
		}

		return $this->success(
			array(
				'post_id'    => $post_id,
				'element_id' => $element_id,
			),
			$result['message']
		);
	}
}

<?php
/**
 * Get a single Elementor element.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetElementTool
 */
class GetElementTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'get_element';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_element',
			'description' => 'Get details and settings of a specific Elementor element by ID',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'element_id' => array( 'type' => 'string', 'description' => 'Elementor element ID' ),
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

		$element = $this->documents->get_element( $post_id, $element_id );

		if ( ! $element ) {
			return $this->fail( __( 'Element not found.', 'agenpress' ) );
		}

		return $this->success( $element, __( 'Element retrieved.', 'agenpress' ) );
	}
}

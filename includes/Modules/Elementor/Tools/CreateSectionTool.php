<?php
/**
 * Create an Elementor section.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class CreateSectionTool
 */
class CreateSectionTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'create_section';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'create_section',
			'description' => 'Create a new Elementor section or container on a page. Returns column_id — always use that column_id (not element_id) when calling create_widget next.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'          => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'settings'         => array(
						'type'        => 'object',
						'description' => 'Section settings (e.g. background_color, padding)',
					),
					'after_element_id' => array( 'type' => 'string', 'description' => 'Insert after this element ID (optional)' ),
				),
				'required'   => array( 'post_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$post_id = (int) ( $args['post_id'] ?? 0 );

		if ( ! $this->can_edit_page( $user_id, $post_id ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$settings = is_array( $args['settings'] ?? null ) ? $args['settings'] : array();
		$after_id = isset( $args['after_element_id'] ) ? sanitize_text_field( $args['after_element_id'] ) : null;

		$result = $this->documents->create_section( $post_id, $settings, $after_id );

		if ( ! $result['success'] ) {
			return $this->fail( $result['message'] );
		}

		return $this->success(
			array(
				'post_id'    => $post_id,
				'element_id' => $result['element_id'],
				'column_id'  => $result['column_id'] ?? null,
				'layout'     => $result['layout'] ?? null,
			),
			$result['message']
		);
	}
}

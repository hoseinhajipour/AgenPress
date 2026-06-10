<?php
/**
 * Create an Elementor widget inside a column.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class CreateWidgetTool
 */
class CreateWidgetTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'create_widget';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'create_widget',
			'description' => 'Add a new Elementor widget inside a column or container. Use column_id from create_section response, or a column/container id from get_page_structure. Widget types: heading, text-editor, icon-box (icon + title + text), image, button, divider, spacer.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'          => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'column_id'        => array( 'type' => 'string', 'description' => 'Parent column or container ID (use column_id from create_section, not the section id)' ),
					'widget_type'      => array( 'type' => 'string', 'description' => 'Widget type slug, e.g. heading, text-editor, image, button' ),
					'settings'         => array(
						'type'        => 'object',
						'description' => 'Widget settings (title, editor, image, text, link, etc.)',
					),
					'after_element_id' => array( 'type' => 'string', 'description' => 'Insert after sibling element ID (optional)' ),
				),
				'required'   => array( 'post_id', 'column_id', 'widget_type' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$post_id     = (int) ( $args['post_id'] ?? 0 );
		$column_id   = sanitize_text_field( $args['column_id'] ?? '' );
		$widget_type = sanitize_key( $args['widget_type'] ?? '' );
		$settings    = is_array( $args['settings'] ?? null ) ? $args['settings'] : array();
		$after_id    = isset( $args['after_element_id'] ) ? sanitize_text_field( $args['after_element_id'] ) : null;

		if ( ! $this->can_edit_page( $user_id, $post_id ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$result = $this->documents->create_widget( $post_id, $column_id, $widget_type, $settings, $after_id );

		if ( ! $result['success'] ) {
			return $this->fail( $result['message'] );
		}

		return $this->success(
			array(
				'post_id'    => $post_id,
				'element_id' => $result['element_id'],
				'widget_type' => $widget_type,
			),
			$result['message']
		);
	}
}

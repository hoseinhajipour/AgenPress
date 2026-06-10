<?php
/**
 * Update Elementor element settings.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class UpdateWidgetSettingsTool
 */
class UpdateWidgetSettingsTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'update_widget_settings';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'update_widget_settings',
			'description' => 'Update settings on an Elementor widget, column, or section (title, colors, typography, image, etc.)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'element_id' => array( 'type' => 'string', 'description' => 'Elementor element ID' ),
					'settings'   => array(
						'type'        => 'object',
						'description' => 'Settings key-value pairs to merge into the element',
					),
				),
				'required'   => array( 'post_id', 'element_id', 'settings' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		$post_id    = (int) ( $args['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $args['element_id'] ?? '' );
		$settings   = $args['settings'] ?? array();

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return $this->fail( __( 'Settings are required.', 'agenpress' ) );
		}

		if ( ! $this->can_edit_page( $user_id, $post_id ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$result = $this->documents->update_element_settings( $post_id, $element_id, $settings );

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

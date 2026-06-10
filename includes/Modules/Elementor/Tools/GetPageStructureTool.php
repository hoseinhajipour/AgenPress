<?php
/**
 * Get Elementor page structure.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetPageStructureTool
 */
class GetPageStructureTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'get_page_structure';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_page_structure',
			'description' => 'Get the Elementor element tree for a page (sections, columns, widgets)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'           => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'include_settings'  => array( 'type' => 'boolean', 'description' => 'Include widget settings (default false)' ),
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

		$structure = $this->documents->get_structure(
			$post_id,
			! empty( $args['include_settings'] )
		);

		if ( ! $structure ) {
			return $this->fail( __( 'Elementor page not found.', 'agenpress' ) );
		}

		return $this->success( $structure, __( 'Page structure retrieved.', 'agenpress' ) );
	}
}

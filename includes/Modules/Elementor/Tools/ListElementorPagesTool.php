<?php
/**
 * List Elementor-built pages.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListElementorPagesTool
 */
class ListElementorPagesTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'list_elementor_pages';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'list_elementor_pages',
			'description' => 'List WordPress pages and posts built with Elementor',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array( 'type' => 'integer', 'description' => 'Max results (default 20)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'edit_posts' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		if ( ! $this->documents->is_available() ) {
			return $this->fail( __( 'Elementor is not active.', 'agenpress' ) );
		}

		$pages = $this->documents->list_pages( (int) ( $args['limit'] ?? 20 ) );

		return $this->success(
			$pages,
			sprintf(
				/* translators: %d: number of pages */
				__( 'Found %d Elementor pages.', 'agenpress' ),
				count( $pages )
			)
		);
	}
}

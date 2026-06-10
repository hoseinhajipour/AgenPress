<?php
/**
 * Search Elementor elements on a page.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchElementsTool
 */
class SearchElementsTool extends ElementorAbstractTool {

	public function get_name(): string {
		return 'search_elements';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'search_elements',
			'description' => 'Find Elementor widgets, columns, or sections by text content or widget type (heading, image, button, text-editor, etc.)',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array( 'type' => 'integer', 'description' => 'Page or post ID' ),
					'query'       => array( 'type' => 'string', 'description' => 'Search text in widget content or settings' ),
					'widget_type' => array( 'type' => 'string', 'description' => 'Filter by widget type slug (optional)' ),
					'limit'       => array( 'type' => 'integer', 'description' => 'Max results (default 15)' ),
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

		$results = $this->documents->search_elements(
			$post_id,
			sanitize_text_field( $args['query'] ?? '' ),
			isset( $args['widget_type'] ) ? sanitize_key( $args['widget_type'] ) : null,
			min( 30, (int) ( $args['limit'] ?? 15 ) )
		);

		if ( null === $results ) {
			return $this->fail( __( 'Elementor page not found.', 'agenpress' ) );
		}

		return $this->success(
			$results,
			sprintf(
				/* translators: %d: match count */
				__( 'Found %d matching elements.', 'agenpress' ),
				count( $results )
			)
		);
	}
}

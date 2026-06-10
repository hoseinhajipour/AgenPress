<?php
/**
 * List categories or tags.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListTermsTool
 */
class ListTermsTool extends AbstractTool {

	public function get_name(): string {
		return 'list_terms';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'list_terms',
			'description' => 'List WordPress categories or tags',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy' => array( 'type' => 'string', 'description' => 'category or post_tag', 'enum' => array( 'category', 'post_tag' ) ),
					'search'   => array( 'type' => 'string', 'description' => 'Search term name' ),
					'limit'    => array( 'type' => 'integer', 'description' => 'Max results (default 20)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'manage_categories' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$taxonomy = sanitize_key( $args['taxonomy'] ?? 'category' );

		if ( ! in_array( $taxonomy, array( 'category', 'post_tag' ), true ) ) {
			$taxonomy = 'category';
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => min( 50, (int) ( $args['limit'] ?? 20 ) ),
				'search'     => sanitize_text_field( $args['search'] ?? '' ),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $this->fail( $terms->get_error_message() );
		}

		$data = array_map(
			static function ( $term ) {
				return array(
					'id'    => $term->term_id,
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => $term->count,
				);
			},
			$terms
		);

		return $this->success( $data, sprintf( __( 'Found %d terms.', 'agenpress' ), count( $data ) ) );
	}
}

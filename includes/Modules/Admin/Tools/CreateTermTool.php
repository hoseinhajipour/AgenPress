<?php
/**
 * Create a category or tag.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class CreateTermTool
 */
class CreateTermTool extends AbstractTool {

	public function get_name(): string {
		return 'create_term';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'create_term',
			'description' => 'Create a new WordPress category or tag',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'name'     => array( 'type' => 'string', 'description' => 'Term name' ),
					'taxonomy' => array( 'type' => 'string', 'enum' => array( 'category', 'post_tag' ) ),
					'parent'   => array( 'type' => 'integer', 'description' => 'Parent category ID (categories only)' ),
				),
				'required'   => array( 'name', 'taxonomy' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'manage_categories' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$taxonomy = sanitize_key( $args['taxonomy'] ?? 'category' );
		$name     = sanitize_text_field( $args['name'] ?? '' );

		if ( '' === $name ) {
			return $this->fail( __( 'Term name is required.', 'agenpress' ) );
		}

		$insert_args = array();
		if ( 'category' === $taxonomy && ! empty( $args['parent'] ) ) {
			$insert_args['parent'] = (int) $args['parent'];
		}

		$result = wp_insert_term( $name, $taxonomy, $insert_args );

		if ( is_wp_error( $result ) ) {
			return $this->fail( $result->get_error_message() );
		}

		$term = get_term( (int) $result['term_id'], $taxonomy );

		return $this->success(
			array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			),
			sprintf( __( 'Created term "%s".', 'agenpress' ), $name )
		);
	}
}

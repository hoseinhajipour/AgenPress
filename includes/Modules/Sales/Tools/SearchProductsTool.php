<?php
/**
 * Search published WooCommerce products.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchProductsTool
 */
class SearchProductsTool extends SalesAbstractTool {

	public function get_name(): string {
		return 'search_products';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'search_products',
			'description' => 'Search published WooCommerce products by name or keyword',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'search' => array( 'type' => 'string', 'description' => 'Search term' ),
					'limit'  => array( 'type' => 'integer', 'description' => 'Max results (default 10)' ),
				),
				'required'   => array( 'search' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->fail( __( 'WooCommerce is not active.', 'agenpress' ) );
		}

		$query = new \WC_Product_Query(
			array(
				'limit'  => min( 20, (int) ( $args['limit'] ?? 10 ) ),
				'status' => 'publish',
				'search' => sanitize_text_field( $args['search'] ?? '' ),
				'return' => 'objects',
			)
		);

		$data = array_map( array( $this, 'format_product' ), $query->get_products() );

		return $this->success( $data, sprintf( __( 'Found %d products.', 'agenpress' ), count( $data ) ) );
	}
}

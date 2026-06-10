<?php
/**
 * Memory REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Memory\BrandExtractor;
use AgenPress\Memory\EmbeddingService;
use AgenPress\Memory\MemoryStore;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class MemoryController
 */
class MemoryController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/memory',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_memory' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_memory' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memory/search',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_memory' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memory/extract-brand',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'extract_brand' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memory/reindex',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reindex_memory' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/memory/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_memory' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_memory' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_memory' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Read permission.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( Capabilities::USE_ADMIN_AI );
	}

	/**
	 * Manage permission.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE_MEMORY );
	}

	/**
	 * GET /memory
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_memory( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );

		$entries = $store->list(
			$request->get_param( 'category' ) ? sanitize_text_field( $request->get_param( 'category' ) ) : null,
			sanitize_text_field( $request->get_param( 'search' ) ?? '' ),
			(int) ( $request->get_param( 'limit' ) ?? 50 ),
			(int) ( $request->get_param( 'offset' ) ?? 0 )
		);

		/** @var EmbeddingService $embedding_service */
		$embedding_service = $this->container->get( 'embedding_service' );

		return $this->success(
			array(
				'entries'             => $entries,
				'categories'          => MemoryStore::CATEGORIES,
				'embeddings_available' => $embedding_service->is_available(),
			)
		);
	}

	/**
	 * GET /memory/search
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function search_memory( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );

		$query = sanitize_text_field( $request->get_param( 'query' ) ?? '' );

		if ( '' === $query ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_query',
					__( 'Search query is required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$category = $request->get_param( 'category' )
			? sanitize_text_field( $request->get_param( 'category' ) )
			: null;

		$categories = $category ? array( $category ) : null;

		return $this->success(
			array(
				'results' => $store->semantic_search(
					$query,
					$categories,
					(int) ( $request->get_param( 'limit' ) ?? 10 )
				),
			)
		);
	}

	/**
	 * POST /memory/extract-brand
	 *
	 * @return \WP_REST_Response
	 */
	public function extract_brand(): \WP_REST_Response {
		/** @var BrandExtractor $extractor */
		$extractor = $this->container->get( 'brand_extractor' );

		return $this->success( $extractor->import() );
	}

	/**
	 * POST /memory/reindex
	 *
	 * @return \WP_REST_Response
	 */
	public function reindex_memory(): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );

		return $this->success( $store->reindex_all() );
	}

	/**
	 * POST /memory
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_memory( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );

		$key_name = sanitize_text_field( $request->get_param( 'key_name' ) ?? '' );
		$value    = sanitize_textarea_field( $request->get_param( 'value' ) ?? '' );

		if ( '' === $key_name || '' === $value ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_memory',
					__( 'Key name and value are required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$entry = $store->create(
			sanitize_text_field( $request->get_param( 'category' ) ?? 'general' ),
			$key_name,
			$value,
			$request->get_param( 'metadata' ) ?? array()
		);

		if ( ! $entry ) {
			return $this->error(
				new \WP_Error(
					'agenpress_create_failed',
					__( 'Failed to create memory entry.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		return $this->success( $entry, 201 );
	}

	/**
	 * GET /memory/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_memory( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );
		$entry = $store->find( (int) $request->get_param( 'id' ) );

		if ( ! $entry ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Memory entry not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		return $this->success( $entry );
	}

	/**
	 * PUT /memory/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function update_memory( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );
		$id    = (int) $request->get_param( 'id' );

		if ( ! $store->find( $id ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Memory entry not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$data = $request->get_json_params();

		if ( ! is_array( $data ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_data',
					__( 'Invalid data.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		return $this->success( $store->update( $id, $data ) );
	}

	/**
	 * DELETE /memory/{id}
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function delete_memory( \WP_REST_Request $request ): \WP_REST_Response {
		/** @var MemoryStore $store */
		$store = $this->container->get( 'memory_store' );
		$id    = (int) $request->get_param( 'id' );

		if ( ! $store->find( $id ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Memory entry not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		$store->delete( $id );

		return $this->success( array( 'deleted' => true ) );
	}
}

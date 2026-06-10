<?php
/**
 * Elementor editor sync REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Modules\Elementor\ElementorDocumentService;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorController
 */
class ElementorController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/elementor/documents/(?P<id>\d+)/elements',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_document_elements' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( Capabilities::USE_ELEMENTOR_AI );
	}

	/**
	 * GET /elementor/documents/{id}/elements
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_document_elements( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_forbidden',
					__( 'Permission denied.', 'agenpress' ),
					array( 'status' => 403 )
				)
			);
		}

		/** @var ElementorDocumentService $documents */
		$documents = $this->container->get( 'elementor_documents' );
		$elements  = $documents->get_raw_elements( $post_id );

		if ( null === $elements ) {
			return $this->error(
				new \WP_Error(
					'agenpress_not_found',
					__( 'Elementor document not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		return $this->success(
			array(
				'post_id'  => $post_id,
				'elements' => $elements,
			)
		);
	}
}

<?php
/**
 * File upload REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\Core\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class UploadController
 */
class UploadController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/upload',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_file' ),
				'permission_callback' => array( $this, 'check_auth' ),
			)
		);
	}

	/**
	 * Auth check.
	 *
	 * @return bool
	 */
	public function check_auth(): bool {
		return is_user_logged_in() && current_user_can( 'upload_files' );
	}

	/**
	 * POST /upload
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function upload_file( \WP_REST_Request $request ): \WP_REST_Response {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_no_file',
					__( 'No file uploaded.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file   = $files['file'];
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_upload_failed',
					$upload['error'],
					array( 'status' => 400 )
				)
			);
		}

		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $this->error( $attachment_id );
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $this->success(
			array(
				'id'   => $attachment_id,
				'url'  => wp_get_attachment_url( $attachment_id ),
				'name' => basename( $upload['file'] ),
				'type' => $upload['type'],
			),
			201
		);
	}
}

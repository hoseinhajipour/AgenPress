<?php
/**
 * AI image generation REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\AI\ProviderFactory;
use AgenPress\Core\Container;
use AgenPress\Media\AiImageSideloader;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ImageController
 */
class ImageController extends RestController {

	/**
	 * Allowed image sizes.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_SIZES = array( '1024x1024', '1792x1024', '1024x1792' );

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/generate-image',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_image' ),
				'permission_callback' => array( $this, 'check_generate_permission' ),
				'args'                => array(
					'prompt' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'size'   => array(
						'type'              => 'string',
						'default'           => '1024x1024',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/posts/(?P<id>\d+)/featured-image',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_featured_image' ),
				'permission_callback' => array( $this, 'check_featured_permission' ),
				'args'                => array(
					'id'            => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'attachment_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission check for image generation.
	 *
	 * @return bool
	 */
	public function check_generate_permission(): bool {
		return current_user_can( Capabilities::USE_ADMIN_AI ) && current_user_can( 'upload_files' );
	}

	/**
	 * Permission check for setting featured image.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_featured_permission( \WP_REST_Request $request ): bool {
		$post_id = (int) $request->get_param( 'id' );

		return current_user_can( Capabilities::USE_ADMIN_AI )
			&& current_user_can( 'edit_post', $post_id )
			&& current_user_can( 'upload_files' );
	}

	/**
	 * POST /generate-image
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function generate_image( \WP_REST_Request $request ): \WP_REST_Response {
		$rate_limit = $this->check_rate_limit();

		if ( is_wp_error( $rate_limit ) ) {
			return $this->error( $rate_limit );
		}

		$prompt = sanitize_text_field( $request->get_param( 'prompt' ) ?? '' );

		if ( '' === $prompt ) {
			return $this->error(
				new \WP_Error(
					'agenpress_missing_prompt',
					__( 'Image prompt is required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$size = sanitize_text_field( $request->get_param( 'size' ) ?? '1024x1024' );

		if ( ! in_array( $size, self::ALLOWED_SIZES, true ) ) {
			$size = '1024x1024';
		}

		/** @var ProviderFactory $provider_factory */
		$provider_factory = $this->container->get( 'provider_factory' );
		$provider         = $provider_factory->get_image_provider();

		if ( ! $provider->is_configured() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_no_image_provider',
					__( 'An image-capable AI provider API key is required (OpenAI, GapGPT, or Custom).', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		try {
			$image = $provider->generate_image(
				$prompt,
				array( 'size' => $size )
			);
		} catch ( \Exception $e ) {
			return $this->error(
				new \WP_Error(
					'agenpress_image_generation_failed',
					$e->getMessage(),
					array( 'status' => 500 )
				)
			);
		}

		if ( empty( $image['url'] ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_image_generation_failed',
					__( 'Image generation failed.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		$attachment_id = AiImageSideloader::sideload( $image['url'], $prompt );

		if ( ! $attachment_id ) {
			return $this->error(
				new \WP_Error(
					'agenpress_sideload_failed',
					__( 'Failed to save image to media library.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		return $this->success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
			)
		);
	}

	/**
	 * POST /posts/{id}/featured-image
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function set_featured_image( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id       = (int) $request->get_param( 'id' );
		$attachment_id = (int) $request->get_param( 'attachment_id' );

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error(
				new \WP_Error(
					'agenpress_post_not_found',
					__( 'Post not found.', 'agenpress' ),
					array( 'status' => 404 )
				)
			);
		}

		if ( ! get_post( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return $this->error(
				new \WP_Error(
					'agenpress_invalid_attachment',
					__( 'Invalid image attachment.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$result = set_post_thumbnail( $post_id, $attachment_id );

		if ( ! $result ) {
			return $this->error(
				new \WP_Error(
					'agenpress_featured_image_failed',
					__( 'Failed to set featured image.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		return $this->success(
			array(
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
			)
		);
	}
}

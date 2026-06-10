<?php
/**
 * Generate AI images for the media library and site content.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;
use AgenPress\AI\ImageSizeRegistry;
use AgenPress\AI\ProviderFactory;
use AgenPress\Media\AiImageSideloader;

defined( 'ABSPATH' ) || exit;

/**
 * Class GenerateImageTool
 */
class GenerateImageTool extends AbstractTool {

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Constructor.
	 *
	 * @param ProviderFactory $provider_factory Provider factory.
	 */
	public function __construct( ProviderFactory $provider_factory ) {
		$this->provider_factory = $provider_factory;
	}

	public function get_name(): string {
		return 'generate_image';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'generate_image',
			'description' => 'Generate an AI image (DALL-E) and save it to the media library. Optionally set it as a post featured image or insert it into post content for any post type.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'prompt'               => array( 'type' => 'string', 'description' => 'Detailed image generation prompt' ),
					'size'                 => array( 'type' => 'string', 'description' => 'Image aspect ratio (1:1, 16:9, 9:16, 4:3, 3:2) or pixel size (1024x1024, 1792x1024, 1024x1792). Uses plugin default when omitted.' ),
					'post_id'              => array( 'type' => 'integer', 'description' => 'Post ID to attach the image to (optional)' ),
					'set_featured'         => array( 'type' => 'boolean', 'description' => 'Set as featured image on the post' ),
					'insert_into_content'  => array( 'type' => 'boolean', 'description' => 'Insert image into post content' ),
					'content_position'     => array( 'type' => 'string', 'description' => 'Where to insert in content: start or end', 'enum' => array( 'start', 'end' ) ),
					'alt_text'             => array( 'type' => 'string', 'description' => 'Image alt text for accessibility and SEO' ),
				),
				'required'   => array( 'prompt' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'upload_files' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$prompt = sanitize_text_field( $args['prompt'] ?? '' );

		if ( '' === $prompt ) {
			return $this->fail( __( 'Image prompt is required.', 'agenpress' ) );
		}

		$provider = $this->provider_factory->get_image_provider();

		if ( ! $provider->is_configured() ) {
			return $this->fail( __( 'An image-capable AI provider API key is required (OpenAI, GapGPT, or Custom).', 'agenpress' ) );
		}

		$size = ImageSizeRegistry::resolve_size( sanitize_text_field( $args['size'] ?? '' ) );

		try {
			$image = $provider->generate_image(
				$prompt,
				array( 'size' => $size )
			);
		} catch ( \Exception $e ) {
			return $this->fail( $e->getMessage() );
		}

		if ( empty( $image['url'] ) && empty( $image['b64_json'] ) ) {
			return $this->fail( __( 'Image generation failed.', 'agenpress' ) );
		}

		$attachment_id = AiImageSideloader::sideload_result( $image, $prompt );

		if ( ! $attachment_id ) {
			return $this->fail( __( 'Failed to save image to media library.', 'agenpress' ) );
		}

		$alt_text = sanitize_text_field( $args['alt_text'] ?? $prompt );

		if ( '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		$image_url       = (string) wp_get_attachment_url( $attachment_id );
		$post_id         = (int) ( $args['post_id'] ?? 0 );
		$set_featured    = ! empty( $args['set_featured'] );
		$insert_content  = ! empty( $args['insert_into_content'] );
		$content_position = sanitize_key( $args['content_position'] ?? 'end' );
		$applied         = array();

		if ( $post_id && ( $set_featured || $insert_content ) ) {
			$post = get_post( $post_id );

			if ( ! $post || ! user_can( $user_id, 'edit_post', $post_id ) ) {
				return $this->fail( __( 'Post not found or permission denied.', 'agenpress' ) );
			}

			if ( $set_featured ) {
				if ( ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
					return $this->fail( __( 'This post type does not support featured images.', 'agenpress' ) );
				}

				if ( set_post_thumbnail( $post_id, $attachment_id ) ) {
					$applied[] = 'featured';
				}
			}

			if ( $insert_content ) {
				$img_html = sprintf(
					'<figure class="wp-block-image size-full"><img src="%s" alt="%s" class="wp-image-%d" /></figure>',
					esc_url( $image_url ),
					esc_attr( $alt_text ),
					$attachment_id
				);

				$content = (string) $post->post_content;

				if ( 'start' === $content_position ) {
					$content = $img_html . "\n\n" . $content;
				} else {
					$content = rtrim( $content ) . "\n\n" . $img_html;
				}

				$updated = wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $content,
					),
					true
				);

				if ( ! is_wp_error( $updated ) ) {
					$applied[] = 'content';
				}
			}
		}

		$message = $this->build_message( $attachment_id, $image_url, $alt_text, $applied, $post_id );

		return $this->success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => $image_url,
				'alt_text'      => $alt_text,
				'post_id'       => $post_id ?: null,
				'applied'       => $applied,
			),
			$message
		);
	}

	/**
	 * Build a human-readable (markdown) result message for chat display.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param string               $image_url     Image URL.
	 * @param string               $alt_text      Alt text.
	 * @param array<string>        $applied       Applied usages.
	 * @param int                  $post_id       Post ID if any.
	 * @return string
	 */
	private function build_message( int $attachment_id, string $image_url, string $alt_text, array $applied, int $post_id ): string {
		$lines = array(
			sprintf(
				/* translators: %d: attachment ID */
				__( 'Image generated and saved to media library (ID: %d).', 'agenpress' ),
				$attachment_id
			),
			'',
			sprintf( '![%s](%s)', $alt_text, $image_url ),
		);

		if ( in_array( 'featured', $applied, true ) && $post_id ) {
			$post = get_post( $post_id );
			$lines[] = '';
			$lines[] = sprintf(
				/* translators: %s: post title */
				__( 'Set as featured image on "%s".', 'agenpress' ),
				$post ? $post->post_title : (string) $post_id
			);
		}

		if ( in_array( 'content', $applied, true ) && $post_id ) {
			$post = get_post( $post_id );
			$lines[] = sprintf(
				/* translators: %s: post title */
				__( 'Inserted into content of "%s".', 'agenpress' ),
				$post ? $post->post_title : (string) $post_id
			);
		}

		return implode( "\n", $lines );
	}
}

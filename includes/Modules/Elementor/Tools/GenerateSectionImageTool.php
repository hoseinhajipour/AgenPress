<?php
/**
 * Generate an AI image for Elementor sections.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor\Tools;

use AgenPress\AI\ProviderFactory;
use AgenPress\Media\AiImageSideloader;
use AgenPress\Modules\Elementor\ElementorDocumentService;

defined( 'ABSPATH' ) || exit;

/**
 * Class GenerateSectionImageTool
 */
class GenerateSectionImageTool extends ElementorAbstractTool {

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Constructor.
	 *
	 * @param ElementorDocumentService $documents        Document service.
	 * @param ProviderFactory          $provider_factory Provider factory.
	 */
	public function __construct( ElementorDocumentService $documents, ProviderFactory $provider_factory ) {
		parent::__construct( $documents );
		$this->provider_factory = $provider_factory;
	}

	public function get_name(): string {
		return 'generate_section_image';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'generate_section_image',
			'description' => 'Generate an AI image (DALL-E) and optionally apply it to an Elementor image widget or section background',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'prompt'     => array( 'type' => 'string', 'description' => 'Image generation prompt' ),
					'post_id'    => array( 'type' => 'integer', 'description' => 'Page ID (optional, for applying to element)' ),
					'element_id' => array( 'type' => 'string', 'description' => 'Element ID to apply image to (optional)' ),
					'size'       => array( 'type' => 'string', 'description' => 'Image size: 1024x1024, 1792x1024, or 1024x1792' ),
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

		try {
			$image = $provider->generate_image(
				$prompt,
				array(
					'size' => sanitize_text_field( $args['size'] ?? '1024x1024' ),
				)
			);
		} catch ( \Exception $e ) {
			return $this->fail( $e->getMessage() );
		}

		if ( empty( $image['url'] ) ) {
			return $this->fail( __( 'Image generation failed.', 'agenpress' ) );
		}

		$attachment_id = AiImageSideloader::sideload( $image['url'], $prompt );

		if ( ! $attachment_id ) {
			return $this->fail( __( 'Failed to save image to media library.', 'agenpress' ) );
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		$post_id   = (int) ( $args['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $args['element_id'] ?? '' );
		$applied   = false;

		if ( $post_id && $element_id && $this->can_edit_page( $user_id, $post_id ) ) {
			$element = $this->documents->get_element( $post_id, $element_id );

			if ( $element ) {
				$settings = $this->image_settings_for_element( $element, $attachment_id, $image_url );
				$update   = $this->documents->update_element_settings( $post_id, $element_id, $settings );
				$applied  = $update['success'];
			}
		}

		return $this->success(
			array(
				'attachment_id' => $attachment_id,
				'url'           => $image_url,
				'applied'       => $applied,
				'post_id'       => $post_id ?: null,
				'element_id'    => $element_id ?: null,
			),
			$applied
				? __( 'Image generated and applied to element.', 'agenpress' )
				: __( 'Image generated and saved to media library.', 'agenpress' )
		);
	}

	/**
	 * Build Elementor image settings for an element type.
	 *
	 * @param array<string, mixed> $element       Element summary.
	 * @param int                  $attachment_id Attachment ID.
	 * @param string               $url           Image URL.
	 * @return array<string, mixed>
	 */
	private function image_settings_for_element( array $element, int $attachment_id, string $url ): array {
		$el_type     = $element['elType'] ?? '';
		$widget_type = $element['widgetType'] ?? '';

		if ( 'widget' === $el_type && 'image' === $widget_type ) {
			return array(
				'image' => array(
					'id'  => $attachment_id,
					'url' => $url,
				),
			);
		}

		if ( 'section' === $el_type || 'column' === $el_type ) {
			return array(
				'background_background' => 'classic',
				'background_image'      => array(
					'id'  => $attachment_id,
					'url' => $url,
				),
			);
		}

		return array(
			'image' => array(
				'id'  => $attachment_id,
				'url' => $url,
			),
		);
	}
}

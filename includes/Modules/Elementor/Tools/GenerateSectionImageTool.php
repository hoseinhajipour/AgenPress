<?php

/**

 * Generate an AI image for Elementor sections.

 *

 * @package AgenPress

 */



namespace AgenPress\Modules\Elementor\Tools;



use AgenPress\AI\ImageSizeRegistry;

use AgenPress\AI\ProviderFactory;

use AgenPress\Media\AiImageSideloader;

use AgenPress\Media\ReferenceImagePreparer;

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

			'description' => 'Generate ONE composite banner/hero image with AI and add it to the page as a single image widget. Use when the user wants a designed banner (text baked into the image, layout, icons, 3D style). When the user attached a photo, pass reference_attachment_id so the AI uses that image (e.g. person on the right). Do NOT use add_attached_image_to_page or heading widgets for banner design requests.',

			'parameters'  => array(

				'type'       => 'object',

				'properties' => array(

					'prompt'                 => array( 'type' => 'string', 'description' => 'Detailed banner prompt: layout (left/right), exact text to render inside the image, icons, colors, 3D/fantasy style, mood' ),

					'post_id'                => array( 'type' => 'integer', 'description' => 'Page ID from editor context (required to place image on page)' ),

					'reference_attachment_id' => array( 'type' => 'integer', 'description' => 'Attachment ID of user-uploaded reference image (character, product, logo) to incorporate into the banner' ),

					'element_id'             => array( 'type' => 'string', 'description' => 'Existing image widget or section/column ID to apply the image to (optional)' ),

					'column_id'              => array( 'type' => 'string', 'description' => 'Target column ID for a new image widget (optional)' ),

					'context_element_id'     => array( 'type' => 'string', 'description' => 'Selected section/column/widget ID for placement context (optional)' ),

					'size'                   => array( 'type' => 'string', 'description' => 'Image aspect ratio (1:1, 16:9, 9:16, 4:3, 3:2) or pixel size. Uses plugin default when omitted.' ),

					'placement'              => array(

						'type'        => 'string',

						'description' => 'How to place the image: widget (default, adds image widget), background (section/container background only when explicitly requested)',

						'enum'        => array( 'widget', 'background' ),

					),

				),

				'required'   => array( 'prompt' ),

			),

		);

	}



	public function execute( array $args, int $user_id ): array {

		if ( ! $this->user_can( $user_id, 'upload_files' ) ) {

			return $this->fail( __( 'Permission denied.', 'agenpress' ) );

		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		$user_prompt = sanitize_textarea_field( $args['prompt'] ?? '' );



		if ( '' === $user_prompt ) {

			return $this->fail( __( 'Image prompt is required.', 'agenpress' ) );

		}



		$reference_id   = (int) ( $args['reference_attachment_id'] ?? 0 );

		$reference_path = null;



		if ( $reference_id > 0 ) {

			$reference_path = ReferenceImagePreparer::prepare_png_path( $reference_id );

			if ( ! $reference_path ) {
				$original = get_attached_file( $reference_id );

				if ( $original && is_readable( $original ) ) {
					$reference_path = $original;
				}
			}

		}



		$prompt   = $this->build_banner_prompt( $user_prompt, $reference_id > 0 );

		$provider = $this->provider_factory->get_image_provider();



		if ( ! $provider->is_configured() ) {

			return $this->fail( __( 'An image-capable AI provider API key is required (OpenAI, GapGPT, or Custom).', 'agenpress' ) );

		}



		$size_input  = sanitize_text_field( $args['size'] ?? '' );
		$gen_options = array(
			'size' => $size_input,
		);



		if ( $reference_path ) {

			$gen_options['reference_path'] = $reference_path;

		}



		try {

			$image = $provider->generate_image( $prompt, $gen_options );

		} catch ( \Exception $e ) {

			return $this->fail( $e->getMessage() );

		}



		if ( empty( $image['url'] ) && empty( $image['b64_json'] ) ) {

			return $this->fail( __( 'Image generation failed.', 'agenpress' ) );

		}



		$attachment_title = 'agenpress-banner-' . gmdate( 'Ymd-His' );
		$sideload         = AiImageSideloader::sideload_result_detailed( $image, $attachment_title );



		if ( empty( $sideload['attachment_id'] ) ) {

			return $this->fail(

				$sideload['error'] ?: __( 'Failed to save image to media library.', 'agenpress' )

			);

		}



		$attachment_id      = (int) $sideload['attachment_id'];

		$image_url          = (string) wp_get_attachment_url( $attachment_id );

		$post_id            = (int) ( $args['post_id'] ?? 0 );

		$target_element_id  = sanitize_text_field( $args['element_id'] ?? '' );

		$column_id          = isset( $args['column_id'] ) ? sanitize_text_field( $args['column_id'] ) : null;

		$context_element_id = isset( $args['context_element_id'] ) ? sanitize_text_field( $args['context_element_id'] ) : null;

		$placement          = sanitize_key( $args['placement'] ?? 'widget' );



		if ( ! in_array( $placement, array( 'widget', 'background' ), true ) ) {

			$placement = 'widget';

		}



		$applied              = false;

		$placement_element_id = null;

		$placement_error      = '';



		if ( $post_id && $this->can_edit_page( $user_id, $post_id ) ) {

			if ( $target_element_id ) {

				$applied              = $this->try_apply_to_element(

					$post_id,

					$target_element_id,

					$attachment_id,

					$image_url,

					$placement

				);

				$placement_element_id = $applied ? $target_element_id : null;

			}



			if ( ! $applied && 'widget' === $placement ) {

				$widget_result = $this->documents->add_attached_image_widget(

					$post_id,

					$attachment_id,

					$column_id,

					$context_element_id ?: $target_element_id,

					null

				);



				$applied              = $widget_result['success'];

				$placement_element_id = $widget_result['element_id'] ?? null;

				$placement_error      = $applied ? '' : (string) ( $widget_result['message'] ?? '' );

			}

			if ( ! $applied && 'widget' === $placement ) {
				$section_result = $this->documents->create_section( $post_id );

				if ( ! empty( $section_result['success'] ) && ! empty( $section_result['column_id'] ) ) {
					$widget_result = $this->documents->add_attached_image_widget(
						$post_id,
						$attachment_id,
						(string) $section_result['column_id'],
						null,
						null
					);

					$applied              = $widget_result['success'];
					$placement_element_id = $widget_result['element_id'] ?? null;
					$placement_error      = $applied ? '' : (string) ( $widget_result['message'] ?? '' );
				}
			}

		} elseif ( ! $post_id ) {

			$placement_error = __( 'Page ID was missing; image saved to media library only.', 'agenpress' );

		}



		if ( $applied ) {

			$message = $reference_id > 0

				? __( 'Banner image generated from the reference photo and added to the page.', 'agenpress' )

				: __( 'Image generated and added to the page as an image widget.', 'agenpress' );

		} elseif ( '' !== $placement_error ) {

			$message = sprintf(

				/* translators: %s: placement error message */

				__( 'Image generated and saved to media library, but could not be added to the page: %s', 'agenpress' ),

				$placement_error

			);

		} else {

			$message = __( 'Image generated and saved to media library.', 'agenpress' );

		}



		if ( $applied && $image_url ) {

			$message .= "\n\n![" . basename( (string) get_attached_file( $attachment_id ) ) . "](" . $image_url . ')';

		}

		$result_data = array(
			'attachment_id'           => $attachment_id,
			'url'                     => $image_url,
			'applied'                 => $applied,
			'reference_attachment_id' => $reference_id ?: null,
			'post_id'                 => $post_id ?: null,
			'element_id'              => $placement_element_id ?: ( $target_element_id ?: null ),
			'placement_element_id'    => $placement_element_id,
		);

		if ( ! $applied ) {
			return array(
				'success' => false,
				'data'    => $result_data,
				'message' => $message,
			);
		}

		return $this->success( $result_data, $message );

	}



	/**

	 * Build a prompt optimized for composite marketing banners.

	 *

	 * @param string $user_prompt      User-facing prompt text.

	 * @param bool   $has_reference    Whether a reference image is supplied.

	 * @return string

	 */

	private function build_banner_prompt( string $user_prompt, bool $has_reference ): string {

		$parts = array();



		if ( $has_reference ) {

			$parts[] = 'Create ONE complete wide advertising banner image. Use the reference photo: preserve the person/subject appearance and place them as directed in the layout.';

			$parts[] = 'The reference image is the source for the character — do not replace them with a generic person.';

		} else {

			$parts[] = 'Create ONE complete wide advertising banner image.';

		}



		$parts[] = 'Render ALL headline and marketing text directly inside the image (not as separate HTML layers).';

		$parts[] = 'Include requested icons and 3D/fantasy visual elements in the same flattened image.';

		$parts[] = 'Professional marketing quality, clean composition, readable text.';

		$parts[] = $user_prompt;



		return implode( ' ', $parts );

	}



	/**

	 * Apply a generated image to an existing element when appropriate.

	 *

	 * @param int    $post_id       Post ID.

	 * @param string $element_id    Element ID.

	 * @param int    $attachment_id Attachment ID.

	 * @param string $image_url     Image URL.

	 * @param string $placement     Placement mode.

	 * @return bool

	 */

	private function try_apply_to_element( int $post_id, string $element_id, int $attachment_id, string $image_url, string $placement ): bool {

		$element = $this->documents->get_element( $post_id, $element_id );



		if ( ! $element ) {

			return false;

		}



		$el_type     = $element['elType'] ?? '';

		$widget_type = $element['widgetType'] ?? '';



		if ( 'widget' === $el_type && 'image' === $widget_type ) {

			$settings = array(

				'image' => array(

					'id'  => $attachment_id,

					'url' => $image_url,

				),

			);

			$update   = $this->documents->update_element_settings( $post_id, $element_id, $settings );



			return $update['success'];

		}



		if ( 'background' !== $placement ) {

			return false;

		}



		if ( in_array( $el_type, array( 'section', 'column', 'container' ), true ) ) {

			$settings = array(

				'background_background' => 'classic',

				'background_image'      => array(

					'id'  => $attachment_id,

					'url' => $image_url,

				),

			);

			$update   = $this->documents->update_element_settings( $post_id, $element_id, $settings );



			return $update['success'];

		}



		return false;

	}

}



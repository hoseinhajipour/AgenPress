<?php
/**
 * Elementor AI module.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor;

use AgenPress\Agents\ToolRegistry;
use AgenPress\AI\ProviderFactory;
use AgenPress\Modules\Elementor\Tools\AddAttachedImageTool;
use AgenPress\Modules\Elementor\Tools\ApplyMediaToElementTool;
use AgenPress\Modules\Elementor\Tools\CreateSectionTool;
use AgenPress\Modules\Elementor\Tools\CreateWidgetTool;
use AgenPress\Modules\Elementor\Tools\DeleteElementTool;
use AgenPress\Modules\Elementor\Tools\DuplicateElementTool;
use AgenPress\Modules\Elementor\Tools\GenerateSectionImageTool;
use AgenPress\Modules\Elementor\Tools\GetElementTool;
use AgenPress\Modules\Elementor\Tools\GetPageStructureTool;
use AgenPress\Modules\Elementor\Tools\ListElementorPagesTool;
use AgenPress\Modules\Elementor\Tools\SearchElementsTool;
use AgenPress\Modules\Elementor\Tools\UpdateWidgetSettingsTool;
use AgenPress\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorModule
 */
class ElementorModule implements ModuleInterface {

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Document service.
	 *
	 * @var ElementorDocumentService
	 */
	private ElementorDocumentService $documents;

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Constructor.
	 *
	 * @param ToolRegistry             $tool_registry    Tool registry.
	 * @param ElementorDocumentService $documents        Document service.
	 * @param ProviderFactory          $provider_factory Provider factory.
	 */
	public function __construct(
		ToolRegistry $tool_registry,
		ElementorDocumentService $documents,
		ProviderFactory $provider_factory
	) {
		$this->tool_registry    = $tool_registry;
		$this->documents        = $documents;
		$this->provider_factory = $provider_factory;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'elementor';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return __( 'Elementor Assistant', 'agenpress' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_tools(): array {
		return array(
			new ListElementorPagesTool( $this->documents ),
			new GetPageStructureTool( $this->documents ),
			new GetElementTool( $this->documents ),
			new CreateSectionTool( $this->documents ),
			new UpdateWidgetSettingsTool( $this->documents ),
			new SearchElementsTool( $this->documents ),
			new CreateWidgetTool( $this->documents ),
			new AddAttachedImageTool( $this->documents ),
			new ApplyMediaToElementTool( $this->documents ),
			new DuplicateElementTool( $this->documents ),
			new DeleteElementTool( $this->documents ),
			new GenerateSectionImageTool( $this->documents, $this->provider_factory ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_system_prompt(): string {
		return implode(
			"\n\n",
			array(
				'You are AgenPress Elementor Assistant. You help design and edit Elementor pages, sections, columns, containers, and widgets.',
				'Workflow: (1) get_page_structure to inspect the page, (2) create_section to add a block, (3) create_widget with the column_id from create_section to add heading, icon-box, text-editor, etc. Never invent element IDs.',
				'After create_section, always use the returned column_id (not element_id) as column_id in create_widget calls.',
				'For contact info with icons, use icon-box widgets (widget_type: icon-box) with title_text and description_text. Example: address, phone, social links.',
				'Do not use update_widget_settings on sections/containers for headings — create heading widgets instead.',
				'search_elements finds widgets by type or text. update_widget_settings changes settings on existing widgets.',
				'When the user uploads an image and asks to add it to the page as-is (no design), call add_attached_image_to_page with post_id and attachment_id. Do NOT use this for banner or graphic design requests.',
				'For banner, hero, or advertising design requests (especially with an attached photo): call generate_section_image ONCE. Pass reference_attachment_id from the upload, post_id from context, size (e.g. 16:9), and a detailed prompt describing layout (character left/right), exact headline text to render inside the image, WordPress/Elementor icons, 3D/fantasy style. Do NOT create separate heading or text widgets — all text must be baked into the generated banner image.',
				'For existing image widgets or section backgrounds only, use apply_media_to_element instead of creating a new widget.',
				'Use brand colors, fonts, and design rules from Site Memory when suggesting or applying styles.',
				'When the user has a selected element, prefer operating on that element ID and the current page.',
				'For hero sections and landing pages, suggest clear structure: headline, subheadline, CTA, imagery.',
				'Never tell the user a banner or image was added to the page unless generate_section_image returned success:true with applied:true in its data. If the tool failed or was not called, say so honestly — do not invent completion.',
				'When the user asks to generate a banner or image for the page, you MUST call generate_section_image with post_id from editor context. Default behavior adds a new image widget to the page. One tool call should produce one finished banner image.',
				'Use placement=background only when the user explicitly wants a section/container background image, not a banner widget.',
				'When generate_section_image fails to save the image, retry the same tool once — do NOT offer a manual widget-based banner (separate heading/image widgets) for banner design requests.',
				'Destructive actions (delete_element) require user confirmation.',
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_suggestions(): array {
		return array(
			__( 'Show me the structure of this page', 'agenpress' ),
			__( 'Find all heading widgets on this page', 'agenpress' ),
			__( 'Update the selected widget heading text', 'agenpress' ),
			__( 'Add a CTA button below the hero', 'agenpress' ),
			__( 'Add my uploaded image to this page', 'agenpress' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return $this->documents->is_available();
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( ! $this->is_available() ) {
			return;
		}

		foreach ( $this->get_tools() as $tool ) {
			$this->tool_registry->register( $tool, 'elementor' );
		}
	}
}

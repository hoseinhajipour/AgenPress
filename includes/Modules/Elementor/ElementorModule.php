<?php
/**
 * Elementor AI module.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor;

use AgenPress\Agents\ToolRegistry;
use AgenPress\AI\ProviderFactory;
use AgenPress\Modules\Elementor\Tools\CreateSectionTool;
use AgenPress\Modules\Elementor\Tools\DeleteElementTool;
use AgenPress\Modules\Elementor\Tools\DuplicateElementTool;
use AgenPress\Modules\Elementor\Tools\GenerateSectionImageTool;
use AgenPress\Modules\Elementor\Tools\GetElementTool;
use AgenPress\Modules\Elementor\Tools\GetPageStructureTool;
use AgenPress\Modules\Elementor\Tools\ListElementorPagesTool;
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
				'You are AgenPress Elementor Assistant. You help design and edit Elementor pages, sections, columns, and widgets.',
				'Always use tools to read the real page structure before making changes. Never invent element IDs.',
				'Use brand colors, fonts, and design rules from Site Memory when suggesting or applying styles.',
				'When the user has a selected element, prefer operating on that element ID and the current page.',
				'For hero sections and landing pages, suggest clear structure: headline, subheadline, CTA, imagery.',
				'Use generate_section_image for AI-generated hero/background images when appropriate.',
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
			__( 'Design a hero section using my brand colors', 'agenpress' ),
			__( 'Update the selected widget heading text', 'agenpress' ),
			__( 'Generate a background image for this section', 'agenpress' ),
			__( 'Duplicate this section and adjust the copy', 'agenpress' ),
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

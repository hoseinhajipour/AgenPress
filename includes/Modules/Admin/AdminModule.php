<?php
/**
 * Admin AI module.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin;

use AgenPress\Agents\ToolRegistry;
use AgenPress\AI\ProviderFactory;
use AgenPress\Modules\Admin\Tools\CreateAgentTaskTool;
use AgenPress\Modules\Admin\Tools\CreatePostTool;
use AgenPress\Modules\Admin\Tools\CreateProductTool;
use AgenPress\Modules\Admin\Tools\CreateTermTool;
use AgenPress\Modules\Admin\Tools\DeletePostTool;
use AgenPress\Modules\Admin\Tools\DeleteProductTool;
use AgenPress\Modules\Admin\Tools\GetPostTool;
use AgenPress\Modules\Admin\Tools\GetProductTool;
use AgenPress\Modules\Admin\Tools\GenerateImageTool;
use AgenPress\Modules\Admin\Tools\GetSiteInfoTool;
use AgenPress\Modules\Admin\Tools\GetUserTool;
use AgenPress\Modules\Admin\Tools\ListPostsTool;
use AgenPress\Modules\Admin\Tools\ListProductsTool;
use AgenPress\Modules\Admin\Tools\ListTermsTool;
use AgenPress\Modules\Admin\Tools\ListUsersTool;
use AgenPress\Modules\Admin\Tools\UpdateMediaTool;
use AgenPress\Modules\Admin\Tools\UpdatePostTool;
use AgenPress\Modules\Admin\Tools\UpdateProductTool;
use AgenPress\Modules\ContentPrompts;
use AgenPress\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminModule
 */
class AdminModule implements ModuleInterface {

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Constructor.
	 *
	 * @param ToolRegistry    $tool_registry    Tool registry.
	 * @param ProviderFactory $provider_factory Provider factory.
	 */
	public function __construct( ToolRegistry $tool_registry, ProviderFactory $provider_factory ) {
		$this->tool_registry    = $tool_registry;
		$this->provider_factory = $provider_factory;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'admin';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return __( 'Admin Assistant', 'agenpress' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_tools(): array {
		$tools = array(
			new ListPostsTool(),
			new GetPostTool(),
			new CreatePostTool(),
			new UpdatePostTool(),
			new DeletePostTool(),
			new ListTermsTool(),
			new CreateTermTool(),
			new ListUsersTool(),
			new GetUserTool(),
			new UpdateMediaTool(),
			new GenerateImageTool( $this->provider_factory ),
			new GetSiteInfoTool(),
			new CreateAgentTaskTool(),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$tools = array_merge(
				$tools,
				array(
					new ListProductsTool(),
					new GetProductTool(),
					new CreateProductTool(),
					new UpdateProductTool(),
					new DeleteProductTool(),
				)
			);
		}

		return $tools;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_system_prompt(): string {
		return implode(
			"\n\n",
			array(
				'You are AgenPress Admin Assistant. You help manage WordPress content, users, media, and site settings.',
				'Always use available tools to fetch real data before answering. Never invent post IDs or product data.',
				'For destructive actions (delete_post, delete_product), the user will be asked to confirm before execution.',
				'Use generate_image to create AI images for any post type: save to media library, set as featured image, or insert into post content. Include descriptive alt text.',
				'When showing generated images in replies, use markdown image syntax so the user can preview them.',
				'For links, always use markdown format [short descriptive title](url). Never paste raw URLs alone. Keep link titles concise (post title, product name, or action like "Edit post").',
				'When the user attaches files, use attachment_id and url from the message. Apply images via generate_image or reference them in content updates.',
				'When the user attaches internal links (@), each item includes post_id and post_type. Use get_post or get_product with that post_id to load real data before answering or editing.',
				'For WooCommerce SEO updates: always set description (full HTML main product description, 250+ words), short_description (brief excerpt), and Rank Math fields (focus_keyword, seo_title, seo_description) via update_product. Never update only short_description when the user asks for product copy or SEO.',
				'When the user attaches or lists multiple products for SEO/description/title work, queue one background task per product with create_agent_task (template product_descriptions, params.product_ids:[id], count:1, create_new:false) instead of updating all products inline in chat.',
				'For multi-step project requests (multiple pages, products, articles, or site builds), prefer queueing background work with create_agent_task instead of doing everything inline in one chat reply.',
				'Use create_agent_task with template seo_articles for blog batches, product_descriptions for WooCommerce product batches, or custom for other planned work. Include full instructions in the task description.',
				'Large site-design prompts are auto-queued as agent tasks when possible; tell the user to check Agent Tasks for progress.',
				ContentPrompts::admin_instructions(),
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_suggestions(): array {
		$suggestions = array(
			__( 'What posts do I have on my site?', 'agenpress' ),
			__( 'Give me an overview of my WordPress site', 'agenpress' ),
			__( 'Write an SEO-optimized blog post about [topic]', 'agenpress' ),
			__( 'Generate meta title and description for my latest post', 'agenpress' ),
			__( 'Create FAQ schema markup for [topic]', 'agenpress' ),
			__( 'List all categories and suggest new ones', 'agenpress' ),
			__( 'Draft a new page with a hero section and CTA', 'agenpress' ),
			__( 'Generate a featured image for my latest post', 'agenpress' ),
			__( 'Create an AI hero image for a landing page', 'agenpress' ),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$suggestions[] = __( 'List my WooCommerce products and their stock levels', 'agenpress' );
			$suggestions[] = __( 'Create a new product draft for [product name]', 'agenpress' );
		}

		return $suggestions;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		foreach ( $this->get_tools() as $tool ) {
			$this->tool_registry->register( $tool, 'admin' );
		}

		( new PostFeaturedImage() )->register();
		( new ClassicEditorToolbar() )->register();
	}
}

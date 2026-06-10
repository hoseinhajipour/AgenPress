<?php
/**
 * Sales AI module.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Sales;

use AgenPress\Agents\ToolRegistry;
use AgenPress\Chat\ConversationRepository;
use AgenPress\Modules\ModuleInterface;
use AgenPress\Modules\Sales\Tools\EscalateToHumanTool;
use AgenPress\Modules\Sales\Tools\GetBestSellersTool;
use AgenPress\Modules\Sales\Tools\GetCartSummaryTool;
use AgenPress\Modules\Sales\Tools\GetMyOrdersTool;
use AgenPress\Modules\Sales\Tools\GetOrderStatusTool;
use AgenPress\Modules\Sales\Tools\GetProductDetailsTool;
use AgenPress\Modules\Sales\Tools\GetSalesOverviewTool;
use AgenPress\Modules\Sales\Tools\ListCouponsTool;
use AgenPress\Modules\Sales\Tools\ListOrdersTool;
use AgenPress\Modules\Sales\Tools\RecommendProductsTool;
use AgenPress\Modules\Sales\Tools\SearchProductsTool;
use AgenPress\Modules\Sales\Tools\ValidateCouponTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class SalesModule
 */
class SalesModule implements ModuleInterface {

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Conversation repository.
	 *
	 * @var ConversationRepository
	 */
	private ConversationRepository $conversations;

	/**
	 * Constructor.
	 *
	 * @param ToolRegistry           $tool_registry Tool registry.
	 * @param ConversationRepository $conversations Conversation repository.
	 */
	public function __construct( ToolRegistry $tool_registry, ConversationRepository $conversations ) {
		$this->tool_registry = $tool_registry;
		$this->conversations = $conversations;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'sales';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return __( 'Sales Assistant', 'agenpress' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_tools(): array {
		return array(
			new SearchProductsTool(),
			new RecommendProductsTool(),
			new GetProductDetailsTool(),
			new GetCartSummaryTool(),
			new ListCouponsTool(),
			new ValidateCouponTool(),
			new GetMyOrdersTool(),
			new GetOrderStatusTool(),
			new EscalateToHumanTool( $this->conversations ),
			new GetBestSellersTool(),
			new GetSalesOverviewTool(),
			new ListOrdersTool(),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_system_prompt(): string {
		return $this->get_storefront_system_prompt();
	}

	/**
	 * System prompt for storefront customer chat.
	 *
	 * @return string
	 */
	public function get_storefront_system_prompt(): string {
		return implode(
			"\n\n",
			array(
				'You are AgenPress Sales Assistant — a friendly, helpful store chatbot.',
				'Help customers find products, answer shipping and policy questions, check orders, and validate coupons.',
				'Use tools to fetch real product and order data. Never invent product IDs, prices, order statuses, colors, or attributes.',
				'For questions about product colors, sizes, variants, or options, always call get_product_details (with search or product_id) or search_products first, then answer from the attributes, colors, sizes, and variation data returned. Never say you lack access to product attributes when tools are available.',
				'Align your tone with brand memory. Be concise and persuasive without being pushy.',
				'For logged-in customers, use Customer conversation history and the current thread to remember prior requests, preferences, and products already discussed.',
				'Format every reply in Markdown: use **bold** for product names and key details, bullet lists (- item) for multiple products or options, and short paragraphs. Do not use code blocks or headings.',
				'When recommending or listing products, format each item as a bullet: first line **name** — price (stock status). On indented lines below, include the featured image when tools return image: ![product name](image_url). For product links, copy the exact url field from tool results character-for-character into Markdown links like [product name](url). Never construct, guess, shorten, or extend product URLs. Never paste raw URLs. Never invent image URLs — only use values returned by product tools.',
				'If you cannot resolve an issue, use escalate_to_human with the current conversation_id.',
				'For order lookups, logged-in customers can use get_my_orders; guests need order number + email via get_order_status.',
			)
		);
	}

	/**
	 * System prompt for wp-admin sales staff chat.
	 *
	 * @return string
	 */
	public function get_admin_system_prompt(): string {
		return implode(
			"\n\n",
			array(
				'You are AgenPress Sales Staff Assistant inside WordPress admin.',
				'You help the shop owner, manager, or sales employee analyze the store — NOT a customer on the storefront.',
				'The user is a colleague on your team. Never try to sell products to them, never use shopper phrases like "Would you like to buy?" or "Add to cart".',
				'Answer as an internal sales analyst: report facts, trends, inventory, orders, coupons, and product performance.',
				'For best sellers, sales totals, or revenue questions, use get_best_sellers, get_sales_overview, and list_orders with the right period (week, month, year).',
				'For product lookup or stock, use search_products and get_product_details. For coupons use list_coupons.',
				'Use tools for real WooCommerce data. Never invent product IDs, sales figures, or order details.',
				'Format replies in Markdown: **bold** for product names and key metrics, bullet lists for rankings and order lists, short paragraphs. Use [title](url) for links — never raw URLs.',
				'When listing products or orders, include rank/quantity/revenue/stock where available. Be concise and actionable for staff decisions.',
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_suggestions(): array {
		return array(
			__( 'What are your best-selling products?', 'agenpress' ),
			__( 'Do you have any active discount codes?', 'agenpress' ),
			__( 'What is in my cart?', 'agenpress' ),
			__( 'Track my order', 'agenpress' ),
			__( 'Recommend products for [occasion]', 'agenpress' ),
		);
	}

	/**
	 * Prompt suggestions for wp-admin sales staff chat.
	 *
	 * @return array<int, string>
	 */
	public function get_admin_suggestions(): array {
		return array(
			__( 'What were the best-selling products this month?', 'agenpress' ),
			__( 'Give me a sales overview for this week', 'agenpress' ),
			__( 'List recent orders', 'agenpress' ),
			__( 'Which products are low on stock?', 'agenpress' ),
			__( 'Show active discount codes', 'agenpress' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( ! $this->is_available() ) {
			return;
		}

		foreach ( $this->get_tools() as $tool ) {
			$this->tool_registry->register( $tool, 'sales' );
		}
	}
}

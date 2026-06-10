<?php
/**
 * Context builder for AI prompts with RAG retrieval.
 *
 * @package AgenPress
 */

namespace AgenPress\Memory;

use AgenPress\Sales\ProductCatalog;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContextBuilder
 */
class ContextBuilder {

	/**
	 * Module-to-category mapping for scoped retrieval.
	 *
	 * @var array<string, array<string>>
	 */
	private const MODULE_CATEGORIES = array(
		'admin'     => array( 'brand', 'contact', 'design', 'seo', 'general' ),
		'elementor' => array( 'brand', 'design', 'general' ),
		'sales'     => array( 'brand', 'contact', 'seo', 'general' ),
	);

	/**
	 * Memory store.
	 *
	 * @var MemoryStore
	 */
	private MemoryStore $memory_store;

	/**
	 * Product catalog.
	 *
	 * @var ProductCatalog
	 */
	private ProductCatalog $product_catalog;

	/**
	 * Constructor.
	 *
	 * @param MemoryStore    $memory_store    Memory store.
	 * @param ProductCatalog $product_catalog Product catalog.
	 */
	public function __construct( MemoryStore $memory_store, ProductCatalog $product_catalog ) {
		$this->memory_store    = $memory_store;
		$this->product_catalog = $product_catalog;
	}

	/**
	 * Build full context string for an AI request.
	 *
	 * @param string $module        Module slug.
	 * @param string $system_prompt Module system prompt.
	 * @param string $query         User query for semantic retrieval.
	 * @return string
	 */
	public function build( string $module, string $system_prompt, string $query = '' ): string {
		$categories     = self::MODULE_CATEGORIES[ $module ] ?? self::MODULE_CATEGORIES['admin'];
		$memory_context = $this->memory_store->get_rag_context( $query, $categories );

		$parts = array_filter(
			array(
				$system_prompt,
				$memory_context,
				'sales' === $module ? $this->product_catalog->get_context_string() : '',
				sprintf( 'Current module: %s', $module ),
				sprintf( 'Site: %s', get_bloginfo( 'name' ) ),
			)
		);

		return implode( "\n\n", $parts );
	}
}

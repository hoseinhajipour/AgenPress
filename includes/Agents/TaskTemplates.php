<?php
/**
 * Predefined agentic task templates.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskTemplates
 */
class TaskTemplates {

	/**
	 * Get all templates for API/UI.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function list(): array {
		return array(
			array(
				'id'          => 'seo_articles',
				'name'        => __( 'SEO Articles Batch', 'agenpress' ),
				'description' => __( 'Generate multiple SEO-optimized articles with categories and tags', 'agenpress' ),
				'fields'      => array(
					array( 'key' => 'count', 'label' => __( 'Number of articles', 'agenpress' ), 'type' => 'number', 'default' => 5 ),
					array( 'key' => 'topic', 'label' => __( 'Topic / niche', 'agenpress' ), 'type' => 'text', 'default' => '' ),
					array( 'key' => 'sections_count', 'label' => __( 'Article sections count', 'agenpress' ), 'type' => 'number', 'default' => 4 ),
					array( 'key' => 'featured_image', 'label' => __( 'Generate featured image', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
					array( 'key' => 'section_images', 'label' => __( 'Generate image for each section', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
					array( 'key' => 'include_faq', 'label' => __( 'Include FAQ section', 'agenpress' ), 'type' => 'boolean', 'default' => true ),
					array( 'key' => 'include_conclusion', 'label' => __( 'Include conclusion', 'agenpress' ), 'type' => 'boolean', 'default' => true ),
					array( 'key' => 'publish', 'label' => __( 'Publish when done', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
				),
			),
			array(
				'id'          => 'product_descriptions',
				'name'        => __( 'Product Descriptions', 'agenpress' ),
				'description' => __( 'Generate SEO product descriptions for WooCommerce products', 'agenpress' ),
				'fields'      => array(
					array( 'key' => 'count', 'label' => __( 'Number of products', 'agenpress' ), 'type' => 'number', 'default' => 5 ),
					array( 'key' => 'niche', 'label' => __( 'Product niche', 'agenpress' ), 'type' => 'text', 'default' => '' ),
				),
			),
			array(
				'id'          => 'custom',
				'name'        => __( 'Custom Task', 'agenpress' ),
				'description' => __( 'AI-planned multi-step task from your description', 'agenpress' ),
				'fields'      => array(),
			),
		);
	}

	/**
	 * Match a template from title and description.
	 *
	 * @param string $title       Task title.
	 * @param string $description Task description.
	 * @param string $template_id   Explicit template ID.
	 * @return string
	 */
	public static function resolve( string $title, string $description, string $template_id = '' ): string {
		if ( '' !== $template_id && self::exists( $template_id ) ) {
			return $template_id;
		}

		$text = strtolower( $title . ' ' . $description );

		if ( preg_match( '/\d+\s+.*(?:article|post|مقاله|blog)/u', $text ) || str_contains( $text, 'seo article' ) ) {
			return 'seo_articles';
		}

		if ( class_exists( 'WooCommerce' ) && ( str_contains( $text, 'product' ) || str_contains( $text, 'woocommerce' ) ) ) {
			return 'product_descriptions';
		}

		return 'custom';
	}

	/**
	 * Build steps for a template.
	 *
	 * @param string               $template_id Template ID.
	 * @param string               $title       Task title.
	 * @param string               $description Task description.
	 * @param array<string, mixed> $params      Template parameters.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_steps( string $template_id, string $title, string $description, array $params = array() ): array {
		return match ( $template_id ) {
			'seo_articles'         => self::seo_article_steps( $title, $description, $params ),
			'product_descriptions' => self::product_description_steps( $description, $params ),
			default                => self::custom_steps( $title, $description ),
		};
	}

	/**
	 * Check if template exists.
	 *
	 * @param string $id Template ID.
	 * @return bool
	 */
	public static function exists( string $id ): bool {
		foreach ( self::list() as $template ) {
			if ( $template['id'] === $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse count from text.
	 *
	 * @param string $text Text.
	 * @param int    $default Default count.
	 * @return int
	 */
	public static function parse_count( string $text, int $default = 5 ): int {
		if ( preg_match( '/(\d+)\s*(?:seo\s+)?(?:article|post|مقاله|blog)/iu', $text, $matches ) ) {
			return min( 20, max( 1, (int) $matches[1] ) );
		}

		return min( 20, max( 1, (int) $default ) );
	}

	/**
	 * Parse topic from text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function parse_topic( string $text ): string {
		if ( preg_match( '/(?:about|on|regarding|موضوع)\s+(.+?)(?:\.|$)/iu', $text, $matches ) ) {
			return trim( $matches[1] );
		}

		return trim( $text );
	}

	/**
	 * SEO articles batch steps.
	 *
	 * @param string               $title       Title.
	 * @param string               $description Description.
	 * @param array<string, mixed> $params      Params.
	 * @return array<int, array<string, mixed>>
	 */
	private static function seo_article_steps( string $title, string $description, array $params ): array {
		$text    = $title . ' ' . $description;
		$count   = (int) ( $params['count'] ?? self::parse_count( $text ) );
		$topic   = sanitize_text_field( (string) ( $params['topic'] ?? self::parse_topic( $description ?: $title ) ) );
		$publish = ! empty( $params['publish'] );

		$article_options = array(
			'sections_count'     => min( 10, max( 2, (int) ( $params['sections_count'] ?? 4 ) ) ),
			'featured_image'     => ! empty( $params['featured_image'] ),
			'section_images'     => ! empty( $params['section_images'] ),
			'include_faq'        => array_key_exists( 'include_faq', $params ) ? ! empty( $params['include_faq'] ) : true,
			'include_conclusion' => array_key_exists( 'include_conclusion', $params ) ? ! empty( $params['include_conclusion'] ) : true,
		);

		$steps = array(
			array(
				'type'        => 'ai',
				'name'        => 'plan',
				'label'       => __( 'Planning article topics', 'agenpress' ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'prompt'      => sprintf(
					'Plan %d unique SEO blog article titles about "%s". Return ONLY a JSON array of strings, each title. No markdown.',
					$count,
					$topic
				),
				'output_key'  => 'article_titles',
			),
		);

		for ( $i = 1; $i <= $count; $i++ ) {
			$steps[] = array(
				'type'        => 'seo_article',
				'name'        => 'article_' . $i,
				'label'       => sprintf(
					/* translators: %d: article number */
					__( 'Creating SEO article %d', 'agenpress' ),
					$i
				),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'index'       => $i - 1,
				'topic'       => $topic,
				'publish'     => $publish,
				'options'     => $article_options,
			);
		}

		$steps[] = array(
			'type'        => 'ai',
			'name'        => 'summary',
			'label'       => __( 'Generating task summary', 'agenpress' ),
			'status'      => 'pending',
			'retries'     => 0,
			'max_retries' => 1,
			'prompt'      => 'Summarize the completed article batch task in 2-3 sentences.',
			'output_key'  => 'summary',
		);

		return $steps;
	}

	/**
	 * Product description batch steps.
	 *
	 * @param string               $description Description.
	 * @param array<string, mixed> $params      Params.
	 * @return array<int, array<string, mixed>>
	 */
	private static function product_description_steps( string $description, array $params ): array {
		$count = (int) ( $params['count'] ?? self::parse_count( $description, 3 ) );
		$niche = sanitize_text_field( (string) ( $params['niche'] ?? self::parse_topic( $description ) ) );

		$steps = array(
			array(
				'type'        => 'tool',
				'name'        => 'list_products',
				'label'       => __( 'Fetching existing products', 'agenpress' ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'tool'        => 'list_products',
				'args'        => array( 'limit' => $count, 'status' => 'any' ),
				'output_key'  => 'products',
			),
		);

		for ( $i = 1; $i <= $count; $i++ ) {
			$steps[] = array(
				'type'        => 'product_description',
				'name'        => 'product_' . $i,
				'label'       => sprintf( __( 'Writing product description %d', 'agenpress' ), $i ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'index'       => $i - 1,
				'niche'       => $niche,
			);
		}

		return $steps;
	}

	/**
	 * Custom task steps (AI-planned fallback).
	 *
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @return array<int, array<string, mixed>>
	 */
	private static function custom_steps( string $title, string $description ): array {
		return array(
			array(
				'type'        => 'ai_plan',
				'name'        => 'ai_plan',
				'label'       => __( 'AI planning task steps', 'agenpress' ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'title'       => $title,
				'description' => $description,
			),
			array(
				'type'        => 'ai',
				'name'        => 'execute',
				'label'       => __( 'Executing planned work', 'agenpress' ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'prompt'      => sprintf(
					'Execute this WordPress admin task and describe what was done: Title: %s. Description: %s',
					$title,
					$description
				),
				'output_key'  => 'result',
			),
		);
	}
}

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
					array( 'key' => 'suggest_services', 'label' => __( 'Suggest services in article', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
					array( 'key' => 'suggest_products', 'label' => __( 'Suggest products in article', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
					array( 'key' => 'publish', 'label' => __( 'Publish when done', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
				),
			),
			array(
				'id'          => 'product_descriptions',
				'name'        => __( 'Product Descriptions', 'agenpress' ),
				'description' => __( 'Create or update WooCommerce products with SEO copy and AI images', 'agenpress' ),
				'fields'      => array(
					array( 'key' => 'count', 'label' => __( 'Number of products', 'agenpress' ), 'type' => 'number', 'default' => 5 ),
					array( 'key' => 'niche', 'label' => __( 'Product niche', 'agenpress' ), 'type' => 'text', 'default' => '' ),
					array( 'key' => 'create_new', 'label' => __( 'Create new products (with images)', 'agenpress' ), 'type' => 'boolean', 'default' => true ),
					array( 'key' => 'publish', 'label' => __( 'Publish when done', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
				),
			),
			array(
				'id'          => 'site_pages',
				'name'        => __( 'Elementor Site Pages', 'agenpress' ),
				'description' => __( 'Create Elementor pages with AI banners, sections, and widgets', 'agenpress' ),
				'fields'      => array(
					array( 'key' => 'brand', 'label' => __( 'Store / brand name', 'agenpress' ), 'type' => 'text', 'default' => '' ),
					array( 'key' => 'colors', 'label' => __( 'Brand colors', 'agenpress' ), 'type' => 'text', 'default' => '' ),
					array( 'key' => 'banner_count', 'label' => __( 'Home page banner count', 'agenpress' ), 'type' => 'number', 'default' => 2 ),
					array( 'key' => 'publish', 'label' => __( 'Publish when done', 'agenpress' ), 'type' => 'boolean', 'default' => false ),
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

		if ( class_exists( '\Elementor\Plugin' ) && self::looks_like_site_pages_request( $text ) ) {
			return 'site_pages';
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
			'site_pages'           => self::site_pages_steps( $title, $description, $params ),
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
	 * Parse count from text (articles/posts).
	 *
	 * @param string $text Text.
	 * @param int    $default Default count.
	 * @return int
	 */
	public static function parse_count( string $text, int $default = 1 ): int {
		$parsed = self::parse_article_count( $text );

		return null !== $parsed ? $parsed : min( 20, max( 1, (int) $default ) );
	}

	/**
	 * Parse product count from text (supports Persian: یک عدد محصول).
	 *
	 * @param string $text Text.
	 * @return int|null
	 */
	public static function parse_product_count( string $text ): ?int {
		return self::parse_quantity_near_keyword( $text, array( 'محصول', 'محصولات', 'product', 'products' ) );
	}

	/**
	 * Parse article/post count from text.
	 *
	 * @param string $text Text.
	 * @return int|null
	 */
	public static function parse_article_count( string $text ): ?int {
		return self::parse_quantity_near_keyword( $text, array( 'پست', 'پست‌ها', 'مقاله', 'مقالات', 'article', 'articles', 'post', 'posts', 'blog' ) );
	}

	/**
	 * Parse a quantity near a keyword in Persian or English.
	 *
	 * @param string              $text     Text.
	 * @param array<int, string>  $keywords Keywords.
	 * @return int|null
	 */
	private static function parse_quantity_near_keyword( string $text, array $keywords ): ?int {
		$keyword_pattern = implode( '|', array_map( 'preg_quote', $keywords ) );
		$number_pattern  = self::number_token_pattern();

		$patterns = array(
			'/(' . $number_pattern . ')\s*(?:عدد\s*)?(?:' . $keyword_pattern . ')/iu',
			'/(?:' . $keyword_pattern . ').{0,30}?(' . $number_pattern . ')\s*عدد/iu',
			'/(\d+)\s*(?:' . $keyword_pattern . ')/iu',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text, $matches ) ) {
				$token = trim( (string) ( $matches[1] ?? '' ) );

				if ( '' !== $token ) {
					return min( 20, max( 1, self::normalize_number_token( $token ) ) );
				}
			}
		}

		return null;
	}

	/**
	 * Regex fragment for Persian/English number tokens.
	 *
	 * @return string
	 */
	private static function number_token_pattern(): string {
		return '\d+|یک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده';
	}

	/**
	 * Convert a number token to integer.
	 *
	 * @param string $token Token.
	 * @return int
	 */
	private static function normalize_number_token( string $token ): int {
		$map = array(
			'یک'   => 1,
			'دو'   => 2,
			'سه'   => 3,
			'چهار' => 4,
			'پنج'  => 5,
			'شش'   => 6,
			'هفت'  => 7,
			'هشت'  => 8,
			'نه'   => 9,
			'ده'   => 10,
		);

		if ( isset( $map[ $token ] ) ) {
			return $map[ $token ];
		}

		return (int) $token;
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
			'suggest_services'   => ! empty( $params['suggest_services'] ),
			'suggest_products'   => ! empty( $params['suggest_products'] ),
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
		$product_ids = self::normalize_product_ids( $params['product_ids'] ?? array() );
		$count       = ! empty( $product_ids )
			? count( $product_ids )
			: (int) ( $params['count'] ?? self::parse_count( $description, 3 ) );
		$niche       = sanitize_text_field( (string) ( $params['niche'] ?? self::parse_topic( $description ) ) );
		$create_new  = ! empty( $product_ids )
			? false
			: ( array_key_exists( 'create_new', $params )
				? ! empty( $params['create_new'] )
				: self::should_create_products( $description ) );
		$publish     = ! empty( $params['publish'] );

		$steps = array();

		if ( ! $create_new && empty( $product_ids ) ) {
			$steps[] = array(
				'type'        => 'tool',
				'name'        => 'list_products',
				'label'       => __( 'Fetching existing products', 'agenpress' ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'tool'        => 'list_products',
				'args'        => array( 'limit' => $count, 'status' => 'any' ),
				'output_key'  => 'products',
			);
		}

		for ( $i = 1; $i <= $count; $i++ ) {
			$step = array(
				'type'        => 'product_description',
				'name'        => 'product_' . $i,
				'label'       => $create_new
					? sprintf( __( 'Creating product %d with image', 'agenpress' ), $i )
					: sprintf( __( 'Writing product description %d', 'agenpress' ), $i ),
				'status'      => 'pending',
				'retries'     => 0,
				'max_retries' => 2,
				'index'       => $i - 1,
				'niche'       => $niche,
				'brief'       => $description,
				'create_new'  => $create_new,
				'publish'     => $publish,
			);

			if ( ! empty( $product_ids[ $i - 1 ] ) ) {
				$step['product_id'] = (int) $product_ids[ $i - 1 ];
				$step['label']      = sprintf(
					/* translators: %s: product title */
					__( 'SEO update: %s', 'agenpress' ),
					get_the_title( (int) $product_ids[ $i - 1 ] ) ?: sprintf( '#%d', (int) $product_ids[ $i - 1 ] )
				);
			}

			$steps[] = $step;
		}

		return $steps;
	}

	/**
	 * Normalize product ID list from task params.
	 *
	 * @param mixed $product_ids Raw product IDs.
	 * @return array<int, int>
	 */
	public static function normalize_product_ids( mixed $product_ids ): array {
		if ( ! is_array( $product_ids ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $product_ids as $product_id ) {
			$product_id = (int) $product_id;

			if ( $product_id > 0 ) {
				$normalized[] = $product_id;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Whether the request asks to create new products (not update existing).
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function should_create_products( string $text ): bool {
		return (bool) preg_match(
			'/(?:ایجاد|ساخت|create|add|new|نمونه|پیش‌فرض|sample).{0,40}(?:محصول|product)|(?:محصول|product).{0,40}(?:ایجاد|ساخت|create|new|نمونه|پیش‌فرض|sample)/iu',
			$text
		);
	}

	/**
	 * Elementor site pages build steps.
	 *
	 * @param string               $title       Title.
	 * @param string               $description Description.
	 * @param array<string, mixed> $params      Params.
	 * @return array<int, array<string, mixed>>
	 */
	private static function site_pages_steps( string $title, string $description, array $params ): array {
		$text         = $title . "\n" . $description;
		$brief        = (string) ( $params['brief'] ?? $description );
		$brand        = sanitize_text_field( (string) ( $params['brand'] ?? self::parse_brand( $text ) ) );
		$colors       = sanitize_text_field( (string) ( $params['colors'] ?? self::parse_colors( $text ) ) );
		$banner_count = (int) ( $params['banner_count'] ?? self::parse_banner_count( $text ) );
		$publish      = ! empty( $params['publish'] );
		$pages        = is_array( $params['pages'] ?? null ) ? $params['pages'] : self::detect_site_pages( $text );

		if ( empty( $pages ) ) {
			$pages = self::default_site_pages();
		}

		$steps = array();

		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			$page_type  = sanitize_key( (string) ( $page['type'] ?? 'page' ) );
			$page_title = sanitize_text_field( (string) ( $page['title'] ?? ucfirst( $page_type ) ) );
			$page_slug  = sanitize_title( (string) ( $page['slug'] ?? $page_type ) );

			$steps[] = array(
				'type'         => 'site_page',
				'name'         => 'page_' . $page_slug,
				'label'        => sprintf(
					/* translators: %s: page title */
					__( 'Building Elementor page: %s', 'agenpress' ),
					$page_title
				),
				'status'       => 'pending',
				'retries'      => 0,
				'max_retries'  => 2,
				'page_type'    => $page_type,
				'page_title'   => $page_title,
				'page_slug'    => $page_slug,
				'brief'        => $brief,
				'brand'        => $brand,
				'colors'       => $colors,
				'banner_count' => 'home' === $page_type ? max( 0, $banner_count ) : 0,
				'publish'      => $publish,
			);
		}

		return $steps;
	}

	/**
	 * Whether text describes a multi-page site build.
	 *
	 * @param string $text Lowercased text.
	 * @return bool
	 */
	public static function looks_like_site_pages_request( string $text ): bool {
		if ( preg_match( '/\b(?:site_pages|elementor\s*pages?)\b/', $text ) ) {
			return true;
		}

		$signals = 0;
		$patterns = array(
			'/\b(?:صفحه|pages?)\b/u',
			'/\b(?:خانه|home|فروشگاه|shop|وبلاگ|blog|تماس|contact|درباره|about)\b/u',
			'/\b(?:طراحی|design|layout|elementor|بنر|banner|اسلایدر|slider)\b/u',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				++$signals;
			}
		}

		return $signals >= 2;
	}

	/**
	 * Detect pages requested in text.
	 *
	 * @param string $text Text.
	 * @return array<int, array{type: string, title: string, slug: string}>
	 */
	public static function detect_site_pages( string $text ): array {
		$map = array(
			array( 'pattern' => '/(?:صفحه\s*خانه|home\s*page|صفحه\s*اصلی)/iu', 'type' => 'home', 'title' => __( 'Home', 'agenpress' ), 'slug' => 'home' ),
			array( 'pattern' => '/(?:صفحه\s*فروشگاه|shop\s*page|فروشگاه\s*آنلاین)/iu', 'type' => 'shop', 'title' => __( 'Shop', 'agenpress' ), 'slug' => 'shop' ),
			array( 'pattern' => '/(?:صفحه\s*وبلاگ|blog\s*page)/iu', 'type' => 'blog', 'title' => __( 'Blog', 'agenpress' ), 'slug' => 'blog' ),
			array( 'pattern' => '/(?:تماس\s*با\s*ما|contact\s*us|contact\s*page)/iu', 'type' => 'contact', 'title' => __( 'Contact Us', 'agenpress' ), 'slug' => 'contact' ),
			array( 'pattern' => '/(?:درباره\s*(?:ما|ی)?|about\s*us|about\s*page)/iu', 'type' => 'about', 'title' => __( 'About Us', 'agenpress' ), 'slug' => 'about' ),
		);

		$pages = array();

		foreach ( $map as $item ) {
			if ( preg_match( $item['pattern'], $text ) ) {
				$pages[] = array(
					'type'  => $item['type'],
					'title' => $item['title'],
					'slug'  => $item['slug'],
				);
			}
		}

		return $pages;
	}

	/**
	 * Default store pages.
	 *
	 * @return array<int, array{type: string, title: string, slug: string}>
	 */
	public static function default_site_pages(): array {
		return array(
			array( 'type' => 'home', 'title' => __( 'Home', 'agenpress' ), 'slug' => 'home' ),
			array( 'type' => 'shop', 'title' => __( 'Shop', 'agenpress' ), 'slug' => 'shop' ),
			array( 'type' => 'blog', 'title' => __( 'Blog', 'agenpress' ), 'slug' => 'blog' ),
			array( 'type' => 'contact', 'title' => __( 'Contact Us', 'agenpress' ), 'slug' => 'contact' ),
			array( 'type' => 'about', 'title' => __( 'About Us', 'agenpress' ), 'slug' => 'about' ),
		);
	}

	/**
	 * Parse brand name from text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function parse_brand( string $text ): string {
		if ( preg_match( '/(?:نام\s*فروشگاه|store\s*name|brand)\s*[:：]\s*(.+?)(?:\n|$)/iu', $text, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) );
		}

		return '';
	}

	/**
	 * Parse brand colors from text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function parse_colors( string $text ): string {
		if ( preg_match( '/(?:رنگ\s*سازمانی|brand\s*colors?)\s*[:：]\s*(.+?)(?:\n|$)/iu', $text, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) );
		}

		return '';
	}

	/**
	 * Parse home banner count from text.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function parse_banner_count( string $text, int $default = 2 ): int {
		$number_pattern = self::number_token_pattern();

		if ( preg_match( '/اسلایدر\s*\(?\s*(' . $number_pattern . ')/iu', $text, $matches ) ) {
			return min( 5, max( 0, self::normalize_number_token( trim( $matches[1] ) ) ) );
		}

		if ( preg_match( '/\((\s*(' . $number_pattern . ')\s*عدد\s*)\)/iu', $text, $matches ) ) {
			return min( 5, max( 0, self::normalize_number_token( trim( $matches[2] ) ) ) );
		}

		if ( preg_match( '/(' . $number_pattern . ')\s*عدد\s*(?:banner|بنر|اسلایدر)/iu', $text, $matches ) ) {
			return min( 5, max( 0, self::normalize_number_token( trim( $matches[1] ) ) ) );
		}

		if ( preg_match( '/(\d+)\s*(?:عدد|banner|بنر)/iu', $text, $matches ) ) {
			return min( 5, max( 0, (int) $matches[1] ) );
		}

		return $default;
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

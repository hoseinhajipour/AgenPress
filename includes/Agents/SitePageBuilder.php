<?php
/**
 * Builds Elementor store pages during agent tasks.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\AI\ProviderFactory;
use AgenPress\Modules\Elementor\ElementorDocumentService;

defined( 'ABSPATH' ) || exit;

/**
 * Class SitePageBuilder
 */
class SitePageBuilder {

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Elementor documents.
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
	 * @param ElementorDocumentService $documents        Elementor documents.
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
	 * Build one site page from a task step.
	 *
	 * @param array<string, mixed> $step    Step definition.
	 * @param int                  $user_id User ID.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	public function build( array $step, int $user_id ): array {
		if ( ! $this->documents->is_available() ) {
			return array(
				'success'         => false,
				'message'         => __( 'Elementor is not active. Cannot build pages.', 'agenpress' ),
				'data'            => null,
				'context_updates' => array(),
			);
		}

		$page_type   = sanitize_key( (string) ( $step['page_type'] ?? 'page' ) );
		$page_title  = sanitize_text_field( (string) ( $step['page_title'] ?? 'Page' ) );
		$page_slug   = sanitize_title( (string) ( $step['page_slug'] ?? $page_type ) );
		$brief       = (string) ( $step['brief'] ?? '' );
		$brand       = sanitize_text_field( (string) ( $step['brand'] ?? '' ) );
		$colors      = sanitize_text_field( (string) ( $step['colors'] ?? '' ) );
		$banner_count = max( 0, (int) ( $step['banner_count'] ?? 0 ) );
		$publish     = ! empty( $step['publish'] );
		$status      = $publish ? 'publish' : 'draft';

		$post_id = $this->ensure_page( $page_slug, $page_title, $status, $user_id );

		if ( $post_id <= 0 ) {
			return array(
				'success'         => false,
				'message'         => __( 'Failed to create WordPress page.', 'agenpress' ),
				'data'            => null,
				'context_updates' => array(),
			);
		}

		if ( ! $this->documents->ensure_builder_page( $post_id ) ) {
			return array(
				'success'         => false,
				'message'         => __( 'Failed to enable Elementor on the page.', 'agenpress' ),
				'data'            => null,
				'context_updates' => array(),
			);
		}

		$logs    = array();
		$context = array(
			'brand'  => $brand,
			'colors' => $colors,
			'brief'  => $brief,
		);

		if ( $banner_count > 0 ) {
			$banner_logs = $this->generate_banners( $post_id, $banner_count, $page_title, $context, $user_id );
			$logs        = array_merge( $logs, $banner_logs );
		}

		$build_result = match ( $page_type ) {
			'home'    => $this->build_home_page( $post_id, $context, $user_id ),
			'contact' => $this->build_contact_page( $post_id, $context, $user_id ),
			'about'   => $this->build_about_page( $post_id, $context, $user_id ),
			default   => $this->build_simple_page( $post_id, $page_title, $page_type, $context, $user_id ),
		};

		$logs = array_merge( $logs, $build_result['logs'] ?? array() );

		if ( ! $build_result['success'] ) {
			return array(
				'success'         => false,
				'message'         => $build_result['message'],
				'data'            => array( 'post_id' => $post_id, 'logs' => $logs ),
				'context_updates' => array(),
			);
		}

		$url = get_permalink( $post_id );

		return array(
			'success'         => true,
			'message'         => sprintf(
				/* translators: 1: page title, 2: page URL */
				__( 'Built Elementor page "%1$s": %2$s', 'agenpress' ),
				$page_title,
				$url ?: '#' . $post_id
			),
			'data'            => array(
				'post_id' => $post_id,
				'url'     => $url,
				'title'   => $page_title,
				'type'    => $page_type,
				'logs'    => $logs,
			),
			'context_updates' => array(
				'last_page_id' => $post_id,
			),
		);
	}

	/**
	 * Ensure a WordPress page exists.
	 *
	 * @param string $slug    Page slug.
	 * @param string $title   Page title.
	 * @param string $status  Post status.
	 * @param int    $user_id User ID.
	 * @return int Post ID or 0.
	 */
	private function ensure_page( string $slug, string $title, string $status, int $user_id ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );

		if ( $existing instanceof \WP_Post ) {
			wp_update_post(
				array(
					'ID'          => $existing->ID,
					'post_title'  => $title,
					'post_status' => $status,
				)
			);

			return (int) $existing->ID;
		}

		$result = $this->tool_registry->execute(
			'create_post',
			array(
				'title'     => $title,
				'content'   => '',
				'post_type' => 'page',
				'status'    => $status,
			),
			$user_id,
			'admin'
		);

		if ( empty( $result['success'] ) || empty( $result['data']['id'] ) ) {
			return 0;
		}

		$post_id = (int) $result['data']['id'];

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => $slug,
			)
		);

		return $post_id;
	}

	/**
	 * Generate banner images on a page.
	 *
	 * @param int                  $post_id      Post ID.
	 * @param int                  $count        Banner count.
	 * @param string               $page_title   Page title.
	 * @param array<string, mixed> $context      Build context.
	 * @param int                  $user_id      User ID.
	 * @return array<int, string>
	 */
	private function generate_banners( int $post_id, int $count, string $page_title, array $context, int $user_id ): array {
		$logs   = array();
		$brand  = (string) ( $context['brand'] ?? '' );
		$colors = (string) ( $context['colors'] ?? 'brown and white' );

		for ( $i = 1; $i <= $count; $i++ ) {
			$prompt = sprintf(
				'Professional e-commerce hero banner for "%s" online store%s. Slide %d of %d. Brand colors: %s. Modern layout with product imagery (bags and shoes), elegant typography, promotional feel. Render headline text inside the image. No watermarks.',
				$brand ?: $page_title,
				$brand ? " ({$brand})" : '',
				$i,
				$count,
				$colors
			);

			$result = $this->tool_registry->execute(
				'generate_section_image',
				array(
					'prompt'  => $prompt,
					'post_id' => $post_id,
					'size'    => '16:9',
				),
				$user_id,
				'elementor'
			);

			$logs[] = $result['message'] . ( $result['success'] ? '' : ' [' . __( 'FAILED', 'agenpress' ) . ']' );
		}

		return $logs;
	}

	/**
	 * Build home page sections after banners.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $context Context.
	 * @param int                  $user_id User ID.
	 * @return array{success: bool, message: string, logs: array<int, string>}
	 */
	private function build_home_page( int $post_id, array $context, int $user_id ): array {
		$logs = array();

		$categories = array(
			array( 'title' => __( 'Women Bags', 'agenpress' ), 'icon' => 'fas fa-shopping-bag' ),
			array( 'title' => __( 'Men Shoes', 'agenpress' ), 'icon' => 'fas fa-shoe-prints' ),
			array( 'title' => __( 'Women Shoes', 'agenpress' ), 'icon' => 'fas fa-heart' ),
			array( 'title' => __( 'Men Bags', 'agenpress' ), 'icon' => 'fas fa-briefcase' ),
		);

		$section = $this->documents->create_section( $post_id, array( 'padding' => array( 'unit' => 'px', 'top' => '40', 'bottom' => '20' ) ) );

		if ( empty( $section['success'] ) || empty( $section['column_id'] ) ) {
			return array(
				'success' => false,
				'message' => $section['message'] ?? __( 'Failed to create category section.', 'agenpress' ),
				'logs'    => $logs,
			);
		}

		$column_id = (string) $section['column_id'];
		$logs[]    = $section['message'];

		$this->documents->create_widget(
			$post_id,
			$column_id,
			'heading',
			array(
				'title' => __( 'Shop by Category', 'agenpress' ),
			)
		);

		foreach ( $categories as $category ) {
			$widget = $this->documents->create_widget(
				$post_id,
				$column_id,
				'icon-box',
				array(
					'title_text'       => $category['title'],
					'description_text' => '',
					'selected_icon'    => array(
						'value'   => $category['icon'],
						'library' => 'fa-solid',
					),
				)
			);
			$logs[] = $widget['message'] ?? '';
		}

		$products_section = $this->documents->create_section( $post_id );
		if ( ! empty( $products_section['column_id'] ) ) {
			$col = (string) $products_section['column_id'];
			$this->documents->create_widget( $post_id, $col, 'heading', array( 'title' => __( 'Latest Products', 'agenpress' ) ) );

			$products_html = class_exists( 'WooCommerce' )
				? '[products limit="4" columns="4" orderby="date" order="DESC"]'
				: '<p>' . esc_html__( 'Add WooCommerce products to display the latest items here.', 'agenpress' ) . '</p>';

			$this->documents->create_widget(
				$post_id,
				$col,
				'text-editor',
				array( 'editor' => $products_html )
			);
		}

		$posts_section = $this->documents->create_section( $post_id );
		if ( ! empty( $posts_section['column_id'] ) ) {
			$col = (string) $posts_section['column_id'];
			$this->documents->create_widget( $post_id, $col, 'heading', array( 'title' => __( 'Latest Articles', 'agenpress' ) ) );

			$posts_result = $this->tool_registry->execute(
				'list_posts',
				array( 'limit' => 4, 'post_type' => 'post', 'post_status' => 'publish' ),
				$user_id,
				'admin'
			);

			$posts_html = $this->format_posts_list( $posts_result['data'] ?? array() );
			$this->documents->create_widget( $post_id, $col, 'text-editor', array( 'editor' => $posts_html ) );
		}

		return array(
			'success' => true,
			'message' => __( 'Home page sections created.', 'agenpress' ),
			'logs'    => $logs,
		);
	}

	/**
	 * Build contact page with icon boxes and map embed.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $context Context.
	 * @param int                  $user_id User ID.
	 * @return array{success: bool, message: string, logs: array<int, string>}
	 */
	private function build_contact_page( int $post_id, array $context, int $user_id ): array {
		$brief   = (string) ( $context['brief'] ?? '' );
		$phone   = $this->extract_pattern( $brief, '/(?:شماره\s*تماس|phone)\s*[:：]?\s*([0-9+\-\s]+)/iu', '0917' );
		$address = $this->extract_pattern( $brief, '/(?:آدرس|address|موقعیت)\s*[:：]?\s*(.+?)(?:\n|$)/iu', 'Dubai, Nad Al Hamar' );
		$brand   = (string) ( $context['brand'] ?? __( 'Contact Us', 'agenpress' ) );

		$section = $this->documents->create_section( $post_id );
		if ( empty( $section['column_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create contact section.', 'agenpress' ),
				'logs'    => array(),
			);
		}

		$column_id = (string) $section['column_id'];

		$this->documents->create_widget( $post_id, $column_id, 'heading', array( 'title' => $brand ) );

		$items = array(
			array(
				'title' => __( 'Address', 'agenpress' ),
				'text'  => $address,
				'icon'  => 'fas fa-map-marker-alt',
			),
			array(
				'title' => __( 'Phone', 'agenpress' ),
				'text'  => $phone,
				'icon'  => 'fas fa-phone',
			),
		);

		foreach ( $items as $item ) {
			$this->documents->create_widget(
				$post_id,
				$column_id,
				'icon-box',
				array(
					'title_text'       => $item['title'],
					'description_text' => $item['text'],
					'selected_icon'    => array(
						'value'   => $item['icon'],
						'library' => 'fa-solid',
					),
				)
			);
		}

		$map_query = rawurlencode( $address );
		$map_html  = sprintf(
			'<iframe src="https://maps.google.com/maps?q=%s&amp;t=&amp;z=13&amp;ie=UTF8&amp;iwloc=&amp;output=embed" width="100%%" height="360" style="border:0;" loading="lazy"></iframe>',
			esc_attr( $map_query )
		);

		$this->documents->create_widget( $post_id, $column_id, 'text-editor', array( 'editor' => $map_html ) );

		return array(
			'success' => true,
			'message' => __( 'Contact page created.', 'agenpress' ),
			'logs'    => array(),
		);
	}

	/**
	 * Build about page with AI-generated SEO content.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $context Context.
	 * @param int                  $user_id User ID.
	 * @return array{success: bool, message: string, logs: array<int, string>}
	 */
	private function build_about_page( int $post_id, array $context, int $user_id ): array {
		$brand = (string) ( $context['brand'] ?? get_bloginfo( 'name' ) );
		$brief = (string) ( $context['brief'] ?? '' );

		$content = $this->generate_page_copy(
			sprintf(
				'Write SEO-friendly HTML about page content (2-3 sections with <h2> and <p>) for online store "%s". Use the same language as this brief. Brief: %s. Return ONLY HTML, no markdown.',
				$brand,
				mb_substr( $brief, 0, 800 )
			)
		);

		$section = $this->documents->create_section( $post_id );
		if ( empty( $section['column_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create about section.', 'agenpress' ),
				'logs'    => array(),
			);
		}

		$column_id = (string) $section['column_id'];
		$this->documents->create_widget( $post_id, $column_id, 'heading', array( 'title' => __( 'About Us', 'agenpress' ) ) );
		$this->documents->create_widget( $post_id, $column_id, 'text-editor', array( 'editor' => wp_kses_post( $content ) ) );

		return array(
			'success' => true,
			'message' => __( 'About page created.', 'agenpress' ),
			'logs'    => array(),
		);
	}

	/**
	 * Build a simple shop/blog page.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param string               $page_title Page title.
	 * @param string               $page_type  Page type.
	 * @param array<string, mixed> $context    Context.
	 * @param int                  $user_id    User ID.
	 * @return array{success: bool, message: string, logs: array<int, string>}
	 */
	private function build_simple_page( int $post_id, string $page_title, string $page_type, array $context, int $user_id ): array {
		$section = $this->documents->create_section( $post_id );
		if ( empty( $section['column_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create page section.', 'agenpress' ),
				'logs'    => array(),
			);
		}

		$column_id = (string) $section['column_id'];
		$this->documents->create_widget( $post_id, $column_id, 'heading', array( 'title' => $page_title ) );

		$editor = match ( $page_type ) {
			'shop' => class_exists( 'WooCommerce' )
				? '[products limit="12" columns="4" paginate="true"]'
				: '<p>' . esc_html__( 'Install WooCommerce to show products here.', 'agenpress' ) . '</p>',
			'blog' => '',
			default => '<p>' . esc_html( $page_title ) . '</p>',
		};

		if ( 'blog' === $page_type ) {
			$posts_result = $this->tool_registry->execute(
				'list_posts',
				array( 'limit' => 8, 'post_type' => 'post', 'post_status' => 'publish' ),
				$user_id,
				'admin'
			);
			$editor = $this->format_posts_list( $posts_result['data'] ?? array(), true );
		}

		$this->documents->create_widget( $post_id, $column_id, 'text-editor', array( 'editor' => $editor ) );

		return array(
			'success' => true,
			'message' => sprintf( __( '%s page created.', 'agenpress' ), $page_title ),
			'logs'    => array(),
		);
	}

	/**
	 * Format posts as HTML list.
	 *
	 * @param mixed $posts    Posts data.
	 * @param bool  $detailed Include excerpts.
	 * @return string
	 */
	private function format_posts_list( mixed $posts, bool $detailed = false ): string {
		if ( ! is_array( $posts ) || empty( $posts ) ) {
			return '<p>' . esc_html__( 'Blog posts will appear here after articles are published.', 'agenpress' ) . '</p>';
		}

		$html = '<ul>';

		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}

			$title = esc_html( (string) ( $post['title'] ?? '' ) );
			$url   = esc_url( (string) ( $post['url'] ?? '#' ) );
			$html .= '<li><a href="' . $url . '">' . $title . '</a>';

			if ( $detailed && ! empty( $post['excerpt'] ) ) {
				$html .= '<br><span>' . esc_html( (string) $post['excerpt'] ) . '</span>';
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Generate HTML copy via AI.
	 *
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function generate_page_copy( string $prompt ): string {
		try {
			$provider = $this->provider_factory->get();
			$response = $provider->chat(
				array(
					array( 'role' => 'user', 'content' => $prompt ),
				)
			);

			$content = trim( (string) ( $response['content'] ?? '' ) );
			$content = preg_replace( '/^```(?:html)?\s*/i', '', $content );
			$content = preg_replace( '/\s*```$/', '', (string) $content );

			return '' !== $content ? $content : '<p>' . esc_html__( 'About our store.', 'agenpress' ) . '</p>';
		} catch ( \Exception $e ) {
			return '<p>' . esc_html__( 'About our store.', 'agenpress' ) . '</p>';
		}
	}

	/**
	 * Extract a value from brief text.
	 *
	 * @param string $text    Text.
	 * @param string $pattern Regex.
	 * @param string $default Default.
	 * @return string
	 */
	private function extract_pattern( string $text, string $pattern, string $default ): string {
		if ( preg_match( $pattern, $text, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) );
		}

		return $default;
	}
}

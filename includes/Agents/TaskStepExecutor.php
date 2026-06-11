<?php
/**
 * Executes individual agent task steps.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\AI\ImageSizeRegistry;
use AgenPress\AI\ProviderFactory;
use AgenPress\Content\FaqSchema;
use AgenPress\Media\AiImageSideloader;
use AgenPress\Memory\ContextBuilder;
use AgenPress\Modules\ContentPrompts;

defined( 'ABSPATH' ) || exit;

/**
 * Class TaskStepExecutor
 */
class TaskStepExecutor {

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Tool registry.
	 *
	 * @var ToolRegistry
	 */
	private ToolRegistry $tool_registry;

	/**
	 * Context builder.
	 *
	 * @var ContextBuilder
	 */
	private ContextBuilder $context_builder;

	/**
	 * Constructor.
	 *
	 * @param ProviderFactory $provider_factory Provider factory.
	 * @param ToolRegistry    $tool_registry    Tool registry.
	 * @param ContextBuilder  $context_builder  Context builder.
	 */
	public function __construct( ProviderFactory $provider_factory, ToolRegistry $tool_registry, ContextBuilder $context_builder ) {
		$this->provider_factory = $provider_factory;
		$this->tool_registry    = $tool_registry;
		$this->context_builder  = $context_builder;
	}

	/**
	 * Execute a single task step.
	 *
	 * @param array<string, mixed> $step    Step definition.
	 * @param int                  $user_id User ID.
	 * @param string               $module  Module slug.
	 * @param array<string, mixed> $context Shared task context.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	public function execute( array $step, int $user_id, string $module, array $context ): array {
		$type = $step['type'] ?? 'tool';

		return match ( $type ) {
			'tool'                => $this->execute_tool( $step, $user_id, $module, $context ),
			'ai'                  => $this->execute_ai( $step, $context, $module ),
			'ai_plan'             => $this->execute_ai_plan( $step, $context, $module ),
			'seo_article'         => $this->execute_seo_article( $step, $user_id, $module, $context ),
			'product_description' => $this->execute_product_description( $step, $user_id, $module, $context ),
			'site_page'           => $this->execute_site_page( $step, $user_id ),
			default               => array(
				'success'         => false,
				'message'         => sprintf( 'Unknown step type: %s', $type ),
				'data'            => null,
				'context_updates' => array(),
			),
		};
	}

	/**
	 * Execute a tool step.
	 *
	 * @param array<string, mixed> $step    Step.
	 * @param int                  $user_id User ID.
	 * @param string               $module  Module.
	 * @param array<string, mixed> $context Context.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	private function execute_tool( array $step, int $user_id, string $module, array $context ): array {
		$tool_name   = $step['tool'] ?? '';
		$args        = $this->resolve_args( $step['args'] ?? array(), $context );
		$tool_module = sanitize_key( (string) ( $step['tool_module'] ?? $module ) );
		$result      = $this->tool_registry->execute( $tool_name, $args, $user_id, $tool_module ?: $module );

		$updates = array();
		if ( ! empty( $step['output_key'] ) && $result['success'] ) {
			$updates[ $step['output_key'] ] = $result['data'];
		}

		return array(
			'success'         => $result['success'],
			'message'         => $result['message'],
			'data'            => $result['data'],
			'context_updates' => $updates,
		);
	}

	/**
	 * Execute an AI prompt step.
	 *
	 * @param array<string, mixed> $step    Step.
	 * @param array<string, mixed> $context Context.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	private function execute_ai( array $step, array $context, string $module = 'admin' ): array {
		$prompt = $this->resolve_string( $step['prompt'] ?? '', $context );

		try {
			$provider = $this->provider_factory->get();
			$system   = $this->context_builder->build(
				$module,
				'You are AgenPress task executor. Be concise and follow instructions exactly.',
				$prompt
			);
			$response = $provider->chat(
				array(
					array( 'role' => 'system', 'content' => $system ),
					array( 'role' => 'user', 'content' => $prompt ),
				)
			);

			$content = trim( $response['content'] ?? '' );
			$updates = array();

			$parsed = $this->try_parse_json( $content );

			if ( ! empty( $step['output_key'] ) ) {
				$updates[ $step['output_key'] ] = null !== $parsed ? $parsed : $content;
			}

			$data = null !== $parsed ? $parsed : $content;

			if ( 'plan' === ( $step['name'] ?? '' ) && is_array( $data ) ) {
				$message = sprintf(
					/* translators: %d: number of planned article titles */
					__( 'Planned %d article titles.', 'agenpress' ),
					count( $data )
				);
			} elseif ( is_string( $data ) ) {
				$message = mb_substr( $data, 0, 200 );
			} else {
				$message = __( 'AI step completed.', 'agenpress' );
			}

			return array(
				'success'         => true,
				'message'         => $message,
				'data'            => $data,
				'context_updates' => $updates,
			);
		} catch ( \Exception $e ) {
			return array(
				'success'         => false,
				'message'         => $e->getMessage(),
				'data'            => null,
				'context_updates' => array(),
			);
		}
	}

	/**
	 * AI planning step for custom tasks.
	 *
	 * @param array<string, mixed> $step    Step.
	 * @param array<string, mixed> $context Context.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	private function execute_ai_plan( array $step, array $context, string $module = 'admin' ): array {
		$title       = $step['title'] ?? '';
		$description = $step['description'] ?? '';

		$prompt = sprintf(
			'Break this WordPress task into 3-5 concrete action items as a JSON array of strings. Title: %s. Description: %s. Return ONLY JSON array.',
			$title,
			$description
		);

		$result = $this->execute_ai(
			array(
				'prompt'     => $prompt,
				'output_key' => 'plan_items',
			),
			$context,
			$module
		);

		if ( $result['success'] ) {
			$result['message'] = __( 'Task plan created.', 'agenpress' );
		}

		return $result;
	}

	/**
	 * Create one SEO article (AI content + create_post).
	 *
	 * @param array<string, mixed> $step    Step.
	 * @param int                  $user_id User ID.
	 * @param string               $module  Module.
	 * @param array<string, mixed> $context Context.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	private function execute_seo_article( array $step, int $user_id, string $module, array $context ): array {
		$index   = (int) ( $step['index'] ?? 0 );
		$topic   = $step['topic'] ?? '';
		$titles  = $context['article_titles'] ?? array();
		$options = is_array( $step['options'] ?? null ) ? $step['options'] : array();

		$title = is_array( $titles ) && isset( $titles[ $index ] )
			? (string) $titles[ $index ]
			: sprintf( '%s — Part %d', $topic, $index + 1 );

		$prompt    = $this->build_seo_article_prompt( $title, $topic, $options );
		$ai_result = $this->execute_ai( array( 'prompt' => $prompt ), $context, $module );

		if ( ! $ai_result['success'] ) {
			return $ai_result;
		}

		$parse_error = '';
		$article     = $this->try_parse_json( $ai_result['data'], $parse_error );

		if ( ! is_array( $article ) ) {
			return array(
				'success'         => false,
				'message'         => sprintf(
					/* translators: %s: parse error detail */
					__( 'Failed to parse generated article: %s', 'agenpress' ),
					$parse_error ?: __( 'invalid JSON structure', 'agenpress' )
				),
				'data'            => array(
					'parse_error'      => $parse_error,
					'response_preview' => $this->preview_ai_response( $ai_result['data'] ),
				),
				'context_updates' => array(),
			);
		}

		$image_logs = $this->generate_seo_article_images( $article, $options, $title );

		$content = $this->assemble_seo_article_content( $article, $options );

		if ( '' === trim( $content ) && ! empty( $article['content'] ) ) {
			$content = FaqSchema::strip_from_content( (string) $article['content'] );
		}

		if ( '' === trim( $content ) ) {
			$sections_count = is_array( $article['sections'] ?? null ) ? count( $article['sections'] ) : 0;

			return array(
				'success'         => false,
				'message'         => sprintf(
					/* translators: %d: number of sections returned by AI */
					__( 'Generated article has no usable content (sections in response: %d).', 'agenpress' ),
					$sections_count
				),
				'data'            => array(
					'parse_error'      => __( 'Article JSON was parsed but sections/content are empty.', 'agenpress' ),
					'sections_count'   => $sections_count,
					'response_preview' => $this->preview_ai_response( $article ),
				),
				'context_updates' => array(),
			);
		}

		$status = ! empty( $step['publish'] ) ? 'publish' : 'draft';

		$tool_result = $this->tool_registry->execute(
			'create_post',
			array(
				'title'      => $article['title'] ?? $title,
				'content'    => $content,
				'excerpt'    => $article['excerpt'] ?? '',
				'categories' => $article['categories'] ?? array(),
				'tags'       => $article['tags'] ?? array(),
				'status'     => $status,
				'post_type'  => 'post',
			),
			$user_id,
			$module
		);

		if ( ! $tool_result['success'] || ! is_array( $tool_result['data'] ) ) {
			return array(
				'success'         => false,
				'message'         => $tool_result['message'],
				'data'            => $tool_result['data'],
				'context_updates' => array(),
			);
		}

		$post_id          = (int) ( $tool_result['data']['id'] ?? 0 );
		$featured_set     = false;
		$faq_schema_saved = false;

		if ( $post_id && ! empty( $options['featured_image'] ) ) {
			$image_prompt = (string) ( $article['featured_image_prompt'] ?? sprintf( 'Professional blog featured image for article: %s', $title ) );
			$featured     = $this->generate_image_attachment(
				$image_prompt,
				__( 'Featured image', 'agenpress' )
			);
			$featured['type'] = 'featured';
			$image_logs[]     = $featured;

			if ( ! empty( $featured['success'] ) && set_post_thumbnail( $post_id, (int) $featured['attachment_id'] ) ) {
				$featured_set = true;
			} elseif ( ! empty( $featured['success'] ) ) {
				$featured['success']  = false;
				$featured['error']    = __( 'Image was generated but could not be set as featured image.', 'agenpress' );
				$featured['error_code'] = 'thumbnail_failed';
				$image_logs[ count( $image_logs ) - 1 ] = $featured;
			}
		}

		if ( $post_id && ! empty( $options['include_faq'] ) && ! empty( $article['faq'] ) && is_array( $article['faq'] ) ) {
			$schema = FaqSchema::build_from_faq_items( $article['faq'] );

			if ( $schema && FaqSchema::save_to_post( $post_id, $schema ) ) {
				$faq_schema_saved = true;
			}
		}

		$report = $this->build_seo_article_report(
			$title,
			(string) $topic,
			$article,
			$options,
			$post_id,
			$status,
			$featured_set,
			$faq_schema_saved,
			$image_logs
		);

		$post_data = array_merge(
			$tool_result['data'],
			array( 'report' => $report )
		);

		$created   = $context['created_posts'] ?? array();
		$created[] = $post_data;

		return array(
			'success'         => true,
			'message'         => $this->format_seo_article_success_message( $report, $tool_result['message'] ),
			'data'            => $post_data,
			'context_updates' => array(
				'created_posts' => $created,
				'last_article'  => $post_data,
			),
		);
	}

	/**
	 * Build SEO article generation prompt from template options.
	 *
	 * @param string               $title   Article title.
	 * @param string               $topic   Article topic.
	 * @param array<string, mixed> $options Template options.
	 * @return string
	 */
	private function build_seo_article_prompt( string $title, string $topic, array $options ): string {
		$sections_count     = min( 10, max( 2, (int) ( $options['sections_count'] ?? 4 ) ) );
		$include_faq        = ! empty( $options['include_faq'] );
		$include_conclusion = ! empty( $options['include_conclusion'] );
		$section_images     = ! empty( $options['section_images'] );
		$featured_image     = ! empty( $options['featured_image'] );
		$suggest_services   = ! empty( $options['suggest_services'] );
		$suggest_products   = ! empty( $options['suggest_products'] );

		$lines   = array(
			'Write a full SEO-optimized blog article.',
			'Title: ' . $title,
			'Topic: ' . $topic,
			'',
			'Structure requirements:',
			sprintf( '- Exactly %d main content sections, each with an H2 heading and rich HTML paragraphs.', $sections_count ),
		);

		if ( $include_faq ) {
			$lines[] = '- Include at least 4 FAQ question-answer pairs.';
		}

		if ( $include_conclusion ) {
			$lines[] = '- Include a conclusion that summarizes key takeaways.';
		}

		if ( $suggest_services ) {
			$services = $this->get_suggestable_services( $topic );

			if ( ! empty( $services ) ) {
				$lines[] = '';
				$lines[] = 'Available services on this site (pick 1-3 relevant items; link naturally in section HTML using <a href="url">title</a>):';
				$lines[] = $this->format_suggestion_catalog_for_prompt( $services );
			}
		}

		if ( $suggest_products ) {
			$products = $this->get_suggestable_products( $topic );

			if ( ! empty( $products ) ) {
				$lines[] = '';
				$lines[] = 'Available products on this site (pick 1-3 relevant items; link naturally in section HTML using <a href="url">title</a>):';
				$lines[] = $this->format_suggestion_catalog_for_prompt( $products );
			}
		}

		$section_keys = 'heading, content (HTML paragraphs only)';

		if ( $section_images ) {
			$section_keys .= ', image_prompt (English visual description, no text in image), image_alt (accessible alt text in article language)';
		}

		$json_keys = array(
			'title',
			'excerpt (1-2 sentence intro shown before sections)',
			'categories (array of strings)',
			'tags (array of strings)',
			'meta_title',
			'meta_description',
			'sections (array of exactly ' . $sections_count . ' objects with keys: ' . $section_keys . ')',
		);

		if ( $section_images ) {
			$lines[] = '- For each section, set image_prompt and image_alt as separate JSON fields (never inside content HTML).';
		}

		if ( $include_faq ) {
			$json_keys[] = 'faq (array of objects with question and answer keys)';
		}

		if ( $include_conclusion ) {
			$json_keys[] = 'conclusion (HTML paragraphs)';
		}

		if ( $featured_image ) {
			$json_keys[] = 'featured_image_prompt (detailed hero image prompt, no text in image)';
		}

		if ( $suggest_services ) {
			$json_keys[] = 'suggested_services (array of objects with title, url, description — only from the services catalog above)';
		}

		if ( $suggest_products ) {
			$json_keys[] = 'suggested_products (array of objects with title, url, description — only from the products catalog above)';
		}

		$lines[] = '';
		$lines[] = 'Content rules:';
		$lines[] = sprintf( '- The sections array must contain exactly %d items, each with a unique H2 heading.', $sections_count );
		$lines[] = '- Section content is reader-facing HTML only. No metadata, prompts, alt text, schema, or hashtags inside content.';
		$lines[] = '- FAQ answers and conclusion are separate JSON fields, not duplicated inside sections.';
		$lines[] = '';
		$lines[] = 'Return JSON with keys: ' . implode( ', ', $json_keys ) . '.';
		$lines[] = ContentPrompts::seo_article_instructions();

		return implode( "\n", $lines );
	}

	/**
	 * Assemble article HTML from structured AI output.
	 *
	 * @param array<string, mixed> $article Article data.
	 * @param array<string, mixed> $options Template options.
	 * @return string
	 */
	private function assemble_seo_article_content( array $article, array $options ): string {
		$html = '';

		$excerpt = trim( (string) ( $article['excerpt'] ?? '' ) );

		if ( '' !== $excerpt ) {
			$html .= '<p class="agenpress-article-intro">' . wp_kses_post( $excerpt ) . '</p>';
		}

		$sections = $article['sections'] ?? array();

		if ( is_array( $sections ) ) {
			foreach ( $sections as $section ) {
				if ( ! is_array( $section ) ) {
					continue;
				}

				$heading = (string) ( $section['heading'] ?? '' );
				$content = FaqSchema::strip_from_content( (string) ( $section['content'] ?? '' ) );

				if ( '' !== $heading ) {
					$html .= '<h2>' . esc_html( $heading ) . '</h2>';
				}

				if ( ! empty( $section['image_url'] ) ) {
					$alt   = (string) ( $section['image_alt'] ?? $heading );
					$html .= sprintf(
						'<figure class="wp-block-image"><img src="%s" alt="%s" /></figure>',
						esc_url( (string) $section['image_url'] ),
						esc_attr( $alt )
					);
				}

				if ( '' !== $content ) {
					$html .= wp_kses_post( $content );
				}
			}
		}

		if ( ! empty( $options['include_faq'] ) && ! empty( $article['faq'] ) && is_array( $article['faq'] ) ) {
			$html .= '<h2>' . esc_html__( 'Frequently Asked Questions', 'agenpress' ) . '</h2>';

			foreach ( $article['faq'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$question = (string) ( $item['question'] ?? '' );
				$answer   = FaqSchema::strip_from_content( (string) ( $item['answer'] ?? '' ) );

				if ( '' === $question ) {
					continue;
				}

				$html .= '<h3>' . esc_html( $question ) . '</h3>';

				if ( '' !== $answer ) {
					$html .= wp_kses_post( $answer );
				}
			}
		}

		if ( ! empty( $options['include_conclusion'] ) && ! empty( $article['conclusion'] ) ) {
			$html .= '<h2>' . esc_html__( 'Conclusion', 'agenpress' ) . '</h2>';
			$html .= wp_kses_post( FaqSchema::strip_from_content( (string) $article['conclusion'] ) );
		}

		if ( ! empty( $options['suggest_services'] ) && ! empty( $article['suggested_services'] ) && is_array( $article['suggested_services'] ) ) {
			$html .= $this->build_suggestions_section(
				__( 'Our Services', 'agenpress' ),
				$article['suggested_services']
			);
		}

		if ( ! empty( $options['suggest_products'] ) && ! empty( $article['suggested_products'] ) && is_array( $article['suggested_products'] ) ) {
			$html .= $this->build_suggestions_section(
				__( 'Recommended Products', 'agenpress' ),
				$article['suggested_products']
			);
		}

		return $html;
	}

	/**
	 * Fetch published service posts for article suggestions.
	 *
	 * @param string $topic Article topic for relevance search.
	 * @return array<int, array{title: string, url: string, description: string}>
	 */
	private function get_suggestable_services( string $topic ): array {
		$post_type = (string) apply_filters( 'agenpress_seo_article_service_post_type', 'service' );

		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 15,
				's'              => $topic,
				'orderby'        => 'relevance',
			)
		);

		if ( empty( $posts ) ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 15,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
		}

		return $this->map_posts_to_suggestions( $posts );
	}

	/**
	 * Fetch published WooCommerce products for article suggestions.
	 *
	 * @param string $topic Article topic for relevance search.
	 * @return array<int, array{title: string, url: string, description: string}>
	 */
	private function get_suggestable_products( string $topic ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$query_args = array(
			'limit'   => 15,
			'status'  => 'publish',
			'return'  => 'objects',
			'orderby' => 'popularity',
			'order'   => 'DESC',
		);

		$topic = trim( $topic );

		if ( '' !== $topic ) {
			$query_args['search'] = $topic;
		}

		$products = ( new \WC_Product_Query( $query_args ) )->get_products();

		if ( empty( $products ) && '' !== $topic ) {
			unset( $query_args['search'] );
			$products = ( new \WC_Product_Query( $query_args ) )->get_products();
		}

		$suggestions = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$url = get_permalink( $product->get_id() );

			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$description = $product->get_short_description() ?: $product->get_description();
			$description = wp_trim_words( wp_strip_all_tags( (string) $description ), 25 );

			$suggestions[] = array(
				'title'       => $product->get_name(),
				'url'         => $url,
				'description' => $description,
			);
		}

		return $suggestions;
	}

	/**
	 * Map WP posts to suggestion catalog entries.
	 *
	 * @param array<int, \WP_Post> $posts Posts.
	 * @return array<int, array{title: string, url: string, description: string}>
	 */
	private function map_posts_to_suggestions( array $posts ): array {
		$suggestions = array();

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$url = get_permalink( $post );

			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$suggestions[] = array(
				'title'       => $post->post_title,
				'url'         => $url,
				'description' => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 25 ),
			);
		}

		return $suggestions;
	}

	/**
	 * Format suggestion catalog for the AI prompt.
	 *
	 * @param array<int, array{title: string, url: string, description: string}> $items Catalog items.
	 * @return string
	 */
	private function format_suggestion_catalog_for_prompt( array $items ): string {
		$lines = array();

		foreach ( $items as $item ) {
			$lines[] = sprintf(
				'- %s — %s%s',
				$item['title'],
				$item['url'],
				'' !== $item['description'] ? ' — ' . $item['description'] : ''
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build an HTML section for suggested services or products.
	 *
	 * @param string                                                              $heading Section heading.
	 * @param array<int, array<string, string>|array{title?: string, url?: string, description?: string}> $items   Suggested items.
	 * @return string
	 */
	private function build_suggestions_section( string $heading, array $items ): string {
		$html = '<h2>' . esc_html( $heading ) . '</h2><ul>';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title = trim( (string) ( $item['title'] ?? '' ) );
			$url   = trim( (string) ( $item['url'] ?? '' ) );

			if ( '' === $title || '' === $url ) {
				continue;
			}

			$description = trim( (string) ( $item['description'] ?? '' ) );
			$html       .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';

			if ( '' !== $description ) {
				$html .= ': ' . esc_html( $description );
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}

	/**
	 * Build a structured report for SEO article task steps.
	 *
	 * @param string               $title            Planned title.
	 * @param string               $topic            Article topic.
	 * @param array<string, mixed> $article          Parsed article JSON.
	 * @param array<string, mixed> $options          Step options.
	 * @param int                  $post_id          Created post ID.
	 * @param string               $status           Post status.
	 * @param bool                 $featured_set     Whether featured image was set.
	 * @param bool                 $faq_schema_saved Whether FAQ schema meta was saved.
	 * @return array<string, mixed>
	 */
	private function build_seo_article_report(
		string $title,
		string $topic,
		array $article,
		array $options,
		int $post_id,
		string $status,
		bool $featured_set,
		bool $faq_schema_saved,
		array $image_logs = array()
	): array {
		$sections         = is_array( $article['sections'] ?? null ) ? $article['sections'] : array();
		$images_generated = 0;
		$images_requested = 0;
		$section_headings = array();

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			if ( ! empty( $section['heading'] ) ) {
				$section_headings[] = (string) $section['heading'];
			}
		}

		if ( ! empty( $options['section_images'] ) ) {
			$images_requested = count( $sections );

			foreach ( $sections as $section ) {
				if ( is_array( $section ) && ! empty( $section['image_url'] ) ) {
					++$images_generated;
				}
			}
		}

		$image_errors = array_values(
			array_filter(
				$image_logs,
				static function ( array $log ): bool {
					return empty( $log['success'] );
				}
			)
		);

		$faq = is_array( $article['faq'] ?? null ) ? $article['faq'] : array();

		return array(
			'planned_title'            => $title,
			'published_title'          => (string) ( $article['title'] ?? $title ),
			'topic'                    => $topic,
			'post_id'                  => $post_id,
			'post_status'              => $status,
			'sections_count'           => count( $sections ),
			'sections_expected'        => (int) ( $options['sections_count'] ?? 0 ),
			'section_headings'         => $section_headings,
			'section_images_requested' => $images_requested,
			'section_images_generated' => $images_generated,
			'featured_image_requested' => ! empty( $options['featured_image'] ),
			'featured_image_set'       => $featured_set,
			'faq_requested'            => ! empty( $options['include_faq'] ),
			'faq_count'                => count( $faq ),
			'faq_schema_saved'         => $faq_schema_saved,
			'conclusion_requested'     => ! empty( $options['include_conclusion'] ),
			'conclusion_included'      => ! empty( $options['include_conclusion'] ) && ! empty( $article['conclusion'] ),
			'categories'               => is_array( $article['categories'] ?? null ) ? $article['categories'] : array(),
			'tags'                     => is_array( $article['tags'] ?? null ) ? $article['tags'] : array(),
			'meta_title'               => (string) ( $article['meta_title'] ?? '' ),
			'meta_description'         => (string) ( $article['meta_description'] ?? '' ),
			'suggest_products'         => ! empty( $options['suggest_products'] ),
			'suggest_services'         => ! empty( $options['suggest_services'] ),
			'image_logs'               => $image_logs,
			'image_errors'             => $image_errors,
			'image_error_count'        => count( $image_errors ),
		);
	}

	/**
	 * Format a human-readable SEO article completion message.
	 *
	 * @param array<string, mixed> $report       Article report.
	 * @param string               $base_message Base tool message.
	 * @return string
	 */
	private function format_seo_article_success_message( array $report, string $base_message ): string {
		$parts = array( $base_message );

		$parts[] = sprintf(
			/* translators: 1: sections count, 2: images generated, 3: images requested, 4: FAQ count */
			__( 'Sections: %1$d | Section images: %2$d/%3$d | FAQ: %4$d', 'agenpress' ),
			(int) ( $report['sections_count'] ?? 0 ),
			(int) ( $report['section_images_generated'] ?? 0 ),
			(int) ( $report['section_images_requested'] ?? 0 ),
			(int) ( $report['faq_count'] ?? 0 )
		);

		if ( ! empty( $report['featured_image_requested'] ) ) {
			$parts[] = ! empty( $report['featured_image_set'] )
				? __( 'Featured image: set', 'agenpress' )
				: __( 'Featured image: not set', 'agenpress' );
		}

		if ( ! empty( $report['faq_schema_saved'] ) ) {
			$parts[] = __( 'FAQ schema saved', 'agenpress' );
		}

		if ( ! empty( $report['image_error_count'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: number of image errors */
				__( 'Image errors: %d (see task logs)', 'agenpress' ),
				(int) $report['image_error_count']
			);
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Generate section images for an SEO article and collect detailed logs.
	 *
	 * @param array<string, mixed> $article Article data (modified by reference).
	 * @param array<string, mixed> $options Step options.
	 * @param string               $title   Article title.
	 * @return array<int, array<string, mixed>>
	 */
	private function generate_seo_article_images( array &$article, array $options, string $title ): array {
		$image_logs = array();

		if ( empty( $options['section_images'] ) || empty( $article['sections'] ) || ! is_array( $article['sections'] ) ) {
			return $image_logs;
		}

		foreach ( $article['sections'] as $i => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$heading   = (string) ( $section['heading'] ?? '' );
			$label     = $heading
				? sprintf(
					/* translators: %s: section heading */
					__( 'Section: %s', 'agenpress' ),
					$heading
				)
				: sprintf(
					/* translators: %d: section number */
					__( 'Section %d', 'agenpress' ),
					$i + 1
				);
			$log_entry = array(
				'type'    => 'section',
				'index'   => $i + 1,
				'heading' => $heading,
				'label'   => $label,
			);

			if ( empty( $section['image_prompt'] ) ) {
				$image_logs[] = array_merge(
					$log_entry,
					array(
						'success'    => false,
						'error_code' => 'missing_prompt',
						'error'      => __( 'AI did not return image_prompt for this section.', 'agenpress' ),
						'message'    => sprintf(
							/* translators: %s: section label */
							__( 'Image skipped for %s: missing image_prompt from AI.', 'agenpress' ),
							$label
						),
					)
				);
				continue;
			}

			$result = $this->generate_image_attachment( (string) $section['image_prompt'], $label );
			$image_logs[] = array_merge( $log_entry, $result );

			if ( ! empty( $result['success'] ) ) {
				$article['sections'][ $i ]['image_url'] = $result['url'];
				$article['sections'][ $i ]['image_alt']  = (string) ( $section['image_alt'] ?? $section['heading'] ?? $title );
			}
		}

		return $image_logs;
	}

	/**
	 * Generate an AI image and sideload it into the media library.
	 *
	 * @param string $prompt Image prompt.
	 * @param string $label  Human-readable label for logs.
	 * @return array<string, mixed>
	 */
	private function generate_image_attachment( string $prompt, string $label = '' ): array {
		$prompt = trim( $prompt );
		$label  = '' !== $label ? $label : __( 'Image', 'agenpress' );

		$result = array(
			'success'        => false,
			'label'          => $label,
			'prompt_preview' => mb_substr( $prompt, 0, 120 ),
			'error'          => '',
			'error_code'     => '',
			'message'        => '',
		);

		if ( '' === $prompt ) {
			$result['error']      = __( 'Empty image prompt.', 'agenpress' );
			$result['error_code'] = 'empty_prompt';
			$result['message']    = sprintf(
				/* translators: %s: image label */
				__( 'Image failed for %1$s: %2$s', 'agenpress' ),
				$label,
				$result['error']
			);

			return $result;
		}

		try {
			$provider = $this->provider_factory->get_image_provider();

			if ( ! $provider->is_configured() ) {
				$result['error']      = __( 'Image AI provider is not configured. Add an API key for OpenAI, GapGPT, or Custom in AgenPress settings.', 'agenpress' );
				$result['error_code'] = 'provider_not_configured';
				$result['message']    = sprintf(
					/* translators: 1: image label, 2: error message */
					__( 'Image failed for %1$s: %2$s', 'agenpress' ),
					$label,
					$result['error']
				);

				return $result;
			}

			$image = $provider->generate_image(
				$prompt,
				array( 'size' => ImageSizeRegistry::resolve_size() )
			);

			if ( empty( $image['url'] ) && empty( $image['b64_json'] ) ) {
				$result['error']      = __( 'AI image API returned no image URL or data.', 'agenpress' );
				$result['error_code'] = 'empty_response';
				$result['message']    = sprintf(
					/* translators: 1: image label, 2: error message */
					__( 'Image failed for %1$s: %2$s', 'agenpress' ),
					$label,
					$result['error']
				);

				return $result;
			}

			$sideload = AiImageSideloader::sideload_result_detailed( $image, $prompt );

			if ( empty( $sideload['attachment_id'] ) ) {
				$result['error']      = (string) ( $sideload['error'] ?? __( 'Failed to save image to media library.', 'agenpress' ) );
				$result['error_code'] = 'sideload_failed';
				$result['message']    = sprintf(
					/* translators: 1: image label, 2: error message */
					__( 'Image failed for %1$s: %2$s', 'agenpress' ),
					$label,
					$result['error']
				);

				return $result;
			}

			$result['success']        = true;
			$result['attachment_id']  = (int) $sideload['attachment_id'];
			$result['url']            = (string) wp_get_attachment_url( $sideload['attachment_id'] );
			$result['message']        = sprintf(
				/* translators: 1: image label, 2: attachment ID */
				__( 'Image created for %1$s (attachment #%2$d).', 'agenpress' ),
				$label,
				$result['attachment_id']
			);

			return $result;
		} catch ( \Exception $e ) {
			$result['error']      = $e->getMessage();
			$result['error_code'] = 'api_exception';
			$result['message']    = sprintf(
				/* translators: 1: image label, 2: exception message */
				__( 'Image failed for %1$s: %2$s', 'agenpress' ),
				$label,
				$result['error']
			);

			return $result;
		}
	}

	/**
	 * Update a WooCommerce product description.
	 *
	 * @param array<string, mixed> $step    Step.
	 * @param int                  $user_id User ID.
	 * @param string               $module  Module.
	 * @param array<string, mixed> $context Context.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	private function execute_product_description( array $step, int $user_id, string $module, array $context ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'         => false,
				'message'         => __( 'WooCommerce is not active.', 'agenpress' ),
				'data'            => null,
				'context_updates' => array(),
			);
		}

		$create_new  = ! empty( $step['create_new'] );
		$products    = $context['products'] ?? array();
		$index       = (int) ( $step['index'] ?? 0 );
		$niche       = (string) ( $step['niche'] ?? '' );
		$brief       = (string) ( $step['brief'] ?? '' );
		$publish     = ! empty( $step['publish'] );
		$status      = $publish ? 'publish' : 'draft';
		$product_id  = (int) ( $step['product_id'] ?? 0 );
		$product     = null;

		if ( $product_id > 0 ) {
			$product = array(
				'id'   => $product_id,
				'name' => get_the_title( $product_id ),
			);
		} elseif ( ! $create_new && is_array( $products ) ) {
			$product = $products[ $index ] ?? null;
		}

		if ( $create_new || ! $product || empty( $product['id'] ) ) {
			$spec_prompt = sprintf(
				'Create WooCommerce product #%d for niche "%s". Brief: %s. Return ONLY JSON with keys: name, short_description, description (HTML), regular_price (numeric string), sku, image_prompt (detailed AI photo prompt), image_alt. Use the same language as the brief.',
				$index + 1,
				$niche,
				mb_substr( $brief, 0, 600 )
			);

			$ai_result = $this->execute_ai( array( 'prompt' => $spec_prompt ), $context, $module );

			if ( ! $ai_result['success'] ) {
				return $ai_result;
			}

			$spec = $this->try_parse_json( $ai_result['data'] );

			if ( ! is_array( $spec ) ) {
				$spec = array(
					'name'              => sprintf( '%s %d', $niche ?: 'Product', $index + 1 ),
					'short_description' => wp_trim_words( (string) $ai_result['data'], 20 ),
					'description'       => (string) $ai_result['data'],
					'regular_price'     => '99',
				);
			}

			$create_result = $this->tool_registry->execute(
				'create_product',
				array(
					'name'              => (string) ( $spec['name'] ?? sprintf( 'Product %d', $index + 1 ) ),
					'description'       => (string) ( $spec['description'] ?? '' ),
					'short_description' => (string) ( $spec['short_description'] ?? '' ),
					'regular_price'     => (string) ( $spec['regular_price'] ?? '99' ),
					'sku'               => (string) ( $spec['sku'] ?? '' ),
					'status'            => $status,
				),
				$user_id,
				$module
			);

			if ( ! $create_result['success'] || empty( $create_result['data']['id'] ) ) {
				return array(
					'success'         => false,
					'message'         => $create_result['message'] ?? __( 'Failed to create product.', 'agenpress' ),
					'data'            => $create_result['data'] ?? null,
					'context_updates' => array(),
				);
			}

			$product_id = (int) $create_result['data']['id'];
			$messages   = array( $create_result['message'] );

			$image_prompt = (string) ( $spec['image_prompt'] ?? sprintf( 'Professional product photo for %s, studio lighting, e-commerce white background', $spec['name'] ?? 'product' ) );

			$image_result = $this->tool_registry->execute(
				'generate_image',
				array(
					'prompt'       => $image_prompt,
					'post_id'      => $product_id,
					'set_featured' => true,
					'alt_text'     => (string) ( $spec['image_alt'] ?? $spec['name'] ?? '' ),
					'size'         => '1:1',
				),
				$user_id,
				$module
			);

			$messages[] = $image_result['message'];

			$created_products   = is_array( $context['created_products'] ?? null ) ? $context['created_products'] : array();
			$created_products[] = array(
				'id'   => $product_id,
				'name' => (string) ( $create_result['data']['name'] ?? '' ),
				'url'  => get_permalink( $product_id ),
			);

			return array(
				'success'         => true,
				'message'         => implode( ' ', array_filter( $messages ) ),
				'data'            => array(
					'id'         => $product_id,
					'name'       => $create_result['data']['name'] ?? '',
					'url'        => get_permalink( $product_id ),
					'image'      => $image_result['data'] ?? null,
					'image_ok'   => ! empty( $image_result['success'] ),
				),
				'context_updates' => array(
					'created_products' => $created_products,
				),
			);
		}

		$json_keys = 'name (only if the brief asks to rename the product), description (full long HTML for the main WooCommerce Description tab — 250+ words, H2 sections, persuasive SEO copy), short_description (1-2 sentence excerpt near price), focus_keyword, seo_title (~60 chars), seo_description (~160 chars)';

		$prompt = sprintf(
			'Write SEO-optimized WooCommerce product copy. Product: %s. Market/niche: %s. Instructions: %s. Return ONLY JSON with keys: %s. Follow every instruction in the brief (title changes, keyword targets, market focus). Use the same language as the brief.',
			$product['name'] ?? 'Product',
			$niche,
			mb_substr( $brief, 0, 1200 ),
			$json_keys
		);

		$ai_result = $this->execute_ai( array( 'prompt' => $prompt ), $context, $module );

		if ( ! $ai_result['success'] ) {
			return $ai_result;
		}

		$copy = $this->try_parse_json( $ai_result['data'] );

		if ( ! is_array( $copy ) ) {
			$copy = array( 'description' => $ai_result['data'], 'short_description' => wp_trim_words( $ai_result['data'], 30 ) );
		}

		$update_args = array(
			'product_id'        => (int) $product['id'],
			'description'       => $copy['description'] ?? '',
			'short_description' => $copy['short_description'] ?? '',
		);

		if ( ! empty( $copy['name'] ) ) {
			$update_args['name'] = (string) $copy['name'];
		}
		if ( ! empty( $copy['focus_keyword'] ) ) {
			$update_args['focus_keyword'] = (string) $copy['focus_keyword'];
		}
		if ( ! empty( $copy['seo_title'] ) ) {
			$update_args['seo_title'] = (string) $copy['seo_title'];
		}
		if ( ! empty( $copy['seo_description'] ) ) {
			$update_args['seo_description'] = (string) $copy['seo_description'];
		}

		$tool_result = $this->tool_registry->execute(
			'update_product',
			$update_args,
			$user_id,
			$module
		);

		return array(
			'success'         => $tool_result['success'],
			'message'         => $tool_result['message'],
			'data'            => $tool_result['data'],
			'context_updates' => array(),
		);
	}

	/**
	 * Build one Elementor site page.
	 *
	 * @param array<string, mixed> $step    Step.
	 * @param int                  $user_id User ID.
	 * @return array{success: bool, message: string, data: mixed, context_updates: array<string, mixed>}
	 */
	private function execute_site_page( array $step, int $user_id ): array {
		/** @var SitePageBuilder $builder */
		$builder = agenpress()->container()->get( 'site_page_builder' );

		return $builder->build( $step, $user_id );
	}

	/**
	 * Resolve placeholders in args.
	 *
	 * @param array<string, mixed> $args    Arguments.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	private function resolve_args( array $args, array $context ): array {
		$resolved = array();

		foreach ( $args as $key => $value ) {
			$resolved[ $key ] = is_string( $value ) ? $this->resolve_string( $value, $context ) : $value;
		}

		return $resolved;
	}

	/**
	 * Resolve {{key}} placeholders in a string.
	 *
	 * @param string               $string  Input string.
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	private function resolve_string( string $string, array $context ): string {
		return preg_replace_callback(
			'/\{\{(\w+)\}\}/',
			static function ( array $matches ) use ( $context ) {
				$key = $matches[1];
				return isset( $context[ $key ] ) ? (string) $context[ $key ] : $matches[0];
			},
			$string
		);
	}

	/**
	 * Try to parse JSON from AI output.
	 *
	 * @param mixed       $content Content.
	 * @param string|null $error   Optional error message output.
	 * @return mixed|null
	 */
	private function try_parse_json( mixed $content, ?string &$error = null ): mixed {
		if ( is_array( $content ) ) {
			return $content;
		}

		if ( ! is_string( $content ) ) {
			$error = __( 'AI response was not text or JSON.', 'agenpress' );
			return null;
		}

		$content   = trim( $content );
		$candidates = array( $content );

		$stripped = preg_replace( '/^```(?:json)?\s*/i', '', $content );
		$stripped = preg_replace( '/\s*```\s*$/', '', (string) $stripped );

		if ( is_string( $stripped ) && $stripped !== $content ) {
			$candidates[] = trim( $stripped );
		}

		if ( preg_match( '/\{.*\}/s', $content, $matches ) ) {
			$candidates[] = trim( $matches[0] );
		}

		$last_error = '';

		foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
			$decoded = json_decode( $candidate, true );

			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}

			$last_error = json_last_error_msg();
		}

		$error = $last_error ?: __( 'Could not decode JSON from AI response.', 'agenpress' );

		return null;
	}

	/**
	 * Build a short preview of AI output for task error logs.
	 *
	 * @param mixed $data AI response data.
	 * @return string
	 */
	private function preview_ai_response( mixed $data ): string {
		if ( is_string( $data ) ) {
			return mb_substr( $data, 0, 400 );
		}

		if ( is_array( $data ) ) {
			return mb_substr( wp_json_encode( $data ), 0, 400 );
		}

		return '';
	}
}

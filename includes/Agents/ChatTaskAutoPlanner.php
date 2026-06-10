<?php
/**
 * Detects multi-step admin chat requests and queues agent tasks automatically.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\AI\ProviderFactory;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatTaskAutoPlanner
 */
class ChatTaskAutoPlanner {

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Task planner.
	 *
	 * @var TaskPlanner
	 */
	private TaskPlanner $task_planner;

	/**
	 * Task queue.
	 *
	 * @var TaskQueue
	 */
	private TaskQueue $task_queue;

	/**
	 * Constructor.
	 *
	 * @param ProviderFactory $provider_factory Provider factory.
	 * @param TaskPlanner     $task_planner     Task planner.
	 * @param TaskQueue       $task_queue       Task queue.
	 */
	public function __construct(
		ProviderFactory $provider_factory,
		TaskPlanner $task_planner,
		TaskQueue $task_queue
	) {
		$this->provider_factory = $provider_factory;
		$this->task_planner     = $task_planner;
		$this->task_queue       = $task_queue;
	}

	/**
	 * Try to queue one or more agent tasks from a chat message.
	 *
	 * @param string $message User message.
	 * @param int    $user_id User ID.
	 * @return array{tasks: array<int, array<string, mixed>>, summary: string}|null
	 */
	public function try_queue( string $message, int $user_id ): ?array {
		if ( ! user_can( $user_id, Capabilities::RUN_AGENTS ) ) {
			return null;
		}

		$message = trim( $message );

		if ( ! $this->is_eligible( $message ) ) {
			return null;
		}

		$definitions = $this->plan_with_ai( $message );

		if ( empty( $definitions ) ) {
			$definitions = $this->plan_with_rules( $message );
		}

		if ( count( $definitions ) < 1 ) {
			return null;
		}

		$created = array();

		foreach ( $definitions as $definition ) {
			$task = $this->create_task_from_definition( $user_id, $definition );

			if ( $task ) {
				$created[] = $task;
			}
		}

		if ( empty( $created ) ) {
			return null;
		}

		return array(
			'tasks'   => $created,
			'summary' => $this->build_summary( $created, $message ),
		);
	}

	/**
	 * Whether a message looks like a multi-step project request.
	 *
	 * @param string $message User message.
	 * @return bool
	 */
	public function is_eligible( string $message ): bool {
		$message = trim( $message );

		if ( mb_strlen( $message ) < 80 ) {
			return false;
		}

		if ( preg_match( '/\b(?:تسک|task\s*queue|agent\s*task|queue\s*task|صف\s*تسک)\b/iu', $message ) ) {
			return true;
		}

		$signals = 0;
		$patterns = array(
			'/\b(?:صفحه|page|pages)\b/iu',
			'/\b(?:محصول|product|products|woocommerce)\b/iu',
			'/\b(?:مقاله|مقالات|article|articles|blog|پست|پست‌ها)\b/iu',
			'/\b(?:طراحی|design|build|create\s+(?:a\s+)?site)\b/iu',
			'/\b(?:فروشگاه|shop|store|e-?commerce)\b/iu',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				++$signals;
			}
		}

		return $signals >= 2;
	}

	/**
	 * Use AI to decompose a request into task definitions.
	 *
	 * @param string $message User message.
	 * @return array<int, array<string, mixed>>
	 */
	private function plan_with_ai( string $message ): array {
		$templates = array(
			'seo_articles'         => 'Batch SEO blog posts. Params: count (int), topic (string), publish (bool).',
			'product_descriptions' => 'WooCommerce products with SEO copy and AI images. Params: count (int), niche (string), create_new (bool, true when user asks to create/add sample products), publish (bool). Requires WooCommerce.',
			'custom'               => 'Any other multi-step WordPress admin work. Params: optional.',
		);

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$templates['site_pages'] = 'Build Elementor pages with AI banners and widgets. Params: brand (string), colors (string), banner_count (int), brief (string), pages (array of {type,title,slug}). Use ONE site_pages task for all static pages — do NOT split each page into separate custom tasks.';
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			unset( $templates['product_descriptions'] );
		}

		$prompt = sprintf(
			"Analyze this WordPress admin request and decide whether it should be queued as background agent tasks instead of executed immediately in chat.\n\n"
			. "Request:\n%s\n\n"
			. "Available templates:\n%s\n\n"
			. "Rules:\n"
			. "- Set should_queue true only for multi-step projects (2+ distinct deliverables such as pages, products, articles, or site sections).\n"
			. "- Split work into separate tasks (e.g. blog batch, product batch, each static page).\n"
			. "- Use the same language as the user for titles and descriptions.\n"
			. "- Include relevant brand/site details from the request in each task description.\n"
			. "- Use EXACT quantities from the user request in params.count (e.g. «یک عدد محصول» = count:1, «1 پست» = count:1). Never default to 5.\n"
			. "- site_pages has one step per page (typically 5 pages = 5 steps). product_descriptions and seo_articles steps depend on params.count.\n"
			. "- Return ONLY JSON: {\"should_queue\":bool,\"tasks\":[{\"title\":\"\",\"description\":\"\",\"template\":\"seo_articles|product_descriptions|site_pages|custom\",\"params\":{}}],\"summary\":\"\"}",
			$message,
			wp_json_encode( $templates )
		);

		try {
			$provider = $this->provider_factory->get();
			$response = $provider->chat(
				array(
					array(
						'role'    => 'system',
						'content' => 'You are AgenPress task planner. Return valid JSON only.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				)
			);

			$parsed = $this->parse_json_object( (string) ( $response['content'] ?? '' ) );

			if ( ! is_array( $parsed ) || empty( $parsed['should_queue'] ) ) {
				return array();
			}

			$tasks = $parsed['tasks'] ?? array();

			return is_array( $tasks ) ? $this->normalize_definitions( $tasks ) : array();
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Rule-based fallback decomposition.
	 *
	 * @param string $message User message.
	 * @return array<int, array<string, mixed>>
	 */
	private function plan_with_rules( string $message ): array {
		$definitions = array();
		$topic       = $this->extract_topic( $message );

		$article_count = TaskTemplates::parse_article_count( $message );
		if ( null !== $article_count ) {
			$definitions[] = array(
				'title'       => sprintf(
					/* translators: %d: number of blog posts */
					__( 'Create %d SEO blog posts', 'agenpress' ),
					$article_count
				),
				'description' => $message,
				'template'    => 'seo_articles',
				'params'      => array(
					'count'   => $article_count,
					'topic'   => $topic,
					'publish' => false,
				),
			);
		}

		$product_count = TaskTemplates::parse_product_count( $message );
		if ( class_exists( 'WooCommerce' ) && null !== $product_count ) {
			$definitions[] = array(
				'title'       => sprintf(
					/* translators: %d: number of products */
					__( 'Create %d WooCommerce products', 'agenpress' ),
					$product_count
				),
				'description' => $message,
				'template'    => 'product_descriptions',
				'params'      => array(
					'count'      => $product_count,
					'niche'      => $topic,
					'create_new' => TaskTemplates::should_create_products( $message ),
					'publish'    => false,
				),
			);
		}

		$site_pages_task = $this->detect_site_pages_task( $message );
		if ( null !== $site_pages_task ) {
			$definitions[] = $site_pages_task;
		}

		return $this->normalize_definitions( $definitions );
	}

	/**
	 * Detect a combined Elementor site-pages task from the request.
	 *
	 * @param string $message User message.
	 * @return array<string, mixed>|null
	 */
	private function detect_site_pages_task( string $message ): ?array {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$pages = TaskTemplates::detect_site_pages( $message );

		if ( count( $pages ) < 2 && ! TaskTemplates::looks_like_site_pages_request( strtolower( $message ) ) ) {
			return null;
		}

		if ( empty( $pages ) ) {
			$pages = TaskTemplates::default_site_pages();
		}

		return array(
			'title'       => __( 'Build Elementor store pages', 'agenpress' ),
			'description' => $message,
			'template'    => 'site_pages',
			'params'      => array(
				'brief'        => $message,
				'brand'        => TaskTemplates::parse_brand( $message ),
				'colors'       => TaskTemplates::parse_colors( $message ),
				'banner_count' => TaskTemplates::parse_banner_count( $message ),
				'pages'        => $pages,
				'publish'      => false,
			),
		);
	}

	/**
	 * Normalize task definitions from AI or rules.
	 *
	 * @param array<int, mixed> $definitions Raw definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_definitions( array $definitions ): array {
		$normalized = array();

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$title = sanitize_text_field( (string) ( $definition['title'] ?? '' ) );

			if ( '' === $title ) {
				continue;
			}

			$template = sanitize_key( (string) ( $definition['template'] ?? 'custom' ) );

			if ( ! TaskTemplates::exists( $template ) ) {
				$template = 'custom';
			}

			if ( 'product_descriptions' === $template && ! class_exists( 'WooCommerce' ) ) {
				continue;
			}

			if ( 'site_pages' === $template && ! class_exists( '\Elementor\Plugin' ) ) {
				continue;
			}

			$task_description = sanitize_textarea_field( (string) ( $definition['description'] ?? '' ) );

			if ( 'custom' === $template && class_exists( '\Elementor\Plugin' ) && TaskTemplates::looks_like_site_pages_request( strtolower( $title . ' ' . $task_description ) ) ) {
				$template = 'site_pages';
				$params   = array(
					'brief'        => $task_description,
					'brand'        => TaskTemplates::parse_brand( $task_description ),
					'colors'       => TaskTemplates::parse_colors( $task_description ),
					'banner_count' => TaskTemplates::parse_banner_count( $task_description ),
					'pages'        => TaskTemplates::detect_site_pages( $task_description ) ?: TaskTemplates::default_site_pages(),
					'publish'      => false,
				);
			} else {
				$params = is_array( $definition['params'] ?? null ) ? $definition['params'] : array();
			}

			if ( 'site_pages' === $template ) {
				$params = array_merge(
					array(
						'brief'        => $task_description,
						'brand'        => TaskTemplates::parse_brand( $task_description ),
						'colors'       => TaskTemplates::parse_colors( $task_description ),
						'banner_count' => TaskTemplates::parse_banner_count( $task_description ),
						'pages'        => TaskTemplates::detect_site_pages( $task_description ) ?: TaskTemplates::default_site_pages(),
						'publish'      => false,
					),
					$params
				);
			}

			if ( 'seo_articles' === $template ) {
				$params = array_merge(
					array(
						'count'   => TaskTemplates::parse_article_count( $task_description ) ?? 1,
						'topic'   => TaskTemplates::parse_topic( $task_description ),
						'publish' => false,
					),
					$params
				);

				$parsed_articles = TaskTemplates::parse_article_count( $task_description );
				if ( null !== $parsed_articles ) {
					$params['count'] = $parsed_articles;
				}
			}

			if ( 'product_descriptions' === $template ) {
				$params = array_merge(
					array(
						'count'      => TaskTemplates::parse_product_count( $task_description ) ?? 1,
						'create_new' => TaskTemplates::should_create_products( $task_description ),
						'niche'      => TaskTemplates::parse_topic( $task_description ),
						'publish'    => false,
					),
					$params
				);

				$parsed_products = TaskTemplates::parse_product_count( $task_description );
				if ( null !== $parsed_products ) {
					$params['count'] = $parsed_products;
				}
			}

			$normalized[] = array(
				'title'       => $title,
				'description' => $task_description,
				'template'    => $template,
				'params'      => $params,
			);
		}

		return $normalized;
	}

	/**
	 * Create a queued task from a definition.
	 *
	 * @param int                  $user_id    User ID.
	 * @param array<string, mixed> $definition Task definition.
	 * @return array<string, mixed>|null
	 */
	private function create_task_from_definition( int $user_id, array $definition ): ?array {
		$title       = (string) ( $definition['title'] ?? '' );
		$description = (string) ( $definition['description'] ?? '' );
		$template    = (string) ( $definition['template'] ?? 'custom' );
		$params      = is_array( $definition['params'] ?? null ) ? $definition['params'] : array();

		$plan = $this->task_planner->plan( $title, $description, 'admin', array(
			'template' => $template,
			'params'   => $params,
		) );

		return $this->task_queue->create(
			$user_id,
			'admin',
			$title,
			$description,
			$plan['steps'],
			$plan['template']
		);
	}

	/**
	 * Build assistant summary for queued tasks.
	 *
	 * @param array<int, array<string, mixed>> $tasks   Created tasks.
	 * @param string                           $message Original user message.
	 * @return string
	 */
	private function build_summary( array $tasks, string $message ): string {
		$lines   = array();
		$lines[] = __( 'Your request was split into background agent tasks and queued for step-by-step execution.', 'agenpress' );
		$lines[] = '';
		$lines[] = __( 'Queued tasks:', 'agenpress' );

		foreach ( $tasks as $task ) {
			$template = (string) ( $task['template'] ?? 'custom' );
			$lines[]  = sprintf(
				'- **%s** (%s, %d/%d steps)',
				(string) ( $task['title'] ?? '' ),
				$template,
				(int) ( $task['current_step'] ?? 0 ),
				(int) ( $task['total_steps'] ?? 0 )
			);
		}

		$lines[] = '';
		$lines[] = __( 'Open **Agent Tasks** to watch progress, pause, or cancel individual tasks. Elementor layout work still needs the Elementor assistant in the page editor.', 'agenpress' );

		if ( preg_match( '/[\x{0600}-\x{06FF}]/u', $message ) ) {
			$lines[] = '';
			$lines[] = 'درخواست شما به چند تسک پس‌زمینه تقسیم و در صف Agent قرار گرفت. پیشرفت را از بخش **Agent Tasks** دنبال کنید.';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Extract a topic/niche from the message.
	 *
	 * @param string $message User message.
	 * @return string
	 */
	private function extract_topic( string $message ): string {
		if ( preg_match( '/(?:موضوع\s*سایت|topic|niche|about)\s*[:：]\s*(.+?)(?:\n|$)/iu', $message, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) );
		}

		if ( preg_match( '/(?:نام\s*فروشگاه|store\s*name|brand)\s*[:：]\s*(.+?)(?:\n|$)/iu', $message, $matches ) ) {
			return sanitize_text_field( trim( $matches[1] ) );
		}

		return sanitize_text_field( mb_substr( trim( $message ), 0, 120 ) );
	}

	/**
	 * Parse a JSON object from AI output.
	 *
	 * @param string $content AI content.
	 * @return array<string, mixed>|null
	 */
	private function parse_json_object( string $content ): ?array {
		$content = trim( $content );
		$content = preg_replace( '/^```(?:json)?\s*/i', '', $content );
		$content = preg_replace( '/\s*```$/', '', $content );

		$parsed = json_decode( trim( $content ), true );

		return is_array( $parsed ) ? $parsed : null;
	}
}

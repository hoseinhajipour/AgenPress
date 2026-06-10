<?php
/**
 * Executes individual agent task steps.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

use AgenPress\AI\ProviderFactory;
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
		$tool_name = $step['tool'] ?? '';
		$args      = $this->resolve_args( $step['args'] ?? array(), $context );
		$result    = $this->tool_registry->execute( $tool_name, $args, $user_id, $module );

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

			if ( ! empty( $step['output_key'] ) ) {
				$parsed = $this->try_parse_json( $content );
				$updates[ $step['output_key'] ] = null !== $parsed ? $parsed : $content;
			}

			return array(
				'success'         => true,
				'message'         => mb_substr( $content, 0, 200 ),
				'data'            => $content,
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
		$index  = (int) ( $step['index'] ?? 0 );
		$topic  = $step['topic'] ?? '';
		$titles = $context['article_titles'] ?? array();

		$title = is_array( $titles ) && isset( $titles[ $index ] )
			? (string) $titles[ $index ]
			: sprintf( '%s — Part %d', $topic, $index + 1 );

		$prompt = sprintf(
			"Write a full SEO-optimized blog article.\nTitle: %s\nTopic: %s\n\nReturn JSON with keys: title, content (HTML), excerpt, categories (array of strings), tags (array of strings), meta_title, meta_description.\n%s",
			$title,
			$topic,
			ContentPrompts::admin_instructions()
		);

		$ai_result = $this->execute_ai( array( 'prompt' => $prompt ), $context, $module );

		if ( ! $ai_result['success'] ) {
			return $ai_result;
		}

		$article = $this->try_parse_json( $ai_result['data'] );

		if ( ! is_array( $article ) || empty( $article['content'] ) ) {
			return array(
				'success'         => false,
				'message'         => __( 'Failed to parse generated article.', 'agenpress' ),
				'data'            => null,
				'context_updates' => array(),
			);
		}

		$status = ! empty( $step['publish'] ) ? 'publish' : 'draft';

		$tool_result = $this->tool_registry->execute(
			'create_post',
			array(
				'title'      => $article['title'] ?? $title,
				'content'    => $article['content'],
				'excerpt'    => $article['excerpt'] ?? '',
				'categories' => $article['categories'] ?? array(),
				'tags'       => $article['tags'] ?? array(),
				'status'     => $status,
				'post_type'  => 'post',
			),
			$user_id,
			$module
		);

		$created = $context['created_posts'] ?? array();
		if ( $tool_result['success'] && is_array( $tool_result['data'] ) ) {
			$created[] = $tool_result['data'];
		}

		return array(
			'success'         => $tool_result['success'],
			'message'         => $tool_result['message'],
			'data'            => $tool_result['data'],
			'context_updates' => array(
				'created_posts' => $created,
				'last_article'  => $tool_result['data'],
			),
		);
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

		$products = $context['products'] ?? array();
		$index    = (int) ( $step['index'] ?? 0 );
		$product  = is_array( $products ) ? ( $products[ $index ] ?? null ) : null;

		if ( ! $product || empty( $product['id'] ) ) {
			$create_result = $this->tool_registry->execute(
				'create_product',
				array(
					'name'              => sprintf( '%s Product %d', $step['niche'] ?? 'Sample', $index + 1 ),
					'short_description' => 'Draft product',
					'status'            => 'draft',
				),
				$user_id,
				$module
			);

			if ( ! $create_result['success'] ) {
				return array(
					'success'         => false,
					'message'         => $create_result['message'],
					'data'            => null,
					'context_updates' => array(),
				);
			}

			$product = $create_result['data'];
		}

		$prompt = sprintf(
			'Write an SEO-optimized WooCommerce product description. Product: %s. Niche: %s. Return JSON with keys: description (HTML), short_description.',
			$product['name'] ?? 'Product',
			$step['niche'] ?? ''
		);

		$ai_result = $this->execute_ai( array( 'prompt' => $prompt ), $context, $module );

		if ( ! $ai_result['success'] ) {
			return $ai_result;
		}

		$copy = $this->try_parse_json( $ai_result['data'] );

		if ( ! is_array( $copy ) ) {
			$copy = array( 'description' => $ai_result['data'], 'short_description' => wp_trim_words( $ai_result['data'], 30 ) );
		}

		$tool_result = $this->tool_registry->execute(
			'update_product',
			array(
				'product_id'        => (int) $product['id'],
				'description'       => $copy['description'] ?? '',
				'short_description' => $copy['short_description'] ?? '',
			),
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
	 * @param mixed $content Content.
	 * @return mixed|null
	 */
	private function try_parse_json( mixed $content ): mixed {
		if ( ! is_string( $content ) ) {
			return null;
		}

		$content = trim( $content );
		$content = preg_replace( '/^```json\s*|\s*```$/s', '', $content );

		$decoded = json_decode( $content, true );

		return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
	}
}

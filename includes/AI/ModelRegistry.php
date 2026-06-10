<?php
/**
 * Available AI models catalog.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class ModelRegistry
 */
class ModelRegistry {

	/**
	 * Get text chat models.
	 *
	 * @return array<int, array{id: string, label: string, providers: array<int, string>}>
	 */
	public static function text_models(): array {
		return array(
			array( 'id' => 'gapgpt-qwen-3.5', 'label' => 'GapGPT: gapgpt-qwen-3.5', 'providers' => array( 'gapgpt' ) ),
			array( 'id' => 'gapgpt-qwen-3.5-thinking', 'label' => 'GapGPT: gapgpt-qwen-3.5-thinking', 'providers' => array( 'gapgpt' ) ),
			array( 'id' => 'gapgpt-qwen-3.6', 'label' => 'GapGPT: gapgpt-qwen-3.6', 'providers' => array( 'gapgpt' ) ),
			array( 'id' => 'gapgpt-qwen-3.6-thinking', 'label' => 'GapGPT: gapgpt-qwen-3.6-thinking', 'providers' => array( 'gapgpt' ) ),
			array( 'id' => 'gpt-5.2', 'label' => 'OpenAI: gpt-5.2', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-5.2-chat-latest', 'label' => 'OpenAI: gpt-5.2-chat-latest', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-5.2-codex', 'label' => 'OpenAI: gpt-5.2-codex', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-5.2-pro', 'label' => 'OpenAI: gpt-5.2-pro', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-5.3-chat-latest', 'label' => 'OpenAI: gpt-5.3-chat-latest', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-5.3-codex', 'label' => 'OpenAI: gpt-5.3-codex', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-5.3-codex-spark', 'label' => 'OpenAI: gpt-5.3-codex-spark', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-opus-4-1-20250805', 'label' => 'Anthropic: claude-opus-4-1-20250805', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-opus-4-20250514', 'label' => 'Anthropic: claude-opus-4-20250514', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-opus-4-5-20251101', 'label' => 'Anthropic: claude-opus-4-5-20251101', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-opus-4-6', 'label' => 'Anthropic: claude-opus-4-6', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-opus-4-7', 'label' => 'Anthropic: claude-opus-4-7', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-opus-4-8', 'label' => 'Anthropic: claude-opus-4-8', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-sonnet-4-20250514', 'label' => 'Anthropic: claude-sonnet-4-20250514', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-sonnet-4-5-20250929', 'label' => 'Anthropic: claude-sonnet-4-5-20250929', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
			array( 'id' => 'claude-sonnet-4-6', 'label' => 'Anthropic: claude-sonnet-4-6', 'providers' => array( 'claude', 'gapgpt', 'custom' ) ),
		);
	}

	/**
	 * Get image generation models.
	 *
	 * @return array<int, array{id: string, label: string, providers: array<int, string>}>
	 */
	public static function image_models(): array {
		return array(
			array( 'id' => 'gapgpt/z-image', 'label' => 'GapGPT: gapgpt/z-image', 'providers' => array( 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-image-2', 'label' => 'OpenAI: gpt-image-2', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gemini-3-pro-image-preview', 'label' => 'Google: gemini-3-pro-image-preview', 'providers' => array( 'gapgpt', 'custom' ) ),
			array( 'id' => 'gpt-image-1-mini', 'label' => 'OpenAI: gpt-image-1-mini', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'dall-e-3', 'label' => 'OpenAI: dall-e-3', 'providers' => array( 'openai', 'gapgpt', 'custom' ) ),
			array( 'id' => 'gemini-2.5-flash-image', 'label' => 'Google: gemini-2.5-flash-image', 'providers' => array( 'gapgpt', 'custom' ) ),
			array( 'id' => 'gemini-3.1-flash-image-preview', 'label' => 'Google: gemini-3.1-flash-image-preview', 'providers' => array( 'gapgpt', 'custom' ) ),
			array( 'id' => 'imagen-4.0-fast-generate-001', 'label' => 'Google: imagen-4.0-fast-generate-001', 'providers' => array( 'gapgpt', 'custom' ) ),
			array( 'id' => 'imagen-4.0-generate-001', 'label' => 'Google: imagen-4.0-generate-001', 'providers' => array( 'gapgpt', 'custom' ) ),
			array( 'id' => 'imagen-4.0-ultra-generate-001', 'label' => 'Google: imagen-4.0-ultra-generate-001', 'providers' => array( 'gapgpt', 'custom' ) ),
		);
	}

	/**
	 * Get full model catalog for settings UI.
	 *
	 * @return array{text: array<int, array<string, mixed>>, image: array<int, array<string, mixed>>}
	 */
	public static function catalog(): array {
		return array(
			'text'  => self::text_models(),
			'image' => self::image_models(),
		);
	}

	/**
	 * Filter models by provider slug.
	 *
	 * @param string $provider Provider slug.
	 * @param string $type     Model type: text or image.
	 * @return array<int, array{id: string, label: string, providers: array<int, string>}>
	 */
	public static function for_provider( string $provider, string $type = 'text' ): array {
		$models = 'image' === $type ? self::image_models() : self::text_models();

		return array_values(
			array_filter(
				$models,
				static function ( array $model ) use ( $provider ): bool {
					return in_array( $provider, $model['providers'], true );
				}
			)
		);
	}
}

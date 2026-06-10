<?php
/**
 * AI provider interface.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ProviderInterface
 */
interface ProviderInterface {

	/**
	 * Get provider slug.
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Send a chat completion request.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Messages.
	 * @param array<int, array<string, mixed>>                 $tools    Tool schemas.
	 * @param array<string, mixed>                             $options  Options.
	 * @return array{content: string, tool_calls: array<int, mixed>, tokens_used: int, model: string}
	 */
	public function chat( array $messages, array $tools = array(), array $options = array() ): array;

	/**
	 * Generate embeddings for text.
	 *
	 * @param string $text Input text.
	 * @return array<int, float>
	 */
	public function embed( string $text ): array;

	/**
	 * Generate an image from a text prompt.
	 *
	 * @param string               $prompt  Image prompt.
	 * @param array<string, mixed> $options Options (size, etc.).
	 * @return array{url: string, revised_prompt: string}
	 */
	public function generate_image( string $prompt, array $options = array() ): array;

	/**
	 * Check if provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;
}

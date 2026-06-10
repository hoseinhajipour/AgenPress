<?php
/**
 * Claude provider stub implementation.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

use AgenPress\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class ClaudeProvider
 */
class ClaudeProvider implements ProviderInterface {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'claude';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_configured(): bool {
		return '' !== $this->settings->get_api_key( 'claude' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function chat( array $messages, array $tools = array(), array $options = array() ): array {
		if ( ! $this->is_configured() ) {
			throw new \RuntimeException(
				__( 'Claude API key is not configured.', 'agenpress' )
			);
		}

		$api_key = $this->settings->get_api_key( 'claude' );
		$model   = $options['model'] ?? $this->settings->get_default_model();

		$body = array(
			'model'      => $model,
			'max_tokens' => $options['max_tokens'] ?? 4096,
			'messages'   => $this->format_messages( $messages ),
		);

		if ( ! empty( $tools ) ) {
			$body['tools'] = $this->format_tools( $tools );
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 60,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error = $data['error']['message'] ?? __( 'Claude API error.', 'agenpress' );
			throw new \RuntimeException( $error );
		}

		$content    = '';
		$tool_calls = array();

		foreach ( $data['content'] ?? array() as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$content .= $block['text'] ?? '';
			}

			if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
				$tool_calls[] = array(
					'id'        => $block['id'] ?? '',
					'name'      => $block['name'] ?? '',
					'arguments' => $block['input'] ?? array(),
				);
			}
		}

		return array(
			'content'     => $content,
			'tool_calls'  => $tool_calls,
			'tokens_used' => ( $data['usage']['input_tokens'] ?? 0 ) + ( $data['usage']['output_tokens'] ?? 0 ),
			'model'       => $model,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_image( string $prompt, array $options = array() ): array {
		throw new \RuntimeException(
			__( 'Image generation is not supported by Claude provider.', 'agenpress' )
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function embed( string $text ): array {
		// Claude does not provide embeddings API — return empty for Phase 1.
		return array();
	}

	/**
	 * Format messages for Claude API.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Messages.
	 * @return array<int, array{role: string, content: string}>
	 */
	private function format_messages( array $messages ): array {
		$formatted = array();

		foreach ( $messages as $message ) {
			if ( 'system' === $message['role'] ) {
				continue;
			}

			$formatted[] = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);
		}

		return $formatted;
	}

	/**
	 * Format OpenAI-style tools for Claude API.
	 *
	 * @param array<int, array<string, mixed>> $tools Tools.
	 * @return array<int, array<string, mixed>>
	 */
	private function format_tools( array $tools ): array {
		$formatted = array();

		foreach ( $tools as $tool ) {
			if ( isset( $tool['function'] ) ) {
				$formatted[] = array(
					'name'         => $tool['function']['name'],
					'description'  => $tool['function']['description'] ?? '',
					'input_schema' => $tool['function']['parameters'] ?? array( 'type' => 'object', 'properties' => array() ),
				);
			}
		}

		return $formatted;
	}
}

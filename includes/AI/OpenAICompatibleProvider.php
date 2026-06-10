<?php
/**
 * OpenAI-compatible API provider (shared chat, embed, image logic).
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

use AgenPress\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract OpenAI-compatible provider.
 */
abstract class OpenAICompatibleProvider implements ProviderInterface {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Provider slug used for API key storage.
	 *
	 * @return string
	 */
	abstract protected function get_key_slug(): string;

	/**
	 * API base URL including trailing path segment (e.g. https://api.example.com/v1/).
	 *
	 * @return string
	 */
	abstract protected function get_base_url(): string;

	/**
	 * Error message when API key is missing.
	 *
	 * @return string
	 */
	abstract protected function get_not_configured_message(): string;

	/**
	 * {@inheritdoc}
	 */
	public function is_configured(): bool {
		if ( '' === $this->get_base_url() ) {
			return false;
		}

		return '' !== $this->settings->get_api_key( $this->get_key_slug() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function chat( array $messages, array $tools = array(), array $options = array() ): array {
		if ( ! $this->is_configured() ) {
			throw new \RuntimeException( $this->get_not_configured_message() );
		}

		$model  = $options['model'] ?? $this->settings->get_default_model();
		$params = array(
			'model'    => $model,
			'messages' => $this->format_messages( $messages ),
		);

		if ( ! empty( $tools ) ) {
			$params['tools']       = $tools;
			$params['tool_choice'] = $options['tool_choice'] ?? 'auto';
		}

		if ( isset( $options['temperature'] ) ) {
			$params['temperature'] = (float) $options['temperature'];
		}

		$data   = $this->request( 'chat/completions', $params );
		$choice = $data['choices'][0] ?? null;

		if ( ! $choice ) {
			throw new \RuntimeException(
				__( 'No response from AI provider.', 'agenpress' )
			);
		}

		$tool_calls = array();

		if ( ! empty( $choice['message']['tool_calls'] ) ) {
			foreach ( $choice['message']['tool_calls'] as $call ) {
				$tool_calls[] = array(
					'id'        => $call['id'] ?? '',
					'name'      => $call['function']['name'] ?? '',
					'arguments' => json_decode( $call['function']['arguments'] ?? '{}', true ) ?: array(),
				);
			}
		}

		return array(
			'content'     => $choice['message']['content'] ?? '',
			'tool_calls'  => $tool_calls,
			'tokens_used' => (int) ( $data['usage']['total_tokens'] ?? 0 ),
			'model'       => $model,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate_image( string $prompt, array $options = array() ): array {
		if ( ! $this->is_configured() ) {
			throw new \RuntimeException( $this->get_not_configured_message() );
		}

		$size = ImageSizeRegistry::resolve_size( (string) ( $options['size'] ?? '' ) );

		$model = $options['model'] ?? $this->settings->get_default_image_model();

		$data = $this->request(
			'images/generations',
			array(
				'model'  => $model,
				'prompt' => $prompt,
				'n'      => 1,
				'size'   => $size,
			)
		);

		$item = $data['data'][0] ?? array();

		return array(
			'url'            => $item['url'] ?? '',
			'b64_json'       => $item['b64_json'] ?? '',
			'revised_prompt' => $item['revised_prompt'] ?? $prompt,
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function embed( string $text ): array {
		if ( ! $this->is_configured() ) {
			return array();
		}

		$data = $this->request(
			'embeddings',
			array(
				'model' => 'text-embedding-3-small',
				'input' => $text,
			)
		);

		return $data['data'][0]['embedding'] ?? array();
	}

	/**
	 * Normalize messages for the OpenAI Chat Completions API.
	 *
	 * @param array<int, array<string, mixed>> $messages Messages.
	 * @return array<int, array<string, mixed>>
	 */
	private function format_messages( array $messages ): array {
		$formatted = array();

		foreach ( $messages as $message ) {
			$role = $message['role'] ?? 'user';

			if ( 'tool' === $role ) {
				$formatted[] = array(
					'role'         => 'tool',
					'tool_call_id' => $message['tool_call_id'] ?? '',
					'content'      => $message['content'] ?? '',
				);
				continue;
			}

			if ( 'assistant' === $role && ! empty( $message['tool_calls'] ) ) {
				$tool_calls = array();

				foreach ( $message['tool_calls'] as $call ) {
					$tool_calls[] = array(
						'id'       => $call['id'] ?? '',
						'type'     => 'function',
						'function' => array(
							'name'      => $call['name'] ?? '',
							'arguments' => wp_json_encode( $call['arguments'] ?? array() ),
						),
					);
				}

				$entry = array(
					'role'       => 'assistant',
					'tool_calls' => $tool_calls,
				);

				if ( ! empty( $message['content'] ) ) {
					$entry['content'] = $message['content'];
				}

				$formatted[] = $entry;
				continue;
			}

			$formatted[] = array(
				'role'    => $role,
				'content' => $message['content'] ?? '',
			);
		}

		return $formatted;
	}

	/**
	 * Make an API request.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $body     Request body.
	 * @return array<string, mixed>
	 */
	private function request( string $endpoint, array $body ): array {
		$base_url = trailingslashit( $this->get_base_url() );
		$response = wp_remote_post(
			$base_url . $endpoint,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->settings->get_api_key( $this->get_key_slug() ),
					'Content-Type'  => 'application/json',
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
			$error = $data['error']['message'] ?? __( 'AI API error.', 'agenpress' );
			throw new \RuntimeException( $error );
		}

		return is_array( $data ) ? $data : array();
	}
}

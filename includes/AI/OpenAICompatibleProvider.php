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

		$reference_path = (string) ( $options['reference_path'] ?? '' );
		$model          = $options['model'] ?? $this->settings->get_default_image_model();

		if ( '' !== $reference_path && file_exists( $reference_path ) ) {
			if ( $this->model_supports_reference_images( $model ) ) {
				try {
					return $this->generate_image_edit( $prompt, $reference_path, $options );
				} catch ( \RuntimeException $e ) {
					if ( $this->model_requires_reference_upload( $model ) ) {
						throw $e;
					}
				}
			}

			$prompt = sprintf(
				/* translators: %s: original image prompt */
				__( 'Using the attached reference photo as visual inspiration (preserve the subject appearance and placement described below), create one complete banner image: %s', 'agenpress' ),
				$prompt
			);
		}

		$model = $options['model'] ?? $this->settings->get_default_image_model();
		$size  = ImageSizeRegistry::resolve_size_for_model(
			(string) ( $options['size'] ?? '' ),
			$model
		);

		$params = array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => 1,
			'response_format' => 'b64_json',
		);

		if ( '' !== $size ) {
			$params['size'] = $size;
		}

		try {
			$data = $this->request( 'images/generations', $params );
		} catch ( \RuntimeException $e ) {
			if ( false === stripos( $e->getMessage(), 'response_format' ) ) {
				throw $e;
			}

			unset( $params['response_format'] );
			$data = $this->request( 'images/generations', $params );
		}

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
	 * Alternate API base URLs when the primary host cannot be resolved.
	 *
	 * @return array<int, string>
	 */
	protected function get_fallback_base_urls(): array {
		return array();
	}

	/**
	 * Make an API request.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $body     Request body.
	 * @return array<string, mixed>
	 */
	private function request( string $endpoint, array $body ): array {
		$response = $this->remote_post(
			$endpoint,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->settings->get_api_key( $this->get_key_slug() ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			throw new \RuntimeException( $this->extract_api_error_message( $code, $data, $response ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Generate or edit an image using a reference photo (images/edits API).
	 *
	 * @param string               $prompt         Image prompt.
	 * @param string               $reference_path Local PNG path.
	 * @param array<string, mixed> $options        Options.
	 * @return array{url: string, b64_json: string, revised_prompt: string}
	 */
	private function generate_image_edit( string $prompt, string $reference_path, array $options ): array {
		$model = $options['model'] ?? $this->settings->get_default_image_model();
		$size  = ImageSizeRegistry::resolve_size_for_model(
			(string) ( $options['size'] ?? '' ),
			$model
		);

		$file_field = str_starts_with( $model, 'gpt-image-' ) ? 'image[]' : 'image';

		$fields = array(
			'model'           => $model,
			'prompt'          => $prompt,
			'n'               => '1',
			'response_format' => 'b64_json',
		);

		if ( '' !== $size ) {
			$fields['size'] = $size;
		}

		$files = array(
			$file_field => $reference_path,
		);

		try {
			$data = $this->request_multipart( 'images/edits', $fields, $files );
		} catch ( \RuntimeException $e ) {
			unset( $fields['response_format'] );

			try {
				$data = $this->request_multipart( 'images/edits', $fields, $files );
			} catch ( \RuntimeException $inner ) {
				if ( str_starts_with( $model, 'gpt-image-' ) ) {
					throw $inner;
				}

				unset( $fields['size'] );
				$fields['model'] = 'dall-e-2';
				$fields['size']  = '1024x1024';
				$data            = $this->request_multipart(
					'images/edits',
					$fields,
					array(
						'image' => $reference_path,
					)
				);
			}
		}

		$item = $data['data'][0] ?? array();

		if ( empty( $item['url'] ) && empty( $item['b64_json'] ) ) {
			throw new \RuntimeException( __( 'Reference image edit failed.', 'agenpress' ) );
		}

		return array(
			'url'            => $item['url'] ?? '',
			'b64_json'       => $item['b64_json'] ?? '',
			'revised_prompt' => $item['revised_prompt'] ?? $prompt,
		);
	}

	/**
	 * Whether a model accepts reference photos via the images/edits endpoint.
	 *
	 * @param string $model Image model ID.
	 * @return bool
	 */
	private function model_supports_reference_images( string $model ): bool {
		return str_starts_with( $model, 'gpt-image-' ) || 'dall-e-2' === $model;
	}

	/**
	 * Whether reference upload failure should abort instead of falling back to text-only generation.
	 *
	 * @param string $model Image model ID.
	 * @return bool
	 */
	private function model_requires_reference_upload( string $model ): bool {
		return str_starts_with( $model, 'gpt-image-' );
	}

	/**
	 * POST multipart/form-data to the API (for image edits).
	 *
	 * @param string                            $endpoint API path.
	 * @param array<string, string>             $fields   Form fields.
	 * @param array<string, string>             $files    File field => path map.
	 * @return array<string, mixed>
	 */
	private function request_multipart( string $endpoint, array $fields, array $files ): array {
		$boundary = 'agenpress-' . wp_generate_password( 16, false, false );
		$body     = '';

		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n";
			$body .= $value . "\r\n";
		}

		foreach ( $files as $name => $file_path ) {
			if ( ! is_readable( $file_path ) ) {
				continue;
			}

			$filename = basename( $file_path );
			$checked  = wp_check_filetype( $file_path );
			$mime     = $checked['type'] ?: 'image/png';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content  = file_get_contents( $file_path );

			if ( false === $content ) {
				throw new \RuntimeException( __( 'Could not read reference image file.', 'agenpress' ) );
			}

			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $filename . "\"\r\n";
			$body .= 'Content-Type: ' . $mime . "\r\n\r\n";
			$body .= $content . "\r\n";
		}

		$body .= '--' . $boundary . "--\r\n";

		$response = $this->remote_post(
			$endpoint,
			array(
				'timeout' => 180,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->settings->get_api_key( $this->get_key_slug() ),
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			throw new \RuntimeException( $this->extract_api_error_message( $code, is_array( $data ) ? $data : array(), $response ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * POST to the provider, trying fallback base URLs on DNS failures.
	 *
	 * @param string               $endpoint API path.
	 * @param array<string, mixed> $args     wp_remote_post args.
	 * @return array<string, mixed>
	 */
	private function remote_post( string $endpoint, array $args ): array {
		$last_error = null;

		foreach ( $this->get_request_base_urls() as $base_url ) {
			$response = wp_remote_post( $base_url . $endpoint, $args );

			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			$last_error = $response;

			if ( ! $this->is_host_resolution_error( $response ) ) {
				break;
			}
		}

		if ( $last_error instanceof \WP_Error ) {
			throw new \RuntimeException( $this->format_transport_error( $last_error ) );
		}

		throw new \RuntimeException( __( 'AI API request failed.', 'agenpress' ) );
	}

	/**
	 * Primary and fallback API base URLs.
	 *
	 * @return array<int, string>
	 */
	private function get_request_base_urls(): array {
		$urls = array( trailingslashit( $this->get_base_url() ) );

		foreach ( $this->get_fallback_base_urls() as $fallback ) {
			$normalized = trailingslashit( $fallback );

			if ( ! in_array( $normalized, $urls, true ) ) {
				$urls[] = $normalized;
			}
		}

		return $urls;
	}

	/**
	 * Whether a transport error is likely DNS / host resolution.
	 *
	 * @param \WP_Error $error Transport error.
	 * @return bool
	 */
	private function is_host_resolution_error( \WP_Error $error ): bool {
		if ( 'http_request_failed' !== $error->get_error_code() ) {
			return false;
		}

		$message = strtolower( $error->get_error_message() );

		return str_contains( $message, 'could not resolve host' )
			|| str_contains( $message, 'name or service not known' )
			|| str_contains( $message, 'getaddrinfo' );
	}

	/**
	 * Format transport-layer API errors for the UI.
	 *
	 * @param \WP_Error $error Transport error.
	 * @return string
	 */
	private function format_transport_error( \WP_Error $error ): string {
		if ( $this->is_host_resolution_error( $error ) ) {
			return sprintf(
				/* translators: %s: underlying cURL/DNS error message */
				__( 'Cannot reach the AI API server (%s). Check your internet connection and DNS settings, or use the alternate GapGPT endpoint in AgenPress settings.', 'agenpress' ),
				$error->get_error_message()
			);
		}

		return $error->get_error_message();
	}

	/**
	 * Extract a human-readable error from an API error response.
	 *
	 * @param int                  $code     HTTP status code.
	 * @param array<string, mixed> $data     Decoded JSON body.
	 * @param array<string, mixed>|\WP_Error $response Raw HTTP response.
	 * @return string
	 */
	private function extract_api_error_message( int $code, array $data, array $response ): string {
		$candidates = array(
			$data['error']['message'] ?? null,
			$data['message'] ?? null,
			is_string( $data['error'] ?? null ) ? $data['error'] : null,
		);

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				return $candidate;
			}
		}

		$body = wp_remote_retrieve_body( $response );
		$body = is_string( $body ) ? trim( wp_strip_all_tags( $body ) ) : '';

		if ( '' !== $body ) {
			$snippet = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 200 ) : substr( $body, 0, 200 );

			return sprintf(
				/* translators: 1: HTTP status code, 2: response body snippet */
				__( 'AI API error (HTTP %1$d): %2$s', 'agenpress' ),
				$code,
				$snippet
			);
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'AI API error (HTTP %d).', 'agenpress' ),
			$code
		);
	}
}

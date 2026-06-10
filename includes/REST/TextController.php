<?php
/**
 * AI text generation REST controller.
 *
 * @package AgenPress
 */

namespace AgenPress\REST;

use AgenPress\AI\ProviderFactory;
use AgenPress\Modules\ContentPrompts;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class TextController
 */
class TextController extends RestController {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/generate-text',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_text' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'prompt'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( Capabilities::USE_ADMIN_AI ) && current_user_can( 'edit_posts' );
	}

	/**
	 * POST /generate-text
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function generate_text( \WP_REST_Request $request ): \WP_REST_Response {
		$rate_limit = $this->check_rate_limit();

		if ( is_wp_error( $rate_limit ) ) {
			return $this->error( $rate_limit );
		}

		$prompt = sanitize_textarea_field( $request->get_param( 'prompt' ) ?? '' );

		if ( '' === $prompt ) {
			return $this->error(
				new \WP_Error(
					'agenpress_missing_prompt',
					__( 'Text prompt is required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$context = sanitize_textarea_field( $request->get_param( 'context' ) ?? '' );

		/** @var ProviderFactory $provider_factory */
		$provider_factory = $this->container->get( 'provider_factory' );
		$provider         = $provider_factory->get();

		if ( ! $provider->is_configured() ) {
			return $this->error(
				new \WP_Error(
					'agenpress_no_provider',
					__( 'An AI provider API key is required.', 'agenpress' ),
					array( 'status' => 400 )
				)
			);
		}

		$user_message = $prompt;

		if ( '' !== $context ) {
			$user_message = sprintf(
				"Selected content from editor:\n%s\n\nInstruction:\n%s",
				$context,
				$prompt
			);
		}

		$system = implode(
			"\n\n",
			array(
				'You are AgenPress, a WordPress content writing assistant.',
				'Generate content based on the user instruction.',
				'Return clean HTML suitable for the WordPress classic editor (use p, h2, h3, ul, ol, li, strong, em tags).',
				'Do not wrap the response in markdown code fences. Do not include explanations outside the content.',
				ContentPrompts::admin_instructions(),
			)
		);

		try {
			$response = $provider->chat(
				array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
					array(
						'role'    => 'user',
						'content' => $user_message,
					),
				)
			);
		} catch ( \Exception $e ) {
			return $this->error(
				new \WP_Error(
					'agenpress_text_generation_failed',
					$e->getMessage(),
					array( 'status' => 500 )
				)
			);
		}

		$content = trim( (string) ( $response['content'] ?? '' ) );

		if ( '' === $content ) {
			return $this->error(
				new \WP_Error(
					'agenpress_text_generation_failed',
					__( 'Text generation failed.', 'agenpress' ),
					array( 'status' => 500 )
				)
			);
		}

		$content = $this->strip_markdown_fences( $content );

		return $this->success(
			array(
				'content' => wp_kses_post( $content ),
			)
		);
	}

	/**
	 * Remove markdown code fences from AI output.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private function strip_markdown_fences( string $content ): string {
		$content = preg_replace( '/^```(?:html)?\s*\n?/i', '', $content ) ?? $content;
		$content = preg_replace( '/\n?```\s*$/', '', $content ) ?? $content;

		return trim( $content );
	}
}

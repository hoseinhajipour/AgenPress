<?php
/**
 * OpenAI provider implementation.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class OpenAIProvider
 */
class OpenAIProvider extends OpenAICompatibleProvider {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'openai';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_key_slug(): string {
		return 'openai';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_base_url(): string {
		return 'https://api.openai.com/v1';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_not_configured_message(): string {
		return __( 'OpenAI API key is not configured.', 'agenpress' );
	}
}

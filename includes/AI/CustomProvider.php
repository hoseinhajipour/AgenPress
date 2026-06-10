<?php
/**
 * Custom OpenAI-compatible AI agent provider.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class CustomProvider
 */
class CustomProvider extends OpenAICompatibleProvider {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'custom';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_key_slug(): string {
		return 'custom';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_base_url(): string {
		return $this->settings->get_custom_api_base_url();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_not_configured_message(): string {
		return __( 'Custom AI agent API key and base URL are not configured.', 'agenpress' );
	}
}

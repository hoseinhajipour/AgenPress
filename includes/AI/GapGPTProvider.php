<?php
/**
 * GapGPT provider (OpenAI-compatible gateway).
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class GapGPTProvider
 */
class GapGPTProvider extends OpenAICompatibleProvider {

	/**
	 * GapGPT API base URL.
	 */
	private const BASE_URL = 'https://api.gapgpt.app/v1';

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'gapgpt';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_key_slug(): string {
		return 'gapgpt';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_base_url(): string {
		return self::BASE_URL;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_not_configured_message(): string {
		return __( 'GapGPT API key is not configured.', 'agenpress' );
	}
}

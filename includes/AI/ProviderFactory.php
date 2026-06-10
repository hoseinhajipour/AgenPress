<?php
/**
 * AI provider factory.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

use AgenPress\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProviderFactory
 */
class ProviderFactory {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Cached provider instances.
	 *
	 * @var array<string, ProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get a provider by slug.
	 *
	 * @param string|null $slug Provider slug or null for default.
	 * @return ProviderInterface
	 */
	public function get( ?string $slug = null ): ProviderInterface {
		$slug = $slug ?? $this->settings->get_default_provider();

		if ( isset( $this->providers[ $slug ] ) ) {
			return $this->providers[ $slug ];
		}

		$this->providers[ $slug ] = match ( $slug ) {
			'claude' => new ClaudeProvider( $this->settings ),
			default  => new OpenAIProvider( $this->settings ),
		};

		return $this->providers[ $slug ];
	}

	/**
	 * Get all available providers with status.
	 *
	 * @return array<int, array{slug: string, configured: bool}>
	 */
	public function list_providers(): array {
		return array(
			array(
				'slug'        => 'openai',
				'configured'  => $this->get( 'openai' )->is_configured(),
			),
			array(
				'slug'        => 'claude',
				'configured'  => $this->get( 'claude' )->is_configured(),
			),
		);
	}
}

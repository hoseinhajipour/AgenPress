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
			'claude'  => new ClaudeProvider( $this->settings ),
			'gapgpt'  => new GapGPTProvider( $this->settings ),
			'custom'  => new CustomProvider( $this->settings ),
			default   => new OpenAIProvider( $this->settings ),
		};

		return $this->providers[ $slug ];
	}

	/**
	 * Get the best configured provider for image generation.
	 *
	 * Picks a provider that supports the configured default image model.
	 *
	 * @return ProviderInterface
	 */
	public function get_image_provider(): ProviderInterface {
		$image_model = $this->settings->get_default_image_model();
		$model_entry = ModelRegistry::find_model( $image_model, 'image' );
		$candidates  = $model_entry['providers'] ?? array( 'openai', 'gapgpt', 'custom' );

		$default_provider = $this->settings->get_default_provider();
		$preferred        = array_values(
			array_unique(
				array_merge(
					in_array( $default_provider, $candidates, true ) ? array( $default_provider ) : array(),
					$candidates
				)
			)
		);

		foreach ( $preferred as $slug ) {
			if ( 'claude' === $slug ) {
				continue;
			}

			$provider = $this->get( $slug );

			if ( $provider->is_configured() ) {
				return $provider;
			}
		}

		return $this->get( 'openai' );
	}

	/**
	 * Get all available providers with status.
	 *
	 * @return array<int, array{slug: string, configured: bool}>
	 */
	public function list_providers(): array {
		return array(
			array(
				'slug'       => 'openai',
				'configured' => $this->get( 'openai' )->is_configured(),
			),
			array(
				'slug'       => 'claude',
				'configured' => $this->get( 'claude' )->is_configured(),
			),
			array(
				'slug'       => 'gapgpt',
				'configured' => $this->get( 'gapgpt' )->is_configured(),
			),
			array(
				'slug'       => 'custom',
				'configured' => $this->get( 'custom' )->is_configured(),
			),
		);
	}
}

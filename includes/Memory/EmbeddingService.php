<?php
/**
 * Embedding generation via AI providers.
 *
 * @package AgenPress
 */

namespace AgenPress\Memory;

use AgenPress\AI\ProviderFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmbeddingService
 */
class EmbeddingService {

	/**
	 * Provider factory.
	 *
	 * @var ProviderFactory
	 */
	private ProviderFactory $provider_factory;

	/**
	 * Constructor.
	 *
	 * @param ProviderFactory $provider_factory Provider factory.
	 */
	public function __construct( ProviderFactory $provider_factory ) {
		$this->provider_factory = $provider_factory;
	}

	/**
	 * Generate embedding vector for text.
	 *
	 * @param string $text Input text.
	 * @return array<int, float>
	 */
	public function embed( string $text ): array {
		$text = trim( $text );

		if ( '' === $text ) {
			return array();
		}

		if ( ! $this->is_available() ) {
			return array();
		}

		try {
			$vector = $this->provider_factory->get( 'openai' )->embed( $text );
			return is_array( $vector ) ? $vector : array();
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Check if embedding generation is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return $this->provider_factory->get( 'openai' )->is_configured();
	}
}

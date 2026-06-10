<?php
/**
 * Plugin settings manager with encrypted API key storage.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	/**
	 * Option name.
	 */
	private const OPTION_KEY = 'agenpress_settings';

	/**
	 * Get all settings (API keys masked).
	 *
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		$settings = $this->get_raw();

		return array(
			'default_provider'      => $settings['default_provider'] ?? 'openai',
			'default_model'         => $settings['default_model'] ?? 'gpt-4o-mini',
			'rate_limit'            => (int) ( $settings['rate_limit'] ?? 60 ),
			'license_tier'          => $settings['license_tier'] ?? 'basic',
			'sales_chat_enabled'    => ! empty( $settings['sales_chat_enabled'] ),
			'sales_chat_title'      => $settings['sales_chat_title'] ?? __( 'Chat with us', 'agenpress' ),
			'sales_chat_position'   => $settings['sales_chat_position'] ?? 'bottom-right',
			'sales_chat_color'      => $settings['sales_chat_color'] ?? '#2271b1',
			'openai_api_key'        => $this->mask_key( $this->decrypt( $settings['openai_api_key'] ?? '' ) ),
			'claude_api_key'        => $this->mask_key( $this->decrypt( $settings['claude_api_key'] ?? '' ) ),
			'has_openai_key'        => ! empty( $settings['openai_api_key'] ),
			'has_claude_key'        => ! empty( $settings['claude_api_key'] ),
		);
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return array<string, mixed>
	 */
	public function update( array $data ): array {
		$current = $this->get_raw();

		$allowed = array(
			'default_provider',
			'default_model',
			'rate_limit',
			'license_tier',
			'sales_chat_enabled',
			'sales_chat_title',
			'sales_chat_position',
			'sales_chat_color',
		);

		foreach ( $allowed as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}

			if ( 'sales_chat_enabled' === $key ) {
				$current[ $key ] = (bool) $data[ $key ];
				continue;
			}

			$current[ $key ] = sanitize_text_field( (string) $data[ $key ] );
		}

		if ( ! empty( $data['openai_api_key'] ) && ! str_contains( $data['openai_api_key'], '****' ) ) {
			$current['openai_api_key'] = $this->encrypt( sanitize_text_field( $data['openai_api_key'] ) );
		}

		if ( ! empty( $data['claude_api_key'] ) && ! str_contains( $data['claude_api_key'], '****' ) ) {
			$current['claude_api_key'] = $this->encrypt( sanitize_text_field( $data['claude_api_key'] ) );
		}

		update_option( self::OPTION_KEY, $current );

		return $this->get_all();
	}

	/**
	 * Get decrypted API key for a provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public function get_api_key( string $provider ): string {
		$settings = $this->get_raw();
		$key      = $provider . '_api_key';

		return isset( $settings[ $key ] ) ? $this->decrypt( $settings[ $key ] ) : '';
	}

	/**
	 * Get default provider slug.
	 *
	 * @return string
	 */
	public function get_default_provider(): string {
		$settings = $this->get_raw();

		return $settings['default_provider'] ?? 'openai';
	}

	/**
	 * Get default model name.
	 *
	 * @return string
	 */
	public function get_default_model(): string {
		$settings = $this->get_raw();

		return $settings['default_model'] ?? 'gpt-4o-mini';
	}

	/**
	 * Get rate limit per hour.
	 *
	 * @return int
	 */
	public function get_rate_limit(): int {
		$settings = $this->get_raw();

		return (int) ( $settings['rate_limit'] ?? 60 );
	}

	/**
	 * Get license tier.
	 *
	 * @return string
	 */
	public function get_license_tier(): string {
		$settings = $this->get_raw();

		return $settings['license_tier'] ?? 'basic';
	}

	/**
	 * Check if storefront sales chat is enabled.
	 *
	 * @return bool
	 */
	public function is_sales_chat_enabled(): bool {
		$settings = $this->get_raw();

		return ! empty( $settings['sales_chat_enabled'] ) && class_exists( 'WooCommerce' );
	}

	/**
	 * Get public sales chat config for frontend.
	 *
	 * @return array<string, mixed>
	 */
	public function get_sales_chat_public_config(): array {
		$settings = $this->get_raw();

		return array(
			'enabled'  => $this->is_sales_chat_enabled(),
			'title'    => $settings['sales_chat_title'] ?? __( 'Chat with us', 'agenpress' ),
			'position' => $settings['sales_chat_position'] ?? 'bottom-right',
			'color'    => $settings['sales_chat_color'] ?? '#2271b1',
		);
	}

	/**
	 * Get raw settings from database.
	 *
	 * @return array<string, mixed>
	 */
	private function get_raw(): array {
		$settings = get_option( self::OPTION_KEY, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Encrypt a value using WordPress salts.
	 *
	 * @param string $value Plain text value.
	 * @return string
	 */
	private function encrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$key    = $this->get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $value Encrypted value.
	 * @return string
	 */
	private function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$data = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $data || strlen( $data ) < 17 ) {
			return '';
		}

		$iv        = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );
		$key       = $this->get_encryption_key();
		$decrypted = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );

		return false !== $decrypted ? $decrypted : '';
	}

	/**
	 * Derive encryption key from WordPress AUTH_KEY.
	 *
	 * @return string
	 */
	private function get_encryption_key(): string {
		return hash( 'sha256', AUTH_KEY . 'agenpress', true );
	}

	/**
	 * Mask an API key for display.
	 *
	 * @param string $key API key.
	 * @return string
	 */
	private function mask_key( string $key ): string {
		if ( '' === $key ) {
			return '';
		}

		$visible = substr( $key, 0, 4 );
		$last    = substr( $key, -4 );

		return $visible . '****' . $last;
	}
}

<?php
/**
 * Plugin settings manager with encrypted API key storage.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

use AgenPress\AI\LanguageRegistry;
use AgenPress\AI\ModelRegistry;

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
			'default_image_model'   => $settings['default_image_model'] ?? 'dall-e-3',
			'ai_language'           => $this->get_ai_language(),
			'custom_api_base_url'   => $settings['custom_api_base_url'] ?? '',
			'rate_limit'            => (int) ( $settings['rate_limit'] ?? 60 ),
			'license_tier'          => $settings['license_tier'] ?? 'basic',
			'sales_chat_enabled'    => ! empty( $settings['sales_chat_enabled'] ),
			'sales_chat_title'      => $settings['sales_chat_title'] ?? __( 'Chat with us', 'agenpress' ),
			'sales_chat_position'   => $settings['sales_chat_position'] ?? 'bottom-right',
			'sales_chat_color'      => $settings['sales_chat_color'] ?? '#2271b1',
			'sales_chat_tone'       => $this->get_sales_chat_tone(),
			'sales_chat_rules_dos'  => $settings['sales_chat_rules_dos'] ?? '',
			'sales_chat_rules_donts' => $settings['sales_chat_rules_donts'] ?? '',
			'openai_api_key'        => $this->mask_key( $this->decrypt( $settings['openai_api_key'] ?? '' ) ),
			'claude_api_key'        => $this->mask_key( $this->decrypt( $settings['claude_api_key'] ?? '' ) ),
			'gapgpt_api_key'        => $this->mask_key( $this->decrypt( $settings['gapgpt_api_key'] ?? '' ) ),
			'custom_api_key'        => $this->mask_key( $this->decrypt( $settings['custom_api_key'] ?? '' ) ),
			'has_openai_key'        => ! empty( $settings['openai_api_key'] ),
			'has_claude_key'        => ! empty( $settings['claude_api_key'] ),
			'has_gapgpt_key'        => ! empty( $settings['gapgpt_api_key'] ),
			'has_custom_key'        => ! empty( $settings['custom_api_key'] ),
			'model_catalog'         => ModelRegistry::catalog(),
			'language_catalog'      => LanguageRegistry::catalog(),
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
			'default_image_model',
			'ai_language',
			'custom_api_base_url',
			'rate_limit',
			'license_tier',
			'sales_chat_enabled',
			'sales_chat_title',
			'sales_chat_position',
			'sales_chat_color',
			'sales_chat_tone',
			'sales_chat_rules_dos',
			'sales_chat_rules_donts',
		);

		foreach ( $allowed as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}

			if ( 'sales_chat_enabled' === $key ) {
				$current[ $key ] = (bool) $data[ $key ];
				continue;
			}

			if ( in_array( $key, array( 'sales_chat_rules_dos', 'sales_chat_rules_donts' ), true ) ) {
				$current[ $key ] = sanitize_textarea_field( (string) $data[ $key ] );
				continue;
			}

			if ( 'sales_chat_tone' === $key ) {
				$tone = sanitize_text_field( (string) $data[ $key ] );
				if ( in_array( $tone, array( 'polite', 'friendly', 'professional' ), true ) ) {
					$current[ $key ] = $tone;
				}
				continue;
			}

			if ( 'ai_language' === $key ) {
				$language = sanitize_text_field( (string) $data[ $key ] );
				if ( LanguageRegistry::is_valid( $language ) ) {
					$current[ $key ] = $language;
				}
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

		if ( ! empty( $data['gapgpt_api_key'] ) && ! str_contains( $data['gapgpt_api_key'], '****' ) ) {
			$current['gapgpt_api_key'] = $this->encrypt( sanitize_text_field( $data['gapgpt_api_key'] ) );
		}

		if ( ! empty( $data['custom_api_key'] ) && ! str_contains( $data['custom_api_key'], '****' ) ) {
			$current['custom_api_key'] = $this->encrypt( sanitize_text_field( $data['custom_api_key'] ) );
		}

		if ( isset( $data['custom_api_base_url'] ) ) {
			$current['custom_api_base_url'] = esc_url_raw( trim( (string) $data['custom_api_base_url'] ) );
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
	 * Get default image model name.
	 *
	 * @return string
	 */
	public function get_default_image_model(): string {
		$settings = $this->get_raw();

		return $settings['default_image_model'] ?? 'dall-e-3';
	}

	/**
	 * Get custom OpenAI-compatible API base URL.
	 *
	 * @return string
	 */
	public function get_custom_api_base_url(): string {
		$settings = $this->get_raw();

		return $settings['custom_api_base_url'] ?? '';
	}

	/**
	 * Get configured AI base language (ISO 639-1).
	 *
	 * @return string
	 */
	public function get_ai_language(): string {
		$settings = $this->get_raw();
		$language = $settings['ai_language'] ?? '';

		if ( is_string( $language ) && LanguageRegistry::is_valid( $language ) ) {
			return $language;
		}

		return LanguageRegistry::default_from_locale( \function_exists( 'get_locale' ) ? \get_locale() : 'en_US' );
	}

	/**
	 * Get system-prompt instruction for the configured AI language.
	 *
	 * @return string
	 */
	public function get_ai_language_instruction(): string {
		return LanguageRegistry::get_ai_instruction( $this->get_ai_language() );
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
	 * Get configured storefront sales chat tone.
	 *
	 * @return string polite|friendly|professional
	 */
	public function get_sales_chat_tone(): string {
		$settings = $this->get_raw();
		$tone     = $settings['sales_chat_tone'] ?? 'polite';

		return in_array( $tone, array( 'polite', 'friendly', 'professional' ), true ) ? $tone : 'polite';
	}

	/**
	 * Build storefront sales chat prompt instructions (tone + sales rules).
	 *
	 * @return string
	 */
	public function get_sales_chat_prompt_instructions(): string {
		$settings = $this->get_raw();
		$parts    = array();

		$tone_instructions = array(
			'polite'       => __( 'Use a polite, respectful tone. Address customers courteously and with warmth.', 'agenpress' ),
			'friendly'     => __( 'Use a warm, casual, conversational tone. Be approachable and relatable while staying helpful.', 'agenpress' ),
			'professional' => __( 'Use a professional, confident business tone. Be clear, structured, and expert without being stiff.', 'agenpress' ),
		);

		$tone = $this->get_sales_chat_tone();
		$parts[] = __( 'Conversation tone:', 'agenpress' ) . ' ' . ( $tone_instructions[ $tone ] ?? $tone_instructions['polite'] );

		$dos = $this->parse_sales_rules( $settings['sales_chat_rules_dos'] ?? '' );
		if ( ! empty( $dos ) ) {
			$parts[] = __( 'Sales rules — DO:', 'agenpress' ) . "\n- " . implode( "\n- ", $dos );
		}

		$donts = $this->parse_sales_rules( $settings['sales_chat_rules_donts'] ?? '' );
		if ( ! empty( $donts ) ) {
			$parts[] = __( 'Sales rules — DO NOT:', 'agenpress' ) . "\n- " . implode( "\n- ", $donts );
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return __( 'Storefront sales chat behavior:', 'agenpress' ) . "\n" . implode( "\n\n", $parts );
	}

	/**
	 * Parse multiline sales rules into a sanitized list.
	 *
	 * @param string $raw Raw rules text.
	 * @return array<int, string>
	 */
	private function parse_sales_rules( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$rules = array();

		foreach ( $lines as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			$line = ltrim( $line, "-•*\t " );

			if ( '' !== $line ) {
				$rules[] = $line;
			}
		}

		return $rules;
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

<?php
/**
 * Auto-extract brand and site info into memory.
 *
 * @package AgenPress
 */

namespace AgenPress\Memory;

defined( 'ABSPATH' ) || exit;

/**
 * Class BrandExtractor
 */
class BrandExtractor {

	/**
	 * Memory store.
	 *
	 * @var MemoryStore
	 */
	private MemoryStore $memory_store;

	/**
	 * Constructor.
	 *
	 * @param MemoryStore $memory_store Memory store.
	 */
	public function __construct( MemoryStore $memory_store ) {
		$this->memory_store = $memory_store;
	}

	/**
	 * Extract site knowledge without saving.
	 *
	 * @return array<int, array{category: string, key_name: string, value: string, metadata: array<string, mixed>}>
	 */
	public function extract(): array {
		$entries = array();

		$entries[] = $this->entry( 'brand', 'site_name', get_bloginfo( 'name' ) );
		$entries[] = $this->entry( 'brand', 'tagline', get_bloginfo( 'description' ) );
		$entries[] = $this->entry( 'contact', 'site_url', home_url() );
		$entries[] = $this->entry( 'contact', 'admin_email', get_option( 'admin_email', '' ) );

		$locale = get_locale();
		if ( $locale ) {
			$entries[] = $this->entry( 'general', 'locale', $locale );
		}

		$entries = array_merge( $entries, $this->extract_theme_customizer() );
		$entries = array_merge( $entries, $this->extract_elementor() );
		$entries = array_merge( $entries, $this->extract_woocommerce() );

		return array_values(
			array_filter(
				$entries,
				static function ( ?array $entry ): bool {
					return null !== $entry && '' !== trim( $entry['value'] ?? '' );
				}
			)
		);
	}

	/**
	 * Extract and upsert into memory store.
	 *
	 * @return array{created: int, updated: int, entries: array<int, array<string, mixed>>}
	 */
	public function import(): array {
		$created  = 0;
		$updated  = 0;
		$saved    = array();

		foreach ( $this->extract() as $item ) {
			$existing = $this->memory_store->find_by_key( $item['category'], $item['key_name'] );

			if ( $existing ) {
				$entry = $this->memory_store->update(
					$existing['id'],
					array(
						'value'    => $item['value'],
						'metadata' => array_merge(
							$existing['metadata'] ?? array(),
							$item['metadata'],
							array( 'source' => 'auto_extract' )
						),
					)
				);
				++$updated;
			} else {
				$entry = $this->memory_store->create(
					$item['category'],
					$item['key_name'],
					$item['value'],
					array_merge( $item['metadata'], array( 'source' => 'auto_extract' ) )
				);
				++$created;
			}

			if ( $entry ) {
				$saved[] = $entry;
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'entries' => $saved,
		);
	}

	/**
	 * Extract theme customizer values.
	 *
	 * @return array<int, array{category: string, key_name: string, value: string, metadata: array<string, mixed>}>
	 */
	private function extract_theme_customizer(): array {
		$entries = array();

		$logo_id = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$entries[] = $this->entry( 'design', 'logo_url', $logo_url );
			}
		}

		$color_keys = array(
			'primary_color'   => 'primary_color',
			'secondary_color' => 'secondary_color',
			'background_color' => 'background_color',
			'header_textcolor' => 'header_text_color',
		);

		foreach ( $color_keys as $mod_key => $memory_key ) {
			$color = get_theme_mod( $mod_key, '' );
			if ( $color ) {
				$entries[] = $this->entry( 'design', $memory_key, $color );
			}
		}

		$stylesheet = get_stylesheet();
		if ( $stylesheet ) {
			$entries[] = $this->entry( 'design', 'active_theme', $stylesheet );
		}

		$site_icon = (int) get_option( 'site_icon', 0 );
		if ( $site_icon ) {
			$icon_url = wp_get_attachment_image_url( $site_icon, 'full' );
			if ( $icon_url ) {
				$entries[] = $this->entry( 'design', 'site_icon_url', $icon_url );
			}
		}

		return $entries;
	}

	/**
	 * Extract Elementor global kit settings when available.
	 *
	 * @return array<int, array{category: string, key_name: string, value: string, metadata: array<string, mixed>}>
	 */
	private function extract_elementor(): array {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return array();
		}

		$entries = array();
		$kit_id  = (int) get_option( 'elementor_active_kit', 0 );

		if ( ! $kit_id ) {
			return array();
		}

		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

		if ( ! is_array( $settings ) ) {
			return array();
		}

		if ( ! empty( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ) {
			$colors = array();
			foreach ( $settings['system_colors'] as $color ) {
				if ( ! empty( $color['title'] ) && ! empty( $color['color'] ) ) {
					$colors[] = sprintf( '%s: %s', $color['title'], $color['color'] );
				}
			}
			if ( ! empty( $colors ) ) {
				$entries[] = $this->entry( 'design', 'elementor_system_colors', implode( ', ', $colors ) );
			}
		}

		if ( ! empty( $settings['system_typography'] ) && is_array( $settings['system_typography'] ) ) {
			$fonts = array();
			foreach ( $settings['system_typography'] as $font ) {
				if ( ! empty( $font['title'] ) && ! empty( $font['typography_font_family'] ) ) {
					$fonts[] = sprintf( '%s: %s', $font['title'], $font['typography_font_family'] );
				}
			}
			if ( ! empty( $fonts ) ) {
				$entries[] = $this->entry( 'design', 'elementor_typography', implode( ', ', $fonts ) );
			}
		}

		if ( ! empty( $settings['container_width']['size'] ) ) {
			$entries[] = $this->entry(
				'design',
				'elementor_container_width',
				(string) $settings['container_width']['size'] . ( $settings['container_width']['unit'] ?? 'px' )
			);
		}

		return $entries;
	}

	/**
	 * Extract WooCommerce store details when available.
	 *
	 * @return array<int, array{category: string, key_name: string, value: string, metadata: array<string, mixed>}>
	 */
	private function extract_woocommerce(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$entries = array();

		$store_name = get_option( 'blogname', '' );
		if ( $store_name ) {
			$entries[] = $this->entry( 'brand', 'store_name', $store_name );
		}

		$address_fields = array(
			'woocommerce_store_address'       => 'store_address',
			'woocommerce_store_address_2'     => 'store_address_2',
			'woocommerce_store_city'          => 'store_city',
			'woocommerce_store_postcode'      => 'store_postcode',
			'woocommerce_default_country'     => 'store_country',
		);

		foreach ( $address_fields as $option => $key ) {
			$value = get_option( $option, '' );
			if ( $value ) {
				$entries[] = $this->entry( 'contact', $key, (string) $value );
			}
		}

		$currency = get_option( 'woocommerce_currency', '' );
		if ( $currency ) {
			$entries[] = $this->entry( 'general', 'store_currency', $currency );
		}

		return $entries;
	}

	/**
	 * Build a normalized entry array.
	 *
	 * @param string $category Category.
	 * @param string $key_name Key name.
	 * @param string $value    Value.
	 * @return array{category: string, key_name: string, value: string, metadata: array<string, mixed>}|null
	 */
	private function entry( string $category, string $key_name, string $value ): ?array {
		$value = trim( wp_strip_all_tags( $value ) );

		if ( '' === $value ) {
			return null;
		}

		return array(
			'category' => $category,
			'key_name' => $key_name,
			'value'    => $value,
			'metadata' => array( 'extracted_at' => current_time( 'mysql', true ) ),
		);
	}
}

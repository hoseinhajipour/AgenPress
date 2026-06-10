<?php
/**
 * Supported AI image aspect ratios and pixel size mappings.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class ImageSizeRegistry
 */
class ImageSizeRegistry {

	/**
	 * Aspect ratio definitions mapped to API pixel sizes.
	 *
	 * @return array<string, array{size: string, label: string}>
	 */
	private static function definitions(): array {
		return array(
			'1:1'  => array(
				'size'  => '1024x1024',
				'label' => __( '1:1 (Square)', 'agenpress' ),
			),
			'16:9' => array(
				'size'  => '1792x1024',
				'label' => __( '16:9 (Landscape)', 'agenpress' ),
			),
			'9:16' => array(
				'size'  => '1024x1792',
				'label' => __( '9:16 (Portrait)', 'agenpress' ),
			),
			'4:3'  => array(
				'size'  => '1792x1024',
				'label' => __( '4:3 (Landscape)', 'agenpress' ),
			),
			'3:2'  => array(
				'size'  => '1792x1024',
				'label' => __( '3:2 (Landscape)', 'agenpress' ),
			),
		);
	}

	/**
	 * Get all aspect ratios for settings UI.
	 *
	 * @return array<int, array{id: string, label: string, size: string}>
	 */
	public static function catalog(): array {
		$items = array();

		foreach ( self::definitions() as $aspect => $definition ) {
			$items[] = array(
				'id'    => $aspect,
				'label' => $definition['label'],
				'size'  => $definition['size'],
			);
		}

		return $items;
	}

	/**
	 * Get data for post editor image modals.
	 *
	 * @param string $default_aspect Default aspect ratio key.
	 * @return array{sizes: array<int, array{value: string, label: string}>, defaultSize: string}
	 */
	public static function editor_localize_data( string $default_aspect = '1:1' ): array {
		$sizes = array();

		foreach ( self::catalog() as $item ) {
			$sizes[] = array(
				'value' => $item['id'],
				'label' => $item['label'],
			);
		}

		$default_aspect = self::is_valid_aspect( $default_aspect ) ? $default_aspect : '1:1';

		return array(
			'sizes'       => $sizes,
			'defaultSize' => $default_aspect,
		);
	}

	/**
	 * Check whether an aspect ratio key is supported.
	 *
	 * @param string $aspect Aspect ratio key.
	 * @return bool
	 */
	public static function is_valid_aspect( string $aspect ): bool {
		return isset( self::definitions()[ $aspect ] );
	}

	/**
	 * Check whether a pixel size is supported by the image API.
	 *
	 * @param string $size Pixel size (e.g. 1024x1024).
	 * @return bool
	 */
	public static function is_valid_pixel_size( string $size ): bool {
		return in_array( $size, self::allowed_pixel_sizes(), true );
	}

	/**
	 * Get unique pixel sizes accepted by the image API.
	 *
	 * @return array<string>
	 */
	public static function allowed_pixel_sizes(): array {
		$sizes = array();

		foreach ( self::definitions() as $definition ) {
			$sizes[] = $definition['size'];
		}

		return array_values( array_unique( $sizes ) );
	}

	/**
	 * Convert an aspect ratio key to a pixel size.
	 *
	 * @param string $aspect Aspect ratio key.
	 * @return string
	 */
	public static function to_pixel_size( string $aspect ): string {
		$definitions = self::definitions();

		if ( isset( $definitions[ $aspect ] ) ) {
			return $definitions[ $aspect ]['size'];
		}

		return '1024x1024';
	}

	/**
	 * Resolve an aspect ratio or pixel size to a valid API pixel size.
	 *
	 * @param string $input Aspect ratio key, pixel size, or empty for plugin default.
	 * @return string
	 */
	public static function resolve_size( string $input = '' ): string {
		if ( self::is_valid_aspect( $input ) ) {
			return self::to_pixel_size( $input );
		}

		if ( self::is_valid_pixel_size( $input ) ) {
			return $input;
		}

		$settings = get_option( 'agenpress_settings', array() );
		$aspect   = is_array( $settings ) ? ( $settings['default_image_aspect'] ?? '1:1' ) : '1:1';

		return self::to_pixel_size( self::is_valid_aspect( $aspect ) ? $aspect : '1:1' );
	}
}

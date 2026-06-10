<?php
/**
 * Sideload AI-generated images as optimized WebP attachments.
 *
 * @package AgenPress
 */

namespace AgenPress\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Class AiImageSideloader
 */
class AiImageSideloader {

	/**
	 * WebP compression quality (0–100).
	 */
	private const WEBP_QUALITY = 82;

	/**
	 * Download a remote image, convert to WebP, and add it to the media library.
	 *
	 * @param string $url   Remote image URL.
	 * @param string $title Attachment title.
	 * @return int Attachment ID or 0 on failure.
	 */
	public static function sideload( string $url, string $title ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );

		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		$webp_path = self::convert_to_webp( $tmp );

		if ( ! $webp_path ) {
			@unlink( $tmp );
			return 0;
		}

		if ( $webp_path !== $tmp ) {
			@unlink( $tmp );
		}

		$file = array(
			'name'     => sanitize_file_name( $title ) . '.webp',
			'tmp_name' => $webp_path,
			'type'     => 'image/webp',
		);

		$attachment_id = media_handle_sideload( $file, 0, $title );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $webp_path );
			return 0;
		}

		return (int) $attachment_id;
	}

	/**
	 * Convert a local image file to optimized WebP.
	 *
	 * @param string $source_path Absolute path to the source image.
	 * @return string|null Absolute path to the WebP file, or null on failure.
	 */
	private static function convert_to_webp( string $source_path ): ?string {
		$editor = wp_get_image_editor( $source_path );

		if ( is_wp_error( $editor ) ) {
			return null;
		}

		$editor->set_quality( self::WEBP_QUALITY );

		if ( $editor->supports_mime_type( 'image/webp' ) ) {
			$saved = $editor->save( null, 'image/webp' );

			if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
				return null;
			}

			return $saved['path'];
		}

		return self::convert_with_gd( $source_path );
	}

	/**
	 * Fallback WebP conversion when the image editor lacks WebP support.
	 *
	 * @param string $source_path Absolute path to the source image.
	 * @return string|null Absolute path to the WebP file, or null on failure.
	 */
	private static function convert_with_gd( string $source_path ): ?string {
		if ( ! function_exists( 'imagewebp' ) ) {
			return null;
		}

		$image = self::gd_load_image( $source_path );

		if ( ! $image ) {
			return null;
		}

		$webp_path = $source_path . '.webp';
		$success   = imagewebp( $image, $webp_path, self::WEBP_QUALITY );
		imagedestroy( $image );

		if ( ! $success || ! file_exists( $webp_path ) ) {
			return null;
		}

		return $webp_path;
	}

	/**
	 * Load an image resource from disk via GD.
	 *
	 * @param string $path Absolute file path.
	 * @return \GdImage|resource|false
	 */
	private static function gd_load_image( string $path ) {
		$mime = wp_check_filetype( $path )['type'] ?? '';

		return match ( $mime ) {
			'image/jpeg' => function_exists( 'imagecreatefromjpeg' ) ? imagecreatefromjpeg( $path ) : false,
			'image/png'  => function_exists( 'imagecreatefrompng' ) ? imagecreatefrompng( $path ) : false,
			'image/gif'  => function_exists( 'imagecreatefromgif' ) ? imagecreatefromgif( $path ) : false,
			'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : false,
			default      => false,
		};
	}
}

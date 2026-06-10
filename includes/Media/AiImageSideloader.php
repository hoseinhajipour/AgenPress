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
	 * Sideload an AI generation result (remote URL or base64 payload).
	 *
	 * @param array{url?: string, b64_json?: string} $image Generation result.
	 * @param string                                 $title Attachment title.
	 * @return int Attachment ID or 0 on failure.
	 */
	public static function sideload_result( array $image, string $title ): int {
		$result = self::sideload_result_detailed( $image, $title );

		return (int) ( $result['attachment_id'] ?? 0 );
	}

	/**
	 * Sideload an AI generation result with error details.
	 *
	 * @param array{url?: string, b64_json?: string} $image Generation result.
	 * @param string                                 $title Attachment title.
	 * @return array{attachment_id: int, error: string}
	 */
	public static function sideload_result_detailed( array $image, string $title ): array {
		if ( ! empty( $image['url'] ) ) {
			return self::sideload_detailed( (string) $image['url'], $title );
		}

		if ( ! empty( $image['b64_json'] ) ) {
			return self::sideload_base64_detailed( (string) $image['b64_json'], $title );
		}

		return array(
			'attachment_id' => 0,
			'error'         => __( 'No image URL or base64 data in AI response.', 'agenpress' ),
		);
	}

	/**
	 * Download a remote image, convert to WebP, and add it to the media library.
	 *
	 * @param string $url   Remote image URL.
	 * @param string $title Attachment title.
	 * @return int Attachment ID or 0 on failure.
	 */
	public static function sideload( string $url, string $title ): int {
		$result = self::sideload_detailed( $url, $title );

		return (int) ( $result['attachment_id'] ?? 0 );
	}

	/**
	 * Download a remote image with error details.
	 *
	 * @param string $url   Remote image URL.
	 * @param string $title Attachment title.
	 * @return array{attachment_id: int, error: string}
	 */
	public static function sideload_detailed( string $url, string $title ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = \download_url( $url );

		if ( \is_wp_error( $tmp ) ) {
			return array(
				'attachment_id' => 0,
				'error'         => sprintf(
					/* translators: %s: download error message */
					__( 'Failed to download image: %s', 'agenpress' ),
					$tmp->get_error_message()
				),
			);
		}

		return self::sideload_temp_file_detailed( $tmp, $title );
	}

	/**
	 * Save a base64-encoded image to the media library as optimized WebP.
	 *
	 * @param string $b64_json Base64 image payload.
	 * @param string $title    Attachment title.
	 * @return int Attachment ID or 0 on failure.
	 */
	public static function sideload_base64( string $b64_json, string $title ): int {
		$result = self::sideload_base64_detailed( $b64_json, $title );

		return (int) ( $result['attachment_id'] ?? 0 );
	}

	/**
	 * Save a base64-encoded image with error details.
	 *
	 * @param string $b64_json Base64 image payload.
	 * @param string $title    Attachment title.
	 * @return array{attachment_id: int, error: string}
	 */
	public static function sideload_base64_detailed( string $b64_json, string $title ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$binary = base64_decode( $b64_json, true );

		if ( false === $binary || '' === $binary ) {
			return array(
				'attachment_id' => 0,
				'error'         => __( 'Invalid base64 image data from AI.', 'agenpress' ),
			);
		}

		$tmp = \wp_tempnam( 'agenpress-ai' );

		if ( ! $tmp ) {
			return array(
				'attachment_id' => 0,
				'error'         => __( 'Could not create temporary file for image.', 'agenpress' ),
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $tmp, $binary ) ) {
			@unlink( $tmp );

			return array(
				'attachment_id' => 0,
				'error'         => __( 'Could not write temporary image file.', 'agenpress' ),
			);
		}

		return self::sideload_temp_file_detailed( $tmp, $title );
	}

	/**
	 * Convert a temp image file to WebP and add it to the media library.
	 *
	 * @param string $tmp_path Absolute path to a local image file.
	 * @param string $title    Attachment title.
	 * @return array{attachment_id: int, error: string}
	 */
	private static function sideload_temp_file_detailed( string $tmp_path, string $title ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$webp_path = self::convert_to_webp( $tmp_path );

		if ( ! $webp_path ) {
			@unlink( $tmp_path );

			return array(
				'attachment_id' => 0,
				'error'         => self::get_webp_conversion_error(),
			);
		}

		if ( $webp_path !== $tmp_path ) {
			@unlink( $tmp_path );
		}

		$file = array(
			'name'     => \sanitize_file_name( $title ) . '.webp',
			'tmp_name' => $webp_path,
			'type'     => 'image/webp',
		);

		$attachment_id = \media_handle_sideload( $file, 0, $title );

		if ( \is_wp_error( $attachment_id ) ) {
			@unlink( $webp_path );

			return array(
				'attachment_id' => 0,
				'error'         => sprintf(
					/* translators: %s: media library error message */
					__( 'Failed to add image to media library: %s', 'agenpress' ),
					$attachment_id->get_error_message()
				),
			);
		}

		return array(
			'attachment_id' => (int) $attachment_id,
			'error'         => '',
		);
	}

	/**
	 * Explain why WebP conversion failed on this server.
	 *
	 * @return string
	 */
	private static function get_webp_conversion_error(): string {
		if ( ! function_exists( 'imagewebp' ) ) {
			return __( 'WebP conversion failed: PHP GD extension does not support WebP on this server.', 'agenpress' );
		}

		return __( 'WebP conversion failed: image could not be processed.', 'agenpress' );
	}

	/**
	 * Convert a local image file to optimized WebP.
	 *
	 * @param string $source_path Absolute path to the source image.
	 * @return string|null Absolute path to the WebP file, or null on failure.
	 */
	private static function convert_to_webp( string $source_path ): ?string {
		$editor = \wp_get_image_editor( $source_path );

		if ( \is_wp_error( $editor ) ) {
			return null;
		}

		$editor->set_quality( self::WEBP_QUALITY );

		if ( $editor->supports_mime_type( 'image/webp' ) ) {
			$saved = $editor->save( null, 'image/webp' );

			if ( \is_wp_error( $saved ) || empty( $saved['path'] ) ) {
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
		$mime = \wp_check_filetype( $path )['type'] ?? '';

		return match ( $mime ) {
			'image/jpeg' => function_exists( 'imagecreatefromjpeg' ) ? imagecreatefromjpeg( $path ) : false,
			'image/png'  => function_exists( 'imagecreatefrompng' ) ? imagecreatefrompng( $path ) : false,
			'image/gif'  => function_exists( 'imagecreatefromgif' ) ? imagecreatefromgif( $path ) : false,
			'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : false,
			default      => false,
		};
	}
}

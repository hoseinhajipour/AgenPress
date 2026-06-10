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
		if ( ! empty( $image['b64_json'] ) ) {
			return self::sideload_base64_detailed( (string) $image['b64_json'], $title );
		}

		if ( ! empty( $image['url'] ) ) {
			return self::sideload_detailed( (string) $image['url'], $title );
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

		$tmp = \download_url( $url, 300 );

		if ( \is_wp_error( $tmp ) ) {
			$tmp = self::download_url_relaxed_ssl( $url );
		}

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
	 * Download a remote image when strict SSL verification fails (common on local dev).
	 *
	 * @param string $url Remote image URL.
	 * @return string|\WP_Error
	 */
	private static function download_url_relaxed_ssl( string $url ) {
		$response = \wp_remote_get(
			$url,
			array(
				'timeout'   => 300,
				'stream'    => true,
				'filename'  => \wp_tempnam( 'agenpress-ai-dl' ),
				'sslverify' => false,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'agenpress_image_download_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Image download failed with HTTP status %d.', 'agenpress' ),
					$code
				)
			);
		}

		$filename = $response['filename'] ?? '';

		if ( ! is_string( $filename ) || '' === $filename || ! file_exists( $filename ) ) {
			return new \WP_Error(
				'agenpress_image_download_failed',
				__( 'Image download failed: temporary file missing.', 'agenpress' )
			);
		}

		return $filename;
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

		if ( $webp_path && $webp_path !== $tmp_path ) {
			$result = self::media_handle_temp_file( $webp_path, $title, 'webp', 'image/webp' );

			if ( ! empty( $result['attachment_id'] ) ) {
				@unlink( $tmp_path );

				return $result;
			}

			if ( file_exists( $webp_path ) ) {
				@unlink( $webp_path );
			}
		}

		return self::sideload_original_file_detailed( $tmp_path, $title );
	}

	/**
	 * Sideload a temp image in its original format when WebP conversion is unavailable.
	 *
	 * @param string $tmp_path Absolute path to a local image file.
	 * @param string $title    Attachment title.
	 * @return array{attachment_id: int, error: string}
	 */
	private static function sideload_original_file_detailed( string $tmp_path, string $title ): array {
		$filetype = self::detect_temp_filetype( $tmp_path );

		return self::media_handle_temp_file( $tmp_path, $title, $filetype['ext'], $filetype['mime'] );
	}

	/**
	 * Detect image type for temp files that may lack a file extension.
	 *
	 * @param string $tmp_path Absolute path to a local image file.
	 * @return array{ext: string, mime: string}
	 */
	private static function detect_temp_filetype( string $tmp_path ): array {
		$checked = \wp_check_filetype_and_ext( $tmp_path, 'image.png' );
		$ext     = $checked['ext'] ?: 'png';
		$mime    = $checked['type'] ?: 'image/png';

		if ( function_exists( 'wp_get_image_mime' ) ) {
			$detected_mime = \wp_get_image_mime( $tmp_path );

			if ( is_string( $detected_mime ) && '' !== $detected_mime ) {
				$mime = $detected_mime;
				$ext  = self::mime_to_extension( $mime ) ?: $ext;
			}
		}

		return array(
			'ext'  => $ext,
			'mime' => $mime,
		);
	}

	/**
	 * Map a MIME type to a file extension.
	 *
	 * @param string $mime MIME type.
	 * @return string
	 */
	private static function mime_to_extension( string $mime ): string {
		return match ( $mime ) {
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			default      => '',
		};
	}

	/**
	 * Ensure the current WordPress uploads directory exists.
	 *
	 * @return string Empty string on success, otherwise an error message.
	 */
	private static function ensure_upload_dir(): string {
		$upload = \wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return (string) $upload['error'];
		}

		if ( ! \wp_mkdir_p( $upload['path'] ) ) {
			return sprintf(
				/* translators: %s: upload directory path */
				__( 'Could not create upload directory: %s', 'agenpress' ),
				$upload['path']
			);
		}

		if ( ! is_writable( $upload['path'] ) ) {
			return sprintf(
				/* translators: %s: upload directory path */
				__( 'Upload directory is not writable: %s', 'agenpress' ),
				$upload['path']
			);
		}

		return '';
	}

	/**
	 * Build a safe attachment filename.
	 *
	 * @param string $title Attachment title.
	 * @param string $ext   File extension without dot.
	 * @return string
	 */
	private static function build_attachment_filename( string $title, string $ext ): string {
		$base = \sanitize_file_name( $title );

		if ( '' === $base ) {
			$base = 'agenpress-ai-' . gmdate( 'Ymd-His' );
		}

		return $base . '.' . $ext;
	}

	/**
	 * Add a temp image file to the media library.
	 *
	 * @param string $tmp_path Absolute path to a local image file.
	 * @param string $title    Attachment title.
	 * @param string $ext      File extension without dot.
	 * @param string $mime     MIME type.
	 * @return array{attachment_id: int, error: string}
	 */
	private static function media_handle_temp_file( string $tmp_path, string $title, string $ext, string $mime ): array {
		$upload_error = self::ensure_upload_dir();

		if ( '' !== $upload_error ) {
			@unlink( $tmp_path );

			return array(
				'attachment_id' => 0,
				'error'         => $upload_error,
			);
		}

		$file = array(
			'name'     => self::build_attachment_filename( $title, $ext ),
			'tmp_name' => $tmp_path,
			'type'     => $mime,
		);

		$attachment_id = \media_handle_sideload( $file, 0, $title );

		if ( \is_wp_error( $attachment_id ) ) {
			$fallback_id = self::insert_attachment_fallback( $tmp_path, $title, $ext, $mime );

			if ( $fallback_id > 0 ) {
				return array(
					'attachment_id' => $fallback_id,
					'error'         => '',
				);
			}

			@unlink( $tmp_path );

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
	 * Fallback attachment insert when media_handle_sideload fails (common on some Windows hosts).
	 *
	 * @param string $tmp_path Absolute path to a local image file.
	 * @param string $title    Attachment title.
	 * @param string $ext      File extension without dot.
	 * @param string $mime     MIME type.
	 * @return int Attachment ID or 0 on failure.
	 */
	private static function insert_attachment_fallback( string $tmp_path, string $title, string $ext, string $mime ): int {
		if ( ! is_readable( $tmp_path ) ) {
			return 0;
		}

		$upload   = \wp_upload_dir();
		$filename = \wp_unique_filename( $upload['path'], self::build_attachment_filename( $title, $ext ) );
		$dest     = trailingslashit( $upload['path'] ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! copy( $tmp_path, $dest ) ) {
			return 0;
		}

		@unlink( $tmp_path );

		$filetype = \wp_check_filetype( $filename, null );
		$mime     = $filetype['type'] ?: $mime;

		$attachment_id = \wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => \sanitize_text_field( $title ) ?: 'AgenPress AI Image',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$dest
		);

		if ( \is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@unlink( $dest );

			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = \wp_generate_attachment_metadata( (int) $attachment_id, $dest );
		\wp_update_attachment_metadata( (int) $attachment_id, $metadata );

		return (int) $attachment_id;
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

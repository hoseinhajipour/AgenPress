<?php
/**
 * Prepare media library attachments for AI image reference APIs.
 *
 * @package AgenPress
 */

namespace AgenPress\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReferenceImagePreparer
 */
class ReferenceImagePreparer {

	/**
	 * Resolve an attachment ID to a local PNG path suitable for image edit APIs.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Absolute path or null on failure.
	 */
	public static function prepare_png_path( int $attachment_id ): ?string {
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return null;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$editor = \wp_get_image_editor( $path );

		if ( \is_wp_error( $editor ) ) {
			return str_ends_with( strtolower( $path ), '.png' ) ? $path : null;
		}

		$size = $editor->get_size();

		if ( is_array( $size ) && ( (int) ( $size['width'] ?? 0 ) ) > 1024 ) {
			$editor->resize( 1024, 1024, false );
		}

		$tmp = \wp_tempnam( 'agenpress-ref' );

		if ( ! $tmp ) {
			return str_ends_with( strtolower( $path ), '.png' ) ? $path : null;
		}

		$saved = $editor->save( $tmp, 'image/png' );

		if ( \is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			@unlink( $tmp );

			return str_ends_with( strtolower( $path ), '.png' ) ? $path : null;
		}

		return (string) $saved['path'];
	}

	/**
	 * Get the first image attachment ID from a context payload.
	 *
	 * @param array<int, mixed> $attachments Attachment list.
	 * @return int
	 */
	public static function first_image_attachment_id( array $attachments ): int {
		foreach ( $attachments as $attachment ) {
			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$id = (int) ( $attachment['id'] ?? 0 );

			if ( $id > 0 && wp_attachment_is_image( $id ) ) {
				return $id;
			}

			$url  = (string) ( $attachment['url'] ?? '' );
			$type = (string) ( $attachment['type'] ?? '' );

			if ( self::looks_like_image( $type, $url ) && $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * @param string $mime MIME type.
	 * @param string $url  File URL.
	 * @return bool
	 */
	private static function looks_like_image( string $mime, string $url ): bool {
		if ( str_starts_with( $mime, 'image/' ) ) {
			return true;
		}

		return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $url );
	}
}

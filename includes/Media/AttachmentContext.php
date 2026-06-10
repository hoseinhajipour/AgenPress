<?php
/**
 * Format uploaded attachments for AI prompts.
 *
 * @package AgenPress
 */

namespace AgenPress\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Class AttachmentContext
 */
class AttachmentContext {

	/**
	 * Append attachment metadata to a user message for the AI.
	 *
	 * @param string               $content     Message text.
	 * @param array<int, mixed>    $attachments Attachment payloads.
	 * @return string
	 */
	public static function append_to_content( string $content, array $attachments ): string {
		if ( empty( $attachments ) ) {
			return $content;
		}

		$lines = array( '', __( 'Attached files:', 'agenpress' ) );

		foreach ( $attachments as $attachment ) {
			if ( ! is_array( $attachment ) ) {
				continue;
			}

			$id   = (int) ( $attachment['id'] ?? 0 );
			$url  = esc_url_raw( (string) ( $attachment['url'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $attachment['name'] ?? '' ) );
			$type = sanitize_text_field( (string) ( $attachment['type'] ?? '' ) );

			if ( $id <= 0 && '' === $url ) {
				continue;
			}

			if ( '' === $url && $id > 0 ) {
				$url = (string) wp_get_attachment_url( $id );
			}

			if ( self::is_image( $type, $url ) ) {
				$lines[] = sprintf(
					'- %s (attachment_id: %d, url: %s)',
					$name ?: __( 'Image', 'agenpress' ),
					$id,
					$url
				);
				$lines[] = sprintf( '  ![%s](%s)', $name ?: 'image', $url );
				continue;
			}

			$lines[] = sprintf(
				'- %s (attachment_id: %d, url: %s, type: %s)',
				$name ?: __( 'File', 'agenpress' ),
				$id,
				$url,
				$type
			);

			$excerpt = self::extract_text_excerpt( $id );

			if ( '' !== $excerpt ) {
				$lines[] = __( '  File excerpt:', 'agenpress' ) . ' ' . $excerpt;
			}
		}

		if ( count( $lines ) <= 2 ) {
			return $content;
		}

		$lines[] = __( 'For Elementor: call add_attached_image_to_page with attachment_id to insert an image widget. Use apply_media_to_element only for existing widgets.', 'agenpress' );

		return rtrim( $content ) . implode( "\n", $lines );
	}

	/**
	 * Format attachments for system/runtime context blocks.
	 *
	 * @param array<int, mixed> $attachments Attachment payloads.
	 * @return string
	 */
	public static function format_context_block( array $attachments ): string {
		if ( empty( $attachments ) ) {
			return '';
		}

		return self::append_to_content( '', $attachments );
	}

	/**
	 * Check if attachment is an image.
	 *
	 * @param string $mime MIME type.
	 * @param string $url  File URL.
	 * @return bool
	 */
	private static function is_image( string $mime, string $url ): bool {
		if ( str_starts_with( $mime, 'image/' ) ) {
			return true;
		}

		return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $url );
	}

	/**
	 * Extract readable text from a text-based attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function extract_text_excerpt( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}

		$mime = (string) get_post_mime_type( $attachment_id );
		$text = '';

		if ( in_array( $mime, array( 'text/plain', 'text/csv', 'application/json', 'text/markdown' ), true ) ) {
			$raw = file_get_contents( $path );
			$text = is_string( $raw ) ? $raw : '';
		}

		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? '';
		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		return mb_strlen( $text ) > 2000 ? mb_substr( $text, 0, 1997 ) . '...' : $text;
	}
}

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
	public static function append_to_content( string $content, array $attachments, string $module = 'admin' ): string {
		if ( empty( $attachments ) ) {
			return $content;
		}

		$lines = array( '' );

		foreach ( $attachments as $attachment ) {
			if ( ! is_array( $attachment ) || ! self::is_internal_link( $attachment ) ) {
				continue;
			}

			$link_line = self::format_internal_link_line( $attachment );

			if ( '' !== $link_line ) {
				if ( 1 === count( $lines ) ) {
					$lines[] = __( 'Attached internal links:', 'agenpress' );
				}

				$lines[] = $link_line;
			}
		}

		$file_started = false;

		foreach ( $attachments as $attachment ) {
			if ( ! is_array( $attachment ) || self::is_internal_link( $attachment ) ) {
				continue;
			}

			$id   = (int) ( $attachment['id'] ?? 0 );
			$url  = esc_url_raw( (string) ( $attachment['url'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $attachment['name'] ?? '' ) );
			$type = sanitize_text_field( (string) ( $attachment['type'] ?? '' ) );

			if ( $id <= 0 && '' === $url ) {
				continue;
			}

			if ( ! $file_started ) {
				$lines[] = __( 'Attached files:', 'agenpress' );
				$file_started = true;
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

		if ( count( $lines ) <= 1 ) {
			return $content;
		}

		if ( '' === trim( $content ) ) {
			return implode( "\n", $lines );
		}

		return rtrim( $content ) . implode( "\n", $lines );
	}

	/**
	 * Whether an attachment payload is an internal site link.
	 *
	 * @param array<string, mixed> $attachment Attachment payload.
	 * @return bool
	 */
	private static function is_internal_link( array $attachment ): bool {
		if ( 'link' === ( $attachment['kind'] ?? '' ) ) {
			return true;
		}

		$type = (string) ( $attachment['type'] ?? '' );

		return str_starts_with( $type, 'link/' ) || ! empty( $attachment['post_id'] ) && ! empty( $attachment['post_type'] );
	}

	/**
	 * Format one internal link for AI context.
	 *
	 * @param array<string, mixed> $attachment Attachment payload.
	 * @return string
	 */
	private static function format_internal_link_line( array $attachment ): string {
		$post_id   = (int) ( $attachment['post_id'] ?? $attachment['id'] ?? 0 );
		$post_type = sanitize_key( (string) ( $attachment['post_type'] ?? 'post' ) );
		$title     = sanitize_text_field( (string) ( $attachment['title'] ?? $attachment['name'] ?? '' ) );
		$url       = esc_url_raw( (string) ( $attachment['url'] ?? '' ) );
		$status    = sanitize_key( (string) ( $attachment['status'] ?? '' ) );
		$excerpt   = sanitize_textarea_field( (string) ( $attachment['excerpt'] ?? '' ) );

		if ( $post_id <= 0 ) {
			return '';
		}

		if ( '' === $url ) {
			$url = (string) get_permalink( $post_id );
		}

		if ( '' === $title ) {
			$title = get_the_title( $post_id ) ?: __( 'Untitled', 'agenpress' );
		}

		$lines   = array();
		$lines[] = sprintf(
			'- [%s](%s) (post_id: %d, post_type: %s%s)',
			$title,
			$url,
			$post_id,
			$post_type,
			'' !== $status ? ', status: ' . $status : ''
		);

		if ( '' !== $excerpt ) {
			$lines[] = '  ' . __( 'Summary:', 'agenpress' ) . ' ' . $excerpt;
		}

		if ( 'product' === $post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );

			if ( $product ) {
				$price = $product->get_price();
				$sku   = $product->get_sku();

				if ( '' !== (string) $price ) {
					$lines[] = '  ' . __( 'Price:', 'agenpress' ) . ' ' . $price;
				}

				if ( '' !== $sku ) {
					$lines[] = '  SKU: ' . $sku;
				}
			}
		}

		$lines[] = '  ' . __( 'Use get_post or get_product with this post_id when the user refers to this attached item.', 'agenpress' );

		return implode( "\n", $lines );
	}

	/**
	 * Format attachments for Elementor editor context.
	 *
	 * @param array<int, mixed> $attachments Attachment payloads.
	 * @return string
	 */
	public static function format_elementor_context_block( array $attachments ): string {
		$block = self::format_context_block( $attachments, 'admin' );

		if ( '' === trim( $block ) ) {
			return '';
		}

		$lines   = array( trim( $block ) );
		$lines[] = __( 'Banner / graphic design with an attached image:', 'agenpress' );
		$lines[] = __( '- Call generate_section_image ONCE with reference_attachment_id and a detailed prompt (layout left/right, exact text inside the image, icons, 3D style, size).', 'agenpress' );
		$lines[] = __( '- Do NOT call add_attached_image_to_page for design requests — that only places the raw file.', 'agenpress' );
		$lines[] = __( '- Do NOT create separate heading or text widgets when the user wants a designed banner — text must be baked into the generated image.', 'agenpress' );
		$lines[] = __( 'Only use add_attached_image_to_page when the user explicitly wants the uploaded file placed as-is without AI design.', 'agenpress' );

		return implode( "\n", $lines );
	}

	/**
	 * Format attachments for system/runtime context blocks.
	 *
	 * @param array<int, mixed> $attachments Attachment payloads.
	 * @param string            $module      Module slug.
	 * @return string
	 */
	public static function format_context_block( array $attachments, string $module = 'admin' ): string {
		if ( empty( $attachments ) ) {
			return '';
		}

		$content = self::append_to_content( '', $attachments, $module );

		if ( 'elementor' === $module ) {
			return self::format_elementor_context_block( $attachments );
		}

		return $content;
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

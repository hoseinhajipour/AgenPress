<?php
/**
 * FAQ JSON-LD schema helpers for post/page content and meta.
 *
 * @package AgenPress
 */

namespace AgenPress\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class FaqSchema
 */
class FaqSchema {

	/**
	 * Post meta key for FAQPage JSON-LD.
	 */
	public const META_KEY = '_agenpress_faq_schema';

	/**
	 * Strip JSON-LD, schema blobs, and metadata leaks from reader-facing content.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	public static function strip_from_content( string $content ): string {
		$content = trim( $content );

		if ( '' === $content ) {
			return '';
		}

		$content = preg_replace( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/is', '', $content );
		$content = preg_replace( '/\{\s*"@context"\s*:\s*"https?:\/\/schema\.org"[^}]*(?:\{[^}]*\}[^}]*)*\}/s', '', $content );
		$content = preg_replace( '/^(?:alt\s*(?:متن|text)?\s*(?:تصویر|image)?|image_prompt)\s*[:：].*$/miu', '', $content );
		$content = preg_replace( '/^(?:هشتگ(?:‌|ی)?های?\s*پیشنهادی|Suggested hashtags)\s*[:：].*$/miu', '', $content );
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );

		return trim( $content );
	}

	/**
	 * Build FAQPage schema from structured FAQ items.
	 *
	 * @param array<int, array<string, string>> $faq_items FAQ items.
	 * @return array<string, mixed>|null
	 */
	public static function build_from_faq_items( array $faq_items ): ?array {
		$entities = array();

		foreach ( $faq_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$question = trim( (string) ( $item['question'] ?? '' ) );
			$answer   = trim( wp_strip_all_tags( (string) ( $item['answer'] ?? '' ) ) );

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $entities ) ) {
			return null;
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	/**
	 * Extract FAQPage schema embedded in post content (script tag or raw JSON).
	 *
	 * @param string $content Post content.
	 * @return array<string, mixed>|null
	 */
	public static function extract_from_content( string $content ): ?array {
		if ( preg_match( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $content, $matches ) ) {
			$schema = self::decode_faq_schema( (string) ( $matches[1] ?? '' ) );

			if ( $schema ) {
				return $schema;
			}
		}

		if ( preg_match( '/\{\s*"@context"\s*:\s*"https?:\/\/schema\.org"[\s\S]*?"@type"\s*:\s*"FAQPage"[\s\S]*?\}/u', $content, $matches ) ) {
			$schema = self::decode_faq_schema( (string) ( $matches[0] ?? '' ) );

			if ( $schema ) {
				return $schema;
			}
		}

		return null;
	}

	/**
	 * Resolve FAQ schema from explicit faq items or embedded content.
	 *
	 * @param string                              $content   Post content.
	 * @param array<int, array<string, string>>|null $faq_items Structured FAQ items.
	 * @return array<string, mixed>|null
	 */
	public static function resolve( string $content, ?array $faq_items = null ): ?array {
		if ( ! empty( $faq_items ) ) {
			$schema = self::build_from_faq_items( $faq_items );

			if ( $schema ) {
				return $schema;
			}
		}

		return self::extract_from_content( $content );
	}

	/**
	 * Save FAQ schema to post meta.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $schema  FAQPage schema.
	 * @return bool
	 */
	public static function save_to_post( int $post_id, array $schema ): bool {
		if ( $post_id <= 0 || empty( $schema['@type'] ) ) {
			return false;
		}

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $schema ) );

		return true;
	}

	/**
	 * Decode and validate FAQPage JSON.
	 *
	 * @param string $json Raw JSON string.
	 * @return array<string, mixed>|null
	 */
	private static function decode_faq_schema( string $json ): ?array {
		$json   = trim( html_entity_decode( $json, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$schema = json_decode( $json, true );

		if ( ! is_array( $schema ) || 'FAQPage' !== ( $schema['@type'] ?? '' ) ) {
			return null;
		}

		return $schema;
	}
}

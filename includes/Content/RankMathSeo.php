<?php
/**
 * Rank Math SEO meta helpers for posts and products.
 *
 * @package AgenPress
 */

namespace AgenPress\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Class RankMathSeo
 */
class RankMathSeo {

	/**
	 * Whether Rank Math SEO is available.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath\Helper' );
	}

	/**
	 * Read Rank Math fields for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{focus_keyword: string, seo_title: string, seo_description: string}
	 */
	public static function get_for_post( int $post_id ): array {
		if ( $post_id <= 0 || ! self::is_active() ) {
			return array(
				'focus_keyword'   => '',
				'seo_title'       => '',
				'seo_description' => '',
			);
		}

		return array(
			'focus_keyword'   => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
			'seo_title'       => (string) get_post_meta( $post_id, 'rank_math_title', true ),
			'seo_description' => (string) get_post_meta( $post_id, 'rank_math_description', true ),
		);
	}

	/**
	 * Save Rank Math SEO fields for a post or product.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $seo     SEO payload (focus_keyword, seo_title, seo_description).
	 * @return bool Whether any meta was saved.
	 */
	public static function save_for_post( int $post_id, array $seo ): bool {
		if ( $post_id <= 0 || ! self::is_active() ) {
			return false;
		}

		$map = array(
			'focus_keyword'   => 'rank_math_focus_keyword',
			'seo_title'       => 'rank_math_title',
			'seo_description' => 'rank_math_description',
		);

		$saved = false;

		foreach ( $map as $key => $meta_key ) {
			if ( ! array_key_exists( $key, $seo ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $seo[ $key ] );

			if ( 'seo_description' === $key ) {
				$value = sanitize_textarea_field( (string) $seo[ $key ] );
			}

			if ( '' === $value ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
			$saved = true;
		}

		return $saved;
	}
}

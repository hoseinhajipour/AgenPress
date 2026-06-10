<?php
/**
 * Supported AI response languages (ISO 639-1).
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class LanguageRegistry
 */
class LanguageRegistry {

	/**
	 * Get all supported languages for settings UI.
	 *
	 * @return array<int, array{id: string, label: string}>
	 */
	public static function catalog(): array {
		$languages = self::definitions();

		$items = array();
		foreach ( $languages as $code => $label ) {
			$items[] = array(
				'id'    => $code,
				'label' => $label,
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $items;
	}

	/**
	 * Check whether a language code is supported.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function is_valid( string $code ): bool {
		return isset( self::definitions()[ $code ] );
	}

	/**
	 * Get display label for a language code.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function get_label( string $code ): string {
		return self::definitions()[ $code ] ?? 'English';
	}

	/**
	 * Resolve default language from a WordPress locale.
	 *
	 * @param string $locale WordPress locale (e.g. fa_IR).
	 * @return string
	 */
	public static function default_from_locale( string $locale ): string {
		$locale = strtolower( str_replace( '_', '-', $locale ) );
		$parts  = explode( '-', $locale );
		$code   = $parts[0] ?? 'en';

		if ( self::is_valid( $code ) ) {
			return $code;
		}

		$aliases = array(
			'nb' => 'no',
			'nn' => 'no',
			'zh' => 'zh',
			'pt' => 'pt',
		);

		if ( isset( $aliases[ $code ] ) && self::is_valid( $aliases[ $code ] ) ) {
			return $aliases[ $code ];
		}

		return 'en';
	}

	/**
	 * Build system-prompt instruction for the configured AI language.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function get_ai_instruction( string $code ): string {
		$label = self::get_label( $code );

		return sprintf(
			'Base language: %1$s (%2$s). Always respond in %1$s. Write all user-facing content, explanations, and generated text in %1$s unless the user explicitly requests another language.',
			$label,
			$code
		);
	}

	/**
	 * ISO 639-1 language definitions.
	 *
	 * @return array<string, string>
	 */
	private static function definitions(): array {
		return array(
			'af' => 'Afrikaans',
			'sq' => 'Shqip',
			'am' => 'አማርኛ',
			'ar' => 'العربية',
			'hy' => 'Հայերեն',
			'az' => 'Azərbaycan',
			'eu' => 'Euskara',
			'be' => 'Беларуская',
			'bn' => 'বাংলা',
			'bs' => 'Bosanski',
			'bg' => 'Български',
			'my' => 'မြန်မာ',
			'ca' => 'Català',
			'ceb' => 'Cebuano',
			'zh' => '中文',
			'co' => 'Corsu',
			'hr' => 'Hrvatski',
			'cs' => 'Čeština',
			'da' => 'Dansk',
			'nl' => 'Nederlands',
			'en' => 'English',
			'eo' => 'Esperanto',
			'et' => 'Eesti',
			'fi' => 'Suomi',
			'fr' => 'Français',
			'fy' => 'Frysk',
			'gl' => 'Galego',
			'ka' => 'ქართული',
			'de' => 'Deutsch',
			'el' => 'Ελληνικά',
			'gu' => 'ગુજરાતી',
			'ht' => 'Kreyòl ayisyen',
			'ha' => 'Hausa',
			'haw' => 'ʻŌlelo Hawaiʻi',
			'he' => 'עברית',
			'hi' => 'हिन्दी',
			'hmn' => 'Hmong',
			'hu' => 'Magyar',
			'is' => 'Íslenska',
			'ig' => 'Igbo',
			'id' => 'Bahasa Indonesia',
			'ga' => 'Gaeilge',
			'it' => 'Italiano',
			'ja' => '日本語',
			'jv' => 'Basa Jawa',
			'kn' => 'ಕನ್ನಡ',
			'kk' => 'Қазақ',
			'km' => 'ខ្មែរ',
			'rw' => 'Kinyarwanda',
			'ko' => '한국어',
			'ku' => 'Kurdî',
			'ky' => 'Кыргызча',
			'lo' => 'ລາວ',
			'la' => 'Latina',
			'lv' => 'Latviešu',
			'lt' => 'Lietuvių',
			'lb' => 'Lëtzebuergesch',
			'mk' => 'Македонски',
			'mg' => 'Malagasy',
			'ms' => 'Bahasa Melayu',
			'ml' => 'മലയാളം',
			'mt' => 'Malti',
			'mi' => 'Te Reo Māori',
			'mr' => 'मराठी',
			'mn' => 'Монгол',
			'ne' => 'नेपाली',
			'no' => 'Norsk',
			'ny' => 'Chichewa',
			'or' => 'ଓଡ଼ିଆ',
			'ps' => 'پښتو',
			'fa' => 'فارسی',
			'pl' => 'Polski',
			'pt' => 'Português',
			'pa' => 'ਪੰਜਾਬੀ',
			'ro' => 'Română',
			'ru' => 'Русский',
			'sm' => 'Gagana Samoa',
			'gd' => 'Gàidhlig',
			'sr' => 'Српски',
			'st' => 'Sesotho',
			'sn' => 'ChiShona',
			'sd' => 'سنڌي',
			'si' => 'සිංහල',
			'sk' => 'Slovenčina',
			'sl' => 'Slovenščina',
			'so' => 'Soomaali',
			'es' => 'Español',
			'su' => 'Basa Sunda',
			'sw' => 'Kiswahili',
			'sv' => 'Svenska',
			'tl' => 'Filipino',
			'tg' => 'Тоҷикӣ',
			'ta' => 'தமிழ்',
			'tt' => 'Татар',
			'te' => 'తెలుగు',
			'th' => 'ไทย',
			'tr' => 'Türkçe',
			'tk' => 'Türkmen',
			'uk' => 'Українська',
			'ur' => 'اردو',
			'ug' => 'ئۇيغۇرچە',
			'uz' => 'Oʻzbek',
			'vi' => 'Tiếng Việt',
			'cy' => 'Cymraeg',
			'xh' => 'isiXhosa',
			'yi' => 'ייִדיש',
			'yo' => 'Yorùbá',
			'zu' => 'isiZulu',
		);
	}
}

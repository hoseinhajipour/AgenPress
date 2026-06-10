<?php
/**
 * AgenPress i18n utilities: extract POT, compile MO, generate JS JSON.
 *
 * Usage:
 *   php scripts/i18n.php pot
 *   php scripts/i18n.php mo [locale]
 *   php scripts/i18n.php json [locale]
 *   php scripts/i18n.php all [locale]
 *
 * @package AgenPress
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

$root = dirname( __DIR__ );

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$command = $argv[1] ?? 'all';
$locale  = $argv[2] ?? 'fa_IR';
$domain  = 'agenpress';

$valid_commands = array( 'pot', 'po', 'mo', 'json', 'all' );

if ( ! in_array( $command, $valid_commands, true ) ) {
	fwrite( STDERR, "Unknown command: {$command}\n" );
	exit( 1 );
}

$scan_dirs = array(
	$root . '/includes',
	$root . '/src',
	$root . '/agenpress.php',
);

$js_bundles = array(
	'assets/js/admin.js',
	'assets/js/elementor-editor.js',
	'assets/js/frontend-chat.js',
	'assets/js/post-editor.js',
);

$languages_dir = $root . '/languages';
$pot_file      = $languages_dir . '/' . $domain . '.pot';
$po_file       = $languages_dir . '/' . $domain . '-' . $locale . '.po';

if ( ! is_dir( $languages_dir ) ) {
	mkdir( $languages_dir, 0755, true );
}

/**
 * @param array<int, string> $dirs
 * @return array<int, string>
 */
function agenpress_i18n_collect_files( array $dirs ): array {
	$files = array();

	foreach ( $dirs as $path ) {
		if ( is_file( $path ) ) {
			$files[] = $path;
			continue;
		}

		if ( ! is_dir( $path ) ) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			/** @var SplFileInfo $file */
			$pathname = $file->getPathname();

			if ( ! preg_match( '/\.(php|jsx?)$/i', $pathname ) ) {
				continue;
			}

			if ( str_contains( $pathname, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}

			$files[] = $pathname;
		}
	}

	sort( $files );

	return $files;
}

/**
 * @return array<string, array{msgid: string, references: array<int, string>}>
 */
function agenpress_i18n_extract_entries( array $files, string $domain ): array {
	$patterns = array(
		'/(?:__|_e|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\(\s*([\'"])(?:\\\\.|(?!\1).)*\1\s*,\s*[\'"]' . preg_quote( $domain, '/' ) . '[\'"]\s*(?:,|\))/s',
		'/_n\(\s*([\'"])(?:\\\\.|(?!\1).)*\1\s*,\s*([\'"])(?:\\\\.|(?!\2).)*\2\s*,/s',
		'/_x\(\s*([\'"])(?:\\\\.|(?!\1).)*\1\s*,\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"]' . preg_quote( $domain, '/' ) . '[\'"]\s*(?:,|\))/s',
	);

	$entries = array();

	foreach ( $files as $file ) {
		$contents = file_get_contents( $file );

		if ( false === $contents ) {
			continue;
		}

		$relative = agenpress_i18n_relative_path( $file );

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match_all( $pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			foreach ( $matches[0] as $index => $match ) {
				$snippet = $match[0];
				$msgid   = agenpress_i18n_parse_first_string( $snippet );

				if ( '' === $msgid ) {
					continue;
				}

				$key = $msgid;

				if ( ! isset( $entries[ $key ] ) ) {
					$entries[ $key ] = array(
						'msgid'      => $msgid,
						'references' => array(),
					);
				}

				$line = substr_count( substr( $contents, 0, $match[1] ), "\n" ) + 1;
				$ref  = $relative . ':' . $line;

				if ( ! in_array( $ref, $entries[ $key ]['references'], true ) ) {
					$entries[ $key ]['references'][] = $ref;
				}
			}
		}
	}

	ksort( $entries, SORT_STRING );

	return $entries;
}

function agenpress_i18n_relative_path( string $file ): string {
	global $root;

	$file = str_replace( '\\', '/', $file );
	$root = str_replace( '\\', '/', $root );

	return ltrim( str_replace( $root . '/', '', $file ), '/' );
}

function agenpress_i18n_parse_first_string( string $snippet ): string {
	if ( ! preg_match( '/([\'"])(?:\\\\.|(?!\1).)*\1/s', $snippet, $match ) ) {
		return '';
	}

	return agenpress_i18n_unquote( $match[0] );
}

function agenpress_i18n_unquote( string $quoted ): string {
	$quote = $quoted[0];
	$body  = substr( $quoted, 1, -1 );

	if ( "'" === $quote ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
	}

	return stripcslashes( $body );
}

/**
 * @param array<string, array{msgid: string, references: array<int, string>}> $entries
 * @param array<string, string>                                               $translations
 */
function agenpress_i18n_write_po( array $entries, string $po_file, string $domain, string $locale, array $translations ): void {
	$language = str_replace( '_', '-', $locale );
	$lines    = array();
	$lines[]  = 'msgid ""';
	$lines[]  = 'msgstr ""';
	$lines[]  = '"Project-Id-Version: AgenPress\\n"';
	$lines[]  = '"Report-Msgid-Bugs-To: https://github.com/agenpress/agenpress\\n"';
	$lines[]  = '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:iO' ) . '\\n"';
	$lines[]  = '"PO-Revision-Date: ' . gmdate( 'Y-m-d H:iO' ) . '\\n"';
	$lines[]  = '"Last-Translator: AgenPress <support@agenpress.io>\\n"';
	$lines[]  = '"Language-Team: Persian\\n"';
	$lines[]  = '"Language: ' . $language . '\\n"';
	$lines[]  = '"MIME-Version: 1.0\\n"';
	$lines[]  = '"Content-Type: text/plain; charset=UTF-8\\n"';
	$lines[]  = '"Content-Transfer-Encoding: 8bit\\n"';
	$lines[]  = '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"';
	$lines[]  = '"X-Domain: ' . $domain . '\\n"';
	$lines[]  = '';

	foreach ( $entries as $entry ) {
		foreach ( $entry['references'] as $reference ) {
			$lines[] = '#: ' . $reference;
		}

		$msgid  = $entry['msgid'];
		$msgstr = $translations[ $msgid ] ?? $msgid;

		$lines[] = 'msgid ' . agenpress_i18n_po_quote( $msgid );
		$lines[] = 'msgstr ' . agenpress_i18n_po_quote( $msgstr );
		$lines[] = '';
	}

	file_put_contents( $po_file, implode( "\n", $lines ) . "\n" );
}

/**
 * @return array<string, string>
 */
function agenpress_i18n_load_translations( string $locale ): array {
	$file = dirname( __DIR__ ) . '/languages/translations/' . $locale . '.php';

	if ( ! file_exists( $file ) ) {
		return array();
	}

	$translations = require $file;

	return is_array( $translations ) ? $translations : array();
}

/**
 * @param array<string, array{msgid: string, references: array<int, string>}> $entries
 */
function agenpress_i18n_write_pot( array $entries, string $pot_file, string $domain ): void {
	$lines   = array();
	$lines[] = 'msgid ""';
	$lines[] = 'msgstr ""';
	$lines[] = '"Project-Id-Version: AgenPress\\n"';
	$lines[] = '"Report-Msgid-Bugs-To: https://github.com/agenpress/agenpress\\n"';
	$lines[] = '"POT-Creation-Date: ' . gmdate( 'Y-m-d H:iO' ) . '\\n"';
	$lines[] = '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"';
	$lines[] = '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"';
	$lines[] = '"Language-Team: LANGUAGE <LL@li.org>\\n"';
	$lines[] = '"MIME-Version: 1.0\\n"';
	$lines[] = '"Content-Type: text/plain; charset=UTF-8\\n"';
	$lines[] = '"Content-Transfer-Encoding: 8bit\\n"';
	$lines[] = '"X-Domain: ' . $domain . '\\n"';
	$lines[] = '';

	foreach ( $entries as $entry ) {
		foreach ( $entry['references'] as $reference ) {
			$lines[] = '#: ' . $reference;
		}

		$lines[] = 'msgid ' . agenpress_i18n_po_quote( $entry['msgid'] );
		$lines[] = 'msgstr ""';
		$lines[] = '';
	}

	file_put_contents( $pot_file, implode( "\n", $lines ) . "\n" );
}

function agenpress_i18n_po_quote( string $string ): string {
	$string = str_replace( array( '\\', "\n", "\r", "\t", '"' ), array( '\\\\', '\\n', '', '\\t', '\\"' ), $string );

	return '"' . $string . '"';
}

/**
 * @return array<string, string>
 */
function agenpress_i18n_parse_po( string $po_file ): array {
	if ( ! file_exists( $po_file ) ) {
		return array();
	}

	$contents = file_get_contents( $po_file );

	if ( false === $contents ) {
		return array();
	}

	$translations = array();
	$blocks       = preg_split( '/\n\s*\n/', trim( $contents ) ) ?: array();

	foreach ( $blocks as $block ) {
		if ( ! preg_match( '/msgid\s+((?:"(?:\\\\.|[^"\\\\])*"(?:\s*)?)+)\s*\nmsgstr\s+((?:"(?:\\\\.|[^"\\\\])*"(?:\s*)?)+)/s', $block, $match ) ) {
			continue;
		}

		$msgid  = agenpress_i18n_read_po_string( $match[1] );
		$msgstr = agenpress_i18n_read_po_string( $match[2] );

		if ( '' === $msgid || '' === $msgstr ) {
			continue;
		}

		$translations[ $msgid ] = $msgstr;
	}

	return $translations;
}

/**
 * @param array<string, array{msgid: string, references: array<int, string>}> $entries
 * @return array<string, string>
 */
function agenpress_i18n_build_translations( array $entries, string $locale ): array {
	$map          = agenpress_i18n_load_translations( $locale );
	$translations = array();

	foreach ( $entries as $entry ) {
		$msgid = $entry['msgid'];
		if ( '' === $msgid ) {
			continue;
		}

		$translations[ $msgid ] = $map[ $msgid ] ?? $msgid;
	}

	return $translations;
}

function agenpress_i18n_read_po_string( string $raw ): string {
	$raw   = trim( $raw );
	$parts = preg_split( '/\n(?=")/', $raw ) ?: array();
	$value = '';

	foreach ( $parts as $part ) {
		$part = trim( $part );

		if ( preg_match( '/^"(.*)"$/s', $part, $match ) ) {
			$value .= stripcslashes( $match[1] );
		}
	}

	return $value;
}

/**
 * @param array<string, string> $translations
 */
function agenpress_i18n_write_mo( array $translations, string $mo_file ): void {
	ksort( $translations, SORT_STRING );

	$ids        = '';
	$strings    = '';
	$id_offsets = array();
	$str_offsets = array();

	foreach ( array_keys( $translations ) as $id ) {
		$id_offsets[] = array( strlen( $id ), strlen( $ids ) );
		$ids         .= $id . "\0";
	}

	foreach ( array_values( $translations ) as $translation ) {
		$str_offsets[] = array( strlen( $translation ), strlen( $strings ) );
		$strings      .= $translation . "\0";
	}

	$count       = count( $translations );
	$header_size = 28;
	$o1          = $header_size;
	$o2          = $o1 + ( 8 * $count );
	$o_strings   = $o2 + ( 8 * $count );
	$o_ids       = $o_strings;
	$o_strs      = $o_ids + strlen( $ids );

	$mo = pack( 'V7', 0x950412de, 0, $count, $o1, $o2, 0, $o_strings );

	for ( $i = 0; $i < $count; $i++ ) {
		$mo .= pack( 'V2', $id_offsets[ $i ][0], $o_ids + $id_offsets[ $i ][1] );
	}

	for ( $i = 0; $i < $count; $i++ ) {
		$mo .= pack( 'V2', $str_offsets[ $i ][0], $o_strs + $str_offsets[ $i ][1] );
	}

	file_put_contents( $mo_file, $mo . $ids . $strings );
}

/**
 * @param array<string, string> $translations
 * @param array<int, string>    $js_bundles
 */
function agenpress_i18n_write_json( array $translations, array $js_bundles, string $languages_dir, string $domain, string $locale ): void {
	global $root;

	foreach ( $js_bundles as $bundle ) {
		$bundle_path = $root . '/' . $bundle;

		if ( ! file_exists( $bundle_path ) ) {
			continue;
		}

		$hash     = md5( $bundle );
		$json_file = $languages_dir . '/' . $domain . '-' . $locale . '-' . $hash . '.json';
		$locale_data = array(
			'' => array(
				'domain'       => 'messages',
				'lang'         => str_replace( '_', '-', $locale ),
				'plural-forms' => 'nplurals=2; plural=(n != 1);',
			),
		);

		foreach ( $translations as $msgid => $msgstr ) {
			$locale_data[ $msgid ] = array( $msgstr );
		}

		$payload = array(
			'domain'      => 'messages',
			'locale_data' => array(
				'messages' => $locale_data,
			),
		);

		file_put_contents( $json_file, wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data
	 * @return string
	 */
	function wp_json_encode( $data, int $flags = 0 ): string {
		return json_encode( $data, $flags ) ?: '{}';
	}
}

$files   = agenpress_i18n_collect_files( $scan_dirs );
$entries = agenpress_i18n_extract_entries( $files, $domain );

if ( in_array( $command, array( 'pot', 'all' ), true ) ) {
	agenpress_i18n_write_pot( $entries, $pot_file, $domain );
	echo "Wrote {$pot_file}\n";
}

if ( in_array( $command, array( 'po', 'all' ), true ) ) {
	$translations = agenpress_i18n_load_translations( $locale );
	agenpress_i18n_write_po( $entries, $po_file, $domain, $locale, $translations );
	echo "Wrote {$po_file}\n";
}

if ( in_array( $command, array( 'mo', 'json', 'all' ), true ) ) {
	$translations = agenpress_i18n_parse_po( $po_file );

	if ( empty( $translations ) ) {
		$translations = agenpress_i18n_build_translations( $entries, $locale );
	}

	if ( empty( $translations ) ) {
		fwrite( STDERR, "No translations found for {$locale}\n" );
		exit( 1 );
	}

	if ( in_array( $command, array( 'mo', 'all' ), true ) ) {
		$mo_file = $languages_dir . '/' . $domain . '-' . $locale . '.mo';
		agenpress_i18n_write_mo( $translations, $mo_file );
		echo "Wrote {$mo_file}\n";
	}

	if ( in_array( $command, array( 'json', 'all' ), true ) ) {
		agenpress_i18n_write_json( $translations, $js_bundles, $languages_dir, $domain, $locale );
		echo "Wrote JS JSON files for {$locale}\n";
	}
}

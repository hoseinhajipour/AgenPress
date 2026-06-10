<?php
/**
 * Phase 1 smoke test — run with: php tests/smoke-test.php
 *
 * @package AgenPress
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'AGENPRESS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'AGENPRESS_VERSION', '0.1.0' );
define( 'AUTH_KEY', 'test-auth-key-for-smoke-test' );

// Minimal WordPress stubs for offline testing.
require_once __DIR__ . '/wp-stubs.php';

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'AgenPress\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$file = AGENPRESS_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

use AgenPress\Agents\TaskState;
use AgenPress\Core\Settings;
use AgenPress\Memory\MemoryStore;
use AgenPress\Agents\ToolRegistry;
use AgenPress\Modules\Admin\Tools\GetSiteInfoTool;

$passed = 0;
$failed = 0;

function assert_true( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  PASS: {$message}\n";
	} else {
		++$failed;
		echo "  FAIL: {$message}\n";
	}
}

echo "AgenPress Phase 1 Smoke Test\n";
echo str_repeat( '-', 40 ) . "\n";

// Task state transitions.
assert_true( TaskState::can_transition( TaskState::PENDING, TaskState::RUNNING ), 'pending -> running' );
assert_true( ! TaskState::can_transition( TaskState::COMPLETED, TaskState::RUNNING ), 'completed -> running blocked' );

// Memory categories.
assert_true( in_array( 'brand', MemoryStore::CATEGORIES, true ), 'memory has brand category' );

// Settings encryption roundtrip.
$settings = new Settings();
$settings->update( array( 'openai_api_key' => 'sk-test-key-12345' ) );
$all = $settings->get_all();
assert_true( $all['has_openai_key'], 'API key stored' );
assert_true( str_contains( $all['openai_api_key'], '****' ), 'API key masked in output' );
assert_true( 'sk-test-key-12345' === $settings->get_api_key( 'openai' ), 'API key decrypts correctly' );

// Tool registry.
$registry = new ToolRegistry();
$registry->register( new GetSiteInfoTool() );
$schemas = $registry->get_schemas();
assert_true( count( $schemas ) === 1, 'tool schema registered' );
$result = $registry->execute( 'get_site_info', array(), 1 );
assert_true( $result['success'], 'get_site_info tool executes' );

echo str_repeat( '-', 40 ) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";

exit( $failed > 0 ? 1 : 0 );

<?php
/**
 * AI toolbar buttons for the WordPress classic editor (TinyMCE).
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin;

use AgenPress\AI\ImageSizeRegistry;
use AgenPress\Core\Settings;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ClassicEditorToolbar
 */
class ClassicEditorToolbar {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mce_buttons', array( $this, 'register_buttons' ), 20 );
		add_filter( 'mce_external_plugins', array( $this, 'register_plugins' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_mount' ) );
	}

	/**
	 * Whether the current screen supports the classic editor toolbar.
	 *
	 * @return bool
	 */
	private function is_supported_screen(): bool {
		if ( ! current_user_can( Capabilities::USE_ADMIN_AI ) || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		if ( ! user_can_richedit() ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'post' !== $screen->base ) {
			return false;
		}

		$post_type = $screen->post_type ?? 'post';

		return post_type_supports( $post_type, 'editor' );
	}

	/**
	 * Get the current post ID on edit screens.
	 *
	 * @return int
	 */
	private function get_post_id(): int {
		if ( isset( $_GET['post'] ) ) {
			return (int) $_GET['post'];
		}

		return 0;
	}

	/**
	 * Add toolbar buttons.
	 *
	 * @param array<int, string> $buttons TinyMCE buttons.
	 * @return array<int, string>
	 */
	public function register_buttons( array $buttons ): array {
		if ( ! $this->is_supported_screen() ) {
			return $buttons;
		}

		$buttons[] = 'agenpress_generate_text';
		$buttons[] = 'agenpress_generate_image';

		return $buttons;
	}

	/**
	 * Register TinyMCE external plugin.
	 *
	 * @param array<string, string> $plugins Plugins.
	 * @return array<string, string>
	 */
	public function register_plugins( array $plugins ): array {
		if ( ! $this->is_supported_screen() ) {
			return $plugins;
		}

		$plugins['agenpress_classic_editor'] = AGENPRESS_PLUGIN_URL . 'assets/js/classic-editor-tinymce.js';

		return $plugins;
	}

	/**
	 * Enqueue modal scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! $this->is_supported_screen() ) {
			return;
		}

		$asset_file = AGENPRESS_PLUGIN_DIR . 'assets/js/post-editor.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => AGENPRESS_VERSION,
		);

		$tinymce_asset_file = AGENPRESS_PLUGIN_DIR . 'assets/js/classic-editor-tinymce.asset.php';
		$tinymce_asset      = file_exists( $tinymce_asset_file ) ? require $tinymce_asset_file : array(
			'dependencies' => array(),
			'version'      => AGENPRESS_VERSION,
		);

		wp_enqueue_style(
			'agenpress-post-editor',
			AGENPRESS_PLUGIN_URL . 'assets/js/post-editor.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_enqueue_script(
			'agenpress-post-editor',
			AGENPRESS_PLUGIN_URL . 'assets/js/post-editor.js',
			array_merge(
				$asset['dependencies'],
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-hooks', 'wp-data' )
			),
			$asset['version'],
			true
		);

		wp_enqueue_script(
			'agenpress-classic-editor-tinymce',
			AGENPRESS_PLUGIN_URL . 'assets/js/classic-editor-tinymce.js',
			$tinymce_asset['dependencies'],
			$tinymce_asset['version'],
			true
		);

		/** @var Settings $settings */
		$settings   = function_exists( 'agenpress' ) ? agenpress()->container()->get( 'settings' ) : new Settings();
		$image_data = ImageSizeRegistry::editor_localize_data( $settings->get_default_image_aspect() );

		wp_localize_script(
			'agenpress-post-editor',
			'agenpressPostEditorData',
			array_merge(
				array(
					'apiUrl' => rest_url( 'agenpress/v1' ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
					'postId' => $this->get_post_id(),
					'labels' => array(
						'generateText'  => __( 'Generate Text', 'agenpress' ),
						'generateImage' => __( 'Generate Image', 'agenpress' ),
					),
				),
				$image_data
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'agenpress-post-editor', 'agenpress', AGENPRESS_PLUGIN_DIR . 'languages' );
		}
	}

	/**
	 * Render React mount point for classic editor modals.
	 *
	 * @return void
	 */
	public function render_mount(): void {
		if ( ! $this->is_supported_screen() ) {
			return;
		}

		echo '<div id="agenpress-classic-editor-root"></div>';
	}
}

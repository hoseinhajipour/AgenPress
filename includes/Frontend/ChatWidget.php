<?php
/**
 * Storefront sales chat widget.
 *
 * @package AgenPress
 */

namespace AgenPress\Frontend;

use AgenPress\Core\Settings;
use AgenPress\Modules\ModuleManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatWidget
 */
class ChatWidget {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings       Settings.
	 * @param ModuleManager $module_manager Module manager.
	 */
	public function __construct( Settings $settings, ModuleManager $module_manager ) {
		$this->settings       = $settings;
		$this->module_manager = $module_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'agenpress_chat', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'wp_footer', array( $this, 'maybe_render_floating' ) );
	}

	/**
	 * Shortcode output.
	 *
	 * @return string
	 */
	public function render_shortcode(): string {
		if ( ! $this->settings->is_sales_chat_enabled() ) {
			return '';
		}

		$this->enqueue_assets();

		return '<div id="agenpress-chat-widget" class="agenpress-chat-inline"></div>';
	}

	/**
	 * Enqueue assets when shortcode is present or floating widget enabled.
	 *
	 * @return void
	 */
	public function maybe_enqueue(): void {
		global $post;

		if ( ! $this->settings->is_sales_chat_enabled() ) {
			return;
		}

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'agenpress_chat' ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Render floating widget in footer.
	 *
	 * @return void
	 */
	public function maybe_render_floating(): void {
		if ( ! $this->settings->is_sales_chat_enabled() ) {
			return;
		}

		global $post;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'agenpress_chat' ) ) {
			return;
		}

		$this->enqueue_assets();
		echo '<div id="agenpress-chat-widget" class="agenpress-chat-floating"></div>';
	}

	/**
	 * Enqueue frontend chat assets.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		static $enqueued = false;

		if ( $enqueued ) {
			return;
		}

		$enqueued   = true;
		$asset_file = AGENPRESS_PLUGIN_DIR . 'assets/js/frontend-chat.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => AGENPRESS_VERSION,
		);

		wp_enqueue_style(
			'agenpress-frontend-chat',
			AGENPRESS_PLUGIN_URL . 'assets/js/frontend-chat.css',
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'agenpress-frontend-chat',
			AGENPRESS_PLUGIN_URL . 'assets/js/frontend-chat.js',
			array_merge( $asset['dependencies'], array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ) ),
			$asset['version'],
			true
		);

		$sales_module = $this->module_manager->get( 'sales' );
		$config       = $this->settings->get_sales_chat_public_config();

		wp_localize_script(
			'agenpress-frontend-chat',
			'agenpressChatData',
			array(
				'apiUrl'      => rest_url( 'agenpress/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn'  => is_user_logged_in(),
				'canUpload'   => is_user_logged_in() && current_user_can( 'upload_files' ),
				'config'      => $config,
				'suggestions' => $sales_module ? $sales_module->get_suggestions() : array(),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'agenpress-frontend-chat', 'agenpress', AGENPRESS_PLUGIN_DIR . 'languages' );
		}
	}
}

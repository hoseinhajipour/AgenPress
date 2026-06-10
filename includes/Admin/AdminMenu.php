<?php
/**
 * WordPress admin menu and asset enqueuing.
 *
 * @package AgenPress
 */

namespace AgenPress\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminMenu
 */
class AdminMenu {

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'AgenPress', 'agenpress' ),
			__( 'AgenPress', 'agenpress' ),
			'agenpress_use_admin_ai',
			'agenpress',
			array( $this, 'render_app' ),
			'dashicons-superhero-alt',
			30
		);
	}

	/**
	 * Render the React app mount point.
	 *
	 * @return void
	 */
	public function render_app(): void {
		echo '<div id="agenpress-root" class="wrap"></div>';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_agenpress' !== $hook ) {
			return;
		}

		$asset_file = AGENPRESS_PLUGIN_DIR . 'assets/js/admin.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => AGENPRESS_VERSION,
		);

		wp_enqueue_style(
			'agenpress-admin',
			AGENPRESS_PLUGIN_URL . 'assets/js/admin.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_enqueue_script(
			'agenpress-admin',
			AGENPRESS_PLUGIN_URL . 'assets/js/admin.js',
			array_merge( $asset['dependencies'], array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ) ),
			$asset['version'],
			true
		);

		$modules = array(
			array(
				'id'          => 'admin',
				'name'        => __( 'Admin AI', 'agenpress' ),
				'suggestions' => array(),
			),
		);

		if ( function_exists( 'agenpress' ) ) {
			/** @var \AgenPress\Modules\ModuleManager $manager */
			$manager = agenpress()->container()->get( 'module_manager' );
			$api_modules = $manager->list_for_admin_api();

			if ( ! empty( $api_modules ) ) {
				$modules = $api_modules;
			}
		}

		wp_localize_script(
			'agenpress-admin',
			'agenpressData',
			array(
				'apiUrl'        => rest_url( 'agenpress/v1' ),
				'adminUrl'      => admin_url(),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'userId'        => get_current_user_id(),
				'userName'      => wp_get_current_user()->display_name,
				'siteName'      => get_bloginfo( 'name' ),
				'version'       => AGENPRESS_VERSION,
				'licenseTier'   => function_exists( 'agenpress' ) ? agenpress()->container()->get( 'settings' )->get_license_tier() : 'basic',
				'modules'       => $modules,
				'woocommerce'   => class_exists( 'WooCommerce' ),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'agenpress-admin', 'agenpress', AGENPRESS_PLUGIN_DIR . 'languages' );
		}
	}
}

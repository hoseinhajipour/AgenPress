<?php
/**
 * Elementor editor panel integration.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Elementor;

use AgenPress\Modules\ModuleManager;
use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class ElementorEditor
 */
class ElementorEditor {

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $module_manager;

	/**
	 * Constructor.
	 *
	 * @param ModuleManager $module_manager Module manager.
	 */
	public function __construct( ModuleManager $module_manager ) {
		$this->module_manager = $module_manager;
	}

	/**
	 * Enqueue editor scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! current_user_can( Capabilities::USE_ELEMENTOR_AI ) ) {
			return;
		}

		$asset_file = AGENPRESS_PLUGIN_DIR . 'assets/js/elementor-editor.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => AGENPRESS_VERSION,
		);

		wp_enqueue_style(
			'agenpress-elementor-editor',
			AGENPRESS_PLUGIN_URL . 'assets/js/elementor-editor.css',
			array(),
			$asset['version']
		);

		wp_enqueue_script(
			'agenpress-elementor-editor',
			AGENPRESS_PLUGIN_URL . 'assets/js/elementor-editor.js',
			array_merge(
				$asset['dependencies'],
				array( 'jquery', 'elementor-editor', 'wp-element', 'wp-api-fetch', 'wp-i18n' )
			),
			$asset['version'],
			true
		);

		$module = $this->module_manager->get( 'elementor' );

		wp_localize_script(
			'agenpress-elementor-editor',
			'agenpressElementorData',
			array(
				'apiUrl'      => rest_url( 'agenpress/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'userName'    => wp_get_current_user()->display_name,
				'suggestions' => $module ? $module->get_suggestions() : array(),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'agenpress-elementor-editor', 'agenpress', AGENPRESS_PLUGIN_DIR . 'languages' );
		}
	}

	/**
	 * Render panel mount point in editor footer.
	 *
	 * @return void
	 */
	public function render_panel_mount(): void {
		if ( ! current_user_can( Capabilities::USE_ELEMENTOR_AI ) ) {
			return;
		}

		echo '<div id="agenpress-elementor-root"></div>';
	}
}

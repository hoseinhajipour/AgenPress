<?php
/**
 * Featured image AI generation on post edit screens.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin;

use AgenPress\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class PostFeaturedImage
 */
class PostFeaturedImage {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'add_classic_editor_mount' ), 10, 3 );
	}

	/**
	 * Whether the current screen supports featured images.
	 *
	 * @return bool
	 */
	private function is_supported_screen(): bool {
		if ( ! current_user_can( Capabilities::USE_ADMIN_AI ) || ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		if ( ! in_array( $screen->base, array( 'post' ), true ) ) {
			return false;
		}

		$post_type = $screen->post_type ?? 'post';

		return post_type_supports( $post_type, 'thumbnail' );
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
	 * Enqueue assets for classic post editor.
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

		$this->enqueue_scripts();
	}

	/**
	 * Enqueue assets for block editor.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets(): void {
		if ( ! $this->is_supported_screen() ) {
			return;
		}

		$this->enqueue_scripts();
	}

	/**
	 * Enqueue shared post editor scripts.
	 *
	 * @return void
	 */
	private function enqueue_scripts(): void {
		$asset_file = AGENPRESS_PLUGIN_DIR . 'assets/js/post-editor.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
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

		wp_localize_script(
			'agenpress-post-editor',
			'agenpressPostEditorData',
			array(
				'apiUrl' => rest_url( 'agenpress/v1' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'postId' => $this->get_post_id(),
				'sizes'  => array(
					array(
						'value' => '1024x1024',
						'label' => __( 'Square (1024×1024)', 'agenpress' ),
					),
					array(
						'value' => '1792x1024',
						'label' => __( 'Landscape (1792×1024)', 'agenpress' ),
					),
					array(
						'value' => '1024x1792',
						'label' => __( 'Portrait (1024×1792)', 'agenpress' ),
					),
				),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'agenpress-post-editor', 'agenpress', AGENPRESS_PLUGIN_DIR . 'languages' );
		}
	}

	/**
	 * Add mount point for classic editor featured image metabox.
	 *
	 * @param string   $content      Existing HTML.
	 * @param int      $post_id      Post ID.
	 * @param int|null $thumbnail_id Thumbnail ID.
	 * @return string
	 */
	public function add_classic_editor_mount( string $content, int $post_id, $thumbnail_id ): string {
		if ( ! current_user_can( Capabilities::USE_ADMIN_AI ) || ! current_user_can( 'upload_files' ) ) {
			return $content;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $content;
		}

		$content .= sprintf(
			'<div id="agenpress-featured-image-root" data-post-id="%d"></div>',
			(int) $post_id
		);

		return $content;
	}
}

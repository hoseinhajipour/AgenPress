<?php
/**
 * Get site info tool for Admin module.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetSiteInfoTool
 */
class GetSiteInfoTool extends AbstractTool {

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'get_site_info';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema(): array {
		return array(
			'name'        => 'get_site_info',
			'description' => 'Get basic WordPress site information',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( array $args, int $user_id ): array {
		return $this->success(
			array(
				'name'        => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'url'         => get_site_url(),
				'language'    => get_bloginfo( 'language' ),
				'wp_version'  => get_bloginfo( 'version' ),
				'post_count'  => (int) wp_count_posts( 'post' )->publish,
				'page_count'  => (int) wp_count_posts( 'page' )->publish,
				'woocommerce' => class_exists( 'WooCommerce' ),
			),
			__( 'Site info retrieved.', 'agenpress' )
		);
	}
}

<?php
/**
 * Get a single WordPress user.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class GetUserTool
 */
class GetUserTool extends AbstractTool {

	public function get_name(): string {
		return 'get_user';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'get_user',
			'description' => 'Get details about a WordPress user including roles and order count if WooCommerce is active',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'user_id' => array( 'type' => 'integer', 'description' => 'User ID' ),
				),
				'required'   => array( 'user_id' ),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'list_users' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$target = get_userdata( (int) ( $args['user_id'] ?? 0 ) );

		if ( ! $target ) {
			return $this->fail( __( 'User not found.', 'agenpress' ) );
		}

		$data = array(
			'id'         => $target->ID,
			'name'       => $target->display_name,
			'email'      => $target->user_email,
			'roles'      => $target->roles,
			'registered' => $target->user_registered,
		);

		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_customer_order_count' ) ) {
			$data['order_count'] = wc_get_customer_order_count( $target->ID );
			$data['total_spent'] = wc_get_customer_total_spent( $target->ID );
		}

		return $this->success( $data, __( 'User retrieved.', 'agenpress' ) );
	}
}

<?php
/**
 * List WordPress users.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules\Admin\Tools;

use AgenPress\Agents\AbstractTool;

defined( 'ABSPATH' ) || exit;

/**
 * Class ListUsersTool
 */
class ListUsersTool extends AbstractTool {

	public function get_name(): string {
		return 'list_users';
	}

	public function get_schema(): array {
		return array(
			'name'        => 'list_users',
			'description' => 'List WordPress users with their roles',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'role'   => array( 'type' => 'string', 'description' => 'Filter by role slug' ),
					'search' => array( 'type' => 'string', 'description' => 'Search by name or email' ),
					'limit'  => array( 'type' => 'integer', 'description' => 'Max results (default 20)' ),
				),
			),
		);
	}

	public function execute( array $args, int $user_id ): array {
		if ( ! $this->user_can( $user_id, 'list_users' ) ) {
			return $this->fail( __( 'Permission denied.', 'agenpress' ) );
		}

		$query_args = array(
			'number' => min( 50, (int) ( $args['limit'] ?? 20 ) ),
			'fields' => array( 'ID', 'display_name', 'user_email', 'user_registered' ),
		);

		if ( ! empty( $args['role'] ) ) {
			$query_args['role'] = sanitize_key( $args['role'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = '*' . sanitize_text_field( $args['search'] ) . '*';
		}

		$users = get_users( $query_args );

		$data = array_map(
			static function ( $user ) {
				return array(
					'id'       => $user->ID,
					'name'     => $user->display_name,
					'email'    => $user->user_email,
					'roles'    => $user->roles,
					'registered' => $user->user_registered,
				);
			},
			$users
		);

		return $this->success( $data, sprintf( __( 'Found %d users.', 'agenpress' ), count( $data ) ) );
	}
}

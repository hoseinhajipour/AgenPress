<?php
/**
 * Stores pending tool actions awaiting user confirmation.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Class PendingActionStore
 */
class PendingActionStore {

	/**
	 * Transient TTL in seconds.
	 */
	private const TTL = 900;

	/**
	 * Create a pending action.
	 *
	 * @param int                  $user_id         User ID.
	 * @param int                  $conversation_id Conversation ID.
	 * @param string               $module          Module slug.
	 * @param string               $tool_name       Tool name.
	 * @param array<string, mixed> $args            Tool arguments.
	 * @param string               $message         Confirmation message.
	 * @return string Pending action ID.
	 */
	public function create(
		int $user_id,
		int $conversation_id,
		string $module,
		string $tool_name,
		array $args,
		string $message
	): string {
		$id   = wp_generate_uuid4();
		$data = array(
			'user_id'         => $user_id,
			'conversation_id' => $conversation_id,
			'module'          => $module,
			'tool_name'       => $tool_name,
			'args'            => $args,
			'message'         => $message,
			'created_at'      => time(),
		);

		set_transient( $this->key( $id ), $data, self::TTL );

		return $id;
	}

	/**
	 * Get a pending action.
	 *
	 * @param string $id Pending action ID.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		$data = get_transient( $this->key( $id ) );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Delete a pending action.
	 *
	 * @param string $id Pending action ID.
	 * @return void
	 */
	public function delete( string $id ): void {
		delete_transient( $this->key( $id ) );
	}

	/**
	 * Validate pending action belongs to user.
	 *
	 * @param string $id      Pending action ID.
	 * @param int    $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	public function get_for_user( string $id, int $user_id ): ?array {
		$data = $this->get( $id );

		if ( ! $data || (int) $data['user_id'] !== $user_id ) {
			return null;
		}

		return $data;
	}

	/**
	 * Build transient key.
	 *
	 * @param string $id Pending action ID.
	 * @return string
	 */
	private function key( string $id ): string {
		return 'agenpress_pending_' . $id;
	}
}

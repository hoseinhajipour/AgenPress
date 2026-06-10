<?php
/**
 * Base class for agent tools.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbstractTool
 */
abstract class AbstractTool implements ToolInterface {

	/**
	 * Module this tool belongs to.
	 *
	 * @return string
	 */
	public function get_module(): string {
		return 'admin';
	}

	/**
	 * Whether execution requires user confirmation.
	 *
	 * @return bool
	 */
	public function requires_confirmation(): bool {
		return false;
	}

	/**
	 * Human-readable confirmation message.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return string
	 */
	public function get_confirmation_message( array $args ): string {
		return sprintf(
			/* translators: %s: tool name */
			__( 'Confirm execution of "%s"?', 'agenpress' ),
			$this->get_name()
		);
	}

	/**
	 * Build a success result.
	 *
	 * @param mixed  $data    Result data.
	 * @param string $message Result message.
	 * @return array{success: bool, data: mixed, message: string}
	 */
	protected function success( mixed $data, string $message ): array {
		return array(
			'success' => true,
			'data'    => $data,
			'message' => $message,
		);
	}

	/**
	 * Build a failure result.
	 *
	 * @param string $message Error message.
	 * @return array{success: bool, data: null, message: string}
	 */
	protected function fail( string $message ): array {
		return array(
			'success' => false,
			'data'    => null,
			'message' => $message,
		);
	}

	/**
	 * Check if a user has a capability.
	 *
	 * @param int    $user_id User ID.
	 * @param string $cap     Capability.
	 * @return bool
	 */
	protected function user_can( int $user_id, string $cap ): bool {
		return user_can( $user_id, $cap );
	}

	/**
	 * Format a post for tool output.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	protected function format_post( \WP_Post $post ): array {
		return array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'status'  => $post->post_status,
			'type'    => $post->post_type,
			'date'    => $post->post_date,
			'url'     => get_permalink( $post ),
			'excerpt' => wp_trim_words( $post->post_content, 30 ),
		);
	}
}

<?php
/**
 * Streaming response helper.
 *
 * @package AgenPress
 */

namespace AgenPress\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Class StreamingResponse
 */
class StreamingResponse {

	/**
	 * Format a chunk for SSE streaming.
	 *
	 * @param array<string, mixed> $data Data to stream.
	 * @return string
	 */
	public static function format_chunk( array $data ): string {
		return 'data: ' . wp_json_encode( $data ) . "\n\n";
	}

	/**
	 * Send stream termination signal.
	 *
	 * @return string
	 */
	public static function done(): string {
		return "data: [DONE]\n\n";
	}
}

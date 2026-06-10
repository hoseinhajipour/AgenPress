<?php
/**
 * Tool interface for agent actions.
 *
 * @package AgenPress
 */

namespace AgenPress\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ToolInterface
 */
interface ToolInterface {

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get tool JSON schema for LLM function calling.
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema(): array;

	/**
	 * Execute the tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @param int                  $user_id User ID.
	 * @return array{success: bool, data: mixed, message: string}
	 */
	public function execute( array $args, int $user_id ): array;
}

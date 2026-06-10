<?php
/**
 * Module interface.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules;

use AgenPress\Agents\ToolInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Interface ModuleInterface
 */
interface ModuleInterface {

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get module display name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get tools provided by this module.
	 *
	 * @return array<int, ToolInterface>
	 */
	public function get_tools(): array;

	/**
	 * Get system prompt for this module.
	 *
	 * @return string
	 */
	public function get_system_prompt(): string;

	/**
	 * Get prompt suggestions for the chat UI.
	 *
	 * @return array<int, string>
	 */
	public function get_suggestions(): array;

	/**
	 * Whether this module is available in the current environment.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Boot module hooks.
	 *
	 * @return void
	 */
	public function boot(): void;
}

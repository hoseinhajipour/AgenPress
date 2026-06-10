<?php
/**
 * Registry of AI modules.
 *
 * @package AgenPress
 */

namespace AgenPress\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Class ModuleManager
 */
class ModuleManager {

	/**
	 * Registered modules.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Register a module.
	 *
	 * @param ModuleInterface $module Module instance.
	 * @return void
	 */
	public function register( ModuleInterface $module ): void {
		$this->modules[ $module->get_id() ] = $module;
	}

	/**
	 * Get a module by ID.
	 *
	 * @param string $id Module ID.
	 * @return ModuleInterface|null
	 */
	public function get( string $id ): ?ModuleInterface {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Get system prompt for a module.
	 *
	 * @param string $id Module ID.
	 * @return string
	 */
	public function get_system_prompt( string $id ): string {
		$module = $this->get( $id );

		return $module ? $module->get_system_prompt() : '';
	}

	/**
	 * Get prompt suggestions for a module.
	 *
	 * @param string $id Module ID.
	 * @return array<int, string>
	 */
	public function get_suggestions( string $id ): array {
		$module = $this->get( $id );

		return $module ? $module->get_suggestions() : array();
	}

	/**
	 * Get all modules for API/frontend.
	 *
	 * @return array<int, array{id: string, name: string, suggestions: array<int, string>}>
	 */
	public function list_for_api(): array {
		$list = array();

		foreach ( $this->modules as $module ) {
			if ( ! $module->is_available() ) {
				continue;
			}

			$list[] = array(
				'id'          => $module->get_id(),
				'name'        => $module->get_name(),
				'suggestions' => $module->get_suggestions(),
			);
		}

		return $list;
	}
}

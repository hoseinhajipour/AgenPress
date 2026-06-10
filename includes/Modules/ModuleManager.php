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
	 * @param string               $id      Module ID.
	 * @param array<string, mixed> $context Optional runtime context.
	 * @return string
	 */
	public function get_system_prompt( string $id, array $context = array() ): string {
		$module = $this->get( $id );

		if ( ! $module ) {
			return '';
		}

		if ( $this->is_admin_sales_context( $id, $context ) && method_exists( $module, 'get_admin_system_prompt' ) ) {
			return $module->get_admin_system_prompt();
		}

		return $module->get_system_prompt();
	}

	/**
	 * Whether sales chat runs in wp-admin staff mode.
	 *
	 * @param string               $id      Module ID.
	 * @param array<string, mixed> $context Runtime context.
	 * @return bool
	 */
	private function is_admin_sales_context( string $id, array $context ): bool {
		return 'sales' === $id && 'admin' === sanitize_key( (string) ( $context['audience'] ?? '' ) );
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
		return $this->build_module_list( false );
	}

	/**
	 * Get modules for the wp-admin chat UI.
	 *
	 * @return array<int, array{id: string, name: string, suggestions: array<int, string>}>
	 */
	public function list_for_admin_api(): array {
		return $this->build_module_list( true );
	}

	/**
	 * Build module list for API consumers.
	 *
	 * @param bool $admin_ui Admin chat UI.
	 * @return array<int, array{id: string, name: string, suggestions: array<int, string>}>
	 */
	private function build_module_list( bool $admin_ui ): array {
		$list = array();

		foreach ( $this->modules as $module ) {
			if ( ! $module->is_available() ) {
				continue;
			}

			$suggestions = $module->get_suggestions();

			if ( $admin_ui && 'sales' === $module->get_id() && method_exists( $module, 'get_admin_suggestions' ) ) {
				$suggestions = $module->get_admin_suggestions();
			}

			$list[] = array(
				'id'          => $module->get_id(),
				'name'        => $module->get_name(),
				'suggestions' => $suggestions,
			);
		}

		return $list;
	}
}

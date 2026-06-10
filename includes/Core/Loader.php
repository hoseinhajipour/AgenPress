<?php
/**
 * WordPress hook loader.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $filters = array();

	/**
	 * Add an action hook.
	 *
	 * @param string $hook          Hook name.
	 * @param object $component     Component instance.
	 * @param string $callback      Callback method name.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Accepted arguments.
	 * @return void
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string $hook          Hook name.
	 * @param object $component     Component instance.
	 * @param string $callback      Callback method name.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Accepted arguments.
	 * @return void
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Register all hooks with WordPress.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}

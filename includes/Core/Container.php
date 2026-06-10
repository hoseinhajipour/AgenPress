<?php
/**
 * Simple service container.
 *
 * @package AgenPress
 */

namespace AgenPress\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Container
 */
class Container {

	/**
	 * Registered service factories.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Resolved service instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a service factory.
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory callback.
	 * @return void
	 */
	public function register( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Set a resolved instance directly.
	 *
	 * @param string $id       Service identifier.
	 * @param mixed  $instance Service instance.
	 * @return void
	 */
	public function set( string $id, mixed $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Get a service by id.
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 */
	public function get( string $id ): mixed {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Service "%s" is not registered.', $id )
			);
		}

		$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );

		return $this->instances[ $id ];
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}
}

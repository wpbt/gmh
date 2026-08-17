<?php
/**
 * Class responsible for the DI-lite service container for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Core;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use Closure;

/**
 * Minimal DI container: register a service as a closure, resolve it
 * once and cache the instance. Plugin::register_hooks() resolves
 * every registered service via this class.
 */
class Container {

	/**
	 * Registered services, keyed by name.
	 *
	 * @var array<string, array{resolver: Closure, instance: object|null}>
	 */
	private array $services = array();

	/**
	 * Register a service
	 *
	 * @param string  $name     Service name/key.
	 * @param Closure $resolver Closure that builds the service instance, given the container.
	 */
	public function set( string $name, Closure $resolver ): void {
		$this->services[ $name ] = array(
			'resolver' => $resolver,
			'instance' => null,
		);
	}

	/**
	 * Get a service (creates it once, then returns same instance).
	 *
	 * @param string $name Service name/key.
	 * @throws \Exception If no service is registered under that name.
	 */
	public function get( string $name ): object {
		// Make sure the service is set first.
		if ( ! isset( $this->services[ $name ] ) ) {
			throw new \Exception( esc_html( "Service '{$name}' not found in container" ) );
		}

		// Return existing instance if already created.
		if ( null !== $this->services[ $name ]['instance'] ) {
			return $this->services[ $name ]['instance'];
		}

		// Create and store the instance.
		$resolver                            = $this->services[ $name ]['resolver'];
		$this->services[ $name ]['instance'] = $resolver( $this );

		return $this->services[ $name ]['instance'];
	}

	/**
	 * Names of every registered service.
	 *
	 * @return string[]
	 */
	public function get_registered_services(): array {
		return array_keys( $this->services );
	}

	/**
	 * Check if service exists
	 *
	 * @param string $name Service name/key.
	 */
	public function has( string $name ): bool {
		return isset( $this->services[ $name ] );
	}
}

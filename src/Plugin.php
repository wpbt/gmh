<?php
/**
 * Main plugin bootstrap/singleton class for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Core\Container;
use GhostMediaHunter\Interfaces\Registrable;
use GhostMediaHunter\Providers\ServiceProvider;

/**
 * Singleton entry point: builds the Container, registers every
 * service from ServiceProvider, then hooks the Registrable ones
 * into WordPress.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * DI container holding every registered service.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Returns the singleton instance, creating it on first call.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers services then hooks them into WordPress.
	 */
	public function init(): void {
		$this->register_services();
		$this->register_hooks();
	}

	/**
	 * Resolves every service from ServiceProvider into the container.
	 */
	private function register_services(): void {
		$services = ServiceProvider::get_services();

		foreach ( $services as $name => $resolver ) {
			$this->container->set( $name, $resolver );
		}
	}

	/**
	 * Hooks the plugin textdomain, then registers every Registrable
	 * service resolved from the container.
	 */
	private function register_hooks(): void {
		// Load plugin textdomain!
		add_action( 'init', array( $this, 'load_text_domain' ) );

		foreach ( $this->container->get_registered_services() as $service ) {
			$hook = $this->container->get( $service );
			if ( $hook instanceof Registrable ) {
				$hook->register();
			}
		}
	}

	/**
	 * Loads the plugin's text domain for translations.
	 */
	public function load_text_domain(): void {
		load_plugin_textdomain(
			'ghost-media-hunter',
			false,
			dirname( GHOST_MEDIA_HUNTER_FILE ) . '/languages'
		);
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {
	}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception Always — unserializing a singleton is not supported.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}

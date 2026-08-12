<?php

declare(strict_types=1);

namespace GhostMediaHunter;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Core\Container;
use GhostMediaHunter\Interfaces\Registrable;
use GhostMediaHunter\Providers\ServiceProvider;

class Plugin
{
    private static ?Plugin $instance = null;
    private Container $container;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->container = new Container();
    }

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        $this->register_services();
        $this->register_hooks();
    }

    private function register_services(): void
    {
        $services = ServiceProvider::get_services();

        foreach ($services as $name => $resolver) {
            $this->container->set($name, $resolver);
        }
    }

    private function register_hooks(): void
    {
        // Load plugin textdomain
        add_action('init', [$this, 'load_text_domain']);

        foreach ($this->container->get_registered_services() as $service) {
            $hook = $this->container->get($service);
            if ($hook instanceof Registrable) {
                $hook->register();
            }
        }
    }

    public function load_text_domain(): void
    {
        load_plugin_textdomain(
            'ghost-media-hunter',
            false,
            dirname(GHOST_MEDIA_HUNTER_FILE) . '/languages'
        );
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
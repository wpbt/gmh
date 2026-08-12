<?php

declare(strict_types=1);

namespace GhostMediaHunter\Core;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use Closure;

class Container
{
    private array $services = [];

    /**
     * Register a service
     */
    public function set(string $name, Closure $resolver): void
    {
        $this->services[$name] = [
            'resolver' => $resolver,
            'instance' => null,
        ];
    }

    /**
     * Get a service (creates it once, then returns same instance)
     */
    public function get(string $name): object
    {
        // make sure the service is set first.
        if (!isset($this->services[$name])) {
            throw new \Exception("Service '{$name}' not found in container");
        }

        // Return existing instance if already created
        if ($this->services[$name]['instance'] !== null) {
            return $this->services[$name]['instance'];
        }

        // Create and store the instance
        $resolver = $this->services[$name]['resolver'];
        $this->services[$name]['instance'] = $resolver($this);

        return $this->services[$name]['instance'];
    }

    public function get_registered_services(): array
    {
        return array_keys($this->services);
    }

    /**
     * Check if service exists
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }
}
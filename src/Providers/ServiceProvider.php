<?php

declare(strict_types=1);

namespace GhostMediaHunter\Providers;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Services\AdminMenu;
use GhostMediaHunter\Services\ScanResultsRepository;

class ServiceProvider
{
    /**
     * Get all services with their resolver functions
     */
    public static function get_services(): array
    {
        return [
            ScanResultsRepository::class => function ($c) {
                return new ScanResultsRepository;
            },
            AdminMenu::class => function ($c) {
                return new AdminMenu;
            }
        ];
    }
}
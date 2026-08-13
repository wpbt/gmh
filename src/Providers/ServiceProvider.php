<?php

declare(strict_types=1);

namespace GhostMediaHunter\Providers;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Services\AdminMenu;
use GhostMediaHunter\Services\Checkers\OptionsChecker;
use GhostMediaHunter\Services\Checkers\MenuChecker;
use GhostMediaHunter\Services\Checkers\PostContentChecker;
use GhostMediaHunter\Services\Checkers\FeaturedImageChecker;
use GhostMediaHunter\Services\Checkers\PostMetaChecker;
use GhostMediaHunter\Services\Checkers\WidgetChecker;
use GhostMediaHunter\Services\IdentifierResolver;
use GhostMediaHunter\Services\Scan\Engine;
use GhostMediaHunter\Services\ScanResultsRepository;
use GhostMediaHunter\Services\ScanTrigger;

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
            IdentifierResolver::class => function ($c) {
                return new IdentifierResolver;
            },
            PostContentChecker::class => function ($c) {
                return new PostContentChecker;
            },
            FeaturedImageChecker::class => function ($c) {
                return new FeaturedImageChecker;
            },
            PostMetaChecker::class => function ($c) {
                return new PostMetaChecker;
            },
            OptionsChecker::class => function ($c) {
                return new OptionsChecker;
            },
            WidgetChecker::class => function ($c) {
                return new WidgetChecker;
            },
            MenuChecker::class => function ($c) {
                return new MenuChecker;
            },
            Engine::class => function ($c) {
                return new Engine(
                    $c->get(IdentifierResolver::class),
                    $c->get(ScanResultsRepository::class),
                    [
                        $c->get(PostContentChecker::class),
                        $c->get(FeaturedImageChecker::class),
                        $c->get(PostMetaChecker::class),
                        $c->get(OptionsChecker::class),
                        $c->get(WidgetChecker::class),
                        $c->get(MenuChecker::class),
                    ]
                );
            },
            AdminMenu::class => function ($c) {
                return new AdminMenu($c->get(ScanResultsRepository::class));
            },
            ScanTrigger::class => function ($c) {
                return new ScanTrigger($c->get(Engine::class));
            }
        ];
    }
}
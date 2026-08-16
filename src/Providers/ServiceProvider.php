<?php

declare(strict_types=1);

namespace GhostMediaHunter\Providers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Services\AdminMenu;
use GhostMediaHunter\Services\Checkers\OptionsChecker;
use GhostMediaHunter\Services\Checkers\MenuChecker;
use GhostMediaHunter\Services\Checkers\PostContentChecker;
use GhostMediaHunter\Services\Checkers\FeaturedImageChecker;
use GhostMediaHunter\Services\Checkers\PostMetaChecker;
use GhostMediaHunter\Services\Checkers\WidgetChecker;
use GhostMediaHunter\Services\IdentifierResolver;
use GhostMediaHunter\Services\Scan\Engine;
use GhostMediaHunter\Services\ResultActions;
use GhostMediaHunter\Services\ScanResultsRepository;
use GhostMediaHunter\Services\ScanRestController;
use GhostMediaHunter\Services\ScanRunner;
use GhostMediaHunter\Services\ScanTrigger;
use GhostMediaHunter\Services\CronScheduler;
use GhostMediaHunter\Services\SettingsPage;

class ServiceProvider {

	/**
	 * Get all services with their resolver functions
	 *
	 * @return array<class-string, \Closure(\GhostMediaHunter\Core\Container): object>
	 */
	public static function get_services(): array {
		return array(
			ScanResultsRepository::class => function ( $c ) {
				return new ScanResultsRepository();
			},
			IdentifierResolver::class    => function ( $c ) {
				return new IdentifierResolver();
			},
			PostContentChecker::class    => function ( $c ) {
				return new PostContentChecker();
			},
			FeaturedImageChecker::class  => function ( $c ) {
				return new FeaturedImageChecker();
			},
			PostMetaChecker::class       => function ( $c ) {
				return new PostMetaChecker();
			},
			OptionsChecker::class        => function ( $c ) {
				return new OptionsChecker();
			},
			WidgetChecker::class         => function ( $c ) {
				return new WidgetChecker();
			},
			MenuChecker::class           => function ( $c ) {
				return new MenuChecker();
			},
			Engine::class                => function ( $c ) {
				return new Engine(
					$c->get( IdentifierResolver::class ),
					$c->get( ScanResultsRepository::class ),
					array(
						$c->get( PostContentChecker::class ),
						$c->get( FeaturedImageChecker::class ),
						$c->get( PostMetaChecker::class ),
						$c->get( OptionsChecker::class ),
						$c->get( WidgetChecker::class ),
						$c->get( MenuChecker::class ),
					)
				);
			},
			ScanRunner::class            => function ( $c ) {
				return new ScanRunner( $c->get( Engine::class ) );
			},
			AdminMenu::class             => function ( $c ) {
				return new AdminMenu( $c->get( ScanResultsRepository::class ) );
			},
			ScanTrigger::class           => function ( $c ) {
				return new ScanTrigger( $c->get( ScanRunner::class ) );
			},
			CronScheduler::class         => function ( $c ) {
				return new CronScheduler( $c->get( ScanRunner::class ) );
			},
			ScanRestController::class    => function ( $c ) {
				return new ScanRestController( $c->get( ScanRunner::class ) );
			},
			SettingsPage::class          => function ( $c ) {
				return new SettingsPage();
			},
			ResultActions::class         => function ( $c ) {
				return new ResultActions( $c->get( ScanResultsRepository::class ) );
			},
		);
	}
}

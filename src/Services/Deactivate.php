<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

class Deactivate {

	public static function run(): void {
		wp_clear_scheduled_hook( CronScheduler::HOOK );
	}
}

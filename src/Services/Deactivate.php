<?php
/**
 * Class responsible for plugin deactivation cleanup for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation — clears the scheduled cron hook so it
 * doesn't keep firing after the plugin is off.
 */
class Deactivate {

	/**
	 * Clears the scheduled scan cron hook.
	 */
	public static function run(): void {
		wp_clear_scheduled_hook( CronScheduler::HOOK );
	}
}

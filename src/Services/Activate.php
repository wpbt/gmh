<?php
/**
 * Class responsible for plugin activation setup for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation — creates the results table, schedules
 * the daily cron scan, and seeds the scan key and checker keywords
 * if they don't already exist.
 */
class Activate {

	private const DEFAULT_CHECKER_KEYWORDS = array(
		'image',
		'logo',
		'photo',
		'banner',
		'thumbnail',
		'icon',
		'avatar',
		'media',
		'background',
		'gallery',
	);

	/**
	 * Installs the table, schedules cron, and seeds default options.
	 * Guarded so re-activation doesn't reschedule cron or clobber an
	 * existing scan key/keyword list.
	 */
	public static function run(): void {
		Installer::install();

		if ( ! wp_next_scheduled( CronScheduler::HOOK ) ) {
			wp_schedule_event( time(), 'daily', CronScheduler::HOOK );
		}

		if ( ! get_option( ScanRestController::OPTION_KEY ) ) {
			update_option( ScanRestController::OPTION_KEY, wp_generate_password( 32, false, false ), false );
		}

		if ( ! get_option( 'gmh_checker_keywords' ) ) {
			update_option( 'gmh_checker_keywords', self::DEFAULT_CHECKER_KEYWORDS, false );
		}
	}
}

<?php
/**
 * Class responsible for wiring the scheduled scan cron hook for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Hooks the scheduled WP-Cron event to a full scan. The event itself
 * is scheduled on plugin activation (see Activate::run()) and cleared
 * on deactivation (see Deactivate::run()) — this class only wires the
 * hook callback on every request, it doesn't own the scheduling.
 */
class CronScheduler implements Registrable {

	public const HOOK = 'gmh_scheduled_scan';

	/**
	 * Shared runner used by the ajax/cron/REST entry points.
	 *
	 * @var ScanRunner
	 */
	private ScanRunner $runner;

	/**
	 * Constructor.
	 *
	 * @param ScanRunner $runner Shared scan runner.
	 */
	public function __construct( ScanRunner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Registers the scheduled-scan cron hook callback.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'handle' ) );
	}

	/**
	 * Runs the scheduled scan, logging (not erroring) if one was
	 * already in progress and this run was skipped.
	 */
	public function handle(): void {
		$scanned = $this->runner->run_all();

		if ( null === $scanned ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- deliberate one-line notice when a scheduled scan is skipped due to overlap; not debug scaffolding.
			error_log( 'Ghost Media Hunter: scheduled scan skipped — a scan was already in progress.' );
		}
	}
}

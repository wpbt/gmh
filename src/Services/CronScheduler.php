<?php

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

	private ScanRunner $runner;

	public function __construct( ScanRunner $runner ) {
		$this->runner = $runner;
	}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'handle' ) );
	}

	public function handle(): void {
		$scanned = $this->runner->run_all();

		if ( $scanned === null ) {
			error_log( 'Ghost Media Hunter: scheduled scan skipped — a scan was already in progress.' );
		}
	}
}

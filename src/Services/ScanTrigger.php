<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Handles the manual "Scan now" button on the admin page — the
 * ajax-specific wrapper around ScanRunner::run_all(). Cron and the
 * external REST trigger call the same runner, just with different
 * auth/entry points.
 */
class ScanTrigger implements Registrable {

	public const ACTION = 'gmh_scan_now';

	private ScanRunner $runner;

	public function __construct( ScanRunner $runner ) {
		$this->runner = $runner;
	}

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function handle(): void {
		check_ajax_referer( self::ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'ghost-media-hunter' ) ), 403 );
		}

		$scanned = $this->runner->run_all();

		if ( $scanned === null ) {
			wp_send_json_error(
				array( 'message' => __( 'A scan is already in progress.', 'ghost-media-hunter' ) ),
				409
			);
		}

		wp_send_json_success(
			array(
				'scanned' => $scanned,
			)
		);
	}
}

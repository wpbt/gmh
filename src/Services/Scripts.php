<?php
/**
 * Class responsible for registering js and css for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

use GhostMediaHunter\Interfaces\Registrable;

class Scripts implements Registrable {

	/**
	 * Registers the admin_enqueue_scripts for this service.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
	}

	/**
	 * Method to register scripts
	 */
	public function register_scripts( string $hook ): void {
		if( ! str_contains( $hook, AdminMenu::SLUG ) ) {
			return;
		}

		wp_enqueue_script( 'gmh-admin-script', GHOST_MEDIA_HUNTER_URL . 'assets/js/gmh.js', array( 'jquery' ), '' );

		wp_localize_script(
			'gmh-admin-script',
			'gmhAdminMenu',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'scanAction'   => ScanTrigger::ACTION,
				'scanNonce'    => wp_create_nonce( ScanTrigger::ACTION ),
				'scanningText' => __( 'Scanning…', 'ghost-media-hunter' ),
				'failedText'   => __( 'Scan failed.', 'ghost-media-hunter' ),
			)
		);

		wp_enqueue_style( 'gmh-style', GHOST_MEDIA_HUNTER_URL . '/assets/css/gmh.css' );
	}
}

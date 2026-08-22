<?php
/**
 * Class responsible for registering js and css for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Registers admin JavaScript and CSS assets.
 */
class Scripts implements Registrable {

	/**
	 * Registers the admin_enqueue_scripts for this service.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
	}

	/**
	 * Registers admin scripts and styles for the plugin settings page.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function register_scripts( string $hook ): void {
		if ( ! str_contains( $hook, AdminMenu::SLUG ) ) {
			return;
		}

		$version = WP_DEBUG ? time() : GHOST_MEDIA_HUNTER_VERSION;

		wp_enqueue_script(
			'gmh-admin-script',
			GHOST_MEDIA_HUNTER_URL . 'assets/js/gmh.js',
			array( 'jquery' ),
			$version,
			true
		);

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

		wp_enqueue_style( 'gmh-style', GHOST_MEDIA_HUNTER_URL . '/assets/css/gmh.css', array(), $version );
	}
}

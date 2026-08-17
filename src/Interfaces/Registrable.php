<?php
/**
 * Interface for services that hook into WordPress, for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Interfaces;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

interface Registrable {

	/**
	 * Register the service (hook into WordPress)
	 * This is called after all services are registered
	 */
	public function register(): void;
}

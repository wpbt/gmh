<?php
/**
 * Plugin Name:       Ghost Media Hunter
 * Plugin URI:        https://bharatt.com.np
 * Description:       WordPress plugin to detect unused media.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Bharat Thapa
 * Author URI:        https://bharatt.com.np
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       ghost-media-hunter
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

/*
 *  Ghost Media Hunter is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 3, as
 *  published by the Free Software Foundation.
 *
 *  Ghost Media Hunter is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Ghost Media Hunter; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

! defined( 'GHOST_MEDIA_HUNTER_FILE' ) && define( 'GHOST_MEDIA_HUNTER_FILE', __FILE__ );
! defined( 'GHOST_MEDIA_HUNTER_BASENAME' ) && define( 'GHOST_MEDIA_HUNTER_BASENAME', plugin_basename( GHOST_MEDIA_HUNTER_FILE ) );
! defined( 'GHOST_MEDIA_HUNTER_PATH' ) && define( 'GHOST_MEDIA_HUNTER_PATH', dirname( GHOST_MEDIA_HUNTER_FILE ) . '/' );
! defined( 'GHOST_MEDIA_HUNTER_URL' ) && define( 'GHOST_MEDIA_HUNTER_URL', plugins_url( '/', GHOST_MEDIA_HUNTER_FILE ) );

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( \GhostMediaHunter\Services\Activate::class, 'run' ) );
register_deactivation_hook( __FILE__, array( \GhostMediaHunter\Services\Deactivate::class, 'run' ) );

/**
 * Bootstrap and run the plugin.
 *
 * @return void
 */
function ghostmediahunter(): void {
	$plugin = GhostMediaHunter\Plugin::get_instance();
	$plugin->init();
}

add_action( 'plugins_loaded', 'ghostmediahunter' );

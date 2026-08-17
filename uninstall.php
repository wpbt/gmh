<?php
/**
 * Uninstall script for Ghost Media Hunter.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$table = $wpdb->prefix . 'gmh_scan_results';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table is built from $wpdb->prefix and used during plugin cleanup.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

delete_option( 'gmh_db_version' );
delete_option( 'gmh_scan_key' );
delete_option( 'gmh_checker_keywords' );

wp_clear_scheduled_hook( 'gmh_scheduled_scan' );

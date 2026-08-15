<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Deliberately self-contained — does NOT load the plugin's
 * autoloader/classes. Uninstall should keep working even if something
 * in the class structure changes later, so table/option names are
 * written directly here rather than referencing Installer::table_info()
 * etc. Deactivation already clears the scheduled cron event (see
 * Deactivate::run()) and WordPress requires deactivation before a
 * plugin can be deleted, so the wp_clear_scheduled_hook() call below
 * is just a safety net, not the primary cleanup path for that.
 */

global $wpdb;

// Delete PLUGIN DB table(s).
$table = $wpdb->prefix . 'gmh_scan_results';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix, not user input
$wpdb->query("DROP TABLE IF EXISTS {$table}");

// Delete PLUGIN options
delete_option('gmh_db_version');
delete_option('gmh_scan_key');
delete_option('gmh_checker_keywords');

wp_clear_scheduled_hook('gmh_scheduled_scan');
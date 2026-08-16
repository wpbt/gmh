<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

class Installer {

	public const DB_VERSION        = '1.0.0';
	public const DB_VERSION_OPTION = 'gmh_db_version';

	/**
	 * @return array{name: string, charset_collate: string}
	 */
	public static function table_info(): array {
		global $wpdb;

		return array(
			'name'            => $wpdb->prefix . 'gmh_scan_results',
			'charset_collate' => $wpdb->get_charset_collate(),
		);
	}

	public static function install(): void {
		[ 'name' => $table_name, 'charset_collate' => $charset_collate ] = self::table_info();

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attachment_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'unknown',
            matched_sources LONGTEXT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ignored TINYINT(1) NOT NULL DEFAULT 0,
            last_checked DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY attachment_id (attachment_id),
            KEY status (status)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}
}

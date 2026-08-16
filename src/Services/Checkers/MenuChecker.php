<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks nav menu items for a custom link pointing directly at the
 * attachment's file (e.g. a "Custom Link" menu item used to link
 * straight to a PDF or image). Menu items are posts of type
 * nav_menu_item; the URL for a custom link lives in the
 * `_menu_item_url` post meta.
 *
 * No ID-style matching here — menus don't store attachment IDs, only
 * the raw URL, so this reuses the same path/filename patterns
 * PostContentChecker uses for URL matching.
 */
class MenuChecker implements CheckerInterface {

	public function name(): string {
		return 'menus';
	}

	public function check( ?array $identifiers ): bool {
		if ( $identifiers === null ) {
			return false;
		}

		global $wpdb;

		$like_path    = '%' . $wpdb->esc_like( $identifiers['relative_path'] ) . '%';
		$like_resized = '%' . $wpdb->esc_like( $identifiers['basename'] . '-' ) . '%'
			. $wpdb->esc_like( '.' . $identifiers['extension'] ) . '%';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() below
		$sql = $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_menu_item_url'
             AND p.post_status NOT IN ('trash', 'auto-draft')
             AND (
                 pm.meta_value LIKE %s
                 OR pm.meta_value LIKE %s
             )
             LIMIT 1",
			$like_path,
			$like_resized
		);

		return $wpdb->get_var( $sql ) !== null;
	}
}

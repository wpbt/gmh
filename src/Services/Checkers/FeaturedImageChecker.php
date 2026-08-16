<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks whether the attachment is set as a post's featured image
 * (the `_thumbnail_id` post meta value).
 */
class FeaturedImageChecker implements CheckerInterface {

	public function name(): string {
		return 'featured_image';
	}

	public function check( ?array $identifiers ): bool {
		if ( $identifiers === null ) {
			return false;
		}

		global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() below
		$sql = $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_thumbnail_id'
             AND pm.meta_value = %d
             AND p.post_status NOT IN ('trash', 'auto-draft')
             LIMIT 1",
			$identifiers['id']
		);

		return $wpdb->get_var( $sql ) !== null;
	}
}

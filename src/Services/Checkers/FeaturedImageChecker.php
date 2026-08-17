<?php
/**
 * Class responsible for checking whether an attachment is a featured image for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

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

	/**
	 * Checker identifier used in matched_sources.
	 */
	public function name(): string {
		return 'featured_image';
	}

	/**
	 * Checks wp_postmeta for a post using this attachment as its
	 * featured image (_thumbnail_id).
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom query joining wp_postmeta/wp_posts, not covered by the Meta API; caching deferred to the SQL/performance review pass.
		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_thumbnail_id'
				AND pm.meta_value = %d
				AND p.post_status NOT IN ('trash', 'auto-draft')
				LIMIT 1",
				$identifiers['id']
			)
		);
	}
}

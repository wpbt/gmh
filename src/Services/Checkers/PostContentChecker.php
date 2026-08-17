<?php
/**
 * Class responsible for checking post content for attachment references for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks wp_posts.post_content for a reference to the attachment.
 * Covers the most common real-world cases:
 *  - "wp-image-{id}" class, added automatically when media library
 *    images are inserted (works for both classic and block editor)
 *  - the exact relative upload path (raw <img src> to the original file)
 *  - a resized variant filename, e.g. "photo-1024x683.jpg"
 */
class PostContentChecker implements CheckerInterface {

	/**
	 * Checker identifier used in matched_sources.
	 */
	public function name(): string {
		return 'post_content';
	}

	/**
	 * Checks wp_posts.post_content for a reference to this attachment.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		global $wpdb;

		$like_class   = '%' . $wpdb->esc_like( 'wp-image-' . $identifiers['id'] ) . '%';
		$like_path    = '%' . $wpdb->esc_like( $identifiers['relative_path'] ) . '%';
		$like_resized = '%' . $wpdb->esc_like( $identifiers['basename'] . '-' ) . '%'
			. $wpdb->esc_like( '.' . $identifiers['extension'] ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom pattern search across wp_posts.post_content, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status NOT IN ('trash', 'auto-draft')
				AND (
					post_content LIKE %s
					OR post_content LIKE %s
					OR post_content LIKE %s
				)
				LIMIT 1",
				$like_class,
				$like_path,
				$like_resized
			)
		);
	}
}

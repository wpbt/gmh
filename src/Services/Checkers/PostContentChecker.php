<?php

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

	public function name(): string {
		return 'post_content';
	}

	public function check( ?array $identifiers ): bool {
		if ( $identifiers === null ) {
			return false;
		}

		global $wpdb;

		$like_class   = '%' . $wpdb->esc_like( 'wp-image-' . $identifiers['id'] ) . '%';
		$like_path    = '%' . $wpdb->esc_like( $identifiers['relative_path'] ) . '%';
		$like_resized = '%' . $wpdb->esc_like( $identifiers['basename'] . '-' ) . '%'
			. $wpdb->esc_like( '.' . $identifiers['extension'] ) . '%';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() below
		$sql = $wpdb->prepare(
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
		);

		return $wpdb->get_var( $sql ) !== null;
	}
}

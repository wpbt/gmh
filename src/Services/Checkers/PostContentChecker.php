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
 *
 * Two correctness fixes on top of the raw pattern search:
 *
 *  - Self-match: an attachment's own wp_posts row (post_content holds
 *    the "Description" field, which is usually empty but not always)
 *    is always excluded — it's metadata about the file, not usage of
 *    it. Unconditional, not tied to any setting.
 *
 *  - Revisions: excluded by default (post_type != 'revision'). A
 *    revision row is a frozen snapshot of past content — if an image
 *    was removed from the live post, the old revision still contains
 *    the reference, which made removed images look permanently
 *    "used." Toggle via the gmh_include_revisions setting for anyone
 *    who wants the more conservative "was this ever referenced"
 *    behaviour instead.
 *
 * Deliberately NOT filtered to specific post_types (e.g. post/page
 * only) — that would silently drop coverage of block themes storing
 * nav menus and other structures as ordinary posts (wp_navigation,
 * wp_template, wp_template_part, etc.), whose content is plain block
 * markup indistinguishable from a page's. Blacklisting the two known
 * non-content types (revision here, attachment via self-match) keeps
 * that coverage intact.
 */
class PostContentChecker implements CheckerInterface {

	/**
	 * Option storing whether revisions should also be searched.
	 * Off by default — see class docblock for why.
	 */
	public const INCLUDE_REVISIONS_OPTION = 'gmh_include_revisions';

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

		$include_revisions = (bool) get_option( self::INCLUDE_REVISIONS_OPTION, false );
		$revision_clause   = $include_revisions ? '' : "AND post_type != 'revision'";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom pattern search across wp_posts.post_content, not covered by WP_Query; $revision_clause is one of two fixed static strings (no user input), toggled by an admin-only boolean setting, so it's interpolated rather than bound as a placeholder; caching deferred to the SQL/performance review pass.
		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE ID != %d
				AND post_status NOT IN ('trash', 'auto-draft')
				{$revision_clause}
				AND (
					post_content LIKE %s
					OR post_content LIKE %s
					OR post_content LIKE %s
				)
				LIMIT 1",
				$identifiers['id'],
				$like_class,
				$like_path,
				$like_resized
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- custom pattern search across wp_posts.post_content, not covered by WP_Query; $revision_clause is one of two fixed static strings (no user input), toggled by an admin-only boolean setting, so it's interpolated rather than bound as a placeholder; caching deferred to the SQL/performance review pass.
	}
}

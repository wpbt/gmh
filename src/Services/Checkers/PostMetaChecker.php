<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks wp_postmeta for a reference to the attachment ID — covers
 * custom fields (plain scalar meta) and serialized array values (e.g.
 * ACF gallery fields storing multiple attachment IDs).
 *
 * Scope: matches on attachment ID only, not URL/filename stored in
 * meta values — that's a separate concern, left out for now.
 *
 * Excludes `_thumbnail_id` — that's FeaturedImageChecker's job, kept
 * separate so matched_sources stays meaningful instead of duplicating.
 *
 * Restricted to meta_keys that look image-related, same reasoning as
 * OptionsChecker: a bare numeric ID match can coincidentally hit an
 * unrelated field (a width, an order, a count) that happens to equal
 * the attachment ID. Real image-storing meta keys almost always
 * contain one of these words.
 *
 * Keywords are configurable via Settings (gmh_checker_keywords option,
 * shared with OptionsChecker) — DEFAULT_KEYWORDS below is only the
 * fallback used if that option is somehow missing (should be seeded
 * on activation).
 */
class PostMetaChecker implements CheckerInterface {

	private const DEFAULT_KEYWORDS = array(
		'image',
		'logo',
		'photo',
		'banner',
		'thumbnail',
		'icon',
		'avatar',
		'media',
		'background',
		'gallery',
	);

	public function name(): string {
		return 'post_meta';
	}

	public function check( ?array $identifiers ): bool {
		if ( $identifiers === null ) {
			return false;
		}

		global $wpdb;

		$id = (string) $identifiers['id'];

		// Plain scalar meta value: meta_value = '42'
		$exact = $id;

		// Serialized string element, e.g. ACF gallery: s:2:"42";
		$like_serialized_string = '%"' . $wpdb->esc_like( $id ) . '";%';

		// Serialized int element, e.g. a:1:{i:0;i:42;}
		$like_serialized_int = '%:' . $wpdb->esc_like( $id ) . ';%';

		$keywords = get_option( 'gmh_checker_keywords', self::DEFAULT_KEYWORDS );

		// See OptionsChecker::check() for why this guard is needed —
		// get_option()'s default doesn't cover "exists but empty."
		if ( ! is_array( $keywords ) || empty( $keywords ) ) {
			$keywords = self::DEFAULT_KEYWORDS;
		}

		$keyword_clauses = array();
		$keyword_values  = array();
		foreach ( $keywords as $keyword ) {
			$keyword_clauses[] = 'pm.meta_key LIKE %s';
			$keyword_values[]  = '%' . $wpdb->esc_like( $keyword ) . '%';
		}
		$keyword_sql = implode( ' OR ', $keyword_clauses );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() below
		$sql = $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key != '_thumbnail_id'
             AND ({$keyword_sql})
             AND p.post_status NOT IN ('trash', 'auto-draft')
             AND (
                 pm.meta_value = %s
                 OR pm.meta_value LIKE %s
                 OR pm.meta_value LIKE %s
             )
             LIMIT 1",
			array_merge( $keyword_values, array( $exact, $like_serialized_string, $like_serialized_int ) )
		);

		return $wpdb->get_var( $sql ) !== null;
	}
}

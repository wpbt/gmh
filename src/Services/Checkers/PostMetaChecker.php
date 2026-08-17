<?php
/**
 * Class responsible for checking post meta for attachment references for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

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

	/**
	 * Checker identifier used in matched_sources.
	 */
	public function name(): string {
		return 'post_meta';
	}

	/**
	 * Checks wp_postmeta for a meta value referencing this attachment ID.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		global $wpdb;

		$id = (string) $identifiers['id'];

		// Plain scalar meta value: meta_value = '42'!
		$exact = $id;

		// Serialized string element, e.g. ACF gallery: s:2:"42";!
		$like_serialized_string = '%"' . $wpdb->esc_like( $id ) . '";%';

		// Serialized int element, e.g. a:1:{i:0;i:42;}!
		$like_serialized_int = '%:' . $wpdb->esc_like( $id ) . ';%';

		$keywords = get_option( 'gmh_checker_keywords', self::DEFAULT_KEYWORDS );

		/**
		 * See OptionsChecker::check() for why this guard is needed —
		 * get_option()'s default doesn't cover "exists but empty."
		 */
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

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Reason: replacement count is dynamic (one %s per configured keyword, plus 3
		// fixed) — array_merge() builds it at runtime, which phpcs can't evaluate
		// statically. $keyword_sql is built entirely from the static string
		// 'pm.meta_key LIKE %s' repeated per keyword — no user input is interpolated,
		// actual values are bound via $keyword_values. Custom pattern search across
		// wp_postmeta, not covered by the Meta API; caching deferred to the SQL/
		// performance review pass.
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

		return null !== $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

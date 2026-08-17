<?php
/**
 * Class responsible for checking wp_options for attachment references for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks wp_options for a reference to the attachment ID — covers
 * theme mods, Customizer settings (logo/favicon), and plugin settings
 * that store an attachment ID, including serialized array values.
 *
 * Scope: matches on attachment ID only, same as PostMetaChecker — not
 * URL/filename stored in option values.
 *
 * Scans the whole options table (no autoload/transient exclusion) —
 * an explicit choice to keep this simple for now; revisit if false
 * positives from transient/cache data turn out to be a problem.
 *
 * Restricted to option_names that look image-related. Without this,
 * a bare numeric ID match can coincidentally hit unrelated data (e.g.
 * a width, a menu order, a version number that happens to equal the
 * attachment ID) — found in practice with attachment 122. Real
 * image-storing option names almost always contain one of these
 * words, so this filter cuts the false-positive surface without
 * meaningfully losing real matches.
 *
 * Keywords are configurable via Settings (gmh_checker_keywords option,
 * shared with PostMetaChecker) — DEFAULT_KEYWORDS below is only the
 * fallback used if that option is somehow missing (should be seeded
 * on activation).
 */
class OptionsChecker implements CheckerInterface {

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
		return 'options';
	}

	/**
	 * Checks wp_options for an option value referencing this attachment ID.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		global $wpdb;

		$id = (string) $identifiers['id'];

		// Plain scalar option value, e.g. a "site_logo" option storing just the ID.
		$exact = $id;

		// Serialized string element, e.g. s:2:"42";.
		$like_serialized_string = '%"' . $wpdb->esc_like( $id ) . '";%';

		// Serialized int element, e.g. i:42;.
		$like_serialized_int = '%:' . $wpdb->esc_like( $id ) . ';%';

		$keywords = get_option( 'gmh_checker_keywords', self::DEFAULT_KEYWORDS );

		/**
		 * The get_option()'s default only applies when the option row doesn't
		 * exist at all — if it exists but was saved as an empty array (e.g.
		 * the settings field got submitted blank), get_option() faithfully
		 * returns that empty array, not our fallback. An empty $keywords
		 * means zero LIKE clauses below, which produces invalid SQL
		 * ("WHERE () AND ...") — guard explicitly instead of trusting the
		 * stored value is always non-empty.
		 */
		if ( ! is_array( $keywords ) || empty( $keywords ) ) {
			$keywords = self::DEFAULT_KEYWORDS;
		}

		$keyword_clauses = array();
		$keyword_values  = array();
		foreach ( $keywords as $keyword ) {
			$keyword_clauses[] = 'option_name LIKE %s';
			$keyword_values[]  = '%' . $wpdb->esc_like( $keyword ) . '%';
		}
		$keyword_sql = implode( ' OR ', $keyword_clauses );

        // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Reason: replacement count is dynamic (one %s per configured keyword, plus 3
		// fixed) — array_merge() builds it at runtime, which phpcs can't evaluate
		// statically. $keyword_sql is built entirely from the static string
		// 'option_name LIKE %s' repeated per keyword — no user input is
		// interpolated, actual values are bound via $keyword_values. Custom pattern
		// search across wp_options, not covered by the Options API; caching
		// deferred to the SQL/performance review pass.
		$sql = $wpdb->prepare(
			"SELECT option_id FROM {$wpdb->options}
			WHERE ({$keyword_sql})
			AND (
				option_value = %s
				OR option_value LIKE %s
				OR option_value LIKE %s
			)
			LIMIT 1",
			array_merge( $keyword_values, array( $exact, $like_serialized_string, $like_serialized_int ) )
		);

		return null !== $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

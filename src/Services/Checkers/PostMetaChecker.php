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
 * Checks wp_postmeta for a reference to the attachment ID — but only
 * against rules the user has explicitly configured (Settings > Custom
 * Rules), never by guessing at meta_key names.
 *
 * This replaces the old keyword-gated version (matched any meta_key
 * *containing* a word like "image"/"logo"), which was a guess, not a
 * guarantee — a plugin storing an image ID under a key that doesn't
 * happen to contain one of those words was invisible to it, and that
 * failure mode is the dangerous kind: a real "used" image silently
 * reported as unused.
 *
 * A rule is {key, location, value_shape}. This checker only consumes
 * rules where location === 'postmeta'; 'options' rules are read by
 * OptionsChecker instead — both share the one gmh_custom_rules option.
 *
 * value_shape is one of:
 *  - 'plain'      exact scalar match (meta_value = '42') — covers both
 *                 a numeric and a string-stored ID, since WordPress
 *                 stores both identically as raw text either way.
 *  - 'serialized' the ID as a serialized string OR int element,
 *                 wherever it's nested in the value (e.g. an ACF
 *                 gallery field's array of IDs) — checked as an OR,
 *                 not asked of the user, since which one a given
 *                 plugin used is an implementation detail they can't
 *                 realistically know.
 *
 * Zero configured postmeta rules = this checker is a no-op (returns
 * false immediately) — no default guessing, by design.
 *
 * Excludes `_thumbnail_id` implicitly: nobody would configure a rule
 * for it (FeaturedImageChecker already owns that key), so it's simply
 * never one of the configured keys — no explicit exclusion needed.
 */
class PostMetaChecker implements CheckerInterface {

	/**
	 * Option storing the shared custom-rule list (postmeta + options).
	 * Canonical definition — OptionsChecker and SettingsPage reference
	 * this constant rather than repeating the string.
	 */
	public const CUSTOM_RULES_OPTION = 'gmh_custom_rules';

	/**
	 * Checker identifier used in matched_sources.
	 */
	public function name(): string {
		return 'post_meta';
	}

	/**
	 * Checks wp_postmeta for a meta value referencing this attachment ID,
	 * against user-configured rules only.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		$rules = get_option( self::CUSTOM_RULES_OPTION, array() );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$postmeta_rules = array_values(
			array_filter(
				$rules,
				static function ( $rule ) {
					return is_array( $rule )
						&& 'postmeta' === ( $rule['location'] ?? '' )
						&& '' !== ( $rule['key'] ?? '' );
				}
			)
		);

		// No rules configured for postmeta — nothing to check, and
		// deliberately no fallback guess. This is the whole point of
		// the redesign: silence here, not a guess that might be wrong.
		if ( array() === $postmeta_rules ) {
			return false;
		}

		global $wpdb;

		$id                      = (string) $identifiers['id'];
		$like_serialized_string = '%"' . $wpdb->esc_like( $id ) . '";%';
		$like_serialized_int    = '%:' . $wpdb->esc_like( $id ) . ';%';

		$rule_clauses = array();
		$rule_values  = array();

		foreach ( $postmeta_rules as $rule ) {
			if ( 'serialized' === ( $rule['value_shape'] ?? '' ) ) {
				$rule_clauses[] = '(pm.meta_key = %s AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s))';
				$rule_values[]  = $rule['key'];
				$rule_values[]  = $like_serialized_string;
				$rule_values[]  = $like_serialized_int;
			} else {
				// 'plain' — the only other supported shape.
				$rule_clauses[] = '(pm.meta_key = %s AND pm.meta_value = %s)';
				$rule_values[]  = $rule['key'];
				$rule_values[]  = $id;
			}
		}

		$rule_sql = implode( ' OR ', $rule_clauses );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Reason: replacement count is dynamic (depends on how many rules are
		// configured, and how many placeholders each rule's shape needs) —
		// built at runtime, which phpcs can't evaluate statically. $rule_sql is
		// built entirely from two fixed static templates repeated per rule — no
		// user input is interpolated into the SQL text itself, actual values are
		// bound via $rule_values (including the rule's own "key", i.e. the
		// meta_key an admin configured in Settings, not end-user input). Custom
		// pattern search across wp_postmeta, not covered by the Meta API;
		// caching deferred to the SQL/performance review pass.
		$sql = $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_status NOT IN ('trash', 'auto-draft')
			AND ({$rule_sql})
			LIMIT 1",
			$rule_values
		);

		return null !== $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

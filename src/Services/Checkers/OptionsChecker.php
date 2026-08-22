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
 * Checks wp_options for a reference to the attachment ID — but only
 * against rules the user has explicitly configured (Settings > Custom
 * Rules), never by guessing at option_name names.
 *
 * This replaces the old keyword-gated version (matched any option_name
 * *containing* a word like "image"/"logo"), which was a guess, not a
 * guarantee — an option storing an image ID under a name that doesn't
 * happen to contain one of those words was invisible to it, and that
 * failure mode is the dangerous kind: a real "used" image silently
 * reported as unused.
 *
 * A rule is {key, location, value_shape}. This checker only consumes
 * rules where location === 'options'; 'postmeta' rules are read by
 * PostMetaChecker instead — both share the one gmh_custom_rules option.
 *
 * value_shape is one of:
 *  - 'plain'      exact scalar match (option_value = '42') — covers both
 *                 a numeric and a string-stored ID, since WordPress
 *                 stores both identically as raw text either way.
 *  - 'serialized' the ID as a serialized string OR int element,
 *                 wherever it's nested in the value (e.g. a plugin
 *                 setting storing an array of IDs) — checked as an OR,
 *                 not asked of the user, since which one a given
 *                 plugin used is an implementation detail they can't
 *                 realistically know.
 *
 * Zero configured options rules = this checker is a no-op (returns
 * false immediately) — no default guessing, by design.
 */
class OptionsChecker implements CheckerInterface {

	/**
	 * Checker identifier used in matched_sources.
	 */
	public function name(): string {
		return 'options';
	}

	/**
	 * Checks wp_options for an option value referencing this attachment ID,
	 * against user-configured rules only.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		$rules = get_option( PostMetaChecker::CUSTOM_RULES_OPTION, array() );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$options_rules = array_values(
			array_filter(
				$rules,
				static function ( $rule ) {
					return is_array( $rule )
						&& 'options' === ( $rule['location'] ?? '' )
						&& '' !== ( $rule['key'] ?? '' );
				}
			)
		);

		// No rules configured for options — nothing to check, and
		// deliberately no fallback guess. This is the whole point of
		// the redesign: silence here, not a guess that might be wrong.
		if ( array() === $options_rules ) {
			return false;
		}

		global $wpdb;

		$id                      = (string) $identifiers['id'];
		$like_serialized_string = '%"' . $wpdb->esc_like( $id ) . '";%';
		$like_serialized_int    = '%:' . $wpdb->esc_like( $id ) . ';%';

		$rule_clauses = array();
		$rule_values  = array();

		foreach ( $options_rules as $rule ) {
			if ( 'serialized' === ( $rule['value_shape'] ?? '' ) ) {
				$rule_clauses[] = '(option_name = %s AND (option_value LIKE %s OR option_value LIKE %s))';
				$rule_values[]  = $rule['key'];
				$rule_values[]  = $like_serialized_string;
				$rule_values[]  = $like_serialized_int;
			} else {
				// 'plain' — the only other supported shape.
				$rule_clauses[] = '(option_name = %s AND option_value = %s)';
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
		// option_name an admin configured in Settings, not end-user input).
		// Custom pattern search across wp_options, not covered by the Options
		// API; caching deferred to the SQL/performance review pass.
		$sql = $wpdb->prepare(
			"SELECT option_id FROM {$wpdb->options}
			WHERE ({$rule_sql})
			LIMIT 1",
			$rule_values
		);

		return null !== $wpdb->get_var( $sql );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined('ABSPATH') || exit;

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
 */
class OptionsChecker implements CheckerInterface
{
    private const KEYWORDS = [
        'image', 'logo', 'photo', 'banner', 'thumbnail',
        'icon', 'avatar', 'media', 'background',
    ];

    public function name(): string
    {
        return 'options';
    }

    public function check(?array $identifiers): bool
    {
        if ($identifiers === null) {
            return false;
        }

        global $wpdb;

        $id = (string) $identifiers['id'];

        // Plain scalar option value, e.g. a "site_logo" option storing just the ID
        $exact = $id;

        // Serialized string element, e.g. s:2:"42";
        $like_serialized_string = '%"' . $wpdb->esc_like($id) . '";%';

        // Serialized int element, e.g. i:42;
        $like_serialized_int = '%:' . $wpdb->esc_like($id) . ';%';

        $keyword_clauses = [];
        $keyword_values  = [];
        foreach (self::KEYWORDS as $keyword) {
            $keyword_clauses[] = 'option_name LIKE %s';
            $keyword_values[]  = '%' . $wpdb->esc_like($keyword) . '%';
        }
        $keyword_sql = implode(' OR ', $keyword_clauses);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() below
        $sql = $wpdb->prepare(
            "SELECT option_id FROM {$wpdb->options}
             WHERE ({$keyword_sql})
             AND (
                 option_value = %s
                 OR option_value LIKE %s
                 OR option_value LIKE %s
             )
             LIMIT 1",
            array_merge($keyword_values, [$exact, $like_serialized_string, $like_serialized_int])
        );

        return $wpdb->get_var($sql) !== null;
    }
}
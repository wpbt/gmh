<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks wp_options for a reference to the attachment inside any
 * widget (option_name LIKE 'widget_%'). Widgets store data in two
 * different shapes depending on type, so this matches both:
 *  - classic "Image" widget (widget_media_image) — stores the
 *    attachment ID directly: serialized as "attachment_id";i:{id};
 *  - Text / Custom HTML / block widgets — store raw HTML or block
 *    markup, same content patterns as PostContentChecker (wp-image-{id}
 *    class, relative path, resized filename)
 *
 * The attachment_id pattern is already scoped by its surrounding key
 * name in the serialized string, so no extra keyword filter is needed
 * here the way OptionsChecker/PostMetaChecker needed one.
 */
class WidgetChecker implements CheckerInterface
{
    public function name(): string
    {
        return 'widgets';
    }

    public function check(?array $identifiers): bool
    {
        if ($identifiers === null) {
            return false;
        }

        global $wpdb;

        $like_attachment_id = '%"attachment_id";i:' . $wpdb->esc_like((string) $identifiers['id']) . ';%';
        $like_class         = '%' . $wpdb->esc_like('wp-image-' . $identifiers['id']) . '%';
        $like_path          = '%' . $wpdb->esc_like($identifiers['relative_path']) . '%';
        $like_resized       = '%' . $wpdb->esc_like($identifiers['basename'] . '-') . '%'
            . $wpdb->esc_like('.' . $identifiers['extension']) . '%';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built via $wpdb->prepare() below
        $sql = $wpdb->prepare(
            "SELECT option_id FROM {$wpdb->options}
             WHERE option_name LIKE 'widget\\_%'
             AND (
                 option_value LIKE %s
                 OR option_value LIKE %s
                 OR option_value LIKE %s
                 OR option_value LIKE %s
             )
             LIMIT 1",
            $like_attachment_id,
            $like_class,
            $like_path,
            $like_resized
        );

        return $wpdb->get_var($sql) !== null;
    }
}
<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Interfaces\Registrable;
use GhostMediaHunter\Services\Scan\Engine;

/**
 * Handles the manual "Scan now" button on the admin page. Scans every
 * attachment on the site in one request — fine for now (dev/testing),
 * will need batching or to move behind cron (step 10) for large libraries.
 */
class ScanTrigger implements Registrable
{
    public const ACTION = 'gmh_scan_now';

    private Engine $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer(self::ACTION);

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Not allowed.', 'ghost-media-hunter')], 403);
        }

        $attachment_ids = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ]);

        $this->engine->run_batch($attachment_ids);

        wp_send_json_success([
            'scanned' => count($attachment_ids),
        ]);
    }
}
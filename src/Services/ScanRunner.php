<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Services\Scan\Engine;

/**
 * Runs a full scan across every attachment on the site. Shared by
 * every scan trigger (manual "Scan now" ajax button, WP-Cron,
 * external REST trigger) so "what does a full scan mean" lives in
 * one place instead of being copy-pasted into each trigger.
 *
 * Guards against overlapping runs with a transient-based lock — the
 * scheduling guards elsewhere (wp_next_scheduled etc.) only prevent
 * duplicate *entries in the schedule*, they say nothing about whether
 * a previous run is still executing. Without this, the ajax button,
 * cron, and the REST trigger could all kick off a scan at the same
 * time with nothing stopping them.
 */
class ScanRunner
{
    private const LOCK_KEY = 'gmh_scan_running';

    // Safety net only — this is NOT the normal way the lock gets
    // cleared (that's the try/finally below on every normal exit,
    // success or failure). This only rescues a stuck lock if PHP dies
    // hard enough that even `finally` never runs (a fatal error, the
    // process getting killed mid-scan).
    private const LOCK_TTL = 15 * MINUTE_IN_SECONDS;

    private Engine $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Runs a full scan. Returns the number of attachments scanned, or
     * null if a scan was already in progress and this call was
     * skipped instead of overlapping it.
     */
    public function run_all(): ?int
    {
        if (get_transient(self::LOCK_KEY)) {
            return null;
        }

        set_transient(self::LOCK_KEY, true, self::LOCK_TTL);

        try {
            $attachment_ids = get_posts([
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]);

            $this->engine->run_batch($attachment_ids);

            return count($attachment_ids);
        } finally {
            delete_transient(self::LOCK_KEY);
        }
    }
}
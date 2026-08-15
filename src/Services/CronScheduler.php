<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Hooks the scheduled WP-Cron event to a full scan. The event itself
 * is scheduled on plugin activation (see Activate::run()) and cleared
 * on deactivation (see Deactivate::run()) — this class only wires the
 * hook callback on every request, it doesn't own the scheduling.
 */
class CronScheduler implements Registrable
{
    public const HOOK = 'gmh_scheduled_scan';

    private ScanRunner $runner;

    public function __construct(ScanRunner $runner)
    {
        $this->runner = $runner;
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'handle']);

        // TEMPORARY — testing only, to watch the cron actually fire without
        // waiting a day or reactivating the plugin. Remove this whole block
        // (and this method) once cron behavior is confirmed working.
        add_action('init', [$this, 'schedule_test_run']);
    }

    /**
     * TEMPORARY test helper — schedules a one-off run ~2 minutes out, using
     * a distinct arg so it doesn't collide with or get masked by the real
     * daily schedule (wp_next_scheduled() checks hook+args together, so
     * without this the daily event's existing timestamp would make this
     * check always return true and never actually schedule the test run).
     * Only ever schedules once — safe to leave across multiple pageloads.
     *
     * Must be a numeric-indexed array, not ['test' => true] — WP-Cron
     * passes event args straight into call_user_func_array(), and since
     * PHP 8.0 a string-keyed array there is treated as named arguments.
     * handle() takes no parameters, so a named $test argument fatals with
     * "Unknown named parameter $test". A numeric key is just an ignored
     * positional arg, which is what we actually want.
     */
    public function schedule_test_run(): void
    {
        if (!wp_next_scheduled(self::HOOK, [true])) {
            wp_schedule_single_event(time() + 60, self::HOOK, [true]);
            error_log( 'Scheduling the cron job for handling media scan' );
        }
    }

    public function handle(): void
    {
        $scanned = $this->runner->run_all();

        if ($scanned === null) {
            error_log('Ghost Media Hunter: scheduled scan skipped — a scan was already in progress.');
        }
    }
}
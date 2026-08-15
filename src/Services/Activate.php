<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

class Activate
{
    private const DEFAULT_CHECKER_KEYWORDS = [
        'image', 'logo', 'photo', 'banner', 'thumbnail',
        'icon', 'avatar', 'media', 'background', 'gallery',
    ];

    public static function run(): void {
        Installer::install();

        if (!wp_next_scheduled(CronScheduler::HOOK)) {
            wp_schedule_event(time(), 'daily', CronScheduler::HOOK);
        }

        if (!get_option(ScanRestController::OPTION_KEY)) {
            update_option(ScanRestController::OPTION_KEY, wp_generate_password(32, false, false), false);
        }

        if (!get_option('gmh_checker_keywords')) {
            update_option('gmh_checker_keywords', self::DEFAULT_CHECKER_KEYWORDS, false);
        }
    }
}
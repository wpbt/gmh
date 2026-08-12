<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

class Activate
{
    public static function run(): void {
        Installer::install();
    }
}
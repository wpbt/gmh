<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Interfaces\Registrable;

class AdminMenu implements Registrable
{
    public const SLUG = 'ghost-media-hunter';
    private const PER_PAGE = 20;

    private ScanResultsRepository $repository;

    public function __construct(ScanResultsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu_page']);
    }

    public function add_menu_page(): void
    {
        add_media_page(
            __('Ghost Media Hunter', 'ghost-media-hunter'),
            __('Ghost Media Hunter', 'ghost-media-hunter'),
            'manage_options',
            self::SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $data = [
            'title'    => get_admin_page_title(),
            'results'  => $this->repository->get_unused(self::PER_PAGE, $page),
            'total'    => $this->repository->count_unused(),
            'page'     => $page,
            'per_page' => self::PER_PAGE,
        ];

        include GHOST_MEDIA_HUNTER_PATH . 'views/admin-menu.php';
    }
}
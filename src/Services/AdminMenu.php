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
        $view = (isset($_GET['view']) && $_GET['view'] === 'kept') ? 'kept' : 'unused';

        $data = [
            'title'          => __('Ghost Media Hunter', 'ghost-media-hunter'),
            'view'           => $view,
            'page'           => $page,
            'per_page'       => self::PER_PAGE,
            'unused_total'   => $this->repository->count_unused(),
            'kept_total'     => $this->repository->count_kept(),
        ];

        $data['results'] = $view === 'kept'
            ? $this->repository->get_kept(self::PER_PAGE, $page)
            : $this->repository->get_unused(self::PER_PAGE, $page);

        $data['total'] = $view === 'kept' ? $data['kept_total'] : $data['unused_total'];

        include GHOST_MEDIA_HUNTER_PATH . 'views/admin-menu.php';
    }
}
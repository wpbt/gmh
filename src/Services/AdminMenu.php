<?php
/**
 * Class responsible for the admin results-list page for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Admin page (Media > Ghost Media Hunter) listing scan results — an
 * "Unused" tab and a "Kept" tab, both paginated.
 */
class AdminMenu implements Registrable {

	public const SLUG      = 'ghost-media-hunter';
	private const PER_PAGE = 20;

	/**
	 * Repository for reading scan results.
	 *
	 * @var ScanResultsRepository
	 */
	private ScanResultsRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param ScanResultsRepository $repository Repository for reading scan results.
	 */
	public function __construct( ScanResultsRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers the admin_menu hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	/**
	 * Adds the "Ghost Media Hunter" submenu page under Media.
	 */
	public function add_menu_page(): void {
		add_media_page(
			__( 'Ghost Media Hunter', 'ghost-media-hunter' ),
			__( 'Ghost Media Hunter', 'ghost-media-hunter' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the results-list page: reads the current page/view from
	 * the query string, pulls the matching data, and includes the view.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination/tab query args, not a form submission; no state change to protect with a nonce.
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination/tab query args, not a form submission; no state change to protect with a nonce.
		$requested_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';
		$view           = in_array( $requested_view, array( 'kept', 'review' ), true ) ? $requested_view : 'unused';

		$data = array(
			'title'              => __( 'Ghost Media Hunter', 'ghost-media-hunter' ),
			'view'               => $view,
			'page'               => $page,
			'per_page'           => self::PER_PAGE,
			'unused_total'       => $this->repository->count_unused(),
			'kept_total'         => $this->repository->count_kept(),
			'needs_review_total' => $this->repository->count_needs_review(),
		);

		if ( 'kept' === $view ) {
			$data['results'] = $this->repository->get_kept( self::PER_PAGE, $page );
			$data['total']   = $data['kept_total'];
		} elseif ( 'review' === $view ) {
			$data['results'] = $this->repository->get_needs_review( self::PER_PAGE, $page );
			$data['total']   = $data['needs_review_total'];
		} else {
			$data['results'] = $this->repository->get_unused( self::PER_PAGE, $page );
			$data['total']   = $data['unused_total'];
		}

		require_once GHOST_MEDIA_HUNTER_PATH . 'views/admin-menu.php';
	}
}

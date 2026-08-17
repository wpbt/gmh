<?php
/**
 * Class responsible for the external REST scan-trigger endpoint for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\Registrable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoint for triggering a scan from outside the site — an
 * external scheduler pinging on a fixed interval, independent of
 * whether anyone visits wp-admin (this is what makes triggering
 * reliable, unlike WP-Cron alone).
 *
 * Auth is a shared secret sent via the X-GMH-Key header, checked
 * against the `gmh_scan_key` option (viewable/regeneratable at
 * Media > GMH Settings, generated on activation — see
 * Activate::run()).
 */
class ScanRestController implements Registrable {

	public const OPTION_KEY = 'gmh_scan_key';

	private const NAMESPACE = 'ghost-media-hunter/v1';
	private const ROUTE     = '/scan';

	/**
	 * Shared runner used by the ajax/cron/REST entry points.
	 *
	 * @var ScanRunner
	 */
	private ScanRunner $runner;

	/**
	 * Constructor.
	 *
	 * @param ScanRunner $runner Shared scan runner.
	 */
	public function __construct( ScanRunner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Registers the rest_api_init hook.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Registers the POST /scan REST route.
	 */
	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authorize' ),
			)
		);
	}

	/**
	 * Verifies the X-GMH-Key header against the configured scan key.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return true|WP_Error
	 */
	public function authorize( WP_REST_Request $request ) {
		$key = get_option( self::OPTION_KEY );

		if ( empty( $key ) ) {
			return new WP_Error(
				'gmh_no_key',
				__( 'Scan key not configured.', 'ghost-media-hunter' ),
				array( 'status' => 500 )
			);
		}

		$provided = $request->get_header( 'X-GMH-Key' );

		if ( empty( $provided ) || ! hash_equals( (string) $key, (string) $provided ) ) {
			return new WP_Error(
				'gmh_unauthorized',
				__( 'Invalid or missing key.', 'ghost-media-hunter' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handles the REST scan request.
	 *
	 * @param WP_REST_Request $request The incoming REST request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$scanned = $this->runner->run_all();

		if ( null === $scanned ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'A scan is already in progress.', 'ghost-media-hunter' ),
				),
				409
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'scanned' => $scanned,
			),
			200
		);
	}
}

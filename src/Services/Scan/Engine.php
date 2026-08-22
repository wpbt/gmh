<?php
/**
 * Class responsible for orchestrating a scan batch for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services\Scan;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;
use GhostMediaHunter\Services\IdentifierResolver;
use GhostMediaHunter\Services\ScanResultsRepository;

/**
 * Orchestrates one scan batch: resolve identifiers for each attachment,
 * run it through every registered checker, save the outcome. Doesn't
 * know or care about cron/scheduling — just runs whatever batch it's given.
 */
class Engine {

	/**
	 * Resolves identifiers for a given attachment.
	 *
	 * @var IdentifierResolver
	 */
	private IdentifierResolver $resolver;

	/**
	 * Repository for saving scan results.
	 *
	 * @var ScanResultsRepository
	 */
	private ScanResultsRepository $repository;

	/**
	 * Registered checkers run against every attachment.
	 *
	 * @var array<int, CheckerInterface>
	 */
	private array $checkers;

	/**
	 * Constructor.
	 *
	 * @param IdentifierResolver           $resolver   Resolves identifiers for a given attachment.
	 * @param ScanResultsRepository        $repository Repository for saving scan results.
	 * @param array<int, CheckerInterface> $checkers   Registered checkers to run against every attachment.
	 */
	public function __construct( IdentifierResolver $resolver, ScanResultsRepository $repository, array $checkers ) {
		$this->resolver   = $resolver;
		$this->repository = $repository;
		$this->checkers   = $checkers;
	}

	/**
	 * Scan a batch of attachments (by ID) and save each result.
	 * Caller (cron handler, ajax handler, WP-CLI, etc.) decides how
	 * attachment IDs are fetched and how batches are chunked.
	 *
	 * @param array<int, int> $attachment_ids Attachment post IDs to scan.
	 */
	public function run_batch( array $attachment_ids ): void {
		foreach ( $attachment_ids as $attachment_id ) {
			$this->scan_one( (int) $attachment_id );
		}
	}

	/**
	 * Resolves identifiers for one attachment, runs it through every
	 * checker, and saves the result.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	private function scan_one( int $attachment_id ): void {
		$identifiers = $this->resolver->resolve( $attachment_id );

		if ( null === $identifiers ) {
			// Attachment no longer resolves (deleted mid-scan, etc.) —
			// nothing meaningful to save, skip it.
			return;
		}

		$matched_sources = $this->matched_sources( $identifiers );
		$status          = $this->resolve_status( $matched_sources );

		$this->repository->save_result(
			$attachment_id,
			$status,
			$matched_sources,
			$identifiers['file_size']
		);
	}

	/**
	 * Decides the result status from which checkers matched. A match from
	 * post_content or featured_image is a direct, guaranteed reference —
	 * status 'used'. A match from post_meta/options only reflects a rule
	 * the user configured themselves, not a guarantee WordPress is really
	 * using it — status 'needs_review' instead, so a human glances at it
	 * rather than it being silently trusted as confirmed-used.
	 *
	 * @param array<int, string> $matched_sources Names of checkers that matched.
	 */
	private function resolve_status( array $matched_sources ): string {
		if ( array() === $matched_sources ) {
			return 'unused';
		}

		$guaranteed = array( 'post_content', 'featured_image' );

		if ( array() !== array_intersect( $guaranteed, $matched_sources ) ) {
			return 'used';
		}

		return 'needs_review';
	}

	/**
	 * Runs every registered checker against one attachment's identifiers.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int} $identifiers Identifiers resolved for the attachment.
	 * @return array<int, string> Names of every checker that found a match.
	 */
	private function matched_sources( array $identifiers ): array {
		$matched = array();

		foreach ( $this->checkers as $checker ) {
			$res = $checker->check( $identifiers );

			if ( $res ) {
				$matched[] = $checker->name();
			}
		}

		return $matched;
	}
}

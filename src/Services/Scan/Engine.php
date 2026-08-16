<?php

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

	private IdentifierResolver $resolver;
	private ScanResultsRepository $repository;

	/** @var array<int, CheckerInterface> */
	private array $checkers;

	/**
	 * @param array<int, CheckerInterface> $checkers
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
	 * @param array<int, int> $attachment_ids
	 */
	public function run_batch( array $attachment_ids ): void {
		foreach ( $attachment_ids as $attachment_id ) {
			$this->scan_one( (int) $attachment_id );
		}
	}

	private function scan_one( int $attachment_id ): void {
		$identifiers = $this->resolver->resolve( $attachment_id );

		if ( $identifiers === null ) {
			// Attachment no longer resolves (deleted mid-scan, etc.) —
			// nothing meaningful to save, skip it.
			return;
		}

		$matched_sources = $this->matched_sources( $identifiers );
		$status          = $matched_sources === array() ? 'unused' : 'used';

		$this->repository->save_result(
			$attachment_id,
			$status,
			$matched_sources,
			$identifiers['file_size']
		);
	}

	/**
	 * @param array{
	 *     id: int,
	 *     relative_path: string,
	 *     filename: string,
	 *     basename: string,
	 *     extension: string,
	 *     file_size: int
	 * } $identifiers
	 * @return array<int, string> names of every checker that found a match
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

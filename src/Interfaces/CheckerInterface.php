<?php
/**
 * Interface for checkers that determine whether an attachment is referenced somewhere, for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Interfaces;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

interface CheckerInterface {

	/**
	 * Short, stable slug identifying this checker's source,
	 * e.g. 'post_content'. Recorded in matched_sources when this
	 * checker finds a reference, so results stay debuggable.
	 */
	public function name(): string;

	/**
	 * Whether the attachment described by $identifiers is referenced
	 * anywhere within this checker's source. $identifiers is null when
	 * IdentifierResolver couldn't resolve the attachment (e.g. it no
	 * longer exists) — checkers must treat that as "not found", not error.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers The array returned by IdentifierResolver::resolve().
	 */
	public function check( ?array $identifiers ): bool;
}

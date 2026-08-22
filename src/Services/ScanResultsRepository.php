<?php
/**
 * Class responsible for reading and writing scan results for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

/**
 * Owns all reads/writes to the {prefix}_gmh_scan_results table.
 * Nothing else in the plugin should write raw SQL against it.
 */
class ScanResultsRepository {

	/**
	 * Name of the results table (with prefix).
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->table = Installer::table_info()['name'];
	}

	/**
	 * Save the outcome of scanning one attachment. Upserts by
	 * attachment_id — one row per attachment, created_at is only
	 * set the first time, last_checked updates every scan.
	 *
	 * @param int                $attachment_id   Attachment post ID.
	 * @param string             $status          Scan status, e.g. 'used' or 'unused'.
	 * @param array<int, string> $matched_sources Which checkers matched, e.g. ['post_content', 'featured_image'].
	 * @param int                $file_size       File size in bytes.
	 */
	public function save_result( int $attachment_id, string $status, array $matched_sources = array(), int $file_size = 0 ): void {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$data = array(
			'status'          => $status,
			'matched_sources' => wp_json_encode( $matched_sources ),
			'file_size'       => $file_size,
			'last_checked'    => $now,
		);

		$existing_id = $this->find_row_id( $attachment_id );

		if ( null !== $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
			$wpdb->update(
				$this->table,
				$data,
				array( 'id' => $existing_id ),
				array( '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
			return;
		}

		$data['attachment_id'] = $attachment_id;
		$data['created_at']    = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		$wpdb->insert(
			$this->table,
			$data,
			array( '%s', '%s', '%d', '%s', '%d', '%s' )
		);
	}

	/**
	 * Whitelist/un-whitelist an attachment so it's excluded from
	 * the "unused" results regardless of scan status.
	 *
	 * @param int  $attachment_id Attachment post ID.
	 * @param bool $ignored       True to mark ignored (kept), false to restore.
	 */
	public function mark_ignored( int $attachment_id, bool $ignored = true ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		$wpdb->update(
			$this->table,
			array( 'ignored' => $ignored ? 1 : 0 ),
			array( 'attachment_id' => $attachment_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Remove a result row entirely — used when the attachment itself
	 * has been deleted, so a stale row doesn't linger pointing at an
	 * attachment_id that no longer exists.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	public function delete_result( int $attachment_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		$wpdb->delete(
			$this->table,
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);
	}

	/**
	 * Paginated list of attachments currently flagged unused
	 * (and not manually whitelisted), most recently checked first.
	 *
	 * @param int $per_page Results per page.
	 * @param int $page     Page number (1-indexed).
	 * @return array<int, object>
	 */
	public function get_unused( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$offset = max( 0, ( $page - 1 ) * $per_page );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
				"SELECT * FROM {$this->table} WHERE status = %s AND ignored = 0 ORDER BY last_checked DESC LIMIT %d OFFSET %d",
				'unused',
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count of attachments currently flagged unused (and not ignored).
	 */
	public function count_unused(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s AND ignored = 0",
				'unused'
			)
		);
	}

	/**
	 * Paginated list of attachments manually marked "Keep" (ignored),
	 * regardless of scan status — this is the only way back to see
	 * (and un-keep, or delete) something after clicking Keep, since
	 * get_unused() excludes ignored rows entirely.
	 *
	 * @param int $per_page Results per page.
	 * @param int $page     Page number (1-indexed).
	 * @return array<int, object>
	 */
	public function get_kept( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$offset = max( 0, ( $page - 1 ) * $per_page );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
				"SELECT * FROM {$this->table} WHERE ignored = 1 ORDER BY last_checked DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count of attachments manually marked "Keep" (ignored).
	 */
	public function count_kept(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
			"SELECT COUNT(*) FROM {$this->table} WHERE ignored = 1"
		);
	}

	/**
	 * Internal: find the row id for an attachment, if a scan
	 * result already exists for it. Null if this is the first scan.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	private function find_row_id( int $attachment_id ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		$id = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
				"SELECT id FROM {$this->table} WHERE attachment_id = %d",
				$attachment_id
			)
		);

		return null !== $id ? (int) $id : null;
	}

		/**
	 * Paginated list of attachments flagged 'needs_review' (and not
	 * manually whitelisted) — a match came only from post_meta/options,
	 * not a guaranteed reference, so a human should glance at it.
	 *
	 * @param int $per_page Results per page.
	 * @param int $page     Page number (1-indexed).
	 * @return array<int, object>
	 */
	public function get_needs_review( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$offset = max( 0, ( $page - 1 ) * $per_page );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
				"SELECT * FROM {$this->table} WHERE status = %s AND ignored = 0 ORDER BY last_checked DESC LIMIT %d OFFSET %d",
				'needs_review',
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Count of attachments currently flagged 'needs_review' (and not ignored).
	 */
	public function count_needs_review(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, not covered by WP_Query; caching deferred to the SQL/performance review pass.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s AND ignored = 0",
				'needs_review'
			)
		);
	}
}

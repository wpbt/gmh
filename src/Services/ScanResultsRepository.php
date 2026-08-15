<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

/**
 * Owns all reads/writes to the {prefix}_gmh_scan_results table.
 * Nothing else in the plugin should write raw SQL against it.
 */
class ScanResultsRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = Installer::table_info()['name'];
    }

    /**
     * Save the outcome of scanning one attachment. Upserts by
     * attachment_id — one row per attachment, created_at is only
     * set the first time, last_checked updates every scan.
     *
     * @param array<int, string> $matched_sources e.g. ['post_content', 'featured_image']
     */
    public function save_result(int $attachment_id, string $status, array $matched_sources = [], int $file_size = 0): void
    {
        global $wpdb;

        $now  = current_time('mysql');
        $data = [
            'status'          => $status,
            'matched_sources' => wp_json_encode($matched_sources),
            'file_size'       => $file_size,
            'last_checked'    => $now,
        ];

        $existing_id = $this->find_row_id($attachment_id);

        if ($existing_id !== null) {
            $wpdb->update(
                $this->table,
                $data,
                ['id' => $existing_id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
            return;
        }

        $data['attachment_id'] = $attachment_id;
        $data['created_at']    = $now;

        $wpdb->insert(
            $this->table,
            $data,
            ['%s', '%s', '%d', '%s', '%d', '%s']
        );
    }

    /**
     * Whitelist/un-whitelist an attachment so it's excluded from
     * the "unused" results regardless of scan status.
     */
    public function mark_ignored(int $attachment_id, bool $ignored = true): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table,
            ['ignored' => $ignored ? 1 : 0],
            ['attachment_id' => $attachment_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Remove a result row entirely — used when the attachment itself
     * has been deleted, so a stale row doesn't linger pointing at an
     * attachment_id that no longer exists.
     */
    public function delete_result(int $attachment_id): void
    {
        global $wpdb;

        $wpdb->delete(
            $this->table,
            ['attachment_id' => $attachment_id],
            ['%d']
        );
    }

    /**
     * Paginated list of attachments currently flagged unused
     * (and not manually whitelisted), most recently checked first.
     *
     * @return array<int, object>
     */
    public function get_unused(int $per_page = 20, int $page = 1): array
    {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE status = %s AND ignored = 0
             ORDER BY last_checked DESC
             LIMIT %d OFFSET %d",
            'unused',
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    public function count_unused(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = %s AND ignored = 0",
            'unused'
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Paginated list of attachments manually marked "Keep" (ignored),
     * regardless of scan status — this is the only way back to see
     * (and un-keep, or delete) something after clicking Keep, since
     * get_unused() excludes ignored rows entirely.
     *
     * @return array<int, object>
     */
    public function get_kept(int $per_page = 20, int $page = 1): array
    {
        global $wpdb;

        $offset = max(0, ($page - 1) * $per_page);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE ignored = 1
             ORDER BY last_checked DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    public function count_kept(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE ignored = 1");
    }

    /**
     * Internal: find the row id for an attachment, if a scan
     * result already exists for it. Null if this is the first scan.
     */
    private function find_row_id(int $attachment_id): ?int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table is not user input
        $sql = $wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE attachment_id = %d",
            $attachment_id
        );

        $id = $wpdb->get_var($sql);

        return $id !== null ? (int) $id : null;
    }
}
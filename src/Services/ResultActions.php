<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Per-row "Keep", "Restore" and "Delete" actions on the results table.
 * Keep whitelists an attachment (mark_ignored) so it's excluded from
 * future "unused" results without touching the file. Restore undoes
 * that — Keep isn't a one-way door, the Kept view (AdminMenu's
 * view=kept) is where you'd find something to restore. Delete
 * actually removes the attachment via wp_delete_attachment().
 *
 * Important: wp_delete_attachment() only moves to trash if the site
 * has MEDIA_TRASH enabled (most sites don't — trashing media isn't a
 * WordPress default). Otherwise this is a PERMANENT delete. This
 * class doesn't force MEDIA_TRASH on — that's a site-wide behavior
 * change this plugin shouldn't silently impose. The confirmation
 * dialog in admin-menu.php's JS is what actually warns the user.
 */
class ResultActions implements Registrable
{
    public const ACTION_KEEP    = 'gmh_mark_kept';
    public const ACTION_RESTORE = 'gmh_restore_kept';
    public const ACTION_DELETE  = 'gmh_delete_attachment';

    private ScanResultsRepository $repository;

    public function __construct(ScanResultsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_KEEP, [$this, 'handle_keep']);
        add_action('wp_ajax_' . self::ACTION_RESTORE, [$this, 'handle_restore']);
        add_action('wp_ajax_' . self::ACTION_DELETE, [$this, 'handle_delete']);
    }

    public function handle_keep(): void
    {
        $attachment_id = $this->authorize_and_get_id(self::ACTION_KEEP);

        $this->repository->mark_ignored($attachment_id, true);

        wp_send_json_success(['attachment_id' => $attachment_id]);
    }

    public function handle_restore(): void
    {
        $attachment_id = $this->authorize_and_get_id(self::ACTION_RESTORE);

        $this->repository->mark_ignored($attachment_id, false);

        wp_send_json_success(['attachment_id' => $attachment_id]);
    }

    public function handle_delete(): void
    {
        $attachment_id = $this->authorize_and_get_id(self::ACTION_DELETE);

        $deleted = wp_delete_attachment($attachment_id, false);

        if (!$deleted) {
            wp_send_json_error(
                ['message' => __('Could not delete that attachment.', 'ghost-media-hunter')],
                500
            );
        }

        $this->repository->delete_result($attachment_id);

        wp_send_json_success(['attachment_id' => $attachment_id]);
    }

    /**
     * Shared nonce + capability + input check for both actions.
     * Ends the request (via check_ajax_referer / wp_send_json_error)
     * on failure, so a normal return only happens once everything's
     * valid.
     */
    private function authorize_and_get_id(string $action): int
    {
        check_ajax_referer($action);

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Not allowed.', 'ghost-media-hunter')], 403);
        }

        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;

        if ($attachment_id <= 0) {
            wp_send_json_error(['message' => __('Missing attachment id.', 'ghost-media-hunter')], 400);
        }

        return $attachment_id;
    }
}
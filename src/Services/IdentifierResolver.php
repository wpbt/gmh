<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined('ABSPATH') || exit;

/**
 * Resolves the identifiers a checker needs to search content for one
 * attachment: its ID, a relative upload path, and filename parts.
 * Every checker builds its matching against these instead of each
 * guessing at URL/filename logic independently.
 */
class IdentifierResolver
{
    /**
     * @return array{
     *     id: int,
     *     relative_path: string,
     *     filename: string,
     *     basename: string,
     *     extension: string,
     *     file_size: int
     * }|null Null if this isn't a valid, existing attachment.
     */
    public function resolve(int $attachment_id): ?array
    {
        $url = wp_get_attachment_url($attachment_id);

        if ($url === false) {
            return null;
        }

        $filename = wp_basename($url);

        return [
            'id'            => $attachment_id,
            'relative_path' => $this->to_relative_path($url),
            'filename'      => $filename,
            'basename'      => pathinfo($filename, PATHINFO_FILENAME),
            'extension'     => pathinfo($filename, PATHINFO_EXTENSION),
            'file_size'     => $this->file_size($attachment_id),
        ];
    }

    /**
     * Bytes on disk for the original file. Not used for matching —
     * this is metadata for later reporting (e.g. space reclaimed
     * over time) — kept separate from the identifier fields above.
     */
    private function file_size(int $attachment_id): int
    {
        $path = get_attached_file($attachment_id);

        if ($path === false || !file_exists($path)) {
            return 0;
        }

        return wp_filesize($path);
    }

    /**
     * Strip the site's uploads base URL (scheme + host + /wp-content/uploads)
     * so matching is resilient to domain changes, http->https migrations,
     * and CDN URL rewriting — checkers match on path only, not full URL.
     */
    private function to_relative_path(string $url): string
    {
        $upload_dir = wp_get_upload_dir();
        $base_url   = $upload_dir['baseurl'] ?? '';

        if ($base_url !== '' && str_starts_with($url, $base_url)) {
            return ltrim(substr($url, strlen($base_url)), '/');
        }

        // Fallback for unexpected setups: strip scheme + host, keep the path.
        $path = wp_parse_url($url, PHP_URL_PATH);

        return $path !== null ? ltrim($path, '/') : $url;
    }
}
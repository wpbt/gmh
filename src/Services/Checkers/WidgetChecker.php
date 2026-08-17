<?php
/**
 * Class responsible for checking widget options for attachment references for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services\Checkers;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\CheckerInterface;

/**
 * Checks wp_options for a reference to the attachment inside any
 * widget (option_name LIKE 'widget_%'). Widgets store data in two
 * different shapes depending on type, so this matches both:
 *  - classic "Image" widget (widget_media_image) — stores the
 *    attachment ID directly: serialized as "attachment_id";i:{id};
 *  - Text / Custom HTML / block widgets — store raw HTML or block
 *    markup, same content patterns as PostContentChecker (wp-image-{id}
 *    class, relative path, resized filename)
 *
 * The attachment_id pattern is already scoped by its surrounding key
 * name in the serialized string, so no extra keyword filter is needed
 * here the way OptionsChecker/PostMetaChecker needed one.
 */
class WidgetChecker implements CheckerInterface {

	/**
	 * Checker identifier used in matched_sources.
	 */
	public function name(): string {
		return 'widgets';
	}

	/**
	 * Checks wp_options for a widget referencing this attachment.
	 *
	 * @param array{id: int, relative_path: string, filename: string, basename: string, extension: string, file_size: int}|null $identifiers Identifiers for the attachment, or null.
	 */
	public function check( ?array $identifiers ): bool {
		if ( null === $identifiers ) {
			return false;
		}

		global $wpdb;

		$like_attachment_id = '%"attachment_id";i:' . $wpdb->esc_like( (string) $identifiers['id'] ) . ';%';
		$like_class         = '%' . $wpdb->esc_like( 'wp-image-' . $identifiers['id'] ) . '%';
		$like_path          = '%' . $wpdb->esc_like( $identifiers['relative_path'] ) . '%';
		$like_resized       = '%' . $wpdb->esc_like( $identifiers['basename'] . '-' ) . '%'
			. $wpdb->esc_like( '.' . $identifiers['extension'] ) . '%';
		$like_option_name   = $wpdb->esc_like( 'widget_' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom pattern search across wp_options, not covered by the Options API; caching deferred to the SQL/performance review pass.
		return null !== $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s AND ( option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s ) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->options is WordPress core, not user input
				$like_option_name,
				$like_attachment_id,
				$like_class,
				$like_path,
				$like_resized
			)
		);
	}
}

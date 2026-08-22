<?php
/**
 * Class responsible for registering settings page for ghost media hunter plugin.
 *
 * @package GhostMediaHunter
 */

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\Registrable;
use GhostMediaHunter\Services\Checkers\PostContentChecker;
use GhostMediaHunter\Services\Checkers\PostMetaChecker;

/**
 * Settings page (Media > GMH Settings). Sections: the REST scan key
 * (view + regenerate), user-configured Custom Rules consumed by
 * PostMetaChecker/OptionsChecker, and Post Content Scanning (revision
 * handling). Cron interval could be a section later.
 */
class SettingsPage implements Registrable {

	public const SLUG                  = 'ghost-media-hunter-settings';
	public const OPTION_GROUP          = 'gmh_settings';
	private const REST_SECTION         = 'gmh_rest_section';
	private const CHECKER_SECTION      = 'gmh_checker_section';
	private const POST_CONTENT_SECTION = 'gmh_post_content_section';

	/**
	 * Allowed values for a rule's "location" field — kept in sync with
	 * what PostMetaChecker/OptionsChecker actually consume.
	 */
	private const RULE_LOCATIONS = array( 'postmeta', 'options' );

	/**
	 * Allowed values for a rule's "value_shape" field — kept in sync
	 * with what PostMetaChecker/OptionsChecker actually handle.
	 */
	private const RULE_VALUE_SHAPES = array( 'plain', 'serialized' );

	/**
	 * Registers the admin_menu and admin_init hooks for this service.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Adds the "GMH Settings" submenu page under Media.
	 */
	public function add_menu_page(): void {
		add_media_page(
			__( 'Ghost Media Hunter Settings', 'ghost-media-hunter' ),
			__( 'GMH Settings', 'ghost-media-hunter' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers settings, sections, and fields for the Settings API.
	 */
	public function register_settings(): void {

		// Sections
		add_settings_section(
			self::POST_CONTENT_SECTION,
			__( 'Post Content Scanning', 'ghost-media-hunter' ),
			array( $this, 'render_post_content_section_intro' ),
			self::SLUG
		);

		add_settings_section(
			self::CHECKER_SECTION,
			__( 'Custom Rules', 'ghost-media-hunter' ),
			array( $this, 'render_checker_section_intro' ),
			self::SLUG
		);

		add_settings_section(
			self::REST_SECTION,
			__( 'External Trigger', 'ghost-media-hunter' ),
			array( $this, 'render_rest_section_intro' ),
			self::SLUG
		);

		// settings
		register_setting(
			self::OPTION_GROUP,
			PostContentChecker::INCLUDE_REVISIONS_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			PostMetaChecker::CUSTOM_RULES_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_custom_rules' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			ScanRestController::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_scan_key' ),
				'default'           => '',
			)
		);

		// Setting fields
		add_settings_field(
			PostContentChecker::INCLUDE_REVISIONS_OPTION,
			__( 'Include revisions', 'ghost-media-hunter' ),
			array( $this, 'render_include_revisions_field' ),
			self::SLUG,
			self::POST_CONTENT_SECTION
		);

		add_settings_field(
			PostMetaChecker::CUSTOM_RULES_OPTION,
			__( 'Rules', 'ghost-media-hunter' ),
			array( $this, 'render_custom_rules_field' ),
			self::SLUG,
			self::CHECKER_SECTION
		);

		add_settings_field(
			ScanRestController::OPTION_KEY,
			__( 'Scan key', 'ghost-media-hunter' ),
			array( $this, 'render_scan_key_field' ),
			self::SLUG,
			self::REST_SECTION
		);
	}

	/**
	 * Renders the intro text for the "External Trigger" section.
	 */
	public function render_rest_section_intro(): void {
		esc_html_e(
			'An external scheduler can trigger a scan reliably (independent of site traffic) by sending a POST request with this key.',
			'ghost-media-hunter'
		);
	}

	/**
	 * Renders the REST scan key field, with a regenerate checkbox.
	 */
	public function render_scan_key_field(): void {
		$key = (string) get_option( ScanRestController::OPTION_KEY, '' );
		?>
		<input
			type="text"
			readonly
			class="regular-text code"
			name="<?php echo esc_attr( ScanRestController::OPTION_KEY ); ?>"
			value="<?php echo esc_attr( $key ); ?>"
			onclick="this.select();"
		/>
		<p>
			<label>
				<input type="checkbox" name="gmh_scan_key_regenerate" value="1" />
				<?php esc_html_e( 'Regenerate a new key on save (the old key stops working immediately)', 'ghost-media-hunter' ); ?>
			</label>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: 1: HTTP method, 2: REST endpoint URL, 3: header name */
				esc_html__( '%1$s %2$s with this key in the %3$s header to trigger a scan.', 'ghost-media-hunter' ),
				'<code>POST</code>',
				'<code>' . esc_html( rest_url( 'ghost-media-hunter/v1/scan' ) ) . '</code>',
				'<code>X-GMH-Key</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitizes the scan key setting, regenerating it if requested.
	 *
	 * @param string $value Submitted value (ignored if regenerating).
	 * @return string
	 */
	public function sanitize_scan_key( string $value ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified upstream by the Settings API (options.php) before sanitize callbacks run.
		if ( ! empty( $_POST['gmh_scan_key_regenerate'] ) ) {
			return wp_generate_password( 32, false, false );
		}

		return $value;
	}

	/**
	 * Renders the intro text for the "Custom Rules" section.
	 */
	public function render_checker_section_intro(): void {
		esc_html_e(
			'Post meta and option values are only checked against attachment IDs for rules you configure below - there is no automatic guessing. A field with no rule configured for it is simply never checked.',
			'ghost-media-hunter'
		);
	}

	/**
	 * Renders the repeatable custom-rules field: one row per configured
	 * rule (key / location / value shape), plus a hidden template row
	 * (index placeholder __INDEX__) that gmh.js clones when "Add rule"
	 * is clicked. Row indices don't need to stay contiguous — PHP
	 * parses whatever index values arrive in $_POST regardless of gaps.
	 */
	public function render_custom_rules_field(): void {
		$rules = get_option( PostMetaChecker::CUSTOM_RULES_OPTION, array() );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$rules = array_values( $rules );
		?>
		<table class="widefat gmh-custom-rules" id="gmh-custom-rules">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Meta key to check', 'ghost-media-hunter' ); ?></th>
					<th><?php esc_html_e( 'Key lives in', 'ghost-media-hunter' ); ?></th>
					<th><?php esc_html_e( 'Data structure', 'ghost-media-hunter' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="gmh-custom-rules-body">
				<?php foreach ( $rules as $index => $rule ) : ?>
					<?php $this->render_custom_rule_row( (string) $index, is_array( $rule ) ? $rule : array() ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<template id="gmh-custom-rule-template">
			<?php $this->render_custom_rule_row( '__INDEX__', array() ); ?>
		</template>

		<p>
			<button type="button" class="button" id="gmh-add-custom-rule">
				<?php esc_html_e( '+ Add rule', 'ghost-media-hunter' ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * Renders one custom-rule row's <tr> markup. Shared between the
	 * server-rendered existing rows and the hidden <template> row
	 * gmh.js clones — $index is either a real array index (string) or
	 * the literal placeholder "__INDEX__" for the template.
	 *
	 * @param string              $index Row index (or "__INDEX__" for the template row).
	 * @param array<string,mixed> $rule  Rule data for this row (empty array for a blank row).
	 */
	private function render_custom_rule_row( string $index, array $rule ): void {
		$key         = (string) ( $rule['key'] ?? '' );
		$location    = (string) ( $rule['location'] ?? 'postmeta' );
		$value_shape = (string) ( $rule['value_shape'] ?? 'plain' );
		$option      = PostMetaChecker::CUSTOM_RULES_OPTION;
		?>
		<tr class="gmh-custom-rule-row">
			<td>
				<input
					type="text"
					class="regular-text"
					name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $index ); ?>][key]"
					value="<?php echo esc_attr( $key ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. hero_image_id', 'ghost-media-hunter' ); ?>"
				/>
			</td>
			<td>
				<select name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $index ); ?>][location]">
					<option value="postmeta" <?php selected( 'postmeta', $location ); ?>><?php esc_html_e( 'Post Meta', 'ghost-media-hunter' ); ?></option>
					<option value="options" <?php selected( 'options', $location ); ?>><?php esc_html_e( 'Options', 'ghost-media-hunter' ); ?></option>
				</select>
			</td>
			<td>
				<select name="<?php echo esc_attr( $option ); ?>[<?php echo esc_attr( $index ); ?>][value_shape]">
					<option value="plain" <?php selected( 'plain', $value_shape ); ?>><?php esc_html_e( 'Plain value (a number or ID string)', 'ghost-media-hunter' ); ?></option>
					<option value="serialized" <?php selected( 'serialized', $value_shape ); ?>><?php esc_html_e( 'Serialized (nested inside an array/object)', 'ghost-media-hunter' ); ?></option>
				</select>
			</td>
			<td>
				<button type="button" class="gmh-remove-custom-rule" title="<?php esc_attr_e( 'Remove rule', 'ghost-media-hunter' ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Remove rule', 'ghost-media-hunter' ); ?></span>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the intro text for the "Post Content Scanning" section.
	 */
	public function render_post_content_section_intro(): void {
		esc_html_e(
			'Controls how the "post_content" checker treats WordPress revisions.',
			'ghost-media-hunter'
		);
	}

	/**
	 * Renders the include-revisions checkbox field.
	 */
	public function render_include_revisions_field(): void {
		$include_revisions = (bool) get_option( PostContentChecker::INCLUDE_REVISIONS_OPTION, false );
		?>
		<input
			type="hidden"
			name="<?php echo esc_attr( PostContentChecker::INCLUDE_REVISIONS_OPTION ); ?>"
			value="0"
		/>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( PostContentChecker::INCLUDE_REVISIONS_OPTION ); ?>"
				value="1"
				<?php checked( $include_revisions ); ?>
			/>
			<?php esc_html_e( 'Also match images referenced only in old revisions', 'ghost-media-hunter' ); ?>
		</label>
		<p class="description">
			<?php
			esc_html_e(
				'Off by default: an image no longer referenced in the live content is "unused" even if an old revision still mentions it. Turning this on is more conservative — an image stays "used" as long as ANY past revision references it. On sites with a long revision history (or unlimited revisions), this can mean most images never show as unused, even long after removal.',
				'ghost-media-hunter'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitizes the submitted custom-rules array: drops blank rows (no
	 * key entered — e.g. the template row if it somehow got submitted,
	 * or a row the user added but didn't fill in), validates location/
	 * value_shape against the known allowed values (falls back to the
	 * safe default rather than rejecting the whole save on a tampered
	 * or stale value), and re-indexes the array.
	 *
	 * @param mixed $value Raw submitted value — expected to be an array of {key, location, value_shape} sub-arrays keyed by row index.
	 * @return array<int, array{key: string, location: string, value_shape: string}>
	 */
	public function sanitize_custom_rules( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = sanitize_text_field( (string) ( $row['key'] ?? '' ) );

			if ( '' === $key ) {
				continue;
			}

			$location = (string) ( $row['location'] ?? '' );
			if ( ! in_array( $location, self::RULE_LOCATIONS, true ) ) {
				$location = 'postmeta';
			}

			$value_shape = (string) ( $row['value_shape'] ?? '' );
			if ( ! in_array( $value_shape, self::RULE_VALUE_SHAPES, true ) ) {
				$value_shape = 'plain';
			}

			$sanitized[] = array(
				'key'         => $key,
				'location'    => $location,
				'value_shape' => $value_shape,
			);
		}

		return $sanitized;
	}

	/**
	 * Renders the settings page markup.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = array(
			'title'        => __( 'Ghost Media Hunter Settings', 'ghost-media-hunter' ),
			'option_group' => self::OPTION_GROUP,
			'page_slug'    => self::SLUG,
		);

		require_once GHOST_MEDIA_HUNTER_PATH . 'views/settings-page.php';
	}
}

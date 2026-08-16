<?php

declare(strict_types=1);

namespace GhostMediaHunter\Services;

// Exit if accessed directly!
defined( 'ABSPATH' ) || exit;

use GhostMediaHunter\Interfaces\Registrable;

/**
 * Settings page (Media > GMH Settings). Two sections so far: the REST
 * scan key (view + regenerate) and the checker matching keywords used
 * by OptionsChecker/PostMetaChecker. Cron interval could be a third
 * section later.
 */
class SettingsPage implements Registrable {

	public const SLUG             = 'ghost-media-hunter-settings';
	public const OPTION_GROUP     = 'gmh_settings';
	private const REST_SECTION    = 'gmh_rest_section';
	private const CHECKER_SECTION = 'gmh_checker_section';
	private const KEYWORDS_OPTION = 'gmh_checker_keywords';

	// Kept in sync with the same list in Activate::run() and the
	// DEFAULT_KEYWORDS fallback in OptionsChecker/PostMetaChecker.
	private const DEFAULT_KEYWORDS = array(
		'image',
		'logo',
		'photo',
		'banner',
		'thumbnail',
		'icon',
		'avatar',
		'media',
		'background',
		'gallery',
	);

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu_page(): void {
		add_media_page(
			__( 'Ghost Media Hunter Settings', 'ghost-media-hunter' ),
			__( 'GMH Settings', 'ghost-media-hunter' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			ScanRestController::OPTION_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_scan_key' ),
				'default'           => '',
			)
		);

		add_settings_section(
			self::REST_SECTION,
			__( 'External Trigger', 'ghost-media-hunter' ),
			array( $this, 'render_rest_section_intro' ),
			self::SLUG
		);

		add_settings_field(
			ScanRestController::OPTION_KEY,
			__( 'Scan key', 'ghost-media-hunter' ),
			array( $this, 'render_scan_key_field' ),
			self::SLUG,
			self::REST_SECTION
		);

		register_setting(
			self::OPTION_GROUP,
			self::KEYWORDS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_keywords' ),
				'default'           => self::DEFAULT_KEYWORDS,
			)
		);

		add_settings_section(
			self::CHECKER_SECTION,
			__( 'Checker Matching', 'ghost-media-hunter' ),
			array( $this, 'render_checker_section_intro' ),
			self::SLUG
		);

		add_settings_field(
			self::KEYWORDS_OPTION,
			__( 'Match keywords', 'ghost-media-hunter' ),
			array( $this, 'render_keywords_field' ),
			self::SLUG,
			self::CHECKER_SECTION
		);
	}

	public function render_rest_section_intro(): void {
		esc_html_e(
			'An external scheduler can trigger a scan reliably (independent of site traffic) by sending a POST request with this key.',
			'ghost-media-hunter'
		);
	}

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

	public function sanitize_scan_key( string $value ): string {
		if ( ! empty( $_POST['gmh_scan_key_regenerate'] ) ) {
			return wp_generate_password( 32, false, false );
		}

		return $value;
	}

	public function render_checker_section_intro(): void {
		esc_html_e(
			'Post meta and option keys must contain one of these words before their value is checked against an attachment ID — this narrows matching to cut down on false positives (e.g. an unrelated numeric setting coincidentally matching an attachment ID). Used by the "post_meta" and "options" checkers.',
			'ghost-media-hunter'
		);
	}

	public function render_keywords_field(): void {
		$keywords = get_option( self::KEYWORDS_OPTION, self::DEFAULT_KEYWORDS );

		if ( ! is_array( $keywords ) || empty( $keywords ) ) {
			$keywords = self::DEFAULT_KEYWORDS;
		}
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( self::KEYWORDS_OPTION ); ?>"
			value="<?php echo esc_attr( implode( ', ', $keywords ) ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Comma-separated. Matching is case-insensitive and checks whether a key contains each word (not an exact match).', 'ghost-media-hunter' ); ?>
		</p>
		<?php
	}

	/**
	 * @param mixed $value
	 * @return string[]
	 */
	public function sanitize_keywords( $value ): array {
		$parts = explode( ',', (string) $value );
		$parts = array_map( 'trim', $parts );
		$parts = array_map( 'strtolower', $parts );
		$parts = array_filter( $parts, static fn ( $word ) => $word !== '' );
		$parts = array_values( array_unique( $parts ) );

		if ( empty( $parts ) ) {
			// An empty list here would leave OptionsChecker/PostMetaChecker
			// with nothing to filter on, breaking their SQL entirely (see
			// the checkers' own guards for the same underlying issue).
			// Refuse to save empty — fall back to the defaults instead.
			add_settings_error(
				self::KEYWORDS_OPTION,
				'gmh_keywords_empty',
				__( 'Match keywords can\'t be empty — reset to the defaults.', 'ghost-media-hunter' )
			);

			return self::DEFAULT_KEYWORDS;
		}

		return $parts;
	}

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

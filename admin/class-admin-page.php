<?php
/**
 * Admin UI.
 *
 * @package WpCompact
 */

namespace WpCompact;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page and asset enqueue.
 */
class Admin_Page {

	const PAGE_SLUG = 'wp-compact';

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'wp_enqueue_media', array( $this, 'enqueue_upload_on_media' ) );
	}

	/**
	 * Media submenu (batch UI — administrators).
	 */
	public function register_menu() {
		add_media_page(
			__( 'wp-compact', 'wp-compact' ),
			__( 'Compress (wp-compact)', 'wp-compact' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Shared JS config.
	 *
	 * @return array<string, mixed>
	 */
	private function script_config() {
		$settings = Settings::get_all();
		return array(
			'restUrl'   => esc_url_raw( rest_url( Rest::NAMESPACE ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'settings'  => $settings,
			'pluginUrl' => WP_COMPACT_URL,
			'wasmUrl'   => WP_COMPACT_URL . 'assets/dist/wasm/',
			'workerUrl' => WP_COMPACT_URL . 'assets/dist/compress-worker.js',
			'canManage' => current_user_can( 'manage_options' ),
			'i18n'      => array(
				'advancedWarning'     => __( 'Advanced mode can change format or dimensions and break themes / CSS / hardcoded URLs.', 'wp-compact' ),
				'compressing'         => __( 'Compressing…', 'wp-compact' ),
				'done'                => __( 'Done', 'wp-compact' ),
				'skipped'             => __( 'Skipped', 'wp-compact' ),
				'error'               => __( 'Error', 'wp-compact' ),
				'stopped'             => __( 'Stopped.', 'wp-compact' ),
				'uploadBadge'         => __( 'wp-compact', 'wp-compact' ),
				'migrateConfirm'      => __( 'Migrate the entire Media Library to WebP and rewrite links in the database? Previous files will be kept until cleanup.', 'wp-compact' ),
				'purgeConfirm'        => __( 'Permanently delete the selected legacy files?', 'wp-compact' ),
				'purgeAllConfirm'     => __( 'Purge ALL tagged legacy files? This cannot be undone.', 'wp-compact' ),
				'settingsSaved'       => __( 'Settings saved.', 'wp-compact' ),
				'saveFailed'          => __( 'Save failed', 'wp-compact' ),
				'statsFailed'         => __( 'Stats', 'wp-compact' ),
				'images'              => __( 'images', 'wp-compact' ),
				'alreadyCompressed'   => __( 'already compressed', 'wp-compact' ),
				'sameSettings'        => __( 'same settings', 'wp-compact' ),
				'alreadyWebp'         => __( 'already WebP', 'wp-compact' ),
				'noGain'              => __( 'no savings', 'wp-compact' ),
				'loadingLibrary'      => __( 'Loading Media Library…', 'wp-compact' ),
				'loadingMigrate'      => __( 'WebP migration: loading Media Library…', 'wp-compact' ),
				'imagesToProcess'     => __( 'image(s) to process.', 'wp-compact' ),
				'jobFailed'           => __( 'Failed', 'wp-compact' ),
				'ignored'             => __( 'skipped', 'wp-compact' ),
				'dbReplacements'      => __( 'DB replacements', 'wp-compact' ),
				'legacyTagged'        => __( 'legacy tagged', 'wp-compact' ),
				'toClean'             => __( 'to clean', 'wp-compact' ),
				'noLegacy'            => __( 'No tagged legacy files.', 'wp-compact' ),
				'noSelection'         => __( 'Nothing selected to purge.', 'wp-compact' ),
				'purgeAdminOnly'      => __( 'Full purge is limited to administrators.', 'wp-compact' ),
				'purgeSummary'        => __( 'Purge', 'wp-compact' ),
				'purgeTotalSummary'   => __( 'Full purge', 'wp-compact' ),
				'mediaItems'          => __( 'media items', 'wp-compact' ),
				'filesDeleted'        => __( 'files', 'wp-compact' ),
				'freed'               => __( 'freed', 'wp-compact' ),
				'workerFailed'        => __( 'Worker', 'wp-compact' ),
				'threshold'           => __( 'threshold', 'wp-compact' ),
				/* translators: %s: file name */
				'uploadNoGain'        => __( 'wp-compact: %s not replaced (no savings, %s).', 'wp-compact' ),
				/* translators: 1: file name 2: savings percent 3: minimum percent */
				'uploadBelowMin'      => __( 'wp-compact: %1$s skipped (savings %2$s%% < %3$s%%).', 'wp-compact' ),
				/* translators: 1: file name 2: optional WebP label 3: original size 4: new size 5: savings percent */
				'uploadDone'          => __( 'wp-compact: %1$s %2$s%3$s → %4$s (−%5$s%%).', 'wp-compact' ),
				/* translators: %s: file name */
				'uploadUnreadable'    => __( 'wp-compact: cannot read %s — uploading original without compression.', 'wp-compact' ),
				'uploadFileFallback'  => __( 'file', 'wp-compact' ),
				/* translators: %d: number of images */
				'uploadCompressing'   => __( 'wp-compact: compressing %d image(s)…', 'wp-compact' ),
				/* translators: 1: file name 2: error message */
				'uploadFailedItem'    => __( 'wp-compact: failed on %1$s (%2$s) — sending original.', 'wp-compact' ),
				/* translators: %s: error message */
				'uploadError'         => __( 'wp-compact: %s', 'wp-compact' ),
			),
		);
	}

	/**
	 * Whether upload compression is enabled.
	 *
	 * @return bool
	 */
	private function upload_enabled() {
		$settings = Settings::get_all();
		return 'off' !== ( $settings['upload_mode'] ?? 'off' );
	}

	/**
	 * Enqueue upload compressor when WP media is loaded (posts, pages, featured image, etc.).
	 */
	public function enqueue_upload_on_media() {
		if ( ! is_admin() || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		if ( ! $this->upload_enabled() ) {
			return;
		}
		$this->enqueue_upload_assets();
	}

	/**
	 * Enqueue upload JS/CSS once, after plupload/media so we can patch them.
	 */
	private function enqueue_upload_assets() {
		if ( wp_script_is( 'wp-compact-upload', 'enqueued' ) ) {
			return;
		}

		$css_path = WP_COMPACT_PATH . 'assets/dist/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WP_COMPACT_VERSION;
		wp_enqueue_style(
			'wp-compact-admin',
			WP_COMPACT_URL . 'assets/dist/admin.css',
			array(),
			$css_ver
		);

		$js_path = WP_COMPACT_PATH . 'assets/dist/admin-upload.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WP_COMPACT_VERSION;

		// Depend on media stack so wp.Uploader / plupload exist when we run.
		$deps = array( 'jquery', 'plupload' );
		foreach ( array( 'wp-util', 'media-models', 'media-views', 'media-editor' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) || wp_script_is( $handle, 'enqueued' ) ) {
				$deps[] = $handle;
			}
		}

		wp_enqueue_script(
			'wp-compact-upload',
			WP_COMPACT_URL . 'assets/dist/admin-upload.js',
			$deps,
			$js_ver,
			true
		);
		wp_localize_script( 'wp-compact-upload', 'wpCompactConfig', $this->script_config() );
	}

	/**
	 * Enqueue assets on relevant screens.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue( $hook ) {
		$is_batch  = ( 'media_page_' . self::PAGE_SLUG === $hook );
		$upload_on = $this->upload_enabled();

		// Screens that commonly open the media modal / uploader.
		$upload_hooks     = array(
			'upload.php',
			'media-new.php',
			'post.php',
			'post-new.php',
			'page.php',
			'page-new.php',
			'edit.php',
			'site-editor.php',
			'widgets.php',
			'customize.php',
		);
		$is_upload_screen = in_array( $hook, $upload_hooks, true )
			|| ( is_string( $hook ) && false !== strpos( $hook, 'post' ) );

		if ( ! $is_batch && ! ( $is_upload_screen && $upload_on ) ) {
			return;
		}

		$config = $this->script_config();

		$css_path = WP_COMPACT_PATH . 'assets/dist/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WP_COMPACT_VERSION;
		wp_enqueue_style(
			'wp-compact-admin',
			WP_COMPACT_URL . 'assets/dist/admin.css',
			array(),
			$css_ver
		);

		if ( $is_batch ) {
			$js_path = WP_COMPACT_PATH . 'assets/dist/admin-batch.js';
			$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WP_COMPACT_VERSION;
			wp_enqueue_script(
				'wp-compact-batch',
				WP_COMPACT_URL . 'assets/dist/admin-batch.js',
				array(),
				$js_ver,
				true
			);
			wp_localize_script( 'wp-compact-batch', 'wpCompactConfig', $config );
		}

		if ( $upload_on && ( $is_upload_screen || $is_batch ) ) {
			$this->enqueue_upload_assets();
		}
	}

	/**
	 * Render batch page shell.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-compact' ) );
		}
		include WP_COMPACT_PATH . 'admin/views/page-batch.php';
	}
}

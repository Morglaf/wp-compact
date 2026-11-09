<?php
/**
 * Admin UI.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page and asset enqueue.
 */
class Admin_Page {

	const PAGE_SLUG = 'wpsquoosh';

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
			__( 'WPsquoosh', 'wpsquoosh' ),
			__( 'Compress (WPsquoosh)', 'wpsquoosh' ),
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
			'pluginUrl' => WPSQUOOSH_URL,
			'wasmUrl'   => WPSQUOOSH_URL . 'assets/dist/wasm/',
			'workerUrl' => WPSQUOOSH_URL . 'assets/dist/compress-worker.js',
			'canManage' => current_user_can( 'manage_options' ),
			'i18n'      => array(
				'advancedWarning'     => __( 'Advanced mode can change format or dimensions and break themes / CSS / hardcoded URLs.', 'wpsquoosh' ),
				'compressing'         => __( 'Compressing…', 'wpsquoosh' ),
				'done'                => __( 'Done', 'wpsquoosh' ),
				'skipped'             => __( 'Skipped', 'wpsquoosh' ),
				'error'               => __( 'Error', 'wpsquoosh' ),
				'stopped'             => __( 'Stopped.', 'wpsquoosh' ),
				'uploadBadge'         => __( 'WPsquoosh', 'wpsquoosh' ),
				'migrateConfirm'      => __( 'Migrate the entire Media Library to WebP and rewrite links in the database? Previous files will be kept until cleanup.', 'wpsquoosh' ),
				'purgeConfirm'        => __( 'Permanently delete the selected legacy files?', 'wpsquoosh' ),
				'purgeAllConfirm'     => __( 'Purge ALL tagged legacy files? This cannot be undone.', 'wpsquoosh' ),
				'settingsSaved'       => __( 'Settings saved.', 'wpsquoosh' ),
				'saveFailed'          => __( 'Save failed', 'wpsquoosh' ),
				'statsFailed'         => __( 'Stats', 'wpsquoosh' ),
				'images'              => __( 'images', 'wpsquoosh' ),
				'alreadyCompressed'   => __( 'already compressed', 'wpsquoosh' ),
				'sameSettings'        => __( 'same settings', 'wpsquoosh' ),
				'alreadyWebp'         => __( 'already WebP', 'wpsquoosh' ),
				'noGain'              => __( 'no savings', 'wpsquoosh' ),
				'loadingLibrary'      => __( 'Loading Media Library…', 'wpsquoosh' ),
				'loadingMigrate'      => __( 'WebP migration: loading Media Library…', 'wpsquoosh' ),
				'imagesToProcess'     => __( 'image(s) to process.', 'wpsquoosh' ),
				'jobFailed'           => __( 'Failed', 'wpsquoosh' ),
				'ignored'             => __( 'skipped', 'wpsquoosh' ),
				'dbReplacements'      => __( 'DB replacements', 'wpsquoosh' ),
				'legacyTagged'        => __( 'legacy tagged', 'wpsquoosh' ),
				'toClean'             => __( 'to clean', 'wpsquoosh' ),
				'noLegacy'            => __( 'No tagged legacy files.', 'wpsquoosh' ),
				'noSelection'         => __( 'Nothing selected to purge.', 'wpsquoosh' ),
				'purgeAdminOnly'      => __( 'Full purge is limited to administrators.', 'wpsquoosh' ),
				'purgeSummary'        => __( 'Purge', 'wpsquoosh' ),
				'purgeTotalSummary'   => __( 'Full purge', 'wpsquoosh' ),
				'mediaItems'          => __( 'media items', 'wpsquoosh' ),
				'filesDeleted'        => __( 'files', 'wpsquoosh' ),
				'freed'               => __( 'freed', 'wpsquoosh' ),
				'workerFailed'        => __( 'Worker', 'wpsquoosh' ),
				'threshold'           => __( 'threshold', 'wpsquoosh' ),
				/* translators: %s: file name */
				'uploadNoGain'        => __( 'WPsquoosh: %s not replaced (no savings, %s).', 'wpsquoosh' ),
				/* translators: 1: file name 2: savings percent 3: minimum percent */
				'uploadBelowMin'      => __( 'WPsquoosh: %1$s skipped (savings %2$s%% < %3$s%%).', 'wpsquoosh' ),
				/* translators: 1: file name 2: optional WebP label 3: original size 4: new size 5: savings percent */
				'uploadDone'          => __( 'WPsquoosh: %1$s %2$s%3$s → %4$s (−%5$s%%).', 'wpsquoosh' ),
				/* translators: %s: file name */
				'uploadUnreadable'    => __( 'WPsquoosh: cannot read %s — uploading original without compression.', 'wpsquoosh' ),
				'uploadFileFallback'  => __( 'file', 'wpsquoosh' ),
				/* translators: %d: number of images */
				'uploadCompressing'   => __( 'WPsquoosh: compressing %d image(s)…', 'wpsquoosh' ),
				/* translators: 1: file name 2: error message */
				'uploadFailedItem'    => __( 'WPsquoosh: failed on %1$s (%2$s) — sending original.', 'wpsquoosh' ),
				/* translators: %s: error message */
				'uploadError'         => __( 'WPsquoosh: %s', 'wpsquoosh' ),
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
		if ( wp_script_is( 'wpsquoosh-upload', 'enqueued' ) ) {
			return;
		}

		$css_path = WPSQUOOSH_PATH . 'assets/dist/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPSQUOOSH_VERSION;
		wp_enqueue_style(
			'wpsquoosh-admin',
			WPSQUOOSH_URL . 'assets/dist/admin.css',
			array(),
			$css_ver
		);

		$js_path = WPSQUOOSH_PATH . 'assets/dist/admin-upload.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPSQUOOSH_VERSION;

		// Depend on media stack so wp.Uploader / plupload exist when we run.
		$deps = array( 'jquery', 'plupload' );
		foreach ( array( 'wp-util', 'media-models', 'media-views', 'media-editor' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) || wp_script_is( $handle, 'enqueued' ) ) {
				$deps[] = $handle;
			}
		}

		wp_enqueue_script(
			'wpsquoosh-upload',
			WPSQUOOSH_URL . 'assets/dist/admin-upload.js',
			$deps,
			$js_ver,
			true
		);
		wp_localize_script( 'wpsquoosh-upload', 'wpSquooshConfig', $this->script_config() );
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

		$css_path = WPSQUOOSH_PATH . 'assets/dist/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPSQUOOSH_VERSION;
		wp_enqueue_style(
			'wpsquoosh-admin',
			WPSQUOOSH_URL . 'assets/dist/admin.css',
			array(),
			$css_ver
		);

		if ( $is_batch ) {
			$js_path = WPSQUOOSH_PATH . 'assets/dist/admin-batch.js';
			$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPSQUOOSH_VERSION;
			wp_enqueue_script(
				'wpsquoosh-batch',
				WPSQUOOSH_URL . 'assets/dist/admin-batch.js',
				array(),
				$js_ver,
				true
			);
			wp_localize_script( 'wpsquoosh-batch', 'wpSquooshConfig', $config );
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
			wp_die( esc_html__( 'Access denied.', 'wpsquoosh' ) );
		}
		include WPSQUOOSH_PATH . 'admin/views/page-batch.php';
	}
}

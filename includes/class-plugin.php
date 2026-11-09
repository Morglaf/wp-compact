<?php
/**
 * Main plugin bootstrap.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton.
 */
class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire hooks.
	 */
	public function init() {
		$rest = new Rest();
		add_action( 'rest_api_init', array( $rest, 'register' ) );

		// Ensure .wasm is served with a usable MIME type on some hosts.
		add_filter( 'upload_mimes', array( $this, 'allow_wasm_mime' ) );
		add_filter( 'mime_types', array( $this, 'allow_wasm_mime' ) );

		if ( is_admin() ) {
			$admin = new Admin_Page();
			$admin->init();
		}
	}

	/**
	 * @param array<string, string> $mimes Mime map.
	 * @return array<string, string>
	 */
	public function allow_wasm_mime( $mimes ) {
		$mimes['wasm'] = 'application/wasm';
		return $mimes;
	}
}

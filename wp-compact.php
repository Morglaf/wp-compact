<?php
/**
 * Plugin Name:       wp-compact
 * Description:       Compress Media Library images in the browser with Squoosh-derived WASM codecs (jSquash). Optional backup, batch jobs, and upload-time compression. Not affiliated with Google or Squoosh.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            morglaf
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-compact
 * Domain Path:       /languages
 *
 * @package WpCompact
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_COMPACT_VERSION', '1.0.0' );
define( 'WP_COMPACT_FILE', __FILE__ );
define( 'WP_COMPACT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_COMPACT_URL', plugin_dir_url( __FILE__ ) );

require_once WP_COMPACT_PATH . 'includes/class-settings.php';
require_once WP_COMPACT_PATH . 'includes/class-backup.php';
require_once WP_COMPACT_PATH . 'includes/class-url-rewriter.php';
require_once WP_COMPACT_PATH . 'includes/class-legacy-cleanup.php';
require_once WP_COMPACT_PATH . 'includes/class-attachments.php';
require_once WP_COMPACT_PATH . 'includes/class-rest.php';
require_once WP_COMPACT_PATH . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once WP_COMPACT_PATH . 'admin/class-admin-page.php';
}

/**
 * Load translations.
 */
function wp_compact_load_textdomain() {
	load_plugin_textdomain(
		'wp-compact',
		false,
		dirname( plugin_basename( WP_COMPACT_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'wp_compact_load_textdomain', 1 );

/**
 * Bootstrap the plugin.
 */
function wp_compact_init() {
	WpCompact\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'wp_compact_init' );

register_activation_hook( __FILE__, array( 'WpCompact\\Settings', 'activate' ) );

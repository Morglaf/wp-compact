<?php
/**
 * Plugin Name:       WPsquoosh
 * Description:       Compresse les images de la médiathèque dans le navigateur via codecs WASM dérivés de Squoosh (jSquash). Remplacement de l’original, backup optionnelle, batch et compression à l’upload.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            morglaf
 * License:           Apache-2.0
 * Text Domain:       wpsquoosh
 *
 * @package WPSquoosh
 */

defined( 'ABSPATH' ) || exit;

define( 'WPSQUOOSH_VERSION', '1.0.0' );
define( 'WPSQUOOSH_FILE', __FILE__ );
define( 'WPSQUOOSH_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPSQUOOSH_URL', plugin_dir_url( __FILE__ ) );

require_once WPSQUOOSH_PATH . 'includes/class-settings.php';
require_once WPSQUOOSH_PATH . 'includes/class-backup.php';
require_once WPSQUOOSH_PATH . 'includes/class-url-rewriter.php';
require_once WPSQUOOSH_PATH . 'includes/class-legacy-cleanup.php';
require_once WPSQUOOSH_PATH . 'includes/class-attachments.php';
require_once WPSQUOOSH_PATH . 'includes/class-rest.php';
require_once WPSQUOOSH_PATH . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once WPSQUOOSH_PATH . 'admin/class-admin-page.php';
}

/**
 * Bootstrap the plugin.
 */
function wpsquoosh_init() {
	WPSquoosh\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'wpsquoosh_init' );

register_activation_hook( __FILE__, array( 'WPSquoosh\\Settings', 'activate' ) );

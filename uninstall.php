<?php
/**
 * Uninstall cleanup.
 *
 * @package WPSquoosh
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wpsquoosh_settings' );

// Leave backup files on disk intentionally (user data).

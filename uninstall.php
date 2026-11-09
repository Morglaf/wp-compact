<?php
/**
 * Uninstall cleanup.
 *
 * @package WpCompact
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wp_compact_settings' );

// Leave backup files on disk intentionally (user data).

<?php
/**
 * Plugin settings.
 *
 * @package WpCompact
 */

namespace WpCompact;

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage and defaults.
 */
class Settings {

	const OPTION_KEY = 'wp_compact_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'jpeg_quality'       => 78,
			'webp_quality'       => 78,
			'avif_quality'       => 45,
			'oxipng_level'       => 2,
			'backup_enabled'     => true,
			'compress_on_upload' => true, // legacy mirror of upload_mode !== off
			'upload_mode'        => 'compress', // off | compress | webp
			'min_savings_pct'    => 2,
			'concurrency'        => 1,
			'advanced_enabled'   => false,
			'max_width'          => 0,
			'max_height'         => 0,
			'convert_to'         => '', // '', 'webp', 'avif'
			'allow_larger'       => false,
		);
	}

	/**
	 * Activation: seed defaults if missing.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::defaults() );
		}
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		// Migrate legacy boolean → upload_mode.
		if ( ! isset( $stored['upload_mode'] ) && array_key_exists( 'compress_on_upload', $stored ) ) {
			$stored['upload_mode'] = ! empty( $stored['compress_on_upload'] ) ? 'compress' : 'off';
		}

		$all = array_merge( self::defaults(), $stored );
		$all['upload_mode'] = self::sanitize_upload_mode( $all['upload_mode'] ?? 'compress' );
		$all['compress_on_upload'] = ( 'off' !== $all['upload_mode'] );

		return $all;
	}

	/**
	 * @param mixed $mode Raw mode.
	 * @return string off|compress|webp
	 */
	public static function sanitize_upload_mode( $mode ) {
		$mode = is_string( $mode ) ? $mode : 'compress';
		$allowed = array( 'off', 'compress', 'webp' );
		return in_array( $mode, $allowed, true ) ? $mode : 'compress';
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Sanitize and save settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function save( $input ) {
		$defaults = self::defaults();
		$clean    = self::get_all();

		if ( isset( $input['jpeg_quality'] ) ) {
			$clean['jpeg_quality'] = max( 1, min( 100, (int) $input['jpeg_quality'] ) );
		}
		if ( isset( $input['webp_quality'] ) ) {
			$clean['webp_quality'] = max( 1, min( 100, (int) $input['webp_quality'] ) );
		}
		if ( isset( $input['avif_quality'] ) ) {
			$clean['avif_quality'] = max( 0, min( 63, (int) $input['avif_quality'] ) );
		}
		if ( isset( $input['oxipng_level'] ) ) {
			$clean['oxipng_level'] = max( 0, min( 6, (int) $input['oxipng_level'] ) );
		}
		if ( array_key_exists( 'backup_enabled', $input ) ) {
			$clean['backup_enabled'] = (bool) $input['backup_enabled'];
		}
		if ( isset( $input['upload_mode'] ) ) {
			$clean['upload_mode'] = self::sanitize_upload_mode( $input['upload_mode'] );
			$clean['compress_on_upload'] = ( 'off' !== $clean['upload_mode'] );
		} elseif ( array_key_exists( 'compress_on_upload', $input ) ) {
			$clean['compress_on_upload'] = (bool) $input['compress_on_upload'];
			$clean['upload_mode']        = $clean['compress_on_upload'] ? 'compress' : 'off';
		}
		if ( isset( $input['min_savings_pct'] ) ) {
			$clean['min_savings_pct'] = max( 0, min( 50, (float) $input['min_savings_pct'] ) );
		}
		if ( isset( $input['concurrency'] ) ) {
			$clean['concurrency'] = max( 1, min( 4, (int) $input['concurrency'] ) );
		}
		if ( array_key_exists( 'advanced_enabled', $input ) ) {
			$clean['advanced_enabled'] = (bool) $input['advanced_enabled'];
		}
		if ( isset( $input['max_width'] ) ) {
			$clean['max_width'] = max( 0, (int) $input['max_width'] );
		}
		if ( isset( $input['max_height'] ) ) {
			$clean['max_height'] = max( 0, (int) $input['max_height'] );
		}
		if ( isset( $input['convert_to'] ) ) {
			$allowed = array( '', 'webp', 'avif' );
			$val     = sanitize_text_field( (string) $input['convert_to'] );
			$clean['convert_to'] = in_array( $val, $allowed, true ) ? $val : '';
		}
		if ( array_key_exists( 'allow_larger', $input ) ) {
			$clean['allow_larger'] = (bool) $input['allow_larger'];
		}

		// Drop unknown keys.
		$clean = array_intersect_key( $clean, $defaults );
		update_option( self::OPTION_KEY, $clean );

		return $clean;
	}

	/**
	 * Hash of compression-relevant settings (for skip-if-same).
	 *
	 * @param array<string, mixed>|null $settings Settings subset.
	 * @return string
	 */
	public static function settings_hash( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_all();
		}
		$relevant = array(
			'jpeg_quality'     => $settings['jpeg_quality'] ?? 78,
			'webp_quality'     => $settings['webp_quality'] ?? 78,
			'avif_quality'     => $settings['avif_quality'] ?? 45,
			'oxipng_level'     => $settings['oxipng_level'] ?? 2,
			'advanced_enabled' => ! empty( $settings['advanced_enabled'] ),
			'max_width'        => $settings['max_width'] ?? 0,
			'max_height'       => $settings['max_height'] ?? 0,
			'convert_to'       => $settings['convert_to'] ?? '',
		);
		return md5( wp_json_encode( $relevant ) );
	}
}

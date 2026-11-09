<?php
/**
 * Attachment queries and file replacement.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * Media library helpers.
 */
class Attachments {

	/**
	 * Supported MIME types for v1.
	 *
	 * @return string[]
	 */
	public static function supported_mimes() {
		return array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' );
	}

	/**
	 * Paginated list of eligible attachments.
	 *
	 * @param int $page Page number (1-based).
	 * @param int $per_page Per page.
	 * @return array{items: array<int, array>, total: int, pages: int}
	 */
	public static function list_media( $page = 1, $per_page = 50 ) {
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 200, (int) $per_page ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => self::supported_mimes(),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		$items = array();
		foreach ( $query->posts as $id ) {
			$row = self::to_list_item( (int) $id );
			if ( $row ) {
				$items[] = $row;
			}
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Stats for admin UI.
	 *
	 * @return array{total: int, optimized: int, bytes: int}
	 */
	public static function stats() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => self::supported_mimes(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$total     = count( $query->posts );
		$optimized = 0;
		$bytes     = 0;

		foreach ( $query->posts as $id ) {
			$path = get_attached_file( (int) $id );
			if ( $path && file_exists( $path ) ) {
				$bytes += (int) filesize( $path );
			}
			if ( get_post_meta( (int) $id, '_wpsquoosh_optimized', true ) ) {
				++$optimized;
			}
		}

		return array(
			'total'     => $total,
			'optimized' => $optimized,
			'bytes'     => $bytes,
		);
	}

	/**
	 * Serialize attachment for list API.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>|null
	 */
	public static function to_list_item( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return null;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, self::supported_mimes(), true ) ) {
			return null;
		}

		$meta   = wp_get_attachment_metadata( $attachment_id );
		$width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		$size   = (int) filesize( $path );

		$hash_done = (string) get_post_meta( $attachment_id, '_wpsquoosh_settings_hash', true );
		$current   = Settings::settings_hash();

		return array(
			'id'                => $attachment_id,
			'title'             => get_the_title( $attachment_id ),
			'url'               => wp_get_attachment_url( $attachment_id ),
			'mime'              => $mime,
			'width'             => $width,
			'height'            => $height,
			'filesize'          => $size,
			'already_optimized' => (bool) get_post_meta( $attachment_id, '_wpsquoosh_optimized', true ),
			'same_settings'     => ( $hash_done && $hash_done === $current ),
			'optimized_at'      => get_post_meta( $attachment_id, '_wpsquoosh_optimized_at', true ),
			'original_size'     => (int) get_post_meta( $attachment_id, '_wpsquoosh_original_size', true ),
			'compressed_size'   => (int) get_post_meta( $attachment_id, '_wpsquoosh_compressed_size', true ),
			'backup_path'       => get_post_meta( $attachment_id, '_wpsquoosh_backup_path', true ),
			'pending_cleanup'   => (bool) get_post_meta( $attachment_id, '_wpsquoosh_pending_cleanup', true ),
		);
	}

	/**
	 * Absolute path to attachment file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|\WP_Error
	 */
	public static function file_path( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return new \WP_Error( 'wpsquoosh_missing_file', __( 'Fichier introuvable.', 'wpsquoosh' ) );
		}
		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, self::supported_mimes(), true ) ) {
			return new \WP_Error( 'wpsquoosh_unsupported', __( 'Type MIME non supporté.', 'wpsquoosh' ) );
		}
		return $path;
	}

	/**
	 * Collect absolute paths for main file + intermediate sizes.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param string $main_path Absolute main file path.
	 * @param array  $meta Attachment metadata.
	 * @return string[]
	 */
	public static function collect_file_paths( $attachment_id, $main_path, $meta ) {
		$paths = array();
		if ( $main_path && file_exists( $main_path ) ) {
			$paths[] = wp_normalize_path( $main_path );
		}

		$dir = dirname( $main_path );
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$thumb = wp_normalize_path( trailingslashit( $dir ) . $size['file'] );
				if ( file_exists( $thumb ) ) {
					$paths[] = $thumb;
				}
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Convert absolute paths under uploads to relative paths.
	 *
	 * @param string[] $absolute_paths Absolute paths.
	 * @return string[]
	 */
	public static function to_relative_uploads_paths( $absolute_paths ) {
		$uploads = wp_upload_dir();
		$base    = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$out     = array();

		foreach ( $absolute_paths as $abs ) {
			$abs = wp_normalize_path( $abs );
			if ( 0 === strpos( $abs, $base ) ) {
				$out[] = ltrim( substr( $abs, strlen( $base ) ), '/' );
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Replace attachment file with uploaded compressed blob.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array                $file $_FILES-like entry.
	 * @param array<string, mixed> $args Options: backup, allow_larger, settings, original_size, mime, keep_legacy, migrate_webp.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function replace_file( $attachment_id, $file, $args = array() ) {
		$path = self::file_path( $attachment_id );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'wpsquoosh_no_upload', __( 'Aucun fichier compressé reçu.', 'wpsquoosh' ) );
		}

		$migrate_webp = ! empty( $args['migrate_webp'] );
		$keep_legacy  = ! empty( $args['keep_legacy'] ) || $migrate_webp;

		$original_size = isset( $args['original_size'] ) ? (int) $args['original_size'] : (int) filesize( $path );
		$new_size      = (int) filesize( $file['tmp_name'] );
		$allow_larger  = ! empty( $args['allow_larger'] ) || $migrate_webp;

		if ( $new_size <= 0 ) {
			return new \WP_Error( 'wpsquoosh_empty', __( 'Fichier compressé vide.', 'wpsquoosh' ) );
		}

		if ( ! $allow_larger && $new_size >= $original_size ) {
			return new \WP_Error(
				'wpsquoosh_no_gain',
				__( 'Le fichier compressé n’est pas plus petit ; remplacement annulé.', 'wpsquoosh' ),
				array( 'original_size' => $original_size, 'new_size' => $new_size )
			);
		}

		$backup_path = null;
		if ( ! empty( $args['backup'] ) && ! $keep_legacy ) {
			$backup = Backup::copy_original( $attachment_id, $path );
			if ( is_wp_error( $backup ) ) {
				return $backup;
			}
			$backup_path = $backup;
		}

		$old_meta     = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $old_meta ) ) {
			$old_meta = array();
		}
		$old_rel_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$old_url      = wp_get_attachment_url( $attachment_id );
		$legacy_abs   = self::collect_file_paths( $attachment_id, $path, $old_meta );

		$url_snapshot_before = null;
		if ( $migrate_webp ) {
			$url_snapshot_before = Url_Rewriter::snapshot_attachment_urls( $attachment_id, $path, $old_meta, $old_rel_file, $old_url );
		}

		$new_mime = isset( $args['mime'] ) ? sanitize_mime_type( $args['mime'] ) : get_post_mime_type( $attachment_id );
		$old_mime = get_post_mime_type( $attachment_id );

		if ( $migrate_webp ) {
			$new_mime = 'image/webp';
		}

		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/avif' => 'avif',
		);

		$target_path  = $path;
		$mime_changed = ( $new_mime && $new_mime !== $old_mime );

		if ( $mime_changed && isset( $ext_map[ $new_mime ] ) ) {
			$dir      = dirname( $path );
			$filename = pathinfo( $path, PATHINFO_FILENAME );
			$new_path = trailingslashit( $dir ) . $filename . '.' . $ext_map[ $new_mime ];

			// Avoid collision with a different existing file.
			if ( file_exists( $new_path ) && realpath( $new_path ) !== realpath( $path ) ) {
				$new_path = trailingslashit( $dir ) . $filename . '-' . time() . '.' . $ext_map[ $new_mime ];
			}

			if ( ! move_uploaded_file( $file['tmp_name'], $new_path ) ) {
				return new \WP_Error( 'wpsquoosh_move', __( 'Impossible d’écrire le fichier compressé.', 'wpsquoosh' ) );
			}

			if ( ! $keep_legacy && realpath( $new_path ) !== realpath( $path ) && file_exists( $path ) ) {
				wp_delete_file( $path );
			}

			$target_path = $new_path;
			update_attached_file( $attachment_id, $target_path );
			wp_update_post(
				array(
					'ID'             => $attachment_id,
					'post_mime_type' => $new_mime,
				)
			);
		} else {
			if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				if ( ! copy( $file['tmp_name'], $target_path ) ) {
					return new \WP_Error( 'wpsquoosh_move', __( 'Impossible d’écrire le fichier compressé.', 'wpsquoosh' ) );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $file['tmp_name'] );
			}
		}

		// Intermediate sizes: delete only when not keeping legacy.
		if ( ! $keep_legacy && ! empty( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
			$dir = dirname( $target_path );
			foreach ( $old_meta['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$thumb = trailingslashit( $dir ) . $size['file'];
				if ( file_exists( $thumb ) ) {
					wp_delete_file( $thumb );
				}
			}
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $target_path );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$settings      = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : Settings::get_all();
		$settings_hash = Settings::settings_hash( $settings );
		$final_size    = file_exists( $target_path ) ? (int) filesize( $target_path ) : $new_size;

		update_post_meta( $attachment_id, '_wpsquoosh_optimized', 1 );
		update_post_meta( $attachment_id, '_wpsquoosh_optimized_at', gmdate( 'c' ) );
		update_post_meta( $attachment_id, '_wpsquoosh_original_size', $original_size );
		update_post_meta( $attachment_id, '_wpsquoosh_compressed_size', $final_size );
		update_post_meta( $attachment_id, '_wpsquoosh_settings_hash', $settings_hash );
		update_post_meta( $attachment_id, '_wpsquoosh_settings', wp_json_encode( $settings ) );

		$urls_updated = 0;
		$url_map      = array();

		if ( $keep_legacy && $mime_changed ) {
			$current_abs = self::collect_file_paths( $attachment_id, $target_path, is_array( $metadata ) ? $metadata : array() );
			$legacy_only = array_values(
				array_filter(
					$legacy_abs,
					static function ( $p ) use ( $current_abs, $target_path ) {
						$p = wp_normalize_path( $p );
						if ( wp_normalize_path( $target_path ) === $p ) {
							return false;
						}
						foreach ( $current_abs as $cur ) {
							if ( wp_normalize_path( $cur ) === $p ) {
								return false;
							}
						}
						return file_exists( $p );
					}
				)
			);

			$legacy_rel = self::to_relative_uploads_paths( $legacy_only );
			// Merge with any previous legacy list.
			$prev = get_post_meta( $attachment_id, '_wpsquoosh_legacy_files', true );
			if ( is_string( $prev ) && $prev !== '' ) {
				$decoded = json_decode( $prev, true );
				if ( is_array( $decoded ) ) {
					$legacy_rel = array_values( array_unique( array_merge( $decoded, $legacy_rel ) ) );
				}
			}

			update_post_meta( $attachment_id, '_wpsquoosh_legacy_files', wp_json_encode( $legacy_rel ) );
			update_post_meta( $attachment_id, '_wpsquoosh_pending_cleanup', 1 );
		}

		if ( $migrate_webp && $url_snapshot_before ) {
			$url_map = Url_Rewriter::build_map_after_migration( $attachment_id, $url_snapshot_before );
			if ( ! empty( $url_map ) ) {
				$rewrite      = Url_Rewriter::apply_map( $url_map );
				$urls_updated = isset( $rewrite['replacements'] ) ? (int) $rewrite['replacements'] : 0;
				update_post_meta( $attachment_id, '_wpsquoosh_url_map', wp_json_encode( $url_map ) );
			}
		}

		$saved = $original_size > 0 ? round( ( 1 - ( $final_size / $original_size ) ) * 100, 2 ) : 0;

		return array(
			'id'               => $attachment_id,
			'original_size'    => $original_size,
			'compressed_size'  => $final_size,
			'saved_pct'        => $saved,
			'backup_path'      => $backup_path,
			'mime'             => get_post_mime_type( $attachment_id ),
			'url'              => wp_get_attachment_url( $attachment_id ),
			'pending_cleanup'  => (bool) get_post_meta( $attachment_id, '_wpsquoosh_pending_cleanup', true ),
			'legacy_files'     => json_decode( (string) get_post_meta( $attachment_id, '_wpsquoosh_legacy_files', true ), true ) ?: array(),
			'urls_updated'     => $urls_updated,
			'url_map'          => $url_map,
		);
	}
}

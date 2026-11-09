<?php
/**
 * Legacy file cleanup after WebP migration.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * List and purge tagged legacy files.
 */
class Legacy_Cleanup {

	/**
	 * List attachments pending cleanup.
	 *
	 * @param int $page Page.
	 * @param int $per_page Per page.
	 * @return array{items: array, total: int, pages: int, bytes: int}
	 */
	public static function list_pending( $page = 1, $per_page = 50 ) {
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 200, (int) $per_page ) );

		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_wpsquoosh_pending_cleanup',
						'value' => '1',
					),
				),
			)
		);

		$items = array();
		$bytes = 0;

		foreach ( $query->posts as $id ) {
			$row = self::item( (int) $id );
			if ( $row ) {
				$items[] = $row;
				$bytes  += (int) $row['bytes'];
			}
		}

		// Total bytes across all pages (approx via separate query for page 1 only when needed).
		$total_bytes = $bytes;
		if ( 1 === $page && (int) $query->max_num_pages > 1 ) {
			$total_bytes = self::total_legacy_bytes();
		} elseif ( 1 === $page ) {
			$total_bytes = $bytes;
		} else {
			$total_bytes = self::total_legacy_bytes();
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'bytes' => $total_bytes,
		);
	}

	/**
	 * Sum legacy file bytes for all pending attachments.
	 *
	 * @return int
	 */
	public static function total_legacy_bytes() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_wpsquoosh_pending_cleanup',
						'value' => '1',
					),
				),
			)
		);

		$sum = 0;
		foreach ( $query->posts as $id ) {
			$row = self::item( (int) $id );
			if ( $row ) {
				$sum += (int) $row['bytes'];
			}
		}
		return $sum;
	}

	/**
	 * One cleanup item.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>|null
	 */
	public static function item( $attachment_id ) {
		if ( ! get_post_meta( $attachment_id, '_wpsquoosh_pending_cleanup', true ) ) {
			return null;
		}

		$files = self::legacy_absolute_paths( $attachment_id );
		$bytes = 0;
		$existing = array();
		foreach ( $files as $path ) {
			if ( file_exists( $path ) ) {
				$existing[] = $path;
				$bytes     += (int) filesize( $path );
			}
		}

		return array(
			'id'         => $attachment_id,
			'title'      => get_the_title( $attachment_id ),
			'url'        => wp_get_attachment_url( $attachment_id ),
			'mime'       => get_post_mime_type( $attachment_id ),
			'file_count' => count( $existing ),
			'bytes'      => $bytes,
			'files'      => Attachments::to_relative_uploads_paths( $existing ),
		);
	}

	/**
	 * Absolute legacy paths still safe to delete.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string[]
	 */
	public static function legacy_absolute_paths( $attachment_id ) {
		$raw = get_post_meta( $attachment_id, '_wpsquoosh_legacy_files', true );
		$rel = array();
		if ( is_string( $raw ) && $raw !== '' ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rel = $decoded;
			}
		}

		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		$current = get_attached_file( $attachment_id );
		$current = $current ? wp_normalize_path( $current ) : '';

		$current_meta = wp_get_attachment_metadata( $attachment_id );
		$protected    = array();
		if ( $current ) {
			$protected[] = $current;
			$protected   = array_merge(
				$protected,
				Attachments::collect_file_paths( $attachment_id, $current, is_array( $current_meta ) ? $current_meta : array() )
			);
		}
		$protected = array_map( 'wp_normalize_path', $protected );

		$out = array();
		foreach ( $rel as $r ) {
			$r = ltrim( str_replace( '\\', '/', (string) $r ), '/' );
			if ( $r === '' || false !== strpos( $r, '..' ) ) {
				continue;
			}
			$abs = wp_normalize_path( $base . $r );
			if ( ! file_exists( $abs ) ) {
				continue;
			}
			if ( in_array( $abs, $protected, true ) ) {
				continue;
			}
			$out[] = $abs;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Purge legacy files for attachment IDs.
	 *
	 * @param int[] $ids Attachment IDs.
	 * @return array{purged: int, files_deleted: int, bytes_freed: int, errors: string[]}
	 */
	public static function purge_ids( $ids ) {
		$purged        = 0;
		$files_deleted = 0;
		$bytes_freed   = 0;
		$errors        = array();

		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$paths = self::legacy_absolute_paths( $id );
			foreach ( $paths as $path ) {
				$size = file_exists( $path ) ? (int) filesize( $path ) : 0;
				$ok   = wp_delete_file( $path );
				if ( false === $ok && file_exists( $path ) ) {
					$errors[] = sprintf( 'delete_failed:%d:%s', $id, basename( $path ) );
					continue;
				}
				++$files_deleted;
				$bytes_freed += $size;
			}
			delete_post_meta( $id, '_wpsquoosh_legacy_files' );
			delete_post_meta( $id, '_wpsquoosh_pending_cleanup' );
			++$purged;
		}

		return compact( 'purged', 'files_deleted', 'bytes_freed', 'errors' );
	}

	/**
	 * Purge all pending.
	 *
	 * @return array{purged: int, files_deleted: int, bytes_freed: int, errors: string[]}
	 */
	public static function purge_all() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_wpsquoosh_pending_cleanup',
						'value' => '1',
					),
				),
			)
		);

		return self::purge_ids( array_map( 'intval', $query->posts ) );
	}
}

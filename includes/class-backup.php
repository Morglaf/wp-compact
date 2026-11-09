<?php
/**
 * Optional original backups.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * Backup helpers.
 */
class Backup {

	/**
	 * Relative uploads subdir for backups.
	 *
	 * @return string
	 */
	public static function relative_dir() {
		return 'wpsquoosh-backups';
	}

	/**
	 * Absolute backup directory for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function dir_for( $attachment_id ) {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::relative_dir() . '/' . (int) $attachment_id;
	}

	/**
	 * Ensure backup directory exists.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|\WP_Error Path or error.
	 */
	public static function ensure_dir( $attachment_id ) {
		$dir = self::dir_for( $attachment_id );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'wpsquoosh_backup_mkdir', __( 'Impossible de créer le dossier de backup.', 'wpsquoosh' ) );
		}

		$index = trailingslashit( dirname( $dir ) ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return $dir;
	}

	/**
	 * Copy original file to backup location.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $source_path Absolute path to current file.
	 * @return string|\WP_Error Backup path or error.
	 */
	public static function copy_original( $attachment_id, $source_path ) {
		if ( ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
			return new \WP_Error( 'wpsquoosh_backup_source', __( 'Fichier source introuvable pour la backup.', 'wpsquoosh' ) );
		}

		$dir = self::ensure_dir( $attachment_id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$basename = basename( $source_path );
		$dest     = trailingslashit( $dir ) . $basename;

		if ( file_exists( $dest ) ) {
			$dest = trailingslashit( $dir ) . time() . '-' . $basename;
		}

		if ( ! copy( $source_path, $dest ) ) {
			return new \WP_Error( 'wpsquoosh_backup_copy', __( 'Échec de la copie de backup.', 'wpsquoosh' ) );
		}

		update_post_meta( $attachment_id, '_wpsquoosh_backup_path', $dest );

		return $dest;
	}
}

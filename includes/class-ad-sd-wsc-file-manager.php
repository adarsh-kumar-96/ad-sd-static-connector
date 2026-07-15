<?php
/**
 * Handles static file reading, writing, version history.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_File_Manager
 */
class AD_SD_WSC_File_Manager {

	/**
	 * Get file content for editor.
	 *
	 * @param int    $zip_id    ZIP ID.
	 * @param string $file_path Relative file path inside the ZIP.
	 * @return array
	 */
	public function get_file_content( $zip_id, $file_path ) {
		// Special list request from JS.
		if ( '__ADSD_LIST__' === $file_path ) {
			return $this->list_zip_files( $zip_id );
		}

		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_path = AD_SD_WSC_Helpers::sanitize_file_path( $file_path );
		$full_path = $zip->extract_path . '/' . $safe_path;

		// Ensure path stays within extract dir.
		$real_extract = realpath( $zip->extract_path );
		$real_file    = realpath( $full_path );

		if ( false === $real_file || 0 !== strpos( $real_file, $real_extract ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid file path.', 'ad-sd-static-connector' ) );
		}

		if ( ! file_exists( $real_file ) ) {
			return array( 'success' => false, 'message' => __( 'File not found.', 'ad-sd-static-connector' ) );
		}

		$ext = strtolower( pathinfo( $real_file, PATHINFO_EXTENSION ) );

		$image_exts = array( 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'bmp' );
		$is_image   = in_array( $ext, $image_exts, true );

		if ( $is_image ) {
			// Binary file: don't read raw bytes into JSON (breaks encoding and
			// causes the editor to hang on "Loading file..."). Send a data URI instead.
			$mime_map = array(
				'png'  => 'image/png',
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'gif'  => 'image/gif',
				'svg'  => 'image/svg+xml',
				'webp' => 'image/webp',
				'ico'  => 'image/x-icon',
				'bmp'  => 'image/bmp',
			);
			$mime = isset( $mime_map[ $ext ] ) ? $mime_map[ $ext ] : 'application/octet-stream';
			$bytes = adsd_file_get_contents( $real_file );

			return array(
				'success'  => true,
				'content'  => '',
				'is_image' => true,
				'data_uri' => 'data:' . $mime . ';base64,' . base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'ext'      => $ext,
				'path'     => $safe_path,
				'size'     => AD_SD_WSC_Helpers::format_file_size( filesize( $real_file ) ),
				'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $real_file ) ),
			);
		}
		$content = adsd_file_get_contents( $real_file );

		return array(
			'success'  => true,
			'content'  => $content,
			'is_image' => false,
			'ext'      => $ext,
			'path'     => $safe_path,
			'base_url' => AD_SD_WSC_Helpers::get_extract_url( $zip->extract_path, $safe_path ),
			'size'     => AD_SD_WSC_Helpers::format_file_size( filesize( $real_file ) ),
			'modified' => gmdate( 'Y-m-d H:i:s', filemtime( $real_file ) ),
		);
	}

	/**
	 * Save file content (with version history backup).
	 *
	 * @param int    $zip_id    ZIP ID.
	 * @param string $file_path Relative file path.
	 * @param string $content   New file content.
	 * @return array
	 */
	public function save_file_content( $zip_id, $file_path, $content ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_path = AD_SD_WSC_Helpers::sanitize_file_path( $file_path );
		$full_path = $zip->extract_path . '/' . $safe_path;

		$real_extract = realpath( $zip->extract_path );
		// Normalize before realpath since file may be new.
		$normalized = realpath( dirname( $full_path ) );
		if ( false === $normalized || 0 !== strpos( $normalized, $real_extract ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid file path.', 'ad-sd-static-connector' ) );
		}

		// Block PHP injection.
		if ( false !== strpos( $content, '<?php' ) || false !== strpos( $content, '<?=' ) ) {
			return array( 'success' => false, 'message' => __( 'PHP code is not allowed in static files.', 'ad-sd-static-connector' ) );
		}

		// Create version backup before saving.
		if ( file_exists( $full_path ) ) {
			$this->create_version_backup( $zip_id, $safe_path, $full_path );
		}

		// Save new content — raw write; PHP injection already blocked above.
		// wp_kses_post() is intentionally NOT used here because it strips valid
		// HTML attributes (data-*, SVG, canvas, custom elements) that static
		// site files legitimately contain.
		$result = adsd_file_put_contents( $full_path, $content );
		if ( false === $result ) {
			return array( 'success' => false, 'message' => __( 'Could not save file. Check folder permissions.', 'ad-sd-static-connector' ) );
		}

		AD_SD_WSC_Helpers::log_activity( 'file_saved', $zip_id, $safe_path );

		// ── Keep the ZIP archive in sync with the edited file ────────────
		// If this ZIP is the currently live one, the extract_path folder is
		// already updated above. We also update the ZIP
		// archive on disk so future downloads/re-deploys contain the new file.
		$this->sync_file_to_zip( $zip, $safe_path, $full_path );

		return array( 'success' => true, 'message' => __( 'File saved successfully.', 'ad-sd-static-connector' ) );
	}

	/**
	 * Update a single file inside the ZIP archive without re-extracting everything.
	 *
	 * @param object $zip       ZIP DB row (id, file_path, extract_path).
	 * @param string $safe_path Relative path inside ZIP.
	 * @param string $full_path Absolute path to the updated file on disk.
	 * @return void
	 */
	private function sync_file_to_zip( $zip, $safe_path, $full_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return;
		}
		// DB column is zip_path (not file_path).
		$zip_file = isset( $zip->zip_path ) ? $zip->zip_path : '';
		if ( ! $zip_file || ! file_exists( $zip_file ) ) {
			return;
		}
		$za = new ZipArchive();
		if ( true !== $za->open( $zip_file ) ) {
			return;
		}
		// Determine the ZIP-internal entry name.
		// Files are stored like "foldername/index.html", so prepend the ZIP root if needed.
		$entry_name = $safe_path;
		// If first entry has a common prefix (the top-level folder), use it.
		if ( $za->numFiles > 0 ) {
			$first = $za->getNameIndex( 0 );
			$slash = strpos( $first, '/' );
			if ( false !== $slash ) {
				$prefix = substr( $first, 0, $slash + 1 );
				// Only prepend if the entry doesn't already exist without prefix.
				if ( false === $za->locateName( $safe_path ) && false !== $za->locateName( $prefix . $safe_path ) ) {
					$entry_name = $prefix . $safe_path;
				}
			}
		}
		$content = adsd_file_get_contents( $full_path );
		if ( false !== $content ) {
			$za->addFromString( $entry_name, $content );
		}
		$za->close();
	}

	/**
	 * Create a version history backup of a file.
	 *
	 * @param int    $zip_id    ZIP ID.
	 * @param string $rel_path  Relative path.
	 * @param string $full_path Full filesystem path.
	 * @return void
	 */
	private function create_version_backup( $zip_id, $rel_path, $full_path ) {
		$backup_dir = wp_upload_dir()['basedir'] . '/ad-sd-wsc/backups/zip_' . $zip_id;
		wp_mkdir_p( $backup_dir );

		$safe_rel   = str_replace( '/', '_', $rel_path );
		$timestamp  = time();
		$backup_file = $backup_dir . '/' . $safe_rel . '_' . $timestamp . '.bak';

		copy( $full_path, $backup_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Keep only last 10 versions.
		$pattern  = $backup_dir . '/' . $safe_rel . '_*.bak';
		$versions = glob( $pattern );
		if ( $versions && count( $versions ) > 10 ) {
			sort( $versions );
			$to_delete = array_slice( $versions, 0, count( $versions ) - 10 );
			foreach ( $to_delete as $old ) {
				wp_delete_file( $old );
			}
		}
	}

	/**
	 * Get version history for a file.
	 *
	 * @param int    $zip_id   ZIP ID.
	 * @param string $rel_path Relative file path.
	 * @return array
	 */
	public function get_version_history( $zip_id, $rel_path ) {
		$backup_dir = wp_upload_dir()['basedir'] . '/ad-sd-wsc/backups/zip_' . absint( $zip_id );
		$safe_rel   = str_replace( '/', '_', AD_SD_WSC_Helpers::sanitize_file_path( $rel_path ) );
		$pattern    = $backup_dir . '/' . $safe_rel . '_*.bak';
		$versions   = glob( $pattern );
		$result     = array();

		if ( $versions ) {
			rsort( $versions );
			foreach ( $versions as $v ) {
				$filename  = basename( $v );
				$parts     = explode( '_', $filename );
				$timestamp = (int) end( $parts );
				$result[]  = array(
					'timestamp'  => $timestamp,
					'date'       => gmdate( 'Y-m-d H:i:s', $timestamp ),
					'size'       => AD_SD_WSC_Helpers::format_file_size( filesize( $v ) ),
					'backup_key' => sanitize_file_name( $filename ),
				);
			}
		}

		return array( 'success' => true, 'versions' => $result );
	}

	/**
	 * Restore a specific version of a file.
	 *
	 * @param int    $zip_id     ZIP ID.
	 * @param string $rel_path   Relative file path.
	 * @param int    $timestamp  Version timestamp.
	 * @return array
	 */
	public function restore_version( $zip_id, $rel_path, $timestamp ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_rel   = str_replace( '/', '_', AD_SD_WSC_Helpers::sanitize_file_path( $rel_path ) );
		$backup_dir = wp_upload_dir()['basedir'] . '/ad-sd-wsc/backups/zip_' . absint( $zip_id );
		$backup_file = $backup_dir . '/' . $safe_rel . '_' . absint( $timestamp ) . '.bak';

		if ( ! file_exists( $backup_file ) ) {
			return array( 'success' => false, 'message' => __( 'Version backup not found.', 'ad-sd-static-connector' ) );
		}

		$safe_path = AD_SD_WSC_Helpers::sanitize_file_path( $rel_path );
		$dest      = $zip->extract_path . '/' . $safe_path;

		// Ensure the restore destination stays inside the extract dir —
		// same containment check used by save/delete/create, so a crafted
		// $rel_path can never write outside the ZIP's own folder.
		$real_extract = realpath( $zip->extract_path );
		$real_parent  = realpath( dirname( $dest ) );
		if ( ! $real_extract || ! $real_parent || 0 !== strpos( $real_parent, $real_extract ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid file path.', 'ad-sd-static-connector' ) );
		}

		copy( $backup_file, $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		AD_SD_WSC_Helpers::log_activity( 'file_restored', $zip_id, $rel_path . ' @ ' . $timestamp );

		return array( 'success' => true, 'message' => __( 'File restored to selected version.', 'ad-sd-static-connector' ) );
	}

	/**
	 * Delete a file from the extracted directory.
	 *
	 * @param int    $zip_id   ZIP ID.
	 * @param string $rel_path Relative file path.
	 * @return array
	 */
	public function delete_file( $zip_id, $rel_path ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_path = AD_SD_WSC_Helpers::sanitize_file_path( $rel_path );
		$full_path = $zip->extract_path . '/' . $safe_path;

		$real_extract = realpath( $zip->extract_path );
		$real_file    = realpath( $full_path );

		if ( false === $real_file || 0 !== strpos( $real_file, $real_extract ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid file path.', 'ad-sd-static-connector' ) );
		}

		wp_delete_file( $real_file );
		AD_SD_WSC_Helpers::log_activity( 'file_deleted', $zip_id, $safe_path );

		return array( 'success' => true, 'message' => __( 'File deleted successfully.', 'ad-sd-static-connector' ) );
	}
	/**
	 * Create a new file inside the extracted ZIP directory.
	 *
	 * @param int    $zip_id    ZIP ID.
	 * @param string $file_path Relative file path (e.g. "pages/contact.html").
	 * @param string $content   Initial file content (empty string is fine).
	 * @return array
	 */
	public function create_file( $zip_id, $file_path, $content = '' ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_path = AD_SD_WSC_Helpers::sanitize_file_path( $file_path );
		if ( '' === $safe_path ) {
			return array( 'success' => false, 'message' => __( 'Invalid file name.', 'ad-sd-static-connector' ) );
		}

		// Block PHP files.
		$ext = strtolower( pathinfo( $safe_path, PATHINFO_EXTENSION ) );
		$blocked_ext = array( 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'exe', 'sh', 'bat' );
		if ( in_array( $ext, $blocked_ext, true ) ) {
			return array( 'success' => false, 'message' => __( 'This file type is not allowed.', 'ad-sd-static-connector' ) );
		}

		$full_path    = $zip->extract_path . '/' . $safe_path;
		$real_extract = realpath( $zip->extract_path );

		// The file doesn't exist yet so we check its parent directory.
		$parent_dir  = dirname( $full_path );
		$real_parent = realpath( $parent_dir );
		if ( ! $real_parent ) {
			// Try to create parent directory.
			wp_mkdir_p( $parent_dir );
			$real_parent = realpath( $parent_dir );
		}
		if ( ! $real_parent || 0 !== strpos( $real_parent, $real_extract ) ) {
			return array( 'success' => false, 'message' => __( 'Invalid file path.', 'ad-sd-static-connector' ) );
		}

		if ( file_exists( $full_path ) ) {
			return array( 'success' => false, 'message' => __( 'A file with this name already exists.', 'ad-sd-static-connector' ) );
		}

		// Block PHP injection in content.
		if ( false !== strpos( $content, '<?php' ) || false !== strpos( $content, '<?=' ) ) {
			return array( 'success' => false, 'message' => __( 'PHP code is not allowed in static files.', 'ad-sd-static-connector' ) );
		}

		$result = adsd_file_put_contents( $full_path, $content );
		if ( false === $result ) {
			return array( 'success' => false, 'message' => __( 'Could not create file. Check folder permissions.', 'ad-sd-static-connector' ) );
		}

		// Sync new file into the ZIP archive.
		$this->sync_file_to_zip( $zip, $safe_path, $full_path );

		AD_SD_WSC_Helpers::log_activity( 'file_created', $zip_id, $safe_path );

		return array(
			'success'   => true,
			'message'   => __( 'File created successfully.', 'ad-sd-static-connector' ),
			'file_path' => $safe_path,
		);
	}

	/**
	 * List all files in an extracted ZIP for the JS file tree.
	 *
	 * @param int $zip_id ZIP ID.
	 * @return void Sends JSON directly.
	 */
	public function list_zip_files( $zip_id ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			wp_send_json( array( 'files' => array() ) );
			return;
		}
		$raw   = AD_SD_WSC_Helpers::list_files_recursive( $zip->extract_path );
		$files = array();
		foreach ( $raw as $f ) {
			$files[] = array(
				'name' => $f['name'],
				'path' => $f['path'],
				'ext'  => $f['ext'],
				'size' => AD_SD_WSC_Helpers::format_file_size( $f['size'] ),
			);
		}
		// FIX: Use wp_send_json_success so the JS can use res.success + res.data.files
		// consistently. The JS loadActualFileTree still checks res.files as a fallback
		// for backward compatibility.
		wp_send_json_success( array( 'files' => $files ) );
	}

}
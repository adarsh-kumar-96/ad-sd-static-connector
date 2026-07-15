<?php
/**
 * Handles ZIP upload, extraction, validation, and deletion.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Zip_Handler
 */
class AD_SD_WSC_Zip_Handler {

	/**
	 * Upload and extract a ZIP file.
	 *
	 * @param array $file $_FILES['zip_file'] entry.
	 * @return array { success: bool, message: string, data: array }
	 */
	public function upload( $file ) {
		// Basic upload validation.
		if ( empty( $file ) || UPLOAD_ERR_OK !== $file['error'] ) {
			return array(
				'success' => false,
				'message' => __( 'File upload failed. Please try again.', 'ad-sd-static-connector' ),
			);
		}

		// MIME check — must be a ZIP.
		$finfo     = new finfo( FILEINFO_MIME_TYPE );
		$mime_type = $finfo->file( $file['tmp_name'] );
		$allowed   = array( 'application/zip', 'application/x-zip-compressed', 'application/x-zip', 'multipart/x-zip' );
		if ( ! in_array( $mime_type, $allowed, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid file type. Please upload a valid ZIP file.', 'ad-sd-static-connector' ),
			);
		}

		// Size check — use actual filesize from tmp_name, not $_FILES['size'] which can be spoofed.
		$max_mb      = absint( get_option( 'adsd_max_zip_size_mb', 50 ) );
		$max_size    = $max_mb * 1024 * 1024;
		$actual_size = file_exists( $file['tmp_name'] ) ? filesize( $file['tmp_name'] ) : 0;
		if ( $actual_size <= 0 || $actual_size > $max_size ) {
			return array(
				'success' => false,
				/* translators: %d: max allowed size in MB */
				'message' => sprintf( __( 'File too large. Maximum allowed size is %d MB.', 'ad-sd-static-connector' ), $max_mb ),
			);
		}

		// Build destination paths.
		$upload_base  = wp_upload_dir()['basedir'] . '/ad-sd-wsc';
		$zip_dir      = $upload_base . '/zips';
		$extract_base = $upload_base . '/extracted';

		// Ensure directories exist before trying to move file into them.
		if ( ! wp_mkdir_p( $zip_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create upload directory. Check server folder permissions.', 'ad-sd-static-connector' ),
			);
		}
		wp_mkdir_p( $extract_base );

		// Protect the upload folder from direct PHP execution.
		$htaccess = $upload_base . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Order allow,deny\n  Deny from all\n</FilesMatch>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, $rules );
		}

		$safe_name   = sanitize_file_name( $file['name'] );
		$unique_name = wp_unique_filename( $zip_dir, $safe_name );
		$zip_dest    = $zip_dir . '/' . $unique_name;

		// Move uploaded file — must use move_uploaded_file() for PHP uploaded temp files.
		// WP_Filesystem::move() cannot handle PHP upload temp paths.
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
		if ( ! move_uploaded_file( $file['tmp_name'], $zip_dest ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not save the uploaded file. Check server folder permissions.', 'ad-sd-static-connector' ),
			);
		}

		// Extract ZIP.
		$folder_name    = preg_replace( '/\.zip$/i', '', $unique_name ) . '_' . time();
		$extract_path   = $extract_base . '/' . $folder_name;
		$extract_result = $this->extract_zip( $zip_dest, $extract_path );
		if ( ! $extract_result['success'] ) {
			wp_delete_file( $zip_dest );
			return $extract_result;
		}

		// Remove any macOS metadata artifacts from the extracted files.
		AD_SD_WSC_Helpers::purge_macos_artifacts( $extract_path );

		// Scan extracted files for PHP/dangerous content.
		$scan_result = $this->scan_extracted_files( $extract_path );
		if ( ! $scan_result['success'] ) {
			wp_delete_file( $zip_dest );
			$this->delete_directory( $extract_path );
			return $scan_result;
		}

		// Ensure DB tables exist (handles cases where activation hook didn't run).
		AD_SD_WSC_Activator::maybe_create_tables();

		// Save to DB — use actual_size (already verified above), not spoofable $_FILES['size'].
		global $wpdb;
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'adsd_zip_files',
			array(
				'file_name'    => $unique_name,
				'zip_path'     => $zip_dest,
				'extract_path' => $extract_path,
				'file_size'    => $actual_size,
				'status'       => 'inactive',
				'uploaded_by'  => get_current_user_id(),
				'uploaded_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			// DB insert failed — clean up extracted files and ZIP.
			wp_delete_file( $zip_dest );
			$this->delete_directory( $extract_path );
			return array(
				'success' => false,
				/* translators: %s: database error message */
				'message' => sprintf( __( 'Database error: could not save upload record. %s', 'ad-sd-static-connector' ), $wpdb->last_error ),
			);
		}

		$zip_id = $wpdb->insert_id;

		AD_SD_WSC_Helpers::log_activity( 'zip_uploaded', $zip_id, $unique_name );

		return array(
			'success' => true,
			'message' => __( 'ZIP file uploaded and extracted successfully.', 'ad-sd-static-connector' ),
			'data'    => array(
				'id'          => $zip_id,
				'file_name'   => $unique_name,
				'uploaded_at' => current_time( 'mysql' ),
				'file_size'   => AD_SD_WSC_Helpers::format_file_size( $file['size'] ),
			),
		);
	}

	/**
	 * Extract a ZIP file to a directory.
	 *
	 * @param string $zip_path     Path to ZIP.
	 * @param string $extract_path Destination directory.
	 * @return array
	 */
	private function extract_zip( $zip_path, $extract_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array(
				'success' => false,
				'message' => __( 'ZipArchive PHP extension is not available on this server.', 'ad-sd-static-connector' ),
			);
		}

		$zip = new ZipArchive();
		$res = $zip->open( $zip_path );
		if ( true !== $res ) {
			return array(
				'success' => false,
				'message' => __( 'Could not open the ZIP file. It may be corrupted.', 'ad-sd-static-connector' ),
			);
		}

		wp_mkdir_p( $extract_path );

		// Extract file by file to prevent path traversal.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->statIndex( $i );
			if ( false === $entry ) {
				continue;
			}

			$entry_name = $entry['name'];

			// Skip macOS metadata artifacts (__MACOSX folder and AppleDouble ._files).
			if ( 0 === strpos( $entry_name, '__MACOSX/' )
				|| false !== strpos( $entry_name, '/__MACOSX/' )
				|| '._' === substr( basename( $entry_name ), 0, 2 )
				|| '.DS_Store' === basename( $entry_name )
			) {
				continue;
			}

			// Skip entries with path traversal patterns.
			if ( false !== strpos( $entry_name, '..' ) || 0 === strpos( $entry_name, '/' ) ) {
				continue;
			}

			// Block PHP and other executable files.
			$ext = strtolower( pathinfo( $entry_name, PATHINFO_EXTENSION ) );
			$blocked_ext = array( 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'exe', 'sh', 'bat', 'py', 'rb', 'pl' );
			if ( in_array( $ext, $blocked_ext, true ) ) {
				continue;
			}

			$dest = $extract_path . '/' . $entry_name;
			$dir  = dirname( $dest );

			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			if ( '/' === substr( $entry_name, -1 ) ) {
				// It's a directory entry.
				wp_mkdir_p( $dest );
			} else {
				$content = $zip->getFromIndex( $i );
				if ( false !== $content ) {
					adsd_file_put_contents( $dest, $content );
				}
			}
		}
		$zip->close();

		return array( 'success' => true );
	}

	/**
	 * Scan extracted files for dangerous content.
	 *
	 * @param string $dir Directory to scan.
	 * @return array
	 */
	private function scan_extracted_files( $dir ) {
		$files = AD_SD_WSC_Helpers::list_files_recursive( $dir );
		foreach ( $files as $file ) {
			// Check for PHP tags in HTML/JS/CSS files.
			if ( in_array( $file['ext'], array( 'html', 'htm', 'js', 'css', 'svg' ), true ) ) {
				$content = adsd_file_get_contents( $file['full_path'] );
				if ( false !== $content && ( false !== strpos( $content, '<?php' ) || false !== strpos( $content, '<?=' ) ) ) {
					return array(
						'success' => false,
						/* translators: %s: file name */
						'message' => sprintf( __( 'Security risk detected in file: %s. PHP code is not allowed in static files.', 'ad-sd-static-connector' ), esc_html( $file['name'] ) ),
					);
				}
			}
		}
		return array( 'success' => true );
	}

	/**
	 * Perform a code check on an extracted ZIP and return errors.
	 *
	 * @param int $zip_id ZIP record ID.
	 * @return array
	 */
	public function check_code( $zip_id ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP record not found.', 'ad-sd-static-connector' ) );
		}

		$files  = AD_SD_WSC_Helpers::list_files_recursive( $zip->extract_path );
		$errors = array();

		foreach ( $files as $file ) {
			if ( ! in_array( $file['ext'], array( 'html', 'htm', 'css', 'js' ), true ) ) {
				continue;
			}
				$content = adsd_file_get_contents( $file['full_path'] );
			if ( false === $content ) {
				continue;
			}
			$file_errors = $this->analyze_code( $content, $file['ext'], $file['path'] );
			$errors      = array_merge( $errors, $file_errors );
		}

		return array(
			'success' => true,
			'errors'  => $errors,
			'count'   => count( $errors ),
		);
	}

	/**
	 * Basic code analysis for common issues.
	 *
	 * @param string $content File content.
	 * @param string $ext     File extension.
	 * @param string $path    Relative file path for reporting.
	 * @return array
	 */
	private function analyze_code( $content, $ext, $path ) {
		$errors = array();
		$lines  = explode( "\n", $content );

		if ( 'html' === $ext || 'htm' === $ext ) {
			foreach ( $lines as $num => $line ) {
				// Check missing alt attributes on images.
				if ( preg_match( '/<img(?![^>]*\balt\b)[^>]*>/i', $line ) ) {
					$errors[] = array(
						'file'    => $path,
						'line'    => $num + 1,
						'type'    => 'warning',
						'message' => __( 'Image tag missing alt attribute (accessibility issue).', 'ad-sd-static-connector' ),
					);
				}
				// Check inline styles (not an error but a note).
				if ( preg_match( '/\sstyle\s*=/i', $line ) ) {
					$errors[] = array(
						'file'    => $path,
						'line'    => $num + 1,
						'type'    => 'info',
						'message' => __( 'Inline style detected. Consider using a separate CSS file.', 'ad-sd-static-connector' ),
					);
				}
				// Broken/empty href.
				if ( preg_match( '/href\s*=\s*["\']#["\']/i', $line ) ) {
					$errors[] = array(
						'file'    => $path,
						'line'    => $num + 1,
						'type'    => 'warning',
						'message' => __( 'Empty anchor link (#) detected.', 'ad-sd-static-connector' ),
					);
				}
				// Absolute URL references that may break on server.
				if ( preg_match( '/(?:src|href)\s*=\s*["\']http/i', $line ) ) {
					$errors[] = array(
						'file'    => $path,
						'line'    => $num + 1,
						'type'    => 'info',
						'message' => __( 'Absolute external URL found. Ensure it is intentional.', 'ad-sd-static-connector' ),
					);
				}
			}
		}

		if ( 'css' === $ext ) {
			foreach ( $lines as $num => $line ) {
				if ( preg_match( '/@import\s+["\']http/i', $line ) ) {
					$errors[] = array(
						'file'    => $path,
						'line'    => $num + 1,
						'type'    => 'info',
						'message' => __( 'External CSS @import detected. This may slow page load.', 'ad-sd-static-connector' ),
					);
				}
			}
		}

		return $errors;
	}

	/**
	 * Delete a ZIP record and its files.
	 *
	 * @param int $zip_id ZIP record ID.
	 * @return array
	 */
	public function delete_zip( $zip_id ) {
		global $wpdb;
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP record not found.', 'ad-sd-static-connector' ) );
		}

		// Prevent deleting the currently live ZIP.
		$live_id = absint( get_option( 'adsd_live_zip_id', 0 ) );
		if ( $live_id === absint( $zip_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot delete the currently live ZIP. Stop live first.', 'ad-sd-static-connector' ),
			);
		}

		// Remove files.
		if ( ! empty( $zip->zip_path ) && file_exists( $zip->zip_path ) ) {
			wp_delete_file( $zip->zip_path );
		}
		if ( ! empty( $zip->extract_path ) && is_dir( $zip->extract_path ) ) {
			$this->delete_directory( $zip->extract_path );
		}

		// Remove DB records.
		$table = $wpdb->prefix . 'adsd_zip_files';
		$wpdb->delete( $table, array( 'id' => absint( $zip_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$wpdb->delete( $wpdb->prefix . 'adsd_seo_settings', array( 'zip_id' => absint( $zip_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'adsd_mapping', array( 'zip_id' => absint( $zip_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		AD_SD_WSC_Helpers::log_activity( 'zip_deleted', $zip_id, $zip->file_name );

		return array( 'success' => true, 'message' => __( 'ZIP file deleted successfully.', 'ad-sd-static-connector' ) );
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	public function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// Use WP_Filesystem to remove empty directory.
		adsd_rmdir( $dir );
	}
}

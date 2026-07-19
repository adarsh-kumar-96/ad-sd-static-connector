<?php
/**
 * Helper utility functions.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Helpers
 */
class AD_SD_WSC_Helpers {

	/**
	 * Sanitize a file path to prevent path traversal attacks.
	 *
	 * @param string $path Raw path.
	 * @return string Sanitized path.
	 */
	public static function sanitize_file_path( $path ) {
		// Decode percent-encoding (including double-encoding) so %2e%2e%2f
		// and similar tricks cannot survive as literal traversal sequences.
		for ( $i = 0; $i < 3; $i++ ) {
			$decoded = rawurldecode( $path );
			if ( $decoded === $path ) {
				break;
			}
			$path = $decoded;
		}

		// Remove null bytes and normalise backslashes to forward slashes.
		$path = str_replace( array( "\0", '\\' ), array( '', '/' ), $path );

		// Strip characters outside our safe allow-list. '.' and '/' stay
		// allowed here so normal file/folder names keep working; traversal
		// is handled below on path *segments* instead, which can't be
		// bypassed by repeating/interleaving characters the way a
		// single-pass str_replace('../', '') could be.
		$path = preg_replace( '/[^a-zA-Z0-9_\-.\/\s@()\[\]+]/', '', $path );

		// Rebuild the path from its '/'-separated segments, dropping any
		// empty, current-dir ('.'), or parent-dir ('..') segment outright.
		// Static site files never legitimately need to reference a parent
		// directory, so segments are discarded rather than "resolved" —
		// this closes bypasses like "....//" that abuse a single find/replace
		// pass (removing one "../" from "....//" used to leave "../" behind).
		$segments      = explode( '/', $path );
		$safe_segments = array();
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				continue;
			}
			$safe_segments[] = $segment;
		}

		return implode( '/', $safe_segments );
	}

	/**
	 * Check if a file extension is allowed.
	 *
	 * @param string $filename File name.
	 * @return bool
	 */
	public static function is_allowed_extension( $filename ) {
		$allowed = get_option( 'adsd_allowed_file_types', array( 'html', 'htm', 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'eot', 'ico', 'json', 'xml', 'txt' ) );
		$ext     = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return in_array( $ext, $allowed, true );
	}

	/**
	 * Format bytes to human-readable size.
	 *
	 * @param int $bytes File size in bytes.
	 * @return string
	 */
	public static function format_file_size( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		}
		if ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 2 ) . ' MB';
		}
		if ( $bytes >= 1024 ) {
			return number_format( $bytes / 1024, 2 ) . ' KB';
		}
		return $bytes . ' B';
	}

	/**
	 * Log an activity to the DB.
	 *
	 * @param string $action    Action identifier.
	 * @param int    $object_id Related object ID.
	 * @param string $details   Human-readable detail.
	 * @return void
	 */
	public static function log_activity( $action, $object_id = 0, $details = '' ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'adsd_activity_logs',
			array(
				'user_id'   => get_current_user_id(),
				'action'    => sanitize_text_field( $action ),
				'object_id' => absint( $object_id ),
				'details'   => sanitize_textarea_field( $details ),
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get all zip files from DB.
	 *
	 * @return array
	 */
	public static function get_all_zips() {
		global $wpdb;
		$table   = esc_sql( $wpdb->prefix . 'adsd_zip_files' );
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY uploaded_at DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get a single zip record by ID.
	 *
	 * @param int $zip_id ZIP ID.
	 * @return object|null
	 */
	public static function get_zip_by_id( $zip_id ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'adsd_zip_files' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $zip_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Validate a nonce and die on failure.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Nonce action.
	 * @return void
	 */
	public static function verify_nonce( $nonce, $action ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'ad-sd-static-connector' ) ), 403 );
		}
	}

	/**
	 * Check if current user can manage plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get MIME type for a file extension.
	 *
	 * @param string $ext File extension.
	 * @return string
	 */
	public static function get_mime_type( $ext ) {
		$mimes = array(
			'html' => 'text/html',
			'htm'  => 'text/html',
			'css'  => 'text/css',
			'js'   => 'application/javascript',
			'json' => 'application/json',
			'xml'  => 'text/xml',
			'svg'  => 'image/svg+xml',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'ico'  => 'image/x-icon',
			'woff' => 'font/woff',
			'woff2'=> 'font/woff2',
			'ttf'  => 'font/ttf',
			'eot'  => 'application/vnd.ms-fontobject',
			'txt'  => 'text/plain',
		);
		return isset( $mimes[ $ext ] ) ? $mimes[ $ext ] : 'application/octet-stream';
	}

	/**
	 * Recursively list files in a directory.
	 *
	 * @param string $dir     Directory path.
	 * @param string $base    Base path for relative paths.
	 * @return array
	 */
	public static function list_files_recursive( $dir, $base = '' ) {
		$files  = array();
		if ( ! is_dir( $dir ) ) {
			return $files;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item || '.htaccess' === $item || 'index.php' === $item ) {
				continue;
			}
			// Skip macOS metadata: __MACOSX folder, AppleDouble ._files, .DS_Store.
			if ( '__MACOSX' === $item || '.DS_Store' === $item || '._' === substr( $item, 0, 2 ) ) {
				continue;
			}
			$full_path = $dir . '/' . $item;
			$rel_path  = $base ? $base . '/' . $item : $item;
			// Also skip if we are already inside a __MACOSX sub-path.
			if ( false !== strpos( $rel_path, '__MACOSX' ) ) {
				continue;
			}
			if ( is_dir( $full_path ) ) {
				$files = array_merge( $files, self::list_files_recursive( $full_path, $rel_path ) );
			} else {
				$ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
				$files[] = array(
					'name'      => $item,
					'path'      => $rel_path,
					'full_path' => $full_path,
					'size'      => filesize( $full_path ),
					'ext'       => $ext,
					'modified'  => filemtime( $full_path ),
				);
			}
		}
		return $files;
	}

	/**
	 * Remove macOS metadata artifacts from an extracted directory.
	 * Cleans up __MACOSX folders, AppleDouble ._files, and .DS_Store files
	 * that may have been extracted from ZIPs created on macOS.
	 *
	 * @param string $dir Root directory to clean.
	 * @return void
	 */
	public static function purge_macos_artifacts( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$full = $dir . '/' . $item;
			// Remove __MACOSX directory entirely.
			if ( '__MACOSX' === $item && is_dir( $full ) ) {
				self::delete_directory_static( $full );
				continue;
			}
			// Remove AppleDouble resource-fork files and .DS_Store.
			if ( is_file( $full ) && ( '.DS_Store' === $item || '._' === substr( $item, 0, 2 ) ) ) {
				wp_delete_file( $full );
				continue;
			}
			// Recurse into sub-directories.
			if ( is_dir( $full ) ) {
				self::purge_macos_artifacts( $full );
			}
		}
	}

	/**
	 * Recursively delete a directory (static version).
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function delete_directory_static( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) { continue; }
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::delete_directory_static( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// Use WP_Filesystem to remove empty directory.
		adsd_rmdir( $dir );
	}

	/**
	 * Replace the {{page-title}} placeholder inside injected header/footer
	 * HTML with the actual title of the page being viewed.
	 *
	 * Usage: user types {{page-title}} anywhere inside their Header/Footer
	 * HTML in the WP Page Injector tab, and it gets swapped for the current
	 * page's title at render time. Matching is case-insensitive and allows
	 * optional spaces, so {{page-title}}, {{ page-title }}, {{Page-Title}}
	 * all work.
	 *
	 * @param string $html  Raw header/footer HTML that may contain the placeholder.
	 * @param string $title Page title to insert in place of the placeholder.
	 * @return string
	 */
	public static function replace_page_title_placeholder( $html, $title ) {
		if ( '' === $html || false === strpos( strtolower( $html ), 'page-title' ) ) {
			return $html;
		}
		return preg_replace(
			'/\{\{\s*page-title\s*\}\}/i',
			esc_html( $title ),
			$html
		);
	}

	/**
	 * Get the public URL for a file inside a ZIP's extract directory,
	 * used as a <base> href so the live preview iframe can resolve
	 * relative asset paths (css/js/images) instead of 404'ing.
	 *
	 * @param string $extract_path Absolute extract directory path.
	 * @param string $rel_path     File's relative path inside the extract dir.
	 * @return string
	 */
	public static function get_extract_url( $extract_path, $rel_path ) {
		$upload_dir = wp_upload_dir();
		$basedir    = $upload_dir['basedir'];
		$baseurl    = $upload_dir['baseurl'];

		$extract_url = $baseurl . substr( $extract_path, strlen( $basedir ) );

		// Folder containing this file, so a trailing slash gives a correct base.
		$dir = dirname( $rel_path );
		if ( '.' !== $dir && '' !== $dir ) {
			$extract_url .= '/' . $dir;
		}

		return trailingslashit( $extract_url );
	}
}

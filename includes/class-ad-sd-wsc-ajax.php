<?php
/**
 * All AJAX action handlers.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Ajax
 */
class AD_SD_WSC_Ajax {

	/**
	 * Constructor: register all AJAX hooks.
	 */
	public function __construct() {
		$actions = array(
			'adsd_upload_zip'           => 'handle_upload_zip',
			'adsd_check_code'           => 'handle_check_code',
			'adsd_delete_zip'           => 'handle_delete_zip',
			'adsd_get_zip_files'        => 'handle_get_zip_files',
			'adsd_get_file_content'     => 'handle_get_file_content',
			'adsd_save_file_content'    => 'handle_save_file_content',
			'adsd_get_version_history'  => 'handle_get_version_history',
			'adsd_restore_version'      => 'handle_restore_version',
			'adsd_delete_file'          => 'handle_delete_file',
			'adsd_create_file'          => 'handle_create_file',
			'adsd_download_file'        => 'handle_download_file',
			'adsd_get_seo'              => 'handle_get_seo',
			'adsd_save_seo'             => 'handle_save_seo',
			'adsd_auto_seo'             => 'handle_auto_seo',
			'adsd_go_live'              => 'handle_go_live',
			'adsd_stop_live'            => 'handle_stop_live',
			'adsd_get_live_info'        => 'handle_get_live_info',
			'adsd_gen_shortcode_div'    => 'handle_gen_shortcode_div',
			'adsd_gen_filter_div'       => 'handle_gen_filter_div',
			'adsd_get_layouts'          => 'handle_get_layouts',
			'adsd_get_terms'            => 'handle_get_terms',
			'adsd_save_layout'          => 'handle_save_layout',
			'adsd_delete_layout'        => 'handle_delete_layout',
			'adsd_reset_layout'         => 'handle_reset_layout',
			'adsd_get_logs'             => 'handle_get_logs',
			'adsd_save_settings'        => 'handle_save_settings',
			'adsd_get_error_log'        => 'handle_get_error_log',
			'adsd_clear_error_log'      => 'handle_clear_error_log',
			'adsd_save_injector'        => 'handle_save_injector',
			'adsd_get_injector'         => 'handle_get_injector',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	// ─────────────────────────────────────────────────
	// DASHBOARD / ZIP HANDLERS
	// ─────────────────────────────────────────────────

	/** Upload ZIP. */
	public function handle_upload_zip() {
		$this->check_admin( 'adsd_upload_nonce' );
		$file    = isset( $_FILES['zip_file'] ) ? $_FILES['zip_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing
		$handler = new AD_SD_WSC_Zip_Handler();
			$this->send( $handler->upload( $file ) );
	}

	/** Check code quality of a ZIP. */
	public function handle_check_code() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id  = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$handler = new AD_SD_WSC_Zip_Handler();
			$this->send( $handler->check_code( $zip_id ) );
	}

	/** Delete a ZIP. */
	public function handle_delete_zip() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id  = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$handler = new AD_SD_WSC_Zip_Handler();
			$this->send( $handler->delete_zip( $zip_id ) );
	}

	/** Get all ZIPs for dashboard cards. */
	public function handle_get_zip_files() {
		$this->check_admin( 'adsd_nonce' );
		$zips   = AD_SD_WSC_Helpers::get_all_zips();
		$output = array();
		foreach ( $zips as $zip ) {
			$output[] = array(
				'id'          => $zip->id,
				'file_name'   => $zip->file_name,
				'file_size'   => AD_SD_WSC_Helpers::format_file_size( $zip->file_size ),
				'status'      => $zip->status,
				'uploaded_at' => $zip->uploaded_at,
			);
		}
		wp_send_json_success( array( 'zips' => $output ) );
	}

	// ─────────────────────────────────────────────────
	// FILE MANAGER HANDLERS
	// ─────────────────────────────────────────────────

	/** Get file content for editor. */
	public function handle_get_file_content() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id    = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$file_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$manager   = new AD_SD_WSC_File_Manager();
			$this->send( $manager->get_file_content( $zip_id, $file_path ) );
	}

	/** Save file content. */
	public function handle_save_file_content() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id    = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$file_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$content   = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$manager   = new AD_SD_WSC_File_Manager();
			$this->send( $manager->save_file_content( $zip_id, $file_path, $content ) );
	}

	/** Get version history. */
	public function handle_get_version_history() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$manager  = new AD_SD_WSC_File_Manager();
			$this->send( $manager->get_version_history( $zip_id, $rel_path ) );
	}

	/** Restore a version. */
	public function handle_restore_version() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id    = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path  = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$timestamp = absint( $_POST['timestamp'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$manager   = new AD_SD_WSC_File_Manager();
			$this->send( $manager->restore_version( $zip_id, $rel_path, $timestamp ) );
	}

	/** Delete a file. */
	public function handle_delete_file() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$manager  = new AD_SD_WSC_File_Manager();
			$this->send( $manager->delete_file( $zip_id, $rel_path ) );
	}

	/** Create a new file inside the ZIP's extracted folder. */
	public function handle_create_file() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$content  = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$manager  = new AD_SD_WSC_File_Manager();
		$this->send( $manager->create_file( $zip_id, $rel_path, $content ) );
	}

	/** Download a single file from a ZIP's extracted folder. */
	public function handle_download_file() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			wp_send_json_error( array( 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) ) );
		}

		$safe_path    = AD_SD_WSC_Helpers::sanitize_file_path( $rel_path );
		$full_path    = $zip->extract_path . '/' . $safe_path;
		$real_extract = realpath( $zip->extract_path );
		$real_file    = realpath( $full_path );

		if ( ! $real_file || 0 !== strpos( $real_file, $real_extract ) || ! is_file( $real_file ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'ad-sd-static-connector' ) ) );
		}

		// Send download URL as a temporary signed token approach — simpler: send base64 content.
		$ext     = strtolower( pathinfo( $real_file, PATHINFO_EXTENSION ) );
		$content = adsd_file_get_contents( $real_file );
		wp_send_json_success( array(
			'file_name' => basename( $real_file ),
			'content'   => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			'ext'       => $ext,
		) );
	}

	// ─────────────────────────────────────────────────
	// SEO HANDLERS
	// ─────────────────────────────────────────────────

	/** Get SEO settings. */
	public function handle_get_seo() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$seo      = new AD_SD_WSC_Seo_Manager();
			$this->send( $seo->get_seo( $zip_id, $rel_path ) );
	}

	/** Save SEO settings. */
	public function handle_save_seo() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		// Sanitize seo_data — only allow known SEO fields.
		$raw_seo  = isset( $_POST['seo_data'] ) ? (array) $_POST['seo_data'] : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$allowed_seo_keys = array( 'seo_title', 'meta_desc', 'meta_keywords', 'og_title', 'og_desc', 'og_image', 'canonical', 'robots', 'schema_type' );
		$data     = array_intersect_key( $raw_seo, array_flip( $allowed_seo_keys ) );
		$seo      = new AD_SD_WSC_Seo_Manager();
			$this->send( $seo->save_seo( $zip_id, $rel_path, $data ) );
	}

	/** Auto-generate a single SEO field. */
	public function handle_auto_seo() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id   = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$rel_path = isset( $_POST['file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['file_path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$field    = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$seo      = new AD_SD_WSC_Seo_Manager();
			$this->send( $seo->auto_generate_field( $zip_id, $rel_path, $field ) );
	}

	// ─────────────────────────────────────────────────
	// MAPPING HANDLERS
	// ─────────────────────────────────────────────────

	/** Go live. */
	public function handle_go_live() {
		$this->check_admin( 'adsd_nonce' );
		$zip_id    = absint( $_POST['zip_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$home_file = isset( $_POST['home_file'] ) ? sanitize_text_field( wp_unslash( $_POST['home_file'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$mapping   = new AD_SD_WSC_Mapping();
			$this->send( $mapping->go_live( $zip_id, $home_file ) );
	}

	/** Stop live. */
	public function handle_stop_live() {
		$this->check_admin( 'adsd_nonce' );
		$mapping = new AD_SD_WSC_Mapping();
			$this->send( $mapping->stop_live() );
	}

	/** Get live info. */
	public function handle_get_live_info() {
		$this->check_admin( 'adsd_nonce' );
		$mapping = new AD_SD_WSC_Mapping();
		wp_send_json_success( $mapping->get_live_info() );
	}

	// ─────────────────────────────────────────────────
	// SHORTCODE BRIDGE HANDLERS
	// ─────────────────────────────────────────────────

	/** Generate shortcode div. */
	public function handle_gen_shortcode_div() {
		$this->check_admin( 'adsd_nonce' );
		$shortcode = isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$bridge    = new AD_SD_WSC_Shortcode_Bridge();
			$this->send( $bridge->generate_shortcode_div( $shortcode ) );
	}

	/** Generate filter div. */
	public function handle_gen_filter_div() {
		$this->check_admin( 'adsd_nonce' );
		// Sanitize filter values — each must be a scalar, sanitized string or integer.
		$raw_filters = isset( $_POST['filters'] ) ? (array) $_POST['filters'] : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$filters = array();
		foreach ( $raw_filters as $key => $val ) {
			$safe_key = sanitize_key( $key );
			$filters[ $safe_key ] = is_array( $val )
				? array_map( 'sanitize_text_field', $val )
				: sanitize_text_field( wp_unslash( (string) $val ) );
		}
		$bridge  = new AD_SD_WSC_Shortcode_Bridge();
			$this->send( $bridge->generate_filter_div( $filters ) );
	}

	/** Get all layouts. */
	public function handle_get_layouts() {
		$this->check_admin( 'adsd_nonce' );
		$bridge  = new AD_SD_WSC_Shortcode_Bridge();
		$layouts = $bridge->get_all_layouts();
		wp_send_json_success( array( 'layouts' => $layouts ) );
	}

	/** Get category/tag term suggestions for a post type (autocomplete). */
	public function handle_get_terms() {
		$this->check_admin( 'adsd_nonce' );
		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! post_type_exists( $post_type ) ) {
			wp_send_json_success( array( 'categories' => array(), 'tags' => array() ) );
			return;
		}
		$bridge = new AD_SD_WSC_Shortcode_Bridge();
		wp_send_json_success( $bridge->get_terms_for_post_type( $post_type ) );
	}

	/** Save layout. */
	public function handle_save_layout() {
		$this->check_admin( 'adsd_nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'editor' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to save layouts.', 'ad-sd-static-connector' ) ), 403 );
		}
		// Sanitize layout keys — only allow known fields through.
		$raw_layout = isset( $_POST['layout'] ) ? (array) $_POST['layout'] : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$allowed_layout_keys = array( 'id', 'layout_name', 'plugin_type', 'template', 'layout_type' );
		$data = array_intersect_key( $raw_layout, array_flip( $allowed_layout_keys ) );
		$bridge = new AD_SD_WSC_Shortcode_Bridge();
		$result = $bridge->save_layout( $data );
		// A saved/edited layout must show up on the live static site right
		// away — without this, filter blocks using this layout keep serving
		// the old cached HTML for up to 24h (DAY_IN_SECONDS transient TTL).
		if ( ! empty( $result['success'] ) ) {
			adsd_flush_sc_cache();
		}
		$this->send( $result );
	}

	/** Delete layout. */
	public function handle_delete_layout() {
		$this->check_admin( 'adsd_nonce' );
		$id     = absint( $_POST['layout_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$bridge = new AD_SD_WSC_Shortcode_Bridge();
		$result = $bridge->delete_layout( $id );
		if ( ! empty( $result['success'] ) ) {
			adsd_flush_sc_cache();
		}
		$this->send( $result );
	}

	/** Reset a preset layout to its original template. */
	public function handle_reset_layout() {
		$this->check_admin( 'adsd_nonce' );
		$name = isset( $_POST['layout_name'] ) ? sanitize_text_field( wp_unslash( $_POST['layout_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Layout name required.', 'ad-sd-static-connector' ) ) );
			return;
		}
		$ok = AD_SD_WSC_Activator::reset_preset_layout( $name );
		if ( $ok ) {
			adsd_flush_sc_cache();
			wp_send_json_success( array( 'message' => __( 'Layout reset to original.', 'ad-sd-static-connector' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Layout not found.', 'ad-sd-static-connector' ) ) );
		}
	}

	// ─────────────────────────────────────────────────
	// SETTINGS & LOGS
	// ─────────────────────────────────────────────────

	/** Get activity logs. */
	public function handle_get_logs() {
		$this->check_admin( 'adsd_nonce' );
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'adsd_activity_logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$logs  = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, u.display_name FROM `{$table}` l LEFT JOIN `{$wpdb->users}` u ON l.user_id = u.ID ORDER BY l.created_at DESC LIMIT %d", 100 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		wp_send_json_success( array( 'logs' => $logs ) );
	}

	/** Save plugin settings. */
	public function handle_save_settings() {
		$this->check_admin( 'adsd_nonce' );
		$max_size = absint( $_POST['max_zip_size_mb'] ?? 50 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_max_zip_size_mb', $max_size );
		$manager_layout = ! empty( $_POST['manager_can_layout'] ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_manager_can_layout', $manager_layout );
		// Shortcode allowlist — comma-separated tags. Empty = allow all.
		$allowed_sc = isset( $_POST['allowed_shortcodes'] ) ? sanitize_text_field( wp_unslash( $_POST['allowed_shortcodes'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_allowed_shortcodes', $allowed_sc );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'ad-sd-static-connector' ) ) );
	}

	/** Get WP error/debug log entries relevant to this plugin or cron. */
	public function handle_get_error_log() {
		$this->check_admin( 'adsd_nonce' );

		$log_path = '';
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_path = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
		}

		// Security: ensure log path stays within WP content directory (prevent path traversal).
		if ( $log_path ) {
			$real_log     = realpath( $log_path );
			$real_content = realpath( WP_CONTENT_DIR );
			if ( ! $real_log || ! $real_content || 0 !== strpos( $real_log, $real_content ) ) {
				$log_path = ''; // Reject any path outside WP content dir.
			}
		}

		// If WP_DEBUG_LOG is off or file doesn't exist, fall back to plugin's own activity log.
		if ( ! $log_path || ! file_exists( $log_path ) ) {
			global $wpdb;
			$table = esc_sql( $wpdb->prefix . 'adsd_activity_logs' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT created_at, action, details FROM `{$table}` ORDER BY created_at DESC LIMIT %d", 100 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$entries = array();
			if ( $rows ) {
				foreach ( $rows as $row ) {
					$entries[] = '[' . $row->created_at . '] [AD-SD-WSC] ' . $row->action . ( $row->details ? ' — ' . $row->details : '' );
				}
			}
			wp_send_json_success( array(
				'entries' => $entries,
				'message' => $entries
					? __( 'WP_DEBUG_LOG is not enabled — showing plugin activity log instead.', 'ad-sd-static-connector' )
					: __( 'WP_DEBUG_LOG is not enabled. Enable it in wp-config.php to see PHP/cron errors here. No plugin activity recorded yet.', 'ad-sd-static-connector' ),
				'debug_off' => true,
			) );
			return;
		}

		if ( ! is_readable( $log_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Debug log file is not readable.', 'ad-sd-static-connector' ) ) );
			return;
		}

		// Read last 200 lines efficiently.
		$lines = array();
		$fp    = fopen( $log_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $fp ) {
			$buffer    = '';
			$chunk     = 8192;
			fseek( $fp, 0, SEEK_END );
			$bytes_left = ftell( $fp );
			while ( $bytes_left > 0 && count( $lines ) < 200 ) {
				$read    = min( $chunk, $bytes_left );
				$bytes_left -= $read;
				fseek( $fp, $bytes_left );
				$buffer  = fread( $fp, $read ) . $buffer; // phpcs:ignore WordPress.WP.AlternativeFunctions
				$lines   = explode( "\n", $buffer );
			}
			fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		// Keep last 200 non-empty lines.
		$lines = array_filter( array_slice( $lines, -200 ), 'strlen' );

		// Filter: only lines containing error keywords or plugin-related terms.
		$keywords = array( 'error', 'Error', 'ERROR', 'warning', 'Warning', 'fatal', 'Fatal',
			'action_scheduler', 'cron', 'Cron', 'could_not_set', 'ad-sd-static-connector', 'adsd', 'WP Cron' );
		$filtered = array();
		foreach ( $lines as $line ) {
			foreach ( $keywords as $kw ) {
				if ( false !== strpos( $line, $kw ) ) {
					$filtered[] = $line;
					break;
				}
			}
		}

		// If nothing matched, return last 50 lines anyway so the log isn't blank.
		if ( empty( $filtered ) ) {
			$filtered = array_slice( $lines, -50 );
		}

		wp_send_json_success( array(
			'entries' => array_values( $filtered ),
			'total'   => count( $filtered ),
		) );
	}

	/** Clear (truncate) the WP debug log. */
	public function handle_clear_error_log() {
		$this->check_admin( 'adsd_nonce' );

		$log_path = '';
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_path = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
		}

		// Security: ensure log path stays within WP content directory.
		if ( $log_path ) {
			$real_log     = realpath( $log_path );
			$real_content = realpath( WP_CONTENT_DIR );
			if ( ! $real_log || ! $real_content || 0 !== strpos( $real_log, $real_content ) ) {
				$log_path = '';
			}
		}

		if ( ! $log_path || ! file_exists( $log_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Log file not found.', 'ad-sd-static-connector' ) ) );
			return;
		}

		if ( ! wp_is_writable( $log_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Log file is not writable.', 'ad-sd-static-connector' ) ) );
			return;
		}

		// Use WP_Filesystem to write file — avoids direct PHP file functions.
		adsd_file_put_contents( $log_path, '' );
		wp_send_json_success( array( 'message' => __( 'Error log cleared.', 'ad-sd-static-connector' ) ) );
	}

	// ─────────────────────────────────────────────────
	// UTILITY
	// ─────────────────────────────────────────────────

	/**
	 * Verify nonce and user capability.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @return void
	 */
	private function check_admin( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ad-sd-static-connector' ) ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		AD_SD_WSC_Helpers::verify_nonce( $nonce, $nonce_action );
	}

	/**
	 * Send a standardised JSON response.
	 * Converts legacy array( 'success' => bool, ...data... ) into proper
	 * wp_send_json_success / wp_send_json_error so the JS always gets
	 * { success: true/false, data: { ... } } — never a flat object.
	 *
	 * @param array $result Result array from any handler method.
	 * @return void
	 */
	private function send( $result ) {
		if ( isset( $result['success'] ) && true === $result['success'] ) {
			unset( $result['success'] );
			wp_send_json_success( $result );
		} else {
			$message = isset( $result['message'] ) ? $result['message'] : __( 'An error occurred.', 'ad-sd-static-connector' );
			wp_send_json_error( array( 'message' => $message ) );
		}
	}

	// ─────────────────────────────────────────────────
	// WP PAGE INJECTOR HANDLERS
	// ─────────────────────────────────────────────────

	/** Save WP Page Injector settings. */
	public function handle_save_injector() {
		$this->check_admin( 'adsd_nonce' );

		// Toggle: disable all header/footer.
		$disable_all_hf  = ! empty( $_POST['adsd_wp_disable_all_hf'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$injector_enabled = ! empty( $_POST['adsd_wp_injector_enabled'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification

		update_option( 'adsd_wp_disable_all_hf', $disable_all_hf );
		// When disable_all_hf is turned ON it also enables the injector.
		// When turned OFF, use the explicit injector_enabled value from the form.
		$enabled = $disable_all_hf ? 1 : $injector_enabled;
		update_option( 'adsd_wp_injector_enabled', $enabled );

		// Keep legacy options in sync for backward compatibility.
		update_option( 'adsd_wp_disable_theme_hf', $disable_all_hf );
		update_option( 'adsd_wp_disable_plugin_hf', $disable_all_hf );

		// HTML / CSS / JS fields.
		$fields = array(
			'adsd_wp_head_code',
			'adsd_wp_header_html',
			'adsd_wp_footer_html',
			'adsd_wp_script_html',
			'adsd_wp_custom_css',
			'adsd_wp_custom_js',
			'adsd_wp_404_html',
		);
		foreach ( $fields as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			update_option( $key, $value );
		}

		// Container width settings.
		update_option( 'adsd_container_enabled', ! empty( $_POST['adsd_container_enabled'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_container_desktop', absint( $_POST['adsd_container_desktop'] ?? 1200 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_container_tablet',  absint( $_POST['adsd_container_tablet']  ?? 900 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_container_mobile',  absint( $_POST['adsd_container_mobile']  ?? 100 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_container_padding',    absint( $_POST['adsd_container_padding']    ?? 16 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_container_margin_top', absint( $_POST['adsd_container_margin_top'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification

		// Post Template options.
		update_option( 'adsd_post_template_enabled',  ! empty( $_POST['adsd_post_template_enabled'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_meta_author',        ! empty( $_POST['adsd_post_meta_author'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_meta_date',          ! empty( $_POST['adsd_post_meta_date'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_meta_category',      ! empty( $_POST['adsd_post_meta_category'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_meta_tags',          ! empty( $_POST['adsd_post_meta_tags'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_meta_read_time',     ! empty( $_POST['adsd_post_meta_read_time'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_meta_views',         ! empty( $_POST['adsd_post_meta_views'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_show_excerpt',       ! empty( $_POST['adsd_post_show_excerpt'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_show_related',       ! empty( $_POST['adsd_post_show_related'] ) ? 1 : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'adsd_post_related_count',      absint( $_POST['adsd_post_related_count'] ?? 3 ) ); // phpcs:ignore WordPress.Security.NonceVerification

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'ad-sd-static-connector' ) ) );
	}

	/** Get WP Page Injector saved settings. */
	public function handle_get_injector() {
		$this->check_admin( 'adsd_nonce' );
		wp_send_json_success( array(
			'injector_enabled'  => (bool) get_option( 'adsd_wp_injector_enabled', 0 ),
			'disable_all_hf'    => (bool) get_option( 'adsd_wp_disable_all_hf', 0 ),
			'head_code'         => get_option( 'adsd_wp_head_code', '' ),
			'header_html'       => get_option( 'adsd_wp_header_html', '' ),
			'footer_html'       => get_option( 'adsd_wp_footer_html', '' ),
			'script_html'       => get_option( 'adsd_wp_script_html', '' ),
			'custom_css'        => get_option( 'adsd_wp_custom_css', '' ),
			'custom_js'         => get_option( 'adsd_wp_custom_js', '' ),
			'page_404'          => get_option( 'adsd_wp_404_html', '' ),
			'container_enabled' => (bool) get_option( 'adsd_container_enabled', 0 ),
			'container_desktop' => (int) get_option( 'adsd_container_desktop', 1200 ),
			'container_tablet'  => (int) get_option( 'adsd_container_tablet', 900 ),
			'container_mobile'  => (int) get_option( 'adsd_container_mobile', 100 ),
			'container_padding'    => (int) get_option( 'adsd_container_padding', 16 ),
			'container_margin_top'     => (int) get_option( 'adsd_container_margin_top', 0 ),
			'post_template_enabled'    => (bool) get_option( 'adsd_post_template_enabled', 0 ),
			'post_meta_author'         => (bool) get_option( 'adsd_post_meta_author', 1 ),
			'post_meta_date'           => (bool) get_option( 'adsd_post_meta_date', 1 ),
			'post_meta_category'       => (bool) get_option( 'adsd_post_meta_category', 1 ),
			'post_meta_tags'           => (bool) get_option( 'adsd_post_meta_tags', 1 ),
			'post_meta_read_time'      => (bool) get_option( 'adsd_post_meta_read_time', 1 ),
			'post_meta_views'          => (bool) get_option( 'adsd_post_meta_views', 0 ),
			'post_show_excerpt'        => (bool) get_option( 'adsd_post_show_excerpt', 1 ),
			'post_show_related'        => (bool) get_option( 'adsd_post_show_related', 1 ),
			'post_related_count'       => (int) get_option( 'adsd_post_related_count', 3 ),
		) );
	}

}

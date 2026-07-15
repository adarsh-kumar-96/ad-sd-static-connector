<?php
/**
 * Handles live deployment / mapping of static ZIP as homepage.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Mapping
 */
class AD_SD_WSC_Mapping {

	/**
	 * Go live: set a ZIP + home file as the active static front.
	 *
	 * @param int    $zip_id    ZIP ID.
	 * @param string $home_file Relative path to the home HTML file.
	 * @return array
	 */
	public function go_live( $zip_id, $home_file ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_file = AD_SD_WSC_Helpers::sanitize_file_path( $home_file );
		$full_path = $zip->extract_path . '/' . $safe_file;

		if ( ! file_exists( $full_path ) ) {
			return array( 'success' => false, 'message' => __( 'Selected home file does not exist.', 'ad-sd-static-connector' ) );
		}

		// Clean up any macOS metadata artifacts before going live.
		AD_SD_WSC_Helpers::purge_macos_artifacts( $zip->extract_path );

		// Deactivate any previously live ZIP.
		$this->stop_live_internal();

		global $wpdb;
		$table     = esc_sql( $wpdb->prefix . 'adsd_zip_files' );
		$map_table = esc_sql( $wpdb->prefix . 'adsd_mapping' );

		// Mark this ZIP as active.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'status' => 'active' ),
			array( 'id' => absint( $zip_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		// Upsert mapping record.
		// phpcs:ignore -- table name built from trusted $wpdb->prefix, not user input
		$exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM `{$wpdb->prefix}adsd_mapping` WHERE zip_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			absint( $zip_id )
		) );

		$map_data = array(
			'zip_id'       => absint( $zip_id ),
			'home_file'    => $safe_file,
			'is_live'      => 1,
			'activated_by' => get_current_user_id(),
			'activated_at' => current_time( 'mysql' ),
		);

		if ( $exists ) {
			$wpdb->update( $map_table, $map_data, array( 'zip_id' => absint( $zip_id ) ), array( '%d', '%s', '%d', '%d', '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( $map_table, $map_data, array( '%d', '%s', '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// Save to options for fast access by router.
		update_option( 'adsd_live_zip_id', absint( $zip_id ) );
		update_option( 'adsd_live_home_file', $safe_file );
		update_option( 'adsd_live_extract_path', $zip->extract_path );

		// Flush rewrite rules so custom routing takes effect.
		flush_rewrite_rules();

		AD_SD_WSC_Helpers::log_activity( 'zip_live', $zip_id, $safe_file );

		return array(
			'success' => true,
			'message' => __( 'Your static site is now LIVE! Visitors will see your static pages.', 'ad-sd-static-connector' ),
		);
	}

	/**
	 * Stop live: deactivate the currently live ZIP.
	 *
	 * @return array
	 */
	public function stop_live() {
		$live_id = absint( get_option( 'adsd_live_zip_id', 0 ) );
		if ( ! $live_id ) {
			return array( 'success' => false, 'message' => __( 'No active live site found.', 'ad-sd-static-connector' ) );
		}

		$this->stop_live_internal( $live_id );

		AD_SD_WSC_Helpers::log_activity( 'zip_stopped', $live_id, '' );

		return array( 'success' => true, 'message' => __( 'Live site has been stopped. WordPress front page is restored.', 'ad-sd-static-connector' ) );
	}

	/**
	 * Internal stop-live without returning a response.
	 *
	 * @param int $zip_id Optional specific ZIP to deactivate. 0 = use option.
	 * @return void
	 */
	private function stop_live_internal( $zip_id = 0 ) {
		if ( ! $zip_id ) {
			$zip_id = absint( get_option( 'adsd_live_zip_id', 0 ) );
		}

		global $wpdb;

		if ( $zip_id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'adsd_zip_files',
				array( 'status' => 'inactive' ),
				array( 'id' => absint( $zip_id ) ),
				array( '%s' ),
				array( '%d' )
			);
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'adsd_mapping',
				array( 'is_live' => 0 ),
				array( 'zip_id' => absint( $zip_id ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		delete_option( 'adsd_live_zip_id' );
		delete_option( 'adsd_live_home_file' );
		delete_option( 'adsd_live_extract_path' );

		flush_rewrite_rules();
	}

	/**
	 * Get current live mapping info.
	 *
	 * @return array
	 */
	public function get_live_info() {
		$live_id = absint( get_option( 'adsd_live_zip_id', 0 ) );
		if ( ! $live_id ) {
			return array( 'is_live' => false );
		}
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $live_id );
		return array(
			'is_live'   => true,
			'zip_id'    => $live_id,
			'zip_name'  => $zip ? $zip->file_name : '',
			'home_file' => get_option( 'adsd_live_home_file', '' ),
		);
	}
}

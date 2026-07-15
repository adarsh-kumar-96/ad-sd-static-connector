<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package AD_SD_WSC
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$adsd_tables = array(
	$wpdb->prefix . 'adsd_zip_files',
	$wpdb->prefix . 'adsd_seo_settings',
	$wpdb->prefix . 'adsd_mapping',
	$wpdb->prefix . 'adsd_layouts',
	$wpdb->prefix . 'adsd_activity_logs',
);
foreach ( $adsd_tables as $adsd_table ) {
	$adsd_table = esc_sql( $adsd_table );
	$wpdb->query( "DROP TABLE IF EXISTS {$adsd_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
}

// Remove plugin options.
$adsd_options = array(
	'adsd_db_version',
	'adsd_max_zip_size_mb',
	'adsd_allowed_file_types',
	'adsd_manager_can_layout',
	'adsd_live_zip_id',
	'adsd_live_home_file',
	'adsd_live_extract_path',
	'adsd_rewrite_rules_flushed',
);
foreach ( $adsd_options as $adsd_opt ) {
	delete_option( $adsd_opt );
}

// Remove uploaded files directory.
$adsd_upload_dir = wp_upload_dir()['basedir'] . '/ad-sd-wsc';
if ( is_dir( $adsd_upload_dir ) ) {
	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	function adsd_uninstall_rmdir( $dir ) {
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
				adsd_uninstall_rmdir( $path );
			} else {
				wp_delete_file( $path );
			}
		}
		// Remove empty directory.
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall context, adsd_get_filesystem not available
	}
	adsd_uninstall_rmdir( $adsd_upload_dir );
}

flush_rewrite_rules();

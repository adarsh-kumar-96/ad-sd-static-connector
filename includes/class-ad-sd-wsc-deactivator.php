<?php
/**
 * Fired during plugin deactivation.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Deactivator
 */
class AD_SD_WSC_Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Remove rewrite rules — data & files are preserved.
		delete_option( 'adsd_rewrite_rules_flushed' );
		flush_rewrite_rules();
	}
}

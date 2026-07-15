<?php
/**
 * Admin panel controller: registers menu, enqueues assets, renders tabs.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Admin
 */
class AD_SD_WSC_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register top-level admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'AD-SD Static Connector', 'ad-sd-static-connector' ),
			__( 'Static Connector', 'ad-sd-static-connector' ),
			'manage_options',
			'ad-sd-wsc',
			array( $this, 'render_page' ),
			'dashicons-cloud-upload',
			30
		);
	}

	/**
	 * Enqueue admin CSS and JS on the plugin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_ad-sd-wsc' !== $hook ) {
			return;
		}

		// Built-in lightweight code editor (no external CDN dependency).
		wp_enqueue_style(
			'ad-sd-wsc-editor',
			AD_SD_WSC_PLUGIN_URL . 'admin/css/adsd-editor.css',
			array(),
			AD_SD_WSC_VERSION
		);
		wp_enqueue_script(
			'ad-sd-wsc-editor',
			AD_SD_WSC_PLUGIN_URL . 'admin/js/adsd-editor.js',
			array(),
			AD_SD_WSC_VERSION,
			true
		);

		// Plugin admin CSS.
		wp_enqueue_style(
			'ad-sd-wsc-admin',
			AD_SD_WSC_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			AD_SD_WSC_VERSION
		);

		// Plugin admin JS.
		wp_enqueue_script(
			'ad-sd-wsc-admin',
			AD_SD_WSC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'ad-sd-wsc-editor' ),
			AD_SD_WSC_VERSION,
			true
		);

		// Localize script with nonces and data.
		wp_localize_script(
			'ad-sd-wsc-admin',
			'adsdData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'adsd_nonce' ),
				'uploadNonce'  => wp_create_nonce( 'adsd_upload_nonce' ),
				'pluginUrl'    => AD_SD_WSC_PLUGIN_URL,
				'siteUrl'      => home_url(),
				'i18n'         => array(
					'confirmDelete'    => __( 'Are you sure you want to delete this? This action cannot be undone.', 'ad-sd-static-connector' ),
					'imageNoCode'      => __( 'Image files have no code to display. See preview on the right.', 'ad-sd-static-connector' ),
					'fallbackEditorNotice' => __( 'Advanced code editor failed to load (offline?) — using simple text editor.', 'ad-sd-static-connector' ),
					'fallbackNoFind'   => __( 'Find & Replace requires the advanced editor, which failed to load.', 'ad-sd-static-connector' ),
					'notFound'         => __( 'No matches found.', 'ad-sd-static-connector' ),
					'replaced'         => __( 'occurrence(s) replaced.', 'ad-sd-static-connector' ),
					'copied'           => __( 'Copied to clipboard.', 'ad-sd-static-connector' ),
					'cut'              => __( 'Cut to clipboard.', 'ad-sd-static-connector' ),
					'pasted'           => __( 'Pasted from clipboard.', 'ad-sd-static-connector' ),
					'clipboardError'   => __( 'Clipboard access was blocked by the browser.', 'ad-sd-static-connector' ),
					'lightMode'        => __( 'Light Mode', 'ad-sd-static-connector' ),
					'darkMode'         => __( 'Dark Mode', 'ad-sd-static-connector' ),
					'runHtmlOnly'      => __( 'Run is only available for HTML files.', 'ad-sd-static-connector' ),
					'confirmLive'      => __( 'This will make your static site LIVE. Visitors will see the static pages. Continue?', 'ad-sd-static-connector' ),
					'confirmStopLive'  => __( 'This will stop your live static site and restore the normal WordPress front page. Continue?', 'ad-sd-static-connector' ),
					'uploading'        => __( 'Uploading...', 'ad-sd-static-connector' ),
					'checking'         => __( 'Checking code...', 'ad-sd-static-connector' ),
					'saving'           => __( 'Saving...', 'ad-sd-static-connector' ),
					'loading'          => __( 'Loading...', 'ad-sd-static-connector' ),
					'noErrors'         => __( 'No issues found! Your code looks clean.', 'ad-sd-static-connector' ),
					'generating'       => __( 'Generating...', 'ad-sd-static-connector' ),
					'copied'           => __( 'Copied!', 'ad-sd-static-connector' ),
					'copyFailed'       => __( 'Copy failed. Please select and copy manually.', 'ad-sd-static-connector' ),
					'fullscreen'       => __( 'Full Screen', 'ad-sd-static-connector' ),
					'exitFullscreen'   => __( 'Exit Full Screen', 'ad-sd-static-connector' ),
					'beautifySuccess'  => __( 'Code beautified.', 'ad-sd-static-connector' ),
					'beautifyUnsupported' => __( 'Beautify supports HTML and CSS files.', 'ad-sd-static-connector' ),
				),
			)
		);
	}

	/**
	 * Render the main plugin admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ad-sd-static-connector' ) );
		}
		require_once AD_SD_WSC_PLUGIN_DIR . 'admin/views/main.php';
	}
}

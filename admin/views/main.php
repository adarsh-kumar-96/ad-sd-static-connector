<?php
/**
 * Main admin page — tab shell.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$adsd_active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification

$adsd_tabs = array(
	'dashboard'    => array( 'label' => __( 'Dashboard', 'ad-sd-static-connector' ), 'icon' => 'dashicons-dashboard' ),
	'file-manager' => array( 'label' => __( 'Static File Manager', 'ad-sd-static-connector' ), 'icon' => 'dashicons-media-code' ),
	'mapping'      => array( 'label' => __( 'Mapping', 'ad-sd-static-connector' ), 'icon' => 'dashicons-admin-site-alt3' ),
	'bridge'       => array( 'label' => __( 'Shortcode Bridge', 'ad-sd-static-connector' ), 'icon' => 'dashicons-shortcode' ),
	'wp-injector'  => array( 'label' => __( 'WP Page Injector', 'ad-sd-static-connector' ), 'icon' => 'dashicons-editor-code' ),
	'settings'     => array( 'label' => __( 'Settings & Logs', 'ad-sd-static-connector' ), 'icon' => 'dashicons-admin-settings' ),
);
?>
<div class="wrap adsd-wrap" id="adsd-app">

	<div class="adsd-header">
		<div class="adsd-header-left">
			<span class="dashicons dashicons-cloud-upload adsd-logo-icon"></span>
			<div>
				<h1 class="adsd-title"><?php esc_html_e( 'AD-SD Static Connector', 'ad-sd-static-connector' ); ?></h1>
				<p class="adsd-subtitle"><?php esc_html_e( 'Deploy & manage your static websites inside WordPress', 'ad-sd-static-connector' ); ?></p>
			</div>
		</div>
		<div class="adsd-header-right">
			<div id="adsd-live-badge" class="adsd-live-badge adsd-live-badge--off">
				<span class="adsd-live-dot"></span>
				<span class="adsd-live-label"><?php esc_html_e( 'No Live Site', 'ad-sd-static-connector' ); ?></span>
			</div>
		</div>
	</div>

	<nav class="adsd-tab-nav">
		<?php foreach ( $adsd_tabs as $adsd_tab_key => $tab ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ad-sd-wsc&tab=' . $adsd_tab_key ) ); ?>"
			   class="adsd-tab-btn <?php echo ( $adsd_active_tab === $adsd_tab_key ) ? 'adsd-tab-btn--active' : ''; ?>"
			   data-tab="<?php echo esc_attr( $adsd_tab_key ); ?>">
				<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="adsd-tab-content">
		<?php
		$adsd_view_file = AD_SD_WSC_PLUGIN_DIR . 'admin/views/tab-' . $adsd_active_tab . '.php';
		if ( file_exists( $adsd_view_file ) ) {
			require_once $adsd_view_file;
		}
		?>
	</div>

</div>

<?php
/**
 * Settings & Logs tab view.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$adsd_max_size       = absint( get_option( 'adsd_max_zip_size_mb', 50 ) );
$adsd_manager_layout = get_option( 'adsd_manager_can_layout', true );
?>
<div class="adsd-panel" id="adsd-settings">

	<!-- ── Plugin Settings ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Plugin Settings', 'ad-sd-static-connector' ); ?></h2>
		</div>

		<div class="adsd-settings-form">

			<div class="adsd-settings-row">
				<div class="adsd-settings-label">
					<label for="adsd-setting-max-size" class="adsd-label">
						<?php esc_html_e( 'Maximum ZIP Upload Size (MB)', 'ad-sd-static-connector' ); ?>
					</label>
					<p class="adsd-field-hint"><?php esc_html_e( 'Maximum allowed size for each uploaded ZIP file. Server PHP limits may also apply.', 'ad-sd-static-connector' ); ?></p>
				</div>
				<div class="adsd-settings-control">
					<input type="number" id="adsd-setting-max-size" class="adsd-input adsd-input--short"
						value="<?php echo esc_attr( $adsd_max_size ); ?>" min="1" max="500">
					<span class="adsd-input-suffix">MB</span>
				</div>
			</div>

			<div class="adsd-settings-row">
				<div class="adsd-settings-label">
					<label for="adsd-setting-manager-layout" class="adsd-label">
						<?php esc_html_e( 'Allow Editor Role to Create Layouts', 'ad-sd-static-connector' ); ?>
					</label>
					<p class="adsd-field-hint"><?php esc_html_e( 'When enabled, users with the Editor role can also create and edit custom layouts in the Shortcode Bridge tab.', 'ad-sd-static-connector' ); ?></p>
				</div>
				<div class="adsd-settings-control">
					<label class="adsd-toggle">
						<input type="checkbox" id="adsd-setting-manager-layout" <?php checked( $adsd_manager_layout ); ?>>
						<span class="adsd-toggle-slider"></span>
					</label>
				</div>
			</div>

			<div class="adsd-settings-info-row">
				<div class="adsd-info-box adsd-info-box--gray">
					<span class="dashicons dashicons-lock"></span>
					<div>
						<strong><?php esc_html_e( 'Security Notice', 'ad-sd-static-connector' ); ?></strong>
						<p><?php esc_html_e( 'All plugin actions (upload, live, delete, edit) require Administrator access. The plugin blocks PHP files from being uploaded or executed.', 'ad-sd-static-connector' ); ?></p>
					</div>
				</div>
			</div>

			<div class="adsd-settings-row">
				<div class="adsd-settings-label">
					<label for="adsd-setting-allowed-sc" class="adsd-label">
						<?php esc_html_e( 'Allowed Shortcodes (Allowlist)', 'ad-sd-static-connector' ); ?>
					</label>
					<p class="adsd-field-hint"><?php esc_html_e( 'Comma-separated shortcode tags allowed on the public render endpoint. Leave empty to allow all registered shortcodes.', 'ad-sd-static-connector' ); ?></p>
				</div>
				<div class="adsd-settings-control">
					<input type="text" id="adsd-setting-allowed-sc" class="adsd-input"
						value="<?php echo esc_attr( get_option( 'adsd_allowed_shortcodes', '' ) ); ?>"
						placeholder="products, woocommerce_cart, my_shortcode">
				</div>
			</div>

			<div class="adsd-settings-actions">
				<button type="button" class="adsd-btn adsd-btn--primary" id="adsd-save-settings">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Settings', 'ad-sd-static-connector' ); ?>
				</button>
			</div>
			<div id="adsd-settings-msg" class="adsd-editor-msg"></div>
		</div>
	</div>

	<!-- ── System Info ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'System Information', 'ad-sd-static-connector' ); ?></h2>
		</div>
		<div class="adsd-sysinfo-grid">
			<?php
			$adsd_upload_dir = wp_upload_dir();
			$adsd_info = array(
				__( 'Plugin Version', 'ad-sd-static-connector' )       => AD_SD_WSC_VERSION,
				__( 'WordPress Version', 'ad-sd-static-connector' )    => get_bloginfo( 'version' ),
				__( 'PHP Version', 'ad-sd-static-connector' )          => PHP_VERSION,
				__( 'ZipArchive', 'ad-sd-static-connector' )           => class_exists( 'ZipArchive' ) ? '<span class="adsd-badge adsd-badge--green">' . esc_html__( 'Available', 'ad-sd-static-connector' ) . '</span>' : '<span class="adsd-badge adsd-badge--red">' . esc_html__( 'Not Available', 'ad-sd-static-connector' ) . '</span>',
				__( 'Upload Directory', 'ad-sd-static-connector' )     => esc_html( $adsd_upload_dir['basedir'] . '/ad-sd-wsc' ),
				__( 'Upload Dir Writable', 'ad-sd-static-connector' )  => wp_is_writable( $adsd_upload_dir['basedir'] ) ? '<span class="adsd-badge adsd-badge--green">' . esc_html__( 'Yes', 'ad-sd-static-connector' ) . '</span>' : '<span class="adsd-badge adsd-badge--red">' . esc_html__( 'No', 'ad-sd-static-connector' ) . '</span>',
				__( 'PHP Upload Max', 'ad-sd-static-connector' )       => esc_html( ini_get( 'upload_max_filesize' ) ),
				__( 'PHP Post Max', 'ad-sd-static-connector' )         => esc_html( ini_get( 'post_max_size' ) ),
				__( 'WooCommerce', 'ad-sd-static-connector' )          => function_exists( 'WC' ) ? '<span class="adsd-badge adsd-badge--green">' . esc_html__( 'Active', 'ad-sd-static-connector' ) . '</span>' : '<span class="adsd-badge adsd-badge--gray">' . esc_html__( 'Not active', 'ad-sd-static-connector' ) . '</span>',
			);
			foreach ( $adsd_info as $adsd_label => $adsd_value ) :
			?>
			<div class="adsd-sysinfo-row">
				<span class="adsd-sysinfo-label"><?php echo esc_html( $adsd_label ); ?></span>
				<span class="adsd-sysinfo-value"><?php echo wp_kses_post( $adsd_value ); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- ── Cron / Scheduler Status ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Cron / Scheduler Status', 'ad-sd-static-connector' ); ?></h2>
		</div>
		<?php
		// Check if WP_CRON is disabled.
		$adsd_cron_disabled = ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );

		// Check if Action Scheduler cron event exists and is not overdue.
		$adsd_as_cron_ok    = false;
		$adsd_as_cron_next  = '';
		$adsd_as_event      = wp_get_scheduled_event( 'action_scheduler_run_queue', array( 'WP Cron' ) );
		if ( $adsd_as_event ) {
			$adsd_as_cron_ok   = true;
			$adsd_as_cron_next = date_i18n( 'Y-m-d H:i:s', $adsd_as_event->timestamp );
		}

		// Check generic cron health using wp_get_ready_cron_jobs().
		$adsd_overdue_count  = 0;
		$adsd_cron_jobs      = _get_cron_array();
		$adsd_now            = time();
		if ( is_array( $adsd_cron_jobs ) ) {
			foreach ( $adsd_cron_jobs as $adsd_timestamp => $adsd_hooks ) {
				if ( $adsd_timestamp < ( $adsd_now - 600 ) ) { // 10 min overdue.
					$adsd_overdue_count += count( $adsd_hooks );
				}
			}
		}
		?>
		<div class="adsd-sysinfo-grid">

			<div class="adsd-sysinfo-row">
				<span class="adsd-sysinfo-label"><?php esc_html_e( 'WP Cron', 'ad-sd-static-connector' ); ?></span>
				<span class="adsd-sysinfo-value">
					<?php if ( $adsd_cron_disabled ) : ?>
						<span class="adsd-badge adsd-badge--red"><?php esc_html_e( 'DISABLE_WP_CRON = true', 'ad-sd-static-connector' ); ?></span>
					<?php else : ?>
						<span class="adsd-badge adsd-badge--green"><?php esc_html_e( 'Enabled', 'ad-sd-static-connector' ); ?></span>
					<?php endif; ?>
				</span>
			</div>

			<div class="adsd-sysinfo-row">
				<span class="adsd-sysinfo-label"><?php esc_html_e( 'Action Scheduler Event', 'ad-sd-static-connector' ); ?></span>
				<span class="adsd-sysinfo-value">
					<?php if ( $adsd_as_cron_ok ) : ?>
						<span class="adsd-badge adsd-badge--green"><?php esc_html_e( 'Scheduled', 'ad-sd-static-connector' ); ?></span>
						<?php /* translators: %s: date and time of next scheduled event */ ?>
						<span style="color:#888;font-size:12px;margin-left:6px;"><?php echo esc_html( sprintf( __( 'Next: %s', 'ad-sd-static-connector' ), $adsd_as_cron_next ) ); ?></span>
					<?php else : ?>
						<span class="adsd-badge adsd-badge--red"><?php esc_html_e( 'Not Scheduled', 'ad-sd-static-connector' ); ?></span>
					<?php endif; ?>
				</span>
			</div>

			<div class="adsd-sysinfo-row">
				<span class="adsd-sysinfo-label"><?php esc_html_e( 'Overdue Cron Jobs', 'ad-sd-static-connector' ); ?></span>
				<span class="adsd-sysinfo-value">
					<?php if ( $adsd_overdue_count > 0 ) : ?>
						<span class="adsd-badge adsd-badge--red"><?php echo esc_html( $adsd_overdue_count ); ?></span>
					<?php else : ?>
						<span class="adsd-badge adsd-badge--green"><?php esc_html_e( 'None', 'ad-sd-static-connector' ); ?></span>
					<?php endif; ?>
				</span>
			</div>

		</div>

		<?php if ( $adsd_cron_disabled ) : ?>
		<div class="adsd-info-box adsd-info-box--red" style="margin-top:12px;">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong><?php esc_html_e( 'WP Cron is disabled!', 'ad-sd-static-connector' ); ?></strong>
				<p>
					<?php
					esc_html_e(
						'DISABLE_WP_CRON is set to true in wp-config.php. This causes the Action Scheduler error: "could_not_set — The cron event list could not be saved." To fix: either remove DISABLE_WP_CRON from wp-config.php, or set up a real server cron job that hits /?doing_wp_cron every minute.',
						'ad-sd-static-connector'
					);
					?>
				</p>
				<code style="display:block;margin-top:6px;font-size:12px;">* * * * * wget -q -O - "<?php echo esc_url( home_url( '/?doing_wp_cron' ) ); ?>" &gt;/dev/null 2&gt;&amp;1</code>
			</div>
		</div>
		<?php elseif ( ! $adsd_as_cron_ok ) : ?>
		<div class="adsd-info-box adsd-info-box--red" style="margin-top:12px;">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong><?php esc_html_e( 'Action Scheduler event missing', 'ad-sd-static-connector' ); ?></strong>
				<p>
					<?php
					esc_html_e(
						'The "action_scheduler_run_queue" cron event is not scheduled. This usually means WooCommerce / Action Scheduler could not register it because WP Cron was temporarily unavailable. Go to WooCommerce → Status → Scheduled Actions and run "Action Scheduler Queue Runner" manually, or deactivate and reactivate WooCommerce to re-register the event.',
						'ad-sd-static-connector'
					);
					?>
				</p>
			</div>
		</div>
		<?php endif; ?>
	</div>


	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Error Log', 'ad-sd-static-connector' ); ?></h2>
			<div style="display:flex;gap:8px;align-items:center;">
				<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-refresh-errors">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Refresh', 'ad-sd-static-connector' ); ?>
				</button>
				<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-clear-errors">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
				</button>
			</div>
		</div>
		<div class="adsd-info-box adsd-info-box--gray" style="margin-bottom:12px;">
			<span class="dashicons dashicons-info"></span>
			<div>
				<p style="margin:0;">
					<?php
					$adsd_wp_debug_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG
						? ( is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log' )
						: '';
					if ( $adsd_wp_debug_log ) {
						/* translators: %s: path to debug log file */
						echo esc_html( sprintf( __( 'Reading from: %s', 'ad-sd-static-connector' ), $adsd_wp_debug_log ) );
					} else {
						esc_html_e( 'WP_DEBUG_LOG is not enabled. Enable it in wp-config.php to see errors here.', 'ad-sd-static-connector' );
					}
					?>
				</p>
			</div>
		</div>
		<div id="adsd-error-log-wrap" style="background:#1e1e1e;border-radius:6px;padding:14px;min-height:120px;max-height:360px;overflow-y:auto;font-family:monospace;font-size:12px;line-height:1.6;color:#d4d4d4;">
			<span style="color:#888;"><?php esc_html_e( 'Loading errors…', 'ad-sd-static-connector' ); ?></span>
		</div>
	</div>

	<!-- ── Activity Log ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Activity Log', 'ad-sd-static-connector' ); ?></h2>
			<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-refresh-logs">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'ad-sd-static-connector' ); ?>
			</button>
		</div>
		<div class="adsd-log-table-wrap" id="adsd-log-table-wrap">
			<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>
		</div>
	</div>

</div>

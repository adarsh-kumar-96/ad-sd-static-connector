<?php
/**
 * Mapping tab view.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="adsd-panel" id="adsd-mapping">

	<!-- Live Status Banner -->
	<div class="adsd-live-status-banner" id="adsd-mapping-live-banner" style="display:none;">
		<div class="adsd-live-status-banner-inner">
			<span class="adsd-live-pulse"></span>
			<div class="adsd-live-status-info">
				<strong><?php esc_html_e( 'Your static site is LIVE!', 'ad-sd-static-connector' ); ?></strong>
				<span id="adsd-live-banner-detail"></span>
			</div>
			<a href="<?php echo esc_url( home_url() ); ?>" target="_blank" class="adsd-btn adsd-btn--ghost adsd-btn--sm">
				<span class="dashicons dashicons-external"></span>
				<?php esc_html_e( 'View Site', 'ad-sd-static-connector' ); ?>
			</a>
			<button type="button" class="adsd-btn adsd-btn--danger adsd-btn--sm" id="adsd-mapping-stop-live">
				<span class="dashicons dashicons-controls-pause"></span>
				<?php esc_html_e( 'Stop Live', 'ad-sd-static-connector' ); ?>
			</button>
		</div>
	</div>

	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e( 'Set for Live Pages', 'ad-sd-static-connector' ); ?></h2>
		</div>

		<div class="adsd-info-box adsd-info-box--blue">
			<span class="dashicons dashicons-info"></span>
			<p>
				<?php esc_html_e( 'Choose a ZIP file and select which file should be the homepage. Once live, the selected ZIP will be served as your website\'s front page — just like a real static website.', 'ad-sd-static-connector' ); ?>
				<strong><?php esc_html_e( ' Visitors will see the static pages instead of your WordPress front page.', 'ad-sd-static-connector' ); ?></strong>
			</p>
		</div>

		<div class="adsd-mapping-form">
			<!-- Step 1: Choose ZIP -->
			<div class="adsd-mapping-step">
				<div class="adsd-step-number">1</div>
				<div class="adsd-step-body">
					<label for="adsd-mapping-zip" class="adsd-label">
						<?php esc_html_e( 'Select ZIP File', 'ad-sd-static-connector' ); ?>
					</label>
					<select id="adsd-mapping-zip" class="adsd-select">
						<option value=""><?php esc_html_e( '— Choose a ZIP file —', 'ad-sd-static-connector' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Step 2: Choose homepage file -->
			<div class="adsd-mapping-step" id="adsd-mapping-step2" style="display:none;">
				<div class="adsd-step-number">2</div>
				<div class="adsd-step-body">
					<label for="adsd-mapping-home-file" class="adsd-label">
						<?php esc_html_e( 'Select Homepage File', 'ad-sd-static-connector' ); ?>
					</label>
					<p class="adsd-field-hint">
						<?php esc_html_e( 'This file will be loaded when visitors open your website\'s main URL (homepage / front page).', 'ad-sd-static-connector' ); ?>
					</p>
					<select id="adsd-mapping-home-file" class="adsd-select">
						<option value=""><?php esc_html_e( '— Choose home page file —', 'ad-sd-static-connector' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Step 3: Go Live -->
			<div class="adsd-mapping-step" id="adsd-mapping-step3" style="display:none;">
				<div class="adsd-step-number">3</div>
				<div class="adsd-step-body">
					<div class="adsd-info-box adsd-info-box--yellow">
						<span class="dashicons dashicons-warning"></span>
						<p><?php esc_html_e( 'Clicking "Go Live" will make your static ZIP the active website. The selected file will be set as the homepage. Your WordPress backend and admin panel remain fully functional.', 'ad-sd-static-connector' ); ?></p>
					</div>
					<button type="button" class="adsd-btn adsd-btn--success adsd-btn--lg" id="adsd-mapping-go-live">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e( 'Go Live', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div id="adsd-mapping-msg" class="adsd-editor-msg" style="margin-top:20px;"></div>
	</div>

	<!-- How it works info -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'How Mapping Works', 'ad-sd-static-connector' ); ?></h2>
		</div>
		<div class="adsd-how-grid">
			<div class="adsd-how-card">
				<span class="dashicons dashicons-media-archive adsd-how-icon"></span>
				<h4><?php esc_html_e( 'ZIP as Public HTML', 'ad-sd-static-connector' ); ?></h4>
				<p><?php esc_html_e( 'Your uploaded ZIP file acts as your website\'s public folder. All files inside are served to visitors just like a real static host.', 'ad-sd-static-connector' ); ?></p>
			</div>
			<div class="adsd-how-card">
				<span class="dashicons dashicons-admin-home adsd-how-icon"></span>
				<h4><?php esc_html_e( 'Custom Homepage', 'ad-sd-static-connector' ); ?></h4>
				<p><?php esc_html_e( 'The selected HTML file becomes your homepage (loaded at your main domain URL). Other files are accessible via their paths.', 'ad-sd-static-connector' ); ?></p>
			</div>
			<div class="adsd-how-card">
				<span class="dashicons dashicons-admin-settings adsd-how-icon"></span>
				<h4><?php esc_html_e( 'WP Admin Intact', 'ad-sd-static-connector' ); ?></h4>
				<p><?php esc_html_e( 'Your WordPress admin panel (/wp-admin) and all plugins continue to work normally. Only the frontend is served statically.', 'ad-sd-static-connector' ); ?></p>
			</div>
			<div class="adsd-how-card">
				<span class="dashicons dashicons-controls-pause adsd-how-icon"></span>
				<h4><?php esc_html_e( 'Easy Stop', 'ad-sd-static-connector' ); ?></h4>
				<p><?php esc_html_e( 'Click "Stop Live" anytime to restore your normal WordPress front page. No data is lost.', 'ad-sd-static-connector' ); ?></p>
			</div>
		</div>
	</div>

</div>

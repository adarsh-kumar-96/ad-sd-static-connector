<?php
/**
 * Dashboard tab view.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="adsd-panel" id="adsd-dashboard">

	<!-- ── UPLOAD SECTION ── -->
	<div class="adsd-section adsd-upload-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload Your File', 'ad-sd-static-connector' ); ?></h2>
		</div>
		<p class="adsd-section-desc">
			<?php esc_html_e( 'Upload your public-html folder files as a ZIP archive. The plugin will extract and manage your static site files securely.', 'ad-sd-static-connector' ); ?>
		</p>

		<!-- ZIP instructions accordion -->
		<div class="adsd-instructions-box">
			<button type="button" class="adsd-instructions-toggle">
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'How to create a ZIP file from your static website?', 'ad-sd-static-connector' ); ?>
				<span class="dashicons dashicons-arrow-down-alt2 adsd-arrow"></span>
			</button>
			<div class="adsd-instructions-content" style="display:none;">
				<div class="adsd-instructions-grid">
					<div class="adsd-instr-card">
						<div class="adsd-instr-num">1</div>
						<h4><?php esc_html_e( 'Prepare Your Files', 'ad-sd-static-connector' ); ?></h4>
						<p><?php esc_html_e( 'Gather all your static website files: HTML, CSS, JS, images, fonts etc. in a single folder (e.g. "my-website").', 'ad-sd-static-connector' ); ?></p>
					</div>
					<div class="adsd-instr-card">
						<div class="adsd-instr-num">2</div>
						<h4><?php esc_html_e( 'Windows', 'ad-sd-static-connector' ); ?></h4>
						<p><?php esc_html_e( 'Right-click the folder → "Send to" → "Compressed (zipped) folder". Your ZIP will appear in the same location.', 'ad-sd-static-connector' ); ?></p>
					</div>
					<div class="adsd-instr-card">
						<div class="adsd-instr-num">3</div>
						<h4><?php esc_html_e( 'Mac / Linux', 'ad-sd-static-connector' ); ?></h4>
						<p><?php esc_html_e( 'Right-click the folder → "Compress". Or in terminal: zip -r my-website.zip my-website/', 'ad-sd-static-connector' ); ?></p>
					</div>
					<div class="adsd-instr-card">
						<div class="adsd-instr-num">4</div>
						<h4><?php esc_html_e( 'Important Notes', 'ad-sd-static-connector' ); ?></h4>
						<p><?php esc_html_e( 'Do NOT include PHP files. Your ZIP should contain only HTML, CSS, JS, images and font files. Max size: 50MB.', 'ad-sd-static-connector' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Drop zone -->
		<div class="adsd-dropzone" id="adsd-dropzone">
			<span class="dashicons dashicons-cloud-upload adsd-dz-icon"></span>
			<p class="adsd-dz-text"><?php esc_html_e( 'Drag & drop your ZIP file here, or click to browse', 'ad-sd-static-connector' ); ?></p>
			<p class="adsd-dz-subtext"><?php esc_html_e( 'Supported: .zip files only | Max size: 50 MB', 'ad-sd-static-connector' ); ?></p>
			<?php /* Label-for is the ONLY browser-safe way to open file picker on real user click */ ?>
			<label for="adsd-zip-input" class="adsd-btn adsd-btn--primary adsd-browse-label" id="adsd-browse-btn">
				<span class="dashicons dashicons-folder-open"></span>
				<?php esc_html_e( 'Browse Files', 'ad-sd-static-connector' ); ?>
			</label>
			<input type="file" id="adsd-zip-input" name="zip_file" accept=".zip" class="adsd-file-input-hidden">
		</div>

		<!-- Upload progress -->
		<div class="adsd-upload-progress" id="adsd-upload-progress" style="display:none;">
			<div class="adsd-progress-bar">
				<div class="adsd-progress-fill" id="adsd-progress-fill"></div>
			</div>
			<p class="adsd-progress-text" id="adsd-progress-text"><?php esc_html_e( 'Uploading...', 'ad-sd-static-connector' ); ?></p>
		</div>

		<!-- Upload result message -->
		<div id="adsd-upload-result"></div>
	</div>

	<!-- ── YOUR FILES SECTION ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Your Files', 'ad-sd-static-connector' ); ?></h2>
			<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-refresh-zips">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'ad-sd-static-connector' ); ?>
			</button>
		</div>

		<div class="adsd-zip-grid" id="adsd-zip-grid">
			<div class="adsd-empty-state">
				<span class="dashicons dashicons-media-archive adsd-empty-icon"></span>
				<p><?php esc_html_e( 'No files uploaded yet. Upload your first ZIP above.', 'ad-sd-static-connector' ); ?></p>
			</div>
		</div>
	</div>

</div>

<!-- Code Check Modal -->
<div class="adsd-modal" id="adsd-code-check-modal" style="display:none;">
	<div class="adsd-modal-overlay"></div>
	<div class="adsd-modal-box adsd-modal-box--lg">
		<div class="adsd-modal-header">
			<h3><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Code Check Results', 'ad-sd-static-connector' ); ?></h3>
			<button type="button" class="adsd-modal-close">&times;</button>
		</div>
		<div class="adsd-modal-body" id="adsd-code-check-body">
			<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>
		</div>
	</div>
</div>

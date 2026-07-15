<?php
/**
 * Static File Manager tab.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="adsd-panel" id="adsd-file-manager">

	<!-- ZIP Selector -->
	<div class="adsd-section adsd-fm-selector-section">
		<div class="adsd-fm-selector-row">
			<label for="adsd-fm-zip-select" class="adsd-label">
				<span class="dashicons dashicons-media-archive"></span>
				<?php esc_html_e( 'Select Your Uploaded ZIP:', 'ad-sd-static-connector' ); ?>
			</label>
			<select id="adsd-fm-zip-select" class="adsd-select">
				<option value=""><?php esc_html_e( '— Choose a ZIP file —', 'ad-sd-static-connector' ); ?></option>
			</select>
			<button type="button" class="adsd-btn adsd-btn--ghost" id="adsd-fm-refresh">
				<span class="dashicons dashicons-update"></span>
			</button>
		</div>
	</div>

	<!-- File Browser -->
	<div class="adsd-fm-layout" id="adsd-fm-layout" style="display:none;">
		<div class="adsd-fm-sidebar" id="adsd-fm-sidebar">
			<div class="adsd-fm-sidebar-header">
				<span class="dashicons dashicons-media-code"></span>
				<span class="adsd-fm-sidebar-title"><?php esc_html_e( 'Files', 'ad-sd-static-connector' ); ?></span>
				<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--xs" id="adsd-fm-new-file" title="<?php esc_attr_e( 'Create new file inside ZIP', 'ad-sd-static-connector' ); ?>" style="margin-left:auto;margin-right:4px;">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'New File', 'ad-sd-static-connector' ); ?>
				</button>
				<button type="button" class="adsd-fm-sidebar-toggle" id="adsd-fm-sidebar-toggle" title="<?php esc_attr_e( 'Collapse sidebar', 'ad-sd-static-connector' ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
				</button>
			</div>
			<div class="adsd-file-tree" id="adsd-file-tree">
				<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>
			</div>
		</div>

		<div class="adsd-fm-main">
			<button type="button" class="adsd-fm-sidebar-expand" id="adsd-fm-sidebar-expand" title="<?php esc_attr_e( 'Expand sidebar', 'ad-sd-static-connector' ); ?>" style="display:none;">
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
			<!-- Default state -->
			<div class="adsd-fm-empty" id="adsd-fm-empty">
				<span class="dashicons dashicons-edit-large adsd-empty-icon"></span>
				<p><?php esc_html_e( 'Select a file from the left to edit, manage SEO, or delete.', 'ad-sd-static-connector' ); ?></p>
			</div>

			<!-- Editor Panel -->
			<div class="adsd-editor-panel" id="adsd-editor-panel" style="display:none;">
				<div class="adsd-editor-header">
					<div class="adsd-editor-info">
						<span class="dashicons dashicons-media-code"></span>
						<span id="adsd-editor-filename" class="adsd-editor-filename"></span>
						<span id="adsd-editor-filesize" class="adsd-editor-meta"></span>
						<span id="adsd-editor-modified" class="adsd-editor-meta"></span>
					</div>
					<div class="adsd-editor-toolbar">
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-find">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Find & Replace', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-goto">
							<span class="dashicons dashicons-editor-ol"></span>
							<?php esc_html_e( 'Go to Line', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-copy">
							<span class="dashicons dashicons-admin-page"></span>
							<?php esc_html_e( 'Copy', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-cut">
							<span class="dashicons dashicons-editor-removeformatting"></span>
							<?php esc_html_e( 'Cut', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-paste">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Paste', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-checkcode">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Check Code', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-beautify">
							<span class="dashicons dashicons-editor-code"></span>
							<?php esc_html_e( 'Beautify', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-theme">
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e( 'Light Mode', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-history">
							<span class="dashicons dashicons-backup"></span>
							<?php esc_html_e( 'Version History', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-reset">
							<span class="dashicons dashicons-undo"></span>
							<?php esc_html_e( 'Reset', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-run">
							<span class="dashicons dashicons-controls-play"></span>
							<?php esc_html_e( 'Run', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-fullscreen">
							<span class="dashicons dashicons-fullscreen-alt"></span>
							<span class="adsd-btn-label"><?php esc_html_e( 'Full Screen', 'ad-sd-static-connector' ); ?></span>
						</button>
						<div class="adsd-layout-switcher" id="adsd-layout-switcher">
							<button type="button" class="adsd-layout-btn adsd-layout-btn--active" data-layout="left-right" title="<?php esc_attr_e( 'Editor Left · Preview Right', 'ad-sd-static-connector' ); ?>">
								<svg width="18" height="14" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0.5" y="0.5" width="7" height="13" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.25"/><rect x="9.5" y="0.5" width="8" height="13" rx="1" stroke="currentColor"/></svg>
							</button>
							<button type="button" class="adsd-layout-btn" data-layout="right-left" title="<?php esc_attr_e( 'Preview Left · Editor Right', 'ad-sd-static-connector' ); ?>">
								<svg width="18" height="14" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0.5" y="0.5" width="8" height="13" rx="1" stroke="currentColor"/><rect x="10.5" y="0.5" width="7" height="13" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.25"/></svg>
							</button>
							<button type="button" class="adsd-layout-btn" data-layout="top-bottom" title="<?php esc_attr_e( 'Editor Top · Preview Bottom', 'ad-sd-static-connector' ); ?>">
								<svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0.5" y="0.5" width="13" height="7" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.25"/><rect x="0.5" y="9.5" width="13" height="8" rx="1" stroke="currentColor"/></svg>
							</button>
							<button type="button" class="adsd-layout-btn" data-layout="bottom-top" title="<?php esc_attr_e( 'Preview Top · Editor Bottom', 'ad-sd-static-connector' ); ?>">
								<svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0.5" y="0.5" width="13" height="8" rx="1" stroke="currentColor"/><rect x="0.5" y="10.5" width="13" height="7" rx="1" stroke="currentColor" fill="currentColor" fill-opacity="0.25"/></svg>
							</button>
						</div>
						<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-editor-preview-newtab">
							<span class="dashicons dashicons-external"></span>
							<span class="adsd-btn-label"><?php esc_html_e( 'Open Preview in New Tab', 'ad-sd-static-connector' ); ?></span>
						</button>
						<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--sm" id="adsd-editor-save">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save', 'ad-sd-static-connector' ); ?>
						</button>
					</div>
				</div>

				<!-- Find & Replace bar -->
				<div class="adsd-edt-findbar" id="adsd-edt-findbar" style="display:none;">
					<input type="text" id="adsd-edt-find-input" placeholder="<?php esc_attr_e( 'Find…', 'ad-sd-static-connector' ); ?>">
					<input type="text" id="adsd-edt-replace-input" placeholder="<?php esc_attr_e( 'Replace with…', 'ad-sd-static-connector' ); ?>">
					<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-edt-find-next"><?php esc_html_e( 'Find Next', 'ad-sd-static-connector' ); ?></button>
					<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-edt-replace-one"><?php esc_html_e( 'Replace', 'ad-sd-static-connector' ); ?></button>
					<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-edt-replace-all"><?php esc_html_e( 'Replace All', 'ad-sd-static-connector' ); ?></button>
					<span class="adsd-edt-findbar-result" id="adsd-edt-find-result"></span>
					<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-edt-find-close"><?php esc_html_e( 'Close', 'ad-sd-static-connector' ); ?></button>
				</div>

				<!-- Go to line bar -->
				<div class="adsd-edt-gotobar" id="adsd-edt-gotobar" style="display:none;">
					<label for="adsd-edt-goto-input"><?php esc_html_e( 'Line number:', 'ad-sd-static-connector' ); ?></label>
					<input type="number" id="adsd-edt-goto-input" min="1" step="1">
					<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--xs" id="adsd-edt-goto-go"><?php esc_html_e( 'Go', 'ad-sd-static-connector' ); ?></button>
					<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-edt-goto-close"><?php esc_html_e( 'Close', 'ad-sd-static-connector' ); ?></button>
				</div>

				<!-- Built-in Editor + Live Preview -->
				<div class="adsd-editor-body">
					<div class="adsd-editor-code-wrap">
						<div id="adsd-monaco-editor" class="adsd-monaco-editor"></div>
					</div>
					<div class="adsd-editor-divider" id="adsd-editor-divider"></div>
					<div class="adsd-editor-preview-wrap">
						<div class="adsd-preview-header">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Live Preview', 'ad-sd-static-connector' ); ?>
							<div class="adsd-preview-devices" id="adsd-preview-devices">
								<button type="button" class="adsd-device-btn adsd-device-btn--active" data-device="desktop" title="<?php esc_attr_e( 'Desktop', 'ad-sd-static-connector' ); ?>">
									<span class="dashicons dashicons-desktop"></span>
								</button>
								<button type="button" class="adsd-device-btn" data-device="tablet" title="<?php esc_attr_e( 'Tablet', 'ad-sd-static-connector' ); ?>">
									<span class="dashicons dashicons-tablet"></span>
								</button>
								<button type="button" class="adsd-device-btn" data-device="mobile" title="<?php esc_attr_e( 'Mobile', 'ad-sd-static-connector' ); ?>">
									<span class="dashicons dashicons-smartphone"></span>
								</button>
							</div>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-preview-refresh">
								<span class="dashicons dashicons-update"></span>
							</button>
						</div>
						<div class="adsd-preview-frame-wrap adsd-device--desktop" id="adsd-preview-frame-wrap">
							<iframe id="adsd-preview-frame" class="adsd-preview-frame" sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe>
						</div>
					</div>
				</div>
				<div id="adsd-editor-msg" class="adsd-editor-msg"></div>
			</div>

			<!-- SEO Panel -->
			<div class="adsd-seo-panel" id="adsd-seo-panel" style="display:none;">
				<div class="adsd-seo-header">
					<h3>
						<span class="dashicons dashicons-chart-line"></span>
						<?php esc_html_e( 'SEO Settings', 'ad-sd-static-connector' ); ?>
						&mdash; <span id="adsd-seo-filename" class="adsd-seo-filename"></span>
					</h3>
					<div class="adsd-seo-score-wrap">
						<div class="adsd-seo-score-ring" id="adsd-seo-score-ring">
							<svg viewBox="0 0 80 80">
								<circle cx="40" cy="40" r="34" class="adsd-score-bg"/>
								<circle cx="40" cy="40" r="34" class="adsd-score-fill" id="adsd-score-circle" stroke-dasharray="213.6 213.6"/>
							</svg>
							<span class="adsd-score-num" id="adsd-score-num">0</span>
						</div>
						<span class="adsd-score-label"><?php esc_html_e( 'SEO Score', 'ad-sd-static-connector' ); ?></span>
					</div>
				</div>

				<div class="adsd-seo-form" id="adsd-seo-form">
					<?php
					$adsd_seo_fields = array(
						'seo_title'     => array( 'label' => __( 'SEO Title', 'ad-sd-static-connector' ), 'type' => 'text', 'hint' => __( 'Ideal length: 10–60 characters', 'ad-sd-static-connector' ) ),
						'meta_desc'     => array( 'label' => __( 'Meta Description', 'ad-sd-static-connector' ), 'type' => 'textarea', 'hint' => __( 'Ideal length: 50–155 characters', 'ad-sd-static-connector' ) ),
						'meta_keywords' => array( 'label' => __( 'Meta Keywords', 'ad-sd-static-connector' ), 'type' => 'text', 'hint' => __( 'Comma-separated keywords', 'ad-sd-static-connector' ) ),
						'og_title'      => array( 'label' => __( 'Open Graph Title', 'ad-sd-static-connector' ), 'type' => 'text', 'hint' => __( 'Used when shared on social media', 'ad-sd-static-connector' ) ),
						'og_desc'       => array( 'label' => __( 'Open Graph Description', 'ad-sd-static-connector' ), 'type' => 'textarea', 'hint' => __( 'Social media preview description', 'ad-sd-static-connector' ) ),
						'og_image'      => array( 'label' => __( 'Open Graph Image URL', 'ad-sd-static-connector' ), 'type' => 'url', 'hint' => __( 'Recommended: 1200×630px image URL', 'ad-sd-static-connector' ) ),
						'canonical'     => array( 'label' => __( 'Canonical URL', 'ad-sd-static-connector' ), 'type' => 'url', 'hint' => __( 'Preferred URL for search engines', 'ad-sd-static-connector' ) ),
					);
					foreach ( $adsd_seo_fields as $adsd_field_key => $adsd_field ) :
					?>
					<div class="adsd-seo-field">
						<div class="adsd-seo-field-header">
							<label for="adsd-seo-<?php echo esc_attr( $adsd_field_key ); ?>" class="adsd-label">
								<?php echo esc_html( $adsd_field['label'] ); ?>
							</label>
							<button type="button" class="adsd-btn adsd-btn--auto-seo adsd-btn--xs"
								data-field="<?php echo esc_attr( $adsd_field_key ); ?>">
								<span class="dashicons dashicons-magic"></span>
								<?php esc_html_e( 'Auto Generate', 'ad-sd-static-connector' ); ?>
							</button>
						</div>
						<?php if ( 'textarea' === $adsd_field['type'] ) : ?>
							<textarea id="adsd-seo-<?php echo esc_attr( $adsd_field_key ); ?>"
								class="adsd-seo-input adsd-textarea"
								name="<?php echo esc_attr( $adsd_field_key ); ?>"
								rows="3"></textarea>
						<?php else : ?>
							<input type="<?php echo esc_attr( $adsd_field['type'] ); ?>"
								id="adsd-seo-<?php echo esc_attr( $adsd_field_key ); ?>"
								class="adsd-seo-input adsd-input"
								name="<?php echo esc_attr( $adsd_field_key ); ?>">
						<?php endif; ?>
						<p class="adsd-field-hint"><?php echo esc_html( $adsd_field['hint'] ); ?></p>
						<div class="adsd-char-count" id="adsd-char-<?php echo esc_attr( $adsd_field_key ); ?>"></div>
					</div>
					<?php endforeach; ?>

					<div class="adsd-seo-field">
						<label for="adsd-seo-robots" class="adsd-label"><?php esc_html_e( 'Robots', 'ad-sd-static-connector' ); ?></label>
						<select id="adsd-seo-robots" class="adsd-select adsd-seo-input" name="robots">
							<option value="index, follow"><?php esc_html_e( 'Index, Follow (recommended)', 'ad-sd-static-connector' ); ?></option>
							<option value="noindex, follow"><?php esc_html_e( 'No Index, Follow', 'ad-sd-static-connector' ); ?></option>
							<option value="index, nofollow"><?php esc_html_e( 'Index, No Follow', 'ad-sd-static-connector' ); ?></option>
							<option value="noindex, nofollow"><?php esc_html_e( 'No Index, No Follow', 'ad-sd-static-connector' ); ?></option>
						</select>
					</div>

					<div class="adsd-seo-field">
						<label for="adsd-seo-schema_type" class="adsd-label"><?php esc_html_e( 'Schema Type', 'ad-sd-static-connector' ); ?></label>
						<select id="adsd-seo-schema_type" class="adsd-select adsd-seo-input" name="schema_type">
							<option value="WebPage">WebPage</option>
							<option value="Article">Article</option>
							<option value="Product">Product</option>
							<option value="LocalBusiness">LocalBusiness</option>
							<option value="Organization">Organization</option>
						</select>
					</div>

					<div class="adsd-seo-actions">
						<button type="button" class="adsd-btn adsd-btn--primary" id="adsd-seo-save">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save SEO Settings', 'ad-sd-static-connector' ); ?>
						</button>
						<button type="button" class="adsd-btn adsd-btn--ghost" id="adsd-seo-cancel">
							<span class="dashicons dashicons-no"></span>
							<?php esc_html_e( 'Cancel', 'ad-sd-static-connector' ); ?>
						</button>
					</div>
					<div id="adsd-seo-msg" class="adsd-editor-msg"></div>
				</div>
			</div>
		</div>
	</div>

</div>

<!-- Version History Modal -->
<div class="adsd-modal" id="adsd-version-modal" style="display:none;">
	<div class="adsd-modal-overlay"></div>
	<div class="adsd-modal-box">
		<div class="adsd-modal-header">
			<h3><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Version History', 'ad-sd-static-connector' ); ?></h3>
			<button type="button" class="adsd-modal-close">&times;</button>
		</div>
		<div class="adsd-modal-body" id="adsd-version-body"></div>
	</div>
</div>
<!-- LAYOUT SWITCHER PLACEHOLDER - will be injected by JS -->

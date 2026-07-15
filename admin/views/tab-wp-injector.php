<?php
/**
 * Tab: WP Page Injector
 * Inject custom head, header, footer, scripts into WordPress pages only.
 * Static ZIP pages are NOT affected by this tab.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load saved values.
$adsd_injector_enabled  = (bool) get_option( 'adsd_wp_injector_enabled', 0 );
$adsd_disable_all_hf    = (bool) get_option( 'adsd_wp_disable_all_hf', 0 );
$adsd_head_code         = get_option( 'adsd_wp_head_code', '' );
$adsd_header_html       = get_option( 'adsd_wp_header_html', '' );
$adsd_footer_html       = get_option( 'adsd_wp_footer_html', '' );
$adsd_script_html       = get_option( 'adsd_wp_script_html', '' );
$adsd_custom_css        = get_option( 'adsd_wp_custom_css', '' );
$adsd_custom_js         = get_option( 'adsd_wp_custom_js', '' );
$adsd_page_404          = get_option( 'adsd_wp_404_html', '' );
?>

<div class="adsd-injector-wrap">

	<div class="adsd-section-header">
		<span class="dashicons dashicons-editor-code"></span>
		<div>
			<h2><?php esc_html_e( 'WP Page Injector', 'ad-sd-static-connector' ); ?></h2>
			<p class="adsd-section-desc">
				<?php esc_html_e( 'Inject your static site\'s header, footer, CSS, JS and head code into WordPress pages — no page builder needed. These codes will NOT load on your uploaded static ZIP pages.', 'ad-sd-static-connector' ); ?>
			</p>
		</div>
	</div>



	<!-- ── DISABLE HEADER & FOOTER FOR ALL PAGES (combined toggle) ──── -->
	<div class="adsd-injector-theme-toggle <?php echo $adsd_disable_all_hf ? 'adsd-injector-theme-toggle--on' : ''; ?>">
		<div class="adsd-injector-theme-toggle-left">
			<span class="dashicons dashicons-visibility"></span>
			<div>
				<h4><?php esc_html_e( 'Disable Header & Footer for all pages', 'ad-sd-static-connector' ); ?></h4>
				<p><?php esc_html_e( 'Turn ON to remove the default WordPress theme header/footer AND any header/footer injected by other plugins (Elementor Pro, Divi, WooCommerce, Genesis, etc.) from all WP pages. Your custom Header HTML and Footer HTML below will be used instead. This also automatically activates the WP Page Injector.', 'ad-sd-static-connector' ); ?></p>
				<div class="adsd-injector-theme-warning <?php echo $adsd_disable_all_hf ? 'adsd-injector-theme-warning--visible' : ''; ?>">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Theme & plugin header/footer are DISABLED. Only your injected code will appear on WP pages.', 'ad-sd-static-connector' ); ?>
				</div>
			</div>
		</div>
		<label class="adsd-toggle-switch" title="<?php esc_attr_e( 'Disable all header & footer and activate injector', 'ad-sd-static-connector' ); ?>">
			<input type="checkbox" id="adsd_wp_disable_all_hf" <?php checked( $adsd_disable_all_hf ); ?> />
			<span class="adsd-toggle-slider"></span>
		</label>
	</div>


	<!-- ── CONTENT CONTAINER WIDTH ─────────────────────────────────── -->
	<div class="adsd-injector-section adsd-injector-section--container">
		<div class="adsd-injector-section-header">
			<div class="adsd-injector-section-icon" style="background:#f0f9ff;">
				<span class="dashicons dashicons-editor-expand" style="color:#0284c7;"></span>
			</div>
			<div class="adsd-injector-section-info">
				<h3><?php esc_html_e( 'Content Container Width', 'ad-sd-static-connector' ); ?></h3>
				<p><?php esc_html_e( 'When Header & Footer is disabled, plugin/page content (WooCommerce, posts etc.) may stretch full-width. Set a max-width container here to keep content readable on all screens.', 'ad-sd-static-connector' ); ?></p>
			</div>
		</div>

		<div class="adsd-container-width-grid">

			<!-- Enable toggle -->
			<div class="adsd-container-width-row">
				<label class="adsd-label" for="adsd_container_enabled"><?php esc_html_e( 'Enable Content Container', 'ad-sd-static-connector' ); ?></label>
				<label class="adsd-toggle-switch">
					<input type="checkbox" id="adsd_container_enabled" <?php checked( get_option( 'adsd_container_enabled', 0 ) ); ?> />
					<span class="adsd-toggle-slider"></span>
				</label>
				<p class="adsd-field-hint"><?php esc_html_e( 'Wraps page content in a centered max-width container.', 'ad-sd-static-connector' ); ?></p>
			</div>

			<!-- Width inputs per device -->
			<div class="adsd-container-devices" id="adsd-container-devices">

				<div class="adsd-container-device-row">
					<div class="adsd-container-device-label">
						<span class="dashicons dashicons-desktop"></span>
						<span><?php esc_html_e( 'Desktop', 'ad-sd-static-connector' ); ?></span>
						<small><?php esc_html_e( '(≥ 1024px screen)', 'ad-sd-static-connector' ); ?></small>
					</div>
					<div class="adsd-container-input-wrap">
						<input type="number" id="adsd_container_desktop" class="adsd-input adsd-input--sm" min="320" max="3840"
							value="<?php echo esc_attr( get_option( 'adsd_container_desktop', '1200' ) ); ?>" />
						<span class="adsd-input-unit">px</span>
					</div>
				</div>

				<div class="adsd-container-device-row">
					<div class="adsd-container-device-label">
						<span class="dashicons dashicons-tablet"></span>
						<span><?php esc_html_e( 'Tablet', 'ad-sd-static-connector' ); ?></span>
						<small><?php esc_html_e( '(768px – 1023px screen)', 'ad-sd-static-connector' ); ?></small>
					</div>
					<div class="adsd-container-input-wrap">
						<input type="number" id="adsd_container_tablet" class="adsd-input adsd-input--sm" min="320" max="1200"
							value="<?php echo esc_attr( get_option( 'adsd_container_tablet', '900' ) ); ?>" />
						<span class="adsd-input-unit">px</span>
					</div>
				</div>

				<div class="adsd-container-device-row">
					<div class="adsd-container-device-label">
						<span class="dashicons dashicons-smartphone"></span>
						<span><?php esc_html_e( 'Mobile', 'ad-sd-static-connector' ); ?></span>
						<small><?php esc_html_e( '(< 768px screen)', 'ad-sd-static-connector' ); ?></small>
					</div>
					<div class="adsd-container-input-wrap">
						<input type="number" id="adsd_container_mobile" class="adsd-input adsd-input--sm" min="280" max="767"
							value="<?php echo esc_attr( get_option( 'adsd_container_mobile', '100' ) ); ?>" />
						<span class="adsd-input-unit">%</span>
						<small class="adsd-input-note"><?php esc_html_e( '(% of screen)', 'ad-sd-static-connector' ); ?></small>
					</div>
				</div>

				<!-- Padding input -->
				<div class="adsd-container-device-row">
					<div class="adsd-container-device-label">
						<span class="dashicons dashicons-editor-indent"></span>
						<span><?php esc_html_e( 'Side Padding', 'ad-sd-static-connector' ); ?></span>
						<small><?php esc_html_e( '(left & right gap)', 'ad-sd-static-connector' ); ?></small>
					</div>
					<div class="adsd-container-input-wrap">
						<input type="number" id="adsd_container_padding" class="adsd-input adsd-input--sm" min="0" max="200"
							value="<?php echo esc_attr( get_option( 'adsd_container_padding', '16' ) ); ?>" />
						<span class="adsd-input-unit">px</span>
					</div>
				</div>

				<div class="adsd-container-device-row adsd-container-device-row--highlight">
					<div class="adsd-container-device-label">
						<span class="dashicons dashicons-sort"></span>
						<span><?php esc_html_e( 'Margin Top', 'ad-sd-static-connector' ); ?></span>
						<small><?php esc_html_e( '(space above content, below header)', 'ad-sd-static-connector' ); ?></small>
					</div>
					<div class="adsd-container-input-wrap">
						<input type="number" id="adsd_container_margin_top" class="adsd-input adsd-input--sm" min="0" max="500"
							value="<?php echo esc_attr( get_option( 'adsd_container_margin_top', '0' ) ); ?>" />
						<span class="adsd-input-unit">px</span>
					</div>
				</div>

			</div><!-- .adsd-container-devices -->

			<div class="adsd-container-preview" id="adsd-container-preview">
				<div class="adsd-container-preview-label"><?php esc_html_e( 'Live Preview', 'ad-sd-static-connector' ); ?></div>
				<div class="adsd-container-preview-screen">
					<div class="adsd-container-preview-box" id="adsd-preview-box">
						<span id="adsd-preview-box-label"><?php esc_html_e( 'Page Content Area', 'ad-sd-static-connector' ); ?></span>
					</div>
				</div>
			</div>

		</div><!-- .adsd-container-width-grid -->
	</div>

	<div class="adsd-notice adsd-notice--info" style="margin-bottom:28px;">
		<span class="dashicons dashicons-info"></span>
		<div>
			<?php esc_html_e( 'All code here only runs on WordPress pages (posts, pages, WooCommerce etc). Your static ZIP site pages are completely unaffected.', 'ad-sd-static-connector' ); ?>
			<br><strong><?php esc_html_e( 'CSS/HTML loads first, then JS is automatically moved to page bottom for best performance.', 'ad-sd-static-connector' ); ?></strong>
		</div>
	</div>

	<form id="adsd-injector-form">

		<!-- ── SECTION 1: HEAD CODE ─────────────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-editor-code"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Head Code', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Paste your static file\'s &lt;head&gt; tag content — meta tags, font links, favicon, CSS links etc. Any &lt;script&gt; tags found here are automatically moved to page bottom (loaded last).', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--html">HTML</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_head_code">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_head_code" name="adsd_wp_head_code" class="adsd-injector-textarea" rows="8"
					placeholder="<?php echo esc_attr( '<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">' ); ?>"
				><?php echo esc_textarea( $adsd_head_code ); ?></textarea>
			</div>
		</div>

		<!-- ── SECTION 2: HEADER HTML ────────────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-align-center"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Header HTML', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Paste your static header HTML code. It will appear at the top of every WordPress page body. Relative file paths (css/style.css, images/logo.png) are auto-resolved to your live ZIP files.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--html">HTML</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_header_html">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_header_html" name="adsd_wp_header_html" class="adsd-injector-textarea" rows="10"
					placeholder="<?php echo esc_attr( '<header class="site-header">...</header>' ); ?>"
				><?php echo esc_textarea( $adsd_header_html ); ?></textarea>
			</div>
		</div>

		<!-- ── SECTION 3: FOOTER HTML ────────────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-align-center" style="transform:scaleY(-1)"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Footer HTML', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Paste your static footer HTML. It will appear just before &lt;/body&gt; on every WordPress page. Relative paths are auto-resolved to your live ZIP.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--html">HTML</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_footer_html">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_footer_html" name="adsd_wp_footer_html" class="adsd-injector-textarea" rows="10"
					placeholder="<?php echo esc_attr( '<footer class="site-footer">...</footer>' ); ?>"
				><?php echo esc_textarea( $adsd_footer_html ); ?></textarea>
			</div>
		</div>

		<!-- ── SECTION 4: SCRIPT FILE CODE ──────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-media-code"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Script File Tags', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Paste &lt;script src="..."&gt; tags for your JS files. These are automatically loaded at the BOTTOM of every WP page (after CSS and HTML) for best performance. Do not add jQuery here — WordPress already includes it.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--js">JS</span>
					<span class="adsd-badge" style="background:#f0fdf4;color:#166534;font-size:10px;">Loaded at bottom ✓</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_script_html">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_script_html" name="adsd_wp_script_html" class="adsd-injector-textarea" rows="6"
					placeholder="<?php echo esc_attr( '/* Paste external script tags here */' ); ?>"
				><?php echo esc_textarea( $adsd_script_html ); ?></textarea>
			</div>
		</div>

		<!-- ── SECTION 5: CUSTOM CSS ─────────────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-art"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Custom CSS', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Write custom CSS. Injected inside a &lt;style&gt; tag in &lt;head&gt; on every WordPress page. Use this for styling fixes specific to WP pages.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--css">CSS</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_custom_css">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_custom_css" name="adsd_wp_custom_css" class="adsd-injector-textarea" rows="8"
					placeholder="<?php esc_attr_e( 'body { font-family: sans-serif; }', 'ad-sd-static-connector' ); ?>"
				><?php echo esc_textarea( $adsd_custom_css ); ?></textarea>
			</div>
		</div>

		<!-- ── SECTION 6: CUSTOM JS ──────────────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-editor-code"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Custom JavaScript', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Write custom JS. Loaded last — inside a &lt;script&gt; tag at the very end of &lt;body&gt;, after all other scripts.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--js">JS</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_custom_js">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_custom_js" name="adsd_wp_custom_js" class="adsd-injector-textarea" rows="8"
					placeholder="<?php esc_attr_e( 'document.addEventListener("DOMContentLoaded", function() { });', 'ad-sd-static-connector' ); ?>"
				><?php echo esc_textarea( $adsd_custom_js ); ?></textarea>
			</div>
		</div>

		<!-- ── SECTION 7: 404 PAGE ───────────────────────────────────── -->
		<div class="adsd-injector-section">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( '404 Page HTML', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Paste a complete custom 404 page HTML. When a WordPress page is not found, this HTML replaces the default theme 404. Leave blank to use the theme default.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-injector-editor-wrap">
				<div class="adsd-injector-editor-toolbar">
					<span class="adsd-badge adsd-badge--html">HTML</span>
					<button type="button" class="adsd-btn adsd-btn--xs adsd-injector-clear" data-target="adsd_wp_404_html">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
				<textarea id="adsd_wp_404_html" name="adsd_wp_404_html" class="adsd-injector-textarea" rows="10"
					placeholder="<?php esc_attr_e( '<!DOCTYPE html><html>...<h1>404 - Page Not Found</h1>...</html>', 'ad-sd-static-connector' ); ?>"
				><?php echo esc_textarea( $adsd_page_404 ); ?></textarea>
			</div>
		</div>


		<!-- ── SECTION: ADSD POST TEMPLATE ───────────────────────────── -->
		<div class="adsd-injector-section adsd-injector-section--post-tpl" style="border-top:3px solid #8b5cf6;">
			<div class="adsd-injector-section-header">
				<div class="adsd-injector-section-icon" style="background:#f5f3ff;">
					<span class="dashicons dashicons-text-page" style="color:#8b5cf6;"></span>
				</div>
				<div class="adsd-injector-section-info">
					<h3><?php esc_html_e( 'Enable ADSD Post Template', 'ad-sd-static-connector' ); ?></h3>
					<p><?php esc_html_e( 'Replace the default theme single-post layout with a beautiful classical design. Layout order: Title → Featured Image → Meta Info → Excerpt Box → Content → Share → Author → Comments → Related Posts.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>

			<div class="adsd-post-tpl-grid">

				<!-- Main toggle -->
				<div class="adsd-post-tpl-toggle-row">
					<div>
						<label class="adsd-label" for="adsd_post_template_enabled" style="font-size:15px;font-weight:700;"><?php esc_html_e( 'Activate Post Template', 'ad-sd-static-connector' ); ?></label>
						<p style="color:#6b7280;font-size:13px;margin-top:3px;"><?php esc_html_e( 'When ON, all single posts use the ADSD classical layout instead of your theme.', 'ad-sd-static-connector' ); ?></p>
					</div>
					<label class="adsd-toggle-switch">
						<input type="checkbox" id="adsd_post_template_enabled" <?php checked( get_option( 'adsd_post_template_enabled', 0 ) ); ?> />
						<span class="adsd-toggle-slider"></span>
					</label>
				</div>

				<!-- Meta info options -->
				<div class="adsd-post-tpl-options" id="adsd-post-tpl-options">

					<div class="adsd-post-tpl-options-title"><?php esc_html_e( 'Meta Info — Choose what to show', 'ad-sd-static-connector' ); ?></div>

					<div class="adsd-post-tpl-options-grid">

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_meta_author" <?php checked( get_option( 'adsd_post_meta_author', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">👤</span>
							<span><?php esc_html_e( 'Author Name & Avatar', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_meta_date" <?php checked( get_option( 'adsd_post_meta_date', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">📅</span>
							<span><?php esc_html_e( 'Publish Date', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_meta_category" <?php checked( get_option( 'adsd_post_meta_category', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">🗂️</span>
							<span><?php esc_html_e( 'Category Badges', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_meta_tags" <?php checked( get_option( 'adsd_post_meta_tags', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">🏷️</span>
							<span><?php esc_html_e( 'Tags', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_meta_read_time" <?php checked( get_option( 'adsd_post_meta_read_time', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">⏱️</span>
							<span><?php esc_html_e( 'Read Time', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_meta_views" <?php checked( get_option( 'adsd_post_meta_views', 0 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">👁️</span>
							<span><?php esc_html_e( 'View Count', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_show_excerpt" <?php checked( get_option( 'adsd_post_show_excerpt', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">💬</span>
							<span><?php esc_html_e( 'Excerpt Box', 'ad-sd-static-connector' ); ?></span>
						</label>

						<label class="adsd-post-tpl-option">
							<input type="checkbox" id="adsd_post_show_related" <?php checked( get_option( 'adsd_post_show_related', 1 ) ); ?> />
							<span class="adsd-post-tpl-option-icon">🔗</span>
							<span><?php esc_html_e( 'Related Posts', 'ad-sd-static-connector' ); ?></span>
						</label>

					</div><!-- .adsd-post-tpl-options-grid -->

					<!-- Related count -->
					<div class="adsd-post-tpl-related-row" id="adsd-related-count-row">
						<label class="adsd-label" for="adsd_post_related_count"><?php esc_html_e( 'Number of Related Posts', 'ad-sd-static-connector' ); ?></label>
						<div class="adsd-container-input-wrap">
							<input type="number" id="adsd_post_related_count" class="adsd-input adsd-input--sm" min="1" max="6"
								value="<?php echo esc_attr( get_option( 'adsd_post_related_count', 3 ) ); ?>" />
							<span class="adsd-input-unit"><?php esc_html_e( 'posts (max 6)', 'ad-sd-static-connector' ); ?></span>
						</div>
					</div>

					<!-- Preview link -->
					<?php
					$adsd_sample_post = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
					if ( $adsd_sample_post ) :
					?>
					<div style="margin-top:16px;">
						<a href="<?php echo esc_url( get_permalink( $adsd_sample_post[0]->ID ) ); ?>" target="_blank" class="adsd-btn adsd-btn--ghost adsd-btn--sm" style="border-color:#8b5cf6;color:#8b5cf6;">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Preview on latest post', 'ad-sd-static-connector' ); ?>
						</a>
					</div>
					<?php endif; ?>

				</div><!-- .adsd-post-tpl-options -->

			</div><!-- .adsd-post-tpl-grid -->
		</div>

		<!-- ── SAVE BUTTON ───────────────────────────────────────────── -->
		<div class="adsd-injector-footer">
			<button type="submit" id="adsd-injector-save" class="adsd-btn adsd-btn--primary adsd-btn--lg">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Save All Settings', 'ad-sd-static-connector' ); ?>
			</button>
			<span id="adsd-injector-status" class="adsd-injector-status"></span>
		</div>

	</form>


	<!-- ── HOW TO USE: ADSD PAGE TEMPLATE ──────────────────────────────── -->
	<div class="adsd-injector-section adsd-injector-section--howto">
		<div class="adsd-injector-section-header">
			<div class="adsd-injector-section-icon" style="background:#f0f9ff;">
				<span class="dashicons dashicons-admin-links" style="color:#0284c7;"></span>
			</div>
			<div class="adsd-injector-section-info">
				<h3><?php esc_html_e( 'How to Use: ADSD Page Template (Recommended)', 'ad-sd-static-connector' ); ?></h3>
				<p><?php esc_html_e( 'Instead of injecting into WP theme pages, use the ADSD standalone template. It shows your header + any WP page content + your footer — with ZERO theme/plugin conflicts.', 'ad-sd-static-connector' ); ?></p>
			</div>
		</div>

		<div class="adsd-howto-grid">
			<div class="adsd-howto-step">
				<div class="adsd-howto-step-num">1</div>
				<div>
					<strong><?php esc_html_e( 'Save your header + footer above', 'ad-sd-static-connector' ); ?></strong>
					<p><?php esc_html_e( 'Fill in Header HTML and Footer HTML sections above and save.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-howto-step">
				<div class="adsd-howto-step-num">2</div>
				<div>
					<strong><?php esc_html_e( 'Enable WP Page Injector', 'ad-sd-static-connector' ); ?></strong>
					<p><?php esc_html_e( 'Turn ON the master toggle at the top of this tab.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
			<div class="adsd-howto-step">
				<div class="adsd-howto-step-num">3</div>
				<div>
					<strong><?php esc_html_e( 'Use this URL pattern to show any WP page', 'ad-sd-static-connector' ); ?></strong>
					<p><?php esc_html_e( 'Replace any WP page link with:', 'ad-sd-static-connector' ); ?></p>
					<div class="adsd-howto-url-example">
						<code id="adsd-page-url-example"><?php echo esc_html( home_url( '/adsd-page/?url=' ) ); ?><em><?php esc_html_e( 'YOUR-PAGE-URL', 'ad-sd-static-connector' ); ?></em></code>
						<button type="button" class="adsd-btn adsd-btn--xs" id="adsd-copy-page-url">
							<span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy Base URL', 'ad-sd-static-connector' ); ?>
						</button>
					</div>
				</div>
			</div>
			<div class="adsd-howto-step">
				<div class="adsd-howto-step-num">4</div>
				<div>
					<strong><?php esc_html_e( 'Example: My Account page', 'ad-sd-static-connector' ); ?></strong>
					<p><?php esc_html_e( 'In your static ZIP header, change the My Account link from:', 'ad-sd-static-connector' ); ?></p>
					<code class="adsd-howto-code"><?php echo esc_html( home_url( '/my-account/' ) ); ?></code>
					<p><?php esc_html_e( 'To:', 'ad-sd-static-connector' ); ?></p>
					<code class="adsd-howto-code"><?php echo esc_html( home_url( '/adsd-page/?url=' ) . rawurlencode( home_url( '/my-account/' ) ) ); ?></code>
					<p class="adsd-howto-result"><?php esc_html_e( '→ The page will open with your custom header/footer, and WooCommerce My Account content in the middle.', 'ad-sd-static-connector' ); ?></p>
				</div>
			</div>
		</div>

		<div class="adsd-howto-preview-link">
			<a href="<?php echo esc_url( home_url( '/adsd-page/?url=' . rawurlencode( home_url( '/' ) ) ) ); ?>" target="_blank" class="adsd-btn adsd-btn--primary">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Preview ADSD Page Template (opens home page in template)', 'ad-sd-static-connector' ); ?>
			</a>
		</div>
	</div>

</div><!-- .adsd-injector-wrap -->

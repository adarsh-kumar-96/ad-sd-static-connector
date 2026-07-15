<?php
/**
 * Shortcode Bridge tab view.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="adsd-panel" id="adsd-bridge">

	<!-- ── SECTION 1: Shortcode to HTML Div ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-shortcode"></span> <?php esc_html_e( 'Shortcode to Embeddable HTML', 'ad-sd-static-connector' ); ?></h2>
		</div>
		<p class="adsd-section-desc">
			<?php esc_html_e( 'Paste any WordPress shortcode below and generate a custom HTML block. Copy that block and paste it into any file in your static ZIP — the shortcode content will load dynamically on the frontend.', 'ad-sd-static-connector' ); ?>
		</p>

		<div class="adsd-bridge-form">
			<div class="adsd-field-row">
				<label for="adsd-shortcode-input" class="adsd-label">
					<?php esc_html_e( 'Enter Shortcode', 'ad-sd-static-connector' ); ?>
				</label>
				<div class="adsd-shortcode-input-wrap">
					<input type="text" id="adsd-shortcode-input" class="adsd-input adsd-input--code"
						placeholder='<?php esc_attr_e( '[products limit="4" columns="4"]', 'ad-sd-static-connector' ); ?>'>
					<button type="button" class="adsd-btn adsd-btn--primary" id="adsd-gen-shortcode-btn">
						<span class="dashicons dashicons-controls-play"></span>
						<?php esc_html_e( 'Generate HTML Code', 'ad-sd-static-connector' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Generated Code Output -->
		<div class="adsd-code-output" id="adsd-sc-output" style="display:none;">
			<div class="adsd-code-output-header">
				<span class="dashicons dashicons-editor-code"></span>
				<?php esc_html_e( 'Generated HTML Block — Copy & paste into your static HTML file', 'ad-sd-static-connector' ); ?>
				<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-copy-btn" data-target="adsd-sc-code-block">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy Code', 'ad-sd-static-connector' ); ?>
				</button>
			</div>
			<pre class="adsd-code-block" id="adsd-sc-code-block"></pre>
			<div class="adsd-code-hint">
				<span class="dashicons dashicons-info-outline"></span>
				<?php esc_html_e( 'Paste this block inside any HTML file in your ZIP (e.g., inside the <body> tag, after a section). The content will load dynamically from WordPress.', 'ad-sd-static-connector' ); ?>
			</div>
		</div>
	</div>

	<!-- ── SECTION 2: Filter-Based Content Generator ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter-Based Content Block', 'ad-sd-static-connector' ); ?></h2>
		</div>
		<p class="adsd-section-desc">
			<?php esc_html_e( 'Build a dynamic content block by choosing a post type and applying filters. The generated code fetches filtered content from WordPress and renders it in your static HTML using your chosen layout.', 'ad-sd-static-connector' ); ?>
		</p>

		<div class="adsd-filter-builder">
			<div class="adsd-filter-grid">

				<!-- Post Type -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Post Type', 'ad-sd-static-connector' ); ?></label>
					<select id="adsd-filter-post-type" class="adsd-select">
						<?php
						// List ALL public post types — built-ins (post/page) plus
						// WooCommerce products and any custom post type registered
						// by other plugins (e.g. ACF, CPT UI), so users filtering
						// on those post types can build content blocks too.
						$adsd_public_types = get_post_types( array( 'public' => true ), 'objects' );
						unset( $adsd_public_types['attachment'] );
						foreach ( $adsd_public_types as $adsd_pt_slug => $adsd_pt_obj ) {
							$adsd_pt_label = 'product' === $adsd_pt_slug
								? __( 'Products (WooCommerce)', 'ad-sd-static-connector' )
								: $adsd_pt_obj->labels->name;
							?>
							<option value="<?php echo esc_attr( $adsd_pt_slug ); ?>" <?php selected( $adsd_pt_slug, 'post' ); ?>>
								<?php echo esc_html( $adsd_pt_label ); ?>
							</option>
							<?php
						}
						?>
					</select>
				</div>

				<!-- Layout -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Layout', 'ad-sd-static-connector' ); ?></label>
					<select id="adsd-filter-layout" class="adsd-select">
						<option value="0"><?php esc_html_e( '— Default Layout —', 'ad-sd-static-connector' ); ?></option>
					</select>
				</div>

				<!-- Count -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Number of Items', 'ad-sd-static-connector' ); ?></label>
					<input type="number" id="adsd-filter-count" class="adsd-input" value="4" min="1" max="50">
				</div>

				<!-- Columns -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Columns per Row', 'ad-sd-static-connector' ); ?></label>
					<input type="number" id="adsd-filter-columns" class="adsd-input" value="3" min="1" max="6">
				</div>

				<!-- Category -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Category (slug)', 'ad-sd-static-connector' ); ?></label>
					<input type="text" id="adsd-filter-category" class="adsd-input" list="adsd-filter-category-list" autocomplete="off"
						placeholder="<?php esc_attr_e( 'e.g. shoes', 'ad-sd-static-connector' ); ?>">
					<datalist id="adsd-filter-category-list"></datalist>
				</div>

				<!-- Tag -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Tag (slug)', 'ad-sd-static-connector' ); ?></label>
					<input type="text" id="adsd-filter-tag" class="adsd-input" list="adsd-filter-tag-list" autocomplete="off"
						placeholder="<?php esc_attr_e( 'e.g. sale', 'ad-sd-static-connector' ); ?>">
					<datalist id="adsd-filter-tag-list"></datalist>
				</div>

				<!-- Order By -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Order By', 'ad-sd-static-connector' ); ?></label>
					<select id="adsd-filter-orderby" class="adsd-select">
						<option value="date"><?php esc_html_e( 'Date (newest)', 'ad-sd-static-connector' ); ?></option>
						<option value="title"><?php esc_html_e( 'Title (A-Z)', 'ad-sd-static-connector' ); ?></option>
						<option value="menu_order"><?php esc_html_e( 'Menu Order', 'ad-sd-static-connector' ); ?></option>
						<option value="rand"><?php esc_html_e( 'Random', 'ad-sd-static-connector' ); ?></option>
						<option value="modified"><?php esc_html_e( 'Last Modified', 'ad-sd-static-connector' ); ?></option>
					</select>
				</div>

				<!-- Order -->
				<div class="adsd-filter-field">
					<label class="adsd-label"><?php esc_html_e( 'Order', 'ad-sd-static-connector' ); ?></label>
					<select id="adsd-filter-order" class="adsd-select">
						<option value="DESC"><?php esc_html_e( 'Descending', 'ad-sd-static-connector' ); ?></option>
						<option value="ASC"><?php esc_html_e( 'Ascending', 'ad-sd-static-connector' ); ?></option>
					</select>
				</div>

			</div>

			<!-- WooCommerce extra filters (shown when product is selected) -->
			<div class="adsd-woo-filters" id="adsd-woo-filters" style="display:none;">
				<div class="adsd-woo-filters-title">
					<span class="dashicons dashicons-cart"></span>
					<?php esc_html_e( 'WooCommerce Filters', 'ad-sd-static-connector' ); ?>
				</div>
				<div class="adsd-checkbox-row">
					<label class="adsd-checkbox-label">
						<input type="checkbox" id="adsd-filter-featured">
						<?php esc_html_e( 'Featured products only', 'ad-sd-static-connector' ); ?>
					</label>
					<label class="adsd-checkbox-label">
						<input type="checkbox" id="adsd-filter-sale">
						<?php esc_html_e( 'On-sale products only', 'ad-sd-static-connector' ); ?>
					</label>
				</div>
				<!-- Star rating filter -->
				<div class="adsd-filter-field adsd-filter-field--rating" style="margin-top:10px;">
					<label class="adsd-label" for="adsd-filter-min-rating">
						<?php esc_html_e( 'Minimum Star Rating', 'ad-sd-static-connector' ); ?>
					</label>
					<select id="adsd-filter-min-rating" class="adsd-select">
						<option value="0"><?php esc_html_e( 'â Any Rating â', 'ad-sd-static-connector' ); ?></option>
						<option value="1">&#9733; <?php esc_html_e( '1 star &amp; above', 'ad-sd-static-connector' ); ?></option>
						<option value="2">&#9733;&#9733; <?php esc_html_e( '2 stars &amp; above', 'ad-sd-static-connector' ); ?></option>
						<option value="3">&#9733;&#9733;&#9733; <?php esc_html_e( '3 stars &amp; above', 'ad-sd-static-connector' ); ?></option>
						<option value="4">&#9733;&#9733;&#9733;&#9733; <?php esc_html_e( '4 stars &amp; above', 'ad-sd-static-connector' ); ?></option>
						<option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; <?php esc_html_e( '5 stars only', 'ad-sd-static-connector' ); ?></option>
					</select>
				</div>
			</div>

			<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--lg" id="adsd-gen-filter-btn">
				<span class="dashicons dashicons-editor-code"></span>
				<?php esc_html_e( 'Generate Filter Code', 'ad-sd-static-connector' ); ?>
			</button>
		</div>

		<!-- Filter Code Output -->
		<div class="adsd-code-output" id="adsd-filter-output" style="display:none;">
			<div class="adsd-code-output-header">
				<span class="dashicons dashicons-editor-code"></span>
				<?php esc_html_e( 'Generated Filter Block', 'ad-sd-static-connector' ); ?>
				<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm adsd-copy-btn" data-target="adsd-filter-code-block">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy Code', 'ad-sd-static-connector' ); ?>
				</button>
			</div>
			<pre class="adsd-code-block" id="adsd-filter-code-block"></pre>
		</div>
	</div>

	<!-- ── SECTION 3: Layout Templates ── -->
	<div class="adsd-section">
		<div class="adsd-section-header">
			<h2><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Layout Templates', 'ad-sd-static-connector' ); ?></h2>
			<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--sm" id="adsd-new-layout-btn">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'Create Custom Layout', 'ad-sd-static-connector' ); ?>
			</button>
		</div>
		<p class="adsd-section-desc">
			<?php esc_html_e( 'Choose from pre-built layouts or create your own. Use placeholders like {{product_name}}, {{product_price}}, {{post_title}} in your template — they will be replaced with real data.', 'ad-sd-static-connector' ); ?>
		</p>

		<!-- Placeholders reference -->
		<div class="adsd-placeholder-ref">
			<div class="adsd-placeholder-title">
				<span class="dashicons dashicons-editor-code"></span>
				<?php esc_html_e( 'Available Placeholders', 'ad-sd-static-connector' ); ?>
			</div>
			<div class="adsd-placeholder-grid">
				<?php
				$adsd_placeholders = array(
					'{{post_title}}'         => __( 'Post / Product title', 'ad-sd-static-connector' ),
					'{{post_excerpt}}'       => __( 'Post excerpt', 'ad-sd-static-connector' ),
					'{{post_url}}'           => __( 'Post permalink', 'ad-sd-static-connector' ),
					'{{post_thumbnail}}'     => __( 'Featured image URL', 'ad-sd-static-connector' ),
					'{{post_category}}'      => __( 'Category name', 'ad-sd-static-connector' ),
					'{{product_name}}'       => __( 'Product name', 'ad-sd-static-connector' ),
					'{{product_price}}'      => __( 'Product price (WooCommerce)', 'ad-sd-static-connector' ),
					'{{product_image}}'      => __( 'Product image URL', 'ad-sd-static-connector' ),
					'{{product_url}}'        => __( 'Product page URL', 'ad-sd-static-connector' ),
					'{{product_rating}}'     => __( 'Star rating (WooCommerce)', 'ad-sd-static-connector' ),
					'{{product_short_desc}}' => __( 'Short description', 'ad-sd-static-connector' ),
					'{{product_sku}}'        => __( 'Product SKU', 'ad-sd-static-connector' ),
				);
				foreach ( $adsd_placeholders as $tag => $adsd_desc ) :
				?>
				<div class="adsd-placeholder-item">
					<code class="adsd-placeholder-tag"><?php echo esc_html( $tag ); ?></code>
					<span class="adsd-placeholder-desc"><?php echo esc_html( $adsd_desc ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Layout cards grid -->
		<div class="adsd-layout-grid" id="adsd-layout-grid">
			<div class="adsd-spinner-wrap"><span class="adsd-spinner"></span></div>
		</div>
	</div>

</div>

<!-- Layout Editor Modal -->
<div class="adsd-modal" id="adsd-layout-modal" style="display:none;">
	<div class="adsd-modal-overlay"></div>
	<div class="adsd-modal-box adsd-modal-box--lg">
		<div class="adsd-modal-header">
			<h3><span class="dashicons dashicons-layout"></span> <span id="adsd-layout-modal-title"><?php esc_html_e( 'Create Custom Layout', 'ad-sd-static-connector' ); ?></span></h3>
			<button type="button" class="adsd-modal-close">&times;</button>
		</div>
		<div class="adsd-modal-body">
			<input type="hidden" id="adsd-layout-id" value="">

			<div class="adsd-layout-form-grid">
				<div class="adsd-layout-form-left">
					<div class="adsd-field-group">
						<label class="adsd-label"><?php esc_html_e( 'Layout Name', 'ad-sd-static-connector' ); ?></label>
						<input type="text" id="adsd-layout-name" class="adsd-input"
							placeholder="<?php esc_attr_e( 'e.g. My Product Cards', 'ad-sd-static-connector' ); ?>">
					</div>
					<div class="adsd-field-group">
						<label class="adsd-label"><?php esc_html_e( 'Plugin / Post Type', 'ad-sd-static-connector' ); ?></label>
						<select id="adsd-layout-plugin" class="adsd-select">
							<option value="generic"><?php esc_html_e( 'Generic (Posts/Pages)', 'ad-sd-static-connector' ); ?></option>
							<option value="woocommerce"><?php esc_html_e( 'WooCommerce (Products)', 'ad-sd-static-connector' ); ?></option>
						</select>
					</div>
					<div class="adsd-field-group">
						<label class="adsd-label"><?php esc_html_e( 'HTML Template', 'ad-sd-static-connector' ); ?></label>

						<!-- Small editor toolbar — scoped to this modal only (adsd-lyt-* ids), does not touch the File Manager editor. -->
						<div class="adsd-lyt-edt-toolbar">
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-find">
								<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Find & Replace', 'ad-sd-static-connector' ); ?>
							</button>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-goto">
								<span class="dashicons dashicons-editor-ol"></span> <?php esc_html_e( 'Go to Line', 'ad-sd-static-connector' ); ?>
							</button>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-beautify">
								<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Beautify', 'ad-sd-static-connector' ); ?>
							</button>
						</div>
						<div class="adsd-edt-findbar" id="adsd-lyt-edt-findbar" style="display:none;">
							<input type="text" id="adsd-lyt-edt-find-input" placeholder="<?php esc_attr_e( 'Find…', 'ad-sd-static-connector' ); ?>">
							<input type="text" id="adsd-lyt-edt-replace-input" placeholder="<?php esc_attr_e( 'Replace with…', 'ad-sd-static-connector' ); ?>">
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-find-next"><?php esc_html_e( 'Find Next', 'ad-sd-static-connector' ); ?></button>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-replace-one"><?php esc_html_e( 'Replace', 'ad-sd-static-connector' ); ?></button>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-replace-all"><?php esc_html_e( 'Replace All', 'ad-sd-static-connector' ); ?></button>
							<span class="adsd-edt-findbar-result" id="adsd-lyt-edt-find-result"></span>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-find-close"><?php esc_html_e( 'Close', 'ad-sd-static-connector' ); ?></button>
						</div>
						<div class="adsd-edt-gotobar" id="adsd-lyt-edt-gotobar" style="display:none;">
							<label for="adsd-lyt-edt-goto-input"><?php esc_html_e( 'Line number:', 'ad-sd-static-connector' ); ?></label>
							<input type="number" id="adsd-lyt-edt-goto-input" min="1" step="1">
							<button type="button" class="adsd-btn adsd-btn--primary adsd-btn--xs" id="adsd-lyt-edt-goto-go"><?php esc_html_e( 'Go', 'ad-sd-static-connector' ); ?></button>
							<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--xs" id="adsd-lyt-edt-goto-close"><?php esc_html_e( 'Close', 'ad-sd-static-connector' ); ?></button>
						</div>

						<textarea id="adsd-layout-template" class="adsd-textarea adsd-textarea--code" rows="14"
							placeholder="<?php esc_attr_e( '<div class="card">\n  <img src="{{product_image}}" alt="{{product_name}}">\n  <h3>{{product_name}}</h3>\n  <span>{{product_price}}</span>\n</div>', 'ad-sd-static-connector' ); ?>" style="display:none;"></textarea>
						<div id="adsd-layout-editor-wrap" style="height:300px;border:1px solid var(--adsd-gray-300);border-radius:var(--adsd-radius-sm);overflow:hidden;"></div>
					</div>

					<div class="adsd-field-group">
						<label class="adsd-label"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Live Preview', 'ad-sd-static-connector' ); ?></label>
						<div class="adsd-lyt-live-preview">
							<iframe id="adsd-layout-live-preview-frame" src="about:blank" sandbox="allow-scripts" title="<?php esc_attr_e( 'Layout live preview', 'ad-sd-static-connector' ); ?>"></iframe>
						</div>
					</div>
				</div>

				<div class="adsd-layout-form-right">
					<div class="adsd-layout-suggestions">
						<div class="adsd-suggestions-title">
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e( 'Placeholder Suggestions', 'ad-sd-static-connector' ); ?>
						</div>
						<p class="adsd-suggestions-hint"><?php esc_html_e( 'Click to insert into template:', 'ad-sd-static-connector' ); ?></p>
						<div class="adsd-suggestions-list">
							<?php foreach ( array_keys( $adsd_placeholders ) as $tag ) : ?>
							<button type="button" class="adsd-suggestion-chip" data-placeholder="<?php echo esc_attr( $tag ); ?>">
								<?php echo esc_html( $tag ); ?>
							</button>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="adsd-modal-footer">
				<button type="button" class="adsd-btn adsd-btn--primary" id="adsd-layout-save">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Layout', 'ad-sd-static-connector' ); ?>
				</button>
				<button type="button" class="adsd-btn adsd-btn--ghost adsd-modal-close">
					<?php esc_html_e( 'Cancel', 'ad-sd-static-connector' ); ?>
				</button>
			</div>
			<div id="adsd-layout-msg" class="adsd-editor-msg"></div>
		</div>
	</div>
</div>

<!-- Layout Preview Modal -->
<div class="adsd-modal" id="adsd-layout-preview-modal" style="display:none;">
	<div class="adsd-modal-overlay"></div>
	<div class="adsd-modal-box adsd-modal-box--lg">
		<div class="adsd-modal-header">
			<h3><span class="dashicons dashicons-visibility"></span> <span id="adsd-layout-preview-modal-title"><?php esc_html_e( 'Layout Preview', 'ad-sd-static-connector' ); ?></span></h3>
			<button type="button" class="adsd-modal-close">&times;</button>
		</div>
		<div class="adsd-modal-body" style="padding:0;height:500px;">
			<iframe id="adsd-layout-preview-frame" src="about:blank" style="width:100%;height:100%;border:none;" sandbox="allow-scripts"></iframe>
		</div>
	</div>
</div>

<?php // ── SECTION 4: Generated Code History ── ?>
<div class="adsd-section" id="adsd-code-history-section">
	<div class="adsd-section-header">
		<h2><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Generated Code History', 'ad-sd-static-connector' ); ?></h2>
		<button type="button" class="adsd-btn adsd-btn--ghost adsd-btn--sm" id="adsd-clear-history-btn" title="<?php esc_attr_e( 'Clear all saved codes', 'ad-sd-static-connector' ); ?>">
			<span class="dashicons dashicons-trash"></span>
			<?php esc_html_e( 'Clear History', 'ad-sd-static-connector' ); ?>
		</button>
	</div>
	<p class="adsd-section-desc">
		<?php esc_html_e( 'All previously generated shortcode and filter codes are saved here. Click "Use Again" to reload the code without regenerating.', 'ad-sd-static-connector' ); ?>
	</p>
	<div class="adsd-history-list" id="adsd-history-list">
		<p class="adsd-history-empty" id="adsd-history-empty"><?php esc_html_e( 'No generated codes yet. Generate a shortcode or filter block above to see it here.', 'ad-sd-static-connector' ); ?></p>
	</div>
</div>

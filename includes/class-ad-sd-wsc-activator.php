<?php
/**
 * Fired during plugin activation.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Activator
 *
 * Creates required DB tables and upload directories on activation.
 */
class AD_SD_WSC_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::create_upload_dirs();
		self::set_default_options();
		// Set flag — adsd_maybe_flush_rules() will register rules and flush
		// on the next 'init' hook (after $wp_rewrite is fully ready).
		// This avoids the "Call to member function add_rule() on null" fatal error.
		update_option( 'adsd_needs_flush', 1 );
	}

	/**
	 * Register all plugin rewrite rules.
	 * Called on activation AND on init so rules are always present.
	 *
	 * @return void
	 */
	public static function register_rewrite_rules() {
		add_rewrite_rule( '^adsd-sc/?$', 'index.php?adsd_sc=1', 'top' );
		add_rewrite_rule( '^adsd-loader\.js$', 'index.php?adsd_loader=1', 'top' );
		add_rewrite_rule( '^adsd-render/filter/?$', 'index.php?adsd_render=filter', 'top' );
		add_rewrite_rule( '^adsd-render/?$', 'index.php?adsd_render=shortcode', 'top' );
		add_rewrite_rule( '^adsd-static/(.+)$', 'index.php?adsd_static_file=$matches[1]', 'top' );
		add_rewrite_rule( '^adsd-page/?$', 'index.php?adsd_page=1', 'top' );
	}

	/**
	 * Ensure DB tables exist — safe to call on every request.
	 * Only runs dbDelta if the stored version doesn't match current version.
	 * This handles: fresh installs where activation hook failed, plugin updates.
	 *
	 * @return void
	 */
	public static function maybe_create_tables() {
		$stored = get_option( 'adsd_db_version', '0' );
		if ( version_compare( $stored, AD_SD_WSC_VERSION, '<' ) ) {
			self::create_tables();
			self::create_upload_dirs();
		}
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Table: uploaded zip files.
		$table_zips = $wpdb->prefix . 'adsd_zip_files';
		$sql_zips   = "CREATE TABLE IF NOT EXISTS {$table_zips} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			file_name   VARCHAR(255)        NOT NULL,
			zip_path    TEXT                NOT NULL,
			extract_path TEXT               NOT NULL,
			file_size   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status      VARCHAR(20)         NOT NULL DEFAULT 'inactive',
			uploaded_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			uploaded_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		// Table: SEO settings per static file.
		$table_seo = $wpdb->prefix . 'adsd_seo_settings';
		$sql_seo   = "CREATE TABLE IF NOT EXISTS {$table_seo} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zip_id       BIGINT(20) UNSIGNED NOT NULL,
			file_path    TEXT                NOT NULL,
			seo_title    VARCHAR(255)        NOT NULL DEFAULT '',
			meta_desc    TEXT                NOT NULL,
			meta_keywords TEXT               NOT NULL,
			og_title     VARCHAR(255)        NOT NULL DEFAULT '',
			og_desc      TEXT                NOT NULL,
			og_image     TEXT                NOT NULL,
			canonical    TEXT                NOT NULL,
			robots       VARCHAR(100)        NOT NULL DEFAULT 'index, follow',
			schema_type  VARCHAR(50)         NOT NULL DEFAULT 'WebPage',
			seo_score    TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			updated_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY zip_id (zip_id)
		) {$charset_collate};";

		// Table: mapping (live deployment config).
		$table_mapping = $wpdb->prefix . 'adsd_mapping';
		$sql_mapping   = "CREATE TABLE IF NOT EXISTS {$table_mapping} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zip_id       BIGINT(20) UNSIGNED NOT NULL,
			home_file    VARCHAR(255)        NOT NULL DEFAULT '',
			is_live      TINYINT(1)          NOT NULL DEFAULT 0,
			activated_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			activated_at DATETIME            DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY zip_id (zip_id)
		) {$charset_collate};";

		// Table: custom layout templates.
		$table_layouts = $wpdb->prefix . 'adsd_layouts';
		$sql_layouts   = "CREATE TABLE IF NOT EXISTS {$table_layouts} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			layout_name VARCHAR(255)        NOT NULL,
			plugin_type VARCHAR(100)        NOT NULL DEFAULT 'generic',
			template    LONGTEXT            NOT NULL,
			layout_type VARCHAR(20)         NOT NULL DEFAULT 'custom',
			created_by  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset_collate};";

		// Table: activity logs.
		$table_logs = $wpdb->prefix . 'adsd_activity_logs';
		$sql_logs   = "CREATE TABLE IF NOT EXISTS {$table_logs} (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action     VARCHAR(100)        NOT NULL,
			object_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			details    TEXT                NOT NULL,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_zips );
		dbDelta( $sql_seo );
		dbDelta( $sql_mapping );
		dbDelta( $sql_layouts );
		dbDelta( $sql_logs );

		update_option( 'adsd_db_version', AD_SD_WSC_VERSION );

		// Insert pre-built layouts.
		self::insert_default_layouts();
	}

	/**
	 * Insert default pre-built layouts into the DB.
	 *
	 * @return void
	 */
	/**
	 * Returns all preset layout definitions.
	 * Called on activation (insert) and by reset AJAX handler (update).
	 *
	 * @return array
	 */
	public static function get_preset_layouts() {
		return array(
			array(
				'layout_name' => 'Minimal Shop Card',
				'plugin_type' => 'woocommerce',
				'layout_type' => 'preset',
				'created_by'  => 0,
				'template'    => '<a href="{{product_url}}" style="text-decoration:none;display:block;" class="adsd-minimal-card">
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.06);" onmouseover="this.style.transform=\'translateY(-3px)\'" onmouseout="this.style.transform=\'\'">
  <div style="background:#f0f0f0;height:220px;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;">
    <img src="{{product_image}}" alt="{{product_name}}" style="max-height:180px;max-width:80%;object-fit:contain;display:block;margin:auto;">
  </div>
  <div style="padding:12px 14px 14px;">
    <div style="font-size:13px;color:#1a1a1a;font-weight:400;margin-bottom:4px;">{{product_name}}</div>
    <div style="font-size:13px;color:#1a1a1a;font-weight:600;">{{product_price}}</div>
  </div>
</div>
</a>',
			),
			array(
				'layout_name' => 'Grocery Product Card',
				'plugin_type' => 'woocommerce',
				'layout_type' => 'preset',
				'created_by'  => 0,
				'template'    => '<div class="adsd-grocery-card" style="background:#fff;border:1px solid #ebebeb;border-radius:10px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;text-align:center;">
  <a href="{{product_url}}" style="display:block;text-decoration:none;">
    <div style="height:160px;background:#fafafa;display:flex;align-items:center;justify-content:center;padding:16px;">
      <img src="{{product_image}}" alt="{{product_name}}" style="max-height:130px;max-width:90%;object-fit:contain;display:block;margin:auto;">
    </div>
  </a>
  <div style="padding:12px 14px 16px;">
    <div style="display:inline-block;background:#f2f2f2;color:#555;font-size:11px;font-weight:500;padding:3px 10px;border-radius:20px;margin-bottom:8px;">{{post_category}}</div>
    <a href="{{product_url}}" style="text-decoration:none;"><div style="font-size:14px;font-weight:600;color:#1a1a1a;margin-bottom:4px;line-height:1.4;">{{product_name}}</div></a>
    <div style="font-size:12px;color:#888;margin-bottom:10px;">{{product_short_desc}}</div>
    <div style="font-size:18px;font-weight:700;color:#e53935;margin-bottom:12px;">{{product_price}}</div>
    <a href="{{product_url}}" style="display:block;background:#2e7d32;color:#fff;padding:10px 0;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">&#128722; View Product</a>
  </div>
</div>',
			),
			array(
				'layout_name' => 'Product Grid Card',
				'plugin_type' => 'woocommerce',
				'layout_type' => 'preset',
				'created_by'  => 0,
				'template'    => '<div class="adsd-product-card" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);transition:transform .2s,box-shadow .2s;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;" onmouseover="this.style.transform=\'translateY(-4px)\';this.style.boxShadow=\'0 8px 24px rgba(0,0,0,.14)\'" onmouseout="this.style.transform=\'\';this.style.boxShadow=\'0 2px 12px rgba(0,0,0,.08)\'">
  <a href="{{product_url}}" style="text-decoration:none;display:block;">
    <div style="overflow:hidden;height:200px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;">
      <img src="{{product_image}}" alt="{{product_name}}" style="max-width:100%;max-height:180px;object-fit:contain;display:block;transition:transform .3s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'\'">
    </div>
  </a>
  <div style="padding:16px;">
    <a href="{{product_url}}" style="text-decoration:none;"><h3 style="margin:0 0 6px;font-size:15px;font-weight:700;color:#1a1a1a;line-height:1.3;">{{product_name}}</h3></a>
    <p style="margin:0 0 12px;font-size:13px;color:#666;line-height:1.5;min-height:40px;">{{product_short_desc}}</p>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span style="font-size:18px;font-weight:800;color:#e63946;">{{product_price}}</span>
      <a href="{{product_url}}" style="background:#4f46e5;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;display:inline-block;">View Product</a>
    </div>
  </div>
</div>',
			),
			array(
				'layout_name' => 'Product List Row',
				'plugin_type' => 'woocommerce',
				'layout_type' => 'preset',
				'created_by'  => 0,
				'template'    => '<a href="{{product_url}}" style="text-decoration:none;display:block;" class="adsd-product-list-row">
<div style="display:flex;gap:16px;align-items:center;background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.07);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;transition:box-shadow .2s;" onmouseover="this.style.boxShadow=\'0 4px 16px rgba(0,0,0,.12)\'" onmouseout="this.style.boxShadow=\'0 1px 6px rgba(0,0,0,.07)\'">
  <div style="width:90px;height:90px;flex-shrink:0;background:#f5f5f5;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
    <img src="{{product_image}}" alt="{{product_name}}" style="max-width:80px;max-height:80px;object-fit:contain;">
  </div>
  <div style="flex:1;min-width:0;">
    <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a1a1a;">{{product_name}}</h3>
    <p style="margin:0 0 6px;font-size:13px;color:#666;line-height:1.5;">{{product_short_desc}}</p>
    <span style="font-size:12px;color:#f59e0b;">&#9733; {{product_rating}}</span>
  </div>
  <div style="text-align:right;flex-shrink:0;">
    <div style="font-size:18px;font-weight:800;color:#e63946;margin-bottom:8px;">{{product_price}}</div>
    <span style="display:inline-block;background:#16a34a;color:#fff;padding:7px 14px;border-radius:6px;font-size:13px;font-weight:600;white-space:nowrap;">View &rarr;</span>
  </div>
</div>
</a>',
			),
			array(
				'layout_name' => 'Post Blog Card',
				'plugin_type' => 'generic',
				'layout_type' => 'preset',
				'created_by'  => 0,
				'template'    => '<div class="adsd-post-card" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;transition:transform .2s;" onmouseover="this.style.transform=\'translateY(-3px)\'" onmouseout="this.style.transform=\'\'">
  <a href="{{post_url}}" style="display:block;text-decoration:none;">
    <div style="height:180px;overflow:hidden;background:#f5f5f5;">
      <img src="{{post_thumbnail}}" alt="{{post_title}}" style="width:100%;height:100%;object-fit:cover;display:block;">
    </div>
  </a>
  <div style="padding:16px;">
    <span style="display:inline-block;background:#eef2ff;color:#4f46e5;font-size:11px;font-weight:700;padding:3px 8px;border-radius:4px;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">{{post_category}}</span>
    <a href="{{post_url}}" style="text-decoration:none;"><h3 style="margin:0 0 8px;font-size:16px;font-weight:700;color:#1a1a1a;line-height:1.4;">{{post_title}}</h3></a>
    <p style="margin:0 0 14px;font-size:13px;color:#555;line-height:1.6;">{{post_excerpt}}</p>
    <a href="{{post_url}}" style="color:#4f46e5;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">Read More &rarr;</a>
  </div>
</div>',
			),
			array(
				'layout_name' => 'Product Deal Card',
				'plugin_type' => 'woocommerce',
				'layout_type' => 'preset',
				'created_by'  => 0,
				'template'    => '<div class="adsd-deal-card" style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:14px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;color:#fff;position:relative;">
  <div style="position:absolute;top:12px;left:12px;background:#e63946;color:#fff;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;z-index:1;text-transform:uppercase;letter-spacing:.5px;">SALE</div>
  <a href="{{product_url}}" style="display:block;text-decoration:none;">
    <div style="height:180px;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;overflow:hidden;">
      <img src="{{product_image}}" alt="{{product_name}}" style="max-height:160px;max-width:90%;object-fit:contain;display:block;">
    </div>
  </a>
  <div style="padding:16px;">
    <a href="{{product_url}}" style="text-decoration:none;"><h3 style="margin:0 0 6px;font-size:15px;font-weight:700;color:#fff;">{{product_name}}</h3></a>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
      <span style="font-size:22px;font-weight:900;color:#ffd700;">{{product_price}}</span>
      <span style="font-size:12px;color:#aaa;">SKU: {{product_sku}}</span>
    </div>
    <a href="{{product_url}}" style="display:block;text-align:center;background:#e63946;color:#fff;padding:10px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:700;">Shop Now</a>
  </div>
</div>',
			),
		);
	}

	private static function insert_default_layouts() {
		global $wpdb;
		$table   = esc_sql( $wpdb->prefix . 'adsd_layouts' );
		$exists  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( $exists > 0 ) {
			// Update existing preset templates to latest version.
			foreach ( self::get_preset_layouts() as $preset ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array( 'template' => $preset['template'] ),
					array( 'layout_name' => $preset['layout_name'], 'layout_type' => 'preset' ),
					array( '%s' ),
					array( '%s', '%s' )
				);
			}
			return;
		}

		foreach ( self::get_preset_layouts() as $layout ) {
			$wpdb->insert( $table, $layout ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Reset a single preset layout to its original template.
	 * Called by AJAX reset handler.
	 *
	 * @param string $layout_name Layout name to reset.
	 * @return bool
	 */
	public static function reset_preset_layout( $layout_name ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'adsd_layouts';
		$presets  = self::get_preset_layouts();
		foreach ( $presets as $preset ) {
			if ( $preset['layout_name'] === $layout_name ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array( 'template' => $preset['template'] ),
					array( 'layout_name' => $layout_name, 'layout_type' => 'preset' ),
					array( '%s' ),
					array( '%s', '%s' )
				);
				return true;
			}
		}
		return false;
	}


	/**
	 * Create upload directories with security files.
	 *
	 * @return void
	 */
	private static function create_upload_dirs() {
		$upload_base = wp_upload_dir()['basedir'] . '/ad-sd-wsc';
		$dirs        = array(
			$upload_base,
			$upload_base . '/zips',
			$upload_base . '/extracted',
			$upload_base . '/temp',
			$upload_base . '/backups',
		);

		// Hardcoded security file contents — no user input, safe to write directly.
		$htaccess_content = "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Order allow,deny\n  Deny from all\n</FilesMatch>\n";
		$index_content    = '<?php // Silence is golden.';

		foreach ( $dirs as $dir ) {
			wp_mkdir_p( $dir );

			$htaccess = $dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $htaccess, $htaccess_content );
			}

			$index = $dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $index, $index_content );
			}
		}
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			'max_zip_size_mb'    => 50,
			'allowed_file_types' => array( 'html', 'htm', 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'eot', 'ico', 'json', 'xml', 'txt' ),
			'manager_can_layout' => true,
			'live_zip_id'        => 0,
			'live_home_file'     => '',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( 'adsd_' . $key ) ) {
				update_option( 'adsd_' . $key, $value );
			}
		}
	}
}

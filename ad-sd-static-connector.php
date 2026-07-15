<?php
/**
 * Plugin Name:       AD-SD Static Connector
 * Plugin URI:        https://wordpress.org/plugins/ad-sd-static-connector
 * Description:       Upload, manage, and serve static HTML/CSS/JS websites directly from WordPress with shortcode bridging, SEO management, and live deployment.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            devadarsh
 * Author URI:        https://profiles.wordpress.org/devadarsh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ad-sd-static-connector
 * Domain Path:       /languages
 *
 * @package AD_SD_WSC
 */

	exit;
}

// =============================================================================
// WP FILESYSTEM HELPER — Safe on frontend AND admin, any server environment.
// WP_Filesystem() is only auto-available in admin. On frontend (router, template)
// we must require the file manually first.
// =============================================================================

/**
 * Safely initialize and return WP_Filesystem instance.
 * Works on frontend, admin, CLI, and any server environment.
 *
 * @return WP_Filesystem_Base|null
 */
function adsd_get_filesystem() {
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( empty( $wp_filesystem ) ) {
		WP_Filesystem();
	}
	return $wp_filesystem;
}

/**
 * Read a file safely using WP_Filesystem with fallback to file_get_contents.
 *
 * @param string $path Absolute file path.
 * @return string|false File contents or false on failure.
 */
function adsd_file_get_contents( $path ) {
	$fs = adsd_get_filesystem();
	if ( $fs ) {
		return $fs->get_contents( $path );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	return file_get_contents( $path );
}

/**
 * Write a file safely using WP_Filesystem with fallback to file_put_contents.
 *
 * @param string $path    Absolute file path.
 * @param string $content Content to write.
 * @return bool True on success.
 */
function adsd_file_put_contents( $path, $content ) {
	$fs = adsd_get_filesystem();
	if ( $fs ) {
		return $fs->put_contents( $path, $content, FS_CHMOD_FILE );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	return false !== file_put_contents( $path, $content );
}

/**
 * Remove a directory safely using WP_Filesystem with fallback to rmdir.
 *
 * @param string $path Absolute directory path.
 * @return bool True on success.
 */
function adsd_rmdir( $path ) {
	$fs = adsd_get_filesystem();
	if ( $fs ) {
		return $fs->rmdir( $path, false );
	}
	return @rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}



define( 'AD_SD_WSC_VERSION', '1.0.0' );
define( 'AD_SD_WSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AD_SD_WSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AD_SD_WSC_PLUGIN_FILE', __FILE__ );
define( 'AD_SD_WSC_MIN_PHP', '7.4' );
define( 'AD_SD_WSC_MIN_WP', '5.8' );

if ( version_compare( PHP_VERSION, AD_SD_WSC_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( 'AD-SD Static Connector requires PHP %s or higher.', 'ad-sd-static-connector' ),
						AD_SD_WSC_MIN_PHP
					)
				) .
				'</p></div>';
		}
	);
	return;
}

require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-activator.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-deactivator.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-helpers.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-zip-handler.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-file-manager.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-seo-manager.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-shortcode-bridge.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-mapping.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-ajax.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'includes/class-ad-sd-wsc-router.php';
require_once AD_SD_WSC_PLUGIN_DIR . 'admin/class-ad-sd-wsc-admin.php';

register_activation_hook( __FILE__, array( 'AD_SD_WSC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AD_SD_WSC_Deactivator', 'deactivate' ) );

function ad_sd_wsc_init() {
	// Ensure DB tables and upload dirs exist on every load (safe, version-checked).
	AD_SD_WSC_Activator::maybe_create_tables();

	if ( is_admin() ) {
		new AD_SD_WSC_Admin();
	}
	new AD_SD_WSC_Ajax();
	new AD_SD_WSC_Router();
}
add_action( 'plugins_loaded', 'ad_sd_wsc_init' );

/**
 * Auto-flush rewrite rules on init (after $wp_rewrite is ready).
 * Runs once after activation — no manual Permalink save needed.
 */
function adsd_maybe_flush_rules() {
	if ( get_option( 'adsd_needs_flush' ) ) {
		AD_SD_WSC_Activator::register_rewrite_rules();
		flush_rewrite_rules();
		delete_option( 'adsd_needs_flush' );
	}
}
add_action( 'init', 'adsd_maybe_flush_rules', 99 );

/**
 * Auto-inject adsd-loader.js in WordPress footer.
 * This runs on every frontend page so static HTML files served by this plugin
 * can include [data-adsd-sc] divs that get populated automatically.
 */
function adsd_inject_loader() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_script(
		'adsd-loader',
		plugins_url( 'assets/js/adsd-loader.js', __FILE__ ),
		array(),
		AD_SD_WSC_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'adsd_inject_loader' );

/**
 * Flush all adsd_sc_* transient caches when any post/product is saved or updated.
 * This ensures the frontend always shows the latest content without manual action.
 */
function adsd_flush_sc_cache() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adsd_sc_%' OR option_name LIKE '_transient_timeout_adsd_sc_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
add_action( 'save_post', 'adsd_flush_sc_cache' );
add_action( 'woocommerce_update_product', 'adsd_flush_sc_cache' );
add_action( 'woocommerce_new_product', 'adsd_flush_sc_cache' );
add_action( 'created_term', 'adsd_flush_sc_cache' );
add_action( 'edited_term', 'adsd_flush_sc_cache' );


// =============================================================================
// ADSD POST TEMPLATE — Classical single-post layout.
// Intercepts single post pages when enabled and renders custom template.
// =============================================================================

/**
 * Load ADSD Post Template for single posts when enabled.
 */
function adsd_load_post_template( $template ) {
	if ( ! is_single() || get_post_type() !== 'post' ) {
		return $template;
	}
	if ( ! get_option( 'adsd_post_template_enabled', 0 ) ) {
		return $template;
	}
	$adsd_tpl = plugin_dir_path( AD_SD_WSC_PLUGIN_FILE ) . 'templates/adsd-post-template.php';
	if ( ! file_exists( $adsd_tpl ) ) {
		return $template;
	}

	// ── Suppress theme output immediately (template_include fires after 'wp' hook) ──
	// Admin bar adds site name text above page — disable it on our standalone template.
	add_filter( 'show_admin_bar', '__return_false' );
	// Suppress get_header / get_footer calls.
	add_filter( 'get_header', '__return_false' );
	add_filter( 'get_footer', '__return_false' );
	// Head cleanup hooks (fires before wp_head output).
	add_action( 'wp_head', 'adsd_suppress_theme_head_output', 0 );
	// ── Remove wp_footer hooks that would double-output injected content ──
	// These must be removed HERE (not via 'wp' hook which has already fired).
	remove_action( 'wp_footer', 'adsd_injector_wp_footer', 98 );
	remove_action( 'wp_footer', 'adsd_injector_container_close', 97 );
	remove_action( 'wp_footer', 'adsd_injector_container_open', 2 );
	// ── Schedule body hook removal for wp_body_open (fires later) ──
	add_action( 'template_redirect', 'adsd_remove_theme_body_hooks', 0 );

	return $adsd_tpl;
}
add_filter( 'template_include', 'adsd_load_post_template', 99 );

/**
 * Remove theme body hooks — called via template_redirect (before page output).
 */
function adsd_remove_theme_body_hooks() {
	remove_all_actions( 'wp_body_open' );
	remove_all_actions( 'storefront_header' );
	remove_all_actions( 'astra_header' );
	remove_all_actions( 'genesis_header' );
	remove_all_actions( 'elementor/page_templates/header-footer/before_content' );
	remove_all_actions( 'before_header' );
	remove_all_actions( 'after_header' );
}

/**
 * Capture and clean wp_head output — strip any theme-injected <header>/<nav>/<footer>
 * tags that themes might output via wp_head hooks or inline styles.
 */
function adsd_suppress_theme_head_output() {
	// Suppress get_header / get_footer calls entirely.
	add_filter( 'get_header', '__return_false' );
	add_filter( 'get_footer', '__return_false' );
	// Remove theme-specific hooks that fire during wp_head and add body markup.
	remove_all_actions( 'before_header' );
	remove_all_actions( 'after_header' );
	// Remove wp_head hooks that output site title, admin bar, and theme header markup.
	remove_action( 'wp_head', 'wp_title', 1 );
	// Disable admin bar on our standalone template (it injects "Coffo" site name text).
	add_filter( 'show_admin_bar', '__return_false' );
	// Remove theme header hooks that sometimes fire via wp_head.
	remove_all_actions( 'wp_head_navbar' );
	remove_all_actions( 'theme_header' );
}

// =============================================================================
// WP PAGE INJECTOR — Inject head/header/footer/CSS/JS into WordPress pages.
// These hooks only fire on native WordPress pages.
// They are completely skipped when the static ZIP router has already exited.
// =============================================================================

/**
 * Helper: Is injector enabled?
 */
function adsd_injector_is_enabled() {
	return (bool) get_option( 'adsd_wp_injector_enabled', 0 );
}

/**
 * Helper: Should all theme & plugin headers/footers be disabled?
 * True when the single "Disable Header & Footer for all pages" toggle is on.
 */
function adsd_injector_all_hf_disabled() {
	return (bool) get_option( 'adsd_wp_disable_all_hf', 0 );
}

/**
 * Helper: Is theme header/footer disabled? (kept for backward compat)
 */
function adsd_injector_theme_disabled() {
	return adsd_injector_all_hf_disabled();
}

/**
 * Resolve a relative URL from injected HTML against the live ZIP extract URL.
 * If the URL is already absolute (http/https/data/mailto) it is returned untouched.
 * If it is relative, we prepend the static base URL so files are served via /adsd-static/.
 *
 * @param string $url Raw URL found in injected HTML.
 * @return string
 */
function adsd_injector_resolve_url( $url ) {
	if ( '' === $url ) {
		return $url;
	}
	// Already absolute — leave untouched.
	if ( preg_match( '/^(https?:\/\/|\/\/|data:|mailto:|tel:|javascript:)/i', $url ) || '#' === $url[0] ) {
		return $url;
	}
	// Absolute path starting with / — leave untouched (WP-root-relative).
	if ( '/' === $url[0] ) {
		return $url;
	}
	// Relative path — resolve against the live ZIP's /adsd-static/ base.
	$extract_path = get_option( 'adsd_live_extract_path', '' );
	if ( ! $extract_path ) {
		return $url; // No live ZIP — can't resolve.
	}
	return home_url( '/adsd-static/' ) . ltrim( $url, './' );
}

/**
 * Rewrite relative src/href paths in injected HTML to /adsd-static/ so
 * CSS/JS/images from the uploaded ZIP are served correctly on WP pages.
 *
 * @param string $html Raw HTML with possible relative URLs.
 * @return string
 */
function adsd_injector_fix_urls( $html ) {
	if ( ! get_option( 'adsd_live_extract_path', '' ) ) {
		return $html; // No live ZIP — nothing to rewrite.
	}

	// Rewrite src="..." and href="..." attributes.
	$html = preg_replace_callback(
		'/\b(src|href)\s*=\s*(["\'`])([^"\'`]*)(\2)/i',
		function ( $m ) {
			$new = adsd_injector_resolve_url( $m[3] );
			return $m[1] . '=' . $m[2] . $new . $m[4];
		},
		$html
	);

	// Rewrite url(...) inside inline style attributes / <style> blocks.
	$html = preg_replace_callback(
		'/\burl\(\s*(["\']?)([^"\')\s]+)\1\s*\)/i',
		function ( $m ) {
			if ( 0 === strpos( $m[2], 'data:' ) ) {
				return $m[0];
			}
			$new = adsd_injector_resolve_url( $m[2] );
			return 'url(' . $m[1] . $new . $m[1] . ')';
		},
		$html
	);

	return $html;
}

/**
 * Inject head code + custom CSS into <head>.
 * Script tags from "Script File Code" section are moved to footer (loaded last).
 */
function adsd_injector_wp_head() {
	if ( is_admin() || ! adsd_injector_is_enabled() ) {
		return;
	}

	$head_code  = get_option( 'adsd_wp_head_code', '' );
	$custom_css = get_option( 'adsd_wp_custom_css', '' );

	// Head code — strip <script> tags from here; they go to footer instead.
	if ( $head_code ) {
		$head_clean = $head_code;
		// Remove everything from <body onwards (handles full-page HTML pastes).
		$head_clean = preg_replace( '/<body[\s\S]*/i', '', $head_clean );
		// Strip wrapper tags.
		$head_clean = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $head_clean );
		$head_clean = preg_replace( '/<\/?(?:html|head|body)[^>]*>/i', '', $head_clean );
		// Remove <script> blocks — they go to footer.
		$head_no_scripts = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/is', '', $head_clean );
		$head_no_scripts = preg_replace( '/<script\b[^>]*\/?>/is', '', $head_no_scripts );
		$head_no_scripts = trim( $head_no_scripts );
		if ( $head_no_scripts ) {
			echo "\n<!-- AD-SD WP Injector: Head Code -->\n";
			echo adsd_injector_fix_urls( $head_no_scripts ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	if ( $custom_css ) {
		echo "\n<!-- AD-SD WP Injector: Custom CSS -->\n";
		echo '<style id="adsd-injector-css">' . "\n";
		echo $custom_css . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</style>' . "\n";
	}
}
add_action( 'wp_head', 'adsd_injector_wp_head', 1 );

/**
 * Inject header HTML right after <body> opens (wp_body_open hook).
 */
function adsd_injector_body_header() {
	if ( is_admin() || ! adsd_injector_is_enabled() ) {
		return;
	}
	$header_html = get_option( 'adsd_wp_header_html', '' );
	if ( $header_html ) {
		echo "\n<!-- AD-SD WP Injector: Header HTML -->\n";
		echo adsd_injector_fix_urls( $header_html ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'wp_body_open', 'adsd_injector_body_header', 1 );

/**
 * Inject footer HTML + script file tags + custom JS just before </body>.
 * All JS is loaded here (bottom of page) so CSS/HTML loads first.
 */
function adsd_injector_wp_footer() {
	if ( is_admin() || ! adsd_injector_is_enabled() ) {
		return;
	}

	$footer_html = get_option( 'adsd_wp_footer_html', '' );
	$script_html = get_option( 'adsd_wp_script_html', '' );
	$custom_js   = get_option( 'adsd_wp_custom_js', '' );
	$head_code   = get_option( 'adsd_wp_head_code', '' );

	// Footer HTML first.
	if ( $footer_html ) {
		echo "\n<!-- AD-SD WP Injector: Footer HTML -->\n";
		echo adsd_injector_fix_urls( $footer_html ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Extract <script> tags from head_code and output here (bottom load).
	if ( $head_code ) {
		// Strip body content first (handles full HTML pastes).
		$head_code_clean = preg_replace( '/<body[\s\S]*/i', '', $head_code );
		$head_code       = $head_code_clean;
		preg_match_all( '/<script\b[^>]*>.*?<\/script>/is', $head_code, $script_blocks );
		preg_match_all( '/<script\b[^>]*src=[^>]*\/?>/is', $head_code, $script_tags );
		$head_scripts = array_merge( $script_blocks[0], $script_tags[0] );
		// Deduplicate.
		$head_scripts = array_unique( $head_scripts );
		if ( $head_scripts ) {
			echo "\n<!-- AD-SD WP Injector: Scripts (moved from head for performance) -->\n";
			foreach ( $head_scripts as $scr ) {
				echo adsd_injector_fix_urls( $scr ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	// External script file tags from "Script File Code" section.
	if ( $script_html ) {
		echo "\n<!-- AD-SD WP Injector: Script Files -->\n";
		echo adsd_injector_fix_urls( $script_html ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// Inline custom JS — always last.
	if ( $custom_js ) {
		echo "\n<!-- AD-SD WP Injector: Custom JS -->\n";
		echo '<script id="adsd-injector-js">' . "\n";
		echo $custom_js . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</script>' . "\n";
	}
}
// Priority 98 — just before adsd-loader at 99.
add_action( 'wp_footer', 'adsd_injector_wp_footer', 98 );


/**
 * Inject content container CSS when enabled.
 * Creates a responsive max-width wrapper for WP page content.
 */
function adsd_injector_container_css() {
	if ( is_admin() || ! adsd_injector_is_enabled() ) {
		return;
	}
	if ( ! get_option( 'adsd_container_enabled', 0 ) ) {
		return;
	}

	$desktop    = absint( get_option( 'adsd_container_desktop', 1200 ) );
	$tablet     = absint( get_option( 'adsd_container_tablet', 900 ) );
	$mobile     = absint( get_option( 'adsd_container_mobile', 100 ) );
	$padding    = absint( get_option( 'adsd_container_padding', 16 ) );
	$margin_top = absint( get_option( 'adsd_container_margin_top', 0 ) );

	// Clamp values to safe ranges.
	$desktop    = max( 320, min( 3840, $desktop ) );
	$tablet     = max( 320, min( 1200, $tablet ) );
	$mobile     = max( 50, min( 100, $mobile ) );
	$padding    = max( 0, min( 200, $padding ) );
	$margin_top = max( 0, min( 500, $margin_top ) );

	// All values are absint()-clamped above — intval() here satisfies PHPCS EscapeOutput rule.
	$css  = '<style id="adsd-container-css">' . "\n";
	$css .= '/* AD-SD Static Connector — Content Container */' . "\n";
	$css .= '.adsd-content-container {' . "\n";
	$css .= '  max-width: ' . intval( $desktop ) . 'px;' . "\n";
	$css .= '  width: 100%;' . "\n";
	$css .= '  margin-left: auto;' . "\n";
	$css .= '  margin-right: auto;' . "\n";
	$css .= '  margin-top: ' . intval( $margin_top ) . 'px;' . "\n";
	$css .= '  padding-left: ' . intval( $padding ) . 'px;' . "\n";
	$css .= '  padding-right: ' . intval( $padding ) . 'px;' . "\n";
	$css .= '  box-sizing: border-box;' . "\n";
	$css .= '}' . "\n";
	$css .= '@media (max-width: 1023px) {' . "\n";
	$css .= '  .adsd-content-container { max-width: ' . intval( $tablet ) . 'px; }' . "\n";
	$css .= '}' . "\n";
	$css .= '@media (max-width: 767px) {' . "\n";
	$css .= '  .adsd-content-container { max-width: ' . intval( $mobile ) . '%; padding-left: ' . intval( min( $padding, 16 ) ) . 'px; padding-right: ' . intval( min( $padding, 16 ) ) . 'px; }' . "\n";
	$css .= '}' . "\n";
	$css .= '</style>' . "\n";
	echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS built from intval()-sanitized integers only
}
add_action( 'wp_head', 'adsd_injector_container_css', 5 );

/**
 * Wrap the main WP page content in the container div when enabled.
 * Hooks into wp_body_open (after header) and wp_footer (before footer).
 */
function adsd_injector_container_open() {
	if ( is_admin() || ! adsd_injector_is_enabled() ) {
		return;
	}
	if ( ! get_option( 'adsd_container_enabled', 0 ) ) {
		return;
	}
	echo '<div class="adsd-content-container">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function adsd_injector_container_close() {
	if ( is_admin() || ! adsd_injector_is_enabled() ) {
		return;
	}
	if ( ! get_option( 'adsd_container_enabled', 0 ) ) {
		return;
	}
	echo '</div><!-- .adsd-content-container -->' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
// Open container just after header (priority 2 = after header at priority 1).
add_action( 'wp_body_open', 'adsd_injector_container_open', 2 );
// Close container just before footer (priority 97 = before footer at 98).
add_action( 'wp_footer', 'adsd_injector_container_close', 97 );

/**
 * Disable ALL theme and plugin header/footer when the single combined toggle is on.
 * Handles: theme get_header/get_footer, Elementor Pro, Divi, WooCommerce, Genesis, etc.
 */
function adsd_injector_maybe_disable_all_hf() {
	if ( ! adsd_injector_all_hf_disabled() ) {
		return;
	}

	// ── Theme header/footer: suppress get_header() / get_footer() template parts.
	add_filter( 'get_header', '__return_false' );
	add_filter( 'get_footer', '__return_false' );

	// ── Elementor Pro Header & Footer Builder ──────────────────────────────
	add_filter( 'elementor/theme/get_location', '__return_false' );
	add_filter( 'elementor_pro/locations/header/should_override', '__return_false' );
	add_filter( 'elementor_pro/locations/footer/should_override', '__return_false' );

	// ── Divi Extra (theme builder) header/footer ───────────────────────────
	add_filter( 'et_builder_render_layout', '__return_empty_string' );

	// ── WooCommerce store notices ──────────────────────────────────────────
	if ( function_exists( 'WC' ) ) {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}

	// ── Output buffer: strip ALL <header> and <footer> HTML elements. ──────
	add_action( 'template_redirect', function() {
		if ( is_admin() ) { return; }
		ob_start( 'adsd_injector_strip_all_hf' );
	}, 1 );
}
// Fire after_setup_theme (early, before most plugins) and wp_loaded (plugins ready).
add_action( 'after_setup_theme', 'adsd_injector_maybe_disable_all_hf' );
add_action( 'wp_loaded', 'adsd_injector_maybe_disable_all_hf' );


/**
 * Output buffer callback: strips ALL <header> and <footer> HTML elements from page output.
 * This handles every theme — Twenty*, Hello Elementor, Divi, Astra, OceanWP, etc.
 * Called via ob_start() when "Disable Header & Footer" toggle is ON.
 *
 * @param string $html Full page HTML.
 * @return string
 */
function adsd_injector_strip_all_hf( $html ) {
	// Strip ALL <header ...>...</header> blocks (any attributes, multiline safe).
	$html = preg_replace( '/<header[^>]*>.*?<\/header>/is', '', $html );
	// Strip ALL <footer ...>...</footer> blocks.
	$html = preg_replace( '/<footer[^>]*>.*?<\/footer>/is', '', $html );
	return $html;
}

// Keep old names as aliases so any external code referencing them still works.
function adsd_injector_strip_plugin_hf( $html ) { return adsd_injector_strip_all_hf( $html ); }
function adsd_injector_strip_theme_hf( $html )  { return adsd_injector_strip_all_hf( $html ); }


/**
 * Custom 404 page.
 */
function adsd_injector_404() {
	if ( ! is_404() || ! adsd_injector_is_enabled() ) {
		return;
	}
	$html_404 = get_option( 'adsd_wp_404_html', '' );
	if ( ! $html_404 ) {
		return;
	}
	if ( ! headers_sent() ) {
		status_header( 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
	}
	echo $html_404; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'template_redirect', 'adsd_injector_404', 5 );

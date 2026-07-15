<?php
/**
 * Frontend router: serves static files when a ZIP is live.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Router
 */
class AD_SD_WSC_Router {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_serve_static_early' ), 0 );
		add_action( 'template_redirect', array( $this, 'intercept_request' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Check if current request is admin, AJAX, REST API, or page builder preview.
	 * Returns true if the plugin should NOT intercept this request.
	 *
	 * Covers: Elementor, Divi, Bricks, Beaver Builder, WPBakery, Oxygen,
	 *         Gutenberg full-site editing, WP admin, AJAX, REST API, WP-Cron.
	 *
	 * @param string $uri      Full request URI.
	 * @param string $uri_path URI path only.
	 * @return bool
	 */
	private function is_admin_or_builder_request( $uri, $uri_path ) {
		// WP admin area.
		if ( is_admin() ) {
			return true;
		}

		// wp-admin or wp-login paths.
		if ( false !== strpos( $uri_path, '/wp-admin/' )
			|| false !== strpos( $uri_path, '/wp-login.php' )
		) {
			return true;
		}

		// WP AJAX.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}

		// WP Cron.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		// WP REST API.
		if ( false !== strpos( $uri_path, '/wp-json/' ) ) {
			return true;
		}

		// Parse query string to check for page builder params.
		$qs     = wp_parse_url( $uri, PHP_URL_QUERY );
		$params = array();
		if ( $qs ) {
			parse_str( $qs, $params );
		}

		// Elementor editor & preview.
		if ( isset( $params['elementor-preview'] )
			|| isset( $params['elementor-app-base'] )
			|| isset( $params['action'] ) && 'elementor' === $params['action']
		) {
			return true;
		}

		// Elementor via HTTP referer (AJAX calls from editor).
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( $referer && false !== strpos( $referer, 'elementor-preview' ) ) {
			return true;
		}

		// Divi Builder.
		if ( isset( $params['et_fb'] ) || isset( $params['PageSpeed'] ) ) {
			return true;
		}

		// Bricks Builder.
		if ( isset( $params['bricks'] ) ) {
			return true;
		}

		// Beaver Builder.
		if ( isset( $params['fl_builder'] ) ) {
			return true;
		}

		// WPBakery / Visual Composer.
		if ( isset( $params['vc_action'] ) || isset( $params['vc_editable'] ) ) {
			return true;
		}

		// Oxygen Builder.
		if ( isset( $params['oxygen_iframe'] ) || isset( $params['ct_builder'] ) ) {
			return true;
		}

		// Thrive Architect.
		if ( isset( $params['tve'] ) ) {
			return true;
		}

		// Generic: any ?preview or ?customize_changeset_uuid (Gutenberg/Customizer).
		if ( isset( $params['preview'] ) || isset( $params['customize_changeset_uuid'] ) ) {
			return true;
		}

		// WP Customizer.
		if ( isset( $params['wp_customize'] ) ) {
			return true;
		}

		// wp-cron.php direct access.
		if ( false !== strpos( $uri_path, '/wp-cron.php' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Register rewrite rules.
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		AD_SD_WSC_Activator::register_rewrite_rules();
	}

	/**
	 * Early intercept — runs before WP rewrite rules.
	 *
	 * @return void
	 */
	public function maybe_serve_static_early() {
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uri_path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! $uri_path ) {
			return;
		}

		// Never intercept admin, AJAX, cron, or page builder requests.
		// This prevents conflicts with Elementor, Divi, Bricks, etc.
		if ( $this->is_admin_or_builder_request( $uri, $uri_path ) ) {
			return;
		}

		// Strip subdirectory prefix.
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home_path && '/' !== $home_path && 0 === strpos( $uri_path, $home_path ) ) {
			$uri_path = substr( $uri_path, strlen( $home_path ) - 1 );
		}

		// 0. /adsd-loader.js — serve the loader JS file directly (no WP boot needed).
		if ( '/adsd-loader.js' === $uri_path ) {
			$file = plugin_dir_path( AD_SD_WSC_PLUGIN_FILE ) . 'assets/js/adsd-loader.js';
			if ( file_exists( $file ) ) {
				header( 'Content-Type: application/javascript; charset=utf-8' );
				header( 'Cache-Control: public, max-age=31536000' );
				// JS loader asset is public and must load from same-site static pages.
				header( 'Access-Control-Allow-Origin: ' . esc_url_raw( home_url() ) );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo adsd_file_get_contents( $file );
			}
			exit;
		}

		// 1. /adsd-render/* — shortcode/filter endpoint.
		if ( 0 === strpos( $uri_path, '/adsd-render' ) ) {
			$query_str = wp_parse_url( $uri, PHP_URL_QUERY );
			$params    = array();
			if ( $query_str ) {
				parse_str( $query_str, $params );
			}
			$type = ( false !== strpos( $uri_path, '/adsd-render/filter' ) ) ? 'filter' : 'shortcode';
			foreach ( $params as $k => $v ) {
				if ( ! isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
					$_GET[ $k ] = $v; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
				}
			}
			$this->handle_render_request( $type );
			exit;
		}

		// Rest needs live ZIP.
		if ( ! absint( get_option( 'adsd_live_zip_id', 0 ) ) ) {
			return;
		}

		$extract_path = get_option( 'adsd_live_extract_path', '' );
		if ( ! $extract_path ) {
			return;
		}
		$real_extract = realpath( $extract_path );
		if ( ! $real_extract ) {
			return;
		}

		// 2. /adsd-static/* — explicit static asset request.
		if ( 0 === strpos( $uri_path, '/adsd-static/' ) ) {
			$rel_path = rawurldecode( substr( $uri_path, strlen( '/adsd-static/' ) ) );
			if ( '' !== $rel_path ) {
				$this->serve_file( $rel_path, $real_extract );
				exit;
			}
			return;
		}

		// 3. Clean-URL request — could be HTML page or unrewritten asset.
		// Supports: /about/ → about.html, /about → about.html, /about/index.html (direct)
		$rel_path  = rawurldecode( ltrim( $uri_path, '/' ) );
		if ( '' === $rel_path ) {
			return;
		}

		$safe_path = $this->safe_rel_path( $rel_path );
		$real_file = realpath( $real_extract . '/' . $safe_path );

		// If exact path not found, try clean-URL resolution:
		// 1. Try appending .html  (about/ → about.html, about → about.html)
		// 2. Try as directory with index.html  (about/ → about/index.html)
		if ( ! $real_file || ! is_file( $real_file ) ) {
			$try_path = rtrim( $safe_path, '/' );

			// Try slug.html
			$candidate = realpath( $real_extract . '/' . $try_path . '.html' );
			if ( $candidate && is_file( $candidate ) ) {
				$real_file = $candidate;
			} else {
				// Try slug/index.html
				$candidate2 = realpath( $real_extract . '/' . $try_path . '/index.html' );
				if ( $candidate2 && is_file( $candidate2 ) ) {
					$real_file = $candidate2;
				} else {
					// Try slug.htm
					$candidate3 = realpath( $real_extract . '/' . $try_path . '.htm' );
					if ( $candidate3 && is_file( $candidate3 ) ) {
						$real_file = $candidate3;
					}
				}
			}
		}

		if ( ! $real_file
			|| 0 !== strpos( $real_file, $real_extract . DIRECTORY_SEPARATOR )
			|| ! is_file( $real_file )
		) {
			return;
		}

		if ( $this->is_macos_artifact( $real_file ) ) {
			status_header( 404 );
			exit;
		}

		$this->output_file( $real_file, $real_extract );
		exit;
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Existing vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'adsd_loader';
		$vars[] = 'adsd_sc';
		$vars[] = 'adsd_render';
		$vars[] = 'adsd_static_file';
		$vars[] = 'adsd_page';
		return $vars;
	}

	/**
	 * Fallback intercept via template_redirect.
	 * Runs after WP is fully booted — post types, WooCommerce, all plugins ready.
	 *
	 * @return void
	 */
	public function intercept_request() {
		// Never intercept admin, AJAX, or page builder preview requests.
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uri_path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( $this->is_admin_or_builder_request( $uri, $uri_path ) ) {
			return;
		}

		$sc     = get_query_var( 'adsd_sc' );
		$render = get_query_var( 'adsd_render' );
		$static = get_query_var( 'adsd_static_file' );
		$page   = get_query_var( 'adsd_page' );

		// /adsd-page/ — render the ADSD standalone page template.
		if ( $page ) {
			$this->handle_adsd_page_request();
			exit;
		}

		// /adsd-sc/ — dynamic shortcode endpoint (runs here so WP/WC is fully ready).
		if ( $sc ) {
			$this->handle_shortcode_request();
			exit;
		}

		if ( $render ) {
			$this->handle_render_request( $render );
			exit;
		}

		if ( $static ) {
			$extract_path = get_option( 'adsd_live_extract_path', '' );
			$real_extract = $extract_path ? realpath( $extract_path ) : '';
			if ( $real_extract ) {
				$safe_path = $this->safe_rel_path( rawurldecode( $static ) );
				$real_file = realpath( $real_extract . '/' . $safe_path );
				if ( $real_file
					&& 0 === strpos( $real_file, $real_extract . DIRECTORY_SEPARATOR )
					&& is_file( $real_file )
					&& ! $this->is_macos_artifact( $real_file )
				) {
					$this->output_file( $real_file, $real_extract );
					exit;
				}
			}
			status_header( 404 );
			exit;
		}

		$live_id = absint( get_option( 'adsd_live_zip_id', 0 ) );
		if ( $live_id && is_front_page() ) {
			$home_file    = get_option( 'adsd_live_home_file', '' );
			$extract_path = get_option( 'adsd_live_extract_path', '' );
			if ( $home_file && $extract_path ) {
				$real_extract = realpath( $extract_path );
				$real_file    = realpath( $extract_path . '/' . $home_file );
				if ( $real_extract && $real_file && is_file( $real_file ) ) {
					$this->output_file( $real_file, $real_extract );
					exit;
				}
			}
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// PRIVATE HELPERS
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Sanitize a relative path — prevents traversal, preserves real filenames.
	 *
	 * @param string $rel_path Raw relative path.
	 * @return string
	 */
	private function safe_rel_path( $rel_path ) {
		$path = str_replace( "\0", '', $rel_path );
		$path = str_replace( '\\', '/', $path );
		// Remove ../ traversal.
		$parts = array();
		foreach ( explode( '/', $path ) as $p ) {
			if ( '..' === $p ) {
				array_pop( $parts );
			} elseif ( '' !== $p && '.' !== $p ) {
				$parts[] = $p;
			}
		}
		return implode( '/', $parts );
	}

	/**
	 * Output a file with correct Content-Type.
	 *
	 * @param string $real_file    Absolute resolved file path.
	 * @param string $real_extract Absolute resolved extract root.
	 * @return void
	 */
	private function output_file( $real_file, $real_extract ) {
		$ext = strtolower( pathinfo( $real_file, PATHINFO_EXTENSION ) );
		if ( 'html' === $ext || 'htm' === $ext ) {
			$this->output_html_file( $real_file, $real_extract );
		} elseif ( 'css' === $ext ) {
			// CSS files need URL rewriting too (url(), @import with relative paths).
			$this->output_css_file( $real_file, $real_extract );
		} else {
			$mime  = AD_SD_WSC_Helpers::get_mime_type( $ext );
			$mtime = filemtime( $real_file );
			$etag  = '"' . dechex( $mtime ) . '-' . dechex( filesize( $real_file ) ) . '"';

			header( 'Content-Type: ' . $mime );
			header( 'Content-Length: ' . filesize( $real_file ) );
			header( 'Cache-Control: no-cache' );
			header( 'ETag: ' . $etag );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );

			$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$if_modified   = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ( $if_none_match && $if_none_match === $etag )
				|| ( ! $if_none_match && $if_modified && strtotime( $if_modified ) >= $mtime )
			) {
				status_header( 304 );
				return;
			}

			readfile( $real_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Resolve a single URL found in HTML or CSS to its final routed form.
	 * External URLs are returned untouched. Relative paths are resolved
	 * against $base_dir (the directory of the file being processed, relative
	 * to the extract root).
	 *
	 * @param string $url      Raw URL string.
	 * @param bool   $is_page  True when the URL targets an HTML page.
	 * @param string $base_dir Directory of the current file, relative to extract root (no trailing slash).
	 * @param string $static_base   Absolute URL prefix for static assets.
	 * @param string $site_root     Absolute URL to site root (for HTML page links).
	 * @param string $extract_url_path URL path of the extract dir (for hardcoded wp-content paths).
	 * @return string
	 */
	private function resolve_url( $url, $is_page, $base_dir, $static_base, $site_root, $extract_url_path ) {
		if ( '' === $url ) {
			return $url;
		}

		// External / special URLs — leave untouched.
		if ( $this->is_external_url( $url ) ) {
			return $url;
		}

		// Separate query / fragment suffix.
		$suffix = '';
		$q_pos  = strpos( $url, '?' );
		$h_pos  = strpos( $url, '#' );
		$sep    = false;
		if ( false !== $q_pos && false !== $h_pos ) {
			$sep = min( $q_pos, $h_pos );
		} elseif ( false !== $q_pos ) {
			$sep = $q_pos;
		} elseif ( false !== $h_pos ) {
			$sep = $h_pos;
		}
		if ( false !== $sep ) {
			$suffix = substr( $url, $sep );
			$url    = substr( $url, 0, $sep );
		}
		if ( '' === $url ) {
			return $suffix;
		}

		// Hardcoded extract-dir path → rewrite to /adsd-static/...
		if ( '' !== $extract_url_path && 0 === strpos( $url, $extract_url_path . '/' ) ) {
			$rel = substr( $url, strlen( $extract_url_path ) + 1 );
			return $static_base . ltrim( $rel, '/' ) . $suffix;
		}

		// WP-internal absolute paths — leave untouched.
		if ( $this->is_wp_internal_url( $url, $extract_url_path ) ) {
			return $url . $suffix;
		}

		// Absolute path (starts with /) — treat as root of ZIP.
		if ( '/' === $url[0] ) {
			$resolved = ltrim( $url, '/' );
		} else {
			// Relative path — resolve against the CURRENT FILE'S directory.
			$base  = '' !== $base_dir ? $base_dir . '/' . $url : $url;
			$parts = array();
			foreach ( explode( '/', $base ) as $p ) {
				if ( '..' === $p ) {
					array_pop( $parts );
				} elseif ( '' !== $p && '.' !== $p ) {
					$parts[] = $p;
				}
			}
			$resolved = implode( '/', $parts );
		}

		// HTML pages → clean site URL (strip .html/.htm extension, add trailing slash).
		// e.g. about.html → https://example.com/about/
		$ext = strtolower( pathinfo( $resolved, PATHINFO_EXTENSION ) );
		if ( 'html' === $ext || 'htm' === $ext ) {
			// Remove the .html / .htm extension and add trailing slash for clean URLs.
			$clean_path = preg_replace( '/\.(html|htm)$/i', '', $resolved );
			// index pages → just the directory (trailing slash already in site_root for root).
			if ( 'index' === strtolower( basename( $clean_path ) ) ) {
				$clean_path = dirname( $clean_path );
				if ( '.' === $clean_path ) {
					$clean_path = '';
				}
			}
			$trailing = '' !== $clean_path ? trailingslashit( $clean_path ) : '';
			return $site_root . $trailing . $suffix;
		}
		return $static_base . $resolved . $suffix;
	}

	/**
	 * Serve a file via the /adsd-static/ prefix path.
	 *
	 * @param string $rel_path     Relative path (stripped of /adsd-static/).
	 * @param string $real_extract Absolute extract root.
	 * @return void
	 */
	private function serve_file( $rel_path, $real_extract ) {
		$safe_path = $this->safe_rel_path( $rel_path );
		if ( $this->is_macos_artifact( $safe_path ) ) {
			status_header( 404 );
			return;
		}
		$real_file = realpath( $real_extract . '/' . $safe_path );
		if ( ! $real_file
			|| 0 !== strpos( $real_file, $real_extract . DIRECTORY_SEPARATOR )
			|| ! is_file( $real_file )
		) {
			status_header( 404 );
			return;
		}
		$this->output_file( $real_file, $real_extract );
	}

	/**
	 * Check if a URL is "external" — should not be rewritten at all.
	 * Uses strpos instead of preg_match to avoid delimiter conflicts.
	 *
	 * @param string $url URL to test.
	 * @return bool
	 */
	private function is_external_url( $url ) {
		if ( '' === $url ) {
			return false;
		}
		// Protocol-absolute with double slash: https://example.com
		if ( false !== strpos( $url, '://' ) ) {
			return true;
		}
		// Protocol-absolute with single slash (malformed but browsers accept it): https:/example.com
		// This can appear when html-rewriting strips one slash from a reconstructed URL.
		if ( preg_match( '/^[a-zA-Z][a-zA-Z0-9+\-.]*:\//', $url ) ) {
			return true;
		}
		// Protocol-relative.
		if ( 0 === strpos( $url, '//' ) ) {
			return true;
		}
		// Data URI.
		if ( 0 === strpos( $url, 'data:' ) ) {
			return true;
		}
		// Mail / tel / JS pseudo-protocols.
		if ( 0 === strpos( $url, 'mailto:' ) ) {
			return true;
		}
		if ( 0 === strpos( $url, 'tel:' ) ) {
			return true;
		}
		if ( 0 === strpos( $url, 'javascript:' ) ) {
			return true;
		}
		// Pure fragment.
		if ( 0 === strpos( $url, '#' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if a URL points at a WordPress-internal path that must not be
	 * rewritten (/wp-content/, /wp-includes/, /wp-admin/).
	 * The extract dir lives inside /wp-content/ so we pass its URL path to
	 * allow rewriting paths that point INTO the extract folder.
	 *
	 * @param string $url             URL to test (must start with /).
	 * @param string $extract_url_path URL path of the extract dir (e.g. /wp-content/uploads/ad-sd-wsc/extracted/site_123).
	 * @return bool
	 */
	private function is_wp_internal_url( $url, $extract_url_path ) {
		// If the URL points into the extract folder, it is NOT a WP-internal URL —
		// we want to rewrite those to /adsd-static/.
		if ( '' !== $extract_url_path && 0 === strpos( $url, $extract_url_path . '/' ) ) {
			return false;
		}
		// Generic WP paths.
		if ( 0 === strpos( $url, '/wp-content/' ) ) {
			return true;
		}
		if ( 0 === strpos( $url, '/wp-includes/' ) ) {
			return true;
		}
		if ( 0 === strpos( $url, '/wp-admin/' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Output an HTML file, rewriting all relative asset URLs correctly.
	 * Relative paths in HTML attributes are resolved against the HTML file's
	 * own directory. CSS files get their own rewriting when served separately.
	 *
	 * @param string $real_file    Absolute path to the HTML file.
	 * @param string $real_extract Absolute path to the extract root.
	 * @return void
	 */
	/**
	 * Output a CSS file, rewriting relative asset URLs inside url() and @import.
	 * Paths are resolved relative to the CSS file's own directory — NOT the HTML page.
	 *
	 * @param string $real_file    Absolute path to the CSS file.
	 * @param string $real_extract Absolute path to the extract root.
	 * @return void
	 */
	private function output_css_file( $real_file, $real_extract ) {
		if ( ! file_exists( $real_file ) ) {
			status_header( 404 );
			return;
		}
		$css = adsd_file_get_contents( $real_file );
		$static_base = home_url( '/adsd-static/' );
		$site_root   = home_url( '/' );

		// CSS file's own directory relative to extract root.
		$rel_file_path = ltrim( substr( $real_file, strlen( $real_extract ) ), '/\\' );
		$css_dir       = dirname( $rel_file_path );
		if ( '.' === $css_dir ) {
			$css_dir = '';
		}

		$upload_dir       = wp_upload_dir();
		$extract_url_path = '';
		if ( 0 === strpos( $real_extract, $upload_dir['basedir'] ) ) {
			$extract_url_path = wp_parse_url(
				$upload_dir['baseurl'] . substr( $real_extract, strlen( $upload_dir['basedir'] ) ),
				PHP_URL_PATH
			);
		}

		// Rewrite url(...) — resolve relative to the CSS file's directory.
		$css = preg_replace_callback(
			'/\burl\(\s*(["\']?)([^"\')\s]+)\1\s*\)/i',
			function ( $m ) use ( $css_dir, $static_base, $site_root, $extract_url_path ) {
				$q   = $m[1];
				$url = $m[2];
				if ( 0 === strpos( $url, 'data:' ) ) {
					return $m[0];
				}
				$new = $this->resolve_url( $url, false, $css_dir, $static_base, $site_root, $extract_url_path );
				return 'url(' . $q . $new . $q . ')';
			},
			$css
		);

		// Rewrite @import — resolve relative to CSS file's own directory.
		$css = preg_replace_callback(
			'/@import\s+(["\'])([^"\']+)\1/i',
			function ( $m ) use ( $css_dir, $static_base, $site_root, $extract_url_path ) {
				$q   = $m[1];
				$new = $this->resolve_url( $m[2], false, $css_dir, $static_base, $site_root, $extract_url_path );
				return '@import ' . $q . $new . $q;
			},
			$css
		);

		header( 'Content-Type: text/css; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function output_html_file( $real_file, $real_extract ) {
		if ( ! file_exists( $real_file ) ) {
			status_header( 404 );
			return;
		}
		$html = adsd_file_get_contents( $real_file );
		$static_base = home_url( '/adsd-static/' );
		$site_root   = home_url( '/' );

		// HTML file's own directory relative to extract root.
		$rel_file_path = ltrim( substr( $real_file, strlen( $real_extract ) ), '/\\' );
		$page_dir      = dirname( $rel_file_path );
		if ( '.' === $page_dir ) {
			$page_dir = '';
		}

		$upload_dir       = wp_upload_dir();
		$extract_url_path = '';
		if ( 0 === strpos( $real_extract, $upload_dir['basedir'] ) ) {
			$extract_url_path = wp_parse_url(
				$upload_dir['baseurl'] . substr( $real_extract, strlen( $upload_dir['basedir'] ) ),
				PHP_URL_PATH
			);
		}

		// ── Rewrite 1: href="" and src="" attributes ─────────────────────
		$html = preg_replace_callback(
			'/\b(src|href)\s*=\s*(["\'`])([^"\'`]*)(\2)/i',
			function ( $m ) use ( $page_dir, $static_base, $site_root, $extract_url_path ) {
				$url_val = $m[3];
				$clean   = strtolower( preg_replace( '/[?#].*$/', '', $url_val ) );
				$is_page = ( substr( $clean, -5 ) === '.html' || substr( $clean, -4 ) === '.htm' );
				$new     = $this->resolve_url( $url_val, $is_page, $page_dir, $static_base, $site_root, $extract_url_path );
				return $m[1] . '=' . $m[2] . $new . $m[4];
			},
			$html
		);

		// ── Rewrite 2: CSS url(...) inside <style> blocks / style="" attrs ─
		$html = preg_replace_callback(
			'/\burl\(\s*(["\']?)([^"\')\s]+)\1\s*\)/i',
			function ( $m ) use ( $page_dir, $static_base, $site_root, $extract_url_path ) {
				$q   = $m[1];
				$url = $m[2];
				if ( 0 === strpos( $url, 'data:' ) ) {
					return $m[0];
				}
				$new = $this->resolve_url( $url, false, $page_dir, $static_base, $site_root, $extract_url_path );
				return 'url(' . $q . $new . $q . ')';
			},
			$html
		);

		// ── Rewrite 3: @import "..." / @import '...' in inline <style> ───
		$html = preg_replace_callback(
			'/@import\s+(["\'])([^"\']+)\1/i',
			function ( $m ) use ( $page_dir, $static_base, $site_root, $extract_url_path ) {
				$q   = $m[1];
				$new = $this->resolve_url( $m[2], false, $page_dir, $static_base, $site_root, $extract_url_path );
				return '@import ' . $q . $new . $q;
			},
			$html
		);

		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Handle /adsd-sc/?sc=BASE64 — renders a shortcode with transient caching.
	 *
	 * Runs at 'init' priority 0, before WordPress populates $_GET from the
	 * rewrite system, so we parse the query string ourselves from REQUEST_URI.
	 */

	/**
	 * Basic rate limiting for public endpoints using transients.
	 * Allows max 30 requests per minute per IP.
	 *
	 * @return bool True if rate limit exceeded.
	 */
	private function is_rate_limited() {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$key     = 'adsd_rl_' . md5( $ip );
		$count   = (int) get_transient( $key );
		if ( $count >= 30 ) {
			return true;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return false;
	}

	private function handle_shortcode_request() {
		// Rate limit: max 30 requests/minute per IP.
		if ( $this->is_rate_limited() ) {
			status_header( 429 );
			header( 'Retry-After: 60' );
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!-- ADSD: rate limit exceeded -->';
			return;
		}

		ob_start();

		// CORS — allow same-host AND admin-origin requests.
		// blob: iframes (live preview) send no Origin header — we allow those too
		// since the request comes from the WP admin on the same server.
		$adsd_sc_origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$adsd_sc_home   = wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST );
		$adsd_sc_admin  = wp_parse_url( admin_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( admin_url(), PHP_URL_HOST );
		if ( ! $adsd_sc_origin ) {
			// No Origin header = same-site or blob: iframe — allow with wildcard.
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		} elseif (
			rtrim( $adsd_sc_origin, '/' ) === rtrim( $adsd_sc_home, '/' ) ||
			rtrim( $adsd_sc_origin, '/' ) === rtrim( $adsd_sc_admin, '/' )
		) {
			header( 'Access-Control-Allow-Origin: ' . $adsd_sc_origin );
			header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
			header( 'Vary: Origin' );
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			ob_end_clean();
			status_header( 204 );
			return;
		}

		// Parse the query string directly from REQUEST_URI — reliable at init:0.
		$uri_raw  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$qs       = wp_parse_url( $uri_raw, PHP_URL_QUERY );
		$params   = array();
		if ( $qs ) {
			parse_str( $qs, $params );
		}

		// Also check $_GET as fallback (populated later in WP lifecycle).
		$raw_sc = '';
		if ( ! empty( $params['sc'] ) ) {
			$raw_sc = $params['sc'];
		} elseif ( ! empty( $_GET['sc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$raw_sc = wp_unslash( $_GET['sc'] ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		}

		$encoded = preg_replace( '/[^A-Za-z0-9+\/=]/', '', $raw_sc );
		if ( ! $encoded ) {
			ob_end_clean();
			status_header( 400 );
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!-- ADSD: missing sc parameter -->';
			return;
		}

		$decoded = base64_decode( $encoded ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( ! $decoded ) {
			ob_end_clean();
			status_header( 400 );
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!-- ADSD: invalid sc parameter -->';
			return;
		}

		// Check if this is a filter payload (JSON) or a plain shortcode string.
		$json = json_decode( $decoded, true );
		if ( isset( $json['type'] ) && 'filter' === $json['type'] && isset( $json['filters'] ) ) {
			// ── Filter-based content block ────────────────────────────────────
			$cache_key = 'adsd_sc_' . md5( $decoded );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				ob_end_clean();
				header( 'Content-Type: text/html; charset=utf-8' );
				header( 'X-ADSD-Cache: HIT' );
				echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			}

			$f_filters = $json['filters'];
			$grid_cols = max( 1, absint( $f_filters['columns'] ?? 3 ) );

			$bridge  = new AD_SD_WSC_Shortcode_Bridge();
			$content = $bridge->render_filter( $f_filters );

			if ( empty( trim( $content ) ) ) {
				$output = '<!-- ADSD: no posts found for these filters -->';
			} else {
				$cols_tablet = min( 2, $grid_cols );
				$css_rules   = "display:grid!important;grid-template-columns:repeat({$grid_cols},1fr)!important;gap:24px!important;width:100%!important;box-sizing:border-box!important;";

				$output = '<div class="adsd-filter-wrap" data-adsd-css="' . esc_attr(
					".adsd-filter-wrap{display:grid;grid-template-columns:repeat({$grid_cols},1fr);gap:24px;width:100%;box-sizing:border-box;}"
					. "@media(max-width:768px){.adsd-filter-wrap{grid-template-columns:repeat({$cols_tablet},1fr);}}"
					. "@media(max-width:480px){.adsd-filter-wrap{grid-template-columns:1fr;}}"
				) . '" style="' . $css_rules . '">'
				. $content
				. '</div>';
			}

			set_transient( $cache_key, $output, DAY_IN_SECONDS );
			ob_end_clean();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-ADSD-Cache: MISS' );
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// Plain shortcode string.
		$shortcode = $decoded;

		// Transient cache — keyed by shortcode, flushed on any post/product save.
		$cache_key = 'adsd_sc_' . md5( $shortcode );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			ob_end_clean();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'X-ADSD-Cache: HIT' );
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		// Boot WooCommerce if needed, then render the shortcode.
		$this->maybe_boot_woocommerce();

		$tag = '';
		if ( preg_match( '/^\[([a-zA-Z0-9_-]+)/', trim( $shortcode ), $m ) ) {
			$tag = $m[1];
		}
		if ( $tag && ! shortcode_exists( $tag ) ) {
			ob_end_clean();
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!-- ADSD: shortcode [' . esc_html( $tag ) . '] not found -->';
			return;
		}

		// Shortcode allowlist check — if admin has set allowed tags, only those run.
		// Empty option means all registered shortcodes are allowed (default behavior).
		if ( $tag ) {
			$allowed_raw = get_option( 'adsd_allowed_shortcodes', '' );
			if ( ! empty( $allowed_raw ) ) {
				$allowed_tags = array_filter( array_map( 'trim', explode( ',', $allowed_raw ) ) );
				if ( ! empty( $allowed_tags ) && ! in_array( $tag, $allowed_tags, true ) ) {
					ob_end_clean();
					header( 'Content-Type: text/html; charset=utf-8' );
					echo '<!-- ADSD: shortcode [' . esc_html( $tag ) . '] not in allowlist -->';
					return;
				}
			}
		}

		// Suppress third-party notices so they don't corrupt the HTML.
		$prev = error_reporting( E_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		// WooCommerce product shortcodes need frontend hooks + woocommerce_loop global.
		if ( function_exists( 'WC' ) ) {
			if ( ! did_action( 'wp_enqueue_scripts' ) ) {
				do_action( 'wp_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			}
			global $woocommerce_loop;
			if ( empty( $woocommerce_loop ) ) {
				$woocommerce_loop = array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
					'loop'         => 0,
					'columns'      => 4,
					'name'         => '',
					'is_shortcode' => true,
					'is_paginated' => false,
					'total'        => 0,
					'total_pages'  => 0,
					'per_page'     => 0,
					'current_page' => 1,
				);
			}
		}

		$html = do_shortcode( $shortcode );
		error_reporting( $prev ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

		// Fix add-to-cart URLs — remove sc= parameter that leaks from endpoint URL.
		$site_url = rtrim( home_url( '/' ), '/' );
		$html     = preg_replace(
			'/href="[^"]*\?sc=[^"]*&amp;(add-to-cart=[^"]*)"/',
			'href="' . $site_url . '?$1"',
			$html
		);
		$html = preg_replace(
			'/href="[^"]*\?sc=[^"]*&(add-to-cart=[^"]*)"/',
			'href="' . $site_url . '?$1"',
			$html
		);

		// Extract columns from shortcode.
		$cols = 4;
		if ( preg_match( '/columns=["\']?(\d+)["\']?/i', $shortcode, $cm ) ) {
			$cols = max( 1, absint( $cm[1] ) );
		}

		$wrap_id      = 'adsd-wrap-' . substr( md5( $shortcode ), 0, 8 );
		$cols_tablet  = min( 2, $cols );

		// Build CSS rules as a plain string — JS will inject this into <head>
		// via document.createElement('style') which ALWAYS works, unlike
		// <style> tags set via innerHTML which browsers silently ignore.
		$css_rules = ''
			. "#{$wrap_id} ul.products::before,#{$wrap_id} ul.products::after{display:none!important;content:''!important;}"
			. "#{$wrap_id} ul.products[class*='columns-']{display:grid!important;grid-template-columns:repeat({$cols},1fr)!important;gap:24px!important;list-style:none!important;padding:0!important;margin:0!important;width:100%!important;float:none!important;}"
			. "#{$wrap_id} ul.products[class*='columns-'] li.product{width:100%!important;float:none!important;clear:none!important;margin:0!important;}"
			. "@media(max-width:768px){#{$wrap_id} ul.products[class*='columns-']{grid-template-columns:repeat({$cols_tablet},1fr)!important;}}"
			. "@media(max-width:480px){#{$wrap_id} ul.products[class*='columns-']{grid-template-columns:1fr!important;}}";

		// Embed CSS as a data attribute — JS reads and injects it into <head>.
		$output = '<div id="' . esc_attr( $wrap_id ) . '" data-adsd-css="' . esc_attr( $css_rules ) . '">'
			. $html
			. '</div>';

		// Cache for 24 h — auto-invalidated on save_post / woocommerce_update_product.
		set_transient( $cache_key, $output, DAY_IN_SECONDS );

		ob_end_clean();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-ADSD-Cache: MISS' );
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Boot WooCommerce shortcode registration if not done yet.
	 */
	private function maybe_boot_woocommerce() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		if ( class_exists( 'WC_Shortcodes' ) && ! shortcode_exists( 'products' ) ) {
			WC_Shortcodes::init();
		}
		if ( ! did_action( 'woocommerce_init' ) ) {
			do_action( 'woocommerce_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}
	}

	private function handle_render_request( $type ) {
		// Buffer immediately so stray PHP warnings/notices from WP or plugins
		// cannot corrupt the response or cause "headers already sent" errors.
		ob_start();

		// CORS — only send header when Origin differs from home URL.
		// Sending Access-Control-Allow-Origin on same-origin requests can
		// confuse some browsers/proxies. For same-origin fetch (no credentials,
		// no cors mode) no CORS headers are needed at all.
		if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
			$origin      = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
			$home_origin = wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST );
			if ( rtrim( $origin, '/' ) !== rtrim( $home_origin, '/' ) ) {
				header( 'Access-Control-Allow-Origin: ' . $origin );
				header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type' );
				header( 'Vary: Origin' );
			}
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			ob_end_clean();
			status_header( 204 );
			exit;
		}

		$bridge = new AD_SD_WSC_Shortcode_Bridge();

		// On the /adsd-render/ early-intercept path WP boots before plugins finish
		// their init. Force WooCommerce shortcode registration if needed.
		if ( function_exists( 'WC' ) ) {
			if ( class_exists( 'WC_Shortcodes' ) && ! shortcode_exists( 'products' ) ) {
				WC_Shortcodes::init();
			}
			if ( ! did_action( 'woocommerce_init' ) ) {
				do_action( 'woocommerce_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			}
		}

		if ( 'shortcode' === $type ) {
			// sanitize_text_field() strips '+' characters which are valid in base64,
			// causing base64_decode() to silently return garbage. Use wp_unslash only
			// and restrict to base64-alphabet chars for safety.
			$raw_sc  = isset( $_GET['sc'] ) ? wp_unslash( $_GET['sc'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			$encoded = preg_replace( '/[^A-Za-z0-9+\/=]/', '', $raw_sc );
			// Discard any output buffered so far (stray notices) before sending content.
			ob_end_clean();
			header( 'Content-Type: text/html; charset=utf-8' );
			echo $bridge->render_shortcode( $encoded ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'filter' === $type ) {
			$filters = array(
				'post_type'     => isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : 'post', // phpcs:ignore WordPress.Security.NonceVerification
				'layout_id'     => isset( $_GET['layout_id'] ) ? absint( $_GET['layout_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'category'      => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				'tag'           => isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( $_GET['tag'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				'orderby'       => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date', // phpcs:ignore WordPress.Security.NonceVerification
				'order'         => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification
				'count'         => isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 4, // phpcs:ignore WordPress.Security.NonceVerification
				'only_featured' => ! empty( $_GET['only_featured'] ), // phpcs:ignore WordPress.Security.NonceVerification
				'only_sale'     => ! empty( $_GET['only_sale'] ), // phpcs:ignore WordPress.Security.NonceVerification
			);
			ob_end_clean();
			header( 'Content-Type: text/html; charset=utf-8' );
			echo $bridge->render_filter( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Detect macOS metadata artifacts.
	 *
	 * @param string $path File path or filename.
	 * @return bool
	 */
	private function is_macos_artifact( $path ) {
		$basename = basename( $path );
		return '._' === substr( $basename, 0, 2 )
			|| '.DS_Store' === $basename
			|| false !== strpos( $path, '__MACOSX' );
	}

	/**
	 * Handle /adsd-page/?url=... requests.
	 * Renders a standalone HTML page: injected header + target WP page content + injected footer.
	 * Zero dependency on the active theme — no plugin conflicts.
	 *
	 * @return void
	 */
	private function handle_adsd_page_request() {
		// Must be enabled.
		if ( ! get_option( 'adsd_wp_injector_enabled', 0 ) ) {
			status_header( 404 );
			echo '<p>ADSD Page template is not enabled.</p>';
			return;
		}

		require_once AD_SD_WSC_PLUGIN_DIR . 'templates/adsd-page-template.php';
	}

}

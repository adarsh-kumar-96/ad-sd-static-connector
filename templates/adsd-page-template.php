<?php
/**
 * ADSD Page Template
 * Standalone HTML template served by the plugin at /adsd-page/?url=...
 * Renders: injected <head> + header + requested page content + footer
 * No WordPress theme is involved — zero plugin conflicts.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Gather saved injector settings ────────────────────────────────────────
$adsd_head_code   = get_option( 'adsd_wp_head_code', '' );
$adsd_header_html = get_option( 'adsd_wp_header_html', '' );
$adsd_footer_html = get_option( 'adsd_wp_footer_html', '' );
$adsd_script_html = get_option( 'adsd_wp_script_html', '' );
$adsd_custom_css  = get_option( 'adsd_wp_custom_css', '' );
$adsd_custom_js   = get_option( 'adsd_wp_custom_js', '' );

// ── Target URL to embed ────────────────────────────────────────────────────
$raw_url    = isset( $_GET['url'] ) ? wp_unslash( $_GET['url'] ) : ''; // phpcs:ignore
$adsd_target_url = esc_url_raw( $raw_url );

// Only allow same-site URLs for security.
$adsd_home_host  = wp_parse_url( home_url(), PHP_URL_HOST );
$adsd_req_host   = $adsd_target_url ? wp_parse_url( $adsd_target_url, PHP_URL_HOST ) : '';
$adsd_is_allowed = ( '' === $adsd_req_host || $adsd_req_host === $adsd_home_host );

// ── Fetch the content of the target WP page ───────────────────────────────
$adsd_page_content = '';
$adsd_page_title   = '';

if ( $adsd_target_url && $adsd_is_allowed ) {
	// Use WP's HTTP API to fetch the target page content (no cookie forwarding for security).
	$adsd_response = wp_remote_get( $adsd_target_url, array(
		'timeout'    => 10,
		'user-agent' => 'ADSD-Page-Fetcher/1.0',
		'sslverify'  => true,
	) );

	if ( ! is_wp_error( $adsd_response ) ) {
		$adsd_raw_body = wp_remote_retrieve_body( $adsd_response );

		// Extract <title>.
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $adsd_raw_body, $tm ) ) {
			$adsd_page_title = html_entity_decode( wp_strip_all_tags( $tm[1] ), ENT_QUOTES, 'UTF-8' );
		}

		// Extract the main content area — try common WP wrappers in order.
		$adsd_extracted = '';
		$adsd_patterns  = array(
			// WooCommerce / most themes: <main ...>
			'/<main\b[^>]*>(.*?)<\/main>/is',
			// Twenty* themes: <div id="primary" ...>
			'/<div[^>]+id=["\']primary["\'][^>]*>(.*?)<\/div>\s*(?:<\/div>|$)/is',
			// Generic .site-main
			'/<div[^>]+class=["\'][^"\']*site-main[^"\']*["\'][^>]*>(.*?)<\/div>/is',
			// Entry content only
			'/<div[^>]+class=["\'][^"\']*entry-content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
		);

		foreach ( $adsd_patterns as $adsd_pattern ) {
			if ( preg_match( $adsd_pattern, $adsd_raw_body, $m ) ) {
				$adsd_extracted = $m[1];
				break;
			}
		}

		// If no pattern matched, show a clean fallback.
		if ( $adsd_extracted ) {
			// Fix relative URLs in the fetched content to absolute.
			$adsd_base = rtrim( home_url(), '/' );
			$adsd_extracted = preg_replace_callback(
				'/\b(src|href)\s*=\s*(["\'])([^"\']+)(\2)/i',
				function( $m ) use ( $adsd_base ) {
					$url = $m[3];
					if ( preg_match( '/^(https?:|\/\/|#|javascript:|mailto:|tel:)/i', $url ) ) {
						// Absolute / special — wrap WP page links to go through /adsd-page/
						if ( preg_match( '/^https?:\/\/' . preg_quote( wp_parse_url( home_url(), PHP_URL_HOST ), '/' ) . '/i', $url )
							&& ! preg_match( '/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot|pdf|zip)(\?|$)/i', $url )
						) {
							$wrapped = home_url( '/adsd-page/' ) . '?url=' . rawurlencode( $url );
							return $m[1] . '=' . $m[2] . $wrapped . $m[4];
						}
						return $m[0];
					}
					// Relative URL — make absolute.
					$abs = $adsd_base . '/' . ltrim( $url, '/' );
					// If it's an HTML page link, wrap through /adsd-page/
					if ( ! preg_match( '/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|eot|pdf|zip)(\?|$)/i', $url ) ) {
						$abs = home_url( '/adsd-page/' ) . '?url=' . rawurlencode( $abs );
					}
					return $m[1] . '=' . $m[2] . $abs . $m[4];
				},
				$adsd_extracted
			);
			$adsd_page_content = $adsd_extracted;
		} else {
			$adsd_page_content = '<p style="color:#999;text-align:center;padding:40px 0;">'
				. esc_html__( 'Content could not be extracted from this page.', 'ad-sd-static-connector' )
				. '</p>';
		}
	} else {
		$adsd_page_content = '<p style="color:#c00;text-align:center;padding:40px 0;">'
			. esc_html__( 'Failed to load page: ', 'ad-sd-static-connector' )
			. esc_html( $adsd_response->get_error_message() )
			. '</p>';
	}
} elseif ( $adsd_target_url && ! $adsd_is_allowed ) {
	$adsd_page_content = '<p style="color:#c00;text-align:center;padding:40px 0;">'
		. esc_html__( 'External URLs are not allowed for security reasons.', 'ad-sd-static-connector' )
		. '</p>';
}

// ── Resolve relative URLs in head/header/footer injected code ─────────────
$adsd_static_base = home_url( '/adsd-static/' );

function adsd_tpl_fix_urls( $html, $adsd_static_base ) {
	return preg_replace_callback(
		'/\b(src|href)\s*=\s*(["\'])([^"\']+)(\2)/i',
		function( $m ) use ( $adsd_static_base ) {
			$url = $m[3];
			if ( preg_match( '/^(https?:|\/\/|data:|#|javascript:|mailto:|tel:)/i', $url ) || '/' === $url[0] ) {
				return $m[0];
			}
			return $m[1] . '=' . $m[2] . $adsd_static_base . ltrim( $url, './' ) . $m[4];
		},
		$html
	);
}

// ── Strip <script> from head_code → move to bottom ───────────────────────
$adsd_head_no_scripts = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $adsd_head_code );
$adsd_head_no_scripts = preg_replace( '/<script\b[^>]*\/>/is', '', $adsd_head_no_scripts );
$adsd_head_no_scripts = trim( $adsd_head_no_scripts );

preg_match_all( '/<script\b[^>]*>.*?<\/script>/is', $adsd_head_code, $head_script_blocks );
$adsd_head_scripts_output = implode( "\n", $head_script_blocks[0] );

// ── Send the full page ─────────────────────────────────────────────────────
// Security headers.
header( 'Content-Type: text/html; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );
header( 'X-Frame-Options: SAMEORIGIN' );
header( 'Referrer-Policy: strict-origin-when-cross-origin' );
// CSP: allow styles/scripts from same origin + inline (needed for injected CSS/JS).
header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data: https:;" );
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( $adsd_page_title ) : ?>
<title><?php echo esc_html( $adsd_page_title ); ?></title>
<?php endif; ?>

<?php if ( $adsd_head_no_scripts ) : ?>
<!-- ADSD Injector: Head Code -->
<?php echo adsd_tpl_fix_urls( $adsd_head_no_scripts, $adsd_static_base ); // phpcs:ignore ?>
<?php endif; ?>

<?php if ( $adsd_custom_css ) : ?>
<!-- ADSD Injector: Custom CSS -->
<style id="adsd-page-css">
<?php echo $adsd_custom_css; // phpcs:ignore ?>
</style>
<?php endif; ?>

</head>
<body class="adsd-page-body">

<?php if ( $adsd_header_html ) : ?>
<!-- ADSD Injector: Header -->
<?php echo adsd_tpl_fix_urls( $adsd_header_html, $adsd_static_base ); // phpcs:ignore ?>
<?php endif; ?>

<!-- ADSD Page Content -->
<div id="adsd-page-content" class="adsd-page-content">
<?php echo $adsd_page_content; // phpcs:ignore ?>
</div>

<?php if ( $adsd_footer_html ) : ?>
<!-- ADSD Injector: Footer -->
<?php echo adsd_tpl_fix_urls( $adsd_footer_html, $adsd_static_base ); // phpcs:ignore ?>
<?php endif; ?>

<?php if ( $adsd_head_scripts_output ) : ?>
<!-- ADSD Injector: Scripts from head (moved to bottom) -->
<?php echo adsd_tpl_fix_urls( $adsd_head_scripts_output, $adsd_static_base ); // phpcs:ignore ?>
<?php endif; ?>

<?php if ( $adsd_script_html ) : ?>
<!-- ADSD Injector: Script Files -->
<?php echo adsd_tpl_fix_urls( $adsd_script_html, $adsd_static_base ); // phpcs:ignore ?>
<?php endif; ?>

<?php if ( $adsd_custom_js ) : ?>
<!-- ADSD Injector: Custom JS -->
<script id="adsd-page-js">
<?php echo $adsd_custom_js; // phpcs:ignore ?>
</script>
<?php endif; ?>

</body>
</html>

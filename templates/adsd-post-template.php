<?php
/**
 * ADSD Post Template — Standalone classical single post layout.
 *
 * EXACT layout order:
 *  1. Post Title
 *  2. Featured Image  (with padding, no border)
 *  3. Post Meta       (Author · Date · Read Time · Views)
 *  4. Excerpt Box
 *  5. Post Content
 *  6. Prev/Next + Share buttons
 *  7. Comments
 *  8. Related Posts
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! is_single() || get_post_type() !== 'post' || ! get_option( 'adsd_post_template_enabled', 0 ) ) {
	return;
}

// ── Options ────────────────────────────────────────────────────────
$adsd_show_author   = (bool) get_option( 'adsd_post_meta_author',    1 );
$adsd_show_date     = (bool) get_option( 'adsd_post_meta_date',      1 );
$adsd_show_category = (bool) get_option( 'adsd_post_meta_category',  1 );
$adsd_show_tags     = (bool) get_option( 'adsd_post_meta_tags',      1 );
$adsd_show_read     = (bool) get_option( 'adsd_post_meta_read_time', 1 );
$adsd_show_views    = (bool) get_option( 'adsd_post_meta_views',     0 );
$adsd_show_excerpt  = (bool) get_option( 'adsd_post_show_excerpt',   1 );
$adsd_show_related  = (bool) get_option( 'adsd_post_show_related',   1 );
$adsd_rel_count     = max( 1, min( 6, absint( get_option( 'adsd_post_related_count', 3 ) ) ) );

// ── Post data ──────────────────────────────────────────────────────
global $post;
$adsd_post_id   = get_the_ID();
$adsd_cats      = get_the_category( $adsd_post_id );
$adsd_tags_list = get_the_tags( $adsd_post_id );
$adsd_words     = str_word_count( wp_strip_all_tags( get_the_content() ) );
$adsd_read_time = max( 1, (int) ceil( $adsd_words / 200 ) );
if ( $adsd_show_views ) {
	$adsd_views = (int) get_post_meta( $adsd_post_id, 'adsd_post_views', true );
	update_post_meta( $adsd_post_id, 'adsd_post_views', $adsd_views + 1 );
	$adsd_views++;
}

// ── Injector fields (user-defined) ────────────────────────────────
$adsd_inj_on     = adsd_injector_is_enabled();
$adsd_inj_hdr    = $adsd_inj_on ? get_option( 'adsd_wp_header_html', '' ) : '';
$adsd_inj_ftr    = $adsd_inj_on ? get_option( 'adsd_wp_footer_html', '' ) : '';
$adsd_head_code  = $adsd_inj_on ? get_option( 'adsd_wp_head_code',   '' ) : '';
$adsd_custom_css = $adsd_inj_on ? get_option( 'adsd_wp_custom_css',  '' ) : '';

// ── Share URLs ────────────────────────────────────────────────────
$adsd_enc_url   = rawurlencode( get_permalink() );
$adsd_enc_title = rawurlencode( get_the_title() );

// ── Prev / Next ───────────────────────────────────────────────────
$adsd_prev = get_previous_post();
$adsd_next = get_next_post();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( get_bloginfo( 'name' ) . ' – ' . get_the_title() ); ?></title>
<?php
// Safe head code (meta/link tags only).
if ( $adsd_head_code ) {
	// Strip any body/html/script tags and text nodes that don't belong in <head>.
	// If user pasted full HTML, extract only valid <head> child elements.
	$adsd_clean_head = $adsd_head_code;
	// Remove everything from <body to end (handles full-page pastes).
	$adsd_clean_head = preg_replace( '/<body[\s\S]*/i', '', $adsd_clean_head );
	// Remove <html>, <head>, </head>, </html> wrapper tags.
	$adsd_clean_head = preg_replace( '/<\/?(?:html|head|body)[^>]*>/i', '', $adsd_clean_head );
	// Remove <script> blocks entirely (security + no JS in head needed here).
	$adsd_clean_head = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $adsd_clean_head );
	// Remove DOCTYPE.
	$adsd_clean_head = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $adsd_clean_head );
	echo wp_kses( $adsd_clean_head, array(
		'link'  => array( 'rel' => true, 'href' => true, 'type' => true, 'media' => true, 'crossorigin' => true, 'integrity' => true ),
		'meta'  => array( 'name' => true, 'content' => true, 'property' => true, 'charset' => true, 'http-equiv' => true ),
		'style' => array( 'type' => true, 'id' => true ),
		'title' => array(),
	) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
wp_head();
?>
<style id="adsd-post-tpl-css">
:root{
  --adsd-pt-max-desktop:<?php echo intval( get_option('adsd_container_desktop',860) ); ?>px;
  --adsd-pt-max-tablet :<?php echo intval( get_option('adsd_container_tablet',760) ); ?>px;
  --adsd-pt-max-mobile :<?php echo intval( get_option('adsd_container_mobile',100) ); ?>%;
  --adsd-pt-padding    :<?php echo intval( get_option('adsd_container_padding',24) ); ?>px;
  --adsd-pt-margin-top :<?php echo intval( get_option('adsd_container_margin_top',0) ); ?>px;
}
/* ══════════════════════════════════════════════
   ADSD POST TEMPLATE — Classical Design
   ══════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

html{scroll-behavior:smooth; margin-top:0 !important;}

body.adsd-pt-body{
  font-family:'Georgia','Times New Roman',serif;
  background:#f2f1ee;
  color:#1f1f1f;
  line-height:1.85;
  font-size:17px;
  -webkit-font-smoothing:antialiased;
}

/* ── User header / footer wrappers ── */
.adsd-pt-site-header{width:100%;display:block;}
.adsd-pt-site-footer{width:100%;display:block;margin-top:48px;}

/* ── Main wrapper ── */
.adsd-pt-page{
  max-width:var(--adsd-pt-max-desktop);
  width:100%;
  margin:var(--adsd-pt-margin-top) auto 0;
  padding-top:36px;
  padding-bottom:72px;
  padding-left:var(--adsd-pt-padding);
  padding-right:var(--adsd-pt-padding);
  box-sizing:border-box;
}
@media(max-width:1023px){
  .adsd-pt-page{max-width:var(--adsd-pt-max-tablet);}
}
@media(max-width:767px){
  .adsd-pt-page{max-width:var(--adsd-pt-max-mobile);padding-left:14px;padding-right:14px;}
}

/* ── Breadcrumb ── */
.adsd-pt-bc{
  font-family:'Helvetica Neue',Arial,sans-serif;
  font-size:12.5px;color:#999;
  margin-bottom:20px;
  display:flex;flex-wrap:wrap;
  gap:3px 5px;align-items:center;
}
.adsd-pt-bc a{color:#c0392b;text-decoration:none;}
.adsd-pt-bc a:hover{text-decoration:underline;}
.adsd-pt-bc-sep{color:#ccc;}

/* ── Category badges ── */
.adsd-pt-cats{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px;}
.adsd-pt-cat{
  background:#c0392b;color:#fff;
  font-family:'Helvetica Neue',Arial,sans-serif;
  font-size:10.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
  padding:3px 11px;border-radius:3px;text-decoration:none;
}
.adsd-pt-cat:hover{background:#a93226;color:#fff;}

/* ══════════════════════════════════════════════
   1. POST TITLE
   ══════════════════════════════════════════════ */
.adsd-pt-title{
  font-size:clamp(26px,4.5vw,44px);
  font-weight:700;
  line-height:1.2;
  color:#111;
  letter-spacing:-.025em;
  margin-bottom:24px;
}

/* ══════════════════════════════════════════════
   2. FEATURED IMAGE  (padded, clean)
   ══════════════════════════════════════════════ */
.adsd-pt-hero{
  padding:16px;                      /* <── padding around image */
  background:#fff;
  border-radius:12px;
  
  margin-bottom:20px;
}
.adsd-pt-hero img{
  width:100%;
  display:block;
  border-radius:8px;                 /* <── image corners */
  max-height:480px;
  object-fit:cover;
}
.adsd-pt-hero-caption{
  margin-top:8px;
  font-family:'Helvetica Neue',Arial,sans-serif;
  font-size:12px;font-style:italic;
  color:#888;text-align:center;
}

/* ══════════════════════════════════════════════
   3. POST META
   ══════════════════════════════════════════════ */
.adsd-pt-meta{
  display:flex;flex-wrap:wrap;
  align-items:center;gap:5px 16px;
  padding:14px 0;
  border-top:2px solid #ddd8d0;
  border-bottom:2px solid #ddd8d0;
  margin-bottom:26px;
  font-family:'Helvetica Neue',Arial,sans-serif;
  font-size:13.5px;color:#666;
}
.adsd-pt-mi{display:flex;align-items:center;gap:5px;}
.adsd-pt-mi svg{width:14px;height:14px;opacity:.55;flex-shrink:0;}
.adsd-pt-mi a{color:#c0392b;text-decoration:none;}
.adsd-pt-mi a:hover{text-decoration:underline;}
.adsd-pt-mi-sep{color:#d0c8c0;}
.adsd-pt-mi-av{
  width:30px;height:30px;border-radius:50%;
  vertical-align:middle;margin-right:3px;
}

/* ══════════════════════════════════════════════
   4. EXCERPT BOX
   ══════════════════════════════════════════════ */
.adsd-pt-excerpt{
  background:#fff9f2;
  border-left:5px solid #c0392b;
  border-radius:0 8px 8px 0;
  padding:20px 26px;
  margin-bottom:28px;
  font-size:16px;font-style:italic;
  color:#444;line-height:1.78;
  box-shadow:0 2px 10px rgba(0,0,0,.05);
}
.adsd-pt-excerpt::before{
  content:'\201C';
  display:block;
  font-size:54px;line-height:.7;
  color:#c0392b;opacity:.2;
  margin-bottom:6px;
  font-family:Georgia,serif;
}
.adsd-pt-excerpt p{margin:0;}

/* ══════════════════════════════════════════════
   5. POST CONTENT
   ══════════════════════════════════════════════ */
.adsd-pt-content{
  background:#fff;border-radius:10px;
  padding:36px 42px;
  box-shadow:0 2px 18px rgba(0,0,0,.07);
  margin-bottom:28px;
}
.adsd-pt-content h1,.adsd-pt-content h2,
.adsd-pt-content h3,.adsd-pt-content h4{
  color:#111;margin:1.5em 0 .55em;line-height:1.28;
}
.adsd-pt-content h2{
  font-size:1.5em;
  border-bottom:1px solid #ede8e0;padding-bottom:.3em;
}
.adsd-pt-content h3{font-size:1.25em;}
.adsd-pt-content p{margin-bottom:1.25em;}
.adsd-pt-content a{color:#c0392b;text-decoration:underline;}
.adsd-pt-content blockquote{
  border-left:4px solid #c0392b;
  margin:1.4em 0;padding:12px 18px;
  background:#fff8f0;font-style:italic;color:#555;
}
.adsd-pt-content img{
  max-width:100%;height:auto;
  border-radius:6px;margin:1em 0;
  padding:8px;background:#fff;
  box-shadow:0 2px 10px rgba(0,0,0,.08);
}
.adsd-pt-content ul,.adsd-pt-content ol{margin:.8em 0 .8em 1.5em;}
.adsd-pt-content li{margin-bottom:.35em;}
.adsd-pt-content pre{
  background:#1e1e2e;color:#cdd6f4;
  padding:18px 22px;border-radius:8px;
  overflow-x:auto;font-size:13.5px;margin:1.2em 0;
}
.adsd-pt-content table{
  width:100%;border-collapse:collapse;
  margin:1.2em 0;font-size:15px;
}
.adsd-pt-content th{background:#f8f4ef;font-weight:700;}
.adsd-pt-content th,.adsd-pt-content td{
  border:1px solid #e0d8ce;padding:9px 13px;text-align:left;
}
<?php if ( $adsd_custom_css ) {
	// Admin-entered CSS — safe as it comes from manage_options users only.
	echo wp_strip_all_tags( $adsd_custom_css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} ?>

/* ── Tags ── */
.adsd-pt-tags{
  display:flex;flex-wrap:wrap;
  gap:8px;margin-bottom:28px;align-items:center;
}
.adsd-pt-tags-lbl{
  font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.06em;color:#999;
  font-family:'Helvetica Neue',Arial,sans-serif;
}
.adsd-pt-tag{
  background:#f0ece6;color:#555;
  font-size:12.5px;padding:4px 12px;
  border-radius:20px;text-decoration:none;
  font-family:'Helvetica Neue',Arial,sans-serif;
  border:1px solid #ddd5c8;transition:background .18s;
}
.adsd-pt-tag:hover{background:#c0392b;color:#fff;border-color:#c0392b;}

/* ══════════════════════════════════════════════
   6. PREV / NEXT + SHARE  (one row)
   ══════════════════════════════════════════════ */
.adsd-pt-nav-share{
  display:grid;
  grid-template-columns:1fr auto 1fr;
  gap:14px;align-items:center;
  margin-bottom:32px;
}
.adsd-pt-nav-link{
  background:#fff;border-radius:8px;
  padding:14px 18px;text-decoration:none;
  box-shadow:0 2px 10px rgba(0,0,0,.07);
  transition:box-shadow .2s,transform .18s;display:block;
}
.adsd-pt-nav-link:hover{
  box-shadow:0 5px 18px rgba(0,0,0,.12);
  transform:translateY(-2px);
}
.adsd-pt-nav-dir{
  font-size:11px;text-transform:uppercase;
  letter-spacing:.07em;color:#c0392b;font-weight:700;
  font-family:'Helvetica Neue',Arial,sans-serif;margin-bottom:4px;
}
.adsd-pt-nav-title{font-size:13.5px;color:#222;font-weight:600;line-height:1.4;}
.adsd-pt-nav-next{text-align:right;}
.adsd-pt-nav-empty{
  background:transparent;box-shadow:none;
  pointer-events:none;
}
/* Share column */
.adsd-pt-share-col{
  display:flex;flex-direction:column;
  align-items:center;gap:6px;
}
.adsd-pt-share-lbl{
  font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:#bbb;
  font-family:'Helvetica Neue',Arial,sans-serif;
}
.adsd-pt-share-btns{display:flex;gap:7px;}
.adsd-pt-sbtn{
  display:inline-flex;align-items:center;
  justify-content:center;width:36px;height:36px;
  border-radius:50%;text-decoration:none;
  transition:opacity .2s,transform .15s;
  border:none;cursor:pointer;font-size:15px;
}
.adsd-pt-sbtn:hover{opacity:.82;transform:scale(1.1);}
.adsd-pt-sbtn-fb{background:#1877f2;color:#fff;}
.adsd-pt-sbtn-tw{background:#000;color:#fff;}
.adsd-pt-sbtn-wa{background:#25d366;color:#fff;}
.adsd-pt-sbtn-lk{background:#0a66c2;color:#fff;}
.adsd-pt-sbtn-cp{background:#f0ece6;color:#555;border:1px solid #ddd;}

/* ── Author box ── */
.adsd-pt-author{
  display:flex;gap:18px;background:#fff;
  border-radius:10px;padding:22px 26px;
  box-shadow:0 2px 12px rgba(0,0,0,.07);
  margin-bottom:32px;align-items:flex-start;
}
.adsd-pt-author-av{
  width:70px;height:70px;border-radius:50%;flex-shrink:0;
  border:3px solid #e8e0d5;
}
.adsd-pt-author-info h4{font-size:15px;font-weight:700;color:#111;margin-bottom:3px;}
.adsd-pt-author-role{
  font-size:11px;text-transform:uppercase;
  letter-spacing:.06em;color:#c0392b;font-weight:700;
  font-family:'Helvetica Neue',Arial,sans-serif;margin-bottom:6px;
}
.adsd-pt-author-info p{font-size:14px;color:#555;line-height:1.6;}

/* ══════════════════════════════════════════════
   7. COMMENTS
   ══════════════════════════════════════════════ */
.adsd-pt-comments-wrap{
  background:#fff;border-radius:10px;
  padding:28px 36px;
  box-shadow:0 2px 14px rgba(0,0,0,.07);
  margin-bottom:32px;
}
.adsd-pt-comments-wrap h2,
.adsd-pt-comments-wrap h3{
  font-size:18px;font-weight:700;
  margin-bottom:18px;padding-bottom:10px;
  border-bottom:2px solid #ede8e0;color:#111;
}
.adsd-pt-comments-wrap .comment-list{list-style:none;}
.adsd-pt-comments-wrap .comment{
  padding:14px 0;border-bottom:1px solid #f0ece6;
}
.adsd-pt-comments-wrap .comment:last-child{border-bottom:none;}
.adsd-pt-comments-wrap .comment-author b{font-weight:700;color:#222;}
.adsd-pt-comments-wrap .comment-meta{
  font-size:12px;color:#999;margin-bottom:6px;
  font-family:'Helvetica Neue',Arial,sans-serif;
}
.adsd-pt-comments-wrap .comment-content p{font-size:15px;}
.adsd-pt-comments-wrap #respond{margin-top:24px;}
.adsd-pt-comments-wrap #respond h3{
  border-bottom:none;padding-bottom:0;
}
.adsd-pt-comments-wrap input[type="text"],
.adsd-pt-comments-wrap input[type="email"],
.adsd-pt-comments-wrap input[type="url"],
.adsd-pt-comments-wrap textarea{
  width:100%;padding:9px 13px;
  border:1px solid #ddd;border-radius:6px;
  font-family:inherit;font-size:14.5px;
  margin-bottom:10px;background:#fafafa;
  transition:border-color .2s;
}
.adsd-pt-comments-wrap input:focus,
.adsd-pt-comments-wrap textarea:focus{
  border-color:#c0392b;outline:none;
}
.adsd-pt-comments-wrap input[type="submit"],
.adsd-pt-comments-wrap .submit{
  background:#c0392b;color:#fff;border:none;
  padding:10px 26px;border-radius:6px;
  font-size:14.5px;font-weight:700;cursor:pointer;
  font-family:'Helvetica Neue',Arial,sans-serif;
}
.adsd-pt-comments-wrap input[type="submit"]:hover{background:#a93226;}

/* ══════════════════════════════════════════════
   8. RELATED POSTS
   ══════════════════════════════════════════════ */
.adsd-pt-related-title{
  font-size:20px;font-weight:700;
  margin-bottom:16px;color:#111;
  padding-bottom:10px;
  border-bottom:3px solid #c0392b;
  display:inline-block;
}
.adsd-pt-related-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
  gap:16px;margin-bottom:32px;
}
.adsd-pt-rc{
  background:#fff;border-radius:8px;overflow:hidden;
  box-shadow:0 2px 12px rgba(0,0,0,.08);
  text-decoration:none;color:inherit;display:block;
  transition:transform .2s,box-shadow .2s;
}
.adsd-pt-rc:hover{
  transform:translateY(-3px);
  box-shadow:0 7px 22px rgba(0,0,0,.13);
}
.adsd-pt-rc-img-wrap{padding:10px 10px 0;}
.adsd-pt-rc img{
  width:100%;aspect-ratio:16/9;object-fit:cover;
  display:block;border-radius:6px;
}
.adsd-pt-rc-ph{
  width:100%;aspect-ratio:16/9;
  background:linear-gradient(135deg,#e8e0d5,#f5f0ea);
  display:flex;align-items:center;
  justify-content:center;font-size:30px;
}
.adsd-pt-rc-body{padding:12px 14px 14px;}
.adsd-pt-rc-cat{
  font-size:10.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:.07em;color:#c0392b;margin-bottom:4px;
  font-family:'Helvetica Neue',Arial,sans-serif;
}
.adsd-pt-rc-title{
  font-size:14.5px;font-weight:700;
  line-height:1.35;color:#111;margin-bottom:6px;
}
.adsd-pt-rc-date{
  font-size:11.5px;color:#999;
  font-family:'Helvetica Neue',Arial,sans-serif;
}

/* ── Responsive ── */
@media(max-width:768px){
  .adsd-pt-content{padding:20px 16px;}
  .adsd-pt-nav-share{grid-template-columns:1fr 1fr;grid-template-rows:auto auto;}
  .adsd-pt-share-col{grid-column:1/-1;flex-direction:row;justify-content:center;}
  .adsd-pt-author{flex-direction:column;}
  .adsd-pt-comments-wrap{padding:20px 16px;}
  .adsd-pt-related-grid{grid-template-columns:1fr 1fr;}
}
@media(max-width:480px){
  .adsd-pt-page{padding:20px 14px 48px;}
  .adsd-pt-related-grid{grid-template-columns:1fr;}
  .adsd-pt-nav-share{grid-template-columns:1fr;}
  .adsd-pt-nav-next{text-align:left;}
  .adsd-pt-hero{padding:10px;}
}
</style>
</head>
<body class="adsd-pt-body <?php echo esc_attr( implode( ' ', get_body_class() ) ); ?>">

<?php
// ── Injected site header (WP Page Injector) ───────────────────────
if ( $adsd_inj_hdr ) {
	$adsd_inj_hdr = AD_SD_WSC_Helpers::replace_page_title_placeholder( $adsd_inj_hdr, get_the_title() );
	echo '<div class="adsd-pt-site-header">' . wp_kses_post( $adsd_inj_hdr ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>

<div class="adsd-pt-page">

	<?php /* ── BREADCRUMB ── */ ?>
	<nav class="adsd-pt-bc" aria-label="<?php esc_attr_e( 'Breadcrumb', 'ad-sd-static-connector' ); ?>">
		<a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Home', 'ad-sd-static-connector' ); ?></a>
		<span class="adsd-pt-bc-sep" aria-hidden="true">›</span>
		<?php if ( $adsd_cats ) : ?>
			<a href="<?php echo esc_url( get_category_link( $adsd_cats[0]->term_id ) ); ?>"><?php echo esc_html( $adsd_cats[0]->name ); ?></a>
			<span class="adsd-pt-bc-sep" aria-hidden="true">›</span>
		<?php endif; ?>
		<span aria-current="page"><?php the_title(); ?></span>
	</nav>

	<?php /* ── CATEGORY BADGES ── */ ?>
	<?php if ( $adsd_show_category && $adsd_cats ) : ?>
	<div class="adsd-pt-cats">
		<?php foreach ( $adsd_cats as $adsd_c ) : ?>
			<a href="<?php echo esc_url( get_category_link( $adsd_c->term_id ) ); ?>" class="adsd-pt-cat"><?php echo esc_html( $adsd_c->name ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php /* ══════════════════════════════════
	   1. POST TITLE
	   ══════════════════════════════════ */ ?>
	<h1 class="adsd-pt-title"><?php the_title(); ?></h1>

	<?php /* ══════════════════════════════════
	   2. FEATURED IMAGE  (padded box)
	   ══════════════════════════════════ */ ?>
	<?php if ( has_post_thumbnail() ) : ?>
	<div class="adsd-pt-hero">
		<?php the_post_thumbnail( 'large', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
		<?php $adsd_cap = wp_get_attachment_caption( get_post_thumbnail_id() ); ?>
		<?php if ( $adsd_cap ) : ?>
			<div class="adsd-pt-hero-caption"><?php echo esc_html( $adsd_cap ); ?></div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php /* ══════════════════════════════════
	   3. POST META
	   ══════════════════════════════════ */ ?>
	<?php if ( $adsd_show_author || $adsd_show_date || $adsd_show_read || $adsd_show_views ) : ?>
	<div class="adsd-pt-meta" role="list">
		<?php if ( $adsd_show_author ) :
			$adsd_auid = (int) get_the_author_meta( 'ID' );
		?>
		<span class="adsd-pt-mi" role="listitem">
			<?php echo get_avatar( $adsd_auid, 30, '', '', array( 'class' => 'adsd-pt-mi-av' ) ); ?>
			<a href="<?php echo esc_url( get_author_posts_url( $adsd_auid ) ); ?>"><?php the_author(); ?></a>
		</span>
		<span class="adsd-pt-mi-sep" aria-hidden="true">·</span>
		<?php endif; ?>

		<?php if ( $adsd_show_date ) : ?>
		<span class="adsd-pt-mi" role="listitem">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
		</span>
		<span class="adsd-pt-mi-sep" aria-hidden="true">·</span>
		<?php endif; ?>

		<?php if ( $adsd_show_read ) : ?>
		<span class="adsd-pt-mi" role="listitem">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
			<?php
			/* translators: %d: estimated reading time in minutes */
			echo esc_html( sprintf( _n( '%d min read', '%d min read', $adsd_read_time, 'ad-sd-static-connector' ), $adsd_read_time ) );
			?>
		</span>
		<?php endif; ?>

		<?php if ( $adsd_show_views && isset( $adsd_views ) ) : ?>
		<span class="adsd-pt-mi-sep" aria-hidden="true">·</span>
		<span class="adsd-pt-mi" role="listitem">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
			<?php
			/* translators: %s: number of post views formatted with locale */
			echo esc_html( sprintf( __( '%s views', 'ad-sd-static-connector' ), number_format_i18n( $adsd_views ) ) );
			?>
		</span>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php /* ══════════════════════════════════
	   4. EXCERPT BOX
	   ══════════════════════════════════ */ ?>
	<?php if ( $adsd_show_excerpt && has_excerpt() ) : ?>
	<div class="adsd-pt-excerpt"><?php the_excerpt(); ?></div>
	<?php endif; ?>

	<?php /* ══════════════════════════════════
	   5. POST CONTENT
	   ══════════════════════════════════ */ ?>
	<div class="adsd-pt-content">
		<?php the_content(); ?>
		<?php
		wp_link_pages( array(
			'before' => '<div style="margin-top:1.2em"><strong>' . esc_html__( 'Pages:', 'ad-sd-static-connector' ) . '</strong>',
			'after'  => '</div>',
		) );
		?>
	</div>

	<?php /* ── TAGS (after content) ── */ ?>
	<?php if ( $adsd_show_tags && $adsd_tags_list ) : ?>
	<div class="adsd-pt-tags">
		<span class="adsd-pt-tags-lbl"><?php esc_html_e( 'Tags:', 'ad-sd-static-connector' ); ?></span>
		<?php foreach ( $adsd_tags_list as $adsd_t ) : ?>
			<a href="<?php echo esc_url( get_tag_link( $adsd_t->term_id ) ); ?>" class="adsd-pt-tag"><?php echo esc_html( $adsd_t->name ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>


	<?php /* ══════════════════════════════════
	   7. COMMENTS
	   ══════════════════════════════════ */ ?>
	<?php if ( comments_open() || get_comments_number() ) : ?>
	<div class="adsd-pt-comments-wrap">
		<?php comments_template(); ?>
	</div>
	<?php endif; ?>

	
	<?php /* ══════════════════════════════════
	   6. PREV / NEXT  +  SHARE BUTTONS
	   ══════════════════════════════════ */ ?>
	<div class="adsd-pt-nav-share">

		<?php // Prev ── ?>
		<?php if ( $adsd_prev ) : ?>
		<a href="<?php echo esc_url( get_permalink( $adsd_prev ) ); ?>" class="adsd-pt-nav-link">
			<div class="adsd-pt-nav-dir">← <?php esc_html_e( 'Previous', 'ad-sd-static-connector' ); ?></div>
			<div class="adsd-pt-nav-title"><?php echo esc_html( get_the_title( $adsd_prev ) ); ?></div>
		</a>
		<?php else : ?><div class="adsd-pt-nav-empty"></div><?php endif; ?>

		<?php // Share ── ?>
		<div class="adsd-pt-share-col">
			<span class="adsd-pt-share-lbl"><?php esc_html_e( 'Share', 'ad-sd-static-connector' ); ?></span>
			<div class="adsd-pt-share-btns">
				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $adsd_enc_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" target="_blank" rel="noopener noreferrer" class="adsd-pt-sbtn adsd-pt-sbtn-fb" aria-label="Facebook">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
				</a>
				<a href="https://x.com/intent/tweet?url=<?php echo $adsd_enc_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>&text=<?php echo $adsd_enc_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" target="_blank" rel="noopener noreferrer" class="adsd-pt-sbtn adsd-pt-sbtn-tw" aria-label="X / Twitter">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.26 5.632z"/></svg>
				</a>
				<a href="https://wa.me/?text=<?php echo $adsd_enc_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>%20<?php echo $adsd_enc_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" target="_blank" rel="noopener noreferrer" class="adsd-pt-sbtn adsd-pt-sbtn-wa" aria-label="WhatsApp">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.998 2.003C6.478 2.003 2 6.481 2 12.001c0 1.767.461 3.424 1.268 4.866L2 22l5.234-1.263A9.93 9.93 0 0011.998 22C17.52 22 22 17.519 22 12.001c0-5.521-4.48-9.998-10.002-9.998z"/></svg>
				</a>
				<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $adsd_enc_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" target="_blank" rel="noopener noreferrer" class="adsd-pt-sbtn adsd-pt-sbtn-lk" aria-label="LinkedIn">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
				</a>
				<button class="adsd-pt-sbtn adsd-pt-sbtn-cp" aria-label="<?php esc_attr_e( 'Copy link', 'ad-sd-static-connector' ); ?>"
					onclick="var b=this;navigator.clipboard&&navigator.clipboard.writeText(window.location.href).then(function(){b.textContent='✓';setTimeout(function(){b.innerHTML='<?php echo '&#128279;'; ?>';},2000);});">
					🔗
				</button>
			</div>
		</div>

		<?php // Next ── ?>
		<?php if ( $adsd_next ) : ?>
		<a href="<?php echo esc_url( get_permalink( $adsd_next ) ); ?>" class="adsd-pt-nav-link adsd-pt-nav-next">
			<div class="adsd-pt-nav-dir"><?php esc_html_e( 'Next', 'ad-sd-static-connector' ); ?> →</div>
			<div class="adsd-pt-nav-title"><?php echo esc_html( get_the_title( $adsd_next ) ); ?></div>
		</a>
		<?php else : ?><div class="adsd-pt-nav-empty"></div><?php endif; ?>

	</div>

	<?php /* ── Author box (below nav/share) ── */ ?>
	<?php if ( $adsd_show_author ) :
		$adsd_au = (int) get_the_author_meta( 'ID' );
		$adsd_bio = get_the_author_meta( 'description' );
	?>
	<div class="adsd-pt-author">
		<?php echo get_avatar( $adsd_au, 70, '', '', array( 'class' => 'adsd-pt-author-av' ) ); ?>
		<div class="adsd-pt-author-info">
			<h4><?php the_author(); ?></h4>
			<div class="adsd-pt-author-role"><?php esc_html_e( 'Author', 'ad-sd-static-connector' ); ?></div>
			<p><?php echo $adsd_bio ? esc_html( $adsd_bio ) : esc_html__( 'No bio available.', 'ad-sd-static-connector' ); ?></p>
		</div>
	</div>
	<?php endif; ?>

	<?php /* ══════════════════════════════════
	   8. RELATED POSTS
	   ══════════════════════════════════ */ ?>
	<?php if ( $adsd_show_related ) :
		// Related posts query — excludes current post via PHP to avoid performance issues.
		$adsd_rq = new WP_Query( array(
			'post_type'           => 'post',
			'posts_per_page'      => $adsd_rel_count,
			'category__in'        => wp_get_post_categories( $adsd_post_id ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		) );
		if ( $adsd_rq->have_posts() ) :
			$adsd_printed = 0;
	?>
	<div class="adsd-pt-related">
		<div class="adsd-pt-related-title"><?php esc_html_e( 'Related Posts', 'ad-sd-static-connector' ); ?></div>
		<div class="adsd-pt-related-grid">
			<?php while ( $adsd_rq->have_posts() ) :
				$adsd_rq->the_post();
				// Skip the current post (excluded via PHP check).
				if ( get_the_ID() === $adsd_post_id ) { continue; }
				if ( $adsd_printed >= $adsd_rel_count ) { break; }
				$adsd_printed++;
			?>
			<a href="<?php the_permalink(); ?>" class="adsd-pt-rc">
				<div class="adsd-pt-rc-img-wrap">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'medium', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
					<?php else : ?>
						<div class="adsd-pt-rc-ph">📄</div>
					<?php endif; ?>
				</div>
				<div class="adsd-pt-rc-body">
					<?php $adsd_rc = get_the_category(); if ( $adsd_rc ) : ?>
						<div class="adsd-pt-rc-cat"><?php echo esc_html( $adsd_rc[0]->name ); ?></div>
					<?php endif; ?>
					<div class="adsd-pt-rc-title"><?php the_title(); ?></div>
					<div class="adsd-pt-rc-date"><?php echo esc_html( get_the_date() ); ?></div>
				</div>
			</a>
			<?php endwhile; wp_reset_postdata(); ?>
		</div>
	</div>
	<?php endif; endif; ?>

</div><!-- .adsd-pt-page -->

<?php
// ── Injected site footer ──────────────────────────────────────────
if ( $adsd_inj_ftr ) {
	$adsd_inj_ftr = AD_SD_WSC_Helpers::replace_page_title_placeholder( $adsd_inj_ftr, get_the_title() );
	echo '<div class="adsd-pt-site-footer">' . wp_kses_post( $adsd_inj_ftr ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
wp_footer();
?>
</body>
</html>

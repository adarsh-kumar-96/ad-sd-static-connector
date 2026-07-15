=== AD-SD Static Connector ===
Contributors: devadarsh
Tags: static site, html upload, static pages, shortcode bridge, seo
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload, manage, and serve static HTML websites directly from WordPress with shortcode bridging, SEO management, and live deployment.

== Description ==

AD-SD Static Connector lets you upload a ZIP of your static website (HTML/CSS/JS) and serve it live from your WordPress installation — without touching your WordPress theme or breaking any existing pages.

**Key Features:**

* **Dashboard** — Upload ZIP files via drag & drop. Each file gets a card with Check Code, Edit, Live, Stop Live, and Delete actions.
* **Static File Manager** — Browse files inside your ZIP, open a Monaco-powered code editor with live preview, manage version history, find & replace, and set per-page SEO.
* **Mapping** — Select a ZIP and a homepage file, then click Go Live. Your static site is instantly served on your domain's front page while WordPress admin stays intact.
* **Shortcode Bridge** — Convert any WordPress shortcode into an embeddable HTML block. Build filter-based content blocks (by post type, category, tag, rating, sale status) and paste them into your static files.
* **Layout Templates** — Use pre-built or custom HTML layouts with placeholders like `{{product_name}}`, `{{product_price}}` to display dynamic WordPress content inside your static pages.
* **Settings & Logs** — Control upload limits, editor permissions, and view full activity logs.

**Security:**
* PHP files are blocked from upload and execution
* Path traversal protection on all file operations
* Nonce verification on all AJAX requests
* Capability checks (manage_options required)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin from the WordPress Plugins screen
3. Navigate to **Static Connector** in your WordPress admin menu
4. Upload your first ZIP file from the Dashboard tab

== Frequently Asked Questions ==

= Will this break my WordPress site? =
No. The plugin only intercepts the front page when a ZIP is set to Live. Your WordPress admin, plugins, and database remain fully functional.

= What files can I upload? =
HTML, CSS, JS, images (jpg, png, gif, svg, webp), fonts (woff, woff2, ttf, eot), and JSON/XML/TXT. PHP files are blocked for security.

= How does the Shortcode Bridge work? =
The plugin generates a small HTML+JS block that fetches rendered shortcode output from a secure WordPress endpoint. Paste it into your static HTML file and the content loads dynamically.

= Does it work with WooCommerce? =
Yes. The Filter Builder and Layout Templates have WooCommerce-specific support including product price, rating, SKU, featured, and on-sale filters.

== Screenshots ==

1. Dashboard — Upload and manage ZIP files
2. Static File Manager — Monaco editor with live preview
3. SEO Manager — Per-page SEO with auto-generate and score
4. Mapping — Go Live configuration
5. Shortcode Bridge — Generate embeddable HTML blocks
6. Layout Templates — Pre-built and custom layouts
7. Settings & Activity Log

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.

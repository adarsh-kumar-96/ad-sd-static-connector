<?php
/**
 * Shortcode Bridge: renders WP shortcodes directly as embeddable HTML.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Shortcode_Bridge
 */
class AD_SD_WSC_Shortcode_Bridge {

	/**
	 * Generate a ready-to-paste HTML block for the given shortcode.
	 *
	 * The block is rendered server-side right now (no fetch, no JS required).
	 * Paste it anywhere in a static HTML file and it will display the content.
	 *
	 * @param string $shortcode Raw shortcode, e.g. [products limit="4" columns="4"].
	 * @return array { success, code, message }
	 */
	/**
	 * Generate a dynamic embeddable code block for a shortcode.
	 *
	 * The generated code is pasted ONCE into any static HTML file.
	 * After that it automatically shows the latest WordPress content
	 * every time the page loads — no regeneration needed when products
	 * or posts change.
	 *
	 * How it works:
	 *  1. A <div class="adsd-sc"> holds the shortcode (base64) in data-sc.
	 *  2. adsd-loader.js fetches /adsd-sc/?sc=BASE64 from WordPress.
	 *  3. WordPress renders the shortcode, caches the result (transient).
	 *  4. Cache is auto-flushed whenever a post/product is saved.
	 *  5. Next page load → fresh content, no manual action needed.
	 *
	 * @param string $shortcode Raw shortcode, e.g. [products limit="4" columns="4"].
	 * @return array { success, code, message }
	 */
	public function generate_shortcode_div( $shortcode ) {
		if ( empty( trim( $shortcode ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Shortcode cannot be empty.', 'ad-sd-static-connector' ),
			);
		}

		$this->maybe_boot_woocommerce();
		$tag = $this->extract_shortcode_tag( $shortcode );
		if ( $tag && ! shortcode_exists( $tag ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: shortcode tag */
					__( 'Shortcode [%s] not found. Make sure the required plugin is active.', 'ad-sd-static-connector' ),
					esc_html( $tag )
				),
			);
		}

		$encoded = base64_encode( $shortcode ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$uid     = 'adsd-' . substr( md5( $shortcode ), 0, 8 );

		// Generic loader script — finds ALL .adsd-sc divs on the page.
		// User only needs ONE <script> tag per page, no matter how many shortcodes.
		// To update shortcode: only change data-adsd-sc value on the div.
		$js_lines = array(
			'(function(){',
			'function adsdLoad(el){',
			'var sc=el.getAttribute("data-adsd-sc");',
			'if(!sc||el.getAttribute("data-adsd-loaded"))return;',
			'el.setAttribute("data-adsd-loaded","1");',
			'var origin=window.__adsd_wp_origin||window.location.origin;',
			'var url=origin+"/adsd-sc/?sc="+encodeURIComponent(sc);',
			'fetch(url).then(function(r){',
			'if(!r.ok)throw new Error("HTTP "+r.status);',
			'return r.text();',
			'}).then(function(h){',
			'el.innerHTML=h;',
			'var wrap=el.querySelector("[data-adsd-css]");',
			'if(wrap){var css=wrap.getAttribute("data-adsd-css");if(css){var st=document.createElement("style");st.textContent=css;document.head.appendChild(st);}wrap.removeAttribute("data-adsd-css");}',
			'[].slice.call(el.querySelectorAll("script")).forEach(function(o){',
			'var s=document.createElement("script");',
			'if(o.src){s.src=o.src;s.async=false;}else{s.textContent=o.textContent;}',
			'o.parentNode.replaceChild(s,o);',
			'});',
			'}).catch(function(e){console.error("[ADSD]",url,e.message);});',
			'}',
			'function adsdInit(){',
			'var els=document.querySelectorAll(".adsd-sc[data-adsd-sc]");',
			'for(var i=0;i<els.length;i++){adsdLoad(els[i]);}',
			'}',
			'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",adsdInit);}',
			'else{adsdInit();}',
			'})()',
		);

		$js = '<script>' . implode( '', $js_lines ) . '</script>';

		$html = '<!-- AD-SD Static Connector -->' . "\n"
			. '<!-- DIV: Paste where you want content. Change data-adsd-sc to update shortcode. -->' . "\n"
			. '<div id="' . esc_attr( $uid ) . '" class="adsd-sc" data-adsd-sc="' . esc_attr( $encoded ) . '"></div>' . "\n"
			. '<!-- SCRIPT: Paste once per page before </body>. One script handles all divs. -->' . "\n"
			. $js . "\n"
			. '<!-- /AD-SD Static Connector -->';

		return array(
			'success' => true,
			'code'    => $html,
			'message' => __( 'Paste this code once. Content updates automatically when WordPress changes — no re-pasting needed.', 'ad-sd-static-connector' ),
		);
	}

	public function generate_filter_div( $filters ) {
		$defaults = array(
			'post_type'     => 'post',
			'layout_id'     => 0,
			'category'      => '',
			'tag'           => '',
			'orderby'       => 'date',
			'order'         => 'DESC',
			'count'         => 4,
			'min_rating'    => 0,
			'only_featured' => false,
			'only_sale'     => false,
		);
		$filters = wp_parse_args( $filters, $defaults );

		// Encode filters as base64 JSON — passes via /adsd-sc/ endpoint.
		$payload = base64_encode( wp_json_encode( array( 'type' => 'filter', 'filters' => $filters ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$uid     = 'adsd-' . substr( md5( $payload ), 0, 8 );

		$js_lines = array(
			'(function(){',
			'function adsdLoad(el){',
			'var sc=el.getAttribute("data-adsd-sc");',
			'if(!sc||el.getAttribute("data-adsd-loaded"))return;',
			'el.setAttribute("data-adsd-loaded","1");',
			'var origin=window.__adsd_wp_origin||window.location.origin;',
			'var url=origin+"/adsd-sc/?sc="+encodeURIComponent(sc);',
			'fetch(url).then(function(r){',
			'if(!r.ok)throw new Error("HTTP "+r.status);',
			'return r.text();',
			'}).then(function(h){',
			'el.innerHTML=h;',
			'var wrap=el.querySelector("[data-adsd-css]");',
			'if(wrap){var css=wrap.getAttribute("data-adsd-css");if(css){var st=document.createElement("style");st.textContent=css;document.head.appendChild(st);}wrap.removeAttribute("data-adsd-css");}',
			'[].slice.call(el.querySelectorAll("script")).forEach(function(o){',
			'var s=document.createElement("script");',
			'if(o.src){s.src=o.src;s.async=false;}else{s.textContent=o.textContent;}',
			'o.parentNode.replaceChild(s,o);',
			'});',
			'}).catch(function(e){console.error("[ADSD]",url,e.message);});',
			'}',
			'function adsdInit(){',
			'var els=document.querySelectorAll(".adsd-sc[data-adsd-sc]");',
			'for(var i=0;i<els.length;i++){adsdLoad(els[i]);}',
			'}',
			'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",adsdInit);}',
			'else{adsdInit();}',
			'})()',
		);
		$js = '<script>' . implode( '', $js_lines ) . '</script>';

		$html = '<!-- AD-SD Static Connector | Filter Block -->' . "\n"
			. '<div id="' . esc_attr( $uid ) . '" class="adsd-sc" data-adsd-sc="' . esc_attr( $payload ) . '"></div>' . "\n"
			. $js . "\n"
			. '<!-- /AD-SD Static Connector -->';

		return array(
			'success' => true,
			'code'    => $html,
			'message' => __( 'Paste this code once. Content updates automatically when WordPress changes.', 'ad-sd-static-connector' ),
		);
	}

	public function get_plugin_styles() {
		return $this->collect_wc_styles();
	}

	/**
	 * Collect all CSS enqueued by the shortcode render, as root-relative <link> tags.
	 * Works for WooCommerce, Contact Form 7, Elementor, or any plugin shortcode.
	 * No domain hardcoded — root-relative paths only.
	 *
	 * Skips WP core / admin / theme utility styles that don't affect plugin output.
	 *
	 * @return string HTML <link> tags.
	 */
	private function collect_wc_styles() {
		global $wp_styles;
		do_action( 'wp_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

		if ( ! $wp_styles instanceof WP_Styles ) {
			return '';
		}

		// Handle prefixes to skip — WP core styles that don't affect plugin shortcode output.
		$skip_prefixes = array( 'wp-block-', 'wp-edit-', 'dashicons', 'global-styles', 'admin', 'common-', 'forms' );
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$out           = '';

		foreach ( $wp_styles->queue as $handle ) {
			foreach ( $skip_prefixes as $prefix ) {
				if ( 0 === strpos( $handle, $prefix ) ) {
					continue 2;
				}
			}

			$src = isset( $wp_styles->registered[ $handle ] ) ? $wp_styles->registered[ $handle ]->src : '';
			if ( ! $src ) {
				continue;
			}

			// Check if it's an external CDN (different host) — keep absolute.
			$src_host = wp_parse_url( $src, PHP_URL_HOST );
			if ( $src_host && $src_host !== $site_host ) {
				$out .= '<link rel="stylesheet" href="' . esc_attr( $src ) . '">' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
				continue;
			}

			// Strip domain → root-relative path only.
			if ( preg_match( '/^https?:\/\/[^\/]+(\/.+)$/', $src, $m ) ) {
				$src = $m[1];
			} elseif ( 0 !== strpos( $src, '/' ) ) {
				$src = '/' . ltrim( $src, '/' );
			}

			// Remove ?ver= query string — cleaner output.
			$src = preg_replace( '/\?ver=[^&]+/', '', $src );

			$out .= '<link rel="stylesheet" href="' . esc_attr( $src ) . '">' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		}
		return $out;
	}

	public function render_filter( $filters ) {
		$args = array(
			'post_type'      => sanitize_text_field( $filters['post_type'] ?? 'post' ),
			'posts_per_page' => absint( $filters['count'] ?? 4 ),
			'orderby'        => sanitize_text_field( $filters['orderby'] ?? 'date' ),
			'order'          => 'ASC' === strtoupper( $filters['order'] ?? 'DESC' ) ? 'ASC' : 'DESC',
			'post_status'    => 'publish',
		);

		// Resolve which taxonomy acts as "category" and which as "tag" for
		// this post type — works for post/page/product AND any custom post
		// type (e.g. registered by ACF/CPT UI), instead of only post/product.
		list( $cat_tax, $tag_tax ) = $this->get_post_type_taxonomies( $args['post_type'] );

		$tax_query = array();

		if ( ! empty( $filters['category'] ) && $cat_tax ) {
			$tax_query[] = array(
				'taxonomy' => $cat_tax,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( $filters['category'] ),
			);
		}

		if ( ! empty( $filters['tag'] ) && $tag_tax ) {
			$tax_query[] = array(
				'taxonomy' => $tag_tax,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( $filters['tag'] ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		if ( ! empty( $filters['only_featured'] ) ) {
			$args['meta_query'][] = array( 'key' => '_featured', 'value' => 'yes' ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		if ( ! empty( $filters['only_sale'] ) && 'product' === $args['post_type'] ) {
			$sale_ids = wc_get_product_ids_on_sale();
			$args['post__in'] = ! empty( $sale_ids ) ? $sale_ids : array( 0 );
		}

		$query  = new WP_Query( $args );
		$output = '';
		$layout = $this->get_layout_template( absint( $filters['layout_id'] ?? 0 ) );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				// Each item gets its own position:relative box so that any
				// position:absolute/fixed rule inside a user-authored layout
				// template anchors to THIS item instead of escaping to the
				// page — matches how a normal grid card component behaves.
				$output .= '<div class="adsd-filter-item" style="position:relative;">'
					. $this->apply_layout( $layout, get_the_ID() )
					. '</div>';
			}
			wp_reset_postdata();
		}
		return $output;
	}

	/**
	 * Determine the "category-like" (hierarchical) and "tag-like"
	 * (non-hierarchical) taxonomy for a given post type, so filtering and
	 * suggestions work for custom post types too, not just post/product.
	 *
	 * @param string $post_type Post type slug.
	 * @return array{0:string,1:string} [category_taxonomy, tag_taxonomy] — either may be ''.
	 */
	private function get_post_type_taxonomies( $post_type ) {
		// Known built-ins first — keeps existing behaviour identical for
		// post/page/product even if a theme registers extra taxonomies on them.
		if ( 'product' === $post_type && taxonomy_exists( 'product_cat' ) ) {
			return array( 'product_cat', taxonomy_exists( 'product_tag' ) ? 'product_tag' : '' );
		}
		if ( 'post' === $post_type || 'page' === $post_type ) {
			return array(
				taxonomy_exists( 'category' ) ? 'category' : '',
				taxonomy_exists( 'post_tag' ) ? 'post_tag' : '',
			);
		}

		// Custom post type: pick the first hierarchical taxonomy as
		// "category" and the first non-hierarchical as "tag".
		$cat_tax = '';
		$tag_tax = '';
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $tax_slug => $tax_obj ) {
			if ( $tax_obj->hierarchical && ! $cat_tax ) {
				$cat_tax = $tax_slug;
			} elseif ( ! $tax_obj->hierarchical && ! $tag_tax ) {
				$tag_tax = $tax_slug;
			}
		}
		return array( $cat_tax, $tag_tax );
	}

	/**
	 * Get category/tag term suggestions for a post type (used by the admin
	 * UI's autocomplete on the Category/Tag filter fields).
	 *
	 * @param string $post_type Post type slug.
	 * @return array{categories: array, tags: array}
	 */
	public function get_terms_for_post_type( $post_type ) {
		list( $cat_tax, $tag_tax ) = $this->get_post_type_taxonomies( sanitize_text_field( $post_type ) );

		$to_list = function ( $taxonomy ) {
			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				return array();
			}
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 200,
			) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return array();
			}
			return array_map(
				function ( $t ) {
					return array( 'name' => $t->name, 'slug' => $t->slug );
				},
				$terms
			);
		};

		return array(
			'categories' => $to_list( $cat_tax ),
			'tags'       => $to_list( $tag_tax ),
		);
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
			do_action( 'woocommerce_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
	}

	/**
	 * Get a layout template string by ID.
	 *
	 * @param int $layout_id Layout ID (0 = default).
	 * @return string
	 */
	private function get_layout_template( $layout_id ) {
		if ( $layout_id ) {
			global $wpdb;
			$table = esc_sql( $wpdb->prefix . 'adsd_layouts' );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT template FROM `{$table}` WHERE id = %d LIMIT 1", $layout_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			if ( $row && ! empty( $row->template ) ) {
				return $row->template;
			}
		}
		return '<div class="adsd-item"><h3>{{title}}</h3><p>{{excerpt}}</p><a href="{{url}}">Read more</a></div>';
	}

	/**
	 * Apply a layout template to a single post/product.
	 * Replaces ALL placeholders shown in the UI.
	 *
	 * @param string $template Layout template string.
	 * @param int    $post_id  Post ID.
	 * @return string
	 */
	private function apply_layout( $template, $post_id ) {
		$thumb    = get_the_post_thumbnail_url( $post_id, 'medium' );
		$thumb    = $thumb ? esc_url( $thumb ) : '';
		$title    = esc_html( get_the_title( $post_id ) );
		$excerpt  = esc_html( wp_trim_words( get_the_excerpt( $post_id ), 20 ) );
		$url      = esc_url( get_permalink( $post_id ) );
		$date     = esc_html( get_the_date( '', $post_id ) );

		// Category.
		$cats     = get_the_terms( $post_id, 'category' );
		if ( ! $cats || is_wp_error( $cats ) ) {
			$cats = get_the_terms( $post_id, 'product_cat' );
		}
		$category = ( $cats && ! is_wp_error( $cats ) ) ? esc_html( $cats[0]->name ) : '';

		// WooCommerce product data.
		$product_name       = '';
		$product_price      = '';
		$product_image      = '';
		$product_url        = $url;
		$product_rating     = '';
		$product_short_desc = '';
		$product_sku        = '';

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$product_name       = esc_html( $product->get_name() );
				$product_price      = wp_kses_post( wc_price( $product->get_price() ) );
				$img_id             = $product->get_image_id();
				$product_image      = $img_id ? esc_url( wp_get_attachment_url( $img_id ) ) : $thumb;
				$product_url        = esc_url( $product->get_permalink() );
				$product_rating     = esc_html( $product->get_average_rating() );
				$product_short_desc = esc_html( wp_strip_all_tags( $product->get_short_description() ) );
				$product_sku        = esc_html( $product->get_sku() );
			}
		}

		// Fallback: use post data for non-WC post types.
		if ( ! $product_name ) {
			$product_name  = $title;
			$product_image = $thumb;
		}

		$search = array(
			'{{post_title}}',
			'{{post_excerpt}}',
			'{{post_url}}',
			'{{post_thumbnail}}',
			'{{post_category}}',
			'{{date}}',
			'{{product_name}}',
			'{{product_price}}',
			'{{product_image}}',
			'{{product_url}}',
			'{{product_rating}}',
			'{{product_short_desc}}',
			'{{product_sku}}',
			// Legacy placeholders.
			'{{title}}',
			'{{excerpt}}',
			'{{url}}',
			'{{thumbnail}}',
		);

		$replace = array(
			$title,
			$excerpt,
			$url,
			$thumb,
			$category,
			$date,
			$product_name,
			$product_price,
			$product_image,
			$product_url,
			$product_rating,
			$product_short_desc,
			$product_sku,
			// Legacy.
			$title,
			$excerpt,
			$url,
			$thumb,
		);

		return str_replace( $search, $replace, $template );
	}

	/**
	 * Extract the shortcode tag name from a shortcode string.
	 *
	 * @param string $shortcode Full shortcode string.
	 * @return string
	 */
	private function extract_shortcode_tag( $shortcode ) {
		if ( preg_match( '/^\[([a-zA-Z0-9_-]+)/', trim( $shortcode ), $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Get all saved layout templates.
	 *
	 * @return array
	 */
	public function get_all_layouts() {
		global $wpdb;
		$table   = esc_sql( $wpdb->prefix . 'adsd_layouts' );
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at ASC LIMIT %d", 500 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $results ? $results : array();
	}

	/**
	 * Save (create or update) a layout template.
	 *
	 * @param array $data Layout data.
	 * @return array
	 */
	public function save_layout( $data ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'adsd_layouts' );

		$record  = array(
			'layout_name' => sanitize_text_field( $data['layout_name'] ?? 'Custom Layout' ),
			'plugin_type' => sanitize_text_field( $data['plugin_type'] ?? 'generic' ),
			'template'    => wp_kses_post( $data['template'] ?? '' ),
			'layout_type' => 'custom',
			'created_by'  => get_current_user_id(),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d' );

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $table, $record, array( 'id' => absint( $data['id'] ) ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = absint( $data['id'] );
		} else {
			$wpdb->insert( $table, $record, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = $wpdb->insert_id;
		}

		return array( 'success' => true, 'id' => $id, 'message' => __( 'Layout saved successfully.', 'ad-sd-static-connector' ) );
	}

	/**
	 * Delete a layout template (only custom ones).
	 *
	 * @param int $layout_id Layout ID.
	 * @return array
	 */
	public function delete_layout( $layout_id ) {
		global $wpdb;
		$table  = esc_sql( $wpdb->prefix . 'adsd_layouts' );
		$layout = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", absint( $layout_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $layout ) {
			return array( 'success' => false, 'message' => __( 'Layout not found.', 'ad-sd-static-connector' ) );
		}
		if ( 'preset' === $layout->layout_type ) {
			return array( 'success' => false, 'message' => __( 'Pre-built layouts cannot be deleted.', 'ad-sd-static-connector' ) );
		}

		$wpdb->delete( $table, array( 'id' => absint( $layout_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array( 'success' => true, 'message' => __( 'Layout deleted.', 'ad-sd-static-connector' ) );
	}
}

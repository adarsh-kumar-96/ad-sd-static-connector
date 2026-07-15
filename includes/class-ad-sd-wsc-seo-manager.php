<?php
/**
 * SEO management for static files.
 *
 * @package AD_SD_WSC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AD_SD_WSC_Seo_Manager
 */
class AD_SD_WSC_Seo_Manager {

	/**
	 * Get SEO settings for a file.
	 *
	 * @param int    $zip_id   ZIP ID.
	 * @param string $rel_path Relative file path.
	 * @return array
	 */
	public function get_seo( $zip_id, $rel_path ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT * FROM `{$wpdb->prefix}adsd_seo_settings` WHERE zip_id = %d AND file_path = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			absint( $zip_id ),
			$rel_path
		) );

		if ( ! $row ) {
			// Return defaults.
			return array(
				'success'      => true,
				'seo_title'    => '',
				'meta_desc'    => '',
				'meta_keywords'=> '',
				'og_title'     => '',
				'og_desc'      => '',
				'og_image'     => '',
				'canonical'    => '',
				'robots'       => 'index, follow',
				'schema_type'  => 'WebPage',
				'seo_score'    => 0,
			);
		}

		return array(
			'success'       => true,
			'seo_title'     => $row->seo_title,
			'meta_desc'     => $row->meta_desc,
			'meta_keywords' => $row->meta_keywords,
			'og_title'      => $row->og_title,
			'og_desc'       => $row->og_desc,
			'og_image'      => $row->og_image,
			'canonical'     => $row->canonical,
			'robots'        => $row->robots,
			'schema_type'   => $row->schema_type,
			'seo_score'     => $row->seo_score,
		);
	}

	/**
	 * Save SEO settings for a file.
	 *
	 * @param int    $zip_id ZIP ID.
	 * @param string $rel_path Relative path.
	 * @param array  $data   SEO data array.
	 * @return array
	 */
	public function save_seo( $zip_id, $rel_path, $data ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'adsd_seo_settings' );

		$seo_score = $this->calculate_seo_score( $data );

		$record = array(
			'zip_id'        => absint( $zip_id ),
			'file_path'     => sanitize_text_field( $rel_path ),
			'seo_title'     => sanitize_text_field( $data['seo_title'] ?? '' ),
			'meta_desc'     => sanitize_textarea_field( $data['meta_desc'] ?? '' ),
			'meta_keywords' => sanitize_textarea_field( $data['meta_keywords'] ?? '' ),
			'og_title'      => sanitize_text_field( $data['og_title'] ?? '' ),
			'og_desc'       => sanitize_textarea_field( $data['og_desc'] ?? '' ),
			'og_image'      => esc_url_raw( $data['og_image'] ?? '' ),
			'canonical'     => esc_url_raw( $data['canonical'] ?? '' ),
			'robots'        => sanitize_text_field( $data['robots'] ?? 'index, follow' ),
			'schema_type'   => sanitize_text_field( $data['schema_type'] ?? 'WebPage' ),
			'seo_score'     => $seo_score,
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		// Upsert.
		$exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM `{$wpdb->prefix}adsd_seo_settings` WHERE zip_id = %d AND file_path = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			absint( $zip_id ),
			$rel_path
		) );

		if ( $exists ) {
			unset( $record['zip_id'], $record['file_path'] );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				$record,
				array( 'id' => $exists ),
				array_slice( $formats, 2 ),
				array( '%d' )
			);
		} else {
			$wpdb->insert( $table, $record, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		// Inject SEO tags into the actual HTML file.
		$this->inject_seo_into_file( $zip_id, $rel_path, $record );

		AD_SD_WSC_Helpers::log_activity( 'seo_saved', $zip_id, $rel_path );

		return array(
			'success'   => true,
			'message'   => __( 'SEO settings saved successfully.', 'ad-sd-static-connector' ),
			'seo_score' => $seo_score,
		);
	}

	/**
	 * Auto-generate SEO content for a specific field by reading the HTML file.
	 *
	 * @param int    $zip_id    ZIP ID.
	 * @param string $rel_path  Relative file path.
	 * @param string $field     Field to generate (seo_title, meta_desc, meta_keywords, og_title, og_desc).
	 * @return array
	 */
	public function auto_generate_field( $zip_id, $rel_path, $field ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return array( 'success' => false, 'message' => __( 'ZIP not found.', 'ad-sd-static-connector' ) );
		}

		$safe_path = AD_SD_WSC_Helpers::sanitize_file_path( $rel_path );
		$full_path = $zip->extract_path . '/' . $safe_path;

		if ( ! file_exists( $full_path ) ) {
			return array( 'success' => false, 'message' => __( 'File not found.', 'ad-sd-static-connector' ) );
		}
		$html = adsd_file_get_contents( $full_path );
		$content = $this->extract_text_from_html( $html );
		$value   = '';

		switch ( $field ) {
			case 'seo_title':
			case 'og_title':
				// Extract from <title> or first <h1>.
				if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
					$value = wp_strip_all_tags( $m[1] );
				} elseif ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $m ) ) {
					$value = wp_strip_all_tags( $m[1] );
				} else {
					$value = sanitize_text_field( pathinfo( $rel_path, PATHINFO_FILENAME ) );
				}
				$value = trim( substr( $value, 0, 60 ) );
				break;

			case 'meta_desc':
			case 'og_desc':
				// Extract from <meta description> or first <p>.
				if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
					$value = sanitize_text_field( $m[1] );
				} elseif ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $m ) ) {
					$value = wp_strip_all_tags( $m[1] );
					$value = preg_replace( '/\s+/', ' ', $value );
				} else {
					$value = substr( $content, 0, 155 );
				}
				$value = trim( substr( $value, 0, 155 ) );
				break;

			case 'meta_keywords':
				// Extract words from h1,h2,h3 and deduplicate.
				preg_match_all( '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', $html, $headings );
				$words = array();
				if ( ! empty( $headings[1] ) ) {
					foreach ( $headings[1] as $heading ) {
						$clean = wp_strip_all_tags( $heading );
						$parts = preg_split( '/\s+/', strtolower( $clean ) );
						foreach ( $parts as $w ) {
							$w = preg_replace( '/[^a-z0-9]/', '', $w );
							if ( strlen( $w ) > 3 ) {
								$words[] = $w;
							}
						}
					}
				}
				$words = array_unique( $words );
				$value = implode( ', ', array_slice( $words, 0, 10 ) );
				break;
		}

		return array(
			'success' => true,
			'field'   => $field,
			'value'   => $value,
		);
	}

	/**
	 * Calculate SEO score based on filled fields.
	 *
	 * @param array $data SEO data.
	 * @return int Score 0-100.
	 */
	public function calculate_seo_score( $data ) {
		$score  = 0;
		$checks = array(
			array( 'field' => 'seo_title',     'points' => 20, 'min' => 10, 'max' => 60 ),
			array( 'field' => 'meta_desc',     'points' => 25, 'min' => 50, 'max' => 155 ),
			array( 'field' => 'meta_keywords', 'points' => 10, 'min' => 5,  'max' => 200 ),
			array( 'field' => 'og_title',      'points' => 15, 'min' => 5,  'max' => 95 ),
			array( 'field' => 'og_desc',       'points' => 15, 'min' => 5,  'max' => 200 ),
			array( 'field' => 'og_image',      'points' => 10, 'min' => 5,  'max' => 500 ),
			array( 'field' => 'canonical',     'points' => 5,  'min' => 5,  'max' => 500 ),
		);

		foreach ( $checks as $check ) {
			$val = trim( $data[ $check['field'] ] ?? '' );
			$len = strlen( $val );
			if ( $len >= $check['min'] && $len <= $check['max'] ) {
				$score += $check['points'];
			} elseif ( $len > 0 ) {
				$score += (int) ( $check['points'] * 0.5 );
			}
		}

		return min( 100, $score );
	}

	/**
	 * Inject SEO meta tags into HTML file <head>.
	 *
	 * @param int    $zip_id ZIP ID.
	 * @param string $rel_path Relative path.
	 * @param array  $seo   SEO data array.
	 * @return void
	 */
	private function inject_seo_into_file( $zip_id, $rel_path, $seo ) {
		$zip = AD_SD_WSC_Helpers::get_zip_by_id( $zip_id );
		if ( ! $zip ) {
			return;
		}
		$ext = strtolower( pathinfo( $rel_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'html', 'htm' ), true ) ) {
			return;
		}

		$full_path = $zip->extract_path . '/' . AD_SD_WSC_Helpers::sanitize_file_path( $rel_path );
		if ( ! file_exists( $full_path ) ) {
			return;
		}
		$html = adsd_file_get_contents( $full_path );

		// Remove previously injected SEO block.
		$html = preg_replace( '/<!-- ADSD-SEO-START -->.*?<!-- ADSD-SEO-END -->/s', '', $html );

		$site_name = get_bloginfo( 'name' );
		$seo_block = "<!-- ADSD-SEO-START -->\n";
		if ( ! empty( $seo['seo_title'] ) ) {
			$seo_block .= '<title>' . esc_html( $seo['seo_title'] ) . '</title>' . "\n";
		}
		if ( ! empty( $seo['meta_desc'] ) ) {
			$seo_block .= '<meta name="description" content="' . esc_attr( $seo['meta_desc'] ) . '">' . "\n";
		}
		if ( ! empty( $seo['meta_keywords'] ) ) {
			$seo_block .= '<meta name="keywords" content="' . esc_attr( $seo['meta_keywords'] ) . '">' . "\n";
		}
		if ( ! empty( $seo['robots'] ) ) {
			$seo_block .= '<meta name="robots" content="' . esc_attr( $seo['robots'] ) . '">' . "\n";
		}
		if ( ! empty( $seo['canonical'] ) ) {
			$seo_block .= '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '">' . "\n";
		}
		// Open Graph.
		$seo_block .= '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
		if ( ! empty( $seo['og_title'] ) ) {
			$seo_block .= '<meta property="og:title" content="' . esc_attr( $seo['og_title'] ) . '">' . "\n";
		}
		if ( ! empty( $seo['og_desc'] ) ) {
			$seo_block .= '<meta property="og:description" content="' . esc_attr( $seo['og_desc'] ) . '">' . "\n";
		}
		if ( ! empty( $seo['og_image'] ) ) {
			$seo_block .= '<meta property="og:image" content="' . esc_url( $seo['og_image'] ) . '">' . "\n";
		}
		$seo_block .= "<!-- ADSD-SEO-END -->\n";

		// Inject before </head> or at the top.
		if ( false !== stripos( $html, '</head>' ) ) {
			$html = str_ireplace( '</head>', $seo_block . '</head>', $html );
		} else {
			$html = $seo_block . $html;
		}
		adsd_file_put_contents( $full_path, $html );
	}

	/**
	 * Extract plain text from HTML for analysis.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	private function extract_text_from_html( $html ) {
		$text = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html );
		$text = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}
}

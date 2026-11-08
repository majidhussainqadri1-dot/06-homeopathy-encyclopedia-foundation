<?php
/**
 * Master Plan v12: sitemap output is a public projection, not the owner.
 *
 * WordPress post_status=publish is not enough for canonical File 06 entry
 * visibility: the editorial, clinical safety and merge gates reside in the
 * File 06 canonical tables.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V12_Sitemap {
	public static function hooks() {
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'post_types' ), 99 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'query_args' ), 99, 2 );
		add_filter( 'posts_clauses', array( __CLASS__, 'query_clauses' ), 99, 2 );
	}

	public static function post_types( $types ) {
		if ( ! is_array( $types ) ) { return array(); }
		// The public research eligibility check depends on private ethics/consent
		// data and cannot be reconstructed from wp_posts alone. Keep it off the
		// generic sitemap until an owner-verifying research sitemap is installed.
		unset( $types[ HE_V2_Domain::RESEARCH_TYPE ] );
		return $types;
	}

	public static function query_args( $args, $post_type ) {
		if ( HE_V2_Domain::ENTRY_TYPE !== $post_type ) { return $args; }
		$args = is_array( $args ) ? $args : array();
		if ( 'active' !== HE_V2_Schema::runtime_status() ) {
			$args['post__in'] = array( 0 ); // No stale schema may enumerate private titles.
			return $args;
		}
		$args['he_v12_sitemap_guard'] = '1';
		$args['post_status'] = 'publish';
		return $args;
	}

	public static function query_clauses( $clauses, $query ) {
		if ( ! $query instanceof WP_Query || '1' !== (string) $query->get( 'he_v12_sitemap_guard' ) ) { return $clauses; }
		global $wpdb;
		if ( 'active' !== HE_V2_Schema::runtime_status() ) {
			$clauses['where'] .= ' AND 1=0';
			return $clauses;
		}
		$canonical = HE_V2_Schema::table( 'concepts' );
		$clauses['join'] .= " INNER JOIN {$canonical} he_v12_sitemap_concept
			ON he_v12_sitemap_concept.post_id={$wpdb->posts}.ID
			AND he_v12_sitemap_concept.status='published'
			AND he_v12_sitemap_concept.review_status='approved'
			AND he_v12_sitemap_concept.safety_status='approved'
			AND he_v12_sitemap_concept.merged_into_id=0
			AND he_v12_sitemap_concept.current_version>0";
		$clauses['where'] .= $wpdb->prepare( ' AND ' . $wpdb->posts . '.post_type=%s AND ' . $wpdb->posts . '.post_status=%s',
			HE_V2_Domain::ENTRY_TYPE, 'publish' );
		return $clauses;
	}
}

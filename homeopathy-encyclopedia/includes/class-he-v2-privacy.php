<?php
/** WordPress privacy exporters/erasers and public/private cache controls. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
		add_action( 'template_redirect', array( $this, 'cache_control' ), 1 );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['he-v2'] = array(
			'exporter_friendly_name' => __( 'Homeopathy Encyclopedia and Research', 'homeopathy-encyclopedia' ),
			'callback' => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['he-v2'] = array(
			'eraser_friendly_name' => __( 'Homeopathy Encyclopedia and Research', 'homeopathy-encyclopedia' ),
			'callback' => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$data = array();
		$posts = get_posts( array(
			'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ),
			'post_status' => array( 'draft', 'pending', 'publish', 'private' ),
			'author' => $user->ID,
			'posts_per_page' => 50,
			'paged' => max( 1, absint( $page ) ),
			'orderby' => 'ID',
			'order' => 'ASC',
		) );
		foreach ( $posts as $post ) {
			$data[] = array(
				'group_id' => 'he-v2-authored',
				'group_label' => __( 'Authored Encyclopedia and Research Records', 'homeopathy-encyclopedia' ),
				'item_id' => 'post-' . $post->ID,
				'data' => array(
					array( 'name' => __( 'Object type', 'homeopathy-encyclopedia' ), 'value' => $post->post_type ),
					array( 'name' => __( 'Title', 'homeopathy-encyclopedia' ), 'value' => $post->post_title ),
					array( 'name' => __( 'Status', 'homeopathy-encyclopedia' ), 'value' => $post->post_status ),
					array( 'name' => __( 'Created', 'homeopathy-encyclopedia' ), 'value' => $post->post_date_gmt ),
					array( 'name' => __( 'Modified', 'homeopathy-encyclopedia' ), 'value' => $post->post_modified_gmt ),
				),
			);
		}
		$reviews = $wpdb->get_results( $wpdb->prepare( 'SELECT object_type,object_id,scope,decision,conflict_declared,note,created_at FROM ' . HE_V2_Schema::table( 'reviews' ) . ' WHERE reviewer_id=%d ORDER BY id ASC LIMIT 500', $user->ID ), ARRAY_A );
		foreach ( $reviews as $index => $review ) {
			$data[] = array(
				'group_id' => 'he-v2-reviews',
				'group_label' => __( 'Encyclopedia Review Records', 'homeopathy-encyclopedia' ),
				'item_id' => 'review-' . $index,
				'data' => array_map( static function( $key, $value ) { return array( 'name' => $key, 'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); }, array_keys( $review ), array_values( $review ) ),
			);
		}
		$integrity = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,object_type,object_id,action_type,status,reason,evidence,created_at,updated_at FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE created_by=%d ORDER BY id ASC LIMIT 500', $user->ID ), ARRAY_A );
		foreach ( $integrity as $record ) {
			$data[] = array(
				'group_id' => 'he-v2-integrity',
				'group_label' => __( 'Correction and Retraction Requests', 'homeopathy-encyclopedia' ),
				'item_id' => 'integrity-' . $record['public_id'],
				'data' => array_map( static function( $key, $value ) { return array( 'name' => $key, 'value' => (string) $value ); }, array_keys( $record ), array_values( $record ) ),
			);
		}
		$bookmarks = $wpdb->get_results( $wpdb->prepare( 'SELECT b.concept_id,b.created_at,c.public_id,c.canonical_slug FROM ' . HE_V2_Schema::table( 'bookmarks' ) . ' b INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' c ON c.id=b.concept_id WHERE b.user_id=%d ORDER BY b.id ASC LIMIT 500', $user->ID ), ARRAY_A );
		foreach ( $bookmarks as $index => $record ) {
			$data[] = array(
				'group_id' => 'he-v2-bookmarks',
				'group_label' => __( 'Saved Encyclopedia Entries', 'homeopathy-encyclopedia' ),
				'item_id' => 'bookmark-' . $index,
				'data' => array_map( static function( $key, $value ) { return array( 'name' => $key, 'value' => (string) $value ); }, array_keys( $record ), array_values( $record ) ),
			);
		}
		$access = $wpdb->get_results( $wpdb->prepare( 'SELECT research_id,purpose,lawful_basis,status,expires_at,created_at,updated_at FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE requester_id=%d ORDER BY id ASC LIMIT 500', $user->ID ), ARRAY_A );
		foreach ( $access as $index => $record ) {
			$data[] = array(
				'group_id' => 'he-v2-dataset-access',
				'group_label' => __( 'Research Dataset Access Requests', 'homeopathy-encyclopedia' ),
				'item_id' => 'dataset-access-' . $index,
				'data' => array_map( static function( $key, $value ) { return array( 'name' => $key, 'value' => (string) $value ); }, array_keys( $record ), array_values( $record ) ),
			);
		}
		return array( 'data' => $data, 'done' => count( $posts ) < 50 );
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$removed = false;
		$retained = false;
		$messages = array();

		$wpdb->delete( HE_V2_Schema::table( 'dataset_access' ), array( 'requester_id' => $user->ID ), array( '%d' ) );
		$wpdb->delete( HE_V2_Schema::table( 'bookmarks' ), array( 'user_id' => $user->ID ), array( '%d' ) );
		$removed = true;

		$wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'integrity_actions' ) . ' SET created_by=0,evidence=%s WHERE created_by=%d', '[personal evidence removed after verified privacy request]', $user->ID ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'reviews' ) . ' SET reviewer_id=0,note=%s WHERE reviewer_id=%d', '[review note retained without reviewer identity for integrity]', $user->ID ) );
		$removed = true;
		$retained = true;
		$messages[] = __( 'Review and integrity decisions were retained in de-identified form to preserve published knowledge and institutional audit integrity.', 'homeopathy-encyclopedia' );

		$posts = get_posts( array(
			'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ),
			'post_status' => array( 'draft', 'pending', 'publish', 'private' ),
			'author' => $user->ID,
			'posts_per_page' => 100,
			'fields' => 'ids',
		) );
		foreach ( $posts as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ) );
				$retained = true;
				$messages[] = __( 'Published institutional knowledge was retained and detached from the user account.', 'homeopathy-encyclopedia' );
			} else {
				wp_delete_post( $post_id, true );
				$removed = true;
			}
		}
		HE_V2_Domain::emit_event( 'File06PrivacyErasureCompleted.v1', 'user', $user->ID, array( 'published_records_retained' => $retained ) );
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => array_values( array_unique( $messages ) ), 'done' => true );
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Homeopathy Encyclopedia and Research', 'homeopathy-encyclopedia' ),
				wp_kses_post( wpautop( __( 'The encyclopedia stores public canonical knowledge, version history, sources, reviews, corrections, research metadata, restricted dataset-access requests and security/audit events. Public DTOs use explicit allowlists. Drafts, rejected records, research access facts and private notes are not placed in public caches or search indexes. Published knowledge and integrity events may be retained after account erasure, with personal identity removed where feasible, because public citations, corrections and institutional research integrity must remain interpretable.', 'homeopathy-encyclopedia' ) ) )
			);
		}
	}

	public function cache_control() {
		if ( is_user_logged_in() && ( is_singular( HE_V2_Domain::ENTRY_TYPE ) || is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) ) {
			nocache_headers();
		}
		if ( is_admin() || current_user_can( HE_V2_Auth::CAP_EDIT ) || current_user_can( HE_V2_Auth::CAP_RESEARCH ) ) {
			nocache_headers();
		}
	}

	public function headers( $headers ) {
		if ( is_singular( HE_V2_Domain::ENTRY_TYPE ) || is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {
			$headers['X-Content-Type-Options'] = 'nosniff';
			$headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
			$headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';
		}
		return $headers;
	}
}

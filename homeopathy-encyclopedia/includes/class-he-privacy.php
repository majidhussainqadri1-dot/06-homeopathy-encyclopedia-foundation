<?php
/** WordPress privacy export, erasure, and policy integration. */

defined( 'ABSPATH' ) || exit;

final class HE_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
	}

	public function exporters( $items ) {
		$items['homeopathy-encyclopedia'] = array( 'exporter_friendly_name' => __( 'Encyclopedia activity', 'homeopathy-encyclopedia' ), 'callback' => array( $this, 'export' ) );
		return $items;
	}

	public function erasers( $items ) {
		$items['homeopathy-encyclopedia'] = array( 'eraser_friendly_name' => __( 'Encyclopedia activity', 'homeopathy-encyclopedia' ), 'callback' => array( $this, 'erase' ) );
		return $items;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$data = array();

		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT entry_id,created_at FROM {$wpdb->prefix}he_bookmarks WHERE user_id=%d ORDER BY created_at DESC", $user->ID ) ) as $row ) {
			$data[] = array( 'name' => __( 'Bookmarked entry', 'homeopathy-encyclopedia' ), 'value' => get_the_title( $row->entry_id ) . ' — ' . $row->created_at );
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}he_feedback WHERE user_id=%d ORDER BY created_at DESC", $user->ID ) ) as $row ) {
			$data[] = array( 'name' => ucfirst( $row->kind ), 'value' => get_the_title( $row->entry_id ) . ' — ' . $row->reason . ' — ' . $row->details . ' — ' . $row->status . ( $row->resolution_note ? ' — ' . $row->resolution_note : '' ) );
		}
		$entries = get_posts( array( 'post_type' => HE_Content::TYPE, 'post_status' => 'any', 'author' => $user->ID, 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
		foreach ( $entries as $entry ) {
			$data[] = array( 'name' => __( 'Authored encyclopedia entry', 'homeopathy-encyclopedia' ), 'value' => $entry->post_title . ' — ' . $entry->post_status . ' — ' . ( HE_Content::meta( $entry->ID, 'workflow_state' ) ?: 'legacy' ) );
		}
		$comments = get_comments( array( 'user_id' => $user->ID, 'post_type' => HE_Content::TYPE, 'status' => 'all', 'number' => 0 ) );
		foreach ( $comments as $comment ) {
			$data[] = array( 'name' => __( 'Encyclopedia comment', 'homeopathy-encyclopedia' ), 'value' => get_the_title( $comment->comment_post_ID ) . ' — ' . $comment->comment_content . ' — ' . $comment->comment_date_gmt );
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT entry_id,action,from_state,to_state,note,created_at FROM {$wpdb->prefix}he_audit_log WHERE actor_id=%d ORDER BY created_at DESC", $user->ID ) ) as $row ) {
			$data[] = array( 'name' => __( 'Editorial audit event', 'homeopathy-encyclopedia' ), 'value' => get_the_title( $row->entry_id ) . ' — ' . $row->action . ' — ' . $row->from_state . ' → ' . $row->to_state . ' — ' . $row->note . ' — ' . $row->created_at );
		}

		return array(
			'data' => $data ? array( array( 'group_id' => 'homeopathy-encyclopedia', 'group_label' => __( 'Homeopathy Encyclopedia', 'homeopathy-encyclopedia' ), 'item_id' => 'user-' . $user->ID, 'data' => $data ) ) : array(),
			'done' => true,
		);
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$removed = $wpdb->delete( $wpdb->prefix . 'he_bookmarks', array( 'user_id' => $user->ID ), array( '%d' ) ) > 0;
		$anonymized = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}he_feedback SET user_id=0 WHERE user_id=%d", $user->ID ) ) > 0;
		$authored = get_posts( array( 'post_type' => HE_Content::TYPE, 'post_status' => 'any', 'author' => $user->ID, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
		$audit_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}he_audit_log WHERE actor_id=%d", $user->ID ) );
		$messages = array();
		if ( $anonymized ) {
			$messages[] = __( 'Correction and report submissions were anonymized while their editorial substance was retained.', 'homeopathy-encyclopedia' );
		}
		if ( $authored ) {
			$messages[] = __( 'Authored entries in all workflow states are retained for publication provenance and editorial accountability.', 'homeopathy-encyclopedia' );
		}
		if ( $audit_count ) {
			$messages[] = __( 'Versioned audit records are retained for platform security and change-control integrity.', 'homeopathy-encyclopedia' );
		}
		$messages[] = __( 'WordPress core separately processes the user’s comments through its registered comment privacy handlers.', 'homeopathy-encyclopedia' );
		return array(
			'items_removed' => $removed || $anonymized,
			'items_retained' => (bool) $authored || $audit_count > 0,
			'messages' => $messages,
			'done' => true,
		);
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Homeopathy Encyclopedia Foundation', 'homeopathy-encyclopedia' ),
				wp_kses_post( '<p>' . __( 'The encyclopedia stores member bookmarks, correction and report submissions, authored-entry provenance, moderation decisions, and audit events. Public catalog HTML does not embed private bookmark state; that state is loaded after page display. Saved Knowledge and submission pages are marked non-cacheable. Retention and erasure decisions should be documented in the platform privacy policy.', 'homeopathy-encyclopedia' ) . '</p>' )
			);
		}
	}
}

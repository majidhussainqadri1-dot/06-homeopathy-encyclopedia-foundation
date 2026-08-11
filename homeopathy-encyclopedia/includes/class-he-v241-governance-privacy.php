<?php
/** Privacy lifecycle for File 06 v2.4.1 editorial-scope and reviewer-assignment metadata. */
defined( 'ABSPATH' ) || exit;

final class HE_V241_Governance_Privacy {
	const PAGE_SIZE = 50;

	public static function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	public static function exporters( $exporters ) {
		$exporters['he-v241-governance'] = array(
			'exporter_friendly_name' => __( 'Homeopathy Encyclopedia Editorial Governance', 'homeopathy-encyclopedia' ),
			'callback' => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['he-v241-governance'] = array(
			'eraser_friendly_name' => __( 'Homeopathy Encyclopedia Editorial Governance', 'homeopathy-encyclopedia' ),
			'callback' => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	private static function assigned_posts_page( $page ) {
		return get_posts( array(
			'post_type' => HE_V2_Domain::ENTRY_TYPE,
			'post_status' => 'any',
			'posts_per_page' => self::PAGE_SIZE,
			'paged' => max( 1, absint( $page ) ),
			'orderby' => 'ID',
			'order' => 'ASC',
			'fields' => 'ids',
			'meta_key' => HE_V241_Governance::META_REVIEW_ASSIGNMENTS,
		) );
	}

	public static function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$page = max( 1, absint( $page ) );
		$data = array();
		if ( 1 === $page ) {
			$scope = get_user_meta( $user->ID, HE_V241_Governance::META_EDITOR_TYPES, true );
			if ( is_array( $scope ) && $scope ) {
				$data[] = array(
					'group_id' => 'he-v241-editor-scope',
					'group_label' => __( 'Encyclopedia Editorial Scope', 'homeopathy-encyclopedia' ),
					'item_id' => 'user-' . $user->ID,
					'data' => array( array( 'name' => 'knowledge_types', 'value' => implode( ', ', array_map( 'sanitize_key', $scope ) ) ) ),
				);
			}
		}
		$post_ids = self::assigned_posts_page( $page );
		foreach ( $post_ids as $post_id ) {
			$assignments = get_post_meta( $post_id, HE_V241_Governance::META_REVIEW_ASSIGNMENTS, true );
			if ( ! is_array( $assignments ) ) { continue; }
			foreach ( $assignments as $scope => $assignment ) {
				if ( ! is_array( $assignment ) || ( absint( $assignment['reviewer_id'] ?? 0 ) !== (int) $user->ID && absint( $assignment['assigned_by'] ?? 0 ) !== (int) $user->ID ) ) { continue; }
				$data[] = array(
					'group_id' => 'he-v241-reviewer-assignment',
					'group_label' => __( 'Encyclopedia Reviewer Assignments', 'homeopathy-encyclopedia' ),
					'item_id' => 'post-' . absint( $post_id ) . '-' . sanitize_key( $scope ),
					'data' => array(
						array( 'name' => 'entry_public_id', 'value' => (string) ( HE_V2_Domain::concept_by_id( get_post_field( 'post_name', $post_id ), true )['public_id'] ?? '' ) ),
						array( 'name' => 'scope', 'value' => sanitize_key( $scope ) ),
						array( 'name' => 'role', 'value' => absint( $assignment['reviewer_id'] ?? 0 ) === (int) $user->ID ? 'reviewer' : 'assigner' ),
						array( 'name' => 'assigned_at', 'value' => sanitize_text_field( $assignment['assigned_at'] ?? '' ) ),
						array( 'name' => 'expires_at', 'value' => sanitize_text_field( $assignment['expires_at'] ?? '' ) ),
					),
				);
			}
		}
		return array( 'data' => $data, 'done' => count( $post_ids ) < self::PAGE_SIZE );
	}

	public static function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$uid = (int) $user->ID;
		if ( apply_filters( 'he_v2_privacy_legal_hold', false, $uid ) ) {
			return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'A documented legal or research-integrity hold is active.', 'homeopathy-encyclopedia' ) ), 'done' => true );
		}
		$page = max( 1, absint( $page ) );
		$removed = false;
		if ( 1 === $page && metadata_exists( 'user', $uid, HE_V241_Governance::META_EDITOR_TYPES ) ) {
			delete_user_meta( $uid, HE_V241_Governance::META_EDITOR_TYPES );
			$removed = true;
		}
		$post_ids = self::assigned_posts_page( $page );
		foreach ( $post_ids as $post_id ) {
			$assignments = get_post_meta( $post_id, HE_V241_Governance::META_REVIEW_ASSIGNMENTS, true );
			if ( ! is_array( $assignments ) ) { continue; }
			$changed = false;
			foreach ( $assignments as $scope => &$assignment ) {
				if ( ! is_array( $assignment ) ) { continue; }
				if ( absint( $assignment['reviewer_id'] ?? 0 ) === $uid ) { unset( $assignments[ $scope ] ); $changed = true; continue; }
				if ( absint( $assignment['assigned_by'] ?? 0 ) === $uid ) { $assignment['assigned_by'] = 0; $changed = true; }
			}
			unset( $assignment );
			if ( $changed ) {
				$removed = true;
				if ( $assignments ) { update_post_meta( $post_id, HE_V241_Governance::META_REVIEW_ASSIGNMENTS, $assignments ); }
				else { delete_post_meta( $post_id, HE_V241_Governance::META_REVIEW_ASSIGNMENTS ); }
			}
		}
		return array(
			'items_removed' => $removed,
			'items_retained' => false,
			'messages' => array(),
			'done' => count( $post_ids ) < self::PAGE_SIZE,
		);
	}
}

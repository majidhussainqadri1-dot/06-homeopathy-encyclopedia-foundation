<?php
/** File 06 v2.4 privacy export/erasure coverage for Future-18 records. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Future_Privacy {
	const PAGE_SIZE = 50;

	public static function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	public static function exporters( $exporters ) {
		$exporters['he-v24-future'] = array(
			'exporter_friendly_name' => __( 'Homeopathy Encyclopedia Future Knowledge Intelligence', 'homeopathy-encyclopedia' ),
			'callback' => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['he-v24-future'] = array(
			'eraser_friendly_name' => __( 'Homeopathy Encyclopedia Future Knowledge Intelligence', 'homeopathy-encyclopedia' ),
			'callback' => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	private static function export_rows( $group, $label, $prefix, $rows, $id_key = 'id' ) {
		$out = array();
		foreach ( $rows as $index => $row ) {
			$item = isset( $row[ $id_key ] ) ? (string) $row[ $id_key ] : (string) $index;
			$values = array();
			foreach ( $row as $key => $value ) {
				$values[] = array( 'name' => (string) $key, 'value' => is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value ) );
			}
			$out[] = array( 'group_id' => $group, 'group_label' => $label, 'item_id' => $prefix . $item, 'data' => $values );
		}
		return $out;
	}

	public static function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		global $wpdb;
		$page = max( 1, absint( $page ) ); $limit = self::PAGE_SIZE; $offset = ( $page - 1 ) * $limit;
		$data = array(); $more = false; $uid = absint( $user->ID );
		$queries = array(
			array( 'claims', 'he-v24-claims', __( 'Knowledge Claims', 'homeopathy-encyclopedia' ), 'claim-', 'created_by=%d OR reviewed_by=%d', array( $uid, $uid ) ),
			array( 'claim_evidence', 'he-v24-claim-evidence', __( 'Claim Evidence Contributions', 'homeopathy-encyclopedia' ), 'claim-evidence-', 'created_by=%d', array( $uid ) ),
			array( 'provenance', 'he-v24-provenance', __( 'Knowledge Provenance Participation', 'homeopathy-encyclopedia' ), 'provenance-', 'actor_id=%d', array( $uid ) ),
			array( 'researcher_ids', 'he-v24-researcher-ids', __( 'Researcher Identifier Mappings', 'homeopathy-encyclopedia' ), 'researcher-id-', 'user_id=%d OR reviewed_by=%d', array( $uid, $uid ) ),
			array( 'concept_mappings', 'he-v24-concept-mappings', __( 'Vocabulary Mapping Reviews', 'homeopathy-encyclopedia' ), 'mapping-', 'reviewed_by=%d', array( $uid ) ),
			array( 'watchlists', 'he-v24-watchlists', __( 'Knowledge Watchlists', 'homeopathy-encyclopedia' ), 'watch-', 'user_id=%d', array( $uid ) ),
			array( 'translations', 'he-v24-translations', __( 'Governed Knowledge Translations', 'homeopathy-encyclopedia' ), 'translation-', 'translator_id=%d OR reviewer_id=%d', array( $uid, $uid ) ),
		);
		foreach ( $queries as $query ) {
			list( $table_key, $group, $label, $prefix, $where, $params ) = $query;
			$table = HE_V24_Future_Schema::table( $table_key );
			$sql_params = $params; $sql_params[] = $limit + 1; $sql_params[] = $offset;
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d", $sql_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$more = $more || count( $rows ) > $limit;
			$data = array_merge( $data, self::export_rows( $group, $label, $prefix, array_slice( $rows, 0, $limit ) ) );
		}
		return array( 'data' => $data, 'done' => ! $more );
	}

	private static function deidentify( $table_key, $where, $params, $data, $limit = 250 ) {
		global $wpdb;
		$table = HE_V24_Future_Schema::table( $table_key );
		$sql_params = $params; $sql_params[] = absint( $limit );
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", $sql_params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = 0;
		foreach ( $ids as $id ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $id ) );
			$count += false !== $result ? 1 : 0;
		}
		return $count;
	}

	public static function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		global $wpdb;
		$uid = absint( $user->ID ); $removed = false; $retained = false; $messages = array();
		$hold = (bool) apply_filters( 'he_v2_privacy_legal_hold', false, $uid );
		if ( $hold ) {
			HE_V2_Domain::emit_event( 'File06FuturePrivacyErasureHeld.v1', 'user', $uid, array( 'legal_hold' => true ) );
			return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'A documented legal or research-integrity hold is active. Future knowledge records were retained for governed review.', 'homeopathy-encyclopedia' ) ), 'done' => true );
		}

		/* User-owned preference/identity mappings are deletable. */
		foreach ( array( 'watchlists' => 'user_id', 'researcher_ids' => 'user_id' ) as $table_key => $column ) {
			$table = HE_V24_Future_Schema::table( $table_key );
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column}=%d LIMIT 250", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$removed = $removed || (int) $result > 0;
		}

		/* Scholarly/integrity records remain interpretable but direct user IDs are removed. */
		if ( self::deidentify( 'claims', 'created_by=%d OR reviewed_by=%d', array( $uid, $uid ), array( 'created_by' => 0, 'reviewed_by' => 0 ) ) ) { $removed = true; $retained = true; }
		if ( self::deidentify( 'claim_evidence', 'created_by=%d', array( $uid ), array( 'created_by' => 0 ) ) ) { $removed = true; $retained = true; }
		/* v2.4 provenance hashes deliberately exclude actor_id so privacy de-identification does not invalidate the append-only content/hash chain. */
		if ( self::deidentify( 'provenance', 'actor_id=%d', array( $uid ), array( 'actor_id' => 0 ) ) ) { $removed = true; $retained = true; }
		if ( self::deidentify( 'researcher_ids', 'reviewed_by=%d', array( $uid ), array( 'reviewed_by' => 0 ) ) ) { $removed = true; $retained = true; }
		if ( self::deidentify( 'concept_mappings', 'reviewed_by=%d', array( $uid ), array( 'reviewed_by' => 0 ) ) ) { $removed = true; $retained = true; }
		if ( self::deidentify( 'translations', 'translator_id=%d OR reviewer_id=%d', array( $uid, $uid ), array( 'translator_id' => 0, 'reviewer_id' => 0 ) ) ) { $removed = true; $retained = true; }

		/* Future erasure must not re-identify a completed privacy request through the shared event object binding. */
		$core_events = HE_V2_Schema::table( 'events' );
		$event_objects = $wpdb->query( $wpdb->prepare( "UPDATE {$core_events} SET object_id='0' WHERE object_type='user' AND object_id=%s", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $event_objects ) {
			HE_V2_Schema::record_runtime_failure( 'future_privacy_event_object_deidentification_failed', 'Future privacy erasure could not de-identify user-bound event object identifiers.' );
		} elseif ( (int) $event_objects > 0 ) { $removed = true; $retained = true; }

		$remaining = 0;
		$checks = array(
			array( 'claims', 'created_by=%d OR reviewed_by=%d', array( $uid, $uid ) ),
			array( 'claim_evidence', 'created_by=%d', array( $uid ) ),
			array( 'provenance', 'actor_id=%d', array( $uid ) ),
			array( 'researcher_ids', 'user_id=%d OR reviewed_by=%d', array( $uid, $uid ) ),
			array( 'concept_mappings', 'reviewed_by=%d', array( $uid ) ),
			array( 'watchlists', 'user_id=%d', array( $uid ) ),
			array( 'translations', 'translator_id=%d OR reviewer_id=%d', array( $uid, $uid ) ),
		);
		foreach ( $checks as $check ) {
			$table = HE_V24_Future_Schema::table( $check[0] );
			$remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$check[1]}", $check[2] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$core_events} WHERE object_type='user' AND object_id=%s", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $retained ) { $messages[] = __( 'Knowledge integrity records were retained only in de-identified form so citations, review history and correction lineage remain interpretable.', 'homeopathy-encyclopedia' ); }
		$done = 0 === $remaining;
		if ( $done ) { HE_V2_Domain::emit_event( 'File06FuturePrivacyErasureCompleted.v1', 'privacy-request', 0, array( 'deidentified_integrity_records_retained' => $retained ) ); }
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => array_values( array_unique( $messages ) ), 'done' => $done );
	}
}

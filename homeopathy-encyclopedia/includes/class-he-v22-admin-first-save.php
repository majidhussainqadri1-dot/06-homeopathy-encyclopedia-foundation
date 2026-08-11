<?php
/** Replays canonical research admin fields only after first-save row materialization. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Admin_First_Save {
	public static function hooks() {
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'save_research_meta' ), 50, 2 );
	}

	public static function save_research_meta( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		/* Use the canonical Research Governance meta-box nonce. */
		if ( ! isset( $_POST['he_v2_research_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_research_nonce'] ) ), 'he_v2_save_research' ) ) {
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, $post_id, 'research-admin-first-save' ) ) {
			return;
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", absint( $post_id ) ), ARRAY_A );
		if ( ! $row ) {
			return;
		}
		/*
		 * HE_V2_Domain::on_save_research materializes a new row at priority 30 with
		 * row_version=1. Existing rows are handled by HE_V2_Admin at priority 20,
		 * which increments their version before this hook runs. This gate therefore
		 * prevents the historical double-write/double-version behavior on later saves.
		 */
		if ( 1 !== (int) $row['row_version'] ) {
			return;
		}

		$record_type = sanitize_key( wp_unslash( $_POST['he_v2_research_type'] ?? $row['record_type'] ) );
		if ( ! in_array( $record_type, array( 'proposal','protocol','publication','successful-case','dataset' ), true ) ) {
			$record_type = 'proposal';
		}
		$data_class = sanitize_key( wp_unslash( $_POST['he_v2_data_class'] ?? $row['data_class'] ) );
		if ( ! in_array( $data_class, array( 'public','restricted','highly-restricted' ), true ) ) {
			$data_class = 'restricted';
		}
		if ( 'dataset' === $record_type && 'public' === $data_class ) {
			$data_class = 'restricted';
		}

		$ethics = array(
			'review_required' => true,
			'approval_reference' => sanitize_text_field( wp_unslash( $_POST['he_v2_ethics_reference'] ?? '' ) ),
		);
		$consent = array(
			'verified' => ! empty( $_POST['he_v2_consent_verified'] ),
			'version' => 'admin-record-v1',
		);
		$case = array(
			'baseline' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_baseline'] ?? '' ) ),
			'intervention' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_intervention'] ?? '' ) ),
			'follow_up' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_follow_up'] ?? '' ) ),
			'adverse_events' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_adverse_events'] ?? '' ) ),
			'limitations' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_limitations'] ?? '' ) ),
		);
		$metadata = array(
			'description' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_dataset_metadata'] ?? '' ) ),
			'de_identified' => true,
			'access_default' => 'restricted',
		);

		$updated = $wpdb->update(
			$table,
			array(
				'record_type' => $record_type,
				'title' => $post->post_title,
				'question' => $post->post_excerpt,
				'protocol' => $post->post_content,
				'ethics_json' => wp_json_encode( $ethics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'consent_json' => wp_json_encode( $consent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'data_class' => $data_class,
				'case_anonymized' => ! empty( $_POST['he_v2_case_anonymized'] ) ? 1 : 0,
				'case_consent_verified' => ! empty( $_POST['he_v2_consent_verified'] ) ? 1 : 0,
				'case_tag' => 'successful-case' === $record_type ? 'کامیاب کیس' : '',
				'case_json' => wp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'metadata_json' => wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'row_version' => 2,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $row['id'], 'row_version' => 1 )
		);
		if ( false === $updated ) {
			HE_V2_Schema::record_runtime_failure( 'research_first_save_replay_failed', 'Research first-save governance fields could not be replayed after row materialization.' );
		}
		if ( 'successful-case' === $record_type ) {
			wp_set_object_terms( $post_id, array( 'کامیاب کیس' ), HE_V2_Domain::TAX_TOPIC, false );
		}
	}
}

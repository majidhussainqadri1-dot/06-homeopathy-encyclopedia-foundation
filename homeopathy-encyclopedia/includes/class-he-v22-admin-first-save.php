<?php
/** Replays canonical research admin fields after row materialization on a first save. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Admin_First_Save {
	public static function hooks() {
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'save_research_meta' ), 50, 2 );
	}

	public static function save_research_meta( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['he_v2_research_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_research_meta_nonce'] ) ), 'he_v2_research_meta' ) ) {
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, $post_id, 'research-admin-save' ) ) {
			return;
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", absint( $post_id ) ), ARRAY_A );
		if ( ! $row ) {
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
		$ethics = array( 'approval_reference' => sanitize_text_field( wp_unslash( $_POST['he_v2_ethics_reference'] ?? '' ) ) );
		$consent = array( 'verified' => ! empty( $_POST['he_v2_consent_verified'] ) );
		$case = json_decode( (string) $row['case_json'], true );
		$case = is_array( $case ) ? $case : array();
		foreach ( array( 'baseline','intervention','follow_up','adverse_events','limitations' ) as $field ) {
			if ( array_key_exists( 'he_v2_case_' . $field, $_POST ) ) {
				$case[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ 'he_v2_case_' . $field ] ) );
			}
		}
		$metadata = json_decode( (string) $row['metadata_json'], true );
		$metadata = is_array( $metadata ) ? $metadata : array();
		if ( array_key_exists( 'he_v2_dataset_metadata', $_POST ) ) {
			$metadata['description'] = sanitize_textarea_field( wp_unslash( $_POST['he_v2_dataset_metadata'] ) );
		}
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET record_type=%s,question=%s,protocol=%s,ethics_json=%s,consent_json=%s,data_class=%s,case_anonymized=%d,case_consent_verified=%d,case_tag=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",
			$record_type,
			sanitize_textarea_field( wp_unslash( $_POST['he_question'] ?? $row['question'] ) ),
			wp_kses_post( wp_unslash( $_POST['he_protocol'] ?? $row['protocol'] ) ),
			wp_json_encode( $ethics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $consent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			$data_class,
			! empty( $_POST['he_v2_case_anonymized'] ) ? 1 : 0,
			! empty( $_POST['he_v2_consent_verified'] ) ? 1 : 0,
			'successful-case' === $record_type ? 'کامیاب کیس' : '',
			wp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			(int) $row['id'], (int) $row['row_version']
		) );
		if ( 1 !== (int) $updated ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'research_first_save_cas_failed', 'Research first-save governance fields could not be persisted against the expected row version.' );
		}
	}
}

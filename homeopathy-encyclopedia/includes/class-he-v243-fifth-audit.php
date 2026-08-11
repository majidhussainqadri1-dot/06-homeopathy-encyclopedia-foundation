<?php
/** Fresh fifth audit hardening for File 06 v2.4.3 candidate. */
defined( 'ABSPATH' ) || exit;

final class HE_V243_Fifth_Audit {
	public static function hooks() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'normalize_research_admin_input' ), 4, 2 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'reconcile_research_case_topic' ), 190, 2 );
	}

	public static function normalize_research_admin_input( $data, $postarr ) {
		if ( ! is_admin() || HE_V2_Domain::RESEARCH_TYPE !== ( $data['post_type'] ?? '' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return $data;
		}
		if ( ! isset( $_POST['he_v2_research_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['he_v2_research_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, 'he_v2_save_research' ) ) {
			return $data;
		}

		$type = sanitize_key( wp_unslash( $_POST['he_v2_research_type'] ?? 'proposal' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed_types = array( 'proposal','protocol','publication','successful-case','dataset' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			wp_die( esc_html__( 'Invalid File 06 research record type.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 research validation', 'homeopathy-encyclopedia' ), array( 'response' => 422 ) );
		}
		$data_class = sanitize_key( wp_unslash( $_POST['he_v2_data_class'] ?? 'restricted' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $data_class, array( 'public','restricted','highly-restricted' ), true ) ) {
			wp_die( esc_html__( 'Invalid File 06 research data classification.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 research validation', 'homeopathy-encyclopedia' ), array( 'response' => 422 ) );
		}
		/* Dataset records remain restricted by default; public-safe metadata is projected separately. */
		if ( 'dataset' === $type && 'public' === $data_class ) {
			$_POST['he_v2_data_class'] = 'restricted'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return $data;
	}

	public static function reconcile_research_case_topic( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['he_v2_research_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_research_nonce'] ) ), 'he_v2_save_research' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, $post_id, 'research-case-topic-reconcile' ) ) {
			return;
		}
		global $wpdb;
		$type = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT record_type FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', absint( $post_id ) ) );
		if ( 'successful-case' === $type ) {
			wp_set_object_terms( $post_id, array( 'کامیاب کیس' ), HE_V2_Domain::TAX_TOPIC, false );
		} else {
			wp_remove_object_terms( $post_id, array( 'کامیاب کیس' ), HE_V2_Domain::TAX_TOPIC );
		}
	}
}

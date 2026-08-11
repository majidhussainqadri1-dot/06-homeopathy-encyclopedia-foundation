<?php
/** Fresh fifth audit hardening for File 06 v2.4.3 candidate. */
defined( 'ABSPATH' ) || exit;

final class HE_V243_Fifth_Audit {
	const PRIVACY_PAGE_SIZE = 50;

	public static function hooks() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'normalize_research_admin_input' ), 4, 2 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'reconcile_research_case_topic' ), 190, 2 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'privacy_exporters' ), 30 );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'privacy_erasers' ), 30 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard_research_creation' ), 349, 3 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard_dataset_access_target' ), 350, 3 );
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

	public static function guard_research_creation( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $response;
		}
		if ( '/' . HE_V2_API::NS . '/research' !== $request->get_route() ) {
			return $response;
		}
		$data = (array) $request->get_json_params();
		$type = sanitize_key( $data['record_type'] ?? 'proposal' );
		if ( ! in_array( $type, array( 'proposal','protocol','publication','successful-case','dataset' ), true ) ) {
			return new WP_Error( 'he_invalid_research_type', __( 'Invalid research record type.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$data_class = sanitize_key( $data['data_class'] ?? 'restricted' );
		if ( ! in_array( $data_class, array( 'public','restricted','highly-restricted' ), true ) ) {
			return new WP_Error( 'he_invalid_data_class', __( 'Research data class is invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( 'dataset' === $type && 'public' === $data_class ) {
			return new WP_Error( 'he_dataset_private_by_default', __( 'Dataset records must remain restricted or highly restricted; only approved metadata is public.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( 'successful-case' === $type ) {
			foreach ( array( 'baseline','intervention','follow_up','adverse_events','limitations' ) as $field ) {
				if ( '' === trim( (string) ( $data[ $field ] ?? '' ) ) ) {
					return new WP_Error( 'he_case_governance_failed', __( 'Successful cases require complete baseline, intervention, follow-up, adverse-events and limitations fields.', 'homeopathy-encyclopedia' ), array( 'status' => 422, 'field' => $field ) );
				}
			}
			if ( empty( $data['consent_verified'] ) || empty( $data['anonymized'] ) ) {
				return new WP_Error( 'he_case_governance_failed', __( 'Successful cases require verified consent and anonymization.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		return $response;
	}

	public static function guard_dataset_access_target( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( ! preg_match( '#^' . preg_quote( $prefix, '#' ) . '/datasets/(\d+)/access$#', $route, $match ) ) {
			return $response;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT id,post_id,record_type,status FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d',
			absint( $match[1] )
		), ARRAY_A );
		if ( ! $row || 'dataset' !== $row['record_type'] || 'published' !== $row['status'] || 'publish' !== get_post_status( (int) $row['post_id'] ) ) {
			return new WP_Error( 'he_dataset_not_found', __( 'Dataset metadata could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return $response;
	}

	public static function privacy_exporters( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();
		$exporters['he-v243-idempotency'] = array(
			'exporter_friendly_name' => __( 'Homeopathy Encyclopedia Request Safety Records', 'homeopathy-encyclopedia' ),
			'callback' => array( __CLASS__, 'export_idempotency' ),
		);
		return $exporters;
	}

	public static function privacy_erasers( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();
		$erasers['he-v243-idempotency'] = array(
			'eraser_friendly_name' => __( 'Homeopathy Encyclopedia Request Safety Records', 'homeopathy-encyclopedia' ),
			'callback' => array( __CLASS__, 'erase_idempotency' ),
		);
		return $erasers;
	}

	public static function export_idempotency( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$page = max( 1, absint( $page ) );
		$limit = self::PRIVACY_PAGE_SIZE;
		$offset = ( $page - 1 ) * $limit;
		$table = HE_V2_Schema::table( 'idempotency' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,operation,idempotency_key,request_hash,response_code,expires_at,created_at FROM {$table} WHERE actor_id=%d ORDER BY id ASC LIMIT %d OFFSET %d",
			absint( $user->ID ), $limit + 1, $offset
		), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array();
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$values = array();
			foreach ( $row as $key => $value ) {
				$values[] = array( 'name' => (string) $key, 'value' => (string) $value );
			}
			$data[] = array(
				'group_id' => 'he-v243-idempotency',
				'group_label' => __( 'Request Safety and Idempotency Records', 'homeopathy-encyclopedia' ),
				'item_id' => 'idempotency-' . absint( $row['id'] ),
				'data' => $values,
			);
		}
		return array( 'data' => $data, 'done' => count( $rows ) <= $limit );
	}

	public static function erase_idempotency( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$uid = absint( $user->ID );
		if ( (bool) apply_filters( 'he_v2_privacy_legal_hold', false, $uid ) ) {
			return array(
				'items_removed' => false,
				'items_retained' => true,
				'messages' => array( __( 'A documented legal or research-integrity hold is active.', 'homeopathy-encyclopedia' ) ),
				'done' => true,
			);
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'idempotency' );
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE actor_id=%d LIMIT 250", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE actor_id=%d", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'items_removed' => $deleted > 0,
			'items_retained' => false,
			'messages' => array(),
			'done' => 0 === $remaining,
		);
	}
}

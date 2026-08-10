<?php
/** Transparent correction/retraction lifecycle for encyclopedia and research records. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Integrity {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ), 95 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'enforce_apply_gate' ), 95, 3 );
	}

	public static function routes() {
		register_rest_route( HE_V2_API::NS, '/integrity/(?P<id>\\d+)/transition', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'transition' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW ); },
		) );
	}

	private static function mutation_guard( WP_REST_Request $request, $operation ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Domain::rate_allow( 'integrity:' . $operation . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function finish( $reservation, $result, $code = 200 ) {
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( ! empty( $reservation['replay'] ) ) {
			return new WP_REST_Response( $reservation['body'], $reservation['code'] );
		}
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			HE_V2_Domain::idempotent_finish( $reservation['id'], $status, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			return $result;
		}
		HE_V2_Domain::idempotent_finish( $reservation['id'], $code, $result );
		return new WP_REST_Response( $result, $code );
	}

	public static function transition( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'transition-' . absint( $request['id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::finish( $reservation, null );
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'integrity_actions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $request['id'] ) ), ARRAY_A );
		if ( ! $row ) {
			return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'The integrity record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
		}
		$data = (array) $request->get_json_params();
		$to = sanitize_key( $data['state'] ?? '' );
		$expected = absint( $data['expected_version'] ?? 0 );
		$transitions = array(
			'submitted' => array( 'triaged' ),
			'triaged' => array( 'under_review', 'rejected' ),
			'under_review' => array( 'accepted', 'rejected' ),
			'accepted' => array(),
			'rejected' => array( 'appealed' ),
			'applied' => array( 'appealed' ),
			'appealed' => array( 'under_review' ),
		);
		if ( ! in_array( $to, $transitions[ $row['status'] ] ?? array(), true ) ) {
			return self::finish( $reservation, new WP_Error( 'he_integrity_transition_forbidden', __( 'This integrity transition is not allowed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) );
		}
		if ( in_array( $to, array( 'accepted', 'rejected' ), true ) && ! trim( (string) ( $data['note'] ?? '' ) ) ) {
			return self::finish( $reservation, new WP_Error( 'he_integrity_decision_reason_required', __( 'A decision note is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		$appeal_status = 'appealed' === $to ? 'open' : ( 'under_review' === $to && 'appealed' === $row['status'] ? 'under_review' : $row['appeal_status'] );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,appeal_status=%s,decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $to, $appeal_status, get_current_user_id(), (int) $row['id'], $expected ) );
		if ( 1 !== (int) $updated ) {
			return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The integrity record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) );
		}
		HE_V2_Domain::emit_event( 'File06IntegrityStateChanged.v1', $row['object_type'], (int) $row['object_id'], array( 'action_id' => $row['public_id'], 'from' => $row['status'], 'to' => $to, 'note' => sanitize_textarea_field( $data['note'] ?? '' ) ) );
		return self::finish( $reservation, array( 'id' => (int) $row['id'], 'status' => $to, 'row_version' => $expected + 1 ) );
	}

	public static function enforce_apply_gate( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( ! preg_match( '#^' . preg_quote( $prefix, '#' ) . '/(?:integrity|research-integrity)/(\\d+)/apply$#', $route, $m ) ) {
			return $response;
		}
		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE id=%d', absint( $m[1] ) ) );
		if ( 'accepted' !== $status ) {
			return new WP_Error( 'he_integrity_acceptance_required', __( 'The integrity action must complete independent review and be accepted before it can be applied.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		return $response;
	}
}

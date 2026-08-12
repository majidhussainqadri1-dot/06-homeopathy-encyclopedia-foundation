<?php
/** Transparent correction/retraction lifecycle for encyclopedia and research records. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Integrity {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ), 95 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'enforce_apply_gate' ), 95, 3 );
	}

	public static function routes() {
		$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
		register_rest_route( HE_V2_API::NS, '/integrity/(?P<id>' . $uuid . ')/transition', array(
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
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		if ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], $reservation['code'] ); }
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $status, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			if ( ! $finished ) {
				return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request outcome could not be recorded safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
			}
			return $result;
		}
		$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $code, $result );
		if ( ! $finished ) {
			return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		return new WP_REST_Response( $result, $code );
	}

	public static function transition( WP_REST_Request $request ) {
		$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
		$reservation = self::mutation_guard( $request, 'transition-' . sanitize_key( $public_id ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::finish( $reservation, null );
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'integrity_actions' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id=%s", $public_id ), ARRAY_A );
		if ( ! $row ) {
			return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'The integrity record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
		}
		$post_id = 0;
		if ( 'concept' === $row['object_type'] ) {
			$concept = HE_V2_Domain::concept_by_id( (int) $row['object_id'], true );
			$post_id = $concept ? (int) $concept['post_id'] : 0;
		} elseif ( 'research' === $row['object_type'] ) {
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', (int) $row['object_id'] ) );
		}
		if ( ! $post_id ) {
			return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'The governed integrity subject is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
		}
		$object_permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW, $post_id, 'file06-integrity-transition' );
		if ( is_wp_error( $object_permission ) ) {
			return self::finish( $reservation, $object_permission );
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
		return self::finish( $reservation, array( 'id' => $row['public_id'], 'status' => $to, 'row_version' => $expected + 1 ) );
	}

	private static function apply_entry_atomic( WP_REST_Request $request, $action_id, $public_id ) {
		$allowed = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$reservation = self::mutation_guard( $request, 'secure-apply-entry-' . sanitize_key( $public_id ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::finish( $reservation, null );
		}
		global $wpdb;
		$data = (array) $request->get_json_params();
		$expected = absint( $data['expected_version'] ?? 0 );
		$actions = HE_V2_Schema::table( 'integrity_actions' );
		$concepts = HE_V2_Schema::table( 'concepts' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			HE_V2_Schema::record_runtime_failure( 'entry_integrity_transaction_start_failed', 'File 06 could not start the entry-integrity apply transaction.' );
			return self::finish( $reservation, new WP_Error( 'he_integrity_apply_failed', __( 'The accepted integrity action could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) );
		}
		try {
			$action = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$actions} WHERE id=%d FOR UPDATE", absint( $action_id ) ), ARRAY_A );
			if ( ! $action || 'concept' !== $action['object_type'] || 'accepted' !== $action['status'] || (int) $action['row_version'] !== $expected || ! hash_equals( strtolower( (string) $action['public_id'] ), strtolower( (string) $public_id ) ) ) {
				throw new RuntimeException( 'integrity-version-conflict' );
			}
			if ( ! in_array( $action['action_type'], array( 'correction', 'retraction' ), true ) ) {
				throw new RuntimeException( 'unsupported-action' );
			}
			$concept = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$concepts} WHERE id=%d FOR UPDATE", (int) $action['object_id'] ), ARRAY_A );
			if ( ! $concept || ! in_array( $concept['status'], array( 'published', 'corrected', 'retracted' ), true ) ) {
				throw new RuntimeException( 'concept-unavailable' );
			}
			$object_permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $concept['post_id'], 'file06-integrity-apply' );
			if ( is_wp_error( $object_permission ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::finish( $reservation, $object_permission );
			}
			$version_id = 0;
			if ( 'correction' === $action['action_type'] ) {
				$validation = HE_V2_Domain::validate_for_review( (int) $concept['id'] );
				if ( is_wp_error( $validation ) ) {
					throw new RuntimeException( 'corrected-content-invalid' );
				}
				$version_id = HE_V2_Domain::snapshot_version( (int) $concept['id'], $action['reason'], 'corrected', get_current_user_id() );
				if ( ! $version_id ) {
					throw new RuntimeException( 'snapshot-failed' );
				}
				$concept_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$concepts} SET status='published',current_version=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $version_id, (int) $concept['id'], (int) $concept['row_version'] ) );
			} else {
				$concept_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$concepts} SET status='retracted',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", (int) $concept['id'], (int) $concept['row_version'] ) );
			}
			$action_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$actions} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='accepted'", get_current_user_id(), (int) $action['id'], $expected ) );
			if ( 1 !== (int) $concept_updated || 1 !== (int) $action_updated ) {
				throw new RuntimeException( 'version-conflict' );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'integrity-commit-failed' );
			}
			HE_V22_Governance::reindex_concept_secure( (int) $concept['id'] );
			$event = 'retraction' === $action['action_type'] ? 'EncyclopediaEntryRetracted.v1' : 'EncyclopediaEntryCorrected.v1';
			HE_V2_Domain::emit_event( $event, 'concept', (int) $concept['id'], array( 'integrity_action' => $action['public_id'], 'reason' => $action['reason'], 'replacement_id' => (int) $action['replacement_object_id'], 'version_id' => $version_id ) );
			return self::finish( $reservation, array( 'applied' => true, 'action_id' => $action['public_id'], 'concept_id' => $concept['public_id'], 'version_id' => $version_id, 'record_status' => 'retraction' === $action['action_type'] ? 'retracted' : 'published' ) );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			$message = $error->getMessage();
			if ( 'integrity-commit-failed' === $message ) {
				HE_V2_Schema::record_runtime_failure( 'entry_integrity_commit_failed', 'File 06 could not confirm the entry-integrity transaction commit.' );
				return self::finish( $reservation, new WP_Error( 'he_integrity_apply_failed', __( 'The integrity outcome could not be confirmed safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) );
			}
			$code = 'unsupported-action' === $message ? 'he_integrity_action_unsupported' : 'he_integrity_apply_conflict';
			$status = 'unsupported-action' === $message ? 422 : 409;
			return self::finish( $reservation, new WP_Error( $code, __( 'The accepted integrity action could not be applied safely to the current record.', 'homeopathy-encyclopedia' ), array( 'status' => $status ) ) );
		}
	}

	public static function enforce_apply_gate( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/(' . $uuid . ')/apply$#', $route, $m ) ) {
			global $wpdb;
			$public_id = strtolower( sanitize_text_field( (string) $m[1] ) );
			$action_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE public_id=%s AND object_type=%s', $public_id, 'concept' ) );
			if ( ! $action_id ) { return new WP_Error( 'he_not_found', __( 'The integrity action is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
			return self::apply_entry_atomic( $request, $action_id, $public_id );
		}
		if ( ! preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research-integrity/(' . $uuid . ')/apply$#', $route, $m ) ) {
			return $response;
		}
		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE public_id=%s AND object_type=%s', strtolower( sanitize_text_field( (string) $m[1] ) ), 'research' ) );
		if ( 'accepted' !== $status ) {
			return new WP_Error( 'he_integrity_acceptance_required', __( 'The integrity action must complete independent review and be accepted before it can be applied.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		return $response;
	}
}

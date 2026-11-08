<?php
/**
 * File 06 v12: institutionally evidenced, immediate withdrawal of research case
 * reuse consent. No claim to authenticate the private data subject directly.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V12_Research_Consent {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'smc_file06_patient_consent_withdrawn_v1', array( __CLASS__, 'file00_subject_withdrew' ), 10, 3 );
	}

	public static function routes() {
		register_rest_route( HE_V2_API::NS, '/v12/research/(?P<id>[0-9a-fA-F-]{36})/consent/attest', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'attest_existing' ),
			'permission_callback' => static function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW ); },
		) );
		register_rest_route( HE_V2_API::NS, '/v12/research/(?P<id>[0-9a-fA-F-]{36})/consent/withdraw', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'withdraw' ),
			'permission_callback' => static function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH ); },
		) );
	}

	private static function fail( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	public static function withdraw( WP_REST_Request $request ) {
		if ( 'active' !== HE_V2_Schema::runtime_status() || get_option( HE_V2_Schema::OPTION_SAFE_MODE )
			|| ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Migration_Safety::ready() ) {
			return self::fail( 'he_v12_withdraw_unavailable', 'The canonical research database is unavailable for a consent withdrawal.', 503 );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) { return self::fail( 'he_v12_withdraw_nonce', 'A valid session nonce is required.', 403 ); }
		global $wpdb;
		$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $public_id ) ) {
			return self::fail( 'he_v12_withdraw_not_found', 'The governed consent record is not available.', 404 );
		}
		$table = HE_V2_Schema::table( 'research' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id=%s", $public_id ), ARRAY_A );
		if ( ! is_array( $existing ) || ! in_array( $existing['record_type'], array( 'successful-case','dataset' ), true ) ) {
			return self::fail( 'he_v12_withdraw_not_found', 'The governed consent-bearing research record was not found.', 404 );
		}
		$auth = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, (int) $existing['post_id'], 'file06-v12-research-consent-withdrawal' );
		if ( true !== $auth ) { return is_wp_error( $auth ) ? $auth : self::fail( 'he_v12_withdraw_not_allowed', 'Research object authorization is required.', 403 ); }
		$data = $request->get_json_params();
		$data = is_array( $data ) ? $data : array();
		$policy_version = sanitize_text_field( (string) ( $data['policy_version'] ?? '' ) );
		$locale = sanitize_text_field( (string) ( $data['locale'] ?? '' ) );
		$purpose = sanitize_key( (string) ( $data['purpose'] ?? '' ) );
		$evidence = sanitize_text_field( (string) ( $data['withdrawal_evidence_ref'] ?? '' ) );
		$expected = absint( $data['row_version'] ?? 0 );
		if ( ! $expected || '' === $policy_version || '' === $locale || '' === $purpose
			|| ! preg_match( '/^[A-Za-z0-9._:-]{8,120}$/', $evidence ) ) {
			return self::fail( 'he_v12_withdraw_proof_missing', 'Policy version, locale, purpose, immutable row version and a restricted withdrawal evidence reference are required.', 422 );
		}
		$key = (string) $request->get_header( 'Idempotency-Key' );
		if ( strlen( $key ) < 8 || strlen( $key ) > 128 ) { return self::fail( 'he_v12_withdraw_idempotency', 'Idempotency-Key is required.', 400 ); }
		$actor = get_current_user_id();
		if ( ! HE_V2_Domain::rate_allow( 'he-v12-consent:' . $actor, 10, MINUTE_IN_SECONDS ) ) {
			return self::fail( 'he_v12_withdraw_rate', 'Too many withdrawal commands.', 429 );
		}
		$reservation = HE_V2_Domain::idempotent_begin( $actor, 'file06-v12-research-consent-withdrawal', $key, $data );
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		if ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], (int) $reservation['code'] ); }

		$error = null;
		$response = null;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			$error = self::fail( 'he_v12_withdraw_transaction', 'Withdrawal transaction could not start.', 503 );
		} else {
			try {
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id=%s FOR UPDATE", $public_id ), ARRAY_A );
				if ( ! $row || (int) $row['row_version'] !== $expected ) { throw new RuntimeException( 'version-conflict' ); }
				$consent = json_decode( (string) $row['consent_json'], true );
				if ( ! is_array( $consent ) ) { throw new RuntimeException( 'consent-scope-mismatch' ); }
				$legacy = empty( $consent['locale'] ) || empty( $consent['purpose'] )
					|| empty( $consent['policy_version'] );
				$expected_purpose = 'successful-case' === $row['record_type']
					? 'patient-story-research' : 'research-dataset';
				if ( $legacy ) {
					// Older consent is never upgraded to verified. It may nevertheless
					// be REMOVED on an authorized, evidenced subject request.
					if ( true !== ( $data['legacy_unverified_acknowledged'] ?? false )
						|| 'legacy-unverified' !== $policy_version
						|| 'und' !== $locale || $purpose !== $expected_purpose ) {
						throw new RuntimeException( 'consent-scope-mismatch' );
					}
					$consent['legacy_unverified'] = true;
				} elseif ( (string) ( $consent['policy_version'] ?? '' ) !== $policy_version
					|| (string) $consent['locale'] !== $locale || (string) $consent['purpose'] !== $purpose ) {
					throw new RuntimeException( 'consent-scope-mismatch' );
				}
				if ( 'withdrawn' === ( $consent['state'] ?? '' ) ) { throw new RuntimeException( 'already-withdrawn' ); }
				$consent['state'] = 'withdrawn';
				$consent['verified'] = false;
				$consent['withdrawn_at_utc'] = gmdate( 'c' );
				$consent['withdrawn_by'] = $actor;
				$consent['withdrawal_evidence_ref'] = $evidence;
				$changed = $wpdb->query( $wpdb->prepare(
					"UPDATE {$table} SET consent_json=%s,case_consent_verified=0,status='retracted',
					row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",
					wp_json_encode( $consent ), (int) $row['id'], $expected
				) );
				if ( 1 !== (int) $changed ) { throw new RuntimeException( 'withdrawal-write-failed' ); }
				// Stop ordinary public WP post display before the withdrawal can be acknowledged.
				$private = wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'private' ), true );
				if ( is_wp_error( $private ) || ! $private ) { throw new RuntimeException( 'post-visibility-write-failed' ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'commit-failed' ); }
				$response = array( 'research_public_id' => $public_id, 'consent_state' => 'withdrawn',
					'status' => 'retracted', 'row_version' => $expected + 1,
					'downstream_revalidation_pending' => true );
			} catch ( Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				$code = $e->getMessage();
				$error = in_array( $code, array( 'version-conflict','already-withdrawn' ), true )
					? self::fail( 'he_v12_withdraw_version_conflict', 'Consent record changed. Reload its exact current state.', 409 )
					: ( 'consent-scope-mismatch' === $code
						? self::fail( 'he_v12_withdraw_scope_mismatch', 'This proof does not match the original consent policy, locale and purpose.', 422 )
						: self::fail( 'he_v12_withdraw_failed', 'Withdrawal was not committed atomically.', 503 ) );
				if ( 503 === (int) $error->get_error_data()['status'] ) {
					update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
					HE_V2_Schema::record_runtime_failure( 'v12_consent_withdraw_failed', 'Research consent withdrawal was rolled back because canonical state and WordPress visibility could not be committed together.' );
				}
			}
		}
		if ( $response ) {
			try {
				HE_V2_Domain::emit_event( 'ResearchRecordRetracted.v1', 'research', (int) $existing['id'],
					array( 'reason_code' => 'consent-withdrawn', 'research_public_id' => $public_id ) );
				if ( class_exists( 'HE_V24_Migration_Safety' ) && HE_V24_Migration_Safety::ready() ) {
					$impact = HE_V24_Future_Schema::queue_impact( 'research', $public_id, 'ResearchRecordRetracted.v1',
						array( 'research_public_id' => $public_id, 'consent_state' => 'withdrawn' ) );
					if ( (int) $impact < 6 ) { throw new RuntimeException( 'downstream-revalidation-incomplete' ); }
				}
			} catch ( Throwable $e ) {
				update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
				HE_V2_Schema::record_runtime_failure( 'v12_consent_downstream_failed', 'Consent was withdrawn but the downstream correction queue was not proven; mutations were paused.' );
				$error = self::fail( 'he_v12_withdraw_downstream_pending', 'Consent is withdrawn; downstream revalidation is incomplete and requires operator repair.', 503 );
			}
		}
		$status = $error ? (int) ( $error->get_error_data()['status'] ?? 503 ) : 200;
		$body = $error ? array( 'code' => $error->get_error_code(), 'consent_withdrawal_unverified' => true ) : $response;
		if ( ! HE_V2_Domain::idempotent_finish( $reservation['id'], $status, $body ) ) {
			return self::fail( 'he_v12_withdraw_idempotency_finalize', 'The command outcome could not be durably recorded; reconcile before retry.', 503 );
		}
		return $error ?: new WP_REST_Response( $response, 200 );
	}

	/**
	 * Independently review a File00 subject-issued receipt after the case
	 * already has an immutable File06 public UUID; never verify during draft
	 * creation while that UUID does not yet exist.
	 */
	public static function attest_existing( WP_REST_Request $request ) {
		if ( 'active' !== HE_V2_Schema::runtime_status()
			|| ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Migration_Safety::ready() ) {
			return self::fail( 'he_v12_case_receipt_schema', 'Case consent schema is unavailable.', 503 );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return self::fail( 'he_v12_case_receipt_nonce', 'Current session nonce required.', 403 );
		}
		global $wpdb;
		$uuid = strtolower( sanitize_text_field( (string) $request['id'] ) );
		$table = HE_V2_Schema::table( 'research' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id,public_id,post_id,record_type,status,row_version,created_by
			FROM {$table} WHERE public_id=%s LIMIT 1", $uuid
		), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error || ! $row || 'successful-case' !== $row['record_type']
			|| in_array( $row['status'], array( 'retracted','rejected' ), true ) ) {
			return self::fail( 'he_v12_case_receipt_missing', 'No eligible current case was found.', 404 );
		}
		$actor = get_current_user_id();
		if ( ! $actor || (int) $row['created_by'] === $actor
			|| true !== HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW,
				(int) $row['post_id'], 'file06-v12-case-independent-consent-review' ) ) {
			return self::fail( 'he_v12_case_receipt_independence', 'An independent authorized reviewer is required.', 403 );
		}
		$data = (array) $request->get_json_params();
		if ( absint( $data['row_version'] ?? 0 ) !== (int) $row['row_version'] ) {
			return self::fail( 'he_v12_case_receipt_conflict', 'The canonical case revision has changed.', 409 );
		}
		$data['research_public_id'] = $uuid;
		$data['consent_verified'] = true;
		$consent = HE_V12_Completion::consent_attestation( $data, $actor, true, 'successful-case' );
		if ( is_wp_error( $consent ) ) { return $consent; }
		$written = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET consent_json=%s,case_consent_verified=1,row_version=row_version+1,
			updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status NOT IN ('retracted','rejected')",
			wp_json_encode( $consent ), (int) $row['id'], (int) $row['row_version']
		) );
		if ( 1 !== (int) $written ) {
			return self::fail( 'he_v12_case_receipt_persist', 'The independently verified patient consent was not durably bound.', 503 );
		}
		try {
			HE_V2_Domain::emit_event( 'ResearchCaseConsentVerified.v1', 'research', (int) $row['id'],
				array( 'research_public_id'=>$uuid, 'policy_version'=>$consent['policy_version'], 'needs_review'=>true ) );
		} catch ( Throwable $error ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'v12_case_consent_event_failed', 'Case consent was stored but its event receipt could not be verified; publication is paused.' );
			return self::fail( 'he_v12_case_receipt_event_failed', 'Consent event reconciliation is required before publication.', 503 );
		}
		return rest_ensure_response( array( 'status'=>'verified-for-human-review',
			'research_public_id'=>$uuid, 'row_version'=>(int)$row['row_version']+1,
			'policy_version'=>$consent['policy_version'] ) );
	}

	/**
	 * File 00 callback: a subject withdrawal must end public reuse even if a
	 * research manager has not yet run the separate institutional withdrawal.
	 */
	public static function file00_subject_withdrew( $uuid, $subject_user_id, $receipt ) {
		if ( 'active' !== HE_V2_Schema::runtime_status() ) { return; }
		$uuid = strtolower( sanitize_text_field( (string) $uuid ) );
		$receipt = sanitize_text_field( (string) $receipt );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f-]{27}$/', $uuid ) || ! $receipt ) { return; }
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id,post_id,row_version,status,consent_json FROM {$table}
			WHERE public_id=%s AND record_type='successful-case' LIMIT 1", $uuid
		), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			return;
		}
		$consent = $row ? json_decode( (string) $row['consent_json'], true ) : null;
		if ( ! is_array( $consent ) || ! hash_equals( $receipt, (string) ( $consent['evidence_ref'] ?? '' ) )
			|| 'withdrawn' === ( $consent['state'] ?? '' ) ) { return; }
		$consent['state'] = 'withdrawn';
		$consent['verified'] = false;
		$consent['withdrawn_at_utc'] = gmdate( 'c' );
		$consent['withdrawal_channel'] = 'file-00-subject-self-service';
		$written = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET consent_json=%s,case_consent_verified=0,status='retracted',
			row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",
			wp_json_encode( $consent ), (int)$row['id'], (int)$row['row_version']
		) );
		if ( 1 !== (int)$written ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'v12_subject_withdrawal_cas_failed', 'File00 withdrawal could not invalidate File06 canonical research state; public cases are paused.' );
			return;
		}
		$private = wp_update_post( array( 'ID'=>(int)$row['post_id'],'post_status'=>'private' ), true );
		if ( is_wp_error( $private ) || ! $private ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			return;
		}
		try {
			HE_V2_Domain::emit_event( 'ResearchRecordRetracted.v1', 'research', (int)$row['id'],
				array( 'research_public_id'=>$uuid, 'reason_code'=>'subject-consent-withdrawn' ) );
			if ( class_exists( 'HE_V24_Migration_Safety' ) && HE_V24_Migration_Safety::ready() ) {
				$count = HE_V24_Future_Schema::queue_impact( 'research', $uuid, 'ResearchRecordRetracted.v1',
					array( 'research_public_id'=>$uuid,'reason_code'=>'subject-consent-withdrawn' ) );
				if ( (int)$count < 6 ) { throw new RuntimeException( 'impact-fanout-incomplete' ); }
			}
		} catch ( Throwable $error ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'v12_subject_withdrawal_reconciliation', 'Canonical case was retracted; downstream invalidation requires operator reconciliation.' );
		}
	}

}

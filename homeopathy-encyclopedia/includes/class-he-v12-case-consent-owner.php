<?php
/**
 * File 06 v12 — canonical clinical-subject relation and approved case consent policy.
 * No clinical subject identity or case linkage is inferred from author, post title,
 * user-entered checkbox, or external file names.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V12_Case_Consent_Owner {
	const POLICY_OPTION = 'he_v12_patient_case_policy_v1';
	const RELATION_PREFIX = 'he_v12_patient_case_relation_';

	public static function hooks() {
		add_filter( 'he_v12_file06_patient_relation_v1', array( __CLASS__, 'relation_claim' ), 10, 3 );
		add_filter( 'he_v12_file06_consent_policy_v1', array( __CLASS__, 'approved_policy' ), 10, 1 );
		add_filter( 'he_v12_file06_concept_assertion_v1', array( __CLASS__, 'concept_assertion' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Exact canonical owner assertion; title/category matching is prohibited. */
	public static function concept_assertion( $value, $public_id, $version ) {
		$public_id = strtolower( sanitize_text_field( (string) $public_id ) );
		if ( 'active' !== HE_V2_Schema::runtime_status()
			|| ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $public_id )
			|| absint( $version ) < 1 ) { return null; }
		$row = HE_V2_Domain::concept_by_id( $public_id, true );
		if ( ! is_array( $row ) || ! hash_equals( $public_id, strtolower( (string) ( $row['public_id'] ?? '' ) ) )
			|| 'published' !== ( $row['status'] ?? '' )
			|| ! HE_V2_Domain::is_public_concept( $row ) ) { return null; }
		$dto = HE_V2_Domain::public_dto( $row );
		if ( ! is_array( $dto ) || ! empty( $dto['is_historical'] )
			|| (int) ( $dto['version'] ?? 0 ) !== absint( $version )
			|| (int) ( $dto['current_version'] ?? 0 ) !== absint( $version ) ) { return null; }
		return array( 'owner_file'=>'file-06','public_id'=>$public_id,
			'published'=>true,'version'=>absint( $version ),
			'canonical_url'=>(string) ( $dto['canonical_url'] ?? '' ) );
	}

	private static function problem( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function case_row( $uuid ) {
		if ( 'active' !== HE_V2_Schema::runtime_status()
			|| ! is_string( $uuid )
			|| ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
			return null;
		}
		global $wpdb;
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT id,post_id,public_id,record_type,status,row_version,created_by FROM ' .
			HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s LIMIT 1',
			strtolower( $uuid )
		), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error || ! is_array( $row )
			|| 'successful-case' !== $row['record_type']
			|| in_array( $row['status'], array( 'retracted','rejected' ), true ) ) { return null; }
		return $row;
	}

	private static function relation_key( $uuid ) {
		return self::RELATION_PREFIX . hash( 'sha256', strtolower( $uuid ) );
	}

	public static function relation_claim( $claim, $subject_user_id, $research_public_id ) {
		$subject_user_id = absint( $subject_user_id );
		$row = self::case_row( (string) $research_public_id );
		if ( ! $row || ! $subject_user_id || ! HE_V2_Auth::membership_allowed( $subject_user_id )
			|| get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) { return null; }
		$binding = get_option( self::relation_key( $row['public_id'] ), null );
		if ( ! is_array( $binding ) || true !== ( $binding['approved'] ?? false )
			|| ! hash_equals( (string) $row['public_id'], (string) ( $binding['research_public_id'] ?? '' ) )
			|| $subject_user_id !== (int) ( $binding['subject_user_id'] ?? 0 )
			|| (int) ( $binding['attested_by'] ?? 0 ) < 1
			|| (int) ( $binding['approved_by'] ?? 0 ) < 1
			|| (int) $binding['attested_by'] === (int) $binding['approved_by']
			|| (int) $binding['attested_by'] === $subject_user_id
			|| (int) $binding['approved_by'] === $subject_user_id
			|| (int) ( $binding['relation_version'] ?? 0 ) < 1 ) {
			return null;
		}
		return array(
			'verified' => true, 'owner_file' => 'file-06',
			'research_public_id' => (string) $row['public_id'],
			'subject_user_id' => $subject_user_id,
			'relation_version' => (int) $binding['relation_version'],
			'attested_by' => (int) $binding['attested_by'],
			'approved_by' => (int) $binding['approved_by'],
		);
	}

	public static function approved_policy( $source, $locale ) {
		$locale = sanitize_text_field( (string) $locale );
		$policy = get_option( self::POLICY_OPTION, null );
		if ( ! is_array( $policy ) || true !== ( $policy['approved'] ?? false )
			|| ! hash_equals( $locale, (string) ( $policy['locale'] ?? '' ) )
			|| empty( $policy['version'] ) || empty( $policy['text'] )
			|| ! hash_equals( hash( 'sha256', (string) $policy['text'] ), (string) ( $policy['text_hash'] ?? '' ) )
			|| (int) ( $policy['approved_by'] ?? 0 ) < 1 ) { return null; }
		return array(
			'owner_file' => 'file-06', 'approved' => true,
			'version' => $policy['version'], 'locale' => $policy['locale'],
			'text' => $policy['text'], 'text_hash' => $policy['text_hash'],
			'purpose' => 'patient-story-research',
		);
	}

	public static function routes() {
		$base = HE_V2_API::NS . '/v12/research';
		register_rest_route( HE_V2_API::NS, '/v12/research/case-consent-policy', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'read_policy' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'approve_policy' ),
				'permission_callback' => static function() { return HE_V2_Auth::is_founder() && HE_V2_Auth::membership_allowed(); } ),
		) );
		register_rest_route( HE_V2_API::NS, '/v12/research/(?P<id>[0-9a-fA-F-]{36})/subject-relation/attest', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'attest_relation' ),
			'permission_callback' => static function( $request ) {
				$row = self::case_row( (string) $request['id'] );
				return $row && true === HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH,
					(int) $row['post_id'], 'file06-v12-subject-binding-attest' );
			},
		) );
		register_rest_route( HE_V2_API::NS, '/v12/research/(?P<id>[0-9a-fA-F-]{36})/subject-relation/approve', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'approve_relation' ),
			'permission_callback' => static function( $request ) {
				$row = self::case_row( (string) $request['id'] );
				return $row && true === HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW,
					(int) $row['post_id'], 'file06-v12-subject-binding-independent-review' );
			},
		) );
	}

	private static function nonce( $request ) {
		return HE_V2_Auth::require_nonce( $request )
			? true : self::problem( 'he_v12_case_nonce', 'A valid WordPress REST nonce is required.', 403 );
	}

	public static function read_policy( $request ) {
		$locale = sanitize_text_field( (string) $request->get_param( 'locale' ) );
		$policy = self::approved_policy( null, $locale );
		return $policy ?: self::problem( 'he_v12_case_policy_unapproved', 'An approved consent policy for this language is not available.', 503 );
	}

	public static function approve_policy( $request ) {
		if ( true !== self::nonce( $request ) ) { return self::nonce( $request ); }
		$data = (array) $request->get_json_params();
		$version = sanitize_text_field( (string) ( $data['version'] ?? '' ) );
		$locale = sanitize_text_field( (string) ( $data['locale'] ?? '' ) );
		$text = trim( (string) ( $data['text'] ?? '' ) );
		$legal_ref = sanitize_text_field( (string) ( $data['approval_reference'] ?? '' ) );
		if ( ! preg_match( '/^[A-Za-z0-9._-]{6,32}$/', $version )
			|| ! preg_match( '/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale )
			|| strlen( $text ) < 100 || strlen( $text ) > 8000
			|| ! preg_match( '/^[A-Za-z0-9._:-]{8,120}$/', $legal_ref ) ) {
			return self::problem( 'he_v12_case_policy_invalid', 'Approval requires a bounded lawful consent text, version, locale and approval evidence.', 422 );
		}
		$now = gmdate( 'c' );
		$row = array( 'approved'=>true,'version'=>$version,'locale'=>$locale,'text'=>$text,
			'text_hash'=>hash('sha256',$text),'approved_by'=>get_current_user_id(),
			'approved_at_utc'=>$now,'approval_reference'=>$legal_ref );
		if ( false === update_option( self::POLICY_OPTION, $row, false )
			&& get_option( self::POLICY_OPTION ) !== $row ) {
			return self::problem( 'he_v12_case_policy_store_failed', 'The approved policy could not be persisted.', 503 );
		}
		return rest_ensure_response( array( 'approved'=>true,'version'=>$version,'locale'=>$locale,
			'text_hash'=>$row['text_hash'],'approved_by'=>$row['approved_by'] ) );
	}

	public static function attest_relation( $request ) {
		if ( true !== self::nonce( $request ) ) { return self::nonce( $request ); }
		$row = self::case_row( (string) $request['id'] );
		if ( ! $row ) { return self::problem( 'he_v12_case_unavailable', 'Case not available.', 404 ); }
		$data = (array) $request->get_json_params();
		$subject = absint( $data['subject_user_id'] ?? 0 );
		$proof_ref = sanitize_text_field( (string) ( $data['clinical_subject_match_reference'] ?? '' ) );
		$actor = get_current_user_id();
		if ( ! $subject || $subject === $actor || ! HE_V2_Auth::membership_allowed( $subject )
			|| ! preg_match( '/^[A-Za-z0-9._:-]{8,120}$/', $proof_ref )
			|| absint( $data['row_version'] ?? 0 ) !== (int) $row['row_version'] ) {
			return self::problem( 'he_v12_case_binding_invalid', 'Current case version, eligible distinct subject and audited clinical match proof are required.', 422 );
		}
		$key = self::relation_key( (string) $row['public_id'] );
		$previous = get_option( $key, null );
		$generation = is_array( $previous ) ? (int) ( $previous['relation_version'] ?? 0 ) + 1 : 1;
		$value = array( 'research_public_id'=>(string) $row['public_id'], 'subject_user_id'=>$subject,
			'attested_by'=>$actor,'attested_at_utc'=>gmdate('c'),'clinical_match_reference'=>$proof_ref,
			'relation_version'=>$generation,'approved'=>false,'approved_by'=>0,'approved_at_utc'=>null );
		if ( false === update_option( $key, $value, false ) && get_option( $key ) !== $value ) {
			return self::problem( 'he_v12_case_binding_write_failed', 'Clinical subject attestation could not be saved.', 503 );
		}
		return rest_ensure_response( array( 'relation_version'=>$generation,'status'=>'awaiting-independent-review' ) );
	}

	public static function approve_relation( $request ) {
		if ( true !== self::nonce( $request ) ) { return self::nonce( $request ); }
		$row = self::case_row( (string) $request['id'] );
		if ( ! $row ) { return self::problem( 'he_v12_case_unavailable', 'Case not available.', 404 ); }
		$key = self::relation_key( (string) $row['public_id'] );
		$value = get_option( $key, null );
		$reviewer = get_current_user_id();
		$data = (array) $request->get_json_params();
		if ( ! is_array( $value ) || true === ( $value['approved'] ?? false )
			|| absint( $data['relation_version'] ?? 0 ) !== (int) ( $value['relation_version'] ?? 0 )
			|| $reviewer < 1 || $reviewer === (int) ( $value['attested_by'] ?? 0 )
			|| $reviewer === (int) ( $value['subject_user_id'] ?? 0 )
			|| ! HE_V2_Auth::membership_allowed( (int) ( $value['subject_user_id'] ?? 0 ) ) ) {
			return self::problem( 'he_v12_case_binding_review_denied', 'Current independent review and an eligible subject are required.', 409 );
		}
		$value['approved'] = true;
		$value['approved_by'] = $reviewer;
		$value['approved_at_utc'] = gmdate( 'c' );
		if ( false === update_option( $key, $value, false ) && get_option( $key ) !== $value ) {
			return self::problem( 'he_v12_case_binding_approval_failed', 'Independent subject-link approval could not be committed.', 503 );
		}
		return rest_ensure_response( array( 'status'=>'independently-approved',
			'relation_version'=>(int) $value['relation_version'] ) );
	}
}

<?php
/** File 06 v2.4 Future-18 REST, authorization, DTO and concurrency hardening. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Future_API {
	const MAX_DUPLICATE_CANDIDATES = 300;

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 240 );
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'extend_contract' ), 240 );
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance' ), 240 );
	}

	public static function register_routes() {
		$ns = HE_V2_API::NS;
		self::route( $ns, '/future/claims', 'GET', 'rest_claims', 'read' );
		self::route( $ns, '/future/claims', 'POST', 'rest_claims_write', HE_V2_Auth::CAP_EDIT );
		self::route( $ns, '/future/claims/(?P<id>[0-9a-fA-F-]{36})/evidence', 'POST', 'rest_claim_evidence', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/claims/(?P<id>[0-9a-fA-F-]{36})/review', 'POST', 'rest_claim_review', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/provenance/(?P<type>[a-z0-9_-]+)/(?P<id>[a-zA-Z0-9_-]+)', 'GET', 'rest_provenance', 'read' );
		self::route( $ns, '/future/external/lookup', 'POST', 'rest_external_lookup', HE_V2_Auth::CAP_RESEARCH );
		self::route( $ns, '/future/retraction-watch', 'POST', 'rest_retraction_watch', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/mappings', 'POST', 'rest_mapping', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/researcher-identities', 'POST', 'rest_researcher_identity', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/duplicates/scan', 'POST', 'rest_duplicate_scan', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/graph/(?P<id>\\d+)', 'GET', 'rest_graph', 'read' );
		self::route( $ns, '/future/time-machine/(?P<id>\\d+)', 'GET', 'rest_time_machine', 'read' );
		self::route( $ns, '/future/impact/(?P<id>\\d+)', 'POST', 'rest_impact', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/freshness/(?P<id>\\d+)', 'GET', 'rest_freshness', 'read' );
		self::route( $ns, '/future/gaps', 'GET', 'rest_gaps', HE_V2_Auth::CAP_RESEARCH );
		self::route( $ns, '/future/citations/(?P<id>\\d+)/(?P<format>[a-z0-9_-]+)', 'GET', 'rest_citations', 'read' );
		self::route( $ns, '/future/watchlist', 'GET', 'rest_watchlist', 'member' );
		self::route( $ns, '/future/watchlist', 'POST', 'rest_watchlist_write', 'member' );
		self::route( $ns, '/future/translations/(?P<id>\\d+)', 'GET', 'rest_translations', 'read' );
		self::route( $ns, '/future/translations/(?P<id>\\d+)', 'POST', 'rest_translation_write', HE_V2_Auth::CAP_EDIT );
		self::route( $ns, '/future/translations/(?P<id>\\d+)/review', 'POST', 'rest_translation_review', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/translations/(?P<id>\\d+)/publish', 'POST', 'rest_translation_publish', HE_V2_Auth::CAP_PUBLISH );
		/* Canonical public-ID surfaces for public Future intelligence reads. Legacy numeric reads remain blocked by HE_V24_Public_Provenance. */
		self::route( $ns, '/future/public/claims/(?P<id>[a-fA-F0-9-]{36})', 'GET', 'rest_claims', 'read' );
		self::route( $ns, '/future/public/graph/(?P<id>[a-fA-F0-9-]{36})', 'GET', 'rest_graph', 'read' );
		self::route( $ns, '/future/public/time-machine/(?P<id>[a-fA-F0-9-]{36})', 'GET', 'rest_time_machine', 'read' );
		self::route( $ns, '/future/public/freshness/(?P<id>[a-fA-F0-9-]{36})', 'GET', 'rest_freshness', 'read' );
		self::route( $ns, '/future/public/citations/(?P<id>[a-fA-F0-9-]{36})/(?P<format>[a-z0-9_-]+)', 'GET', 'rest_citations', 'read' );
		self::route( $ns, '/future/command-center', 'GET', 'rest_command_center', HE_V2_Auth::CAP_REVIEW );
	}

	private static function route( $ns, $path, $method, $callback, $permission ) {
		register_rest_route( $ns, $path, array(
			'methods' => $method,
			'callback' => array( __CLASS__, $callback ),
			'permission_callback' => static function() use ( $permission ) {
				if ( 'read' === $permission ) {
					return true;
				}
				if ( 'member' === $permission ) {
					if ( ! is_user_logged_in() ) {
						return new WP_Error( 'he_auth_required', __( 'Authentication is required.', 'homeopathy-encyclopedia' ), array( 'status' => 401 ) );
					}
					if ( ! HE_V2_Auth::provider_ready() ) {
						return new WP_Error( 'he_identity_provider_unavailable', __( 'The platform identity service is temporarily unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
					}
					return HE_V2_Auth::membership_allowed() ? true : new WP_Error( 'he_forbidden', __( 'This account is not eligible for the requested action.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
				}
				return HE_V2_Auth::rest_permission( $permission );
			},
		), true );
	}

	private static function mutation_guard( WP_REST_Request $request, $operation, $capability = '', $member_only = false ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Public reading remains available, but mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( $capability ) {
			$allowed = HE_V2_Auth::rest_permission( $capability );
			if ( is_wp_error( $allowed ) ) { return $allowed; }
		} elseif ( $member_only && ! HE_V2_Auth::membership_allowed() ) {
			return new WP_Error( 'he_forbidden', __( 'This account is not eligible for the requested action.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Domain::rate_allow( 'v24:' . sanitize_key( $operation ) . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function mutation_finish( $reservation, $result, $success_code = 200 ) {
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
		$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $result );
		if ( ! $finished ) {
			return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		return new WP_REST_Response( $result, $success_code );
	}

	private static function request_data( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : $request->get_params();
	}

	private static function concept_by_public_id( $identifier, $public_only = false ) {
		global $wpdb;$id=strtolower(sanitize_text_field((string)$identifier));
		if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$id)){return null;}
		$where='public_id=%s';if($public_only){$where.=" AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0";}
		return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.HE_V2_Schema::table('concepts').' WHERE '.$where,$id),ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function public_read_concept( WP_REST_Request $request ) {
		global $wpdb;
		$identifier = trim( (string) $request->get_param( 'id' ) );
		if ( '' === $identifier ) { $identifier = trim( (string) $request->get_param( 'concept_id' ) ); }
		if ( '' === $identifier ) { return null; }
		if ( ctype_digit( $identifier ) ) { return HE_V24_Future_Schema::concept_row( absint( $identifier ), true ); }
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $identifier ) ) { return null; }
		$public_id = strtolower( $identifier );
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE public_id=%s AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0",
			$public_id
		), ARRAY_A );
	}

	public static function rest_claims( WP_REST_Request $request ) {
		$concept = self::public_read_concept( $request );
		if ( ! $concept ) {
			return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( HE_V24_Future_Schema::public_claims( (int) $concept['id'] ) );
	}

	public static function rest_claims_write( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-claim-save', HE_V2_Auth::CAP_EDIT );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb;
		$data = self::request_data( $request );
		$concept = self::concept_by_public_id( $data['concept_id'] ?? '', false );
		if ( ! $concept ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$key = sanitize_key( $data['claim_key'] ?? '' );
		$text = wp_kses_post( (string) ( $data['claim_text'] ?? '' ) );
		if ( ! $key || '' === trim( wp_strip_all_tags( $text ) ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_claim_invalid', __( 'claim_key and claim_text are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$version_number = absint( $data['version_number'] ?? 0 );
		$version_id = $version_number ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE concept_id=%d AND version_number=%d', (int) $concept['id'], $version_number ) ) : (int) $concept['current_version'];
		if ( ! $version_id || ! HE_V24_Future_Schema::version_belongs( $concept['id'], $version_id ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_version_invalid', __( 'The selected public knowledge version does not belong to this concept.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$confidence = max( 0, min( 1, (float) ( $data['confidence'] ?? 0 ) ) );
		$table = HE_V24_Future_Schema::table( 'claims' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND claim_key=%s", $concept['id'], $key ), ARRAY_A );
		$now = current_time( 'mysql', true );
		if ( $existing ) {
			$expected = absint( $data['row_version'] ?? 0 );
			if ( ! $expected || $expected !== (int) $existing['row_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The claim changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET claim_text=%s,version_id=%d,confidence=%f,review_status='pending',reviewed_by=0,row_version=row_version+1,updated_at=%s WHERE id=%d AND row_version=%d", $text, $version_id, $confidence, $now, $existing['id'], $expected ) );
			if ( 1 !== (int) $updated ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The claim changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
			$id = (int) $existing['id'];
		} else {
			$ok = $wpdb->insert( $table, array( 'concept_id' => $concept['id'], 'version_id' => $version_id, 'public_id' => wp_generate_uuid4(), 'claim_key' => $key, 'claim_text' => $text, 'claim_state' => 'active', 'evidence_state' => 'ungraded', 'confidence' => $confidence, 'review_status' => 'pending', 'reviewed_by' => 0, 'row_version' => 1, 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) );
			if ( ! $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_claim_write_failed', __( 'The claim could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
			$id = (int) $wpdb->insert_id;
		}
		HE_V24_Future_Schema::append_provenance( 'claim', (string) $id, 'claim.saved', '', array( 'concept_id' => (int) $concept['id'], 'version_id' => $version_id, 'claim_key' => $key ) );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,public_id,concept_id,version_id,claim_key,claim_state,evidence_state,confidence,review_status,row_version,updated_at FROM {$table} WHERE id=%d", $id ), ARRAY_A );
		return self::mutation_finish( $reservation, $row, $existing ? 200 : 201 );
	}

	private static function external_evidence_token( $provider, $external_id ) {
		$provider = sanitize_key( $provider );
		$external_id = sanitize_text_field( $external_id );
		return $provider && $external_id ? $provider . '|' . rawurlencode( $external_id ) : '';
	}

	public static function external_evidence_token_parts( $token ) {
		$token = (string) $token; $pos = strpos( $token, '|' );
		if ( false === $pos ) { return null; }
		$provider = sanitize_key( substr( $token, 0, $pos ) );
		$external_id = sanitize_text_field( rawurldecode( substr( $token, $pos + 1 ) ) );
		if ( ! $provider || ! $external_id || ! HE_V24_Future_Schema::validate_external_id( $provider, $external_id ) ) { return null; }
		return array( 'provider' => $provider, 'external_id' => $external_id );
	}

	public static function rest_claim_evidence( WP_REST_Request $request ) {
		$reservation=self::mutation_guard($request,'future-claim-evidence-'.absint($request['id']),HE_V2_Auth::CAP_REVIEW);
		if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,null,200);}
		global $wpdb;$data=self::request_data($request);$expected=absint($data['row_version']??0);
		if(!$expected){return self::mutation_finish($reservation,new WP_Error('he_version_conflict',__('The claim version loaded for editing is required.','homeopathy-encyclopedia'),array('status'=>409)));}
		if(false===$wpdb->query('START TRANSACTION')){return self::mutation_finish($reservation,new WP_Error('he_future_evidence_transaction_failed',__('The evidence change could not start safely.','homeopathy-encyclopedia'),array('status'=>503)));}
		try{
			$claim=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.HE_V24_Future_Schema::table('claims').' WHERE public_id=%s FOR UPDATE',strtolower(sanitize_text_field((string)$request['id']))),ARRAY_A);
			if(!$claim){throw new RuntimeException('not-found');} if($expected!==(int)$claim['row_version']){throw new RuntimeException('version-conflict');}
			$concept=HE_V24_Future_Schema::concept_row($claim['concept_id'],false); if(!$concept||!$claim['version_id']||(int)$claim['version_id']!==(int)$concept['current_version']){throw new RuntimeException('version-gate');}
			$relation=sanitize_key($data['relation']??''); if(!in_array($relation,array('supports','contradicts','uncertain','historical'),true)){throw new RuntimeException('relation-invalid');}
			$reference_token=sanitize_text_field((string)($data['reference_id']??''));$reference_id=$reference_token?HE_V2_Domain::decode_public_cursor('reference',$reference_token):0;$external_id=sanitize_text_field($data['external_id']??'');$external_token=''; if((null===$reference_id||!$reference_id)&&!$external_id){throw new RuntimeException('evidence-required');}
			if($reference_id){$valid=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.HE_V2_Schema::table('references').' WHERE id=%d AND concept_id=%d AND version_id=%d',$reference_id,$claim['concept_id'],$claim['version_id']));if(!$valid){throw new RuntimeException('reference-invalid');}}
			$provider=sanitize_key($data['external_provider']??''); if($external_id){$canonical_external=HE_V24_Future_Schema::validate_external_id($provider,$external_id);if(!$provider||!$canonical_external){throw new RuntimeException('provider-invalid');}$valid=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.HE_V24_Future_Schema::table('external_records')." WHERE provider=%s AND external_id=%s AND concept_id=%d AND ((object_type='claim' AND object_id=%d) OR object_type='concept') ORDER BY id DESC LIMIT 1",$provider,$canonical_external,$claim['concept_id'],$claim['id']));if(!$valid){throw new RuntimeException('external-invalid');}$external_token=self::external_evidence_token($provider,$canonical_external);}
			$weight=max(-1,min(1,(float)($data['weight']??0)));$ok=$wpdb->replace(HE_V24_Future_Schema::table('claim_evidence'),array('claim_id'=>(int)$claim['id'],'reference_id'=>$reference_id,'external_id'=>$external_token,'relation'=>$relation,'weight'=>$weight,'note'=>sanitize_textarea_field($data['note']??''),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql',true)));if(false===$ok){throw new RuntimeException('evidence-write');}
			$changed=$wpdb->query($wpdb->prepare("UPDATE ".HE_V24_Future_Schema::table('claims')." SET evidence_state='linked',review_status='pending',reviewed_by=0,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",$claim['id'],$expected));if(1!==(int)$changed){throw new RuntimeException('version-conflict');}
			$prov=HE_V24_Future_Schema::append_provenance('claim',(string)$claim['id'],'evidence.linked','',array('relation'=>$relation,'reference_id'=>$reference_id,'external_provider'=>$provider,'external_id'=>$external_id));if(!$prov){throw new RuntimeException('provenance-write');}
			if(false===$wpdb->query('COMMIT')){throw new RuntimeException('commit-failed');}
		}catch(Throwable $e){$wpdb->query('ROLLBACK');$m=$e->getMessage();if('not-found'===$m){$err=new WP_Error('he_not_found',__('Claim not found.','homeopathy-encyclopedia'),array('status'=>404));}elseif('version-conflict'===$m){$err=new WP_Error('he_version_conflict',__('The claim changed in another session. Reload and retry.','homeopathy-encyclopedia'),array('status'=>409));}elseif(in_array($m,array('version-gate','evidence-required','reference-invalid','provider-invalid','external-invalid','relation-invalid'),true)){$err=new WP_Error('he_future_evidence_invalid',__('The evidence binding is no longer valid for the current governed claim.','homeopathy-encyclopedia'),array('status'=>422));}else{HE_V2_Schema::record_runtime_failure('claim_evidence_atomic_failed','Claim evidence, review invalidation or provenance could not be committed atomically.');$err=new WP_Error('he_future_evidence_write_failed',__('The evidence change could not be saved atomically.','homeopathy-encyclopedia'),array('status'=>503));}return self::mutation_finish($reservation,$err,201);}
		return self::mutation_finish($reservation,array('saved'=>true,'claim_id'=>$claim['public_id'],'review_status'=>'pending','row_version'=>$expected+1),201);
	}

	public static function rest_claim_review( WP_REST_Request $request ) {
		$claim_public=strtolower(sanitize_text_field((string)$request['id']));$reservation=self::mutation_guard($request,'future-claim-review-'.sanitize_key($claim_public),HE_V2_Auth::CAP_REVIEW);if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,null,200);}
		global $wpdb;$data=self::request_data($request);$table=HE_V24_Future_Schema::table('claims');$claim=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id=%s",$claim_public),ARRAY_A);if(!$claim){return self::mutation_finish($reservation,new WP_Error('he_not_found',__('Claim not found.','homeopathy-encyclopedia'),array('status'=>404)));}
		$reviewer=get_current_user_id();if((int)$claim['created_by']===$reviewer&&!HE_V2_Auth::is_founder($reviewer)){return self::mutation_finish($reservation,new WP_Error('he_independent_review_required',__('The claim author cannot provide the independent approval review.','homeopathy-encyclopedia'),array('status'=>422)));}
		$decision=sanitize_key($data['decision']??'changes-required');if(!in_array($decision,array('approved','changes-required','rejected'),true)){return self::mutation_finish($reservation,new WP_Error('he_future_review_invalid',__('Invalid review decision.','homeopathy-encyclopedia'),array('status'=>400)));}
		$expected=absint($data['row_version']??0);if(!$expected||$expected!==(int)$claim['row_version']){return self::mutation_finish($reservation,new WP_Error('he_version_conflict',__('The claim changed in another session. Reload and retry.','homeopathy-encyclopedia'),array('status'=>409)));}
		if('approved'===$decision){$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.HE_V24_Future_Schema::table('claim_evidence').' WHERE claim_id=%d',$claim['id']));if($count<1){return self::mutation_finish($reservation,new WP_Error('he_future_claim_evidence_required',__('A claim cannot be approved without governed evidence.','homeopathy-encyclopedia'),array('status'=>422)));}}
		$status='changes-required'===$decision?'pending':$decision;$updated=$wpdb->query($wpdb->prepare("UPDATE {$table} SET review_status=%s,reviewed_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",$status,$reviewer,$claim['id'],$expected));if(1!==(int)$updated){return self::mutation_finish($reservation,new WP_Error('he_version_conflict',__('The claim changed in another session. Reload and retry.','homeopathy-encyclopedia'),array('status'=>409)));}
		HE_V24_Future_Schema::append_provenance('claim',(string)$claim['id'],'claim.reviewed','',array('decision'=>$decision));return self::mutation_finish($reservation,array('claim_id'=>$claim['public_id'],'review_status'=>$status,'row_version'=>$expected+1),200);
	}

	public static function rest_provenance( WP_REST_Request $request ) {
		$format = sanitize_key( $request->get_param( 'format' ) ?: 'json' );
		if ( ! in_array( $format, array( 'json','jsonld' ), true ) ) { return new WP_Error( 'he_future_provenance_format', __( 'Unsupported provenance format.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		return rest_ensure_response( HE_V24_Future_Schema::public_provenance( $request['type'], $request['id'], $format ) );
	}

	private static function resolve_external_binding( $data ) {
		global $wpdb;$type=sanitize_key($data['object_type']??'');$public=strtolower(sanitize_text_field((string)($data['object_id']??'')));
		if(!in_array($type,array('concept','claim','research'),true)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',$public)){return new WP_Error('he_future_binding_required',__('A canonical governed concept, claim or research public identifier is required.','homeopathy-encyclopedia'),array('status'=>422));}
		$concept_id=0;$object_id=0;
		if('concept'===$type){$row=self::concept_by_public_id($public,false);if(!$row){return new WP_Error('he_not_found',__('Concept not found.','homeopathy-encyclopedia'),array('status'=>404));}$object_id=(int)$row['id'];$concept_id=$object_id;}
		elseif('claim'===$type){$row=$wpdb->get_row($wpdb->prepare('SELECT id,concept_id FROM '.HE_V24_Future_Schema::table('claims').' WHERE public_id=%s',$public),ARRAY_A);if(!$row){return new WP_Error('he_not_found',__('Claim not found.','homeopathy-encyclopedia'),array('status'=>404));}$object_id=(int)$row['id'];$concept_id=(int)$row['concept_id'];}
		else{$row=$wpdb->get_row($wpdb->prepare('SELECT id FROM '.HE_V2_Schema::table('research').' WHERE public_id=%s',$public),ARRAY_A);if(!$row){return new WP_Error('he_not_found',__('Research record not found.','homeopathy-encyclopedia'),array('status'=>404));}$object_id=(int)$row['id'];}
		return array('object_type'=>$type,'object_id'=>$object_id,'concept_id'=>$concept_id,'public_id'=>$public);
	}

	private static function external_binding_permission( $binding ) {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( 'research' === $binding['object_type'] ) {
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', $binding['object_id'] ) );
			return $post_id ? HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, $post_id, 'file06-future-external-stage-research' ) : new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$concept = HE_V24_Future_Schema::concept_row( $binding['concept_id'], false );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		if ( ! HE_V241_Governance::editor_type_allowed( $user_id, $concept['type_slug'] ) ) { return new WP_Error( 'he_editor_type_scope_required', __( 'This actor is not assigned to this knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, (int) $concept['post_id'], 'file06-future-external-stage' );
	}

	public static function rest_external_lookup( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-external-stage', HE_V2_Auth::CAP_RESEARCH );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 201 ); }
		global $wpdb; $data = self::request_data( $request );
		$provider = sanitize_key( $data['provider'] ?? '' );
		$external_id = HE_V24_Future_Schema::validate_external_id( $provider, $data['external_id'] ?? '' );
		if ( ! $external_id ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_external_id_invalid', __( 'The scholarly identifier is invalid for this provider.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$binding = self::resolve_external_binding( $data );
		if ( is_wp_error( $binding ) ) { return self::mutation_finish( $reservation, $binding ); }
		$permission = self::external_binding_permission( $binding );
		if ( is_wp_error( $permission ) ) { return self::mutation_finish( $reservation, $permission ); }
		if ( 'clinicaltrials' === $provider && ! in_array( $binding['object_type'], array( 'claim','research' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_trial_binding_invalid', __( 'Clinical-trial evidence must be bound to a claim or research record.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$metadata = HE_V24_Future_Schema::lookup_external( $provider, $external_id );
		if ( is_wp_error( $metadata ) ) { return self::mutation_finish( $reservation, $metadata ); }
		$relation = sanitize_key( $data['relation'] ?? $data['purpose'] ?? 'literature' ); $purpose = sanitize_key( $data['purpose'] ?? 'literature' );
		$table = HE_V24_Future_Schema::table( 'external_records' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE provider=%s AND external_id=%s AND object_type=%s AND object_id=%d", $provider, $external_id, $binding['object_type'], $binding['object_id'] ), ARRAY_A );
		$row = array( 'provider' => $provider, 'external_id' => $external_id, 'concept_id' => $binding['concept_id'], 'object_type' => $binding['object_type'], 'object_id' => $binding['object_id'], 'relation' => $relation, 'purpose' => $purpose, 'status' => 'staged', 'metadata_json' => wp_json_encode( $metadata ), 'checked_at' => current_time( 'mysql', true ), 'review_required' => 1 );
		if ( $existing ) { $ok = $wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ); $record_id = (int) $existing['id']; } else { $ok = $wpdb->insert( $table, $row ); $record_id = (int) $wpdb->insert_id; }
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_external_stage_failed', __( 'The scholarly metadata could not be staged.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'external-record', (string) $record_id, 'metadata.staged', '', array( 'provider' => $provider, 'external_id' => $external_id, 'binding' => $binding, 'source_hash' => hash( 'sha256', wp_json_encode( $metadata ) ) ) );
		$public_binding = array( 'object_type' => $binding['object_type'], 'object_id' => $binding['public_id'] );
		return self::mutation_finish( $reservation, array( 'id' => HE_V2_Domain::encode_public_cursor( 'external-record', $record_id ), 'provider' => $provider, 'external_id' => $external_id, 'binding' => $public_binding, 'status' => 'staged', 'review_required' => true, 'metadata' => $metadata ), $existing ? 200 : 201 );
	}

	public static function rest_retraction_watch( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-retraction-watch', HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		return self::mutation_finish( $reservation, HE_V24_Future_Schema::scan_retractions(), 200 );
	}

	public static function rest_mapping( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-vocabulary-map', HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb;
		$data = self::request_data( $request );
		$vocabulary = sanitize_key( $data['vocabulary'] ?? '' );
		if ( ! in_array( $vocabulary, array( 'mesh','datacite','pubmed','clinicaltrials' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_mapping_invalid', __( 'Unsupported concept-mapping vocabulary. ORCID is a researcher identity mapping, not a concept taxonomy.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$concept = HE_V24_Future_Schema::concept_row( absint( $data['concept_id'] ?? 0 ), false );
		if ( ! $concept ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$external = HE_V24_Future_Schema::validate_external_id( $vocabulary, $data['external_id'] ?? '' );
		if ( ! $external ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_mapping_identifier_invalid', __( 'The external vocabulary identifier is invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$table = HE_V24_Future_Schema::table( 'concept_mappings' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,created_at FROM {$table} WHERE concept_id=%d AND vocabulary=%s AND external_id=%s", $concept['id'], $vocabulary, $external ), ARRAY_A );
		$now = current_time( 'mysql', true );
		$row = array( 'concept_id' => $concept['id'], 'vocabulary' => $vocabulary, 'external_id' => $external, 'preferred_label' => sanitize_text_field( $data['preferred_label'] ?? '' ), 'mapping_state' => 'reviewed', 'reviewed_by' => get_current_user_id(), 'created_at' => $existing ? $existing['created_at'] : $now, 'updated_at' => $now );
		if ( $existing ) { $ok = $wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ); } else { $ok = $wpdb->insert( $table, $row ); }
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_mapping_write_failed', __( 'The vocabulary mapping could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'concept', (string) $concept['id'], 'mapping.reviewed', '', array( 'vocabulary' => $vocabulary, 'external_id' => $external ) );
		return self::mutation_finish( $reservation, array( 'saved' => true, 'concept_id' => $concept['public_id'], 'vocabulary' => $vocabulary, 'external_id' => $external ), 200 );
	}

	public static function rest_researcher_identity( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-researcher-identity', HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb;
		$data = self::request_data( $request );
		$user_id = absint( $data['user_id'] ?? 0 );
		$orcid = HE_V24_Future_Schema::validate_external_id( 'orcid', $data['external_id'] ?? '' );
		if ( ! $user_id || ! $orcid || ! HE_V2_Auth::provider_ready() || ! HE_V2_Auth::membership_allowed( $user_id ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_researcher_identity_invalid', __( 'A valid, active File 00 identity and ORCID iD are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$table = HE_V24_Future_Schema::table( 'researcher_ids' );
		$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE provider='orcid' AND external_id=%s", $orcid ) );
		if ( $owner && $owner !== $user_id ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_orcid_conflict', __( 'This ORCID iD is already mapped to another governed identity.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,created_at FROM {$table} WHERE user_id=%d AND provider='orcid'", $user_id ), ARRAY_A );
		$now = current_time( 'mysql', true );
		$row = array( 'user_id' => $user_id, 'provider' => 'orcid', 'external_id' => $orcid, 'preferred_label' => sanitize_text_field( $data['preferred_label'] ?? '' ), 'mapping_state' => 'reviewed', 'reviewed_by' => get_current_user_id(), 'created_at' => $existing ? $existing['created_at'] : $now, 'updated_at' => $now );
		if ( $existing ) { $ok = $wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ); } else { $ok = $wpdb->insert( $table, $row ); }
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_researcher_identity_write_failed', __( 'The researcher identity mapping could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'researcher-identity', $orcid, 'orcid.mapping.reviewed', '', array( 'mapping_only' => true ) );
		return self::mutation_finish( $reservation, array( 'saved' => true, 'provider' => 'orcid', 'external_id' => $orcid, 'mapping_only' => true, 'grants_platform_privilege' => false ), 200 );
	}

	private static function tokens( $text ) {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( wp_strip_all_tags( (string) $text ), 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_unique( array_filter( $parts, static function( $token ) { return mb_strlen( $token, 'UTF-8' ) > 2; } ) ) );
	}

	private static function concept_fingerprint( $concept ) {
		global $wpdb;
		$post = get_post( (int) $concept['post_id'] );
		$parts = array( $post ? $post->post_title : '', $post ? $post->post_excerpt : '', $post ? $post->post_content : '' );
		$aliases = $wpdb->get_col( $wpdb->prepare( 'SELECT alias FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d ORDER BY id ASC LIMIT 100', $concept['id'] ) );
		$parts = array_merge( $parts, $aliases );
		if ( ! empty( $concept['current_version'] ) ) {
			$structured = $wpdb->get_var( $wpdb->prepare( 'SELECT structured_json FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', $concept['current_version'], $concept['id'] ) );
			$parts[] = (string) $structured;
		}
		$refs = $wpdb->get_results( $wpdb->prepare( 'SELECT title,doi,source_type,evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d ORDER BY id ASC LIMIT 50', $concept['id'] ), ARRAY_A );
		foreach ( $refs as $ref ) { $parts[] = implode( ' ', $ref ); }
		$rels = $wpdb->get_results( $wpdb->prepare( 'SELECT relation_type,source_concept_id,target_concept_id FROM ' . HE_V2_Schema::table( 'relations' ) . " WHERE (source_concept_id=%d OR target_concept_id=%d) AND status='active' ORDER BY id ASC LIMIT 80", $concept['id'], $concept['id'] ), ARRAY_A );
		foreach ( $rels as $rel ) { $parts[] = $rel['relation_type']; }
		return implode( ' ', $parts );
	}

	private static function jaccard( $left, $right ) {
		$a = self::tokens( $left ); $b = self::tokens( $right );
		if ( ! $a || ! $b ) { return 0.0; }
		$intersection = count( array_intersect( $a, $b ) );
		$union = count( array_unique( array_merge( $a, $b ) ) );
		return $union ? $intersection / $union : 0.0;
	}

	public static function rest_duplicate_scan( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-duplicate-scan', HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb;
		$data = self::request_data( $request );
		$concept = HE_V24_Future_Schema::concept_row( absint( $data['concept_id'] ?? 0 ), false );
		if ( ! $concept ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$source = self::concept_fingerprint( $concept );
		$others = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE id<>%d AND merged_into_id=0 AND type_slug=%s ORDER BY updated_at DESC LIMIT %d", $concept['id'], $concept['type_slug'], self::MAX_DUPLICATE_CANDIDATES ), ARRAY_A );
		$out = array();
		foreach ( $others as $other ) {
			$score = self::jaccard( $source, self::concept_fingerprint( $other ) );
			if ( $score < 0.25 ) { continue; }
			$a = min( (int) $concept['id'], (int) $other['id'] ); $b = max( (int) $concept['id'], (int) $other['id'] );
			$now = current_time( 'mysql', true );
			$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V24_Future_Schema::table( 'similarity' ) . ' WHERE concept_a=%d AND concept_b=%d', $a, $b ) );
			$row = array( 'concept_a' => $a, 'concept_b' => $b, 'score' => $score, 'reason_json' => wp_json_encode( array( 'method' => 'multisignal-jaccard-v2', 'signals' => array( 'content','aliases','structured-fields','references','graph-context' ) ) ), 'state' => 'candidate', 'created_at' => $now, 'updated_at' => $now );
			if ( $existing ) { $wpdb->update( HE_V24_Future_Schema::table( 'similarity' ), $row, array( 'id' => (int) $existing ) ); } else { $wpdb->insert( HE_V24_Future_Schema::table( 'similarity' ), $row ); }
			$out[] = array( 'concept_id' => $other['public_id'], 'score' => round( $score, 5 ), 'advisory_only' => true );
		}
		usort( $out, static function( $x, $y ) { return $y['score'] <=> $x['score']; } );
		return self::mutation_finish( $reservation, array( 'concept_id' => $concept['public_id'], 'candidates' => array_slice( $out, 0, 50 ), 'auto_merge' => false ), 200 );
	}

	public static function rest_graph( WP_REST_Request $request ) {
		global $wpdb;
		$concept = self::public_read_concept( $request );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$relations = $wpdb->get_results( $wpdb->prepare( 'SELECT r.relation_type,r.owner_file,r.source_concept_id,r.target_concept_id,r.source_reference_id FROM ' . HE_V2_Schema::table( 'relations' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' sc ON sc.id=r.source_concept_id INNER JOIN ' . HE_V2_Schema::table( 'references' ) . " sr ON sr.id=r.source_reference_id AND sr.concept_id=r.source_concept_id AND sr.version_id=sc.current_version WHERE (r.source_concept_id=%d OR r.target_concept_id=%d) AND r.status='active' AND sc.current_version>0 ORDER BY r.id DESC LIMIT 300", $concept['id'], $concept['id'] ), ARRAY_A );
		$edges = array(); $nodes = array();
		foreach ( $relations as $relation ) {
			$source = HE_V24_Future_Schema::concept_row( $relation['source_concept_id'], true );
			$target = HE_V24_Future_Schema::concept_row( $relation['target_concept_id'], true );
			if ( ! $source || ! $target ) { continue; }
			$nodes[ $source['public_id'] ] = array( 'id' => $source['public_id'], 'type' => $source['type_slug'], 'url' => get_permalink( (int) $source['post_id'] ) );
			$nodes[ $target['public_id'] ] = array( 'id' => $target['public_id'], 'type' => $target['type_slug'], 'url' => get_permalink( (int) $target['post_id'] ) );
			$edge = array( 'source' => $source['public_id'], 'target' => $target['public_id'], 'relation' => $relation['relation_type'], 'owner_file' => $relation['owner_file'] );
			if ( ! empty( $relation['source_reference_id'] ) ) {
				$ref = $wpdb->get_row( $wpdb->prepare( 'SELECT source_type,title,doi,evidence_grade,rights_status FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d', absint( $relation['source_reference_id'] ) ), ARRAY_A );
				if ( $ref ) { $edge['reference'] = $ref; }
			}
			$edges[] = $edge;
		}
		$mappings = $wpdb->get_results( $wpdb->prepare( 'SELECT vocabulary,external_id,preferred_label,mapping_state FROM ' . HE_V24_Future_Schema::table( 'concept_mappings' ) . " WHERE concept_id=%d AND mapping_state='reviewed' ORDER BY id ASC LIMIT 100", $concept['id'] ), ARRAY_A );
		$claims = HE_V24_Future_Schema::public_claims( $concept['id'] );
		return rest_ensure_response( array( 'concept_id' => $concept['public_id'], 'nodes' => array_values( $nodes ), 'edges' => $edges, 'claims' => is_wp_error( $claims ) ? array() : $claims, 'mappings' => $mappings, 'visual_owner' => 'file-25', 'bounded' => true ) );
	}

	public static function rest_time_machine( WP_REST_Request $request ) {
		global $wpdb;
		$concept = self::public_read_concept( $request );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$as_of = trim( (string) $request->get_param( 'as_of' ) );
		$params = array( $concept['id'] );
		$where = 'concept_id=%d';
		if ( '' !== $as_of ) {
			$time = strtotime( $as_of );
			if ( ! $time ) { return new WP_Error( 'he_future_as_of_invalid', __( 'The as_of timestamp is invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
			$where .= ' AND COALESCE(effective_at,created_at)<=%s'; $params[] = gmdate( 'Y-m-d H:i:s', $time );
		}
		$params[] = 200;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,version_number,status,title,summary,content_hash,change_reason,effective_at,created_at FROM ' . HE_V2_Schema::table( 'versions' ) . " WHERE {$where} ORDER BY version_number DESC LIMIT %d", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array( 'version_number' => (int) $row['version_number'], 'status' => $row['status'], 'title' => $row['title'], 'summary' => $row['summary'], 'content_hash' => $row['content_hash'], 'change_reason' => $row['change_reason'], 'effective_at' => $row['effective_at'], 'created_at' => $row['created_at'], 'is_current' => (int) $row['id'] === (int) $concept['current_version'], 'superseded' => (int) $row['id'] !== (int) $concept['current_version'], 'safety_warning' => in_array( $row['status'], array( 'corrected','retracted' ), true ) ? 'historical-version-do-not-treat-as-current' : '' );
		}
		return rest_ensure_response( array( 'concept_id' => $concept['public_id'], 'as_of' => $as_of ?: null, 'versions' => $out ) );
	}

	public static function rest_impact( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-impact-' . absint( $request['id'] ), HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 202 ); }
		$concept = HE_V24_Future_Schema::concept_row( absint( $request['id'] ), false );
		if ( ! $concept ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$data = self::request_data( $request );
		$reason = sanitize_textarea_field( $data['reason'] ?? '' );
		if ( '' === trim( $reason ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_impact_reason_required', __( 'A reason is required for consumer revalidation.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$count = HE_V24_Future_Schema::queue_impact( 'concept', $concept['public_id'], 'KnowledgeConceptChanged.v1', array( 'reason' => $reason, 'concept_id' => $concept['public_id'] ) );
		return self::mutation_finish( $reservation, array( 'queued' => $count, 'concept_id' => $concept['public_id'], 'direct_companion_writes' => false ), 202 );
	}

	public static function rest_freshness( WP_REST_Request $request ) {
		global $wpdb;
		$concept = self::public_read_concept( $request );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT freshness_state,risk_tier,priority_score,last_evidence_scan,last_human_review,review_due_at,updated_at FROM ' . HE_V24_Future_Schema::table( 'freshness' ) . ' WHERE concept_id=%d', $concept['id'] ), ARRAY_A );
		if ( ! $row ) { $row = HE_V24_Future_Schema::refresh_freshness( $concept['id'], false ); if ( is_wp_error( $row ) ) { return $row; } unset( $row['concept_id'] ); }
		$row['concept_id'] = $concept['public_id']; $row['priority_score'] = (float) $row['priority_score'];
		return rest_ensure_response( $row );
	}

	public static function rest_gaps() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT g.gap_type,g.severity,g.priority_score,g.rationale,g.metrics_json,g.detected_at,g.updated_at,c.public_id FROM ' . HE_V24_Future_Schema::table( 'research_gaps' ) . ' g INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=g.concept_id WHERE g.state='open' ORDER BY g.priority_score DESC,g.updated_at DESC LIMIT 300", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $rows as &$row ) { $row['concept_id'] = $row['public_id']; unset( $row['public_id'] ); $row['priority_score'] = (float) $row['priority_score']; $row['metrics'] = json_decode( $row['metrics_json'], true ); unset( $row['metrics_json'] ); }
		return rest_ensure_response( $rows );
	}

	private static function citation_rows( $concept_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT r.source_type,r.author,r.title,r.edition,r.volume,r.page_locator,r.publisher,r.year,r.url,r.doi,r.evidence_grade,r.rights_status,r.quotation_word_count,r.link_status FROM ' . HE_V2_Schema::table( 'references' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' c ON c.id=r.concept_id AND r.version_id=c.current_version WHERE r.concept_id=%d ORDER BY r.id ASC LIMIT 500', $concept_id ), ARRAY_A );
	}

	private static function citation_format( $refs, $format ) {
		if ( 'json' === $format ) { return $refs; }
		if ( 'csl-json' === $format ) {
			$out = array(); foreach ( $refs as $i => $r ) { $out[] = array( 'id' => 'he-' . ( $i + 1 ), 'type' => 'article', 'title' => $r['title'], 'author' => $r['author'] ? array( array( 'literal' => $r['author'] ) ) : array(), 'publisher' => $r['publisher'], 'issued' => $r['year'] ? array( 'raw' => $r['year'] ) : null, 'DOI' => $r['doi'], 'URL' => $r['url'] ); } return $out;
		}
		if ( 'jsonld' === $format ) {
			$items = array(); foreach ( $refs as $r ) { $items[] = array( '@type' => 'CreativeWork', 'name' => $r['title'], 'author' => $r['author'], 'publisher' => $r['publisher'], 'identifier' => $r['doi'], 'url' => $r['url'] ); } return array( '@context' => 'https://schema.org', '@graph' => $items );
		}
		$text = '';
		foreach ( $refs as $index => $r ) {
			$key = 'he' . ( $index + 1 ); $title = str_replace( array( '{','}' ), '', $r['title'] ?: 'Untitled' );
			if ( 'bibtex' === $format ) { $text .= "@misc{{$key},\n  title = {{$title}},\n  author = {" . str_replace( array( '{','}' ), '', $r['author'] ) . "},\n  doi = {" . $r['doi'] . "},\n  url = {" . $r['url'] . "}\n}\n\n"; }
			else { $text .= "TY  - GEN\nTI  - {$title}\nAU  - " . $r['author'] . "\nDO  - " . $r['doi'] . "\nUR  - " . $r['url'] . "\nER  - \n\n"; }
		}
		return $text;
	}

	public static function rest_citations( WP_REST_Request $request ) {
		$concept = self::public_read_concept( $request );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$format = sanitize_key( $request['format'] );
		if ( ! in_array( $format, array( 'json','jsonld','bibtex','ris','csl-json' ), true ) ) { return new WP_Error( 'he_future_citation_format', __( 'Unsupported citation format.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		$refs = self::citation_rows( $concept['id'] );
		return rest_ensure_response( array( 'format' => $format, 'concept_id' => $concept['public_id'], 'rights_policy' => 'bibliographic-metadata-only; no restricted full text', 'content' => self::citation_format( $refs, $format ) ) );
	}

	public static function rest_watchlist() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT object_type,object_id,event_mask,created_at,updated_at FROM ' . HE_V24_Future_Schema::table( 'watchlists' ) . ' WHERE user_id=%d AND active=1 ORDER BY updated_at DESC LIMIT 500', get_current_user_id() ), ARRAY_A );
		return rest_ensure_response( $rows );
	}

	public static function rest_watchlist_write( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-watchlist', '', true );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb;
		$data = self::request_data( $request );
		$type = sanitize_key( $data['object_type'] ?? 'concept' );
		$public_id = sanitize_text_field( $data['object_id'] ?? '' );
		if ( 'concept' !== $type || ! preg_match( '/^[a-f0-9-]{36}$/i', $public_id ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_watch_invalid', __( 'Only canonical encyclopedia concept public IDs may be watched.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE public_id=%s AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0", $public_id ), ARRAY_A );
		if ( ! $concept ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$active = $request->has_param( 'active' ) ? (bool) rest_sanitize_boolean( $data['active'] ) : true;
		$allowed_events = array( 'updated','corrected','retracted','translation' );
		$events = isset( $data['events'] ) ? array_values( array_intersect( $allowed_events, array_map( 'sanitize_key', (array) $data['events'] ) ) ) : $allowed_events;
		$event_mask = implode( ',', $events ?: $allowed_events );
		$table = HE_V24_Future_Schema::table( 'watchlists' ); $uid = get_current_user_id(); $now = current_time( 'mysql', true );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id,created_at FROM {$table} WHERE user_id=%d AND object_type='concept' AND object_id=%s", $uid, $public_id ), ARRAY_A );
		$row = array( 'user_id' => $uid, 'object_type' => 'concept', 'object_id' => $public_id, 'event_mask' => $event_mask, 'active' => $active ? 1 : 0, 'created_at' => $existing ? $existing['created_at'] : $now, 'updated_at' => $now );
		if ( $existing ) { $ok = $wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ); } else { $ok = $wpdb->insert( $table, $row ); }
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_watch_write_failed', __( 'The watch preference could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		return self::mutation_finish( $reservation, array( 'saved' => true, 'active' => $active, 'object_id' => $public_id, 'events' => explode( ',', $event_mask ), 'delivery_owner' => 'file-19' ), 200 );
	}

	private static function clean_translation_content( $content ) {
		$content = is_array( $content ) ? $content : array( 'body' => (string) $content );
		$out = array();
		foreach ( array( 'title','summary','body','key_points','safety_note' ) as $key ) {
			if ( isset( $content[ $key ] ) ) { $out[ $key ] = 'body' === $key ? wp_kses_post( (string) $content[ $key ] ) : sanitize_textarea_field( (string) $content[ $key ] ); }
		}
		return $out;
	}

	public static function rest_translations( WP_REST_Request $request ) {
		global $wpdb;
		$concept = self::public_read_concept( $request );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT locale,source_locale,source_version,translation_version,content_json,content_hash,published_at,updated_at FROM ' . HE_V24_Future_Schema::table( 'translations' ) . " WHERE concept_id=%d AND status='published' AND source_version=%d ORDER BY locale ASC LIMIT 20", $concept['id'], $concept['current_version'] ), ARRAY_A );
		foreach ( $rows as &$row ) { $row['content'] = json_decode( $row['content_json'], true ); unset( $row['content_json'] ); $row['translation_version'] = (int) $row['translation_version']; }
		return rest_ensure_response( array( 'concept_id' => $concept['public_id'], 'translations' => $rows ) );
	}

	public static function rest_translation_write( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-translation-save-' . absint( $request['id'] ), HE_V2_Auth::CAP_EDIT );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb;
		$data = self::request_data( $request );
		$concept = HE_V24_Future_Schema::concept_row( absint( $request['id'] ), false );
		if ( ! $concept ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$locale = sanitize_text_field( $data['locale'] ?? '' );
		if ( ! in_array( $locale, array( 'ur-PK','ar','en-US' ), true ) || $locale === $concept['language'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_locale_invalid', __( 'The target locale is invalid or identical to the source locale.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$source = absint( $data['source_version'] ?? 0 );
		if ( ! $source || $source !== (int) $concept['current_version'] || ! HE_V24_Future_Schema::version_belongs( $concept['id'], $source ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_source_invalid', __( 'Translations must bind to the current governed source version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$content = self::clean_translation_content( $data['content'] ?? array() );
		if ( empty( $content['title'] ) || empty( $content['body'] ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_content_required', __( 'Translated title and body are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$hash = hash( 'sha256', wp_json_encode( $content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); $table = HE_V24_Future_Schema::table( 'translations' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND locale=%s", $concept['id'], $locale ), ARRAY_A ); $now = current_time( 'mysql', true );
		if ( $existing ) {
			$expected = absint( $data['expected_translation_version'] ?? 0 );
			if ( ! $expected || $expected !== (int) $existing['translation_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
			$version = $expected + 1;
			$ok = $wpdb->update( $table, array( 'source_locale' => $concept['language'], 'source_version' => $source, 'translation_version' => $version, 'status' => 'draft', 'translator_id' => get_current_user_id(), 'reviewer_id' => 0, 'content_json' => wp_json_encode( $content ), 'content_hash' => $hash, 'published_at' => null, 'updated_at' => $now ), array( 'id' => (int) $existing['id'], 'translation_version' => $expected ) );
		} else {
			$version = 1; $ok = $wpdb->insert( $table, array( 'concept_id' => $concept['id'], 'locale' => $locale, 'source_locale' => $concept['language'], 'source_version' => $source, 'translation_version' => $version, 'status' => 'draft', 'translator_id' => get_current_user_id(), 'reviewer_id' => 0, 'content_json' => wp_json_encode( $content ), 'content_hash' => $hash, 'created_at' => $now, 'updated_at' => $now ) );
		}
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_write_failed', __( 'The translation could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'translation', $concept['public_id'] . ':' . $locale, 'translation.saved', '', array( 'source_version' => $source, 'translation_version' => $version, 'source_hash' => $hash ) );
		return self::mutation_finish( $reservation, array( 'saved' => true, 'status' => 'draft', 'translation_version' => $version, 'source_version' => $source ), $existing ? 200 : 201 );
	}

	private static function translation_row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'translations' ) . ' WHERE id=%d', absint( $id ) ), ARRAY_A );
	}

	public static function rest_translation_review( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-translation-review-' . absint( $request['id'] ), HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb; $data = self::request_data( $request ); $row = self::translation_row( $request['id'] );
		if ( ! $row ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Translation not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$reviewer = get_current_user_id(); if ( (int) $row['translator_id'] === $reviewer && ! HE_V2_Auth::is_founder( $reviewer ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_independent_review_required', __( 'The translator cannot provide the independent approval review.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $row['concept_id'], false ); if ( ! $concept || (int) $row['source_version'] !== (int) $concept['current_version'] ) { $wpdb->update( HE_V24_Future_Schema::table( 'translations' ), array( 'status' => 'translation-outdated', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) ); return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_outdated', __( 'The source knowledge changed; this translation must be refreshed before approval.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$decision = sanitize_key( $data['decision'] ?? '' ); if ( ! in_array( $decision, array( 'approved','changes-required','rejected' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_review_invalid', __( 'Invalid translation review decision.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$expected = absint( $data['translation_version'] ?? 0 ); if ( ! $expected || $expected !== (int) $row['translation_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$status = 'approved' === $decision ? 'approved' : ( 'rejected' === $decision ? 'rejected' : 'draft' );
		$ok = $wpdb->update( HE_V24_Future_Schema::table( 'translations' ), array( 'status' => $status, 'reviewer_id' => $reviewer, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'], 'translation_version' => $expected ) );
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_review_failed', __( 'The translation review could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'translation', (string) $row['id'], 'translation.reviewed', '', array( 'decision' => $decision, 'translation_version' => $expected ) );
		return self::mutation_finish( $reservation, array( 'status' => $status, 'translation_version' => $expected ), 200 );
	}

	public static function rest_translation_publish( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-translation-publish-' . absint( $request['id'] ), HE_V2_Auth::CAP_PUBLISH );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb; $data = self::request_data( $request ); $row = self::translation_row( $request['id'] );
		if ( ! $row || 'approved' !== $row['status'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_not_approved', __( 'Only an independently approved translation can be published.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $row['concept_id'], true ); if ( ! $concept || (int) $row['source_version'] !== (int) $concept['current_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_outdated', __( 'The translation source is no longer current.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$expected = absint( $data['translation_version'] ?? 0 ); if ( ! $expected || $expected !== (int) $row['translation_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$ok = $wpdb->update( HE_V24_Future_Schema::table( 'translations' ), array( 'status' => 'published', 'published_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'], 'translation_version' => $expected ) );
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_translation_publish_failed', __( 'The translation could not be published.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'translation', (string) $row['id'], 'translation.published', '', array( 'translation_version' => $expected, 'source_version' => (int) $row['source_version'] ) );
		HE_V24_Future_Schema::queue_impact( 'translation', $concept['public_id'] . ':' . $row['locale'], 'KnowledgeTranslationUpdated.v1', array( 'concept_id' => $concept['public_id'], 'locale' => $row['locale'] ) );
		return self::mutation_finish( $reservation, array( 'status' => 'published', 'translation_version' => $expected, 'concept_id' => $concept['public_id'] ), 200 );
	}

	public static function rest_command_center() {
		global $wpdb;
		$q = array();
		$q['claims_without_evidence'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'claims' ) . " c WHERE c.review_status='approved' AND NOT EXISTS (SELECT 1 FROM " . HE_V24_Future_Schema::table( 'claim_evidence' ) . " e WHERE e.claim_id=c.id)" );
		$q['unreviewed_claims'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'claims' ) . " WHERE review_status='pending'" );
		$q['stale_or_urgent'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'freshness' ) . " WHERE freshness_state IN ('stale','urgent-review')" );
		$q['research_gaps'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'research_gaps' ) . " WHERE state='open'" );
		$q['duplicate_candidates'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'similarity' ) . " WHERE state='candidate'" );
		$q['retraction_or_correction_reviews'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'external_records' ) . " WHERE status='urgent-review'" );
		$q['outdated_translations'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'translations' ) . " WHERE status='translation-outdated'" );
		$q['pending_or_retry_impacts'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'impact_queue' ) . " WHERE impact_state IN ('pending','retry')" );
		$q['dead_letter_impacts'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'impact_queue' ) . " WHERE impact_state='dead-letter'" );
		$q['active_watches'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'watchlists' ) . " WHERE active=1" );
		$q['watched_concepts'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT object_id) FROM " . HE_V24_Future_Schema::table( 'watchlists' ) . " WHERE active=1 AND object_type='concept'" );
		$q['orphan_concepts'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c LEFT JOIN ' . $wpdb->posts . ' p ON p.ID=c.post_id WHERE c.post_id=0 OR p.ID IS NULL' );
		$providers = array(); foreach ( array_keys( HE_V24_Future_Schema::providers() ) as $provider ) { $providers[ $provider ] = array( 'records' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . ' WHERE provider=%s', $provider ) ), 'urgent_review' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . " WHERE provider=%s AND status='urgent-review'", $provider ) ), 'last_checked_at' => $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(checked_at) FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . ' WHERE provider=%s', $provider ) ) ); }
		$q['connector_health'] = $providers;
		$q['source_of_truth'] = 'file-06'; $q['security_assurance_owner'] = 'file-24'; $q['visual_owner'] = 'file-25'; $q['notification_delivery_owner'] = 'file-19'; $q['autonomous_high_risk_actions'] = false;
		return rest_ensure_response( $q );
	}

	public static function extend_contract( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		if ( empty( $contracts['file-06'] ) ) { return $contracts; }
		$contracts['file-06']['future_hardening'] = array( 'version' => '2.4', 'claim_evidence_fail_closed' => true, 'provenance_hash_chain' => true, 'provider_identifier_validation' => true, 'impact_ack_retry_dead_letter' => true, 'public_dto_internal_ids' => false, 'future_privacy_lifecycle' => true );
		$contracts['file-06']['scholarly_metadata_providers'] = array_keys( HE_V24_Future_Schema::providers() );
		$contracts['file-06']['notification_delivery_owner'] = 'file-19'; $contracts['file-06']['graph_visual_owner'] = 'file-25'; $contracts['file-06']['global_search_owner'] = 'file-26';
		return $contracts;
	}

	public static function assurance( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers['file-06-future-v24'] = array( 'owner' => 'file-06', 'assurance_owner' => 'file-24', 'health' => array( 'HE_V24_Future_Schema', 'health' ), 'native_enforcement_preserved' => true );
		return $providers;
	}
}

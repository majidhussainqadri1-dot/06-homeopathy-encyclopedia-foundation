<?php
/** Final v2.4 Future-18 review gates, public-ID aliases and race-condition guards. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Future_Review_Guard {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 280 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 235, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_callbacks' ), 235, 3 );
	}

	public static function register_routes() {
		$ns = HE_V2_API::NS;
		register_rest_route( $ns, '/future/external/(?P<id>\\d+)/review', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_external_review' ),
			'permission_callback' => static function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW ); },
		), true );
		register_rest_route( $ns, '/future/translations/(?P<id>\\d+)/review', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_translation_review' ),
			'permission_callback' => static function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW ); },
		), true );
		register_rest_route( $ns, '/future/translations/(?P<id>\\d+)/publish', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_translation_publish' ),
			'permission_callback' => static function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH ); },
		), true );

		foreach ( array(
			'/future/public/claims/(?P<id>[a-fA-F0-9-]{36})' => array( 'rest_public_claims', 'GET' ),
			'/future/public/graph/(?P<id>[a-fA-F0-9-]{36})' => array( 'rest_public_graph', 'GET' ),
			'/future/public/time-machine/(?P<id>[a-fA-F0-9-]{36})' => array( 'rest_public_time_machine', 'GET' ),
			'/future/public/freshness/(?P<id>[a-fA-F0-9-]{36})' => array( 'rest_public_freshness', 'GET' ),
			'/future/public/citations/(?P<id>[a-fA-F0-9-]{36})/(?P<format>[a-z0-9_-]+)' => array( 'rest_public_citations', 'GET' ),
			'/future/public/translations/(?P<id>[a-fA-F0-9-]{36})' => array( 'rest_public_translations', 'GET' ),
		) as $route => $definition ) {
			register_rest_route( $ns, $route, array( 'methods' => $definition[1], 'callback' => array( __CLASS__, $definition[0] ), 'permission_callback' => '__return_true' ) );
		}
	}

	private static function route_prefix() { return '/' . HE_V2_API::NS; }

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) { return $response; }
		$route = $request->get_route(); $prefix = self::route_prefix();
		if ( 0 !== strpos( $route, $prefix . '/future/' ) ) { return $response; }

		if ( $prefix . '/future/mappings' === $route && 'POST' === $request->get_method() ) {
			$vocabulary = sanitize_key( $request->get_param( 'vocabulary' ) );
			if ( 'mesh' !== $vocabulary ) {
				return new WP_Error( 'he_future_mapping_scope', __( 'The concept-mapping endpoint is reserved for MeSH vocabulary mappings. Literature, trials and datasets use staged external-record bindings; ORCID uses researcher identity mapping.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		if ( $prefix . '/future/external/lookup' === $route && 'POST' === $request->get_method() && 'orcid' === sanitize_key( $request->get_param( 'provider' ) ) ) {
			return new WP_Error( 'he_future_orcid_scope', __( 'ORCID belongs to researcher identity mapping and cannot be attached to a knowledge concept as scholarly evidence.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/claims/(\\d+)/review$#', $route, $match ) && 'POST' === $request->get_method() && 'approved' === sanitize_key( $request->get_param( 'decision' ) ) ) {
			return self::claim_approval_gate( absint( $match[1] ), $response );
		}
		return $response;
	}

	private static function claim_approval_gate( $claim_id, $response ) {
		global $wpdb;
		$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE id=%d', $claim_id ), ARRAY_A );
		if ( ! $claim ) { return new WP_Error( 'he_not_found', __( 'Claim not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $claim['concept_id'], false );
		if ( ! $concept || ! $claim['version_id'] || (int) $claim['version_id'] !== (int) $concept['current_version'] || ! HE_V24_Future_Schema::version_belongs( $claim['concept_id'], $claim['version_id'] ) ) {
			return new WP_Error( 'he_future_claim_version_gate', __( 'A public claim must be bound to the current governed concept version before approval.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$links = $wpdb->get_results( $wpdb->prepare( 'SELECT reference_id,external_id FROM ' . HE_V24_Future_Schema::table( 'claim_evidence' ) . ' WHERE claim_id=%d ORDER BY id ASC LIMIT 100', $claim_id ), ARRAY_A );
		if ( ! $links ) { return new WP_Error( 'he_future_claim_evidence_required', __( 'A claim cannot be approved without governed current-version evidence.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		foreach ( $links as $link ) {
			if ( ! empty( $link['reference_id'] ) ) {
				$valid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d AND version_id=%d', absint( $link['reference_id'] ), $claim['concept_id'], $claim['version_id'] ) );
				if ( ! $valid ) { return new WP_Error( 'he_future_reference_version_gate', __( 'Linked internal evidence must belong to the same current concept version as the claim.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
				continue;
			}
			$parts = HE_V24_Future_API::external_evidence_token_parts( $link['external_id'] ?? '' );
			if ( ! $parts ) { return new WP_Error( 'he_future_external_relink_required', __( 'Legacy or ambiguous external evidence must be re-linked with an explicit provider before approval.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
			$reviewed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . " WHERE provider=%s AND external_id=%s AND concept_id=%d AND ((object_type='claim' AND object_id=%d) OR object_type='concept') AND status='reviewed' AND review_required=0 ORDER BY id DESC LIMIT 1", $parts['provider'], $parts['external_id'], $claim['concept_id'], $claim_id ) );
			if ( ! $reviewed ) { return new WP_Error( 'he_future_external_review_required', __( 'The exact linked external scholarly record must receive human review before the claim can be approved.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		}
		return $response;
	}

	private static function guard( WP_REST_Request $request, $operation, $capability ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) { return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ); }
		$allowed = HE_V2_Auth::rest_permission( $capability ); if ( is_wp_error( $allowed ) ) { return $allowed; }
		if ( ! HE_V2_Auth::require_nonce( $request ) ) { return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		if ( ! HE_V2_Domain::rate_allow( 'v24-review:' . sanitize_key( $operation ) . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) { return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) ); }
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) ); if ( '' === $key || strlen( $key ) > 128 ) { return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function finish( $reservation, $result, $status = 200 ) {
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		if ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], $reservation['code'] ); }
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data(); $code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			HE_V2_Domain::idempotent_finish( $reservation['id'], $code, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) ); return $result;
		}
		HE_V2_Domain::idempotent_finish( $reservation['id'], $status, $result ); return new WP_REST_Response( $result, $status );
	}

	public static function rest_external_review( WP_REST_Request $request ) {
		$reservation = self::guard( $request, 'external-review-' . absint( $request['id'] ), HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null ); }
		global $wpdb; $table = HE_V24_Future_Schema::table( 'external_records' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $request['id'] ) ), ARRAY_A );
		if ( ! $row ) { return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'External scholarly record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$decision = sanitize_key( $request->get_param( 'decision' ) );
		if ( ! in_array( $decision, array( 'approved','rejected' ), true ) ) { return self::finish( $reservation, new WP_Error( 'he_future_external_review_invalid', __( 'External scholarly review decision must be approved or rejected.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$status = 'approved' === $decision ? 'reviewed' : 'rejected';
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,review_required=0,checked_at=checked_at WHERE id=%d AND review_required=1", $status, $row['id'] ) );
		if ( 1 !== (int) $updated ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'This external scholarly record was already reviewed or changed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'external-record', (string) $row['id'], 'metadata.reviewed', '', array( 'decision' => $decision, 'provider' => $row['provider'], 'external_id' => $row['external_id'] ) );
		return self::finish( $reservation, array( 'id' => (int) $row['id'], 'status' => $status, 'review_required' => false ), 200 );
	}

	private static function translation_row( $id ) {
		global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'translations' ) . ' WHERE id=%d', absint( $id ) ), ARRAY_A );
	}

	public static function rest_translation_review( WP_REST_Request $request ) {
		$reservation = self::guard( $request, 'translation-review-' . absint( $request['id'] ), HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null ); }
		global $wpdb; $row = self::translation_row( $request['id'] );
		if ( ! $row ) { return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'Translation not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$reviewer = get_current_user_id();
		if ( (int) $row['translator_id'] === $reviewer && ! HE_V2_Auth::is_founder( $reviewer ) ) { return self::finish( $reservation, new WP_Error( 'he_independent_review_required', __( 'The translator cannot provide the independent approval review.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $row['concept_id'], false );
		if ( ! $concept || (int) $row['source_version'] !== (int) $concept['current_version'] ) { return self::finish( $reservation, new WP_Error( 'he_future_translation_outdated', __( 'The source knowledge changed; refresh the translation before review.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$decision = sanitize_key( $request->get_param( 'decision' ) ); if ( ! in_array( $decision, array( 'approved','changes-required','rejected' ), true ) ) { return self::finish( $reservation, new WP_Error( 'he_future_translation_review_invalid', __( 'Invalid translation review decision.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$expected = absint( $request->get_param( 'translation_version' ) ); if ( ! $expected ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The expected translation version is required.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$status = 'approved' === $decision ? 'approved' : ( 'rejected' === $decision ? 'rejected' : 'draft' ); $table = HE_V24_Future_Schema::table( 'translations' );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,reviewer_id=%d,updated_at=UTC_TIMESTAMP() WHERE id=%d AND translation_version=%d AND status='draft'", $status, $reviewer, $row['id'], $expected ) );
		if ( 1 !== (int) $updated ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed or was already reviewed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'translation', (string) $row['id'], 'translation.reviewed', '', array( 'decision' => $decision, 'translation_version' => $expected ) );
		return self::finish( $reservation, array( 'status' => $status, 'translation_version' => $expected ), 200 );
	}

	public static function rest_translation_publish( WP_REST_Request $request ) {
		$reservation = self::guard( $request, 'translation-publish-' . absint( $request['id'] ), HE_V2_Auth::CAP_PUBLISH );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null ); }
		global $wpdb; $row = self::translation_row( $request['id'] );
		if ( ! $row ) { return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'Translation not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $row['concept_id'], true );
		if ( ! $concept || (int) $row['source_version'] !== (int) $concept['current_version'] ) { return self::finish( $reservation, new WP_Error( 'he_future_translation_outdated', __( 'The translation source is no longer current.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$expected = absint( $request->get_param( 'translation_version' ) ); if ( ! $expected ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The expected translation version is required.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		$table = HE_V24_Future_Schema::table( 'translations' ); $now = current_time( 'mysql', true );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='published',published_at=%s,updated_at=%s WHERE id=%d AND translation_version=%d AND status='approved' AND source_version=%d", $now, $now, $row['id'], $expected, $concept['current_version'] ) );
		if ( 1 !== (int) $updated ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed, lost approval, or was already published.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'translation', (string) $row['id'], 'translation.published', '', array( 'translation_version' => $expected, 'source_version' => (int) $row['source_version'] ) );
		HE_V24_Future_Schema::queue_impact( 'translation', $concept['public_id'] . ':' . $row['locale'], 'KnowledgeTranslationUpdated.v1', array( 'concept_id' => $concept['public_id'], 'locale' => $row['locale'] ) );
		return self::finish( $reservation, array( 'status' => 'published', 'translation_version' => $expected, 'concept_id' => $concept['public_id'] ), 200 );
	}

	private static function resolve_public_concept( $public_id ) {
		global $wpdb; return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE public_id=%s AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0", sanitize_text_field( $public_id ) ), ARRAY_A );
	}

	private static function adapt_public_request( WP_REST_Request $request ) {
		$concept = self::resolve_public_concept( $request['id'] );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$request->set_param( 'id', (int) $concept['id'] ); return $concept;
	}
	public static function rest_public_claims( WP_REST_Request $request ) { $concept = self::adapt_public_request( $request ); if ( is_wp_error( $concept ) ) { return $concept; } $request->set_param( 'concept_id', (int) $concept['id'] ); return HE_V24_Future_API::rest_claims( $request ); }
	public static function rest_public_graph( WP_REST_Request $request ) { $concept = self::adapt_public_request( $request ); return is_wp_error( $concept ) ? $concept : HE_V24_Future_API::rest_graph( $request ); }
	public static function rest_public_time_machine( WP_REST_Request $request ) { $concept = self::adapt_public_request( $request ); return is_wp_error( $concept ) ? $concept : HE_V24_Future_API::rest_time_machine( $request ); }
	public static function rest_public_freshness( WP_REST_Request $request ) { $concept = self::adapt_public_request( $request ); return is_wp_error( $concept ) ? $concept : HE_V24_Future_API::rest_freshness( $request ); }
	public static function rest_public_citations( WP_REST_Request $request ) { $concept = self::adapt_public_request( $request ); return is_wp_error( $concept ) ? $concept : HE_V24_Future_API::rest_citations( $request ); }
	public static function rest_public_translations( WP_REST_Request $request ) { $concept = self::adapt_public_request( $request ); return is_wp_error( $concept ) ? $concept : HE_V24_Future_API::rest_translations( $request ); }

	public static function after_callbacks( $response, $handler, $request ) {
		if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response ) { return $response; }
		$route = $request->get_route(); $prefix = self::route_prefix(); $data = $response->get_data();
		if ( ( $prefix . '/future/claims' === $route || false !== strpos( $route, $prefix . '/future/public/claims/' ) ) && is_array( $data ) ) {
			$concept_id = absint( $request->get_param( 'concept_id' ) ); $concept = HE_V24_Future_Schema::concept_row( $concept_id, true );
			if ( $concept ) { $data = array_values( array_filter( $data, static function( $claim ) use ( $concept ) { return is_array( $claim ) && ! empty( $claim['version_id'] ) && (int) $claim['version_id'] === (int) $concept['current_version']; } ) ); $response->set_data( $data ); }
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/(?:public/)?graph/#', $route ) && is_array( $data ) && isset( $data['claims'] ) ) {
			$concept = null; if ( ! empty( $data['concept_id'] ) ) { $concept = self::resolve_public_concept( $data['concept_id'] ); }
			if ( $concept ) { $data['claims'] = array_values( array_filter( (array) $data['claims'], static function( $claim ) use ( $concept ) { return is_array( $claim ) && ! empty( $claim['version_id'] ) && (int) $claim['version_id'] === (int) $concept['current_version']; } ) ); $response->set_data( $data ); }
		}
		if ( false !== strpos( $route, $prefix . '/future/provenance/' ) && is_array( $data ) ) {
			$response->set_data( self::sanitize_provenance_response( $data ) );
		}
		return $response;
	}

	private static function sanitize_provenance_response( $data ) {
		global $wpdb;
		$rows = isset( $data['@graph'] ) ? $data['@graph'] : $data;
		if ( isset( $data['@graph'] ) ) { return $data; }
		foreach ( $rows as &$row ) {
			if ( ! is_array( $row ) ) { continue; }
			if ( isset( $row['object_id'] ) && ctype_digit( (string) $row['object_id'] ) ) {
				if ( 'concept' === ( $row['object_type'] ?? '' ) ) { $public = $wpdb->get_var( $wpdb->prepare( 'SELECT public_id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d', absint( $row['object_id'] ) ) ); $row['object_id'] = $public ?: 'redacted-internal-object'; }
				elseif ( 'claim' === ( $row['object_type'] ?? '' ) ) { $public = $wpdb->get_var( $wpdb->prepare( 'SELECT public_id FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE id=%d', absint( $row['object_id'] ) ) ); $row['object_id'] = $public ?: 'redacted-internal-object'; }
				else { $row['object_id'] = 'redacted-internal-object'; }
			}
			if ( isset( $row['metadata'] ) && is_array( $row['metadata'] ) ) { $row['metadata'] = self::strip_internal_ids( $row['metadata'] ); }
		}
		return $rows;
	}

	private static function strip_internal_ids( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		$out = array();
		foreach ( $value as $key => $item ) {
			$key_string = (string) $key;
			if ( preg_match( '/(?:^|_)(?:concept|version|reference|claim|research|user|actor|reviewer|translator)_?id$/', $key_string ) && 'external_id' !== $key_string && 'public_id' !== $key_string ) { continue; }
			$out[ $key ] = is_array( $item ) ? self::strip_internal_ids( $item ) : $item;
		}
		return $out;
	}
}

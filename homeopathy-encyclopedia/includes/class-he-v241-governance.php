<?php
/**
 * File 06 v2.4.1 governance hardening.
 *
 * Enforces the plan's native object/type scope rules on protected REST writes,
 * introduces explicit reviewer/editor assignments owned by File 06 (identity
 * truth remains File 00), and serializes the Future-18 maintenance worker.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V241_Governance {
	const META_EDITOR_TYPES = '_he_editor_type_scope';
	const META_REVIEW_ASSIGNMENTS = '_he_review_assignments';

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 320 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 325, 3 );

		/* V24 Future Schema is the sole maintenance owner and serializes itself. */
	}

	public static function register_routes() {
		register_rest_route( HE_V2_API::NS, '/governance/editor-scope/(?P<user_id>\d+)', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'save_editor_scope' ),
			'permission_callback' => static function() {
				return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH );
			},
		) );
		register_rest_route( HE_V2_API::NS, '/governance/reviewer-assignment/(?P<id>[A-Za-z0-9-]+)', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'save_reviewer_assignment' ),
			'permission_callback' => static function( WP_REST_Request $request ) {
				$concept = HE_V2_Domain::concept_by_id( $request['id'], true );
				if ( ! $concept ) {
					return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
				}
				return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $concept['post_id'], 'file06-review-assignment' );
			},
		) );
	}

	private static function guard_mutation( WP_REST_Request $request, $operation ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Domain::rate_allow( 'v241-governance:' . sanitize_key( $operation ) . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( strlen( $key ) < 8 || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function finish_mutation( $reservation, $result, $code = 200 ) {
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

	public static function save_editor_scope( WP_REST_Request $request ) {
		$reservation = self::guard_mutation( $request, 'editor-scope-' . absint( $request['user_id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::finish_mutation( $reservation, null );
		}
		$user_id = absint( $request['user_id'] );
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) || ! HE_V2_Auth::provider_ready() || ! HE_V2_Auth::membership_allowed( $user_id ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, 0, '', $user_id ) ) {
			return self::finish_mutation( $reservation, new WP_Error( 'he_editor_scope_identity_invalid', __( 'The target user is not an active File 00-authorized knowledge editor.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		$requested = array_values( array_unique( array_map( 'sanitize_key', (array) $request->get_param( 'types' ) ) ) );
		$allowed = array_keys( HE_V2_Domain::types() );
		$types = array_values( array_intersect( $requested, $allowed ) );
		if ( ! $types || count( $types ) !== count( $requested ) ) {
			return self::finish_mutation( $reservation, new WP_Error( 'he_editor_scope_invalid', __( 'One or more knowledge-type assignments are invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		update_user_meta( $user_id, self::META_EDITOR_TYPES, $types );
		HE_V2_Domain::emit_event( 'File06EditorScopeChanged.v1', 'user', $user_id, array( 'types' => $types, 'assigned_by' => get_current_user_id() ) );
		return self::finish_mutation( $reservation, array( 'user_id' => $user_id, 'types' => $types, 'identity_authority' => 'file-00', 'content_scope_owner' => 'file-06' ) );
	}

	public static function save_reviewer_assignment( WP_REST_Request $request ) {
		$reservation = self::guard_mutation( $request, 'reviewer-assignment-' . sanitize_key( $request['id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::finish_mutation( $reservation, null );
		}
		$concept = HE_V2_Domain::concept_by_id( $request['id'], true );
		if ( ! $concept ) {
			return self::finish_mutation( $reservation, new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
		}
		$reviewer_id = absint( $request->get_param( 'reviewer_id' ) );
		$scope = sanitize_key( $request->get_param( 'scope' ) ?: 'scientific' );
		$valid_scopes = array( 'scientific', 'clinical', 'source', 'language', 'shariah', 'privacy' );
		if ( ! in_array( $scope, $valid_scopes, true ) || ! $reviewer_id || ! HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, (int) $concept['post_id'], 'file06-review-assignment-target', $reviewer_id ) ) {
			return self::finish_mutation( $reservation, new WP_Error( 'he_reviewer_assignment_invalid', __( 'The reviewer, scope or current File 00 authorization is invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		$post = get_post( (int) $concept['post_id'] );
		if ( $post && (int) $post->post_author === $reviewer_id ) {
			return self::finish_mutation( $reservation, new WP_Error( 'he_self_review_forbidden', __( 'An entry author cannot be assigned as its independent reviewer.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		$expiry = 0;
		if ( $request->get_param( 'expires_at' ) ) {
			$expiry = strtotime( (string) $request->get_param( 'expires_at' ) );
			if ( ! $expiry || $expiry <= time() || $expiry > time() + YEAR_IN_SECONDS ) {
				return self::finish_mutation( $reservation, new WP_Error( 'he_reviewer_assignment_expiry_invalid', __( 'Reviewer assignment expiry must be in the future and within one year.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
			}
		}
		$assignments = get_post_meta( (int) $concept['post_id'], self::META_REVIEW_ASSIGNMENTS, true );
		$assignments = is_array( $assignments ) ? $assignments : array();
		$previous = isset( $assignments[ $scope ] ) && is_array( $assignments[ $scope ] ) ? $assignments[ $scope ] : array();
		$assignments[ $scope ] = array(
			'reviewer_id' => $reviewer_id,
			'assigned_by' => get_current_user_id(),
			'assigned_at' => gmdate( 'c' ),
			'expires_at' => $expiry ? gmdate( 'c', $expiry ) : '',
			'assignment_version' => 1 + absint( $previous['assignment_version'] ?? 0 ),
		);
		update_post_meta( (int) $concept['post_id'], self::META_REVIEW_ASSIGNMENTS, $assignments );
		HE_V2_Domain::emit_event( 'File06ReviewerAssigned.v1', 'concept', (int) $concept['id'], array( 'scope' => $scope, 'reviewer_user_id' => $reviewer_id, 'assignment_version' => $assignments[ $scope ]['assignment_version'] ) );
		return self::finish_mutation( $reservation, array( 'concept_id' => $concept['public_id'], 'scope' => $scope, 'reviewer_id' => $reviewer_id, 'assignment_version' => $assignments[ $scope ]['assignment_version'], 'expires_at' => $assignments[ $scope ]['expires_at'] ) );
	}

	public static function editor_type_allowed( $user_id, $type ) {
		$user_id = absint( $user_id );
		$type = sanitize_key( $type );
		if ( HE_V2_Auth::is_founder( $user_id ) ) {
			return true;
		}
		$types = get_user_meta( $user_id, self::META_EDITOR_TYPES, true );
		return is_array( $types ) && in_array( $type, $types, true );
	}

	public static function reviewer_assigned( $post_id, $user_id, $scope = '' ) {
		$user_id = absint( $user_id );
		if ( HE_V2_Auth::is_founder( $user_id ) ) {
			return true;
		}
		$assignments = get_post_meta( absint( $post_id ), self::META_REVIEW_ASSIGNMENTS, true );
		if ( ! is_array( $assignments ) ) {
			return false;
		}
		$scopes = $scope ? array( sanitize_key( $scope ) ) : array_keys( $assignments );
		foreach ( $scopes as $candidate_scope ) {
			$assignment = isset( $assignments[ $candidate_scope ] ) && is_array( $assignments[ $candidate_scope ] ) ? $assignments[ $candidate_scope ] : array();
			if ( absint( $assignment['reviewer_id'] ?? 0 ) !== $user_id ) {
				continue;
			}
			$expiry = ! empty( $assignment['expires_at'] ) ? strtotime( $assignment['expires_at'] ) : 0;
			if ( $expiry && $expiry <= time() ) {
				continue;
			}
			return true;
		}
		return false;
	}

	private static function concept_from_claim( $claim_id ) {
		global $wpdb;$public=strtolower(sanitize_text_field((string)$claim_id));
		$concept_id=(int)$wpdb->get_var($wpdb->prepare('SELECT concept_id FROM '.HE_V24_Future_Schema::table('claims').' WHERE public_id=%s',$public));
		return $concept_id?HE_V24_Future_Schema::concept_row($concept_id,false):null;
	}

	private static function concept_from_translation( $translation_id ) {
		global $wpdb;
		$concept_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT concept_id FROM ' . HE_V24_Future_Schema::table( 'translations' ) . ' WHERE id=%d', absint( $translation_id ) ) );
		return $concept_id ? HE_V24_Future_Schema::concept_row( $concept_id, false ) : null;
	}

	private static function research_row( $research_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id,post_id,created_by,status,record_type FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
	}

	private static function concept_for_external_record( $record_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT concept_id,object_type,object_id FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . ' WHERE id=%d', absint( $record_id ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( ! empty( $row['concept_id'] ) ) {
			return HE_V24_Future_Schema::concept_row( (int) $row['concept_id'], false );
		}
		return null;
	}

	private static function object_permission( $cap, $post_id, $purpose ) {
		if ( ! $post_id ) {
			return new WP_Error( 'he_not_found', __( 'The governed object could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return HE_V2_Auth::rest_permission( $cap, absint( $post_id ), $purpose );
	}

	/**
	 * Defense-in-depth for protected writes that previously checked only a
	 * coarse capability and then accepted an arbitrary object identifier.
	 */
	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'GET' === $request->get_method() ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		$user_id = get_current_user_id();
		global $wpdb;

		/* New entry drafts are restricted to File 06-assigned knowledge types. */
		if ( $route === $prefix . '/entries' && 'POST' === $request->get_method() ) {
			$type = sanitize_key( $request->get_param( 'type' ) );
			if ( ! self::editor_type_allowed( $user_id, $type ) ) {
				return new WP_Error( 'he_editor_type_scope_required', __( 'This editor is not assigned to the requested knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return $response;
		}

		/* Existing entry edit paths: native type scope is required in addition to capability/ownership. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([A-Za-z0-9-]+)/(aliases|references|transition)$#', $route, $match ) ) {
			$concept = HE_V2_Domain::concept_by_id( $match[1], true );
			if ( $concept && ! self::editor_type_allowed( $user_id, $concept['type_slug'] ) && ! HE_V2_Auth::is_founder( $user_id ) ) {
				$state = sanitize_key( $request->get_param( 'state' ) );
				$is_publish_transition = 'transition' === $match[2] && in_array( $state, array( 'approved', 'scheduled', 'published', 'corrected', 'retracted', 'archived' ), true );
				if ( ! $is_publish_transition ) {
					return new WP_Error( 'he_editor_type_scope_required', __( 'This editor is not assigned to this knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
				}
			}
		}

		/* Core entry reviews must be explicitly assigned by scope. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([A-Za-z0-9-]+)/review$#', $route, $match ) ) {
			$concept = HE_V2_Domain::concept_by_id( $match[1], true );
			$scope = sanitize_key( $request->get_param( 'scope' ) ?: 'scientific' );
			if ( ! $concept || ! self::reviewer_assigned( (int) $concept['post_id'], $user_id, $scope ) ) {
				return new WP_Error( 'he_reviewer_assignment_required', __( 'An active reviewer assignment for this scope is required.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return $response;
		}

		/* Core integrity application is a content-object decision, not a global capability-only write. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/(\d+)/apply$#', $route, $match ) ) {
			$concept_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT object_id FROM " . HE_V2_Schema::table( 'integrity_actions' ) . " WHERE id=%d AND object_type='concept'", absint( $match[1] ) ) );
			$concept = $concept_id ? HE_V2_Domain::concept_by_id( $concept_id, true ) : null;
			return $concept ? self::object_permission( HE_V2_Auth::CAP_PUBLISH, (int) $concept['post_id'], 'file06-integrity-apply' ) : new WP_Error( 'he_not_found', __( 'Integrity action not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}

		/* Core research transitions are bound to the actual research post. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/(\d+)/transition$#', $route, $match ) ) {
			$research = self::research_row( $match[1] );
			return $research ? self::object_permission( HE_V2_Auth::CAP_RESEARCH, (int) $research['post_id'], 'file06-research-transition' ) : new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}

		/* Dataset approval must resolve to the governed research object. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/(\d+)/approve$#', $route, $match ) ) {
			$research_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT research_id FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE id=%d', absint( $match[1] ) ) );
			$research = $research_id ? self::research_row( $research_id ) : null;
			return $research ? self::object_permission( HE_V2_Auth::CAP_DATASET, (int) $research['post_id'], 'file06-dataset-approval' ) : new WP_Error( 'he_not_found', __( 'Dataset access request not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}

		/* Future claim creation/update must honor the parent concept's native edit scope. */
		if ( $route === $prefix . '/future/claims' && 'POST' === $request->get_method() ) {
			$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( (string) $request->get_param( 'concept_id' ) ) ) ), ARRAY_A );
			if ( ! $concept ) {
				return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			if ( ! self::editor_type_allowed( $user_id, $concept['type_slug'] ) ) {
				return new WP_Error( 'he_editor_type_scope_required', __( 'This editor is not assigned to this knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return self::object_permission( HE_V2_Auth::CAP_EDIT, (int) $concept['post_id'], 'file06-future-claim-edit' );
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/claims/([0-9a-fA-F-]{36})/(evidence|review)$#', $route, $match ) ) {
			$concept = self::concept_from_claim( $match[1] );
			if ( ! $concept ) {
				return new WP_Error( 'he_not_found', __( 'Claim not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			$permission = self::object_permission( HE_V2_Auth::CAP_REVIEW, (int) $concept['post_id'], 'file06-future-claim-' . $match[2] );
			if ( true !== $permission ) {
				return $permission;
			}
			if ( 'review' === $match[3] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id ) ) {
				return new WP_Error( 'he_reviewer_assignment_required', __( 'An active reviewer assignment is required for this claim decision.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return $response;
		}

		if ( $route === $prefix . '/future/mappings' || $route === $prefix . '/future/duplicates/scan' ) {
			$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( (string) $request->get_param( 'concept_id' ) ) ) ), ARRAY_A );
			return $concept ? self::object_permission( HE_V2_Auth::CAP_REVIEW, (int) $concept['post_id'], 'file06-future-concept-review' ) : new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/impact/([0-9a-fA-F-]{36})$#', $route, $match ) ) {
			$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( $match[1] ) ) ), ARRAY_A );
			return $concept ? self::object_permission( HE_V2_Auth::CAP_REVIEW, (int) $concept['post_id'], 'file06-future-impact' ) : new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/translations/([0-9a-fA-F-]{36})$#', $route, $match ) && 'POST' === $request->get_method() ) {
			$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( $match[1] ) ) ), ARRAY_A );
			if ( ! $concept ) {
				return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			if ( ! self::editor_type_allowed( $user_id, $concept['type_slug'] ) ) {
				return new WP_Error( 'he_editor_type_scope_required', __( 'This editor is not assigned to this knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return self::object_permission( HE_V2_Auth::CAP_EDIT, (int) $concept['post_id'], 'file06-future-translation-edit' );
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/translations/([0-9a-fA-F-]{36})/([A-Za-z0-9-]+)/(review|publish)$#', $route, $match ) ) {
			$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( $match[1] ) ) ), ARRAY_A );
			if ( ! $concept ) {
				return new WP_Error( 'he_not_found', __( 'Translation not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			$cap = 'publish' === $match[3] ? HE_V2_Auth::CAP_PUBLISH : HE_V2_Auth::CAP_REVIEW;
			$permission = self::object_permission( $cap, (int) $concept['post_id'], 'file06-future-translation-' . $match[3] );
			if ( true !== $permission ) {
				return $permission;
			}
			if ( 'review' === $match[2] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id, 'language' ) ) {
				return new WP_Error( 'he_reviewer_assignment_required', __( 'An active language-review assignment is required.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return $response;
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/(\d+)/review$#', $route, $match ) ) {
			$concept = self::concept_for_external_record( $match[1] );
			if ( $concept ) {
				$permission = self::object_permission( HE_V2_Auth::CAP_REVIEW, (int) $concept['post_id'], 'file06-future-external-review' );
				if ( true !== $permission ) {
					return $permission;
				}
				if ( ! self::reviewer_assigned( (int) $concept['post_id'], $user_id ) ) {
					return new WP_Error( 'he_reviewer_assignment_required', __( 'An active reviewer assignment is required for this external-evidence decision.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
				}
			}
			return $response;
		}

		return $response;
	}

}

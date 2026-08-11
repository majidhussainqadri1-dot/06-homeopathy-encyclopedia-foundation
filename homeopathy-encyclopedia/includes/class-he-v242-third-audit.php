<?php
/**
 * File 06 v2.4.2 — third fresh 80-round review hardening.
 *
 * This layer closes defects that remained reachable after v2.4.1: early REST
 * short-circuit authorization, cross-concept version provenance, research
 * admin concurrency/enum validation, public research/post-state parity,
 * canonical-alias language repair, governed hard-delete prevention, pristine
 * composer rollback, research conflict-shape normalization, and dataset state
 * parity. It does not claim staging or live deployment.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Third_Audit {
	const RESEARCH_EXPECTED_VERSION = 'he_v242_expected_research_version';
	private static $composer_rollback = false;

	public static function hooks() {
		/* Must run before v2.2 preflight/integrity filters that can short-circuit callbacks. */
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'early_rest_guard' ), 70, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_rest' ), 360, 3 );

		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_research_admin_write' ), 4, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'research_concurrency_box' ), 60 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'reconcile_manual_research_state' ), 160, 3 );
		add_action( 'save_post_' . HE_V2_Domain::ENTRY_TYPE, array( __CLASS__, 'repair_canonical_alias_language' ), 160, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'language_meta_changed' ), 20, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'language_meta_changed' ), 20, 4 );

		add_filter( 'pre_delete_post', array( __CLASS__, 'guard_hard_delete' ), 1, 3 );
		add_filter( 'pre_trash_post', array( __CLASS__, 'guard_trash' ), 1, 3 );
		add_filter( 'sabri_composer_content_types', array( __CLASS__, 'harden_composer_rollback' ), 1001 );

		add_action( 'template_redirect', array( __CLASS__, 'guard_public_research_route' ), -10 );
	}

	private static function is_uuid( $value ) {
		return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value );
	}

	private static function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => absint( $status ) ) );
	}

	private static function research_row( $id, $public = false ) {
		global $wpdb;
		if ( $public ) {
			return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', sanitize_text_field( (string) $id ) ), ARRAY_A );
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $id ) ), ARRAY_A );
	}

	private static function research_post_is_public( $row ) {
		if ( ! is_array( $row ) || empty( $row['post_id'] ) ) {
			return false;
		}
		$post = get_post( (int) $row['post_id'] );
		return $post && HE_V2_Domain::RESEARCH_TYPE === $post->post_type && 'publish' === $post->post_status;
	}

	private static function assignment_valid( $post_id, $user_id, $scope = '' ) {
		$user_id = absint( $user_id );
		if ( HE_V2_Auth::is_founder( $user_id ) ) {
			return true;
		}
		$assignments = get_post_meta( absint( $post_id ), HE_V241_Governance::META_REVIEW_ASSIGNMENTS, true );
		if ( ! is_array( $assignments ) ) {
			return false;
		}
		$scopes = $scope ? array( sanitize_key( $scope ) ) : array_keys( $assignments );
		foreach ( $scopes as $candidate ) {
			$assignment = isset( $assignments[ $candidate ] ) && is_array( $assignments[ $candidate ] ) ? $assignments[ $candidate ] : array();
			if ( absint( $assignment['reviewer_id'] ?? 0 ) !== $user_id ) {
				continue;
			}
			$expires = ! empty( $assignment['expires_at'] ) ? strtotime( (string) $assignment['expires_at'] ) : 0;
			if ( ! $expires || $expires > time() ) {
				return true;
			}
		}
		return false;
	}

	private static function guard_alias_ambiguity( $identifier ) {
		$identifier = sanitize_text_field( (string) $identifier );
		if ( '' === $identifier || self::is_uuid( $identifier ) || ctype_digit( $identifier ) ) {
			return true;
		}
		global $wpdb;
		$concepts = HE_V2_Schema::table( 'concepts' );
		$exact = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$concepts} WHERE canonical_slug=%s OR public_id=%s LIMIT 1", sanitize_title( $identifier ), $identifier ) );
		if ( $exact ) {
			return true;
		}
		$normalized = HE_V2_Domain::normalize( $identifier );
		if ( ! $normalized ) {
			return true;
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT a.concept_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . $concepts . ' c ON c.id=a.concept_id WHERE a.normalized_alias=%s AND c.merged_into_id=0 LIMIT 3',
			$normalized
		) );
		if ( count( array_unique( array_map( 'absint', $ids ) ) ) > 1 ) {
			return self::error( 'he_alias_ambiguous', __( 'This alias resolves to more than one language-specific concept. Use the canonical public identifier or canonical slug.', 'homeopathy-encyclopedia' ), 409 );
		}
		return true;
	}

	private static function guard_reference_version( WP_REST_Request $request, $identifier ) {
		$data = (array) $request->get_json_params();
		$version_id = absint( $data['version_id'] ?? $request->get_param( 'version_id' ) );
		if ( ! $version_id ) {
			return true;
		}
		$concept = HE_V2_Domain::concept_by_id( $identifier, true );
		if ( ! $concept ) {
			return self::error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), 404 );
		}
		global $wpdb;
		$belongs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', $version_id, (int) $concept['id'] ) );
		return $belongs ? true : self::error( 'he_reference_version_scope', __( 'A reference version must belong to the same canonical concept.', 'homeopathy-encyclopedia' ), 422 );
	}

	private static function integrity_object_guard( WP_REST_Request $request, $action_id, $operation ) {
		global $wpdb;
		$action = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE id=%d', absint( $action_id ) ), ARRAY_A );
		if ( ! $action || ! in_array( $action['object_type'], array( 'concept', 'research' ), true ) ) {
			return self::error( 'he_not_found', __( 'The integrity action is not available.', 'homeopathy-encyclopedia' ), 404 );
		}
		$post_id = 0;
		if ( 'concept' === $action['object_type'] ) {
			$concept = HE_V2_Domain::concept_by_id( (int) $action['object_id'], true );
			$post_id = $concept ? (int) $concept['post_id'] : 0;
		} else {
			$research = self::research_row( (int) $action['object_id'] );
			$post_id = $research ? (int) $research['post_id'] : 0;
		}
		if ( ! $post_id ) {
			return self::error( 'he_not_found', __( 'The governed integrity object is not available.', 'homeopathy-encyclopedia' ), 404 );
		}
		$cap = 'apply' === $operation ? HE_V2_Auth::CAP_PUBLISH : HE_V2_Auth::CAP_REVIEW;
		$permission = HE_V2_Auth::rest_permission( $cap, $post_id, 'file06-integrity-' . sanitize_key( $operation ) );
		if ( true !== $permission ) {
			return $permission;
		}
		if ( 'transition' === $operation ) {
			$state = sanitize_key( $request->get_param( 'state' ) );
			if ( in_array( $state, array( 'under_review', 'accepted', 'rejected' ), true ) && ! self::assignment_valid( $post_id, get_current_user_id() ) ) {
				return self::error( 'he_reviewer_assignment_required', __( 'An active reviewer assignment for this governed object is required before an integrity review decision.', 'homeopathy-encyclopedia' ), 403 );
			}
		}
		return true;
	}

	private static function merge_guard( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		$reason = trim( (string) ( $data['reason'] ?? '' ) );
		if ( '' === $reason ) {
			return self::error( 'he_merge_reason_required', __( 'A documented merge decision reason is required.', 'homeopathy-encyclopedia' ), 422 );
		}
		$source = HE_V2_Domain::concept_by_id( $data['source_id'] ?? '', true );
		$target = HE_V2_Domain::concept_by_id( $data['target_id'] ?? '', true );
		if ( ! $source || ! $target || (int) $source['id'] === (int) $target['id'] ) {
			return self::error( 'he_invalid_merge', __( 'A valid source and target concept are required.', 'homeopathy-encyclopedia' ), 400 );
		}
		foreach ( array( $source, $target ) as $concept ) {
			$permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_TAXONOMY, (int) $concept['post_id'], 'file06-merge-object' );
			if ( true !== $permission ) {
				return $permission;
			}
		}
		return true;
	}

	private static function validate_replacement( WP_REST_Request $request, $object_type, $source_id ) {
		$data = (array) $request->get_json_params();
		$replacement = $data['replacement_id'] ?? 0;
		if ( ! $replacement ) {
			return true;
		}
		if ( 'concept' === $object_type ) {
			$row = HE_V2_Domain::concept_by_id( $replacement, true );
			if ( ! $row || (int) $row['id'] === absint( $source_id ) ) {
				return self::error( 'he_invalid_replacement', __( 'A replacement must identify a different existing canonical concept.', 'homeopathy-encyclopedia' ), 422 );
			}
			return true;
		}
		$row = is_numeric( $replacement ) ? self::research_row( $replacement ) : self::research_row( $replacement, true );
		if ( ! $row || (int) $row['id'] === absint( $source_id ) ) {
			return self::error( 'he_invalid_replacement', __( 'A replacement must identify a different existing research record.', 'homeopathy-encyclopedia' ), 422 );
		}
		return true;
	}

	private static function dataset_post_gate( $research_id ) {
		$row = self::research_row( $research_id );
		if ( ! $row || 'dataset' !== $row['record_type'] || ! in_array( $row['status'], array( 'published', 'corrected' ), true ) || ! self::research_post_is_public( $row ) ) {
			return self::error( 'he_dataset_not_found', __( 'Dataset metadata could not be found.', 'homeopathy-encyclopedia' ), 404 );
		}
		return true;
	}

	private static function research_external_assignment_guard( $record_id ) {
		global $wpdb;
		$record = $wpdb->get_row( $wpdb->prepare( 'SELECT object_type,object_id FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . ' WHERE id=%d', absint( $record_id ) ), ARRAY_A );
		if ( ! $record || 'research' !== $record['object_type'] || empty( $record['object_id'] ) ) {
			return true;
		}
		$research = self::research_row( (int) $record['object_id'] );
		if ( ! $research ) {
			return self::error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), 404 );
		}
		if ( ! self::assignment_valid( (int) $research['post_id'], get_current_user_id() ) ) {
			return self::error( 'he_reviewer_assignment_required', __( 'An active research reviewer assignment is required for this external-evidence decision.', 'homeopathy-encyclopedia' ), 403 );
		}
		return true;
	}

	private static function validate_research_create_conflicts( WP_REST_Request $request ) {
		$data = (array) $request->get_json_params();
		$conflicts = HE_V2_Domain::sanitize_text_list( $data['conflicts'] ?? array() );
		if ( ! $conflicts ) {
			return self::error( 'he_conflict_disclosure_required', __( 'Research submission requires an explicit conflict-of-interest statement, including an explicit declaration when none exists.', 'homeopathy-encyclopedia' ), 422 );
		}
		return true;
	}

	public static function early_rest_guard( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		if ( 0 !== strpos( $route, $prefix ) ) {
			return $response;
		}

		if ( 'GET' === $request->get_method() && $route === $prefix . '/research' ) {
			return rest_ensure_response( self::safe_browse_research( $request ) );
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)(?:/|$)#', $route, $match ) || preg_match( '#^' . preg_quote( $prefix, '#' ) . '/graph/([^/]+)$#', $route, $match ) ) {
			$ambiguous = self::guard_alias_ambiguity( $match[1] );
			if ( is_wp_error( $ambiguous ) ) {
				return $ambiguous;
			}
		}

		if ( 'POST' === $request->get_method() && preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/references$#', $route, $match ) ) {
			$valid = self::guard_reference_version( $request, $match[1] );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		if ( 'POST' === $request->get_method() && preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/(\d+)/(apply|transition)$#', $route, $match ) ) {
			$guard = self::integrity_object_guard( $request, $match[1], $match[2] );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( 'POST' === $request->get_method() && $route === $prefix . '/merge' ) {
			$guard = self::merge_guard( $request );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( 'POST' === $request->get_method() && preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/integrity$#', $route, $match ) ) {
			$concept = HE_V2_Domain::concept_by_id( $match[1], true );
			if ( ! $concept ) {
				return self::error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), 404 );
			}
			$guard = self::validate_replacement( $request, 'concept', (int) $concept['id'] );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( 'POST' === $request->get_method() && preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/(\d+)/integrity$#', $route, $match ) ) {
			$guard = self::validate_replacement( $request, 'research', absint( $match[1] ) );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/datasets/(\d+)/access$#', $route, $match ) ) {
			$guard = self::dataset_post_gate( absint( $match[1] ) );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/(\d+)/approve$#', $route, $match ) ) {
			global $wpdb;
			$research_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT research_id FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE id=%d', absint( $match[1] ) ) );
			$guard = $research_id ? self::dataset_post_gate( $research_id ) : self::error( 'he_dataset_access_not_available', __( 'The dataset access request is not available.', 'homeopathy-encyclopedia' ), 404 );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( 'POST' === $request->get_method() && $route === $prefix . '/research' ) {
			$guard = self::validate_research_create_conflicts( $request );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		if ( 'POST' === $request->get_method() && preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/(\d+)/review$#', $route, $match ) ) {
			$guard = self::research_external_assignment_guard( $match[1] );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}
		return $response;
	}

	public static function after_rest( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request || is_wp_error( $response ) || 'POST' !== $request->get_method() ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		if ( $route !== $prefix . '/research' || ! $response instanceof WP_REST_Response ) {
			return $response;
		}
		$data = $response->get_data();
		$public_id = '';
		if ( is_array( $data ) && isset( $data['data']['id'] ) ) {
			$public_id = sanitize_text_field( (string) $data['data']['id'] );
		} elseif ( is_array( $data ) && isset( $data['id'] ) ) {
			$public_id = sanitize_text_field( (string) $data['id'] );
		}
		if ( ! $public_id ) {
			return $response;
		}
		$row = self::research_row( $public_id, true );
		if ( ! $row ) {
			return $response;
		}
		$input = (array) $request->get_json_params();
		$parts = HE_V2_Domain::sanitize_text_list( $input['conflicts'] ?? array() );
		if ( ! $parts ) {
			return $response;
		}
		$statement = implode( '; ', $parts );
		$none = (bool) preg_match( '/\b(?:no|none)\s+(?:conflict|conflicts)\b/i', $statement );
		global $wpdb;
		$wpdb->update( HE_V2_Schema::table( 'research' ), array(
			'conflicts_json' => wp_json_encode( array( 'recorded' => true, 'statement' => $statement, 'none_declared' => $none ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'row_version' => (int) $row['row_version'] + 1,
			'updated_at' => current_time( 'mysql', true ),
		), array( 'id' => (int) $row['id'], 'row_version' => (int) $row['row_version'] ), array( '%s','%d','%s' ), array( '%d','%d' ) );
		return $response;
	}

	public static function research_concurrency_box() {
		add_meta_box(
			'he-v242-research-concurrency',
			__( 'Governed save version', 'homeopathy-encyclopedia' ),
			array( __CLASS__, 'render_research_concurrency_box' ),
			HE_V2_Domain::RESEARCH_TYPE,
			'side',
			'high'
		);
	}

	public static function render_research_concurrency_box( $post ) {
		global $wpdb;
		$version = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT row_version FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', absint( $post->ID ) ) );
		echo '<input type="hidden" name="' . esc_attr( self::RESEARCH_EXPECTED_VERSION ) . '" value="' . esc_attr( $version ) . '">';
		echo '<p>' . esc_html( sprintf( __( 'Loaded domain row version: %d. A stale editor form is rejected instead of overwriting a newer research record.', 'homeopathy-encyclopedia' ), $version ) ) . '</p>';
	}

	public static function guard_research_admin_write( $data, $postarr ) {
		if ( ! is_admin() || HE_V2_Domain::RESEARCH_TYPE !== ( $data['post_type'] ?? '' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return $data;
		}
		$post_id = absint( $postarr['ID'] ?? ( $_POST['post_ID'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- validation only; canonical save handlers verify their own nonces.
		if ( ! $post_id || wp_is_post_revision( $post_id ) ) {
			return $data;
		}
		$has_governance_form = isset( $_POST['he_v2_research_nonce'] ) || isset( $_POST['he_v22_research_nonce'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $has_governance_form ) {
			wp_die( esc_html__( 'Research records must be edited through the complete File 06 governed editor; Quick Edit, bulk or partial writes are disabled.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 governed write', 'homeopathy-encyclopedia' ), array( 'response' => 409 ) );
		}
		if ( isset( $_POST['he_v2_research_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$type = sanitize_key( wp_unslash( $_POST['he_v2_research_type'] ) );
			if ( ! in_array( $type, array( 'proposal','protocol','publication','successful-case','dataset' ), true ) ) {
				wp_die( esc_html__( 'Invalid File 06 research record type.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 validation', 'homeopathy-encyclopedia' ), array( 'response' => 422 ) );
			}
		}
		if ( isset( $_POST['he_v2_data_class'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$class = sanitize_key( wp_unslash( $_POST['he_v2_data_class'] ) );
			if ( ! in_array( $class, array( 'public','restricted','highly-restricted' ), true ) ) {
				wp_die( esc_html__( 'Invalid File 06 research data class.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 validation', 'homeopathy-encyclopedia' ), array( 'response' => 422 ) );
			}
		}
		global $wpdb;
		$current = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT row_version FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post_id ) );
		if ( $current ) {
			$expected = isset( $_POST[ self::RESEARCH_EXPECTED_VERSION ] ) ? absint( $_POST[ self::RESEARCH_EXPECTED_VERSION ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $expected || $expected !== $current ) {
				wp_die( esc_html__( 'This research record changed after the editor form was loaded. Reload before saving; no stale overwrite was accepted.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 version conflict', 'homeopathy-encyclopedia' ), array( 'response' => 409 ) );
			}
		}
		return $data;
	}

	public static function reconcile_manual_research_state( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post ) {
			return;
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", absint( $post_id ) ), ARRAY_A );
		if ( ! $row || 'published' !== $row['status'] || 'publish' === $post->post_status ) {
			return;
		}
		$approved_reviews = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='research' AND object_id=%d AND decision='approved' AND conflict_declared=0", (int) $row['id'] ) );
		if ( 0 === $approved_reviews ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='proposal',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='published'", (int) $row['id'], (int) $row['row_version'] ) );
			HE_V2_Domain::emit_event( 'ResearchStateFailClosed.v1', 'research', (int) $row['id'], array( 'reason' => 'non-published-wordpress-post-without-approved-review' ) );
		}
	}

	public static function language_meta_changed( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_he_language' === $meta_key && HE_V2_Domain::ENTRY_TYPE === get_post_type( $object_id ) ) {
			self::repair_canonical_alias_language( $object_id, get_post( $object_id ), true );
		}
	}

	public static function repair_canonical_alias_language( $post_id, $post = null, $update = true ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		global $wpdb;
		$concept = $wpdb->get_row( $wpdb->prepare( 'SELECT id,language,created_by FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ), ARRAY_A );
		if ( ! $concept ) {
			return;
		}
		$language = sanitize_text_field( (string) ( get_post_meta( $post_id, '_he_language', true ) ?: $concept['language'] ?: 'en-US' ) );
		if ( $language !== $concept['language'] ) {
			$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'language' => $language, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $concept['id'] ), array( '%s','%s' ), array( '%d' ) );
		}
		$title = get_the_title( $post_id );
		$normalized = HE_V2_Domain::normalize( $title );
		$aliases = HE_V2_Schema::table( 'aliases' );
		$canonical = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$aliases} WHERE concept_id=%d AND alias_type='canonical' ORDER BY id ASC LIMIT 1", (int) $concept['id'] ), ARRAY_A );
		if ( ! $canonical ) {
			HE_V2_Domain::add_alias( (int) $concept['id'], $title, $language, 'canonical', true, (int) $concept['created_by'] );
			return;
		}
		if ( $canonical['language'] === $language && $canonical['normalized_alias'] === $normalized ) {
			return;
		}
		$collision = (int) $wpdb->get_var( $wpdb->prepare( "SELECT concept_id FROM {$aliases} WHERE normalized_alias=%s AND language=%s AND concept_id<>%d LIMIT 1", $normalized, $language, (int) $concept['id'] ) );
		if ( $collision ) {
			HE_V2_Schema::record_runtime_failure( 'canonical_alias_language_collision', 'A canonical alias cannot be moved to the entry language because that normalized alias is already owned by another concept.' );
			return;
		}
		$wpdb->update( $aliases, array( 'alias' => sanitize_text_field( $title ), 'normalized_alias' => $normalized, 'language' => $language, 'is_primary' => 1 ), array( 'id' => (int) $canonical['id'] ), array( '%s','%s','%s','%d' ), array( '%d' ) );
	}

	public static function guard_hard_delete( $delete, $post, $force_delete ) {
		$post = is_object( $post ) ? $post : get_post( $post );
		if ( ! $post || self::$composer_rollback ) {
			return $delete;
		}
		global $wpdb;
		if ( HE_V2_Domain::ENTRY_TYPE === $post->post_type ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', (int) $post->ID ) );
			return $exists ? false : $delete;
		}
		if ( HE_V2_Domain::RESEARCH_TYPE === $post->post_type ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', (int) $post->ID ) );
			return $exists ? false : $delete;
		}
		return $delete;
	}

	public static function guard_trash( $trash, $post, $previous_status ) {
		$post = is_object( $post ) ? $post : get_post( $post );
		if ( ! $post || self::$composer_rollback ) {
			return $trash;
		}
		if ( in_array( $post->post_type, array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), true ) ) {
			return false;
		}
		return $trash;
	}

	public static function harden_composer_rollback( $types ) {
		$types = is_array( $types ) ? $types : array();
		if ( isset( $types['file06_encyclopedia_entry'] ) && is_array( $types['file06_encyclopedia_entry'] ) ) {
			$types['file06_encyclopedia_entry']['rollback_command'] = array( __CLASS__, 'composer_rollback_draft' );
			$types['file06_encyclopedia_entry']['governed_pristine_rollback'] = true;
		}
		return $types;
	}

	private static function future_children_exist( $concept_id ) {
		if ( ! class_exists( 'HE_V24_Future_Schema' ) ) {
			return false;
		}
		global $wpdb;
		foreach ( array( 'claims','external_records','concept_mappings','similarity','freshness','impact_queue','research_gaps','watchlists','translations' ) as $suffix ) {
			$table = HE_V24_Future_Schema::table( $suffix );
			$column = 'similarity' === $suffix ? 'source_concept_id' : 'concept_id';
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column}=%d", absint( $concept_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $exists ) {
				return true;
			}
			if ( 'similarity' === $suffix ) {
				$other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE target_concept_id=%d", absint( $concept_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $other ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function composer_rollback_draft( $native_id, $context = array() ) {
		$row = HE_V2_Domain::concept_by_id( $native_id, true );
		$actor_id = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : get_current_user_id();
		if ( ! $row || 'draft' !== $row['status'] || (int) $row['current_version'] || ! HE_V241_Governance::editor_type_allowed( $actor_id, $row['type_slug'] ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, (int) $row['post_id'], 'file06-composer-rollback', $actor_id ) ) {
			return false;
		}
		if ( self::future_children_exist( (int) $row['id'] ) ) {
			return false;
		}
		global $wpdb;
		$concept_id = (int) $row['id'];
		$wpdb->query( 'START TRANSACTION' );
		try {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d', $concept_id, $concept_id ) );
			foreach ( array( 'aliases','references','versions','search_index','bookmarks' ) as $suffix ) {
				$wpdb->delete( HE_V2_Schema::table( $suffix ), array( 'concept_id' => $concept_id ), array( '%d' ) );
			}
			$wpdb->delete( HE_V2_Schema::table( 'reviews' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) );
			$wpdb->delete( HE_V2_Schema::table( 'integrity_actions' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) );
			$deleted = $wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) );
			if ( 1 !== (int) $deleted ) {
				throw new RuntimeException( 'concept-delete-failed' );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		self::$composer_rollback = true;
		try {
			$deleted_post = wp_delete_post( (int) $row['post_id'], true );
		} finally {
			self::$composer_rollback = false;
		}
		HE_V2_Domain::emit_event( 'EncyclopediaDraftRolledBack.v1', 'concept', $concept_id, array( 'public_id' => $row['public_id'], 'reason' => 'composer-compensation' ) );
		return (bool) $deleted_post;
	}

	private static function public_research_valid( $row ) {
		if ( ! is_array( $row ) || ! in_array( $row['status'], array( 'published','corrected','retracted' ), true ) || ! self::research_post_is_public( $row ) ) {
			return false;
		}
		$validation = HE_V22_Research_Guard::validate_row( $row );
		return ! is_wp_error( $validation );
	}

	private static function public_research_dto( $row ) {
		if ( ! self::public_research_valid( $row ) ) {
			return null;
		}
		$dto = array(
			'id' => $row['public_id'],
			'record_type' => $row['record_type'],
			'status' => $row['status'],
			'title' => $row['title'],
			'question' => $row['question'],
			'case_tag' => $row['case_tag'],
			'canonical_url' => home_url( '/research/' . rawurlencode( $row['public_id'] ) . '/' ),
			'updated_at' => $row['updated_at'],
			'freshness' => array( 'contract_version' => HE_CONTRACT_VERSION, 'updated_at' => $row['updated_at'] ),
		);
		if ( 'retracted' === $row['status'] ) {
			$dto['protocol'] = '';
			$dto['notice'] = __( 'This research record has been retracted. Metadata remains visible for correction and citation integrity.', 'homeopathy-encyclopedia' );
		} else {
			$dto['protocol'] = 'public' === $row['data_class'] ? $row['protocol'] : '';
		}
		if ( 'successful-case' === $row['record_type'] ) {
			$dto['case'] = json_decode( (string) $row['case_json'], true );
		}
		if ( 'dataset' === $row['record_type'] ) {
			$dto['dataset_metadata'] = json_decode( (string) $row['metadata_json'], true );
		}
		return $dto;
	}

	private static function safe_browse_research( WP_REST_Request $request ) {
		global $wpdb;
		$limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$cursor = max( 0, absint( $request->get_param( 'cursor' ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT r.* FROM ' . HE_V2_Schema::table( 'research' ) . ' r INNER JOIN ' . $wpdb->posts . " p ON p.ID=r.post_id AND p.post_type=%s AND p.post_status='publish' WHERE r.status IN ('published','corrected','retracted') AND r.id>%d ORDER BY r.id ASC LIMIT %d",
			HE_V2_Domain::RESEARCH_TYPE, $cursor, $limit + 1
		), ARRAY_A );
		$items = array();
		$last_id = 0;
		$scanned = 0;
		foreach ( $rows as $row ) {
			$last_id = (int) $row['id'];
			++$scanned;
			$dto = self::public_research_dto( $row );
			if ( $dto && count( $items ) < $limit ) {
				$items[] = $dto;
			}
		}
		$has_more = count( $rows ) > $limit;
		return array( 'items' => $items, 'next_cursor' => $has_more && $last_id ? $last_id : null, 'limit' => $limit, 'governance_filtered' => true );
	}

	public static function guard_public_research_route() {
		$public_id = sanitize_text_field( (string) get_query_var( 'he_v22_research_id' ) );
		if ( ! $public_id ) {
			return;
		}
		$row = self::research_row( $public_id, true );
		if ( self::public_research_valid( $row ) ) {
			return;
		}
		set_query_var( 'he_v22_research_id', '' );
		global $wp_query;
		if ( $wp_query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}
}

<?php
/** File 06 v2.4.1 admin/composer/object and maintenance fail-closed guards. */
defined( 'ABSPATH' ) || exit;

final class HE_V241_Runtime_Guard {
	const CORE_LEASE_OPTION = 'he_v241_core_maintenance_lease';
	const CORE_LEASE_TTL = 10 * MINUTE_IN_SECONDS;

	public static function hooks() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_admin_entry_write' ), 5, 2 );
		add_filter( 'sabri_composer_content_types', array( __CLASS__, 'harden_composer_types' ), 999 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 335, 3 );

		/*
		 * The inherited HE_V2_Domain::maintenance() calls publish_due() without
		 * the v2.2 content/review fingerprint gate. Remove that fallback entirely
		 * and run the secure scheduler + housekeeping under one lease.
		 */
		remove_action( 'he_v2_maintenance', array( 'HE_V22_Schedule', 'publish_due_securely' ), 5 );
		remove_action( 'he_v2_maintenance', array( 'HE_V2_Domain', 'maintenance' ) );
		add_action( 'he_v2_maintenance', array( __CLASS__, 'core_maintenance_serialized' ), 5 );
	}

	public static function guard_admin_entry_write( $data, $postarr ) {
		if ( ! is_admin() || HE_V2_Domain::ENTRY_TYPE !== ( $data['post_type'] ?? '' ) || HE_V2_Auth::is_founder() ) {
			return $data;
		}
		/* Autosave/revision buffers are not canonical writes; final save is rechecked. */
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! empty( $postarr['post_type'] ) && 'revision' === $postarr['post_type'] ) {
			return $data;
		}
		$type = isset( $_POST['he_v2_type'] ) ? sanitize_key( wp_unslash( $_POST['he_v2_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the native save handler verifies the same request nonce; this is an earlier fail-closed gate.
		$post_id = absint( $postarr['ID'] ?? 0 );
		if ( ! $type && $post_id ) {
			$type = HE_V2_Domain::taxonomy_slug( $post_id, HE_V2_Domain::TAX_TYPE );
		}
		if ( ! $type || ! HE_V241_Governance::editor_type_allowed( get_current_user_id(), $type ) ) {
			wp_die( esc_html__( 'This editor is not assigned to this File 06 knowledge type.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 authorization', 'homeopathy-encyclopedia' ), array( 'response' => 403 ) );
		}
		return $data;
	}

	public static function harden_composer_types( $types ) {
		$types = is_array( $types ) ? $types : array();
		if ( isset( $types['file06_encyclopedia_entry'] ) && is_array( $types['file06_encyclopedia_entry'] ) ) {
			$types['file06_encyclopedia_entry']['draft_command'] = array( __CLASS__, 'composer_create_draft' );
			$types['file06_encyclopedia_entry']['rollback_command'] = array( __CLASS__, 'composer_rollback_draft' );
			$scope = get_user_meta( get_current_user_id(), HE_V241_Governance::META_EDITOR_TYPES, true );
			$types['file06_encyclopedia_entry']['available'] = HE_V2_Schema::runtime_status() === 'active'
				&& HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT )
				&& ( HE_V2_Auth::is_founder() || ( is_array( $scope ) && ! empty( $scope ) ) );
			$types['file06_encyclopedia_entry']['native_type_scope_required'] = true;
		}
		return $types;
	}

	public static function composer_create_draft( $payload, $context = array() ) {
		$payload = is_array( $payload ) ? $payload : array();
		$actor_id = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : get_current_user_id();
		$type = sanitize_key( $payload['type'] ?? '' );
		if ( ! $actor_id || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, 0, 'file06-composer', $actor_id ) || ! HE_V241_Governance::editor_type_allowed( $actor_id, $type ) ) {
			return new WP_Error( 'he_composer_type_scope_forbidden', __( 'File 06 creation is not authorized for this knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		return HE_V2_Domain::create_entry( $payload, $actor_id );
	}

	public static function composer_rollback_draft( $native_id, $context = array() ) {
		$row = HE_V2_Domain::concept_by_id( $native_id, true );
		$actor_id = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : get_current_user_id();
		if ( ! $row || 'draft' !== $row['status'] || ! HE_V241_Governance::editor_type_allowed( $actor_id, $row['type_slug'] ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, (int) $row['post_id'], 'file06-composer-rollback', $actor_id ) ) {
			return false;
		}
		return (bool) wp_delete_post( (int) $row['post_id'], true );
	}

	private static function research_row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id,post_id,status,created_by FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $id ) ), ARRAY_A );
	}

	private static function concept_from_claim( $id ) {
		global $wpdb;
		$concept_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT concept_id FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE id=%d', absint( $id ) ) );
		return $concept_id ? HE_V24_Future_Schema::concept_row( $concept_id, false ) : null;
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'GET' === $request->get_method() ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		global $wpdb;

		/* External scholarly staging must be scoped to the object it is being attached to. */
		if ( $route === $prefix . '/future/external/lookup' ) {
			$type = sanitize_key( $request->get_param( 'object_type' ) ?: ( $request->get_param( 'concept_id' ) ? 'concept' : '' ) );
			$id = absint( $request->get_param( 'object_id' ) ?: $request->get_param( 'concept_id' ) );
			$post_id = 0;
			if ( 'concept' === $type ) {
				$concept = HE_V24_Future_Schema::concept_row( $id, false );
				$post_id = $concept ? (int) $concept['post_id'] : 0;
			} elseif ( 'claim' === $type ) {
				$concept = self::concept_from_claim( $id );
				$post_id = $concept ? (int) $concept['post_id'] : 0;
			} elseif ( 'research' === $type ) {
				$research = self::research_row( $id );
				$post_id = $research ? (int) $research['post_id'] : 0;
			}
			if ( ! $post_id ) {
				return new WP_Error( 'he_not_found', __( 'The governed scholarly binding could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, $post_id, 'file06-external-stage-object' );
		}

		/* Research-bound external records need object scope too; concept-bound records are checked by HE_V241_Governance. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/(\d+)/review$#', $route, $match ) ) {
			$record = $wpdb->get_row( $wpdb->prepare( 'SELECT object_type,object_id,concept_id FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . ' WHERE id=%d', absint( $match[1] ) ), ARRAY_A );
			if ( ! $record ) {
				return new WP_Error( 'he_not_found', __( 'External scholarly record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			if ( 'research' === $record['object_type'] && ! empty( $record['object_id'] ) ) {
				$research = self::research_row( $record['object_id'] );
				if ( ! $research ) {
					return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
				}
				$permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW, (int) $research['post_id'], 'file06-external-review-research' );
				if ( true !== $permission ) {
					return $permission;
				}
				if ( ! HE_V241_Governance::reviewer_assigned( (int) $research['post_id'], get_current_user_id() ) ) {
					return new WP_Error( 'he_reviewer_assignment_required', __( 'An active File 06 research reviewer assignment is required for this external-evidence decision.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
				}
				return true;
			}
		}
		return $response;
	}

	private static function acquire_lease( $option, $ttl ) {
		global $wpdb;
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'time' => time() );
		if ( add_option( $option, $value, '', false ) ) { return $token; }
		$existing = get_option( $option, array() );
		if ( ! is_array( $existing ) || empty( $existing['time'] ) || ( time() - absint( $existing['time'] ) ) <= absint( $ttl ) ) { return ''; }
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
			$option, maybe_serialize( $existing )
		) );
		return 1 === (int) $deleted && add_option( $option, $value, '', false ) ? $token : '';
	}

	private static function release_lease( $option, $token ) {
		global $wpdb;
		$current = get_option( $option, array() );
		if ( is_array( $current ) && ! empty( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
				$option, maybe_serialize( $current )
			) );
		}
	}

	public static function core_maintenance_serialized() {
		$token = self::acquire_lease( self::CORE_LEASE_OPTION, self::CORE_LEASE_TTL );
		if ( ! $token ) {
			return;
		}
		try {
			/* The secure scheduler owns all due-publication decisions. */
			HE_V22_Schedule::publish_due_securely();

			global $wpdb;
			$now = current_time( 'mysql', true );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'idempotency' ) . ' WHERE expires_at<%s LIMIT 1000', $now ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'rate_limits' ) . ' WHERE expires_at<%s LIMIT 1000', $now ) );
			$wpdb->query( $wpdb->prepare( "UPDATE " . HE_V2_Schema::table( 'dataset_access' ) . " SET status='expired',updated_at=%s WHERE status='approved' AND expires_at IS NOT NULL AND expires_at<%s LIMIT 1000", $now, $now ) );
			HE_V2_Integrations::process_outbox( 50 );
		} finally {
			self::release_lease( self::CORE_LEASE_OPTION, $token );
		}
	}
}

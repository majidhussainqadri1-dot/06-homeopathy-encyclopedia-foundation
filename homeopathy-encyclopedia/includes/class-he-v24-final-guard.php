<?php
/** File 06 v2.4 final guard for Audit-80 authorization, idempotency and public-state truth. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Final_Guard {
	const PARAM_RESERVATION = '_he_v24_idempotency_reservation';

	public static function hooks() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 1, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'post_dispatch' ), 200, 3 );
	}

	private static function route( WP_REST_Request $request ) {
		return (string) $request->get_route();
	}

	private static function is_future( $route ) {
		return 0 === strpos( $route, '/' . HE_V2_API::NS . '/future/' );
	}

	private static function concept_id_from_public_route( $route, WP_REST_Request $request ) {
		if ( '/' . HE_V2_API::NS . '/future/claims' === $route ) {
			return absint( $request->get_param( 'concept_id' ) );
		}
		if ( preg_match( '#/future/(?:graph|time-machine|freshness|citations|translations)/(?P<id>\d+)#', $route, $m ) ) {
			return absint( $m['id'] );
		}
		return 0;
	}

	private static function fully_public_concept( $concept_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT c.status,c.review_status,c.safety_status,c.merged_into_id,p.post_status FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c LEFT JOIN ' . $wpdb->posts . ' p ON p.ID=c.post_id WHERE c.id=%d',
			absint( $concept_id )
		), ARRAY_A );
		return $row
			&& in_array( $row['status'], array( 'published', 'corrected' ), true )
			&& 'approved' === $row['review_status']
			&& 'approved' === $row['safety_status']
			&& empty( $row['merged_into_id'] )
			&& 'publish' === $row['post_status'];
	}

	private static function route_permission( $route, WP_REST_Request $request ) {
		if ( '/' . HE_V2_API::NS . '/future/claims' === $route ) {
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT );
		}
		if ( preg_match( '#/future/claims/\d+/evidence$#', $route ) ) {
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW );
		}
		if ( '/' . HE_V2_API::NS . '/future/external/lookup' === $route ) {
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH );
		}
		if ( '/' . HE_V2_API::NS . '/future/retraction-watch' === $route || '/' . HE_V2_API::NS . '/future/mappings' === $route || '/' . HE_V2_API::NS . '/future/duplicates/scan' === $route ) {
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW );
		}
		if ( preg_match( '#/future/impact/\d+$#', $route ) ) {
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW );
		}
		if ( '/' . HE_V2_API::NS . '/future/watchlist' === $route ) {
			return is_user_logged_in() && HE_V2_Auth::membership_allowed()
				? true
				: new WP_Error( 'he_auth_required', __( 'An eligible signed-in account is required.', 'homeopathy-encyclopedia' ), array( 'status' => 401 ) );
		}
		if ( preg_match( '#/future/translations/\d+/transition$#', $route ) ) {
			$state = sanitize_key( $request->get_param( 'state' ) );
			return HE_V2_Auth::rest_permission( 'published' === $state ? HE_V2_Auth::CAP_PUBLISH : HE_V2_Auth::CAP_REVIEW );
		}
		if ( preg_match( '#/future/translations/\d+$#', $route ) ) {
			return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT );
		}
		return true;
	}

	public static function pre_dispatch( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		if ( null !== $result ) {
			return $result;
		}
		$route = self::route( $request );
		if ( ! self::is_future( $route ) ) {
			return $result;
		}

		// Public read routes must never expose unreviewed, unsafe, merged or draft concepts.
		if ( 'GET' === $request->get_method() ) {
			$concept_id = self::concept_id_from_public_route( $route, $request );
			if ( $concept_id && ! self::fully_public_concept( $concept_id ) ) {
				return new WP_Error( 'he_not_found', __( 'The requested knowledge object is not publicly available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			if ( preg_match( '#/future/provenance/[^/]+/[^/]+$#', $route ) ) {
				$permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW );
				if ( is_wp_error( $permission ) ) {
					return $permission;
				}
			}
			if ( '/' . HE_V2_API::NS . '/future/command-center' === $route ) {
				$permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW );
				if ( is_wp_error( $permission ) ) {
					return $permission;
				}
				return self::command_center();
			}
			return $result;
		}

		// Every future mutation revalidates identity/capability before idempotent replay.
		$permission = self::route_permission( $route, $request );
		if ( is_wp_error( $permission ) || true !== $permission ) {
			return is_wp_error( $permission ) ? $permission : new WP_Error( 'he_forbidden', __( 'This action is not permitted.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		$user_id = get_current_user_id();
		if ( ! HE_V2_Domain::rate_allow( 'future-rest:' . md5( $route ) . ':' . $user_id, 60, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$reservation = HE_V2_Domain::idempotent_begin( $user_id, 'future:' . md5( $route ), $key, $request->get_json_params() ?: $request->get_params() );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( ! empty( $reservation['replay'] ) ) {
			return new WP_REST_Response( $reservation['body'], (int) $reservation['code'] );
		}
		if ( isset( $reservation['id'] ) ) {
			$request->set_param( self::PARAM_RESERVATION, absint( $reservation['id'] ) );
		}
		return $result;
	}

	public static function post_dispatch( $response, WP_REST_Server $server, WP_REST_Request $request ) {
		$route = self::route( $request );
		if ( ! self::is_future( $route ) || 'GET' === $request->get_method() ) {
			return $response;
		}
		$reservation_id = absint( $request->get_param( self::PARAM_RESERVATION ) );
		if ( ! $reservation_id ) {
			return $response;
		}
		$status = $response instanceof WP_REST_Response ? $response->get_status() : 200;
		$body = $response instanceof WP_REST_Response ? $response->get_data() : $response;
		if ( is_wp_error( $response ) ) {
			$data = $response->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			$body = array( 'code' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $data );
		}
		HE_V2_Domain::idempotent_finish( $reservation_id, $status, $body );
		return $response;
	}

	private static function command_center() {
		global $wpdb;
		$claims = self::table( 'claims' );
		$freshness = self::table( 'freshness' );
		$gaps = self::table( 'research_gaps' );
		$similarity = self::table( 'similarity' );
		$external = self::table( 'external_records' );
		$translations = self::table( 'translations' );
		$impact = self::table( 'impact_queue' );
		$watch = self::table( 'watchlists' );
		return rest_ensure_response( array(
			'stale_or_urgent' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$freshness} WHERE freshness_state IN ('stale','urgent-review')" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'research_gaps' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$gaps} WHERE state='open'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'duplicate_candidates' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$similarity} WHERE state='candidate'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'urgent_external_reviews' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$external} WHERE review_required=1 AND status='urgent-review'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'unreviewed_claims' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$claims} WHERE evidence_state='ungraded' OR claim_state<>'active'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'outdated_translations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$translations} WHERE status='translation-outdated'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'active_watch_relations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$watch} WHERE active=1" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'pending_impacts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$impact} WHERE impact_state IN ('pending','retry')" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'dead_letter_impacts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$impact} WHERE impact_state='dead-letter'" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'source_of_truth' => 'file-06',
			'notification_delivery_owner' => 'file-19',
			'security_assurance_owner' => 'file-24',
			'visual_owner' => 'file-25',
			'autonomous_high_risk_actions' => false,
		) );
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'he_' . sanitize_key( $name );
	}
}

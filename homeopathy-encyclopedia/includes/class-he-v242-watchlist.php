<?php
/** File 06 v2.4.2 governed knowledge watchlists; File 19 remains delivery owner. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Watchlist {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'override_route' ), 520 );
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'contract' ), 530 );
	}

	public static function override_route() {
		if ( ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Migration_Safety::ready() ) { return; }
		register_rest_route( HE_V2_API::NS, '/future/watchlist', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'read' ), 'permission_callback' => array( __CLASS__, 'permission' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'write' ), 'permission_callback' => array( __CLASS__, 'permission' ) ),
		), true );
	}

	public static function permission() {
		if ( ! is_user_logged_in() || ! HE_V2_Auth::membership_allowed() ) {
			return new WP_Error( 'he_auth_required', __( 'An active platform account is required for knowledge watchlists.', 'homeopathy-encyclopedia' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
		}
		return true;
	}

	private static function resolve_object( $type, $id ) {
		$type = sanitize_key( $type );
		$id = sanitize_text_field( (string) $id );
		if ( 'concept' === $type ) {
			$row = HE_V2_Domain::concept_by_id( $id, false );
			return $row ? array( 'type' => 'concept', 'id' => $row['public_id'], 'label' => get_the_title( (int) $row['post_id'] ) ) : null;
		}
		if ( 'topic' === $type ) {
			$term = ctype_digit( $id ) ? get_term( absint( $id ), HE_V2_Domain::TAX_TOPIC ) : get_term_by( 'slug', sanitize_title( $id ), HE_V2_Domain::TAX_TOPIC );
			return $term && ! is_wp_error( $term ) ? array( 'type' => 'topic', 'id' => $term->slug, 'label' => $term->name ) : null;
		}
		if ( 'research' === $type ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $id ), ARRAY_A );
			$post = $row ? get_post( (int) $row['post_id'] ) : null;
			return $row && $post && 'publish' === $post->post_status && HE_V22_Research_Guard::public_surface_eligible( $row ) ? array( 'type' => 'research', 'id' => $row['public_id'], 'label' => get_the_title( $post ) ) : null;
		}
		return null;
	}

	private static function begin( WP_REST_Request $request ) {
		if ( ! HE_V2_Auth::require_nonce( $request ) ) { return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		if ( ! HE_V2_Domain::rate_allow( 'v242-watchlist:' . get_current_user_id(), 60, MINUTE_IN_SECONDS ) ) { return new WP_Error( 'he_rate_limited', __( 'Too many watchlist requests. Retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) ); }
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( strlen( $key ) < 8 || strlen( $key ) > 128 ) { return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), 'future-watchlist', $key, $request->get_json_params() ?: $request->get_params() );
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

	public static function read() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT object_type,object_id,event_mask,created_at,updated_at FROM ' . HE_V24_Future_Schema::table( 'watchlists' ) . ' WHERE user_id=%d AND active=1 ORDER BY updated_at DESC LIMIT 500', get_current_user_id() ), ARRAY_A );
		return rest_ensure_response( array( 'items' => $rows, 'delivery_owner' => 'file-19', 'private' => true ) );
	}

	public static function write( WP_REST_Request $request ) {
		$reservation = self::begin( $request );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null ); }
		$data = $request->get_json_params(); $data = is_array( $data ) ? $data : $request->get_params();
		$object = self::resolve_object( $data['object_type'] ?? '', $data['object_id'] ?? '' );
		if ( ! $object ) { return self::finish( $reservation, new WP_Error( 'he_future_watch_invalid', __( 'The watchlist object is unavailable or not a governed File 06 public object.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$mask = sanitize_text_field( (string) ( $data['event_mask'] ?? 'update,correction,retraction,freshness,evidence' ) );
		$allowed = array( 'update','correction','retraction','freshness','evidence','translation' );
		$events = array_values( array_unique( array_intersect( $allowed, array_map( 'sanitize_key', preg_split( '/[,\s]+/', $mask ) ) ) ) );
		if ( ! $events ) { return self::finish( $reservation, new WP_Error( 'he_watch_event_mask_invalid', __( 'Select at least one governed knowledge event.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$active = ! array_key_exists( 'active', $data ) ? true : (bool) rest_sanitize_boolean( $data['active'] );
		$now = current_time( 'mysql', true );
		global $wpdb; $table = HE_V24_Future_Schema::table( 'watchlists' );
		$ok = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (user_id,object_type,object_id,event_mask,active,created_at,updated_at) VALUES (%d,%s,%s,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE event_mask=VALUES(event_mask),active=VALUES(active),updated_at=VALUES(updated_at)",
			get_current_user_id(), $object['type'], $object['id'], implode( ',', $events ), $active ? 1 : 0, $now, $now
		) );
		if ( false === $ok ) { return self::finish( $reservation, new WP_Error( 'he_watch_write_failed', __( 'The knowledge watch could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		/* Watch preferences are private account data; do not copy them into public/general provenance streams. */
		return self::finish( $reservation, array( 'saved' => true, 'object_type' => $object['type'], 'object_id' => $object['id'], 'events' => $events, 'active' => $active, 'delivery_owner' => 'file-19', 'privacy_minimized' => true ) );
	}

	public static function contract( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		if ( isset( $contracts['file-06'] ) && is_array( $contracts['file-06'] ) ) {
			$contracts['file-06']['watchlists'] = array( 'objects' => array( 'concept','topic','research' ), 'private' => true, 'delivery_owner' => 'file-19', 'idempotent_write' => true, 'validated_public_objects_only' => true, 'excluded_from_public_provenance' => true );
		}
		return $contracts;
	}
}

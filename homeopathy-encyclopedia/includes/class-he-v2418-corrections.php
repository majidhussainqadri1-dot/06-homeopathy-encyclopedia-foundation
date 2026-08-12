<?php
/** File 06 v2.4.18 nineteenth-cycle corrective governance layer. */
defined( 'ABSPATH' ) || exit;

final class HE_V2418_Corrections {
	public static function hooks() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 65, 3 );
	}

	private static function uuid_pattern() {
		return '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
	}

	private static function integrity_subject_post_id( $action ) {
		if ( ! is_array( $action ) ) { return 0; }
		if ( 'concept' === ( $action['object_type'] ?? '' ) ) {
			$concept = HE_V2_Domain::concept_by_id( (int) $action['object_id'], true );
			return $concept ? (int) $concept['post_id'] : 0;
		}
		if ( 'research' === ( $action['object_type'] ?? '' ) ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', (int) $action['object_id'] ) );
		}
		return 0;
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'GET' === $request->get_method() ) { return $response; }
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/(' . self::uuid_pattern() . ')/transition$#', $route, $match ) ) {
			$state = sanitize_key( $request->get_param( 'state' ) );
			if ( in_array( $state, array( 'under_review', 'accepted', 'rejected' ), true ) ) {
				global $wpdb;
				$public_id = strtolower( sanitize_text_field( (string) $match[1] ) );
				$action = $wpdb->get_row( $wpdb->prepare( 'SELECT object_type,object_id FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
				$post_id = self::integrity_subject_post_id( $action );
				if ( ! $post_id ) { return new WP_Error( 'he_not_found', __( 'The governed integrity object is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
				if ( ! HE_V241_Governance::reviewer_assigned( $post_id, get_current_user_id() ) ) {
					return new WP_Error( 'he_reviewer_assignment_required', __( 'An active reviewer assignment is required before this integrity review decision.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
				}
			}
		}
		return $response;
	}
}

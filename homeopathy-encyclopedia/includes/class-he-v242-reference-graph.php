<?php
/** File 06 v2.4.2 reference-rights and relationship provenance validation. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Reference_Graph {
	public static function hooks() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 68, 3 );
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) { return $response; }
		$route = $request->get_route(); $prefix = '/' . HE_V2_API::NS;
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/graph/([^/]+)$#', $route, $match ) ) {
			$reference_token = sanitize_text_field( (string) $request->get_param( 'reference_id' ) );
			$reference_id = HE_V2_Domain::decode_public_cursor( 'reference', $reference_token );
			if ( null === $reference_id || ! $reference_id ) {
				return new WP_Error( 'he_relation_provenance_required', __( 'Every knowledge-graph relationship requires the opaque reference identifier returned by the governed reference command.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			$source = HE_V2_Domain::concept_by_id( $match[1], true );
			if ( ! $source ) { return new WP_Error( 'he_not_found', __( 'Source concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
			global $wpdb;
			$reference = $wpdb->get_row( $wpdb->prepare( 'SELECT id,version_id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d', $reference_id, (int) $source['id'] ), ARRAY_A );
			if ( ! $reference || ( (int) $reference['version_id'] !== 0 && (int) $reference['version_id'] !== (int) $source['current_version'] ) || ( ! $source['current_version'] && (int) $reference['version_id'] !== 0 ) ) { return new WP_Error( 'he_relation_provenance_invalid', __( 'Relationship provenance must be pending for the next source snapshot or bound to the current source version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/references$#', $route ) ) {
			$data = (array) $request->get_json_params();
			$rights = sanitize_key( $data['rights_status'] ?? 'citation-only' );
			$allowed = array( 'citation-only','public-domain','licensed','permission','fair-use','restricted','unknown' );
			if ( ! in_array( $rights, $allowed, true ) ) {
				return new WP_Error( 'he_reference_rights_invalid', __( 'Reference rights status is outside the governed rights vocabulary.', 'homeopathy-encyclopedia' ), array( 'status' => 422, 'allowed' => $allowed ) );
			}
			if ( absint( $data['quotation_word_count'] ?? 0 ) > 25 ) {
				return new WP_Error( 'he_reference_quote_limit', __( 'Stored quotation metadata may not claim more than 25 quoted words for one source.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		return $response;
	}
}

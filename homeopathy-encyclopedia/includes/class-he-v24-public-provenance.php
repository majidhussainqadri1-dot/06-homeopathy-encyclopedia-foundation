<?php
/** Public provenance policy: canonical public IDs only, no internal-object enumeration. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Public_Provenance {
	public static function hooks() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 300, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_callbacks' ), 300, 3 );
	}

	private static function route_pattern() {
		return '#^/' . preg_quote( HE_V2_API::NS, '#' ) . '/future/provenance/(concept|claim)/([a-fA-F0-9-]{36})$#';
	}

	private static function any_provenance_pattern() {
		return '#^/' . preg_quote( HE_V2_API::NS, '#' ) . '/future/provenance/([^/]+)/([^/]+)$#';
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'GET' !== $request->get_method() ) {
			return $response;
		}
		$route = $request->get_route();
		if ( ! preg_match( self::any_provenance_pattern(), $route ) ) {
			return $response;
		}
		if ( ! preg_match( self::route_pattern(), $route, $match ) ) {
			return new WP_Error( 'he_public_provenance_scope', __( 'Public provenance is available only for canonical public concept or approved claim identifiers.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$type = $match[1];
		$public_id = strtolower( $match[2] );
		global $wpdb;
		$internal_id = 0;
		if ( 'concept' === $type ) {
			$internal_id = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE public_id=%s AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0",
				$public_id
			) );
		} else {
			$internal_id = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT cl.id FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' cl INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=cl.concept_id WHERE cl.public_id=%s AND cl.claim_state='active' AND cl.review_status='approved' AND cl.version_id=c.current_version AND EXISTS (SELECT 1 FROM " . HE_V24_Future_Schema::table( 'claim_evidence' ) . " e WHERE e.claim_id=cl.id) AND c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 AND c.current_version>0",
				$public_id
			) );
		}
		if ( ! $internal_id ) {
			return new WP_Error( 'he_not_found', __( 'The requested public provenance record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$request->set_param( '_he_public_provenance_id', $public_id );
		$request->set_param( '_he_public_provenance_type', $type );
		$request->set_param( 'id', (string) $internal_id );
		return $response;
	}

	public static function after_callbacks( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response || is_wp_error( $response ) ) {
			return $response;
		}
		$public_id = sanitize_text_field( (string) $request->get_param( '_he_public_provenance_id' ) );
		$type = sanitize_key( (string) $request->get_param( '_he_public_provenance_type' ) );
		if ( ! $public_id || ! in_array( $type, array( 'concept','claim' ), true ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as &$node ) {
				if ( ! is_array( $node ) ) { continue; }
				$node['prov:specializationOf'] = $type . ':' . $public_id;
				$node = self::strip_internal_ids( $node );
			}
			$response->set_data( $data );
			return $response;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as &$row ) {
				if ( ! is_array( $row ) ) { continue; }
				$row['object_type'] = $type;
				$row['object_id'] = $public_id;
				if ( isset( $row['metadata'] ) ) {
					$row['metadata'] = self::strip_internal_ids( $row['metadata'] );
				}
			}
			$response->set_data( $data );
		}
		return $response;
	}

	private static function strip_internal_ids( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		$out = array();
		foreach ( $value as $key => $item ) {
			$name = (string) $key;
			if ( preg_match( '/(?:^|_)(?:concept|version|reference|claim|research|user|actor|reviewer|translator)_?id$/', $name ) && ! in_array( $name, array( 'external_id','public_id' ), true ) ) {
				continue;
			}
			$out[ $key ] = is_array( $item ) ? self::strip_internal_ids( $item ) : $item;
		}
		return $out;
	}
}

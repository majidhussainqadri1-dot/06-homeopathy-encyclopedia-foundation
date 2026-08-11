<?php
/** Type-specific structured-field contracts for the sixteen fixed knowledge types. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Type_Schemas {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ), 60 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'validate_transition' ), 88, 3 );
		add_filter( 'sabri_composer_content_types', array( __CLASS__, 'composer_schema' ), 120 );
		add_action( 'save_post_' . HE_V2_Domain::ENTRY_TYPE, array( __CLASS__, 'save_entry_meta' ), 45, 2 );
	}

	public static function schemas() {
		return array(
			'remedy' => array( 'required' => array( 'source', 'key_points', 'modalities', 'safety', 'limitations', 'emergency_boundary' ), 'optional' => array( 'symptoms', 'causes', 'red_flags', 'evidence_summary' ), 'body_system_required' => false ),
			'symptom' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'limitations', 'emergency_boundary', 'evidence_summary' ), 'body_system_required' => true ),
			'health-condition' => array( 'required' => array( 'source', 'key_points', 'causes', 'red_flags', 'limitations', 'emergency_boundary' ), 'optional' => array( 'symptoms', 'modalities', 'safety', 'evidence_summary' ), 'body_system_required' => true ),
			'anatomy' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'symptoms', 'causes', 'limitations', 'evidence_summary' ), 'body_system_required' => true ),
			'pathology' => array( 'required' => array( 'source', 'key_points', 'causes', 'red_flags', 'limitations', 'emergency_boundary' ), 'optional' => array( 'symptoms', 'modalities', 'safety', 'evidence_summary' ), 'body_system_required' => true ),
			'body-system' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'symptoms', 'causes', 'limitations', 'evidence_summary' ), 'body_system_required' => true ),
			'cause-etiology' => array( 'required' => array( 'source', 'key_points', 'causes' ), 'optional' => array( 'symptoms', 'modalities', 'safety', 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'modalities' => array( 'required' => array( 'source', 'key_points', 'modalities' ), 'optional' => array( 'symptoms', 'causes', 'safety', 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'clinical-terminology' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'symptoms', 'causes', 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'nutrition' => array( 'required' => array( 'source', 'key_points', 'safety', 'limitations' ), 'optional' => array( 'causes', 'modalities', 'red_flags', 'emergency_boundary', 'evidence_summary' ), 'body_system_required' => false ),
			'principles-hygiene' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'safety', 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'islamic-spiritual-healing' => array( 'required' => array( 'source', 'key_points', 'limitations' ), 'optional' => array( 'safety', 'emergency_boundary', 'evidence_summary' ), 'body_system_required' => false ),
			'homeopathy-philosophy' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'modalities', 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'historical-person' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'book-reference' => array( 'required' => array( 'source', 'key_points' ), 'optional' => array( 'limitations', 'evidence_summary' ), 'body_system_required' => false ),
			'research-reference' => array( 'required' => array( 'source', 'key_points', 'evidence_summary' ), 'optional' => array( 'limitations', 'safety' ), 'body_system_required' => false ),
		);
	}

	public static function routes() {
		register_rest_route( HE_V2_API::NS, '/schemas', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => function() { return rest_ensure_response( self::schemas() ); },
			'permission_callback' => '__return_true',
		) );
	}

	private static function filled( $value ) {
		if ( is_array( $value ) ) {
			return (bool) array_filter( $value, static function( $item ) { return '' !== trim( (string) $item ); } );
		}
		return '' !== trim( wp_strip_all_tags( (string) $value ) );
	}

	public static function validate_concept( $row ) {
		if ( ! is_array( $row ) || empty( $row['post_id'] ) || empty( $row['type_slug'] ) ) {
			return new WP_Error( 'he_type_schema_missing', __( 'The knowledge type schema could not be resolved.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$schemas = self::schemas();
		if ( ! isset( $schemas[ $row['type_slug'] ] ) ) {
			return new WP_Error( 'he_type_schema_missing', __( 'The knowledge type is not part of the fixed governed taxonomy.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$base = HE_V2_Domain::validate_for_review( (int) $row['id'] );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$fields = get_post_meta( (int) $row['post_id'], '_he_structured', true );
		$fields = is_array( $fields ) ? $fields : array();
		$missing = array();
		foreach ( $schemas[ $row['type_slug'] ]['required'] as $field ) {
			if ( ! array_key_exists( $field, $fields ) || ! self::filled( $fields[ $field ] ) ) {
				$missing[] = $field;
			}
		}
		if ( ! empty( $schemas[ $row['type_slug'] ]['body_system_required'] ) ) {
			$system = HE_V2_Domain::taxonomy_slug( (int) $row['post_id'], HE_V2_Domain::TAX_SYSTEM );
			if ( ! $system || 'not-applicable' === $system ) {
				$missing[] = 'body_system';
			}
		}
		if ( $missing ) {
			return new WP_Error( 'he_type_schema_validation_failed', __( 'Required fields for this knowledge type are incomplete.', 'homeopathy-encyclopedia' ), array( 'status' => 422, 'fields' => array_values( array_unique( $missing ) ), 'type' => $row['type_slug'] ) );
		}
		return true;
	}

	/** The inherited admin callback runs before concept materialization on a first save; repeat safely after priority 30. */
	public static function save_entry_meta( $post_id, $post ) {
		if ( ! isset( $_POST['he_v2_entry_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_entry_meta_nonce'] ) ), 'he_v2_entry_meta' ) ) {
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, $post_id, 'entry-admin-save' ) ) {
			return;
		}
		$fields = array();
		foreach ( array( 'source','key_points','symptoms','causes','modalities','red_flags','safety','limitations','emergency_boundary','evidence_summary' ) as $field ) {
			$fields[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ 'he_' . $field ] ?? '' ) );
		}
		update_post_meta( $post_id, '_he_structured', $fields );
		$safety = sanitize_key( wp_unslash( $_POST['he_safety_status'] ?? 'pending' ) );
		update_post_meta( $post_id, '_he_safety_status', in_array( $safety, array( 'pending','approved','restricted' ), true ) ? $safety : 'pending' );
		global $wpdb;
		$concept_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ) );
		if ( $concept_id ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'concepts' ) . ' SET row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d', $concept_id ) );
			HE_V22_Governance::reindex_concept_secure( $concept_id );
		}
	}

	public static function validate_transition( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/(?:review|transition)$#', $route, $m ) ) {
			$validate = true;
			if ( false !== strpos( $route, '/transition' ) ) {
				$state = sanitize_key( $request->get_param( 'state' ) );
				$validate = in_array( $state, array( 'review', 'approved', 'scheduled', 'published' ), true );
			}
			if ( $validate ) {
				$row = HE_V2_Domain::concept_by_id( $m[1], true );
				$result = $row ? self::validate_concept( $row ) : new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		return $response;
	}

	public static function composer_schema( $types ) {
		$types = is_array( $types ) ? $types : array();
		if ( isset( $types['file06_encyclopedia_entry'] ) ) {
			$types['file06_encyclopedia_entry']['fixed_type_schemas'] = self::schemas();
			$types['file06_encyclopedia_entry']['schema_contract_version'] = HE_CONTRACT_VERSION;
		}
		return $types;
	}
}

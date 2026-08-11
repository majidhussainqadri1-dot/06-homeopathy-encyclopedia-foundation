<?php
/** Complete research proposal/case/dataset governance for REST and wp-admin paths. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Research_Guard {
	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ), 30 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'save_meta' ), 45, 2 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'validate_transition' ), 86, 3 );
	}

	public static function meta_boxes() {
		add_meta_box(
			'he-v22-research-completeness',
			__( 'Research Completeness — File 06 v2.2', 'homeopathy-encyclopedia' ),
			array( __CLASS__, 'box' ),
			HE_V2_Domain::RESEARCH_TYPE,
			'normal',
			'high'
		);
	}

	private static function row_for_post( $post_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', absint( $post_id ) ), ARRAY_A );
	}

	public static function box( $post ) {
		$row = self::row_for_post( $post->ID );
		$investigators = $row ? json_decode( (string) $row['investigators_json'], true ) : array();
		$conflicts = $row ? json_decode( (string) $row['conflicts_json'], true ) : array();
		$case = $row ? json_decode( (string) $row['case_json'], true ) : array();
		$metadata = $row ? json_decode( (string) $row['metadata_json'], true ) : array();
		wp_nonce_field( 'he_v22_research_completeness', 'he_v22_research_nonce' );
		$investigator_text = '';
		if ( is_array( $investigators ) ) {
			$names = array();
			foreach ( $investigators as $investigator ) {
				$names[] = is_array( $investigator ) ? (string) ( $investigator['name'] ?? '' ) : (string) $investigator;
			}
			$investigator_text = implode( "\n", array_filter( $names ) );
		}
		$conflict_text = '';
		if ( is_array( $conflicts ) ) {
			if ( ! empty( $conflicts['statement'] ) ) {
				$conflict_text = (string) $conflicts['statement'];
			} elseif ( ! empty( $conflicts['none_declared'] ) ) {
				$conflict_text = __( 'No conflict declared.', 'homeopathy-encyclopedia' );
			}
		}
		?>
		<div class="he-v2 he-v2__admin-grid">
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Investigators — one name per line', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v22_investigators" rows="4"><?php echo esc_textarea( $investigator_text ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Conflict-of-interest disclosure', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v22_conflicts" rows="3"><?php echo esc_textarea( $conflict_text ); ?></textarea><small><?php esc_html_e( 'State any conflict, or explicitly state that none is declared.', 'homeopathy-encyclopedia' ); ?></small></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Successful-case observation label', 'homeopathy-encyclopedia' ); ?></span><input name="he_v22_observation_label" value="<?php echo esc_attr( $case['observation_label'] ?? '' ); ?>"></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset description', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v22_dataset_description" rows="3"><?php echo esc_textarea( $metadata['description'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset de-identification method', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v22_dataset_deidentification" rows="3"><?php echo esc_textarea( $metadata['de_identification'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset lawful basis / consent basis', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v22_dataset_lawful_basis" rows="3"><?php echo esc_textarea( $metadata['lawful_basis'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset access policy', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v22_dataset_access_policy" rows="3"><?php echo esc_textarea( $metadata['access_policy'] ?? '' ); ?></textarea></label>
		</div>
		<?php
	}

	private static function lines( $value ) {
		$items = preg_split( '/\r\n|\r|\n/u', (string) $value );
		$out = array();
		foreach ( (array) $items as $item ) {
			$item = sanitize_text_field( $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_slice( $out, 0, 50 );
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['he_v22_research_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v22_research_nonce'] ) ), 'he_v22_research_completeness' ) ) {
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, $post_id, 'research-admin-save' ) ) {
			return;
		}
		$row = self::row_for_post( $post_id );
		if ( ! $row ) {
			return;
		}
		$investigators = self::lines( wp_unslash( $_POST['he_v22_investigators'] ?? '' ) );
		$conflict_statement = sanitize_textarea_field( wp_unslash( $_POST['he_v22_conflicts'] ?? '' ) );
		$conflicts = array( 'recorded' => '' !== trim( $conflict_statement ), 'statement' => $conflict_statement, 'none_declared' => false !== stripos( $conflict_statement, 'no conflict' ) );
		$case = json_decode( (string) $row['case_json'], true );
		$case = is_array( $case ) ? $case : array();
		$case['observation_label'] = sanitize_text_field( wp_unslash( $_POST['he_v22_observation_label'] ?? '' ) );
		$metadata = json_decode( (string) $row['metadata_json'], true );
		$metadata = is_array( $metadata ) ? $metadata : array();
		$metadata['description'] = sanitize_textarea_field( wp_unslash( $_POST['he_v22_dataset_description'] ?? ( $metadata['description'] ?? '' ) ) );
		$metadata['de_identification'] = sanitize_textarea_field( wp_unslash( $_POST['he_v22_dataset_deidentification'] ?? '' ) );
		$metadata['lawful_basis'] = sanitize_textarea_field( wp_unslash( $_POST['he_v22_dataset_lawful_basis'] ?? '' ) );
		$metadata['access_policy'] = sanitize_textarea_field( wp_unslash( $_POST['he_v22_dataset_access_policy'] ?? '' ) );
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET investigators_json=%s,conflicts_json=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",
			wp_json_encode( $investigators, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			(int) $row['id'], (int) $row['row_version']
		) );
		if ( 1 !== (int) $updated ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'research_completeness_concurrency_conflict', 'Research completeness fields were not stored because the row changed concurrently.' );
		}

	}

	private static function has_direct_identifier( $text ) {
		foreach ( array(
			'/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u',
			'/\b(?:\+?92|0)?3\d{9}\b/u',
			'/\b\d{5}-\d{7}-\d\b/u',
			'/\b(?:CNIC|NIC|passport|phone|mobile|address|email|mrn|patient\s*id|national\s*id)\s*[:#-]?\s*[A-Za-z0-9@._+\/-]{4,}\b/ui',
		) as $pattern ) {
			if ( preg_match( $pattern, (string) $text ) ) {
				return true;
			}
		}
		return false;
	}

	public static function validate_row( $row ) {
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$investigators = json_decode( (string) $row['investigators_json'], true );
		$ethics = json_decode( (string) $row['ethics_json'], true );
		$consent = json_decode( (string) $row['consent_json'], true );
		$conflicts = json_decode( (string) $row['conflicts_json'], true );
		$case = json_decode( (string) $row['case_json'], true );
		$metadata = json_decode( (string) $row['metadata_json'], true );
		if ( empty( $investigators ) ) {
			return new WP_Error( 'he_investigators_required', __( 'At least one investigator is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( empty( $ethics['approval_reference'] ) ) {
			return new WP_Error( 'he_ethics_gate_failed', __( 'A documented ethics or governance approval reference is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( ! is_array( $conflicts ) || empty( $conflicts['recorded'] ) ) {
			return new WP_Error( 'he_conflict_disclosure_required', __( 'Conflict-of-interest disclosure must be explicitly recorded.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( ! in_array( $row['data_class'], array( 'public', 'restricted', 'highly-restricted' ), true ) ) {
			return new WP_Error( 'he_invalid_data_class', __( 'Research data class is invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( 'successful-case' === $row['record_type'] ) {
			foreach ( array( 'observation_label', 'baseline', 'intervention', 'follow_up', 'adverse_events', 'limitations' ) as $field ) {
				if ( empty( $case[ $field ] ) ) {
					return new WP_Error( 'he_case_governance_failed', __( 'Successful-case governance fields are incomplete.', 'homeopathy-encyclopedia' ), array( 'status' => 422, 'field' => $field ) );
				}
			}
			if ( empty( $row['case_anonymized'] ) || empty( $row['case_consent_verified'] ) || empty( $consent['verified'] ) || 'کامیاب کیس' !== $row['case_tag'] ) {
				return new WP_Error( 'he_case_governance_failed', __( 'Successful cases require anonymization, verified consent and the mandatory کامیاب کیس classification.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			if ( self::has_direct_identifier( implode( ' ', array_map( 'strval', $case ) ) ) ) {
				return new WP_Error( 'he_case_pii_detected', __( 'Potential direct patient identifiers were detected.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		if ( 'dataset' === $row['record_type'] ) {
			foreach ( array( 'description', 'de_identification', 'lawful_basis', 'access_policy' ) as $field ) {
				if ( empty( $metadata[ $field ] ) ) {
					return new WP_Error( 'he_dataset_governance_required', __( 'Dataset governance metadata is incomplete.', 'homeopathy-encyclopedia' ), array( 'status' => 422, 'field' => $field ) );
				}
			}
			if ( 'public' === $row['data_class'] ) {
				return new WP_Error( 'he_dataset_private_by_default', __( 'Dataset records must remain restricted or highly restricted; only approved metadata is public.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		return true;
	}

	/** Canonical public-research eligibility shared by every File 06 public surface.
	 * Dataset payloads remain restricted/highly-restricted while their governed metadata may be public.
	 */
	public static function public_surface_eligible( $row ) {
		if ( ! is_array( $row ) || ! in_array( $row['status'], array( 'published','corrected','retracted' ), true ) ) {
			return false;
		}
		$valid = self::validate_row( $row );
		if ( is_wp_error( $valid ) ) {
			return false;
		}
		if ( 'dataset' === $row['record_type'] ) {
			return in_array( $row['data_class'], array( 'restricted','highly-restricted' ), true );
		}
		return 'public' === $row['data_class'];
	}

	public static function validate_transition( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( ! preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/(\d+)/transition$#', $route, $m ) ) {
			return $response;
		}
		$state = sanitize_key( $request->get_param( 'state' ) );
		if ( ! in_array( $state, array( 'approved', 'active', 'analysis', 'peer_review', 'published' ), true ) ) {
			return $response;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $m[1] ) ), ARRAY_A );
		$result = self::validate_row( $row );
		return is_wp_error( $result ) ? $result : $response;
	}
}

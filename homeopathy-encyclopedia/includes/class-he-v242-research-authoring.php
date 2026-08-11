<?php
/** File 06 v2.4.2 research authoring/composer completeness hardening. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Research_Authoring {
	const NONCE_ACTION = 'he_v242_research_authoring';
	const NONCE_FIELD = 'he_v242_research_authoring_nonce';
	private static $rollback_delete = false;

	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ), 70 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'preflight_admin' ), 3, 2 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'save_admin' ), 170, 3 );
		add_filter( 'sabri_composer_content_types', array( __CLASS__, 'composer_contract' ), 1003 );
	}

	private static function conflicts( $value ) {
		$parts = HE_V2_Domain::sanitize_text_list( $value );
		$statement = sanitize_textarea_field( implode( '; ', $parts ) );
		if ( '' === trim( $statement ) ) { return array(); }
		$none = (bool) preg_match( '/\b(?:no|none)\s+(?:conflict|conflicts)\b/i', $statement );
		return array( 'recorded' => true, 'statement' => $statement, 'none_declared' => $none );
	}

	private static function investigators( $value ) {
		$parts = is_array( $value ) ? $value : preg_split( '/[\r\n,;]+/u', (string) $value );
		$out = array();
		foreach ( (array) $parts as $item ) {
			if ( is_array( $item ) ) { $item = $item['name'] ?? ''; }
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) { $out[] = $item; }
		}
		return array_values( array_unique( $out ) );
	}

	public static function add_box() {
		add_meta_box(
			'he-v242-research-authorship',
			__( 'Research investigators, conflicts and dataset governance', 'homeopathy-encyclopedia' ),
			array( __CLASS__, 'render_box' ),
			HE_V2_Domain::RESEARCH_TYPE,
			'normal',
			'high'
		);
	}

	public static function render_box( $post ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT investigators_json,conflicts_json,metadata_json FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', absint( $post->ID ) ), ARRAY_A );
		$investigators = self::investigators( $row ? json_decode( (string) $row['investigators_json'], true ) : array() );
		$conflicts = $row ? json_decode( (string) $row['conflicts_json'], true ) : array();
		$metadata = $row ? json_decode( (string) $row['metadata_json'], true ) : array();
		$statement = is_array( $conflicts ) ? (string) ( $conflicts['statement'] ?? ( isset( $conflicts[0] ) ? implode( '; ', array_map( 'strval', $conflicts ) ) : '' ) ) : '';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="he-v2 he-v2__admin-grid">
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Investigators — one per line', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v242_investigators" rows="4" required><?php echo esc_textarea( implode( "\n", (array) $investigators ) ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Conflict-of-interest statement — explicitly state “No conflicts” when none exist', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v242_conflict_statement" rows="3" required><?php echo esc_textarea( $statement ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset de-identification method', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v242_de_identification" rows="3"><?php echo esc_textarea( $metadata['de_identification'] ?? '' ); ?></textarea></label>
			<label><span><?php esc_html_e( 'Dataset lawful basis', 'homeopathy-encyclopedia' ); ?></span><input name="he_v242_lawful_basis" value="<?php echo esc_attr( $metadata['lawful_basis'] ?? '' ); ?>"></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset access policy', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v242_access_policy" rows="3"><?php echo esc_textarea( $metadata['access_policy'] ?? '' ); ?></textarea></label>
		</div>
		<?php
	}

	public static function preflight_admin( $data, $postarr ) {
		if ( ! is_admin() || HE_V2_Domain::RESEARCH_TYPE !== ( $data['post_type'] ?? '' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return $data; }
		$post_id = absint( $postarr['ID'] ?? ( $_POST['post_ID'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $post_id || wp_is_post_revision( $post_id ) || ! isset( $_POST[ self::NONCE_FIELD ] ) ) { return $data; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'The research-authoring security token is missing or expired.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 validation', 'homeopathy-encyclopedia' ), array( 'response' => 403 ) );
		}
		$investigators = self::investigators( wp_unslash( $_POST['he_v242_investigators'] ?? '' ) );
		$conflicts = self::conflicts( wp_unslash( $_POST['he_v242_conflict_statement'] ?? '' ) );
		if ( ! $investigators || ! $conflicts ) {
			wp_die( esc_html__( 'Research records require at least one investigator and an explicit conflict-of-interest statement.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 validation', 'homeopathy-encyclopedia' ), array( 'response' => 422 ) );
		}
		$type = sanitize_key( wp_unslash( $_POST['he_v2_research_type'] ?? 'proposal' ) );
		if ( 'dataset' === $type ) {
			foreach ( array( 'he_v2_dataset_metadata', 'he_v242_de_identification', 'he_v242_lawful_basis', 'he_v242_access_policy' ) as $field ) {
				if ( '' === trim( (string) wp_unslash( $_POST[ $field ] ?? '' ) ) ) {
					wp_die( esc_html__( 'Dataset records require description, de-identification method, lawful basis and access policy.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 dataset governance', 'homeopathy-encyclopedia' ), array( 'response' => 422 ) );
				}
			}
		}
		/* Published/corrected/retracted research cannot be silently rewritten through wp-admin. */
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status,title,question,protocol FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post_id ), ARRAY_A );
		if ( $row && in_array( $row['status'], array( 'published','corrected','retracted' ), true ) ) {
			$changed = (string) ( $data['post_title'] ?? '' ) !== (string) $row['title'] || (string) ( $data['post_excerpt'] ?? '' ) !== (string) $row['question'] || (string) ( $data['post_content'] ?? '' ) !== (string) $row['protocol'];
			if ( $changed ) {
				wp_die( esc_html__( 'Published research content is immutable in the normal editor. Submit a governed correction/retraction workflow instead.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 integrity workflow required', 'homeopathy-encyclopedia' ), array( 'response' => 409 ) );
			}
		}
		return $data;
	}

	public static function save_admin( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! isset( $_POST[ self::NONCE_FIELD ] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, $post_id, 'research-authoring-save' ) ) { return; }
		$investigators = self::investigators( wp_unslash( $_POST['he_v242_investigators'] ?? '' ) );
		$conflicts = self::conflicts( wp_unslash( $_POST['he_v242_conflict_statement'] ?? '' ) );
		if ( ! $investigators || ! $conflicts ) { return; }
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", absint( $post_id ) ), ARRAY_A );
		if ( ! $row ) { return; }
		$metadata = json_decode( (string) $row['metadata_json'], true );
		$metadata = is_array( $metadata ) ? $metadata : array();
		$metadata['description'] = sanitize_textarea_field( wp_unslash( $_POST['he_v2_dataset_metadata'] ?? ( $metadata['description'] ?? '' ) ) );
		$metadata['de_identification'] = sanitize_textarea_field( wp_unslash( $_POST['he_v242_de_identification'] ?? ( $metadata['de_identification'] ?? '' ) ) );
		$metadata['lawful_basis'] = sanitize_key( wp_unslash( $_POST['he_v242_lawful_basis'] ?? ( $metadata['lawful_basis'] ?? '' ) ) );
		$metadata['access_policy'] = sanitize_textarea_field( wp_unslash( $_POST['he_v242_access_policy'] ?? ( $metadata['access_policy'] ?? '' ) ) );
		$expected_loaded = isset( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) ? absint( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		/* Account for every verified same-request writer that runs before priority 170. */
		$expected_now = $expected_loaded;
		if ( $expected_loaded ) {
			if ( isset( $_POST['he_v2_research_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_research_nonce'] ) ), 'he_v2_save_research' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				++$expected_now;
			}
			if ( isset( $_POST['he_v22_research_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v22_research_nonce'] ) ), 'he_v22_research_completeness' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				++$expected_now;
			}
		} else {
			$expected_now = (int) $row['row_version'];
		}
		if ( (int) $row['row_version'] !== $expected_now ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'research_authoring_concurrency_conflict', 'Research governance fields were not written because the domain row moved during the same admin request.' );
			return;
		}
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET investigators_json=%s,conflicts_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",
			wp_json_encode( $investigators, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			(int) $row['id'], $expected_now
		) );
		if ( 1 !== (int) $updated ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'research_authoring_write_failed', 'Research investigators/conflict/dataset governance could not be stored atomically.' );
		}
	}

	public static function composer_contract( $types ) {
		$types = is_array( $types ) ? $types : array();
		if ( isset( $types['file06_research_record'] ) && is_array( $types['file06_research_record'] ) ) {
			$fields = isset( $types['file06_research_record']['fields'] ) ? (array) $types['file06_research_record']['fields'] : array();
			$types['file06_research_record']['fields'] = array_values( array_unique( array_merge( $fields, array( 'conflicts' ) ) ) );
			$types['file06_research_record']['draft_command'] = array( __CLASS__, 'composer_create_research' );
			$types['file06_research_record']['rollback_command'] = array( __CLASS__, 'composer_rollback_research' );
			$types['file06_research_record']['explicit_conflict_disclosure_required'] = true;
			$types['file06_research_record']['governed_pristine_rollback'] = true;
		}
		return $types;
	}

	private static function validate_payload( $data ) {
		$type = sanitize_key( $data['record_type'] ?? 'proposal' );
		if ( ! in_array( $type, array( 'proposal','protocol','publication','successful-case','dataset' ), true ) ) { return new WP_Error( 'he_invalid_research_type', __( 'Invalid research record type.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		$class = sanitize_key( $data['data_class'] ?? 'restricted' );
		if ( ! in_array( $class, array( 'public','restricted','highly-restricted' ), true ) ) { return new WP_Error( 'he_invalid_data_class', __( 'Invalid research data class.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		if ( ! HE_V2_Domain::sanitize_text_list( $data['investigators'] ?? array() ) || ! HE_V2_Domain::normalize_conflicts( $data['conflicts'] ?? array() ) ) { return new WP_Error( 'he_research_governance_required', __( 'At least one investigator and an explicit conflict-of-interest statement are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		if ( 'successful-case' === $type ) {
			foreach ( array( 'baseline','intervention','follow_up','adverse_events','limitations' ) as $field ) { if ( '' === trim( (string) ( $data[ $field ] ?? '' ) ) ) { return new WP_Error( 'he_case_governance_failed', __( 'Successful cases require complete baseline, intervention, follow-up, adverse-events and limitations fields.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); } }
			if ( empty( $data['consent_verified'] ) || empty( $data['anonymized'] ) ) { return new WP_Error( 'he_case_governance_failed', __( 'Successful cases require verified consent and anonymization.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		}
		if ( 'dataset' === $type ) {
			foreach ( array( 'dataset_description','de_identification','lawful_basis','access_policy' ) as $field ) { if ( '' === trim( (string) ( $data[ $field ] ?? '' ) ) ) { return new WP_Error( 'he_dataset_governance_required', __( 'Dataset metadata requires description, de-identification, lawful basis and access policy.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); } }
		}
		return true;
	}

	public static function composer_create_research( $payload, $context = array() ) {
		$payload = is_array( $payload ) ? $payload : array();
		$actor = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : get_current_user_id();
		if ( ! $actor || $actor !== get_current_user_id() || ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, 0, 'file06-research-composer', $actor ) ) { return new WP_Error( 'he_composer_forbidden', __( 'File 06 research creation is not authorized for this actor.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		$valid = self::validate_payload( $payload );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$result = HE_V2_Domain::create_research( $payload, $actor );
		if ( is_wp_error( $result ) ) { return $result; }
		/* Domain creation now persists the canonical conflict structure before success. Verify it without a second mutation. */
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id,conflicts_json FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $result['id'] ?? '' ), ARRAY_A );
		$expected_conflicts = HE_V2_Domain::normalize_conflicts( $payload['conflicts'] ?? array() );
		$stored_conflicts = $row ? json_decode( (string) $row['conflicts_json'], true ) : null;
		if ( ! $row || $stored_conflicts !== $expected_conflicts ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'research_conflict_canonicalization_failed', 'Research creation did not persist the canonical conflict disclosure shape.' );
			return new WP_Error( 'he_research_normalization_conflict', __( 'Research governance normalization could not be verified; mutations have been paused.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
		}
		return HE_V2_Domain::research_dto( (int) $row['id'], true );
	}

	public static function composer_rollback_research( $native_id, $context = array() ) {
		global $wpdb;
		$row = is_numeric( $native_id ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $native_id ) ), ARRAY_A ) : $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', sanitize_text_field( (string) $native_id ) ), ARRAY_A );
		$actor = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : get_current_user_id();
		if ( ! $row || 'proposal' !== $row['status'] || $actor !== get_current_user_id() || (int) $row['created_by'] !== $actor || ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, (int) $row['post_id'], 'file06-research-rollback', $actor ) ) { return false; }
		$children = 0;
		$children += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='research' AND object_id=%d", (int) $row['id'] ) );
		$children += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HE_V2_Schema::table( 'integrity_actions' ) . " WHERE object_type='research' AND object_id=%d", (int) $row['id'] ) );
		$children += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE research_id=%d', (int) $row['id'] ) );
		if ( class_exists( 'HE_V24_Future_Schema' ) ) {
			$children += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HE_V24_Future_Schema::table( 'external_records' ) . " WHERE object_type='research' AND object_id=%d", (int) $row['id'] ) );
		}
		if ( $children ) { return false; }
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post || 'draft' !== $post->post_status ) { return false; }
		$guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' );
		$wpdb->query( 'START TRANSACTION' );
		remove_filter( 'pre_delete_post', $guard, 1 );
		try {
			if ( ! wp_delete_post( (int) $row['post_id'], true ) ) { throw new RuntimeException( 'post-delete-failed' ); }
			if ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'research' ), array( 'id' => (int) $row['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'research-delete-failed' ); }
			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			clean_post_cache( (int) $row['post_id'] );
			HE_V2_Schema::record_runtime_failure( 'research_composer_rollback_failed', sanitize_text_field( $error->getMessage() ) );
			return false;
		} finally {
			add_filter( 'pre_delete_post', $guard, 1, 3 );
		}
		HE_V2_Domain::emit_event( 'ResearchDraftRolledBack.v1', 'research', (int) $row['id'], array( 'public_id' => $row['public_id'], 'reason' => 'composer-compensation' ) );
		return true;
	}
}

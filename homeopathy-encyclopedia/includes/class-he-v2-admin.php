<?php
/** Editorial, research and operational administration. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . HE_V2_Domain::ENTRY_TYPE, array( $this, 'save_entry_meta' ), 20, 2 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( $this, 'save_research_meta' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_he_v2_repair', array( $this, 'repair' ) );
		add_action( 'admin_post_he_v2_safe_mode', array( $this, 'safe_mode' ) );
		add_filter( 'manage_he_entry_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_he_entry_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE,
			__( 'Encyclopedia Operations', 'homeopathy-encyclopedia' ),
			__( 'Operations', 'homeopathy-encyclopedia' ),
			HE_V2_Auth::CAP_REPAIR,
			'he-v2-operations',
			array( $this, 'operations_page' )
		);
		add_submenu_page(
			'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE,
			__( 'Encyclopedia Contracts', 'homeopathy-encyclopedia' ),
			__( 'Contracts', 'homeopathy-encyclopedia' ),
			HE_V2_Auth::CAP_REVIEW,
			'he-v2-contracts',
			array( $this, 'contracts_page' )
		);
	}

	public function assets( $hook ) {
		if ( false !== strpos( $hook, 'he_entry' ) || false !== strpos( $hook, 'he-v2' ) ) {
			wp_enqueue_style( 'he-v2-admin', HE_URL . 'assets/css/encyclopedia-v2.css', array(), HE_VERSION );
		}
	}

	public function meta_boxes() {
		add_meta_box( 'he-v2-governance', __( 'File 06 Governance', 'homeopathy-encyclopedia' ), array( $this, 'entry_governance_box' ), HE_V2_Domain::ENTRY_TYPE, 'normal', 'high' );
		add_meta_box( 'he-v2-fields', __( 'Structured Knowledge and Safety', 'homeopathy-encyclopedia' ), array( $this, 'entry_fields_box' ), HE_V2_Domain::ENTRY_TYPE, 'normal', 'default' );
		add_meta_box( 'he-v2-research-governance', __( 'Research Governance', 'homeopathy-encyclopedia' ), array( $this, 'research_box' ), HE_V2_Domain::RESEARCH_TYPE, 'normal', 'high' );
	}

	public function entry_governance_box( $post ) {
		global $wpdb;
		wp_nonce_field( 'he_v2_save_entry', 'he_v2_nonce' );
		$concept = HE_V2_Domain::concept_by_id( $post->post_name, true );
		$type = HE_V2_Domain::taxonomy_slug( $post->ID, HE_V2_Domain::TAX_TYPE );
		$system = HE_V2_Domain::taxonomy_slug( $post->ID, HE_V2_Domain::TAX_SYSTEM );
		$language = get_post_meta( $post->ID, '_he_language', true ) ?: 'en-US';
		?>
		<div class="he-v2 he-v2__admin-grid">
			<label><span><?php esc_html_e( 'Knowledge type', 'homeopathy-encyclopedia' ); ?></span><select name="he_v2_type" required><?php foreach ( HE_V2_Domain::types() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Body system', 'homeopathy-encyclopedia' ); ?></span><select name="he_v2_system" required><?php foreach ( HE_V2_Domain::systems() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $system, $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Source language', 'homeopathy-encyclopedia' ); ?></span><input type="text" value="<?php echo esc_attr( class_exists( 'HE_V242_Multilingual' ) ? ( HE_V242_Multilingual::canonical_locale( $language ) ?: $language ) : $language ); ?>" readonly><small><?php esc_html_e( 'Edit the canonical BCP-47 source language in the Original source language box.', 'homeopathy-encyclopedia' ); ?></small></label>
			<?php if ( $concept ) : ?>
				<div class="he-v2__panel"><strong><?php esc_html_e( 'Canonical public ID', 'homeopathy-encyclopedia' ); ?></strong><code><?php echo esc_html( $concept['public_id'] ); ?></code><br><strong><?php esc_html_e( 'Workflow state', 'homeopathy-encyclopedia' ); ?></strong> <?php echo esc_html( $concept['status'] ); ?><br><strong><?php esc_html_e( 'Record version', 'homeopathy-encyclopedia' ); ?></strong> <?php echo absint( $concept['row_version'] ); ?><br><strong><?php esc_html_e( 'Published version', 'homeopathy-encyclopedia' ); ?></strong> <?php echo absint( $concept['current_version'] ); ?></div>
				<input type="hidden" name="he_v2_expected_version" value="<?php echo absint( $concept['row_version'] ); ?>">
			<?php endif; ?>
		</div>
		<?php
	}

	public function entry_fields_box( $post ) {
		$fields = get_post_meta( $post->ID, '_he_structured', true );
		$fields = is_array( $fields ) ? $fields : array();
		$labels = array(
			'source' => __( 'Source or classification', 'homeopathy-encyclopedia' ),
			'key_points' => __( 'Key educational points', 'homeopathy-encyclopedia' ),
			'symptoms' => __( 'Symptoms or characteristics', 'homeopathy-encyclopedia' ),
			'causes' => __( 'Causes and etiology', 'homeopathy-encyclopedia' ),
			'modalities' => __( 'Modalities', 'homeopathy-encyclopedia' ),
			'red_flags' => __( 'Medical red flags', 'homeopathy-encyclopedia' ),
			'safety' => __( 'Safety', 'homeopathy-encyclopedia' ),
			'limitations' => __( 'Limitations', 'homeopathy-encyclopedia' ),
			'emergency_boundary' => __( 'Urgent-care boundary', 'homeopathy-encyclopedia' ),
			'evidence_summary' => __( 'Evidence summary', 'homeopathy-encyclopedia' ),
		);
		?><div class="he-v2 he-v2__admin-grid"><?php foreach ( $labels as $key => $label ) : ?><label class="he-v2__admin-full"><span><?php echo esc_html( $label ); ?></span><textarea name="he_v2_fields[<?php echo esc_attr( $key ); ?>]" rows="4"><?php echo esc_textarea( $fields[ $key ] ?? '' ); ?></textarea></label><?php endforeach; ?></div><?php
	}

	public function save_entry_meta( $post_id, $post ) {
		if ( ! isset( $_POST['he_v2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_nonce'] ) ), 'he_v2_save_entry' ) ) {
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, $post_id, 'admin-save' ) ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_POST['he_v2_type'] ?? '' ) );
		$system = sanitize_key( wp_unslash( $_POST['he_v2_system'] ?? '' ) );
		if ( isset( HE_V2_Domain::types()[ $type ] ) ) {
			wp_set_object_terms( $post_id, array( $type ), HE_V2_Domain::TAX_TYPE, false );
		}
		if ( isset( HE_V2_Domain::systems()[ $system ] ) ) {
			wp_set_object_terms( $post_id, array( $system ), HE_V2_Domain::TAX_SYSTEM, false );
		}
		/* v2.4.2+ owns source-language writes; never transiently reset a wider BCP-47 source through the legacy three-locale field. */
		if ( ! class_exists( 'HE_V242_Language_Surfaces' ) || ! isset( $_POST[ HE_V242_Language_Surfaces::NONCE_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$language = sanitize_text_field( wp_unslash( $_POST['he_v2_language'] ?? 'en-US' ) );
			if ( in_array( $language, array( 'en-US', 'ur-PK', 'ar' ), true ) ) {
				update_post_meta( $post_id, '_he_language', $language );
			}
		}
		$fields = isset( $_POST['he_v2_fields'] ) ? HE_V2_Domain::sanitize_structured( wp_unslash( $_POST['he_v2_fields'] ) ) : array();
		update_post_meta( $post_id, '_he_structured', $fields );
		HE_V2_Domain::ensure_concept_for_post( $post_id );
		HE_V2_Domain::reindex_concept_by_post( $post_id );
	}

	public function research_box( $post ) {
		global $wpdb;
		wp_nonce_field( 'he_v2_save_research', 'he_v2_research_nonce' );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post->ID ), ARRAY_A );
		$type = $row['record_type'] ?? 'proposal';
		$ethics = $row ? json_decode( $row['ethics_json'], true ) : array();
		$consent = $row ? json_decode( $row['consent_json'], true ) : array();
		$case = $row ? json_decode( $row['case_json'], true ) : array();
		$metadata = $row ? json_decode( $row['metadata_json'], true ) : array();
		?>
		<div class="he-v2 he-v2__admin-grid">
			<label><span><?php esc_html_e( 'Research type', 'homeopathy-encyclopedia' ); ?></span><select name="he_v2_research_type"><option value="proposal" <?php selected( $type, 'proposal' ); ?>><?php esc_html_e( 'Proposal', 'homeopathy-encyclopedia' ); ?></option><option value="protocol" <?php selected( $type, 'protocol' ); ?>><?php esc_html_e( 'Protocol', 'homeopathy-encyclopedia' ); ?></option><option value="publication" <?php selected( $type, 'publication' ); ?>><?php esc_html_e( 'Publication', 'homeopathy-encyclopedia' ); ?></option><option value="successful-case" <?php selected( $type, 'successful-case' ); ?>><?php esc_html_e( 'Successful Case', 'homeopathy-encyclopedia' ); ?></option><option value="dataset" <?php selected( $type, 'dataset' ); ?>><?php esc_html_e( 'Dataset Metadata', 'homeopathy-encyclopedia' ); ?></option></select></label>
			<label><span><?php esc_html_e( 'Data class', 'homeopathy-encyclopedia' ); ?></span><select name="he_v2_data_class"><option value="public" <?php selected( $row['data_class'] ?? '', 'public' ); ?>><?php esc_html_e( 'Public metadata', 'homeopathy-encyclopedia' ); ?></option><option value="restricted" <?php selected( $row['data_class'] ?? 'restricted', 'restricted' ); ?>><?php esc_html_e( 'Restricted', 'homeopathy-encyclopedia' ); ?></option><option value="highly-restricted" <?php selected( $row['data_class'] ?? '', 'highly-restricted' ); ?>><?php esc_html_e( 'Highly restricted', 'homeopathy-encyclopedia' ); ?></option></select></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Ethics approval reference', 'homeopathy-encyclopedia' ); ?></span><input name="he_v2_ethics_reference" value="<?php echo esc_attr( $ethics['approval_reference'] ?? '' ); ?>"></label>
			<label><input type="checkbox" name="he_v2_consent_verified" value="1" <?php checked( ! empty( $consent['verified'] ) ); ?>> <?php esc_html_e( 'Consent verified', 'homeopathy-encyclopedia' ); ?></label>
			<label><input type="checkbox" name="he_v2_case_anonymized" value="1" <?php checked( ! empty( $row['case_anonymized'] ) ); ?>> <?php esc_html_e( 'Case anonymized', 'homeopathy-encyclopedia' ); ?></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Successful-case baseline', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v2_case_baseline" rows="3"><?php echo esc_textarea( $case['baseline'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Intervention', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v2_case_intervention" rows="3"><?php echo esc_textarea( $case['intervention'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Follow-up and outcome', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v2_case_follow_up" rows="3"><?php echo esc_textarea( $case['follow_up'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Adverse or unexpected events', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v2_case_adverse_events" rows="3"><?php echo esc_textarea( $case['adverse_events'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Limitations', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v2_case_limitations" rows="3"><?php echo esc_textarea( $case['limitations'] ?? '' ); ?></textarea></label>
			<label class="he-v2__admin-full"><span><?php esc_html_e( 'Dataset purpose and de-identification metadata', 'homeopathy-encyclopedia' ); ?></span><textarea name="he_v2_dataset_metadata" rows="4"><?php echo esc_textarea( $metadata['description'] ?? '' ); ?></textarea></label>
			<?php if ( $row ) : ?><div class="he-v2__panel"><strong><?php esc_html_e( 'Permanent research ID', 'homeopathy-encyclopedia' ); ?></strong><code><?php echo esc_html( $row['public_id'] ); ?></code><br><strong><?php esc_html_e( 'State', 'homeopathy-encyclopedia' ); ?></strong> <?php echo esc_html( $row['status'] ); ?><br><strong><?php esc_html_e( 'Version', 'homeopathy-encyclopedia' ); ?></strong> <?php echo absint( $row['row_version'] ); ?></div><?php endif; ?>
		</div>
		<?php
	}

	public function save_research_meta( $post_id, $post ) {
		if ( ! isset( $_POST['he_v2_research_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_research_nonce'] ) ), 'he_v2_save_research' ) ) {
			return;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, $post_id, 'research-admin-save' ) ) {
			return;
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", $post_id ), ARRAY_A );
		if ( ! $row ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_POST['he_v2_research_type'] ?? 'proposal' ) );
		$data_class = sanitize_key( wp_unslash( $_POST['he_v2_data_class'] ?? 'restricted' ) );
		$ethics = array( 'review_required' => true, 'approval_reference' => sanitize_text_field( wp_unslash( $_POST['he_v2_ethics_reference'] ?? '' ) ) );
		$consent = array( 'verified' => ! empty( $_POST['he_v2_consent_verified'] ), 'version' => 'admin-record-v1' );
		$anonymized = ! empty( $_POST['he_v2_case_anonymized'] );
		$case_tag = 'successful-case' === $type ? 'کامیاب کیس' : '';
		$case = array(
			'baseline' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_baseline'] ?? '' ) ),
			'intervention' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_intervention'] ?? '' ) ),
			'follow_up' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_follow_up'] ?? '' ) ),
			'adverse_events' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_adverse_events'] ?? '' ) ),
			'limitations' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_case_limitations'] ?? '' ) ),
		);
		$metadata = array(
			'description' => sanitize_textarea_field( wp_unslash( $_POST['he_v2_dataset_metadata'] ?? '' ) ),
			'de_identified' => true,
			'access_default' => 'restricted',
		);
		if ( ! in_array( $type, array( 'proposal','protocol','publication','successful-case','dataset' ), true ) ) { $type = 'proposal'; }
		if ( ! in_array( $data_class, array( 'public','restricted','highly-restricted' ), true ) || ( 'dataset' === $type && 'public' === $data_class ) ) { $data_class = 'restricted'; }
		$updated = $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET record_type=%s,title=%s,question=%s,protocol=%s,ethics_json=%s,consent_json=%s,data_class=%s,case_anonymized=%d,case_consent_verified=%d,case_tag=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d",
			$type, $post->post_title, $post->post_excerpt, $post->post_content, wp_json_encode( $ethics ), wp_json_encode( $consent ), $data_class,
			$anonymized ? 1 : 0, $consent['verified'] ? 1 : 0, $case_tag, wp_json_encode( $case ), wp_json_encode( $metadata ), (int) $row['id'], (int) $row['row_version']
		) );
		if ( 1 !== (int) $updated ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'legacy_research_admin_cas_failed', 'Legacy research admin metadata could not be persisted against the expected row version.' );
			return;
		}
		if ( $case_tag ) { wp_set_object_terms( $post_id, array( $case_tag ), HE_V2_Domain::TAX_TOPIC, false ); }
	}

	public function operations_page() {
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR ) ) {
			wp_die( esc_html__( 'You are not authorized to operate File 06.', 'homeopathy-encyclopedia' ) );
		}
		$health = HE_V2_Schema::health();
		?>
		<div class="wrap he-v2"><h1><?php esc_html_e( 'File 06 Operations', 'homeopathy-encyclopedia' ); ?></h1><p><?php esc_html_e( 'Read-first health, bounded repair, queue visibility and reversible safe mode.', 'homeopathy-encyclopedia' ); ?></p>
			<div class="he-v2__grid">
				<div class="he-v2__card"><h2><?php esc_html_e( 'Runtime', 'homeopathy-encyclopedia' ); ?></h2><p><strong><?php echo esc_html( $health['status'] ); ?></strong></p><p><?php echo esc_html( sprintf( __( 'Plugin %1$s · Schema %2$d/%3$d', 'homeopathy-encyclopedia' ), $health['plugin_version'], $health['schema_version'], $health['expected_schema'] ) ); ?></p></div>
				<div class="he-v2__card"><h2><?php esc_html_e( 'Contracts', 'homeopathy-encyclopedia' ); ?></h2><p>File 00: <?php echo $health['file00'] ? esc_html__( 'available', 'homeopathy-encyclopedia' ) : esc_html__( 'degraded', 'homeopathy-encyclopedia' ); ?></p><p>File 20: <?php echo $health['file20'] ? esc_html__( 'available', 'homeopathy-encyclopedia' ) : esc_html__( 'optional presentation unavailable', 'homeopathy-encyclopedia' ); ?></p></div>
				<div class="he-v2__card"><h2><?php esc_html_e( 'Outbox', 'homeopathy-encyclopedia' ); ?></h2><p><?php echo esc_html( sprintf( __( '%d event(s) pending or retrying.', 'homeopathy-encyclopedia' ), $health['pending_outbox'] ) ); ?></p></div>
			</div>
			<h2><?php esc_html_e( 'Schema tables', 'homeopathy-encyclopedia' ); ?></h2><table class="widefat striped"><tbody><?php foreach ( $health['tables'] as $name => $ok ) : ?><tr><th><?php echo esc_html( $name ); ?></th><td><?php echo $ok ? esc_html__( 'Available', 'homeopathy-encyclopedia' ) : esc_html__( 'Missing', 'homeopathy-encyclopedia' ); ?></td></tr><?php endforeach; ?></tbody></table>
			<div class="he-v2__actions">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="he_v2_repair"><?php wp_nonce_field( 'he_v2_repair' ); ?><button class="button" name="dry_run" value="1"><?php esc_html_e( 'Preview repair', 'homeopathy-encyclopedia' ); ?></button><button class="button button-primary" name="dry_run" value="0"><?php esc_html_e( 'Run bounded repair and reindex', 'homeopathy-encyclopedia' ); ?></button></form>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="he_v2_safe_mode"><?php wp_nonce_field( 'he_v2_safe_mode' ); ?><button class="button" name="enabled" value="<?php echo get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ? '0' : '1'; ?>"><?php echo get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ? esc_html__( 'Disable safe mode', 'homeopathy-encyclopedia' ) : esc_html__( 'Enable safe mode', 'homeopathy-encyclopedia' ); ?></button></form>
			</div>
		</div>
		<?php
	}

	public function contracts_page() {
		$contract = he_contract_descriptor();
		?><div class="wrap he-v2"><h1><?php esc_html_e( 'File 06 Contract Registry', 'homeopathy-encyclopedia' ); ?></h1><pre class="he-v2__code"><?php echo esc_html( wp_json_encode( $contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre><p><strong><?php esc_html_e( 'REST namespace:', 'homeopathy-encyclopedia' ); ?></strong> <code><?php echo esc_html( rest_url( HE_V2_API::NS ) ); ?></code></p></div><?php
	}

	public function repair() {
		check_admin_referer( 'he_v2_repair' );
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR ) ) {
			wp_die( esc_html__( 'Not authorized.', 'homeopathy-encyclopedia' ) );
		}
		$dry_run = ! empty( $_POST['dry_run'] );
		$result = HE_V2_Schema::repair( $dry_run );
		set_transient( 'he_v2_admin_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => wp_json_encode( $result ) ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE . '&page=he-v2-operations' ) );
		exit;
	}

	public function safe_mode() {
		check_admin_referer( 'he_v2_safe_mode' );
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR ) ) {
			wp_die( esc_html__( 'Not authorized.', 'homeopathy-encyclopedia' ) );
		}
		$enabled = ! empty( $_POST['enabled'] );
		if ( $enabled ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Domain::emit_event( 'File06SafeModeEnabled.v1', 'system', 0, array( 'actor_id' => get_current_user_id() ) );
		} else {
			$result = HE_V2_Schema::repair( false );
			if ( is_wp_error( $result ) || get_option( HE_V2_Schema::OPTION_SAFE_MODE ) || ! HE_V2_Schema::schema_complete() ) {
				set_transient( 'he_v2_admin_notice_' . get_current_user_id(), array( 'type' => 'error', 'message' => __( 'Safe mode remains active because verified repair did not establish a healthy runtime.', 'homeopathy-encyclopedia' ) ), 60 );
				wp_safe_redirect( admin_url( 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE . '&page=he-v2-operations' ) ); exit;
			}
			HE_V2_Domain::emit_event( 'File06SafeModeDisabled.v1', 'system', 0, array( 'actor_id' => get_current_user_id(), 'verified_repair' => true ) );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE . '&page=he-v2-operations' ) );
		exit;
	}

	public function notices() {
		$failure = get_option( HE_V2_Schema::OPTION_FAILURE );
		if ( is_array( $failure ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'File 06 is degraded.', 'homeopathy-encyclopedia' ) . '</strong> ' . esc_html( $failure['message'] ?? '' ) . '</p></div>';
		}
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'File 06 safe mode is active.', 'homeopathy-encyclopedia' ) . '</strong> ' . esc_html__( 'Public reading remains available; high-risk mutations are disabled.', 'homeopathy-encyclopedia' ) . '</p></div>';
		}
		$notice = get_transient( 'he_v2_admin_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'he_v2_admin_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}

	public function columns( $columns ) {
		$columns['he_canonical'] = __( 'Canonical ID', 'homeopathy-encyclopedia' );
		$columns['he_state'] = __( 'Governance state', 'homeopathy-encyclopedia' );
		$columns['he_version'] = __( 'Version', 'homeopathy-encyclopedia' );
		return $columns;
	}

	public function column( $column, $post_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT public_id,status,review_status,safety_status,current_version,row_version FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', $post_id ), ARRAY_A );
		if ( ! $row ) {
			echo '—';
			return;
		}
		if ( 'he_canonical' === $column ) {
			echo '<code>' . esc_html( $row['public_id'] ) . '</code>';
		} elseif ( 'he_state' === $column ) {
			echo esc_html( $row['status'] . ' / ' . $row['review_status'] . ' / ' . $row['safety_status'] );
		} elseif ( 'he_version' === $column ) {
			echo esc_html( $row['current_version'] . ' · row ' . $row['row_version'] );
		}
	}
}

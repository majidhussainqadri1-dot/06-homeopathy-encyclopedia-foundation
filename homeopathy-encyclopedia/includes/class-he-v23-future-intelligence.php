<?php
/**
 * File 06 Future Knowledge Intelligence v2.3.
 * Implements F06-FUT-001..018 while preserving canonical ownership boundaries.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V23_Future_Intelligence {
	const SCHEMA_OPTION = 'he_v23_future_schema';
	const SCHEMA_VERSION = 1;
	const CRON_HOOK = 'he_v23_future_refresh';
	const NS = 'sabri/v2/file-06';

	private static function table( $suffix ) { return HE_V2_Schema::table( 'future_' . $suffix ); }

	public static function capabilities() {
		return array(
			'F06-FUT-001' => 'claim-level-evidence-graph',
			'F06-FUT-002' => 'universal-provenance-ledger',
			'F06-FUT-003' => 'retraction-correction-watch',
			'F06-FUT-004' => 'pubmed-ncbi-literature-connector',
			'F06-FUT-005' => 'clinical-trials-evidence-linker',
			'F06-FUT-006' => 'orcid-researcher-identity-layer',
			'F06-FUT-007' => 'datacite-dataset-doi-intelligence',
			'F06-FUT-008' => 'mesh-biomedical-vocabulary-mapping',
			'F06-FUT-009' => 'semantic-duplicate-intelligence',
			'F06-FUT-010' => 'interactive-knowledge-graph-explorer',
			'F06-FUT-011' => 'knowledge-time-machine',
			'F06-FUT-012' => 'cross-platform-impact-propagation',
			'F06-FUT-013' => 'living-knowledge-freshness-engine',
			'F06-FUT-014' => 'evidence-gap-research-priority-radar',
			'F06-FUT-015' => 'citation-laboratory',
			'F06-FUT-016' => 'knowledge-watchlists',
			'F06-FUT-017' => 'governed-multilingual-knowledge-editions',
			'F06-FUT-018' => 'encyclopedia-integrity-command-center',
		);
	}

	public static function activate() {
		self::ensure_schema();
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) { wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK ); }
	}

	public static function deactivate() { wp_clear_scheduled_hook( self::CRON_HOOK ); }

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'scheduled_refresh' ) );
		add_action( 'he_v2_event', array( __CLASS__, 'on_core_event' ), 40, 4 );
		add_filter( 'sabri_file06_future_capabilities', array( __CLASS__, 'capability_filter' ), 100 );
		add_filter( 'sabri_public_component_registry', array( __CLASS__, 'public_components' ), 140 );
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance_provider' ), 150 );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) { wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', self::CRON_HOOK ); }
		self::ensure_schema();
	}

	public static function capability_filter( $capabilities ) {
		$capabilities = is_array( $capabilities ) ? $capabilities : array();
		$capabilities['file-06'] = array(
			'owner' => 'file-06', 'contract_version' => HE_CONTRACT_VERSION, 'features' => self::capabilities(),
			'write_authority' => 'file-06-governed-only', 'notifications_owner' => 'file-19', 'layout_owner' => 'file-20',
			'visual_owner' => 'file-25', 'global_search_owner' => 'file-26', 'identity_authority' => 'file-00',
		);
		return $capabilities;
	}

	public static function public_components( $components ) {
		$components = is_array( $components ) ? $components : array();
		$components['file06-knowledge-graph-explorer-v1'] = array(
			'owner' => 'file-06', 'presentation_owner' => 'file-25', 'contract_version' => HE_CONTRACT_VERSION,
			'query' => rest_url( self::NS . '/future/entries/{id}/graph-explorer' ), 'read_only' => true,
		);
		$components['file06-time-machine-v1'] = array(
			'owner' => 'file-06', 'presentation_owner' => 'file-25', 'contract_version' => HE_CONTRACT_VERSION,
			'query' => rest_url( self::NS . '/future/entries/{id}/time-machine' ), 'read_only' => true,
		);
		return $components;
	}

	public static function assurance_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		if ( isset( $providers['file-06'] ) ) {
			$providers['file-06']['future_intelligence'] = array(
				'features' => count( self::capabilities() ), 'health' => array( __CLASS__, 'health' ),
				'external_connectors' => array( 'crossref', 'pubmed', 'clinicaltrials', 'orcid', 'datacite', 'mesh' ),
				'external_content_auto_publish' => false,
			);
		}
		return $providers;
	}

	public static function ensure_schema() {
		if ( (int) get_option( self::SCHEMA_OPTION, 0 ) >= self::SCHEMA_VERSION ) { return; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$sql = array();
		$sql[] = "CREATE TABLE " . self::table( 'claims' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, public_id char(36) NOT NULL, concept_id bigint(20) unsigned NOT NULL,
			claim_type varchar(40) NOT NULL DEFAULT 'statement', claim_text longtext NOT NULL, status varchar(24) NOT NULL DEFAULT 'draft',
			confidence decimal(5,2) NOT NULL DEFAULT 0, row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY public_id (public_id), KEY concept_status (concept_id,status)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'claim_evidence' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, claim_id bigint(20) unsigned NOT NULL, reference_id bigint(20) unsigned NOT NULL DEFAULT 0,
			external_source_id bigint(20) unsigned NOT NULL DEFAULT 0, relation_type varchar(24) NOT NULL DEFAULT 'supports', strength varchar(20) NOT NULL DEFAULT 'ungraded',
			note text NOT NULL, created_by bigint(20) unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY claim_reference_external (claim_id,reference_id,external_source_id,relation_type), KEY claim_relation (claim_id,relation_type)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'provenance' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, event_id char(36) NOT NULL, object_type varchar(40) NOT NULL, object_id varchar(80) NOT NULL,
			action_name varchar(80) NOT NULL, source_uri text NOT NULL, actor_id bigint(20) unsigned NOT NULL DEFAULT 0, payload_hash char(64) NOT NULL,
			payload_json longtext NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY event_id (event_id),
			KEY object_action (object_type,object_id,action_name), KEY created_at (created_at)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'external_sources' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, provider varchar(30) NOT NULL, identifier varchar(191) NOT NULL,
			subject_type varchar(30) NOT NULL DEFAULT 'concept', subject_id bigint(20) unsigned NOT NULL DEFAULT 0, status varchar(32) NOT NULL DEFAULT 'current',
			update_type varchar(40) NOT NULL DEFAULT '', metadata_json longtext NOT NULL, metadata_hash char(64) NOT NULL, last_checked datetime NOT NULL,
			next_check datetime NOT NULL, created_by bigint(20) unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY provider_identifier_subject (provider,identifier,subject_type,subject_id), KEY status_next (status,next_check), KEY subject (subject_type,subject_id)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'watchers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, user_id bigint(20) unsigned NOT NULL, concept_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY user_concept (user_id,concept_id), KEY concept_id (concept_id)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'translations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_id bigint(20) unsigned NOT NULL, language varchar(20) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'draft', source_version bigint(20) unsigned NOT NULL DEFAULT 0, translation_version bigint(20) unsigned NOT NULL DEFAULT 1,
			translation_json longtext NOT NULL, content_hash char(64) NOT NULL, translator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL, created_at datetime NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY concept_language (concept_id,language), KEY status (status)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'freshness' ) . " (
			concept_id bigint(20) unsigned NOT NULL, freshness_state varchar(24) NOT NULL DEFAULT 'review-due', last_evidence_scan datetime NULL,
			last_human_review datetime NULL, review_due_at datetime NULL, priority_score int(10) unsigned NOT NULL DEFAULT 0, reason_json longtext NOT NULL,
			updated_at datetime NOT NULL, PRIMARY KEY (concept_id), KEY state_due (freshness_state,review_due_at), KEY priority_score (priority_score)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'impact_queue' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, event_id char(36) NOT NULL, object_type varchar(40) NOT NULL, object_id varchar(80) NOT NULL,
			event_name varchar(100) NOT NULL, consumer_file varchar(20) NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending', attempts int(10) unsigned NOT NULL DEFAULT 0,
			payload_json longtext NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY event_consumer (event_id,consumer_file),
			KEY consumer_status (consumer_file,status)
		) {$charset};";
		$sql[] = "CREATE TABLE " . self::table( 'research_gaps' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_id bigint(20) unsigned NOT NULL, gap_type varchar(40) NOT NULL,
			severity varchar(20) NOT NULL DEFAULT 'medium', details_json longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'open', updated_at datetime NOT NULL,
			created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY concept_gap (concept_id,gap_type), KEY severity_status (severity,status)
		) {$charset};";
		foreach ( $sql as $statement ) { dbDelta( $statement ); }
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	public static function routes() {
		register_rest_route( self::NS, '/future/capabilities', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'rest_capabilities' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/claims', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'claims' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'create_claim' ), 'permission_callback' => array( __CLASS__, 'can_edit_entry' ) ),
		) );
		register_rest_route( self::NS, '/future/claims/(?P<claim_id>\\d+)/evidence', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'add_claim_evidence' ), 'permission_callback' => array( __CLASS__, 'can_edit_claim' ) ) );
		register_rest_route( self::NS, '/future/claims/(?P<claim_id>\\d+)/transition', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'transition_claim' ), 'permission_callback' => array( __CLASS__, 'can_review_claim' ) ) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/provenance', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'provenance' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/future/external/resolve', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'resolve_external' ), 'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW ); } ) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/semantic-duplicates', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'semantic_duplicates' ), 'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_TAXONOMY ); } ) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/graph-explorer', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'graph_explorer' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/time-machine', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'time_machine' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/watch', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'watch_state' ), 'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); } ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'watch' ), 'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); } ),
		) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/translations', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'translations' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'save_translation' ), 'permission_callback' => array( __CLASS__, 'can_edit_entry' ) ),
		) );
		register_rest_route( self::NS, '/future/entries/(?P<id>[A-Za-z0-9-]+)/citation-bundle', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'citation_bundle' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NS, '/future/research-gaps', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'research_gaps' ), 'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH ); } ) );
		register_rest_route( self::NS, '/future/integrity-command-center', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'integrity_command_center' ), 'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR ); } ) );
		register_rest_route( self::NS, '/future/impact/reconcile', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'reconcile_impact' ), 'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR ); } ) );
	}

	public static function rest_capabilities() {
		return rest_ensure_response( array( 'owner' => 'file-06', 'contract_version' => HE_CONTRACT_VERSION, 'features' => self::capabilities(),
			'external_connectors' => array( 'crossref', 'pubmed', 'clinicaltrials', 'orcid', 'datacite', 'mesh' ), 'auto_publish_external_content' => false ) );
	}

	private static function concept( $public_id, $include_private = false ) { return HE_V2_Domain::concept_by_id( sanitize_text_field( (string) $public_id ), $include_private ); }

	public static function can_edit_entry( $request ) {
		$row = self::concept( $request['id'], true );
		return $row ? HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT, (int) $row['post_id'], 'file06-future' ) : new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
	}

	private static function claim_row( $claim_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'claims' ) . ' WHERE id=%d', absint( $claim_id ) ), ARRAY_A );
	}

	public static function can_edit_claim( $request ) {
		$claim = self::claim_row( $request['claim_id'] );
		if ( ! $claim ) { return new WP_Error( 'he_claim_not_found', __( 'Claim not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		global $wpdb;
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d', $claim['concept_id'] ) );
		return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT, $post_id, 'file06-claim' );
	}

	public static function can_review_claim( $request ) {
		$claim = self::claim_row( $request['claim_id'] );
		if ( ! $claim ) { return new WP_Error( 'he_claim_not_found', __( 'Claim not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		global $wpdb;
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d', $claim['concept_id'] ) );
		$cap = 'published' === sanitize_key( $request->get_param( 'state' ) ) ? HE_V2_Auth::CAP_PUBLISH : HE_V2_Auth::CAP_REVIEW;
		return HE_V2_Auth::rest_permission( $cap, $post_id, 'file06-claim-review' );
	}

	private static function mutation_begin( WP_REST_Request $request, $operation ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) { return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ); }
		if ( ! HE_V2_Auth::require_nonce( $request ) ) { return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		$user_id = get_current_user_id();
		if ( ! HE_V2_Domain::rate_allow( 'future:' . $operation . ':' . $user_id, 40, MINUTE_IN_SECONDS ) ) { return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) ); }
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) { return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		return HE_V2_Domain::idempotent_begin( $user_id, 'future:' . $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function mutation_finish( $reservation, $result, $code = 200 ) {
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		if ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], $reservation['code'] ); }
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data(); $status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			$body = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $data );
			HE_V2_Domain::idempotent_finish( $reservation['id'], $status, $body ); return $result;
		}
		$body = array( 'data' => $result, 'trace_id' => HE_V2_Domain::trace_id() ); HE_V2_Domain::idempotent_finish( $reservation['id'], $code, $body );
		return new WP_REST_Response( $body, $code );
	}

	public static function claims( $request ) {
		$row = self::concept( $request['id'], is_user_logged_in() );
		if ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		global $wpdb; $private = HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, (int) $row['post_id'] ); $where = $private ? '' : " AND c.status='published'";
		$claims = $wpdb->get_results( $wpdb->prepare( 'SELECT c.* FROM ' . self::table( 'claims' ) . " c WHERE c.concept_id=%d {$where} ORDER BY c.id ASC", $row['id'] ), ARRAY_A );
		foreach ( $claims as &$claim ) { $claim['evidence'] = $wpdb->get_results( $wpdb->prepare( 'SELECT e.id,e.reference_id,e.external_source_id,e.relation_type,e.strength,e.note FROM ' . self::table( 'claim_evidence' ) . ' e WHERE e.claim_id=%d ORDER BY e.id ASC', $claim['id'] ), ARRAY_A ); }
		return rest_ensure_response( array( 'items' => $claims, 'count' => count( $claims ) ) );
	}

	public static function create_claim( WP_REST_Request $request ) {
		$reservation = self::mutation_begin( $request, 'create-claim' ); if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, array(), 201 ); }
		$row = self::concept( $request['id'], true ); $text = trim( wp_strip_all_tags( (string) $request->get_param( 'claim_text' ) ) );
		if ( mb_strlen( $text ) < 10 ) { return self::mutation_finish( $reservation, new WP_Error( 'he_claim_too_short', __( 'A substantive claim is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$type = sanitize_key( $request->get_param( 'claim_type' ) ?: 'statement' );
		if ( ! in_array( $type, array( 'statement','safety','historical','clinical-observation','definition','mechanism','comparison' ), true ) ) { $type = 'statement'; }
		global $wpdb; $now = current_time( 'mysql', true ); $public_id = wp_generate_uuid4();
		$wpdb->insert( self::table( 'claims' ), array( 'public_id'=>$public_id, 'concept_id'=>(int)$row['id'], 'claim_type'=>$type, 'claim_text'=>$text, 'status'=>'draft',
			'confidence'=>min(100,max(0,(float)$request->get_param('confidence'))), 'row_version'=>1, 'created_by'=>get_current_user_id(), 'created_at'=>$now, 'updated_at'=>$now ) );
		$id = (int) $wpdb->insert_id; self::record_provenance( 'claim', (string)$id, 'ClaimCreated.v1', array( 'concept_id'=>(int)$row['id'], 'public_id'=>$public_id, 'claim_type'=>$type ) );
		return self::mutation_finish( $reservation, self::claim_row( $id ), 201 );
	}

	public static function add_claim_evidence( WP_REST_Request $request ) {
		$reservation = self::mutation_begin( $request, 'claim-evidence' ); if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, array(), 201 ); }
		$claim = self::claim_row( $request['claim_id'] ); $relation = sanitize_key( $request->get_param('relation_type') ?: 'supports' );
		if ( ! in_array( $relation, array( 'supports','contradicts','uncertain','historical' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_invalid_evidence_relation', __( 'Invalid evidence relation.', 'homeopathy-encyclopedia' ), array( 'status'=>400 ) ) ); }
		$reference_id = absint( $request->get_param('reference_id') ); $external_id = absint( $request->get_param('external_source_id') );
		if ( ! $reference_id && ! $external_id ) { return self::mutation_finish( $reservation, new WP_Error( 'he_evidence_source_required', __( 'A governed reference or external source is required.', 'homeopathy-encyclopedia' ), array( 'status'=>400 ) ) ); }
		global $wpdb;
		if ( $reference_id && ! (int)$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table('references') . ' WHERE id=%d AND concept_id=%d', $reference_id, $claim['concept_id'] ) ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_reference_scope_invalid', __( 'The reference is not governed by this concept.', 'homeopathy-encyclopedia' ), array( 'status'=>409 ) ) ); }
		if ( $external_id && ! (int)$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table('external_sources') . " WHERE id=%d AND subject_type='concept' AND subject_id=%d", $external_id, $claim['concept_id'] ) ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_external_scope_invalid', __( 'The external source is not linked to this concept.', 'homeopathy-encyclopedia' ), array( 'status'=>409 ) ) ); }
		$wpdb->insert( self::table('claim_evidence'), array( 'claim_id'=>(int)$claim['id'], 'reference_id'=>$reference_id, 'external_source_id'=>$external_id, 'relation_type'=>$relation,
			'strength'=>sanitize_key($request->get_param('strength')?:'ungraded'), 'note'=>sanitize_textarea_field((string)$request->get_param('note')), 'created_by'=>get_current_user_id(), 'created_at'=>current_time('mysql',true) ) );
		$id=(int)$wpdb->insert_id; self::record_provenance('claim',(string)$claim['id'],'ClaimEvidenceLinked.v1',array('evidence_id'=>$id,'relation'=>$relation,'reference_id'=>$reference_id,'external_source_id'=>$external_id));
		return self::mutation_finish($reservation,array('id'=>$id,'claim_id'=>(int)$claim['id'],'relation_type'=>$relation),201);
	}

	public static function transition_claim( WP_REST_Request $request ) {
		$reservation=self::mutation_begin($request,'transition-claim'); if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,array());}
		$claim=self::claim_row($request['claim_id']); $state=sanitize_key($request->get_param('state'));
		$allowed=array('draft'=>array('reviewed','retracted'),'reviewed'=>array('published','draft','retracted'),'published'=>array('retracted'),'retracted'=>array());
		if(!isset($allowed[$claim['status']])||!in_array($state,$allowed[$claim['status']],true)){return self::mutation_finish($reservation,new WP_Error('he_claim_transition_invalid',__('The claim transition is not allowed.','homeopathy-encyclopedia'),array('status'=>409)));}
		global $wpdb; if('published'===$state){$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('claim_evidence').' WHERE claim_id=%d',$claim['id'])); if($count<1){return self::mutation_finish($reservation,new WP_Error('he_claim_evidence_required',__('A claim cannot be published without governed evidence.','homeopathy-encyclopedia'),array('status'=>409)));}}
		$wpdb->update(self::table('claims'),array('status'=>$state,'row_version'=>(int)$claim['row_version']+1,'updated_at'=>current_time('mysql',true)),array('id'=>$claim['id']));
		self::record_provenance('claim',(string)$claim['id'],'ClaimStateChanged.v1',array('from'=>$claim['status'],'to'=>$state)); return self::mutation_finish($reservation,self::claim_row($claim['id']));
	}

	public static function record_provenance( $object_type, $object_id, $action, $payload, $source_uri='' ) {
		global $wpdb; $payload=is_array($payload)?$payload:array('value'=>$payload); $json=wp_json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		$wpdb->insert(self::table('provenance'),array('event_id'=>wp_generate_uuid4(),'object_type'=>sanitize_key($object_type),'object_id'=>sanitize_text_field((string)$object_id),
			'action_name'=>sanitize_text_field((string)$action),'source_uri'=>esc_url_raw((string)$source_uri),'actor_id'=>get_current_user_id(),'payload_hash'=>hash('sha256',$json),'payload_json'=>$json,'created_at'=>current_time('mysql',true)));
	}

	public static function provenance( $request ) {
		$row=self::concept($request['id']); if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}
		global $wpdb; $rows=$wpdb->get_results($wpdb->prepare('SELECT event_id,object_type,object_id,action_name,source_uri,actor_id,payload_hash,payload_json,created_at FROM '.self::table('provenance')." WHERE (object_type='concept' AND object_id=%s) OR (object_type='claim' AND object_id IN (SELECT CAST(id AS CHAR) FROM ".self::table('claims').' WHERE concept_id=%d)) ORDER BY id ASC LIMIT 500',(string)$row['id'],$row['id']),ARRAY_A);
		if('jsonld'===sanitize_key($request->get_param('format')?:'json')){$graph=array();foreach($rows as $item){$graph[]=array('@id'=>'urn:uuid:'.$item['event_id'],'@type'=>'prov:Activity','prov:generatedAtTime'=>gmdate('c',strtotime($item['created_at'])),'prov:wasAssociatedWith'=>$item['actor_id']?'urn:wp-user:'.$item['actor_id']:'urn:system:file-06','he:action'=>$item['action_name'],'he:payloadHash'=>$item['payload_hash'],'prov:used'=>$item['source_uri']?:null);}return rest_ensure_response(array('@context'=>array('prov'=>'http://www.w3.org/ns/prov#','he'=>home_url('/ns/file-06#')),'@graph'=>$graph));}
		return rest_ensure_response(array('items'=>$rows));
	}

	private static function external_provider_url( $provider, $identifier ) {
		$identifier=trim((string)$identifier);
		switch($provider){
			case 'crossref': return preg_match('#^10\\.\\d{4,9}/\\S+$#i',$identifier)?'https://api.crossref.org/works/'.rawurlencode($identifier):'';
			case 'pubmed': return ctype_digit($identifier)?'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id='.rawurlencode($identifier):'';
			case 'clinicaltrials': return preg_match('/^NCT\\d{8}$/i',$identifier)?'https://clinicaltrials.gov/api/v2/studies/'.rawurlencode(strtoupper($identifier)):'';
			case 'datacite': return preg_match('#^10\\.\\d{4,9}/\\S+$#i',$identifier)?'https://api.datacite.org/dois/'.rawurlencode($identifier):'';
			case 'mesh': return preg_match('/^[A-Z][0-9]{6,9}$/i',$identifier)?'https://id.nlm.nih.gov/mesh/'.rawurlencode(strtoupper($identifier)).'.json':'';
			case 'orcid': return preg_match('/^\\d{4}-\\d{4}-\\d{4}-[\\dX]{4}$/i',$identifier)?'https://pub.orcid.org/v3.0/'.rawurlencode(strtoupper($identifier)).'/record':'';
		}
		return '';
	}

	private static function fetch_external( $provider, $identifier ) {
		$url=self::external_provider_url($provider,$identifier); if(!$url){return new WP_Error('he_external_identifier_invalid',__('The external identifier is invalid.','homeopathy-encyclopedia'),array('status'=>400));}
		$headers=array('Accept'=>'application/json','User-Agent'=>'SabriFile06/'.HE_VERSION.' (knowledge-intelligence)');
		if('crossref'===$provider){$mailto=sanitize_email((string)apply_filters('he_v23_crossref_mailto',''));if($mailto){$url=add_query_arg('mailto',$mailto,$url);}}
		if('pubmed'===$provider){$key=sanitize_text_field((string)apply_filters('he_v23_ncbi_api_key',''));if($key){$url=add_query_arg('api_key',$key,$url);}}
		if('orcid'===$provider){$headers['Accept']='application/vnd.orcid+json';$token=sanitize_text_field((string)apply_filters('he_v23_orcid_access_token',''));if(!$token){return new WP_Error('he_orcid_credentials_required',__('ORCID Public API credentials are not configured.','homeopathy-encyclopedia'),array('status'=>503));}$headers['Authorization']='Bearer '.$token;}
		$args=apply_filters('he_v23_external_request_args',array('timeout'=>8,'redirection'=>2,'headers'=>$headers),$provider,$identifier,$url); $response=wp_safe_remote_get($url,$args);
		if(is_wp_error($response)){return $response;} $code=(int)wp_remote_retrieve_response_code($response); if($code<200||$code>=300){return new WP_Error('he_external_http_error',__('The external metadata provider returned an error.','homeopathy-encyclopedia'),array('status'=>502,'provider_status'=>$code));}
		$body=json_decode(wp_remote_retrieve_body($response),true); if(!is_array($body)){return new WP_Error('he_external_invalid_json',__('The external metadata provider returned invalid data.','homeopathy-encyclopedia'),array('status'=>502));}
		return array('url'=>$url,'body'=>$body);
	}

	private static function external_status( $provider, $body ) {
		$status='current';$update_type='';
		if('crossref'===$provider){$message=isset($body['message'])&&is_array($body['message'])?$body['message']:array();$updates=array_merge(isset($message['update-to'])?(array)$message['update-to']:array(),isset($message['updated-by'])?(array)$message['updated-by']:array());foreach($updates as $update){$type=sanitize_key(isset($update['type'])?$update['type']:'');if(in_array($type,array('retraction','withdrawal'),true)){return array('retracted',$type);}if(in_array($type,array('expression-of-concern','correction','erratum'),true)){$status='review-required';$update_type=$type;}}}
		return array($status,$update_type);
	}

	private static function store_external( $provider,$identifier,$subject_type,$subject_id,$body,$source_url,$actor_id ) {
		global $wpdb; list($status,$update_type)=self::external_status($provider,$body);$json=wp_json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$hash=hash('sha256',$json);$table=self::table('external_sources');
		$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE provider=%s AND identifier=%s AND subject_type=%s AND subject_id=%d",$provider,$identifier,$subject_type,$subject_id),ARRAY_A);$now=current_time('mysql',true);
		$data=array('provider'=>$provider,'identifier'=>$identifier,'subject_type'=>$subject_type,'subject_id'=>$subject_id,'status'=>$status,'update_type'=>$update_type,'metadata_json'=>$json,'metadata_hash'=>$hash,'last_checked'=>$now,'next_check'=>gmdate('Y-m-d H:i:s',time()+DAY_IN_SECONDS),'updated_at'=>$now);
		if($existing){$wpdb->update($table,$data,array('id'=>$existing['id']));$id=(int)$existing['id'];if($existing['metadata_hash']!==$hash||$existing['status']!==$status){self::record_provenance('external-source',(string)$id,'ExternalEvidenceChanged.v1',array('provider'=>$provider,'identifier'=>$identifier,'from_status'=>$existing['status'],'to_status'=>$status,'update_type'=>$update_type),$source_url);self::enqueue_impact('external-source',(string)$id,'ExternalEvidenceChanged.v1',array('provider'=>$provider,'identifier'=>$identifier,'subject_type'=>$subject_type,'subject_id'=>$subject_id,'status'=>$status));if('concept'===$subject_type&&in_array($status,array('retracted','review-required'),true)){self::upsert_gap($subject_id,'external-integrity-change','critical',array('provider'=>$provider,'identifier'=>$identifier,'status'=>$status,'update_type'=>$update_type));}}}
		else{$data['created_by']=$actor_id;$data['created_at']=$now;$wpdb->insert($table,$data);$id=(int)$wpdb->insert_id;self::record_provenance('external-source',(string)$id,'ExternalEvidenceLinked.v1',array('provider'=>$provider,'identifier'=>$identifier,'subject_type'=>$subject_type,'subject_id'=>$subject_id,'status'=>$status),$source_url);}
		return $wpdb->get_row($wpdb->prepare("SELECT id,provider,identifier,subject_type,subject_id,status,update_type,metadata_hash,last_checked,next_check FROM {$table} WHERE id=%d",$id),ARRAY_A);
	}

	public static function resolve_external( WP_REST_Request $request ) {
		$reservation=self::mutation_begin($request,'external-resolve');if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,array());}
		$provider=sanitize_key($request->get_param('provider'));if(!in_array($provider,array('crossref','pubmed','clinicaltrials','orcid','datacite','mesh'),true)){return self::mutation_finish($reservation,new WP_Error('he_external_provider_invalid',__('Unsupported external metadata provider.','homeopathy-encyclopedia'),array('status'=>400)));}
		$identifier=trim(sanitize_text_field((string)$request->get_param('identifier')));$subject_type=sanitize_key($request->get_param('subject_type')?:'concept');$subject_id=absint($request->get_param('subject_id'));
		if(!in_array($subject_type,array('concept','user','research','dataset'),true)){return self::mutation_finish($reservation,new WP_Error('he_external_subject_invalid',__('Invalid external-link subject.','homeopathy-encyclopedia'),array('status'=>400)));}
		if('concept'===$subject_type){global $wpdb;if(!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.HE_V2_Schema::table('concepts').' WHERE id=%d',$subject_id))){return self::mutation_finish($reservation,new WP_Error('he_external_subject_missing',__('The target concept does not exist.','homeopathy-encyclopedia'),array('status'=>404)));}}
		if('orcid'===$provider&&('user'!==$subject_type||($subject_id!==get_current_user_id()&&!HE_V2_Auth::can(HE_V2_Auth::CAP_REVIEW)))){return self::mutation_finish($reservation,new WP_Error('he_orcid_subject_forbidden',__('ORCID may only be linked to the authorized researcher identity.','homeopathy-encyclopedia'),array('status'=>403)));}
		$fetched=self::fetch_external($provider,$identifier);if(is_wp_error($fetched)){return self::mutation_finish($reservation,$fetched);}return self::mutation_finish($reservation,self::store_external($provider,$identifier,$subject_type,$subject_id,$fetched['body'],$fetched['url'],get_current_user_id()));
	}

	private static function normalized_text($text){$text=remove_accents(wp_strip_all_tags((string)$text));$text=strtolower(preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$text));return trim(preg_replace('/\\s+/',' ',$text));}
	private static function similarity($a,$b){$a=self::normalized_text($a);$b=self::normalized_text($b);if(''===$a||''===$b){return 0.0;}similar_text(mb_substr($a,0,2000),mb_substr($b,0,2000),$percent);return round((float)$percent,2);}
	private static function alias_set($concept_id){global $wpdb;$rows=$wpdb->get_col($wpdb->prepare('SELECT normalized_alias FROM '.HE_V2_Schema::table('aliases').' WHERE concept_id=%d',$concept_id));return array_values(array_unique(array_filter(array_map(array(__CLASS__,'normalized_text'),$rows))));}

	public static function semantic_duplicates( $request ) {
		$row=self::concept($request['id'],true);if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}global $wpdb;$versions=HE_V2_Schema::table('versions');$concepts=HE_V2_Schema::table('concepts');
		$source=$wpdb->get_row($wpdb->prepare("SELECT v.title,v.summary FROM {$versions} v WHERE v.concept_id=%d AND v.version_number=%d",$row['id'],$row['current_version']),ARRAY_A);if(!$source){return rest_ensure_response(array('items'=>array()));}
		$candidates=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.public_id,c.canonical_slug,c.type_slug,c.current_version,v.title,v.summary FROM {$concepts} c JOIN {$versions} v ON v.concept_id=c.id AND v.version_number=c.current_version WHERE c.id<>%d AND c.merged_into_id=0 ORDER BY c.updated_at DESC LIMIT 250",$row['id']),ARRAY_A);$source_aliases=self::alias_set($row['id']);$out=array();
		foreach($candidates as $candidate){$title_score=self::similarity($source['title'],$candidate['title']);$summary_score=self::similarity($source['summary'],$candidate['summary']);$aliases=self::alias_set($candidate['id']);$overlap=count(array_intersect($source_aliases,$aliases));$alias_score=$overlap?min(100,50+25*$overlap):0;$score=round(0.60*$title_score+0.25*$summary_score+0.15*$alias_score,2);if($score>=55){$out[]=array('id'=>$candidate['public_id'],'slug'=>$candidate['canonical_slug'],'type'=>$candidate['type_slug'],'title'=>$candidate['title'],'score'=>$score,'signals'=>array('title'=>$title_score,'summary'=>$summary_score,'alias'=>$alias_score));}}
		usort($out,static function($a,$b){return $a['score']===$b['score']?0:($a['score']<$b['score']?1:-1);});return rest_ensure_response(array('items'=>array_slice($out,0,50),'auto_merge'=>false));
	}

	public static function graph_explorer( $request ) {
		$row=self::concept($request['id']);if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}$dto=HE_V2_Domain::public_dto($row);$graph=HE_V2_Domain::get_related_graph($row['public_id'],2,100);global $wpdb;
		$claims=$wpdb->get_results($wpdb->prepare('SELECT c.* FROM '.self::table('claims')." c WHERE c.concept_id=%d AND c.status='published' ORDER BY c.id ASC",$row['id']),ARRAY_A);foreach($claims as &$claim){$claim['evidence']=$wpdb->get_results($wpdb->prepare('SELECT reference_id,external_source_id,relation_type,strength,note FROM '.self::table('claim_evidence').' WHERE claim_id=%d ORDER BY id ASC',$claim['id']),ARRAY_A);}
		$external=$wpdb->get_results($wpdb->prepare('SELECT id,provider,identifier,status,update_type,last_checked FROM '.self::table('external_sources')." WHERE subject_type='concept' AND subject_id=%d ORDER BY provider,identifier",$row['id']),ARRAY_A);
		return rest_ensure_response(array('entry'=>array_intersect_key($dto,array_flip(array('id','canonical_url','type','title','summary','version','safety_status','record_status','freshness'))),'relations'=>$graph,'claims'=>$claims,'external_sources'=>$external,'presentation_owner'=>'file-25'));
	}

	public static function time_machine($request){$row=self::concept($request['id']);if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}global $wpdb;$versions=$wpdb->get_results($wpdb->prepare('SELECT version_number,status,title,summary,content_hash,change_reason,effective_at,created_by,created_at FROM '.HE_V2_Schema::table('versions').' WHERE concept_id=%d ORDER BY version_number ASC',$row['id']),ARRAY_A);return rest_ensure_response(array('entry_id'=>$row['public_id'],'current_version'=>(int)$row['current_version'],'versions'=>$versions));}

	public static function watch_state($request){$row=self::concept($request['id']);if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}global $wpdb;$watching=(bool)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('watchers').' WHERE user_id=%d AND concept_id=%d',get_current_user_id(),$row['id']));return rest_ensure_response(array('watching'=>$watching,'notification_owner'=>'file-19'));}
	public static function watch(WP_REST_Request $request){$reservation=self::mutation_begin($request,'watch');if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,array());}$row=self::concept($request['id']);$enabled=filter_var($request->get_param('enabled'),FILTER_VALIDATE_BOOLEAN);global $wpdb;if($enabled){$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.self::table('watchers').' (user_id,concept_id,created_at) VALUES (%d,%d,%s)',get_current_user_id(),$row['id'],current_time('mysql',true)));}else{$wpdb->delete(self::table('watchers'),array('user_id'=>get_current_user_id(),'concept_id'=>$row['id']));}self::record_provenance('concept',(string)$row['id'],$enabled?'KnowledgeWatchAdded.v1':'KnowledgeWatchRemoved.v1',array('user_id'=>get_current_user_id()));return self::mutation_finish($reservation,array('watching'=>$enabled,'notification_owner'=>'file-19'));}

	public static function translations($request){$row=self::concept($request['id']);if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}global $wpdb;$can_review=HE_V2_Auth::can(HE_V2_Auth::CAP_REVIEW,(int)$row['post_id']);$where=$can_review?'':" AND status='published'";$items=$wpdb->get_results($wpdb->prepare('SELECT language,status,source_version,translation_version,translation_json,content_hash,translator_id,reviewer_id,updated_at FROM '.self::table('translations')." WHERE concept_id=%d {$where} ORDER BY language",$row['id']),ARRAY_A);foreach($items as &$item){$item['content']=json_decode($item['translation_json'],true);unset($item['translation_json']);$item['outdated']=(int)$item['source_version']<(int)$row['current_version'];}return rest_ensure_response(array('items'=>$items,'source_version'=>(int)$row['current_version']));}

	public static function save_translation(WP_REST_Request $request){$reservation=self::mutation_begin($request,'save-translation');if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,array());}$row=self::concept($request['id'],true);$language=sanitize_text_field($request->get_param('language'));if(!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/',$language)||$language===$row['language']){return self::mutation_finish($reservation,new WP_Error('he_translation_language_invalid',__('A valid target language different from the source is required.','homeopathy-encyclopedia'),array('status'=>400)));}$content=$request->get_param('content');$content=is_array($content)?$content:array();$clean=array('title'=>sanitize_text_field(isset($content['title'])?$content['title']:''),'summary'=>sanitize_textarea_field(isset($content['summary'])?$content['summary']:''),'body'=>wp_kses_post(isset($content['body'])?$content['body']:''));if(''===$clean['title']||''===$clean['summary']){return self::mutation_finish($reservation,new WP_Error('he_translation_content_required',__('Translated title and summary are required.','homeopathy-encyclopedia'),array('status'=>400)));}$status=sanitize_key($request->get_param('status')?:'draft');if('published'===$status&&!HE_V2_Auth::can(HE_V2_Auth::CAP_PUBLISH,(int)$row['post_id'])){$status='review-required';}if(!in_array($status,array('draft','review-required','published','outdated'),true)){$status='draft';}$json=wp_json_encode($clean,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$hash=hash('sha256',$json);global $wpdb;$table=self::table('translations');$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE concept_id=%d AND language=%s",$row['id'],$language),ARRAY_A);$now=current_time('mysql',true);$data=array('status'=>$status,'source_version'=>(int)$row['current_version'],'translation_json'=>$json,'content_hash'=>$hash,'translator_id'=>get_current_user_id(),'reviewer_id'=>'published'===$status?get_current_user_id():0,'updated_at'=>$now);if($existing){$data['translation_version']=(int)$existing['translation_version']+1;$wpdb->update($table,$data,array('id'=>$existing['id']));$id=(int)$existing['id'];}else{$data['concept_id']=(int)$row['id'];$data['language']=$language;$data['translation_version']=1;$data['created_at']=$now;$wpdb->insert($table,$data);$id=(int)$wpdb->insert_id;}self::record_provenance('translation',(string)$id,'KnowledgeTranslationSaved.v1',array('concept_id'=>(int)$row['id'],'language'=>$language,'source_version'=>(int)$row['current_version'],'status'=>$status,'content_hash'=>$hash));return self::mutation_finish($reservation,array('id'=>$id,'language'=>$language,'status'=>$status,'source_version'=>(int)$row['current_version'],'content_hash'=>$hash));}

	private static function entry_references($concept_id){global $wpdb;return $wpdb->get_results($wpdb->prepare('SELECT id,source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade FROM '.HE_V2_Schema::table('references').' WHERE concept_id=%d ORDER BY id ASC',$concept_id),ARRAY_A);}
	private static function citation_ris($refs){$out=array();foreach($refs as $ref){$out[]='TY  - GEN';if($ref['author']){$out[]='AU  - '.$ref['author'];}$out[]='TI  - '.$ref['title'];if($ref['publisher']){$out[]='PB  - '.$ref['publisher'];}if($ref['year']){$out[]='PY  - '.$ref['year'];}if($ref['doi']){$out[]='DO  - '.$ref['doi'];}if($ref['url']){$out[]='UR  - '.$ref['url'];}$out[]='ER  - ';}return implode("\n",$out)."\n";}
	private static function citation_bibtex($refs){$out=array();foreach($refs as $ref){$key='sabri'.absint($ref['id']);$fields=array('title'=>$ref['title']);if($ref['author']){$fields['author']=$ref['author'];}if($ref['publisher']){$fields['publisher']=$ref['publisher'];}if($ref['year']){$fields['year']=$ref['year'];}if($ref['doi']){$fields['doi']=$ref['doi'];}if($ref['url']){$fields['url']=$ref['url'];}$lines=array();foreach($fields as $name=>$value){$value=str_replace(array('{','}'),'',(string)$value);$lines[]='  '.$name.' = {'.$value.'}';}$out[]='@misc{'.$key.",\n".implode(",\n",$lines)."\n}";}return implode("\n\n",$out)."\n";}
	public static function citation_bundle($request){$row=self::concept($request['id']);if(!$row){return new WP_Error('he_not_found',__('Entry not found.','homeopathy-encyclopedia'),array('status'=>404));}$refs=self::entry_references($row['id']);$format=sanitize_key($request->get_param('format')?:'citeproc');if('ris'===$format){return rest_ensure_response(array('format'=>'RIS','content'=>self::citation_ris($refs)));}if('bibtex'===$format){return rest_ensure_response(array('format'=>'BibTeX','content'=>self::citation_bibtex($refs)));}if('jsonld'===$format){$items=array();foreach($refs as $ref){$items[]=array_filter(array('@type'=>'CreativeWork','name'=>$ref['title'],'author'=>$ref['author'],'publisher'=>$ref['publisher'],'datePublished'=>$ref['year'],'identifier'=>$ref['doi'],'url'=>$ref['url']));}return rest_ensure_response(array('@context'=>'https://schema.org','@graph'=>$items));}$items=array();foreach($refs as $ref){$items[]=array_filter(array('id'=>$ref['doi']?:'file06-ref-'.$ref['id'],'type'=>'article','title'=>$ref['title'],'author'=>$ref['author']?array(array('literal'=>$ref['author'])):array(),'publisher'=>$ref['publisher'],'issued'=>$ref['year']?array('raw'=>$ref['year']):null,'DOI'=>$ref['doi'],'URL'=>$ref['url']));}return rest_ensure_response(array('format'=>'Citeproc JSON','items'=>$items));}

	private static function enqueue_impact($object_type,$object_id,$event_name,$payload){global $wpdb;$event_id=wp_generate_uuid4();$json=wp_json_encode(is_array($payload)?$payload:array(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);foreach(array('file-05','file-12','file-15','file-16','file-21','file-26') as $consumer){$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.self::table('impact_queue').' (event_id,object_type,object_id,event_name,consumer_file,status,attempts,payload_json,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%d,%s,%s,%s)',$event_id,sanitize_key($object_type),sanitize_text_field((string)$object_id),sanitize_text_field($event_name),$consumer,'pending',0,$json,current_time('mysql',true),current_time('mysql',true)));}return $event_id;}
	public static function on_core_event($name,$payload,$event_id='',$trace_id=''){$name=sanitize_text_field((string)$name);$payload=is_array($payload)?$payload:array();$object_id=isset($payload['public_id'])?$payload['public_id']:(isset($payload['id'])?$payload['id']:($event_id?:wp_generate_uuid4()));$concept_id=self::concept_internal_id($object_id);self::record_provenance('concept',(string)($concept_id?:$object_id),$name,array('payload'=>$payload,'trace_id'=>$trace_id));if(preg_match('/(?:Published|Corrected|Retracted|Merged|IntegrityStateChanged)/',$name)){self::enqueue_impact('concept',(string)($concept_id?:$object_id),$name,$payload);self::notify_watchers($concept_id?:$object_id,$name,$payload);self::mark_translations_outdated($concept_id?:$object_id);}}
	private static function concept_internal_id($value){global $wpdb;if(is_numeric($value)){return (int)$value;}return (int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.HE_V2_Schema::table('concepts').' WHERE public_id=%s OR canonical_slug=%s',$value,$value));}
	private static function notify_watchers($concept_value,$event_name,$payload){$concept_id=self::concept_internal_id($concept_value);if(!$concept_id){return;}global $wpdb;$users=$wpdb->get_col($wpdb->prepare('SELECT user_id FROM '.self::table('watchers').' WHERE concept_id=%d',$concept_id));foreach($users as $user_id){do_action('sabri_notification_event',array('owner'=>'file-06','transport_owner'=>'file-19','event'=>'KnowledgeWatchChanged.v1','user_id'=>(int)$user_id,'concept_id'=>$concept_id,'source_event'=>$event_name,'payload'=>$payload));}}
	private static function mark_translations_outdated($concept_value){$concept_id=self::concept_internal_id($concept_value);if(!$concept_id){return;}global $wpdb;$current=(int)$wpdb->get_var($wpdb->prepare('SELECT current_version FROM '.HE_V2_Schema::table('concepts').' WHERE id=%d',$concept_id));$wpdb->query($wpdb->prepare('UPDATE '.self::table('translations')." SET status='outdated',updated_at=%s WHERE concept_id=%d AND source_version<%d AND status='published'",current_time('mysql',true),$concept_id,$current));}

	public static function reconcile_impact(WP_REST_Request $request){$reservation=self::mutation_begin($request,'impact-reconcile');if(is_wp_error($reservation)||!empty($reservation['replay'])){return self::mutation_finish($reservation,array());}$result=self::process_impact_queue(min(100,max(1,absint($request->get_param('limit')?:50))));return self::mutation_finish($reservation,$result);}
	private static function process_impact_queue($limit=50){global $wpdb;$table=self::table('impact_queue');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status IN ('pending','retry') ORDER BY id ASC LIMIT %d",$limit),ARRAY_A);$done=0;$failed=0;foreach($rows as $row){$payload=json_decode($row['payload_json'],true);try{$envelope=array('event_id'=>$row['event_id'],'event_name'=>$row['event_name'],'object_type'=>$row['object_type'],'object_id'=>$row['object_id'],'payload'=>is_array($payload)?$payload:array(),'owner'=>'file-06','contract_version'=>HE_CONTRACT_VERSION);do_action('sabri_file06_impact_event',$row['consumer_file'],$envelope);$ack=(bool)apply_filters('sabri_file06_impact_delivery_ack',false,$row['consumer_file'],$envelope);if(!$ack){throw new RuntimeException('consumer-ack-missing:'.$row['consumer_file']);}$wpdb->update($table,array('status'=>'delivered','attempts'=>(int)$row['attempts']+1,'updated_at'=>current_time('mysql',true)),array('id'=>$row['id']));$done++;}catch(Throwable $error){$attempts=(int)$row['attempts']+1;$wpdb->update($table,array('status'=>$attempts>=5?'dead-letter':'retry','attempts'=>$attempts,'updated_at'=>current_time('mysql',true)),array('id'=>$row['id']));$failed++;}}return array('processed'=>count($rows),'delivered'=>$done,'failed'=>$failed);}

	private static function upsert_gap($concept_id,$gap_type,$severity,$details){global $wpdb;$table=self::table('research_gaps');$now=current_time('mysql',true);$json=wp_json_encode(is_array($details)?$details:array(),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE concept_id=%d AND gap_type=%s",$concept_id,$gap_type));$data=array('severity'=>sanitize_key($severity),'details_json'=>$json,'status'=>'open','updated_at'=>$now);if($existing){$wpdb->update($table,$data,array('id'=>$existing));}else{$data['concept_id']=$concept_id;$data['gap_type']=sanitize_key($gap_type);$data['created_at']=$now;$wpdb->insert($table,$data);}}
	private static function refresh_freshness($limit=100){global $wpdb;$concepts=$wpdb->get_results($wpdb->prepare('SELECT id,current_version,updated_at,review_status,safety_status,status FROM '.HE_V2_Schema::table('concepts')." WHERE merged_into_id=0 ORDER BY updated_at ASC LIMIT %d",$limit),ARRAY_A);foreach($concepts as $concept){$refs=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.HE_V2_Schema::table('references').' WHERE concept_id=%d',$concept['id']));$contradictions=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('claim_evidence').' e JOIN '.self::table('claims')." c ON c.id=e.claim_id WHERE c.concept_id=%d AND e.relation_type='contradicts'",$concept['id']));$external_alerts=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('external_sources')." WHERE subject_type='concept' AND subject_id=%d AND status IN ('retracted','review-required')",$concept['id']));$age_days=max(0,floor((time()-strtotime($concept['updated_at'].' UTC'))/DAY_IN_SECONDS));$priority=min(100,($refs?0:35)+min(30,(int)floor($age_days/30))+min(20,$contradictions*10)+min(40,$external_alerts*20)+('reviewed'===$concept['safety_status']?0:15));$state=$external_alerts?'urgent-review':($priority>=70?'stale':($priority>=35?'review-due':'current'));$due=gmdate('Y-m-d H:i:s',time()+('urgent-review'===$state?DAY_IN_SECONDS:('stale'===$state?7*DAY_IN_SECONDS:90*DAY_IN_SECONDS)));$reasons=array('reference_count'=>$refs,'age_days'=>$age_days,'contradictory_evidence_links'=>$contradictions,'external_integrity_alerts'=>$external_alerts,'safety_status'=>$concept['safety_status']);$table=self::table('freshness');$exists=(int)$wpdb->get_var($wpdb->prepare("SELECT concept_id FROM {$table} WHERE concept_id=%d",$concept['id']));$data=array('freshness_state'=>$state,'last_evidence_scan'=>current_time('mysql',true),'review_due_at'=>$due,'priority_score'=>$priority,'reason_json'=>wp_json_encode($reasons),'updated_at'=>current_time('mysql',true));if($exists){$wpdb->update($table,$data,array('concept_id'=>$concept['id']));}else{$data['concept_id']=$concept['id'];$data['last_human_review']=null;$wpdb->insert($table,$data);}if(0===$refs){self::upsert_gap($concept['id'],'missing-references','high',$reasons);}if($contradictions){self::upsert_gap($concept['id'],'contradictory-evidence',$contradictions>2?'high':'medium',$reasons);}if($age_days>365){self::upsert_gap($concept['id'],'stale-evidence-review','medium',$reasons);}}return count($concepts);}
	private static function refresh_external_sources($limit=25){global $wpdb;$table=self::table('external_sources');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE next_check<=UTC_TIMESTAMP() ORDER BY next_check ASC LIMIT %d",$limit),ARRAY_A);$checked=0;foreach($rows as $row){$fetched=self::fetch_external($row['provider'],$row['identifier']);if(is_wp_error($fetched)){$wpdb->update($table,array('next_check'=>gmdate('Y-m-d H:i:s',time()+6*HOUR_IN_SECONDS),'updated_at'=>current_time('mysql',true)),array('id'=>$row['id']));continue;}self::store_external($row['provider'],$row['identifier'],$row['subject_type'],(int)$row['subject_id'],$fetched['body'],$fetched['url'],(int)$row['created_by']);$checked++;}return $checked;}
	public static function scheduled_refresh(){self::ensure_schema();$external=self::refresh_external_sources(25);$freshness=self::refresh_freshness(100);$impact=self::process_impact_queue(50);self::record_provenance('system','file-06','FutureIntelligenceRefreshCompleted.v1',array('external_checked'=>$external,'freshness_scanned'=>$freshness,'impact'=>$impact));}

	public static function research_gaps($request){global $wpdb;$severity=sanitize_key($request->get_param('severity')?:'');$where="g.status='open'";$args=array();if($severity&&in_array($severity,array('low','medium','high','critical'),true)){$where.=' AND g.severity=%s';$args[]=$severity;}$sql='SELECT g.id,g.concept_id,g.gap_type,g.severity,g.details_json,g.updated_at,c.public_id,c.canonical_slug,c.type_slug FROM '.self::table('research_gaps').' g JOIN '.HE_V2_Schema::table('concepts')." c ON c.id=g.concept_id WHERE {$where} ORDER BY FIELD(g.severity,'critical','high','medium','low'),g.updated_at DESC LIMIT 200";$rows=$args?$wpdb->get_results($wpdb->prepare($sql,$args),ARRAY_A):$wpdb->get_results($sql,ARRAY_A);foreach($rows as &$row){$row['details']=json_decode($row['details_json'],true);unset($row['details_json']);}return rest_ensure_response(array('items'=>$rows));}

	public static function integrity_command_center(){global $wpdb;$counts=array();$counts['stale_or_due']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('freshness')." WHERE freshness_state IN ('review-due','stale','urgent-review')");$counts['urgent_review']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('freshness')." WHERE freshness_state='urgent-review'");$counts['retracted_external']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('external_sources')." WHERE status='retracted'");$counts['research_gaps']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('research_gaps')." WHERE status='open'");$counts['translation_outdated']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('translations')." WHERE status='outdated'");$counts['claim_contradictions']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('claim_evidence')." WHERE relation_type='contradicts'");$counts['impact_pending']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('impact_queue')." WHERE status IN ('pending','retry')");$counts['impact_dead_letter']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('impact_queue')." WHERE status='dead-letter'");$counts['claims_without_evidence']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('claims').' c LEFT JOIN '.self::table('claim_evidence')." e ON e.claim_id=c.id WHERE c.status IN ('reviewed','published') AND e.id IS NULL");$counts['orphan_external_links']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table('external_sources')." e LEFT JOIN ".HE_V2_Schema::table('concepts')." c ON e.subject_type='concept' AND e.subject_id=c.id WHERE e.subject_type='concept' AND c.id IS NULL");return rest_ensure_response(array('owner'=>'file-06','generated_at'=>gmdate('c'),'counts'=>$counts,'health'=>self::health(),'boundaries'=>array('security_assurance'=>'file-24','notifications_transport'=>'file-19','layout'=>'file-20','visuals'=>'file-25','global_search'=>'file-26')));}

	public static function health(){global $wpdb;$tables=array('claims','claim_evidence','provenance','external_sources','watchers','translations','freshness','impact_queue','research_gaps');$available=array();foreach($tables as $suffix){$table=self::table($suffix);$available[$suffix]=$table===$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));}return array('schema'=>(int)get_option(self::SCHEMA_OPTION,0),'expected_schema'=>self::SCHEMA_VERSION,'features'=>count(self::capabilities()),'tables'=>$available,'cron_scheduled'=>(bool)wp_next_scheduled(self::CRON_HOOK),'external_auto_publish'=>false);}
}

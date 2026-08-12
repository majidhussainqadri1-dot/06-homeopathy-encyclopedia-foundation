<?php
/**
 * File 06 v2.4 Future-18 schema, integrity and background-job hardening.
 *
 * This layer corrects the v2.3 additive schema without changing canonical
 * ownership: File 06 owns knowledge truth; companion files remain consumers.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Future_Schema {
	const OPTION_VERSION = 'he_v24_future_version';
	const VERSION = 2;
	const CRON = 'he_v24_future_maintenance';
	const BATCH = 40;
	const MAX_PROVIDER_BYTES = 524288;
	const OPTION_MAINTENANCE_LEASE = 'he_v24_future_maintenance_lease';
	const MAINTENANCE_LEASE_TTL = 20 * MINUTE_IN_SECONDS;
	private static $maintenance_lease_token = '';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'he_' . sanitize_key( $name );
	}

	public static function required_shape() {
		return array(
			'claims'=>array('id','concept_id','version_id','public_id','claim_key','claim_text','claim_state','evidence_state','review_status','row_version'),
			'claim_evidence'=>array('id','claim_id','reference_id','external_id','relation','weight'),
			'concept_mappings'=>array('id','concept_id','vocabulary','external_id','mapping_state'),
			'similarity'=>array('id','concept_a','concept_b','score','state'),
			'provenance'=>array('id','object_type','object_id','metadata_json','parent_hash','record_hash'),
			'external_records'=>array('id','provider','external_id','concept_id','object_type','object_id','status','review_required'),
			'researcher_ids'=>array('id','user_id','provider','external_id','mapping_state'),
			'freshness'=>array('concept_id','freshness_state','priority_score','updated_at'),
			'impact_queue'=>array('id','source_type','source_id','consumer_file','dedupe_key','impact_state','attempts','last_error','acknowledged_at'),
			'research_gaps'=>array('id','concept_id','gap_type','state','priority_score','resolved_at'),
			'watchlists'=>array('id','user_id','object_type','object_id','event_mask','active'),
			'translations'=>array('id','concept_id','locale','source_locale','source_version','translation_version','status','content_hash','published_at'),
		);
	}

	public static function schema_complete() {
		global $wpdb;
		foreach ( self::required_shape() as $key => $columns ) {
			$table=self::table($key);
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
			$actual=(array)$wpdb->get_col( "SHOW COLUMNS FROM `{$table}`",0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach($columns as $column){ if(!in_array($column,$actual,true)){ return false; } }
		}
		return true;
	}

	public static function hooks() {
		remove_action( HE_V23_Future::CRON, array( 'HE_V23_Future', 'maintenance' ) );
		if ( wp_next_scheduled( HE_V23_Future::CRON ) ) {
			wp_clear_scheduled_hook( HE_V23_Future::CRON );
		}
		add_action( self::CRON, array( __CLASS__, 'maintenance' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 140 );
		self::ensure_schedule();
	}

	public static function activate() {
		self::install();
		wp_clear_scheduled_hook( HE_V23_Future::CRON );
		self::ensure_schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		wp_clear_scheduled_hook( HE_V23_Future::CRON );
	}

	private static function ensure_schedule() {
		if ( wp_next_scheduled( self::CRON ) ) { return true; }
		$result = wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::CRON, array(), true );
		if ( is_wp_error( $result ) || ! $result ) {
			HE_V2_Schema::record_runtime_failure( 'future_maintenance_schedule_failed', 'File 06 could not register the Future-18 maintenance schedule.' );
			return false;
		}
		return true;
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPTION_VERSION, 0 ) < self::VERSION || ! self::schema_complete() ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$sql = array();

		$sql[] = "CREATE TABLE " . self::table( 'claims' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			version_id bigint(20) unsigned NOT NULL DEFAULT 0,
			public_id char(36) NOT NULL,
			claim_key varchar(120) NOT NULL,
			claim_text longtext NOT NULL,
			claim_state varchar(30) NOT NULL DEFAULT 'active',
			evidence_state varchar(30) NOT NULL DEFAULT 'ungraded',
			confidence decimal(6,5) NOT NULL DEFAULT 0,
			review_status varchar(30) NOT NULL DEFAULT 'pending',
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY concept_claim (concept_id,claim_key),
			KEY concept_review (concept_id,review_status),
			KEY version_id (version_id),
			KEY claim_state (claim_state)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'claim_evidence' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			claim_id bigint(20) unsigned NOT NULL,
			reference_id bigint(20) unsigned NOT NULL DEFAULT 0,
			external_id varchar(191) NOT NULL DEFAULT '',
			relation varchar(24) NOT NULL,
			weight decimal(5,2) NOT NULL DEFAULT 0,
			note text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY claim_source (claim_id,reference_id,external_id(96),relation),
			KEY claim_id (claim_id)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'concept_mappings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			vocabulary varchar(30) NOT NULL,
			external_id varchar(191) NOT NULL,
			preferred_label text NOT NULL,
			mapping_state varchar(30) NOT NULL DEFAULT 'proposed',
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY concept_vocab (concept_id,vocabulary,external_id(100)),
			KEY vocabulary (vocabulary)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'similarity' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_a bigint(20) unsigned NOT NULL,
			concept_b bigint(20) unsigned NOT NULL,
			score decimal(6,5) NOT NULL DEFAULT 0,
			reason_json longtext NOT NULL,
			state varchar(24) NOT NULL DEFAULT 'candidate',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY pair (concept_a,concept_b),
			KEY score (score),
			KEY state (state)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'provenance' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(40) NOT NULL,
			object_id varchar(191) NOT NULL,
			action varchar(60) NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_uri text NOT NULL,
			source_hash char(64) NOT NULL DEFAULT '',
			transform varchar(80) NOT NULL DEFAULT '',
			metadata_json longtext NOT NULL,
			parent_hash char(64) NOT NULL DEFAULT '',
			record_hash char(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY record_hash (record_hash),
			KEY object_lookup (object_type,object_id(96)),
			KEY created_at (created_at)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'external_records' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(30) NOT NULL,
			external_id varchar(191) NOT NULL,
			concept_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(30) NOT NULL DEFAULT 'concept',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			relation varchar(40) NOT NULL DEFAULT 'literature',
			purpose varchar(40) NOT NULL DEFAULT 'literature',
			status varchar(30) NOT NULL DEFAULT 'staged',
			metadata_json longtext NOT NULL,
			source_updated_at datetime NULL,
			checked_at datetime NOT NULL,
			review_required tinyint(1) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_binding (provider,external_id(96),object_type,object_id),
			KEY concept_id (concept_id),
			KEY object_lookup (object_type,object_id),
			KEY review_required (review_required)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'researcher_ids' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			provider varchar(30) NOT NULL DEFAULT 'orcid',
			external_id varchar(191) NOT NULL,
			preferred_label varchar(191) NOT NULL DEFAULT '',
			mapping_state varchar(30) NOT NULL DEFAULT 'reviewed',
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_external (provider,external_id(120)),
			UNIQUE KEY user_provider (user_id,provider),
			KEY mapping_state (mapping_state)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'freshness' ) . " (
			concept_id bigint(20) unsigned NOT NULL,
			last_evidence_scan datetime NULL,
			last_human_review datetime NULL,
			review_due_at datetime NULL,
			freshness_state varchar(24) NOT NULL DEFAULT 'review-due',
			risk_tier varchar(16) NOT NULL DEFAULT 'normal',
			priority_score decimal(7,2) NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (concept_id),
			KEY freshness_state (freshness_state),
			KEY priority_score (priority_score),
			KEY review_due_at (review_due_at)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'impact_queue' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_type varchar(30) NOT NULL,
			source_id varchar(191) NOT NULL,
			event_name varchar(80) NOT NULL,
			consumer_file varchar(16) NOT NULL,
			dedupe_key char(64) NOT NULL,
			impact_state varchar(24) NOT NULL DEFAULT 'pending',
			payload_json longtext NOT NULL,
			attempts int unsigned NOT NULL DEFAULT 0,
			last_error text NOT NULL,
			next_attempt_at datetime NULL,
			acknowledged_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_key (dedupe_key),
			KEY impact_state (impact_state),
			KEY consumer_file (consumer_file),
			KEY source_lookup (source_type,source_id(96))
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'research_gaps' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			gap_type varchar(40) NOT NULL,
			severity varchar(16) NOT NULL DEFAULT 'medium',
			priority_score decimal(7,2) NOT NULL DEFAULT 0,
			rationale text NOT NULL,
			metrics_json longtext NOT NULL,
			state varchar(24) NOT NULL DEFAULT 'open',
			detected_at datetime NOT NULL,
			resolved_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY concept_gap (concept_id,gap_type),
			KEY severity (severity),
			KEY priority_score (priority_score),
			KEY state (state)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'watchlists' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			object_type varchar(30) NOT NULL,
			object_id varchar(191) NOT NULL,
			event_mask varchar(255) NOT NULL DEFAULT 'updated,corrected,retracted,translation',
			active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_object (user_id,object_type,object_id(96)),
			KEY user_id (user_id),
			KEY active (active)
		) {$c};";

		$sql[] = "CREATE TABLE " . self::table( 'translations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			locale varchar(20) NOT NULL,
			source_locale varchar(20) NOT NULL DEFAULT 'en-US',
			source_version bigint(20) unsigned NOT NULL DEFAULT 0,
			translation_version bigint(20) unsigned NOT NULL DEFAULT 1,
			status varchar(30) NOT NULL DEFAULT 'draft',
			translator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			content_json longtext NOT NULL,
			content_hash char(64) NOT NULL,
			published_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY concept_locale (concept_id,locale),
			KEY source_version (source_version),
			KEY status (status)
		) {$c};";

		foreach ( $sql as $statement ) {
			$wpdb->last_error = '';
			dbDelta( $statement );
			if ( '' !== (string) $wpdb->last_error ) {
				throw new RuntimeException( 'File 06 Future schema dbDelta failed: ' . $wpdb->last_error );
			}
		}
		self::verify_schema();
		update_option( self::OPTION_VERSION, self::VERSION, false );
	}

	private static function verify_schema() {
		if ( ! self::schema_complete() ) { throw new RuntimeException( 'File 06 v2.4 Future schema verification failed: required table/column shape is incomplete.' ); }
	}

	public static function concept_row( $concept_id, $public_only = false ) {
		global $wpdb;
		$concept_id = absint( $concept_id );
		if ( ! $concept_id ) {
			return null;
		}
		$where = 'id=%d';
		if ( $public_only ) {
			$where .= " AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0";
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE ' . $where, $concept_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function version_belongs( $concept_id, $version_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', absint( $version_id ), absint( $concept_id ) ) );
	}

	public static function public_claims( $concept_id ) {
		global $wpdb;
		$concept = self::concept_row( $concept_id, true );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT c.id AS internal_claim_id,c.public_id,c.claim_key,c.claim_text,c.claim_state,c.evidence_state,c.confidence,c.review_status,v.version_number FROM ' . self::table( 'claims' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'versions' ) . " v ON v.id=c.version_id AND v.concept_id=c.concept_id WHERE c.concept_id=%d AND c.claim_state='active' AND c.review_status='approved' AND c.version_id=%d AND EXISTS (SELECT 1 FROM " . self::table( 'claim_evidence' ) . " e WHERE e.claim_id=c.id) ORDER BY c.id ASC LIMIT 300",
			$concept['id'], $concept['current_version']
		), ARRAY_A );
		$out = array();
		foreach ( $rows as $row ) {
			$claim_id = (int) $row['internal_claim_id']; unset( $row['internal_claim_id'] );
			$evidence = $wpdb->get_results( $wpdb->prepare( 'SELECT relation,reference_id,external_id,weight,note FROM ' . self::table( 'claim_evidence' ) . ' WHERE claim_id=%d ORDER BY id ASC LIMIT 100', $claim_id ), ARRAY_A );
			$safe_evidence = array();
			foreach ( $evidence as $link ) {
				$item = array( 'relation' => $link['relation'], 'weight' => (float) $link['weight'] );
				if ( ! empty( $link['reference_id'] ) ) {
					$ref = $wpdb->get_row( $wpdb->prepare( 'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,link_status FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d AND version_id=%d', absint( $link['reference_id'] ), $concept['id'], $concept['current_version'] ), ARRAY_A );
					if ( $ref ) { $item['reference'] = $ref; }
				} elseif ( ! empty( $link['external_id'] ) ) {
					$parts = HE_V24_Future_API::external_evidence_token_parts( $link['external_id'] );
					if ( $parts ) {
						$reviewed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'external_records' ) . " WHERE provider=%s AND external_id=%s AND concept_id=%d AND ((object_type='claim' AND object_id=%d) OR object_type='concept') AND status='reviewed' AND review_required=0 ORDER BY id DESC LIMIT 1", $parts['provider'], $parts['external_id'], $concept['id'], $claim_id ) );
						if ( $reviewed ) { $item['external'] = array( 'provider' => $parts['provider'], 'external_id' => $parts['external_id'] ); }
					}
				}
				if ( ! empty( $item['reference'] ) || ! empty( $item['external'] ) ) { $safe_evidence[] = $item; }
			}
			if ( ! $safe_evidence ) { continue; }
			$row['version_number'] = (int) $row['version_number']; $row['confidence'] = (float) $row['confidence']; $row['evidence'] = $safe_evidence; $out[] = $row;
		}
		return $out;
	}

	public static function append_provenance( $type, $id, $action, $source_uri = '', $metadata = array(), $actor_id = 0 ) {
		global $wpdb;
		$table = self::table( 'provenance' );
		$lock_name = substr( $wpdb->prefix . 'he_v24_provenance_chain', 0, 64 );
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock_name ) );
		if ( 1 !== $locked ) {
			HE_V2_Schema::record_runtime_failure( 'provenance_chain_busy', 'File 06 could not acquire the provenance-chain serialization lock.' );
			return false;
		}
		try {
			$parent = (string) $wpdb->get_var( "SELECT record_hash FROM {$table} WHERE record_hash<>'' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$created = current_time( 'mysql', true );
			$source_hash = ! empty( $metadata['source_hash'] ) ? preg_replace( '/[^a-f0-9]/i', '', (string) $metadata['source_hash'] ) : '';
			$transform = ! empty( $metadata['transform'] ) ? sanitize_key( $metadata['transform'] ) : '';
			$metadata_json = wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$hash_payload = wp_json_encode( array( 'parent_hash'=>$parent,'object_type'=>sanitize_key($type),'object_id'=>sanitize_text_field($id),'action'=>sanitize_key($action),'source_uri'=>esc_url_raw($source_uri),'source_hash'=>$source_hash,'transform'=>$transform,'metadata_json'=>$metadata_json,'created_at'=>$created ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$record_hash = hash( 'sha256', $hash_payload );
			$ok = $wpdb->insert( $table, array( 'object_type'=>sanitize_key($type),'object_id'=>sanitize_text_field($id),'action'=>sanitize_key($action),'actor_id'=>absint($actor_id ?: get_current_user_id()),'source_uri'=>esc_url_raw($source_uri),'source_hash'=>$source_hash,'transform'=>$transform,'metadata_json'=>$metadata_json,'parent_hash'=>$parent,'record_hash'=>$record_hash,'created_at'=>$created ) );
			return $ok ? $record_hash : false;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function public_provenance( $type, $id, $format = 'json' ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT object_type,object_id,action,source_uri,source_hash,transform,metadata_json,parent_hash,record_hash,created_at FROM ' . self::table( 'provenance' ) . ' WHERE object_type=%s AND object_id=%s ORDER BY id ASC LIMIT 300',
			sanitize_key( $type ), sanitize_text_field( $id )
		), ARRAY_A );
		$out = array();
		foreach ( $rows as $row ) {
			$row['metadata'] = json_decode( $row['metadata_json'], true );
			unset( $row['metadata_json'] );
			$out[] = $row;
		}
		if ( 'jsonld' !== $format ) {
			return $out;
		}
		$graph = array();
		foreach ( $out as $row ) {
			$graph[] = array(
				'@type' => 'prov:Entity',
				'@id' => 'urn:sha256:' . $row['record_hash'],
				'prov:specializationOf' => $row['object_type'] . ':' . $row['object_id'],
				'prov:generatedAtTime' => $row['created_at'] . 'Z',
				'prov:wasDerivedFrom' => $row['parent_hash'] ? 'urn:sha256:' . $row['parent_hash'] : null,
				'he:action' => $row['action'],
				'he:source' => $row['source_uri'],
			);
		}
		return array( '@context' => array( 'prov' => 'http://www.w3.org/ns/prov#', 'he' => 'https://www.sabrihomeopathy.com/ns/file-06#' ), '@graph' => $graph );
	}

	public static function providers() {
		return array(
			'crossref' => 'https://api.crossref.org/works/%s',
			'pubmed' => 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=%s&retmode=json',
			'clinicaltrials' => 'https://clinicaltrials.gov/api/v2/studies/%s',
			'orcid' => 'https://pub.orcid.org/v3.0/%s/record',
			'datacite' => 'https://api.datacite.org/dois/%s',
			'mesh' => 'https://id.nlm.nih.gov/mesh/lookup/details?descriptor=%s',
		);
	}

	public static function validate_external_id( $provider, $external_id ) {
		$provider = sanitize_key( $provider );
		$external_id = trim( sanitize_text_field( (string) $external_id ) );
		if ( ! isset( self::providers()[ $provider ] ) || '' === $external_id ) {
			return false;
		}
		if ( in_array( $provider, array( 'crossref','datacite' ), true ) ) {
			return preg_match( '#^10\.\d{4,9}/[^\s<>]{1,180}$#i', $external_id ) ? $external_id : false;
		}
		if ( 'pubmed' === $provider ) {
			return preg_match( '/^[1-9][0-9]{0,9}$/', $external_id ) ? $external_id : false;
		}
		if ( 'clinicaltrials' === $provider ) {
			$external_id = strtoupper( $external_id );
			return preg_match( '/^NCT[0-9]{8}$/', $external_id ) ? $external_id : false;
		}
		if ( 'orcid' === $provider ) {
			$external_id = strtoupper( $external_id );
			return self::valid_orcid( $external_id ) ? $external_id : false;
		}
		if ( 'mesh' === $provider ) {
			return preg_match( "/^[A-Za-z0-9 .,'()\/-]{1,100}$/u", $external_id ) ? $external_id : false;
		}
		return false;
	}

	private static function valid_orcid( $id ) {
		if ( ! preg_match( '/^([0-9]{4}-){3}[0-9]{3}[0-9X]$/', $id ) ) {
			return false;
		}
		$digits = str_replace( '-', '', $id );
		$total = 0;
		for ( $i = 0; $i < 15; $i++ ) {
			$total = ( $total + (int) $digits[ $i ] ) * 2;
		}
		$result = ( 12 - ( $total % 11 ) ) % 11;
		$check = 10 === $result ? 'X' : (string) $result;
		return $check === $digits[15];
	}

	public static function lookup_external( $provider, $external_id ) {
		$provider = sanitize_key( $provider );
		$external_id = self::validate_external_id( $provider, $external_id );
		$providers = self::providers();
		if ( ! $external_id || empty( $providers[ $provider ] ) ) {
			return new WP_Error( 'he_future_external_id_invalid', __( 'The scholarly identifier is invalid for this provider.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$url = sprintf( $providers[ $provider ], rawurlencode( $external_id ) );
		$response = wp_safe_remote_get( $url, array(
			'timeout' => 12,
			'redirection' => 2,
			'limit_response_size' => self::MAX_PROVIDER_BYTES,
			'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Sabri-File06/' . HE_VERSION . '; ' . home_url( '/' ) ),
		) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'he_future_provider_unavailable', __( 'The scholarly provider is temporarily unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 503, 'provider' => $provider ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'he_future_provider_error', __( 'The scholarly provider returned an unsuccessful response.', 'homeopathy-encyclopedia' ), array( 'status' => 502, 'provider' => $provider, 'provider_status' => $code ) );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( strlen( $body ) >= self::MAX_PROVIDER_BYTES ) {
			return new WP_Error( 'he_future_provider_response_too_large', __( 'The scholarly provider response exceeded the safe processing limit.', 'homeopathy-encyclopedia' ), array( 'status' => 502, 'provider' => $provider ) );
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'he_future_provider_invalid_json', __( 'The scholarly provider response could not be safely interpreted.', 'homeopathy-encyclopedia' ), array( 'status' => 502, 'provider' => $provider ) );
		}
		return $data;
	}

	public static function queue_impact( $type, $id, $event, $payload = array(), $consumers = array() ) {
		global $wpdb;
		$consumers = $consumers ?: array( 'file-05','file-12','file-15','file-16','file-21','file-26' );
		$now = current_time( 'mysql', true );
		$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$queued = 0;
		foreach ( array_unique( array_map( 'sanitize_key', $consumers ) ) as $consumer ) {
			$dedupe = hash( 'sha256', sanitize_key( $type ) . '|' . sanitize_text_field( $id ) . '|' . sanitize_text_field( $event ) . '|' . $consumer . '|' . $payload_json );
			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'impact_queue' ) . ' WHERE dedupe_key=%s', $dedupe ) );
			if ( $existing ) {
				continue;
			}
			$ok = $wpdb->insert( self::table( 'impact_queue' ), array(
				'source_type' => sanitize_key( $type ),
				'source_id' => sanitize_text_field( $id ),
				'event_name' => sanitize_text_field( $event ),
				'consumer_file' => $consumer,
				'dedupe_key' => $dedupe,
				'impact_state' => 'pending',
				'payload_json' => $payload_json,
				'attempts' => 0,
				'last_error' => '',
				'created_at' => $now,
				'updated_at' => $now,
			) );
			$queued += $ok ? 1 : 0;
		}
		if ( $queued ) {
			do_action( 'he_v24_knowledge_impact_queued', $type, $id, $event, $payload );
		}
		return $queued;
	}

	private static function process_impact_queue() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM " . self::table( 'impact_queue' ) . " WHERE impact_state IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) ORDER BY id ASC LIMIT " . self::BATCH, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$delays = array( 5, 15, 60, 240, 720 );
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload_json'], true );
			$ack = apply_filters( 'he_v24_consumer_revalidation_ack', null, $row['consumer_file'], $row['event_name'], $payload, $row );
			do_action( 'he_v24_consumer_revalidation_required', $row['consumer_file'], $row['event_name'], $payload, $row );
			$acknowledged = true === $ack || ( is_array( $ack ) && ! empty( $ack['acknowledged'] ) );
			$attempts = (int) $row['attempts'] + 1;
			if ( $acknowledged ) {
				$written = $wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'acknowledged', 'attempts' => $attempts, 'last_error' => '', 'acknowledged_at' => current_time( 'mysql', true ), 'next_attempt_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
				if ( 1 !== (int) $written ) { HE_V2_Schema::record_runtime_failure( 'impact_queue_ack_write_failed', 'A consumer acknowledgement could not be persisted; queue processing stopped for this run.' ); break; }
				continue;
			}
			if ( $attempts >= count( $delays ) ) {
				$written = $wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'dead-letter', 'attempts' => $attempts, 'last_error' => 'consumer acknowledgement not received', 'next_attempt_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
				if ( 1 !== (int) $written ) { HE_V2_Schema::record_runtime_failure( 'impact_queue_dead_letter_write_failed', 'A dead-letter transition could not be persisted; queue processing stopped for this run.' ); break; }
				continue;
			}
			$next = gmdate( 'Y-m-d H:i:s', time() + $delays[ $attempts - 1 ] * MINUTE_IN_SECONDS );
			$written = $wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'retry', 'attempts' => $attempts, 'last_error' => 'consumer acknowledgement not received', 'next_attempt_at' => $next, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
			if ( 1 !== (int) $written ) { HE_V2_Schema::record_runtime_failure( 'impact_queue_retry_write_failed', 'A retry transition could not be persisted; queue processing stopped for this run.' ); break; }
		}
	}

	public static function refresh_freshness( $concept_id, $persist = true ) {
		global $wpdb;
		$concept = self::concept_row( $concept_id, false );
		if ( ! $concept ) {
			return null;
		}
		$risk = sanitize_key( get_post_meta( (int) $concept['post_id'], '_he_risk_tier', true ) );
		$risk = in_array( $risk, array( 'high','critical' ), true ) ? $risk : 'normal';
		$days = 'critical' === $risk ? 30 : ( 'high' === $risk ? 90 : 365 );
		$review = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM " . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0", $concept['id'] ) );
		$now = time();
		$due = $review ? strtotime( $review . ' UTC' ) + $days * DAY_IN_SECONDS : $now;
		$state = 'current';
		if ( $due <= $now && in_array( $risk, array( 'high','critical' ), true ) ) {
			$state = 'urgent-review';
		} elseif ( $due <= $now ) {
			$state = 'stale';
		} elseif ( $due <= $now + 30 * DAY_IN_SECONDS ) {
			$state = 'review-due';
		}
		$priority = ( 'critical' === $risk ? 70 : ( 'high' === $risk ? 45 : 10 ) ) + ( 'urgent-review' === $state ? 30 : ( 'stale' === $state ? 20 : ( 'review-due' === $state ? 10 : 0 ) ) );
		$row = array(
			'concept_id' => (int) $concept['id'],
			'last_evidence_scan' => gmdate( 'Y-m-d H:i:s' ),
			'last_human_review' => $review ?: null,
			'review_due_at' => gmdate( 'Y-m-d H:i:s', $due ),
			'freshness_state' => $state,
			'risk_tier' => $risk,
			'priority_score' => $priority,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( $persist && false === $wpdb->replace( self::table( 'freshness' ), $row ) ) {
			return new WP_Error( 'he_future_freshness_write_failed', __( 'Freshness state could not be stored.', 'homeopathy-encyclopedia' ) );
		}
		return $row;
	}

	private static function scan_concepts_with_cursor( $option ) {
		global $wpdb;
		$cursor = absint( get_option( $option, 0 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE id>%d AND status IN ('published','corrected') ORDER BY id ASC LIMIT %d", $cursor, self::BATCH ), ARRAY_A );
		if ( ! $rows && $cursor ) {
			$cursor = 0;
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE id>%d AND status IN ('published','corrected') ORDER BY id ASC LIMIT %d", 0, self::BATCH ), ARRAY_A );
		}
		return $rows;
	}

	public static function detect_gaps( $concept_id ) {
		global $wpdb;
		$concept = self::concept_row( $concept_id, false );
		if ( ! $concept ) {
			return 0;
		}
		$current_version = absint( $concept['current_version'] );
		$refs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', $concept['id'], $current_version ) );
		$broken = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . " WHERE concept_id=%d AND version_id=%d AND link_status IN ('broken','error')", $concept['id'], $current_version ) );
		$claims = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' WHERE concept_id=%d AND version_id=%d', $concept['id'], $current_version ) );
		$without_evidence = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' c WHERE c.concept_id=%d AND c.version_id=%d AND c.review_status=\'approved\' AND NOT EXISTS (SELECT 1 FROM ' . self::table( 'claim_evidence' ) . ' e WHERE e.claim_id=c.id)', $concept['id'], $current_version ) );
		$contradictions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::table( 'claim_evidence' ) . " e INNER JOIN " . self::table( 'claims' ) . " c ON c.id=e.claim_id WHERE c.concept_id=%d AND c.version_id=%d AND e.relation='contradicts'", $concept['id'], $current_version ) );
		$validation = HE_V2_Domain::validate_for_review( $concept['id'] );
		$safety_missing = false;
		if ( is_wp_error( $validation ) ) {
			$data = $validation->get_error_data();
			$fields = is_array( $data ) && ! empty( $data['fields'] ) ? (array) $data['fields'] : array();
			$safety_missing = (bool) array_intersect( $fields, array( 'red-flags-required','remedy-safety-limitations-required','emergency-boundary-required' ) );
		}
		$gaps = array();
		$write_ok = true;
		if ( $refs < 2 ) { $gaps['insufficient-references'] = array( $refs ? 'medium' : 'high', $refs ? 55 : 80, 'Fewer than two governed references.' ); }
		if ( $claims < 1 ) { $gaps['claim-structure-missing'] = array( 'medium', 55, 'No structured claims are recorded.' ); }
		if ( $without_evidence > 0 ) { $gaps['claims-without-evidence'] = array( 'high', 85, 'One or more approved claims lack linked evidence.' ); }
		if ( $contradictions > 0 ) { $gaps['contradictory-evidence'] = array( 'high', 90, 'Contradictory evidence requires human synthesis.' ); }
		if ( $broken > 0 ) { $gaps['stale-reference-links'] = array( 'medium', 65, 'One or more governed reference links are broken.' ); }
		if ( $safety_missing ) { $gaps['missing-safety-fields'] = array( 'high', 95, 'Required clinical safety fields are incomplete.' ); }
		$now = current_time( 'mysql', true );
		$active = array_keys( $gaps );
		$existing = $wpdb->get_col( $wpdb->prepare( "SELECT gap_type FROM " . self::table( 'research_gaps' ) . " WHERE concept_id=%d AND state='open'", $concept['id'] ) );
		foreach ( $existing as $gap_type ) {
			if ( ! in_array( $gap_type, $active, true ) ) {
				if ( false === $wpdb->update( self::table( 'research_gaps' ), array( 'state' => 'resolved', 'resolved_at' => $now, 'updated_at' => $now ), array( 'concept_id' => $concept['id'], 'gap_type' => $gap_type ) ) ) { $write_ok = false; }
			}
		}
		foreach ( $gaps as $type => $data ) {
			$metrics = array( 'references' => $refs, 'claims' => $claims, 'claims_without_evidence' => $without_evidence, 'contradictions' => $contradictions, 'broken_reference_links' => $broken, 'missing_safety_fields' => $safety_missing );
			$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'research_gaps' ) . ' WHERE concept_id=%d AND gap_type=%s', $concept['id'], $type ) );
			$row = array( 'severity' => $data[0], 'priority_score' => $data[1], 'rationale' => $data[2], 'metrics_json' => wp_json_encode( $metrics ), 'state' => 'open', 'resolved_at' => null, 'updated_at' => $now );
			if ( $id ) {
				if ( false === $wpdb->update( self::table( 'research_gaps' ), $row, array( 'id' => $id ) ) ) { $write_ok = false; }
			} else {
				$row['concept_id'] = $concept['id']; $row['gap_type'] = $type; $row['detected_at'] = $now;
				if ( false === $wpdb->insert( self::table( 'research_gaps' ), $row ) ) { $write_ok = false; }
			}
		}
		if ( ! $write_ok ) { return new WP_Error( 'he_future_gap_write_failed', __( 'Research-gap state could not be stored.', 'homeopathy-encyclopedia' ) ); }
		return count( $gaps );
	}

	private static function crossref_signal( $data ) {
		$message = isset( $data['message'] ) && is_array( $data['message'] ) ? $data['message'] : array();
		$signals = array();
		foreach ( array( 'update-to','update-policy' ) as $key ) {
			$items = isset( $message[ $key ] ) ? (array) $message[ $key ] : array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) { continue; }
				$type = strtolower( sanitize_text_field( $item['type'] ?? '' ) );
				if ( in_array( $type, array( 'retraction','correction','expression-of-concern' ), true ) ) {
					$signals[] = $type;
				}
			}
		}
		if ( ! empty( $message['relation'] ) && is_array( $message['relation'] ) ) {
			foreach ( array_keys( $message['relation'] ) as $relation ) {
				$relation = strtolower( (string) $relation );
				if ( false !== strpos( $relation, 'retract' ) ) { $signals[] = 'retraction'; }
				if ( false !== strpos( $relation, 'correct' ) ) { $signals[] = 'correction'; }
				if ( false !== strpos( $relation, 'concern' ) ) { $signals[] = 'expression-of-concern'; }
			}
		}
		return array_values( array_unique( $signals ) );
	}

	public static function scan_retractions( $limit = self::BATCH ) {
		global $wpdb;
		$cursor=absint(get_option('he_v24_retraction_cursor',0));
		$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('external_records')." WHERE provider='crossref' AND id>%d ORDER BY id ASC LIMIT %d",$cursor,absint($limit)),ARRAY_A);
		if(!$rows&&$cursor){$cursor=0;$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('external_records')." WHERE provider='crossref' AND id>%d ORDER BY id ASC LIMIT %d",0,absint($limit)),ARRAY_A);}
		$flagged=0;$processed=0;$last_completed_id=$cursor;
		foreach($rows as $row){
			$data=self::lookup_external('crossref',$row['external_id']); if(is_wp_error($data)){break;}
			$signals=self::crossref_signal($data);$urgent=!empty($signals);
			$written=$wpdb->update(self::table('external_records'),array('metadata_json'=>wp_json_encode($data),'checked_at'=>current_time('mysql',true),'review_required'=>$urgent?1:(int)$row['review_required'],'status'=>$urgent?'urgent-review':$row['status']),array('id'=>(int)$row['id']));
			if(false===$written){HE_V2_Schema::record_runtime_failure('retraction_watch_state_write_failed','A retraction-watch provider result could not be persisted; the cursor was not advanced past the failed row.');break;}
			if($urgent){
				$hash=self::append_provenance('external-record',(string)$row['id'],'integrity.signal.detected','',array('signals'=>$signals,'source_hash'=>hash('sha256',wp_json_encode($data))));
				if(!$hash){HE_V2_Schema::record_runtime_failure('retraction_watch_provenance_failed','A retraction signal could not be written to the provenance chain; the cursor was not advanced.');break;}
				self::queue_impact('external-record',(string)$row['id'],'KnowledgeEvidenceChanged.v1',array('provider'=>'crossref','external_id'=>$row['external_id'],'signals'=>$signals));$flagged++;
			}
			$processed++;$last_completed_id=(int)$row['id'];
		}
		if($last_completed_id!==$cursor){update_option('he_v24_retraction_cursor',$last_completed_id,false);}
		return array('checked'=>$processed,'flagged'=>$flagged);
	}

	private static function mark_outdated_translations() {
		global $wpdb;
		$result=$wpdb->query('UPDATE '.self::table('translations').' t INNER JOIN '.HE_V2_Schema::table('concepts')." c ON c.id=t.concept_id SET t.status='translation-outdated',t.updated_at=UTC_TIMESTAMP() WHERE t.status IN ('approved','published') AND t.source_version<>c.current_version AND c.current_version>0"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if(false===$result){HE_V2_Schema::record_runtime_failure('translation_outdated_maintenance_failed','Future maintenance could not persist translation-outdated state.');return false;}
		return true;
	}

	private static function acquire_maintenance_lease() {
		global $wpdb;
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'time' => time() );
		if ( add_option( self::OPTION_MAINTENANCE_LEASE, $value, '', false ) ) {
			self::$maintenance_lease_token = $token;
			return true;
		}
		$existing = get_option( self::OPTION_MAINTENANCE_LEASE );
		if ( ! is_array( $existing ) || empty( $existing['time'] ) || time() - (int) $existing['time'] <= self::MAINTENANCE_LEASE_TTL ) { return false; }
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
			self::OPTION_MAINTENANCE_LEASE, maybe_serialize( $existing )
		) );
		if ( 1 !== (int) $deleted || ! add_option( self::OPTION_MAINTENANCE_LEASE, $value, '', false ) ) { return false; }
		self::$maintenance_lease_token = $token;
		return true;
	}

	private static function release_maintenance_lease() {
		global $wpdb;
		if ( ! self::$maintenance_lease_token ) { return; }
		$current = get_option( self::OPTION_MAINTENANCE_LEASE );
		if ( is_array( $current ) && ! empty( $current['token'] ) && hash_equals( (string) $current['token'], self::$maintenance_lease_token ) ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
				self::OPTION_MAINTENANCE_LEASE, maybe_serialize( $current )
			) );
		}
		self::$maintenance_lease_token = '';
	}

	public static function maintenance() {
		if ( ! self::acquire_maintenance_lease() ) { return; }
		try {
			$fresh = self::scan_concepts_with_cursor( 'he_v24_freshness_cursor' );
			foreach ( $fresh as $row ) {
				$result = self::refresh_freshness( (int) $row['id'] );
				if ( is_wp_error( $result ) || ! is_array( $result ) ) { break; }
				update_option( 'he_v24_freshness_cursor', (int) $row['id'], false );
			}
			$gaps = self::scan_concepts_with_cursor( 'he_v24_gap_cursor' );
			foreach ( $gaps as $row ) {
				$result = self::detect_gaps( (int) $row['id'] );
				if ( is_wp_error( $result ) ) { break; }
				update_option( 'he_v24_gap_cursor', (int) $row['id'], false );
			}
			self::scan_retractions( self::BATCH );
			self::process_impact_queue();
			if ( ! self::mark_outdated_translations() ) { return; }
		} finally {
			self::release_maintenance_lease();
		}
	}

	public static function health() {
		global $wpdb;
		$complete=self::schema_complete();
		$base=array('version'=>HE_VERSION,'schema'=>HE_SCHEMA_VERSION,'future_hardening_schema'=>(int)get_option(self::OPTION_VERSION,0),'future_schema_complete'=>$complete,'maintenance_scheduled'=>(bool)wp_next_scheduled(self::CRON),'identity_authority'=>'file-00','assurance_owner'=>'file-24');
		if(!$complete){$base['status']='degraded';$base['pending_impacts']=null;$base['dead_letter_impacts']=null;$base['urgent_external_reviews']=null;$base['open_research_gaps']=null;return $base;}
		$base['status']=HE_V24_Migration_Safety::ready()?'active':'degraded';
		$base['pending_impacts']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('impact_queue')." WHERE impact_state IN ('pending','retry')");
		$base['dead_letter_impacts']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('impact_queue')." WHERE impact_state='dead-letter'");
		$base['urgent_external_reviews']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('external_records')." WHERE status='urgent-review'");
		$base['open_research_gaps']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('research_gaps')." WHERE state='open'");
		return $base;
	}

}

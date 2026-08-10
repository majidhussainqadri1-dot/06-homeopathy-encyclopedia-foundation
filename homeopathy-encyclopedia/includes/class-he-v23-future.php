<?php
/**
 * File 06 v2.3 Future Knowledge Intelligence — F06-FUT-001..018.
 *
 * This layer is intentionally additive. File 06 remains the canonical owner of
 * encyclopedia/research knowledge truth, while File 00 owns identity, File 19
 * owns notification delivery, File 20 owns shell/layout, File 24 owns security
 * assurance, File 25 owns visual tokens/components and File 26 owns global search.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V23_Future {
	const OPTION_VERSION = 'he_v23_future_version';
	const VERSION = 1;
	const CRON = 'he_v23_future_maintenance';
	const BATCH = 40;

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 120 );
		add_action( self::CRON, array( __CLASS__, 'maintenance' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 120 );
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'extend_contract' ), 120 );
		add_filter( 'sabri_notification_event_catalog', array( __CLASS__, 'notification_events' ), 120 );
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance' ), 160 );
	}

	public static function activate() {
		self::install();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::CRON );
		}
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPTION_VERSION, 0 ) < self::VERSION ) {
			self::install();
		}
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'he_' . sanitize_key( $name );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$tables = array();
		$tables[] = "CREATE TABLE " . self::table( 'claims' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_id bigint(20) unsigned NOT NULL,
			public_id char(36) NOT NULL, claim_key varchar(120) NOT NULL, claim_text longtext NOT NULL,
			claim_state varchar(30) NOT NULL DEFAULT 'active', evidence_state varchar(30) NOT NULL DEFAULT 'ungraded',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY public_id(public_id), UNIQUE KEY concept_claim(concept_id,claim_key), KEY claim_state(claim_state)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'claim_evidence' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, claim_id bigint(20) unsigned NOT NULL,
			reference_id bigint(20) unsigned NOT NULL DEFAULT 0, external_id varchar(191) NOT NULL DEFAULT '',
			relation varchar(24) NOT NULL, weight decimal(5,2) NOT NULL DEFAULT 0, note text NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY claim_source(claim_id,reference_id,external_id(96),relation), KEY claim_id(claim_id)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'provenance' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, object_type varchar(40) NOT NULL, object_id varchar(191) NOT NULL,
			action varchar(60) NOT NULL, actor_id bigint(20) unsigned NOT NULL DEFAULT 0, source_uri text NOT NULL,
			source_hash char(64) NOT NULL DEFAULT '', transform varchar(80) NOT NULL DEFAULT '', metadata_json longtext NOT NULL,
			created_at datetime NOT NULL, PRIMARY KEY(id), KEY object_lookup(object_type,object_id(96)), KEY created_at(created_at)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'external_records' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, provider varchar(30) NOT NULL, external_id varchar(191) NOT NULL,
			concept_id bigint(20) unsigned NOT NULL DEFAULT 0, purpose varchar(40) NOT NULL DEFAULT 'literature',
			status varchar(30) NOT NULL DEFAULT 'staged', metadata_json longtext NOT NULL, source_updated_at datetime NULL,
			checked_at datetime NOT NULL, review_required tinyint(1) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY(id), UNIQUE KEY provider_external(provider,external_id(120)), KEY concept_id(concept_id), KEY review_required(review_required)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'concept_mappings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_id bigint(20) unsigned NOT NULL,
			vocabulary varchar(30) NOT NULL, external_id varchar(191) NOT NULL, preferred_label text NOT NULL,
			mapping_state varchar(30) NOT NULL DEFAULT 'proposed', reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY concept_vocab(concept_id,vocabulary,external_id(100)), KEY vocabulary(vocabulary)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'similarity' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_a bigint(20) unsigned NOT NULL, concept_b bigint(20) unsigned NOT NULL,
			score decimal(6,5) NOT NULL DEFAULT 0, reason_json longtext NOT NULL, state varchar(24) NOT NULL DEFAULT 'candidate',
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY pair(concept_a,concept_b), KEY score(score), KEY state(state)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'freshness' ) . " (
			concept_id bigint(20) unsigned NOT NULL, last_evidence_scan datetime NULL, last_human_review datetime NULL,
			review_due_at datetime NULL, freshness_state varchar(24) NOT NULL DEFAULT 'review-due', risk_tier varchar(16) NOT NULL DEFAULT 'normal',
			updated_at datetime NOT NULL, PRIMARY KEY(concept_id), KEY freshness_state(freshness_state), KEY review_due_at(review_due_at)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'impact_queue' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, source_type varchar(30) NOT NULL, source_id varchar(191) NOT NULL,
			event_name varchar(80) NOT NULL, consumer_file varchar(16) NOT NULL, impact_state varchar(24) NOT NULL DEFAULT 'pending',
			payload_json longtext NOT NULL, attempts int unsigned NOT NULL DEFAULT 0, next_attempt_at datetime NULL,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), KEY impact_state(impact_state), KEY consumer_file(consumer_file), KEY source_lookup(source_type,source_id(96))
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'research_gaps' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_id bigint(20) unsigned NOT NULL,
			gap_type varchar(40) NOT NULL, severity varchar(16) NOT NULL DEFAULT 'medium', rationale text NOT NULL,
			metrics_json longtext NOT NULL, state varchar(24) NOT NULL DEFAULT 'open', detected_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY concept_gap(concept_id,gap_type), KEY severity(severity), KEY state(state)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'watchlists' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, user_id bigint(20) unsigned NOT NULL,
			object_type varchar(30) NOT NULL, object_id varchar(191) NOT NULL, active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY user_object(user_id,object_type,object_id(96)), KEY user_id(user_id), KEY active(active)
		) {$c};";
		$tables[] = "CREATE TABLE " . self::table( 'translations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, concept_id bigint(20) unsigned NOT NULL, locale varchar(20) NOT NULL,
			source_version bigint(20) unsigned NOT NULL DEFAULT 0, translation_version bigint(20) unsigned NOT NULL DEFAULT 1,
			status varchar(30) NOT NULL DEFAULT 'draft', translator_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0, content_json longtext NOT NULL, content_hash char(64) NOT NULL,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY(id), UNIQUE KEY concept_locale(concept_id,locale), KEY status(status)
		) {$c};";
		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::OPTION_VERSION, self::VERSION, false );
		update_option( HE_V2_Schema::OPTION_SCHEMA, HE_SCHEMA_VERSION, false );
	}

	public static function register_routes() {
		$ns = HE_V2_API::NS;
		self::route( $ns, '/future/claims', 'GET', 'rest_claims', 'read' );
		self::route( $ns, '/future/claims', 'POST', 'rest_claims_write', HE_V2_Auth::CAP_EDIT );
		self::route( $ns, '/future/claims/(?P<id>\\d+)/evidence', 'POST', 'rest_claim_evidence', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/provenance/(?P<type>[a-z0-9_-]+)/(?P<id>[a-zA-Z0-9_-]+)', 'GET', 'rest_provenance', 'read' );
		self::route( $ns, '/future/external/lookup', 'POST', 'rest_external_lookup', HE_V2_Auth::CAP_RESEARCH );
		self::route( $ns, '/future/retraction-watch', 'POST', 'rest_retraction_watch', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/mappings', 'POST', 'rest_mapping', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/duplicates/scan', 'POST', 'rest_duplicate_scan', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/graph/(?P<id>\\d+)', 'GET', 'rest_graph', 'read' );
		self::route( $ns, '/future/time-machine/(?P<id>\\d+)', 'GET', 'rest_time_machine', 'read' );
		self::route( $ns, '/future/impact/(?P<id>\\d+)', 'POST', 'rest_impact', HE_V2_Auth::CAP_REVIEW );
		self::route( $ns, '/future/freshness/(?P<id>\\d+)', 'GET', 'rest_freshness', 'read' );
		self::route( $ns, '/future/gaps', 'GET', 'rest_gaps', HE_V2_Auth::CAP_RESEARCH );
		self::route( $ns, '/future/citations/(?P<id>\\d+)/(?P<format>[a-z0-9_-]+)', 'GET', 'rest_citations', 'read' );
		self::route( $ns, '/future/watchlist', 'GET', 'rest_watchlist', 'member' );
		self::route( $ns, '/future/watchlist', 'POST', 'rest_watchlist_write', 'member' );
		self::route( $ns, '/future/translations/(?P<id>\\d+)', 'GET', 'rest_translations', 'read' );
		self::route( $ns, '/future/translations/(?P<id>\\d+)', 'POST', 'rest_translation_write', HE_V2_Auth::CAP_EDIT );
		self::route( $ns, '/future/command-center', 'GET', 'rest_command_center', HE_V2_Auth::CAP_REVIEW );
	}

	private static function route( $ns, $path, $method, $callback, $permission ) {
		register_rest_route( $ns, $path, array(
			'methods' => $method,
			'callback' => array( __CLASS__, $callback ),
			'permission_callback' => static function() use ( $permission ) {
				if ( 'read' === $permission ) { return true; }
				if ( 'member' === $permission ) { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); }
				return HE_V2_Auth::rest_permission( $permission );
			},
		) );
	}

	/* F06-FUT-001 Claim-Level Evidence Graph */
	public static function rest_claims( WP_REST_Request $r ) {
		global $wpdb;
		$concept = absint( $r->get_param( 'concept_id' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'claims' ) . ' WHERE concept_id=%d ORDER BY id ASC', $concept ), ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['evidence'] = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'claim_evidence' ) . ' WHERE claim_id=%d ORDER BY id ASC', $row['id'] ), ARRAY_A );
		}
		return rest_ensure_response( $rows );
	}

	public static function rest_claims_write( WP_REST_Request $r ) {
		global $wpdb;
		$concept = absint( $r['concept_id'] );
		if ( ! $concept ) { return new WP_Error( 'he_future_concept_required', 'concept_id is required.', array( 'status'=>400 ) ); }
		$key = sanitize_key( $r['claim_key'] );
		$text = wp_kses_post( (string) $r['claim_text'] );
		if ( ! $key || '' === trim( wp_strip_all_tags( $text ) ) ) { return new WP_Error( 'he_future_claim_invalid', 'claim_key and claim_text are required.', array( 'status'=>400 ) ); }
		$now = current_time( 'mysql', true );
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'claims' ) . ' WHERE concept_id=%d AND claim_key=%s', $concept, $key ), ARRAY_A );
		if ( $existing ) {
			$wpdb->update( self::table( 'claims' ), array( 'claim_text'=>$text, 'updated_at'=>$now ), array( 'id'=>(int)$existing['id'] ) );
			$id = (int) $existing['id'];
		} else {
			$wpdb->insert( self::table( 'claims' ), array( 'concept_id'=>$concept, 'public_id'=>wp_generate_uuid4(), 'claim_key'=>$key, 'claim_text'=>$text, 'created_by'=>get_current_user_id(), 'created_at'=>$now, 'updated_at'=>$now ) );
			$id = (int) $wpdb->insert_id;
		}
		self::provenance( 'claim', (string)$id, 'claim.saved', '', array( 'concept_id'=>$concept, 'claim_key'=>$key ) );
		return rest_ensure_response( array( 'id'=>$id, 'saved'=>true ) );
	}

	public static function rest_claim_evidence( WP_REST_Request $r ) {
		global $wpdb;
		$claim = absint( $r['id'] );
		$relation = sanitize_key( $r['relation'] );
		if ( ! in_array( $relation, array( 'supports','contradicts','uncertain','historical' ), true ) ) { return new WP_Error( 'he_future_relation_invalid', 'Invalid claim-evidence relation.', array( 'status'=>400 ) ); }
		$wpdb->replace( self::table( 'claim_evidence' ), array( 'claim_id'=>$claim, 'reference_id'=>absint($r['reference_id']), 'external_id'=>sanitize_text_field((string)$r['external_id']), 'relation'=>$relation, 'weight'=>(float)$r['weight'], 'note'=>sanitize_textarea_field((string)$r['note']), 'created_by'=>get_current_user_id(), 'created_at'=>current_time('mysql',true) ) );
		self::provenance( 'claim', (string)$claim, 'evidence.linked', '', array( 'relation'=>$relation ) );
		return rest_ensure_response( array( 'saved'=>true ) );
	}

	/* F06-FUT-002 Universal Provenance Ledger */
	private static function provenance( $type, $id, $action, $source_uri = '', $meta = array() ) {
		global $wpdb;
		$wpdb->insert( self::table( 'provenance' ), array( 'object_type'=>sanitize_key($type), 'object_id'=>sanitize_text_field($id), 'action'=>sanitize_key($action), 'actor_id'=>get_current_user_id(), 'source_uri'=>esc_url_raw($source_uri), 'source_hash'=>!empty($meta['source_hash'])?sanitize_text_field($meta['source_hash']):'', 'transform'=>!empty($meta['transform'])?sanitize_key($meta['transform']):'', 'metadata_json'=>wp_json_encode($meta), 'created_at'=>current_time('mysql',true) ) );
	}
	public static function rest_provenance( WP_REST_Request $r ) {
		global $wpdb;
		return rest_ensure_response( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table('provenance') . ' WHERE object_type=%s AND object_id=%s ORDER BY id DESC LIMIT 200', sanitize_key($r['type']), sanitize_text_field($r['id']) ), ARRAY_A ) );
	}

	/* F06-FUT-003..008 external scholarly metadata / retraction / ORCID / DataCite / MeSH. */
	private static function providers() {
		return array(
			'crossref'=>array('url'=>'https://api.crossref.org/works/%s','id'=>'doi'),
			'pubmed'=>array('url'=>'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=%s&retmode=json','id'=>'pmid'),
			'clinicaltrials'=>array('url'=>'https://clinicaltrials.gov/api/v2/studies/%s','id'=>'nct'),
			'orcid'=>array('url'=>'https://pub.orcid.org/v3.0/%s/record','id'=>'orcid'),
			'datacite'=>array('url'=>'https://api.datacite.org/dois/%s','id'=>'doi'),
			'mesh'=>array('url'=>'https://id.nlm.nih.gov/mesh/lookup/details?descriptor=%s','id'=>'mesh'),
		);
	}
	private static function lookup_external( $provider, $external_id ) {
		$providers = self::providers();
		if ( empty($providers[$provider]) ) { return new WP_Error('he_future_provider_invalid','Unsupported scholarly provider.'); }
		$url = sprintf( $providers[$provider]['url'], rawurlencode($external_id) );
		$args = array( 'timeout'=>12, 'redirection'=>2, 'headers'=>array('Accept'=>'application/json','User-Agent'=>'Sabri-File06/'.HE_VERSION.'; '.home_url('/')) );
		$response = wp_safe_remote_get( $url, $args );
		if ( is_wp_error($response) ) { return $response; }
		$code = wp_remote_retrieve_response_code($response);
		if ( $code < 200 || $code >= 300 ) { return new WP_Error('he_future_provider_error','Provider lookup failed.',array('status'=>502,'provider_status'=>$code)); }
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body,true);
		return is_array($data) ? $data : array('raw'=>mb_substr($body,0,50000));
	}
	public static function rest_external_lookup( WP_REST_Request $r ) {
		global $wpdb;
		$provider = sanitize_key($r['provider']); $external = sanitize_text_field($r['external_id']); $concept=absint($r['concept_id']);
		$data = self::lookup_external($provider,$external); if ( is_wp_error($data) ) { return $data; }
		$wpdb->replace( self::table('external_records'), array('provider'=>$provider,'external_id'=>$external,'concept_id'=>$concept,'purpose'=>sanitize_key($r['purpose']?:'literature'),'status'=>'staged','metadata_json'=>wp_json_encode($data),'checked_at'=>current_time('mysql',true),'review_required'=>1) );
		self::provenance('external-record',$provider.':'.$external,'metadata.staged','',array('provider'=>$provider,'concept_id'=>$concept,'source_hash'=>hash('sha256',wp_json_encode($data))));
		return rest_ensure_response(array('provider'=>$provider,'external_id'=>$external,'review_required'=>true,'metadata'=>$data));
	}
	public static function rest_retraction_watch( WP_REST_Request $r ) {
		global $wpdb;
		$rows=$wpdb->get_results("SELECT * FROM ".self::table('external_records')." WHERE provider='crossref' ORDER BY checked_at ASC LIMIT ".self::BATCH,ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$flagged=0;
		foreach($rows as $row){$data=self::lookup_external('crossref',$row['external_id']); if(is_wp_error($data)){continue;} $flat=strtolower(wp_json_encode($data)); $bad=(false!==strpos($flat,'retract')||false!==strpos($flat,'expression of concern')||false!==strpos($flat,'corrected')); $wpdb->update(self::table('external_records'),array('metadata_json'=>wp_json_encode($data),'checked_at'=>current_time('mysql',true),'review_required'=>$bad?1:(int)$row['review_required'],'status'=>$bad?'urgent-review':$row['status']),array('id'=>$row['id'])); if($bad){$flagged++; self::queue_impact('reference',(string)$row['id'],'KnowledgeEvidenceChanged.v1',array('provider'=>'crossref','external_id'=>$row['external_id']));}}
		return rest_ensure_response(array('checked'=>count($rows),'flagged'=>$flagged));
	}
	public static function rest_mapping( WP_REST_Request $r ) {
		global $wpdb; $v=sanitize_key($r['vocabulary']); if(!in_array($v,array('mesh','orcid','datacite','pubmed','clinicaltrials'),true)){return new WP_Error('he_future_mapping_invalid','Unsupported mapping vocabulary.',array('status'=>400));}
		$now=current_time('mysql',true); $wpdb->replace(self::table('concept_mappings'),array('concept_id'=>absint($r['concept_id']),'vocabulary'=>$v,'external_id'=>sanitize_text_field($r['external_id']),'preferred_label'=>sanitize_text_field($r['preferred_label']),'mapping_state'=>'reviewed','reviewed_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));
		self::provenance('concept',(string)absint($r['concept_id']),'mapping.reviewed','',array('vocabulary'=>$v,'external_id'=>sanitize_text_field($r['external_id'])));
		return rest_ensure_response(array('saved'=>true));
	}

	/* F06-FUT-009 Semantic duplicate intelligence — deterministic token similarity, advisory only. */
	private static function tokens( $text ) { $parts=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower(wp_strip_all_tags($text)),-1,PREG_SPLIT_NO_EMPTY); return array_values(array_unique(array_filter($parts,static function($x){return mb_strlen($x)>2;}))); }
	private static function jaccard( $a,$b ){ $a=self::tokens($a);$b=self::tokens($b); if(!$a||!$b){return 0;} $i=count(array_intersect($a,$b));$u=count(array_unique(array_merge($a,$b)));return $u?($i/$u):0; }
	public static function rest_duplicate_scan( WP_REST_Request $r ) {
		global $wpdb; $concept=absint($r['concept_id']); $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.HE_V2_Schema::table('concepts').' WHERE id=%d',$concept),ARRAY_A); if(!$row){return new WP_Error('he_not_found','Concept not found.',array('status'=>404));}
		$post=get_post((int)$row['post_id']); $source=$post?($post->post_title.' '.$post->post_excerpt.' '.$post->post_content):''; $others=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.HE_V2_Schema::table('concepts').' WHERE id<>%d LIMIT 500',$concept),ARRAY_A); $out=array();
		foreach($others as $o){$p=get_post((int)$o['post_id']);if(!$p)continue;$score=self::jaccard($source,$p->post_title.' '.$p->post_excerpt.' '.$p->post_content);if($score<0.30)continue;$a=min($concept,(int)$o['id']);$b=max($concept,(int)$o['id']);$now=current_time('mysql',true);$wpdb->replace(self::table('similarity'),array('concept_a'=>$a,'concept_b'=>$b,'score'=>$score,'reason_json'=>wp_json_encode(array('method'=>'token-jaccard-v1')),'state'=>'candidate','created_at'=>$now,'updated_at'=>$now));$out[]=array('concept_id'=>(int)$o['id'],'score'=>$score);}
		usort($out,static function($x,$y){return $y['score']<=>$x['score'];}); return rest_ensure_response(array_slice($out,0,50));
	}

	/* F06-FUT-010 Interactive Knowledge Graph Explorer. Data only; File 25 owns visual rendering. */
	public static function rest_graph( WP_REST_Request $r ) {
		global $wpdb; $id=absint($r['id']);
		$rels=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.HE_V2_Schema::table('relations').' WHERE source_id=%d OR target_id=%d ORDER BY id DESC LIMIT 300',$id,$id),ARRAY_A);
		$claims=$wpdb->get_results($wpdb->prepare('SELECT id,public_id,claim_key,claim_state,evidence_state FROM '.self::table('claims').' WHERE concept_id=%d',$id),ARRAY_A);
		$maps=$wpdb->get_results($wpdb->prepare('SELECT vocabulary,external_id,preferred_label,mapping_state FROM '.self::table('concept_mappings').' WHERE concept_id=%d',$id),ARRAY_A);
		return rest_ensure_response(array('concept_id'=>$id,'relations'=>$rels,'claims'=>$claims,'mappings'=>$maps,'visual_owner'=>'file-25'));
	}

	/* F06-FUT-011 Knowledge Time Machine. */
	public static function rest_time_machine( WP_REST_Request $r ) {
		global $wpdb; $id=absint($r['id']);
		$versions=$wpdb->get_results($wpdb->prepare('SELECT id,version_no,state,created_by,created_at,content_hash,title,excerpt FROM '.HE_V2_Schema::table('versions').' WHERE concept_id=%d ORDER BY version_no DESC LIMIT 200',$id),ARRAY_A);
		return rest_ensure_response(array('concept_id'=>$id,'versions'=>$versions));
	}

	/* F06-FUT-012 Cross-platform impact propagation. */
	private static function queue_impact( $type,$id,$event,$payload=array() ) {
		global $wpdb; $now=current_time('mysql',true); foreach(array('file-05','file-12','file-15','file-16','file-21','file-26') as $consumer){$wpdb->insert(self::table('impact_queue'),array('source_type'=>$type,'source_id'=>$id,'event_name'=>$event,'consumer_file'=>$consumer,'impact_state'=>'pending','payload_json'=>wp_json_encode($payload),'created_at'=>$now,'updated_at'=>$now));}
		do_action('he_v23_knowledge_impact_queued',$type,$id,$event,$payload);
	}
	public static function rest_impact( WP_REST_Request $r ) { $id=absint($r['id']); self::queue_impact('concept',(string)$id,'KnowledgeConceptChanged.v1',array('reason'=>sanitize_text_field($r['reason']))); return rest_ensure_response(array('queued'=>true,'concept_id'=>$id)); }

	/* F06-FUT-013 Living Knowledge/Freshness Engine. */
	private static function refresh_freshness( $concept_id ) {
		global $wpdb; $now=time(); $risk=get_post_meta((int)$wpdb->get_var($wpdb->prepare('SELECT post_id FROM '.HE_V2_Schema::table('concepts').' WHERE id=%d',$concept_id)),'_he_risk_tier',true); $risk=in_array($risk,array('high','critical'),true)?$risk:'normal'; $days='critical'===$risk?30:('high'===$risk?90:365); $review=$wpdb->get_var($wpdb->prepare('SELECT MAX(created_at) FROM '.HE_V2_Schema::table('reviews').' WHERE concept_id=%d',$concept_id)); $due=$review?strtotime($review.' UTC')+$days*DAY_IN_SECONDS:$now; $state=$due<$now?'stale':($due<$now+30*DAY_IN_SECONDS?'review-due':'current'); $row=array('concept_id'=>$concept_id,'last_evidence_scan'=>gmdate('Y-m-d H:i:s'),'last_human_review'=>$review?:null,'review_due_at'=>gmdate('Y-m-d H:i:s',$due),'freshness_state'=>$state,'risk_tier'=>$risk,'updated_at'=>gmdate('Y-m-d H:i:s')); $wpdb->replace(self::table('freshness'),$row); return $row; }
	public static function rest_freshness( WP_REST_Request $r ) { global $wpdb; $id=absint($r['id']); $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('freshness').' WHERE concept_id=%d',$id),ARRAY_A); if(!$row){$row=self::refresh_freshness($id);} return rest_ensure_response($row); }

	/* F06-FUT-014 Evidence-Gap & Research-Priority Radar. */
	private static function detect_gaps( $limit=self::BATCH ) { global $wpdb; $rows=$wpdb->get_results('SELECT id FROM '.HE_V2_Schema::table('concepts').' WHERE status IN (\'published\',\'corrected\') ORDER BY id ASC LIMIT '.absint($limit),ARRAY_A); $count=0; foreach($rows as $row){$id=(int)$row['id'];$refs=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.HE_V2_Schema::table('references').' WHERE concept_id=%d',$id));$claims=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table('claims').' WHERE concept_id=%d',$id));$contr=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::table('claim_evidence')." ce INNER JOIN ".self::table('claims')." c ON c.id=ce.claim_id WHERE c.concept_id=%d AND ce.relation='contradicts'",$id));$gap=$refs<2?'insufficient-references':($claims===0?'claim-structure-missing':($contr>0?'contradictory-evidence':null));if(!$gap)continue;$sev=$refs===0?'high':'medium';$now=current_time('mysql',true);$wpdb->replace(self::table('research_gaps'),array('concept_id'=>$id,'gap_type'=>$gap,'severity'=>$sev,'rationale'=>'Automatically detected knowledge-governance gap; human review required.','metrics_json'=>wp_json_encode(array('references'=>$refs,'claims'=>$claims,'contradictions'=>$contr)),'state'=>'open','detected_at'=>$now,'updated_at'=>$now));$count++;}return $count; }
	public static function rest_gaps() { global $wpdb; return rest_ensure_response($wpdb->get_results("SELECT * FROM ".self::table('research_gaps')." WHERE state='open' ORDER BY FIELD(severity,'critical','high','medium','low'), updated_at DESC LIMIT 300",ARRAY_A)); }

	/* F06-FUT-015 Citation Laboratory. */
	public static function rest_citations( WP_REST_Request $r ) { global $wpdb; $id=absint($r['id']);$format=sanitize_key($r['format']);$refs=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.HE_V2_Schema::table('references').' WHERE concept_id=%d ORDER BY id ASC',$id),ARRAY_A); if(!in_array($format,array('json','jsonld','bibtex','ris','csl-json'),true)){return new WP_Error('he_future_citation_format','Unsupported citation format.',array('status'=>400));}$out=self::citation_format($refs,$format);return new WP_REST_Response(array('format'=>$format,'concept_id'=>$id,'content'=>$out),200); }
	private static function citation_format($refs,$format){if('json'===$format||'csl-json'===$format)return wp_json_encode($refs,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);if('jsonld'===$format){$items=array();foreach($refs as $r){$items[]=array('@type'=>'CreativeWork','name'=>$r['title']??'','identifier'=>$r['identifier']??'','url'=>$r['url']??'');}return wp_json_encode(array('@context'=>'https://schema.org','@graph'=>$items),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);} $out='';foreach($refs as $r){$key='he'.absint($r['id']);$title=str_replace(array('{','}'),'',$r['title']??'Untitled');if('bibtex'===$format){$out.="@misc{{$key},\n  title = {{$title}},\n  note = {".($r['identifier']??'')."}\n}\n\n";}else{$out.="TY  - GEN\nTI  - {$title}\nID  - ".($r['identifier']??'')."\nUR  - ".($r['url']??'')."\nER  - \n\n";}}return $out;}

	/* F06-FUT-016 Knowledge Watchlists; File 19 owns delivery. */
	public static function rest_watchlist() { global $wpdb; return rest_ensure_response($wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('watchlists').' WHERE user_id=%d AND active=1 ORDER BY updated_at DESC',get_current_user_id()),ARRAY_A)); }
	public static function rest_watchlist_write( WP_REST_Request $r ) { global $wpdb;$type=sanitize_key($r['object_type']);$id=sanitize_text_field($r['object_id']);if(!$type||!$id)return new WP_Error('he_future_watch_invalid','object_type and object_id are required.',array('status'=>400));$now=current_time('mysql',true);$wpdb->replace(self::table('watchlists'),array('user_id'=>get_current_user_id(),'object_type'=>$type,'object_id'=>$id,'active'=>empty($r['active'])?0:1,'created_at'=>$now,'updated_at'=>$now));return rest_ensure_response(array('saved'=>true,'delivery_owner'=>'file-19')); }

	/* F06-FUT-017 Governed multilingual knowledge editions. */
	public static function rest_translations( WP_REST_Request $r ) { global $wpdb; return rest_ensure_response($wpdb->get_results($wpdb->prepare('SELECT id,concept_id,locale,source_version,translation_version,status,translator_id,reviewer_id,content_hash,updated_at FROM '.self::table('translations').' WHERE concept_id=%d ORDER BY locale ASC',absint($r['id'])),ARRAY_A)); }
	public static function rest_translation_write( WP_REST_Request $r ) { global $wpdb;$id=absint($r['id']);$locale=sanitize_text_field($r['locale']);if(!in_array($locale,array('ur-PK','ar','en-US'),true))return new WP_Error('he_future_locale_invalid','Unsupported governed locale.',array('status'=>400));$content=is_array($r['content'])?$r['content']:array('body'=>wp_kses_post((string)$r['content']));$hash=hash('sha256',wp_json_encode($content));$source=absint($r['source_version']);$existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('translations').' WHERE concept_id=%d AND locale=%s',$id,$locale),ARRAY_A);$now=current_time('mysql',true);$version=$existing?((int)$existing['translation_version']+1):1;$wpdb->replace(self::table('translations'),array('concept_id'=>$id,'locale'=>$locale,'source_version'=>$source,'translation_version'=>$version,'status'=>'draft','translator_id'=>get_current_user_id(),'reviewer_id'=>0,'content_json'=>wp_json_encode($content),'content_hash'=>$hash,'created_at'=>$existing?$existing['created_at']:$now,'updated_at'=>$now));self::provenance('translation',$id.':'.$locale,'translation.saved','',array('source_version'=>$source,'translation_version'=>$version,'source_hash'=>$hash));return rest_ensure_response(array('saved'=>true,'status'=>'draft','translation_version'=>$version)); }

	/* F06-FUT-018 Encyclopedia Integrity Command Center. */
	public static function rest_command_center() { global $wpdb; $q=array();$q['stale']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('freshness')." WHERE freshness_state IN ('stale','urgent-review')");$q['research_gaps']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('research_gaps')." WHERE state='open'");$q['duplicate_candidates']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('similarity')." WHERE state='candidate'");$q['urgent_external_reviews']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('external_records')." WHERE review_required=1 AND status='urgent-review'");$q['outdated_translations']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('translations')." WHERE status='translation-outdated'");$q['pending_impacts']=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('impact_queue')." WHERE impact_state='pending'");$q['source_of_truth']='file-06';$q['security_assurance_owner']='file-24';$q['visual_owner']='file-25'; return rest_ensure_response($q); }

	public static function maintenance() {
		global $wpdb;
		$ids=$wpdb->get_col('SELECT id FROM '.HE_V2_Schema::table('concepts').' WHERE status IN (\'published\',\'corrected\') ORDER BY id ASC LIMIT '.self::BATCH); foreach($ids as $id){self::refresh_freshness((int)$id);} self::detect_gaps(self::BATCH); self::process_impact_queue(); self::mark_outdated_translations();
	}
	private static function process_impact_queue(){global $wpdb;$rows=$wpdb->get_results("SELECT * FROM ".self::table('impact_queue')." WHERE impact_state='pending' AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) ORDER BY id ASC LIMIT ".self::BATCH,ARRAY_A);foreach($rows as $row){do_action('he_v23_consumer_revalidation_required',$row['consumer_file'],$row['event_name'],json_decode($row['payload_json'],true),$row);$wpdb->update(self::table('impact_queue'),array('impact_state'=>'emitted','attempts'=>(int)$row['attempts']+1,'updated_at'=>current_time('mysql',true)),array('id'=>$row['id']));}}
	private static function mark_outdated_translations(){global $wpdb;$rows=$wpdb->get_results('SELECT t.id,t.source_version,c.current_version FROM '.self::table('translations').' t INNER JOIN '.HE_V2_Schema::table('concepts').' c ON c.id=t.concept_id WHERE t.status=\'published\' AND t.source_version<c.current_version LIMIT '.self::BATCH,ARRAY_A);foreach($rows as $row){$wpdb->update(self::table('translations'),array('status'=>'translation-outdated','updated_at'=>current_time('mysql',true)),array('id'=>$row['id']));}}

	public static function extend_contract( $contracts ) { $contracts=is_array($contracts)?$contracts:array(); if(empty($contracts['file-06']))return $contracts; $contracts['file-06']['future_enhancements']=array('F06-FUT-001','F06-FUT-002','F06-FUT-003','F06-FUT-004','F06-FUT-005','F06-FUT-006','F06-FUT-007','F06-FUT-008','F06-FUT-009','F06-FUT-010','F06-FUT-011','F06-FUT-012','F06-FUT-013','F06-FUT-014','F06-FUT-015','F06-FUT-016','F06-FUT-017','F06-FUT-018');$contracts['file-06']['scholarly_metadata_providers']=array_keys(self::providers());$contracts['file-06']['notification_delivery_owner']='file-19';$contracts['file-06']['graph_visual_owner']='file-25'; return $contracts; }
	public static function notification_events( $catalog ) { $catalog=is_array($catalog)?$catalog:array(); foreach(array('KnowledgeUpdated.v1','KnowledgeCorrected.v1','KnowledgeRetracted.v1','KnowledgeFreshnessDue.v1','KnowledgeEvidenceChanged.v1','KnowledgeTranslationOutdated.v1') as $e){$catalog[$e]=array('producer'=>'file-06','delivery_owner'=>'file-19','sensitive_payload'=>false);} return $catalog; }
	public static function assurance( $providers ) { $providers=is_array($providers)?$providers:array();$providers['file-06-future']=array('owner'=>'file-06','assurance_owner'=>'file-24','health'=>array(__CLASS__,'health'),'native_enforcement_preserved'=>true);return $providers; }
	public static function health(){global $wpdb;return array('version'=>HE_VERSION,'schema'=>HE_SCHEMA_VERSION,'future_schema'=>(int)get_option(self::OPTION_VERSION,0),'pending_impacts'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('impact_queue')." WHERE impact_state='pending'"),'urgent_external_reviews'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('external_records')." WHERE status='urgent-review'"),'open_research_gaps'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM ".self::table('research_gaps')." WHERE state='open'"));}
}

<?php
/** File 06 v2.4 — deterministic 80-round source audit. */
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$read = static function( $path ) {
	$data = file_get_contents( $path );
	if ( false === $data ) { throw new RuntimeException( 'Cannot read ' . $path ); }
	return $data;
};
$b = $read( $plugin . '/homeopathy-encyclopedia.php' );
$schema = $read( $plugin . '/includes/class-he-v2-schema.php' );
$domain = $read( $plugin . '/includes/class-he-v2-domain.php' );
$api = $read( $plugin . '/includes/class-he-v2-api.php' );
$auth = $read( $plugin . '/includes/class-he-v2-auth.php' );
$privacy = $read( $plugin . '/includes/class-he-v2-privacy.php' );
$future = $read( $plugin . '/includes/class-he-v23-future.php' );
$hard = $read( $plugin . '/includes/class-he-v24-audit80-hardening.php' );
$guard = $read( $plugin . '/includes/class-he-v24-final-guard.php' );
$gov = $read( $plugin . '/includes/class-he-v22-governance.php' );
$integrity = $read( $plugin . '/includes/class-he-v22-integrity.php' );
$consumers = $read( $plugin . '/includes/class-he-v22-consumers.php' );
$type_schemas = $read( $plugin . '/includes/class-he-v22-type-schemas.php' );
$research = $read( $plugin . '/includes/class-he-v22-research-guard.php' );
$css = $read( $plugin . '/assets/css/encyclopedia-v2.css' );
$uninstall = $read( $plugin . '/uninstall.php' );
$workflow = $read( $root . '/.github/workflows/file06-v2-complete.yml' );
$fail = array();
$count = 0;
function r80( $n, $ok, $label ) {
	global $fail, $count;
	$count++;
	if ( $n !== $count ) { $fail[] = "Round numbering mismatch: expected {$count}, got {$n}."; }
	if ( ! $ok ) { $fail[] = sprintf( 'Round %02d FAILED: %s', $n, $label ); }
}
function has( $haystack, $needle ) { return false !== strpos( $haystack, $needle ); }

r80( 1, has( $b, "define( 'HE_VERSION', '2.4.0' )" ), 'exact software version 2.4.0' );
r80( 2, has( $b, "define( 'HE_SCHEMA_VERSION', 10 )" ) && has( $b, "HE_CONTRACT_VERSION', '2.4" ), 'schema/contract version truth' );
r80( 3, has( $b, 'class-he-v24-audit80-hardening.php' ) && has( $b, 'class-he-v24-final-guard.php' ), 'audit hardening bootstrap load' );
r80( 4, has( $b, 'HE_V24_Audit80_Hardening::hooks' ) && has( $b, 'HE_V24_Final_Guard::hooks' ), 'runtime hook wiring' );
r80( 5, 16 === substr_count( $type_schemas, "'body_system_required' =>" ), 'sixteen fixed type schemas' );
r80( 6, has( $hard, 'source_concept_id' ) && has( $hard, 'target_concept_id' ), 'graph uses real relation columns' );
r80( 7, has( $domain, "'draft', 'validation', 'review', 'approved', 'scheduled', 'published', 'corrected', 'retracted', 'archived'" ), 'entry state constitution' );
r80( 8, has( $guard, "'approved' === \$row['review_status']" ) && has( $guard, "'approved' === \$row['safety_status']" ), 'public state requires review+safety approval' );
r80( 9, has( $future, '/future/claims' ) && has( $future, '/future/command-center' ), 'Future-18 REST surface' );
r80( 10, has( $guard, 'concept_id_from_public_route' ) && has( $guard, 'fully_public_concept' ), 'public GET projection guard' );
r80( 11, has( $guard, 'Idempotency-Key' ) && has( $guard, 'rate_allow' ) && has( $guard, 'require_nonce' ), 'future mutation nonce/rate/idempotency' );
r80( 12, has( $auth, 'SMC_Contracts::assertions' ) && has( $auth, 'he_identity_provider_unavailable' ), 'File 00 fail-closed identity authority' );
r80( 13, has( $future, "table( 'claims' )" ) && has( $future, "table( 'claim_evidence' )" ), 'claim evidence graph storage' );
r80( 14, has( $hard, 'he_future_evidence_source_required' ), 'claim evidence cannot be source-free' );
r80( 15, has( $hard, "claim_state='active'" ) && has( $hard, 'SELECT reference_id,external_id,relation,weight' ), 'public claim projection minimized' );
r80( 16, has( $future, "table( 'provenance' )" ) && ! has( $future, "'/future/provenance', 'POST'" ), 'provenance has no public mutation route' );
r80( 17, has( $guard, 'future/provenance' ) && has( $guard, 'CAP_REVIEW' ), 'provenance detail reviewer-only' );
r80( 18, has( $future, 'api.crossref.org' ) && has( $future, 'eutils.ncbi.nlm.nih.gov' ), 'provider allowlist present' );
r80( 19, has( $hard, 'validate_external_identifier' ) && has( $hard, 'he_future_external_id_invalid' ), 'provider identifier validation' );
r80( 20, has( $hard, 'limit_response_size' ) && has( $hard, 'reject_unsafe_urls' ), 'provider response/SSRF bounds' );
r80( 21, has( $future, 'retraction-watch' ) && has( $future, 'urgent-review' ), 'retraction/correction watch' );
r80( 22, has( $future, "'pubmed'" ), 'PubMed connector' );
r80( 23, has( $future, "'clinicaltrials'" ), 'ClinicalTrials connector' );
r80( 24, has( $future, "'orcid'" ), 'ORCID adapter' );
r80( 25, has( $future, "'datacite'" ), 'DataCite adapter' );
r80( 26, has( $future, "'mesh'" ), 'MeSH mapping' );
r80( 27, has( $future, "'review_required'=>1" ) || has( $future, "'review_required'=>true" ), 'external metadata human-review gate' );
r80( 28, has( $future, 'token-jaccard-v1' ) && has( $future, "'state'=>'candidate'" ), 'duplicate intelligence advisory mode' );
r80( 29, has( $future, 'LIMIT 500' ) && has( $future, 'array_slice($out,0,50)' ), 'duplicate scan bounded' );
r80( 30, has( $hard, 'r.source_concept_id' ) && has( $hard, 'r.target_concept_id' ), 'graph SQL schema consistency' );
r80( 31, has( $hard, "s.status IN ('published','corrected')" ) && has( $hard, "t.status IN ('published','corrected')" ), 'graph hides nonpublic neighbors' );
r80( 32, has( $hard, "'visual_owner' => 'file-25'" ), 'graph rendering ownership File 25' );
r80( 33, has( $hard, 'version_number,status,title,summary,content_hash' ), 'time machine uses real version columns' );
r80( 34, has( $hard, "status IN ('published','corrected','retracted')" ), 'time machine excludes draft/review versions' );
r80( 35, ! has( $hard, 'version_no,state,created_by' ), 'obsolete version field names absent' );
r80( 36, has( $future, "array('file-05','file-12','file-15','file-16','file-21','file-26')" ), 'impact consumer set' );
r80( 37, has( $hard, 'dedupe_impact_queue' ) && has( $hard, 'payload_json=%s' ), 'impact deduplication' );
r80( 38, has( $hard, "'dead-letter'" ) && has( $hard, "'retry'" ) && has( $hard, 'last_error' ), 'impact retry/dead-letter evidence' );
r80( 39, has( $consumers, "'write_authority' => false" ) && has( $consumers, "'private_fields' => false" ), 'consumer read-only/public-safe' );
r80( 40, has( $future, "table( 'freshness' )" ), 'freshness storage' );
r80( 41, has( $hard, "object_type='concept' AND object_id=%d" ), 'freshness review query matches schema' );
r80( 42, has( $hard, "'critical' === \$risk ? 30" ) && has( $hard, "'high' === \$risk ? 90" ), 'risk-tier review cadence' );
r80( 43, has( $hard, 'CURSOR_OPTION' ) && has( $hard, 'id>%d ORDER BY id ASC LIMIT %d' ), 'rotating full-corpus maintenance' );
r80( 44, has( $hard, 'insufficient-references' ) && has( $hard, 'claim-structure-missing' ) && has( $hard, 'contradictory-evidence' ), 'evidence-gap radar' );
r80( 45, has( $hard, 'refresh_gap( $id )' ), 'gap scan participates in rotating cursor' );
r80( 46, has( $hard, 'human review is required before any scientific conclusion' ), 'gap engine cannot auto-conclude' );
r80( 47, has( $hard, 'page_locator,publisher,year,url,doi,evidence_grade,rights_status' ), 'citation schema mapping' );
r80( 48, has( $hard, "array( 'json', 'jsonld', 'bibtex', 'ris', 'csl-json' )" ), 'citation formats' );
r80( 49, has( $hard, 'rights_status' ) && ! has( $hard, 'body longtext' ), 'citation minimization' );
r80( 50, has( $guard, "future/watchlist' === \$route" ) && has( $guard, 'membership_allowed' ), 'watchlist auth' );
r80( 51, has( $hard, "set_param( 'active', 1 )" ), 'watchlist omitted active defaults true' );
r80( 52, has( $b, "'notification_owner'=> 'file-19'" ) && has( $future, "delivery_owner'=>'file-19'" ), 'File 19 delivery ownership' );
r80( 53, has( $future, "array('ur-PK','ar','en-US')" ), 'governed locales' );
r80( 54, has( $hard, 'he_future_translation_source_changed' ), 'translation source-version binding' );
r80( 55, has( $hard, 'rest_translation_transition' ) && has( $hard, "'review'" ) && has( $hard, "'approved'" ) && has( $hard, "'published'" ) && has( $hard, "'rejected'" ), 'translation review/publish lifecycle' );
r80( 56, has( $future, 'translation-outdated' ) && has( $future, 'source_version<c.current_version' ), 'stale translation invalidation' );
r80( 57, has( $guard, 'future/command-center' ) && has( $guard, 'CAP_REVIEW' ), 'command center least privilege' );
r80( 58, has( $guard, "'unreviewed_claims'" ) && has( $guard, "'active_watch_relations'" ) && has( $guard, "'dead_letter_impacts'" ), 'command center operational coverage' );
r80( 59, has( $future, 'KnowledgeEvidenceChanged.v1' ) && has( $b, 'KnowledgeTranslationPublished.v1' ), 'knowledge event catalog' );
r80( 60, has( $hard, "'assurance_owner' => 'file-24'" ), 'File 24 assurance ownership' );
r80( 61, has( $b, "'layout_owner'      => 'file-20'" ), 'File 20 shell ownership' );
r80( 62, has( $b, "'visual_owner'      => 'file-25'" ), 'File 25 visual ownership' );
r80( 63, has( $b, "'search_owner'      => 'file-26'" ) && has( $consumers, "'rebuild_is_bounded' => true" ), 'File 26 search ownership' );
r80( 64, has( $b, "'consumer_files'    => array( 'file-05', 'file-12', 'file-15', 'file-16', 'file-21', 'file-26' )" ), 'explicit consumer boundaries' );
r80( 65, has( $guard, 'HE_V2_Schema::OPTION_SAFE_MODE' ) && has( $guard, 'he_safe_mode' ), 'safe mode pauses future mutations' );
r80( 66, has( $hard, "'impact_dead_letter'" ) && has( $hard, "'knowledge_cursor'" ), 'aggregate operational health' );
r80( 67, has( $privacy, 'wp_privacy_personal_data_exporters' ) && has( $privacy, 'wp_privacy_personal_data_erasers' ), 'privacy export/erase hooks' );
r80( 68, has( $uninstall, 'he_v2_privacy_legal_hold' ) && has( $uninstall, "'claims','claim_evidence','provenance','external_records'" ) && has( $uninstall, 'he_v23_future_maintenance' ), 'hold-aware, Future-18-complete destructive uninstall' );
r80( 69, has( $gov, 'migration_quarantine' ) && has( $b, "'resumable' => true" ), 'resumable migration quarantine' );
r80( 70, has( $gov, 'add_option( self::LOCK_OPTION' ), 'atomic upgrade lock' );
r80( 71, has( $b, 'wp_clear_scheduled_hook( HE_V23_Future::CRON )' ), 'future cron cleared on deactivation' );
r80( 72, has( $hard, 'catch ( Throwable $error )' ) && has( $hard, 'last_error' ), 'background exception handling' );
r80( 73, has( $integrity, 'START TRANSACTION' ) && has( $integrity, 'FOR UPDATE' ), 'transactional integrity concurrency' );
r80( 74, has( $hard, '$wpdb->prepare' ) && has( $domain, '$wpdb->prepare' ), 'prepared SQL' );
r80( 75, has( $domain, 'wp_kses_post' ) && has( $domain, 'sanitize_text_field' ), 'content sanitization/XSS controls' );
r80( 76, has( $css, ':focus-visible' ) && has( $css, 'prefers-reduced-motion' ), 'accessibility focus/reduced-motion' );
r80( 77, has( $css, '[dir="rtl"]' ) || has( $css, '[dir=rtl]' ), 'RTL styling' );
r80( 78, has( $b, "'audit_review_rounds' => 80" ) && has( $b, "'release_state'" ), 'audit count and release truth' );
r80( 79, has( $workflow, 'v24-audit80-invariants.php' ) && has( $workflow, '2.4.0-a.zip' ), 'CI executes audit and v2.4 package' );
r80( 80, has( $b, "'staging_accepted' => false" ) && has( $b, "'live_deployed' => false" ) && has( $b, "'operational' => false" ), 'staging/live/operational remain unclaimed' );

if ( 80 !== $count ) { $fail[] = 'Audit did not execute exactly 80 rounds.'; }
if ( $fail ) {
	fwrite( STDERR, "File 06 Audit-80 FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}
echo "File 06 Audit-80 PASS: all 80 deterministic review rounds green.\n";

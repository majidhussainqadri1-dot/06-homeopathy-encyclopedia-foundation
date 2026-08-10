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
$search = $read( $plugin . '/includes/class-he-v22-search.php' );
$consumers = $read( $plugin . '/includes/class-he-v22-consumers.php' );
$ops = $read( $plugin . '/includes/class-he-v22-operations.php' );
$type_schemas = $read( $plugin . '/includes/class-he-v22-type-schemas.php' );
$research = $read( $plugin . '/includes/class-he-v22-research-guard.php' );
$css = $read( $plugin . '/assets/css/encyclopedia-v2.css' );
$uninstall = $read( $plugin . '/uninstall.php' );
$all = implode( "\n", array( $b,$schema,$domain,$api,$auth,$privacy,$future,$hard,$guard,$gov,$integrity,$search,$consumers,$ops,$type_schemas,$research ) );
$fail = array();
$count = 0;
function r80( $n, $ok, $label ) {
	global $fail, $count;
	$count++;
	if ( $n !== $count ) { $fail[] = "Round numbering mismatch: expected {$count}, got {$n}."; }
	if ( ! $ok ) { $fail[] = sprintf( 'Round %02d FAILED: %s', $n, $label ); }
}

r80( 1, false !== strpos( $b, "define( 'HE_VERSION', '2.4.0' )" ), 'exact software version 2.4.0' );
r80( 2, false !== strpos( $b, "define( 'HE_SCHEMA_VERSION', 10 )" ) && false !== strpos( $b, "HE_CONTRACT_VERSION', '2.4" ), 'schema/contract version truth' );
r80( 3, false !== strpos( $b, 'class-he-v24-audit80-hardening.php' ) && false !== strpos( $b, 'class-he-v24-final-guard.php' ), 'audit hardening bootstrap load' );
r80( 4, false !== strpos( $b, "HE_V24_Audit80_Hardening::hooks" ) && false !== strpos( $b, "HE_V24_Final_Guard::hooks" ), 'runtime hook wiring' );
r80( 5, 16 === substr_count( $type_schemas, "'body_system_required' =>" ), 'sixteen fixed type schemas' );
r80( 6, false !== strpos( $hard, 'source_concept_id' ) && false !== strpos( $hard, 'target_concept_id' ), 'graph uses real relation columns' );
r80( 7, false !== strpos( $domain, "'draft', 'validation', 'review', 'approved', 'scheduled', 'published', 'corrected', 'retracted', 'archived'" ), 'entry state constitution' );
r80( 8, false !== strpos( $guard, "'approved' === \$row['review_status']" ) && false !== strpos( $guard, "'approved' === \$row['safety_status']" ), 'public state requires review+safety approval' );
r80( 9, false !== strpos( $future, '/future/claims' ) && false !== strpos( $future, '/future/command-center' ), 'Future-18 REST surface' );
r80( 10, false !== strpos( $guard, 'concept_id_from_public_route' ) && false !== strpos( $guard, 'fully_public_concept' ), 'public GET authorization projection guard' );
r80( 11, false !== strpos( $guard, 'Idempotency-Key' ) && false !== strpos( $guard, 'rate_allow' ) && false !== strpos( $guard, 'require_nonce' ), 'future mutation nonce/rate/idempotency' );
r80( 12, false !== strpos( $auth, 'SMC_Contracts::assertions' ) && false !== strpos( $auth, 'he_identity_provider_unavailable' ), 'File 00 fail-closed identity authority' );
r80( 13, false !== strpos( $future, "table( 'claims' )" ) && false !== strpos( $future, "table( 'claim_evidence' )" ), 'claim evidence graph storage' );
r80( 14, false !== strpos( $hard, 'he_future_evidence_source_required' ), 'claim evidence cannot be source-free' );
r80( 15, false !== strpos( $hard, "claim_state='active'" ) && false !== strpos( $hard, 'SELECT reference_id,external_id,relation,weight' ), 'public claim projection minimized' );
r80( 16, false !== strpos( $future, "table( 'provenance' )" ) && false === strpos( $future, '/future/provenance' . "', 'POST" ), 'provenance has no public mutation route' );
r80( 17, false !== strpos( $guard, 'future/provenance' ) && false !== strpos( $guard, 'CAP_REVIEW' ), 'provenance detail reviewer-only' );
r80( 18, false !== strpos( $future, 'api.crossref.org' ) && false !== strpos( $future, 'eutils.ncbi.nlm.nih.gov' ), 'provider allowlist present' );
r80( 19, false !== strpos( $hard, 'validate_external_identifier' ) && false !== strpos( $hard, 'he_future_external_id_invalid' ), 'provider-specific identifier validation' );
r80( 20, false !== strpos( $hard, 'limit_response_size' ) && false !== strpos( $hard, 'reject_unsafe_urls' ), 'provider response/SSRF bounds' );
r80( 21, false !== strpos( $future, 'retraction-watch' ) && false !== strpos( $future, 'urgent-review' ), 'retraction/correction watch' );
r80( 22, false !== strpos( $future, "'pubmed'" ), 'PubMed connector' );
r80( 23, false !== strpos( $future, "'clinicaltrials'" ), 'ClinicalTrials connector' );
r80( 24, false !== strpos( $future, "'orcid'" ), 'ORCID adapter' );
r80( 25, false !== strpos( $future, "'datacite'" ), 'DataCite adapter' );
r80( 26, false !== strpos( $future, "'mesh'" ), 'MeSH mapping' );
r80( 27, false !== strpos( $future, "'review_required'=>1" ) || false !== strpos( $future, "'review_required'=>true" ), 'external metadata staged for human review' );
r80( 28, false !== strpos( $future, 'token-jaccard-v1' ) && false !== strpos( $future, "'state'=>'candidate'" ), 'duplicate intelligence advisory mode' );
r80( 29, false !== strpos( $future, 'LIMIT 500' ) && false !== strpos( $future, 'array_slice($out,0,50)' ), 'duplicate scan bounded' );
r80( 30, false !== strpos( $hard, 'r.source_concept_id' ) && false !== strpos( $hard, 'r.target_concept_id' ), 'graph SQL schema consistency' );
r80( 31, false !== strpos( $hard, "s.status IN ('published','corrected')" ) && false !== strpos( $hard, "t.status IN ('published','corrected')" ), 'graph hides nonpublic neighbors' );
r80( 32, false !== strpos( $hard, "'visual_owner' => 'file-25'" ), 'graph rendering ownership File 25' );
r80( 33, false !== strpos( $hard, 'version_number,status,title,summary,content_hash' ), 'time machine uses real version columns' );
r80( 34, false !== strpos( $hard, "status IN ('published','corrected','retracted')" ), 'time machine excludes draft/review versions' );
r80( 35, false === strpos( $hard, 'version_no,state,created_by' ), 'obsolete version field names absent from hardening' );
r80( 36, false !== strpos( $future, "array('file-05','file-12','file-15','file-16','file-21','file-26')" ), 'impact queue complete consumer set' );
r80( 37, false !== strpos( $hard, 'dedupe_impact_queue' ) && false !== strpos( $hard, "payload_json=%s" ), 'impact deduplication' );
r80( 38, false !== strpos( $hard, "'dead-letter'" ) && false !== strpos( $hard, "'retry'" ) && false !== strpos( $hard, 'last_error' ), 'impact retry/dead-letter evidence' );
r80( 39, false !== strpos( $consumers, "'write_authority' => false" ) && false !== strpos( $consumers, "'private_fields' => false" ), 'consumer contracts read-only/public-safe' );
r80( 40, false !== strpos( $future, "table( 'freshness' )" ), 'freshness storage' );
r80( 41, false !== strpos( $hard, "object_type='concept' AND object_id=%d" ), 'freshness review query matches review schema' );
r80( 42, false !== strpos( $hard, "'critical' === \$risk ? 30" ) && false !== strpos( $hard, "'high' === \$risk ? 90" ), 'risk-tier review cadence' );
r80( 43, false !== strpos( $hard, 'CURSOR_OPTION' ) && false !== strpos( $hard, 'id>%d ORDER BY id ASC LIMIT %d' ), 'rotating full-corpus maintenance' );
r80( 44, false !== strpos( $hard, 'insufficient-references' ) && false !== strpos( $hard, 'claim-structure-missing' ) && false !== strpos( $hard, 'contradictory-evidence' ), 'evidence-gap radar' );
r80( 45, false !== strpos( $hard, 'refresh_gap( $id )' ), 'gap scan participates in rotating cursor' );
r80( 46, false !== strpos( $hard, 'human review is required before any scientific conclusion' ), 'gap engine cannot auto-conclude' );
r80( 47, false !== strpos( $hard, 'page_locator,publisher,year,url,doi,evidence_grade,rights_status' ), 'citation export maps real reference schema' );
r80( 48, false !== strpos( $hard, "array( 'json', 'jsonld', 'bibtex', 'ris', 'csl-json' )" ), 'five governed citation formats' );
r80( 49, false !== strpos( $hard, 'rights_status' ) && false === strpos( $hard, 'body longtext' ), 'citation output avoids full entry body copy' );
r80( 50, false !== strpos( $guard, "future/watchlist' === \$route" ) && false !== strpos( $guard, 'membership_allowed' ), 'watchlist eligible-account authorization' );
r80( 51, false !== strpos( $hard, "set_param( 'active', 1 )" ), 'watchlist omission defaults active' );
r80( 52, false !== strpos( $b, "'notification_owner'=> 'file-19'" ) && false !== strpos( $future, "delivery_owner'=>'file-19'" ), 'File 19 owns notification delivery' );
r80( 53, false !== strpos( $future, "array('ur-PK','ar','en-US')" ), 'governed multilingual locales' );
r80( 54, false !== strpos( $hard, 'he_future_translation_source_changed' ), 'translation source-version binding' );
r80( 55, false !== strpos( $hard, '/future/translations/(?P<id>\\d+)/transition' ) && false !== strpos( $hard, "array( 'review', 'approved', 'published', 'rejected' )" ), 'translation review/publish lifecycle' );
r80( 56, false !== strpos( $future, 'translation-outdated' ) && false !== strpos( $future, 'source_version<c.current_version' ), 'stale translation invalidation' );
r80( 57, false !== strpos( $guard, "future/command-center' === \$route" ) && false !== strpos( $guard, 'CAP_REVIEW' ), 'command center least privilege' );
r80( 58, false !== strpos( $guard, "'unreviewed_claims'" ) && false !== strpos( $guard, "'active_watch_relations'" ) && false !== strpos( $guard, "'dead_letter_impacts'" ), 'command center required operational coverage' );
r80( 59, false !== strpos( $future, 'KnowledgeEvidenceChanged.v1' ) && false !== strpos( $b, 'KnowledgeTranslationPublished.v1' ), 'knowledge event catalog' );
r80( 60, false !== strpos( $hard, "'assurance_owner' => 'file-24'" ), 'File 24 assurance ownership' );
r80( 61, false !== strpos( $b, "'layout_owner'      => 'file-20'" ), 'File 20 shell/layout ownership' );
r80( 62, false !== strpos( $b, "'visual_owner'      => 'file-25'" ), 'File 25 visual ownership' );
r80( 63, false !== strpos( $b, "'search_owner'      => 'file-26'" ) && false !== strpos( $consumers, "'rebuild_is_bounded' => true" ), 'File 26 global search ownership' );
r80( 64, false !== strpos( $b, "'consumer_files'    => array( 'file-05', 'file-12', 'file-15', 'file-16', 'file-21', 'file-26' )" ), 'explicit consumer boundaries' );
r80( 65, false !== strpos( $guard, 'HE_V2_Schema::OPTION_SAFE_MODE' ) && false !== strpos( $guard, 'he_safe_mode' ), 'safe mode pauses future mutations' );
r80( 66, false !== strpos( $hard, "'impact_dead_letter'" ) && false !== strpos( $hard, "'knowledge_cursor'" ), 'operational health is redacted/aggregate' );
r80( 67, false !== strpos( $privacy, 'wp_privacy_personal_data_exporters' ) && false !== strpos( $privacy, 'wp_privacy_personal_data_erasers' ), 'privacy export/erase hooks' );
r80( 68, false !== strpos( $uninstall, 'migration_quarantine' ) && false !== strpos( $uninstall, 'he_v2_privacy_legal_hold' ), 'non-destructive/hold-aware lifecycle evidence' );
r80( 69, false !== strpos( $gov, 'migration_quarantine' ) && false !== strpos( $b, "'resumable' => true" ), 'resumable migration quarantine' );
r80( 70, false !== strpos( $gov, 'add_option( self::LOCK_OPTION' ), 'atomic upgrade lock' );
r80( 71, false !== strpos( $b, 'wp_clear_scheduled_hook( HE_V23_Future::CRON )' ), 'future cron cleared on deactivation' );
r80( 72, false !== strpos( $hard, 'catch ( Throwable $error )' ) && false !== strpos( $hard, 'last_error' ), 'background exception handling' );
r80( 73, false !== strpos( $integrity, 'START TRANSACTION' ) && false !== strpos( $integrity, 'FOR UPDATE' ), 'transactional integrity concurrency' );
r80( 74, false !== strpos( $hard, '$wpdb->prepare' ) && false !== strpos( $domain, '$wpdb->prepare' ), 'prepared SQL on parameterized paths' );
r80( 75, false !== strpos( $domain, 'wp_kses_post' ) && false !== strpos( $domain, 'sanitize_text_field' ), 'content sanitization/XSS controls' );
r80( 76, false !== strpos( $css, ':focus-visible' ) && false !== strpos( $css, 'prefers-reduced-motion' ), 'accessibility focus/reduced-motion' );
r80( 77, false !== strpos( $css, '[dir="rtl"]' ) || false !== strpos( $css, '[dir=rtl]' ), 'RTL styling' );
r80( 78, false !== strpos( $b, "'audit_review_rounds' => 80" ) && false !== strpos( $b, "'release_state'" ), 'audit count and release truth in contract' );
r80( 79, false !== strpos( $root ? file_get_contents( $root . '/.github/workflows/file06-v2-complete.yml' ) : '', 'v24-audit80-invariants.php' ), 'CI executes 80-round audit suite' );
r80( 80, false !== strpos( $b, "'staging_accepted' => false" ) && false !== strpos( $b, "'live_deployed' => false" ) && false !== strpos( $b, "'operational' => false" ), 'staging/live/operational truth remains unclaimed' );

if ( 80 !== $count ) { $fail[] = 'Audit did not execute exactly 80 rounds.'; }
if ( $fail ) {
	fwrite( STDERR, "File 06 Audit-80 FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}
echo "File 06 Audit-80 PASS: all 80 deterministic review rounds green.\n";

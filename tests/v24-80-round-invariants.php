<?php
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$b = file_get_contents( $plugin . '/homeopathy-encyclopedia.php' );
$s = file_get_contents( $plugin . '/includes/class-he-v24-future-schema.php' );
$m = file_get_contents( $plugin . '/includes/class-he-v24-migration-safety.php' );
$a = file_get_contents( $plugin . '/includes/class-he-v24-future-api.php' );
$p = file_get_contents( $plugin . '/includes/class-he-v24-future-privacy.php' );
$u = file_get_contents( $plugin . '/uninstall.php' );
$fail = array();
function v24_assert( $ok, $msg ) { global $fail; if ( ! $ok ) { $fail[] = $msg; } }
function v24_has( $haystack, $needle, $msg ) { v24_assert( false !== strpos( $haystack, $needle ), $msg ); }

v24_has( $b, 'Version: 2.4.0', 'plugin header 2.4.0' );
v24_has( $b, "HE_VERSION', '2.4.0", 'runtime 2.4.0' );
v24_has( $b, "HE_SCHEMA_VERSION', 10", 'schema 10' );
v24_has( $b, "HE_CONTRACT_VERSION', '2.4", 'contract 2.4' );
foreach ( array( 'class-he-v24-future-schema.php','class-he-v24-migration-safety.php','class-he-v24-future-api.php','class-he-v24-future-privacy.php' ) as $file ) { v24_has( $b, $file, 'bootstrap ' . $file ); }
v24_has( $b, "'staging_accepted' => false", 'truthful staging status' );
v24_has( $b, "'live_deployed' => false", 'truthful live status' );

foreach ( array( 'version_id','confidence','review_status','reviewed_by','row_version' ) as $token ) { v24_has( $s, $token, 'claim field ' . $token ); }
v24_has( $s, "c.review_status='approved'", 'public claims approved only' );
v24_has( $s, 'EXISTS (SELECT 1 FROM ', 'claim evidence fail-closed' );
v24_has( strtolower( $a ), 'cannot be approved without governed evidence', 'claim approval evidence gate' );
v24_has( $a, 'row_version=row_version+1', 'claim optimistic concurrency' );

foreach ( array( 'parent_hash','record_hash','sha256','http://www.w3.org/ns/prov#' ) as $token ) { v24_has( $s, $token, 'provenance ' . $token ); }
v24_assert( false === strpos( $s, 'SELECT actor_id,object_type,object_id,action' ), 'public provenance actor leak' );
v24_has( $m, 'backfill_provenance', 'legacy provenance backfill' );

foreach ( array( 'crossref','pubmed','clinicaltrials','orcid','datacite','mesh' ) as $provider ) { v24_has( $s, "'{$provider}'", 'provider ' . $provider ); }
v24_has( $s, 'wp_safe_remote_get', 'safe provider HTTP' );
v24_has( $s, 'limit_response_size', 'provider response bound' );
v24_has( $s, 'valid_orcid', 'ORCID checksum' );
v24_has( $s, 'NCT[0-9]{8}', 'NCT validation' );
v24_has( $a, "array( 'claim','research' )", 'trial claim/research binding' );
v24_has( $a, 'researcher-identities', 'separate ORCID researcher mapping' );
v24_has( $a, 'grants_platform_privilege', 'ORCID privilege boundary' );
v24_has( $m, "mapping_state='legacy-invalid'", 'legacy concept ORCID quarantine' );

foreach ( array( 'aliases','structured-fields','references','graph-context' ) as $token ) { v24_has( $a, $token, 'duplicate signal ' . $token ); }
v24_has( $a, 'source_concept_id', 'graph source schema' );
v24_has( $a, 'target_concept_id', 'graph target schema' );
v24_assert( false === strpos( $a, 'WHERE source_id=%d OR target_id=%d' ), 'legacy graph columns present in v2.4 override' );
foreach ( array( 'version_number','summary','change_reason','effective_at' ) as $token ) { v24_has( $a, $token, 'time machine ' . $token ); }
v24_assert( false === strpos( $a, 'SELECT id,version_no,state,created_by' ), 'legacy time-machine query present in override' );

foreach ( array( 'dedupe_key','retry','dead-letter','acknowledged','last_error','next_attempt_at' ) as $token ) { v24_has( $s, $token, 'impact ' . $token ); }
v24_has( $s, 'he_v24_consumer_revalidation_ack', 'consumer acknowledgement contract' );
v24_has( $m, "impact_state='retry'", 'legacy emitted queue revalidation' );

v24_has( $s, "object_type='concept' AND object_id=%d", 'freshness real review schema' );
foreach ( array( 'urgent-review','priority_score','he_v24_freshness_cursor','he_v24_gap_cursor','claims-without-evidence','missing-safety-fields','stale-reference-links' ) as $token ) { v24_has( $s, $token, 'freshness/gap ' . $token ); }

v24_has( $a, 'bibliographic-metadata-only; no restricted full text', 'citation rights policy' );
v24_has( $a, "'doi'", 'citation DOI field' );
v24_assert( false === strpos( $a, "SELECT * FROM ' . HE_V2_Schema::table( 'references' )" ), 'raw citation rows exposed' );

v24_has( $a, '$request->has_param( \'active\' )', 'watch omission handling' );
v24_has( $a, "'concept' !== $type", 'watch canonical concept restriction' );
v24_has( $a, "'delivery_owner' => 'file-19'", 'File 19 watch delivery owner' );

v24_has( $a, "status='published'", 'public translations published only' );
v24_has( $a, 'rest_translation_review', 'translation review route' );
v24_has( $a, 'rest_translation_publish', 'translation publish route' );
v24_has( $a, 'independent approval review', 'translation independent review' );
v24_has( $s, "status='translation-outdated'", 'translation outdated state' );

foreach ( array( 'claims_without_evidence','unreviewed_claims','orphan_concepts','dead_letter_impacts','connector_health','active_watches','autonomous_high_risk_actions' ) as $token ) { v24_has( $a, $token, 'command center ' . $token ); }

foreach ( array( 'wp_privacy_personal_data_exporters','wp_privacy_personal_data_erasers','he_v2_privacy_legal_hold','watchlists','researcher_ids','translations','provenance' ) as $token ) { v24_has( $p, $token, 'future privacy ' . $token ); }
v24_has( $p, "'actor_id' => 0", 'provenance privacy de-identification' );

v24_has( $m, 'preflight', 'migration preflight' );
v24_has( $m, 'postflight', 'migration postflight' );
v24_has( $m, 'DROP INDEX `provider_external`', 'legacy external unique index migration' );
v24_has( $b, "HE_V24_Future_Schema', 'deactivate", 'v2.4 deactivation hook' );
foreach ( array( 'he_v23_future_maintenance','he_v24_future_maintenance','researcher_ids','he_v24_future_version' ) as $token ) { v24_has( $u, $token, 'guarded purge ' . $token ); }

foreach ( array( 'file-00','file-19','file-20','file-24','file-25','file-26' ) as $owner ) { v24_has( $b, $owner, 'ownership boundary ' . $owner ); }
foreach ( array( 'file-05','file-12','file-15','file-16','file-21','file-26' ) as $consumer ) { v24_has( $b, "'{$consumer}'", 'consumer ' . $consumer ); }

foreach ( array( 'HE_V2_Schema::OPTION_SAFE_MODE','HE_V2_Auth::require_nonce','HE_V2_Domain::rate_allow','Idempotency-Key','idempotent_begin','idempotent_finish' ) as $token ) { v24_has( $a, $token, 'future mutation guard ' . $token ); }

if ( $fail ) {
 fwrite( STDERR, "File 06 v2.4 80-round hardening invariants FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
 exit( 1 );
}
echo "File 06 v2.4 80-round hardening invariants passed.\n";

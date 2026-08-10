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

/* Runtime/source truth. */
v24_assert( false !== strpos( $b, "Version: 2.4.0" ), 'plugin header 2.4.0' );
v24_assert( false !== strpos( $b, "HE_VERSION', '2.4.0" ), 'runtime 2.4.0' );
v24_assert( false !== strpos( $b, "HE_SCHEMA_VERSION', 10" ), 'schema 10' );
v24_assert( false !== strpos( $b, "HE_CONTRACT_VERSION', '2.4" ), 'contract 2.4' );
foreach ( array( 'class-he-v24-future-schema.php','class-he-v24-migration-safety.php','class-he-v24-future-api.php','class-he-v24-future-privacy.php' ) as $file ) {
 v24_assert( false !== strpos( $b, $file ), 'bootstrap missing ' . $file );
}
v24_assert( false !== strpos( $b, "'staging_accepted' => false" ), 'truthful staging status' );
v24_assert( false !== strpos( $b, "'live_deployed' => false" ), 'truthful live status' );

/* F06-FUT-001 claim-level evidence and fail-closed publication. */
foreach ( array( 'version_id','confidence','review_status','reviewed_by','row_version' ) as $token ) { v24_assert( false !== strpos( $s, $token ), 'claim governance field ' . $token ); }
v24_assert( false !== strpos( $s, "c.review_status='approved'" ), 'public claims approved only' );
v24_assert( false !== strpos( $s, 'EXISTS (SELECT 1 FROM ' ), 'public claim evidence fail-closed' );
v24_assert( false !== strpos( $a, 'claim cannot be approved without governed evidence' ) || false !== strpos( strtolower( $a ), 'cannot be approved without governed evidence' ), 'claim approval evidence gate' );
v24_assert( false !== strpos( $a, 'row_version=row_version+1' ), 'claim optimistic concurrency' );

/* F06-FUT-002 provenance hash chain and public-safe DTO. */
foreach ( array( 'parent_hash','record_hash','sha256','http://www.w3.org/ns/prov#' ) as $token ) { v24_assert( false !== strpos( $s, $token ), 'provenance hardening ' . $token ); }
v24_assert( false === strpos( $s, 'SELECT actor_id,object_type,object_id,action' ), 'public provenance must not select actor_id' );
v24_assert( false !== strpos( $m, 'backfill_provenance' ), 'legacy provenance hash backfill' );

/* External connectors, SSRF, bounded responses and provider-specific IDs. */
foreach ( array( 'crossref','pubmed','clinicaltrials','orcid','datacite','mesh' ) as $provider ) { v24_assert( false !== strpos( $s, "'{$provider}'" ), 'provider ' . $provider ); }
v24_assert( false !== strpos( $s, 'wp_safe_remote_get' ), 'safe HTTP client' );
v24_assert( false !== strpos( $s, 'limit_response_size' ), 'provider response bound' );
v24_assert( false !== strpos( $s, 'valid_orcid' ), 'ORCID validation' );
v24_assert( false !== strpos( $s, 'NCT[0-9]{8}' ), 'ClinicalTrials identifier validation' );
v24_assert( false !== strpos( $a, "array( 'claim','research' )" ), 'clinical-trial claim/research binding' );
v24_assert( false !== strpos( $a, 'researcher-identities' ), 'separate researcher ORCID route' );
v24_assert( false !== strpos( $a, 'grants_platform_privilege' ) && false !== strpos( $a, 'false' ), 'ORCID no privilege grant' );
v24_assert( false !== strpos( $m, "mapping_state='legacy-invalid'" ), 'legacy concept ORCID quarantine' );

/* Duplicate intelligence, graph and time machine schema correctness. */
foreach ( array( 'aliases','structured-fields','references','graph-context' ) as $token ) { v24_assert( false !== strpos( $a, $token ), 'duplicate signal ' . $token ); }
v24_assert( false !== strpos( $a, 'source_concept_id' ) && false !== strpos( $a, 'target_concept_id' ), 'graph uses real relation schema' );
v24_assert( false === strpos( $a, "WHERE source_id=%d OR target_id=%d" ), 'graph must not use v2.3 invalid columns' );
foreach ( array( 'version_number','status','summary','change_reason','effective_at' ) as $token ) { v24_assert( false !== strpos( $a, $token ), 'time-machine field ' . $token ); }
v24_assert( false === strpos( $a, 'SELECT id,version_no,state,created_by' ), 'time-machine must not expose legacy invalid/internal fields' );

/* Impact queue reliability. */
foreach ( array( 'dedupe_key','retry','dead-letter','acknowledged','last_error','next_attempt_at' ) as $token ) { v24_assert( false !== strpos( $s, $token ), 'impact reliability ' . $token ); }
v24_assert( false !== strpos( $s, 'he_v24_consumer_revalidation_ack' ), 'consumer acknowledgement contract' );
v24_assert( false !== strpos( $m, "impact_state='retry'" ), 'legacy emitted queue revalidation' );

/* Freshness, gap radar and bounded cursors. */
v24_assert( false !== strpos( $s, "object_type='concept' AND object_id=%d" ), 'freshness uses real reviews schema' );
foreach ( array( 'urgent-review','priority_score','he_v24_freshness_cursor','he_v24_gap_cursor','claims-without-evidence','missing-safety-fields','stale-reference-links' ) as $token ) { v24_assert( false !== strpos( $s, $token ), 'freshness/gap hardening ' . $token ); }

/* Citation rights/public DTO. */
v24_assert( false !== strpos( $a, 'bibliographic-metadata-only; no restricted full text' ), 'citation rights policy' );
v24_assert( false !== strpos( $a, "'doi'" ), 'citation DOI field' );
v24_assert( false === strpos( $a, "SELECT * FROM ' . HE_V2_Schema::table( 'references' )" ), 'citation endpoint must not select raw reference rows' );

/* Watchlists. */
v24_assert( false !== strpos( $a, "$request->has_param( 'active' )" ), 'watch active omission handled explicitly' );
v24_assert( false !== strpos( $a, "'concept' !== $type" ), 'watchlist canonical concept restriction' );
v24_assert( false !== strpos( $a, "'delivery_owner' => 'file-19'" ), 'File 19 owns watch delivery' );

/* Governed translations. */
v24_assert( false !== strpos( $a, "status='published'" ), 'public translations published only' );
v24_assert( false !== strpos( $a, 'rest_translation_review' ) && false !== strpos( $a, 'rest_translation_publish' ), 'translation review/publish workflow' );
v24_assert( false !== strpos( $a, 'independent approval review' ), 'translation independent review' );
v24_assert( false !== strpos( $s, "status='translation-outdated'" ), 'source-change translation invalidation' );

/* Integrity command center. */
foreach ( array( 'claims_without_evidence','unreviewed_claims','orphan_concepts','dead_letter_impacts','connector_health','active_watches','autonomous_high_risk_actions' ) as $token ) { v24_assert( false !== strpos( $a, $token ), 'command-center metric ' . $token ); }

/* Future privacy lifecycle. */
foreach ( array( 'wp_privacy_personal_data_exporters','wp_privacy_personal_data_erasers','he_v2_privacy_legal_hold','watchlists','researcher_ids','translations','provenance' ) as $token ) { v24_assert( false !== strpos( $p, $token ), 'future privacy ' . $token ); }
v24_assert( false !== strpos( $p, "'actor_id' => 0" ), 'provenance actor de-identification' );

/* Migration/deactivation/uninstall lifecycle. */
v24_assert( false !== strpos( $m, 'preflight' ) && false !== strpos( $m, 'postflight' ), 'upgrade pre/postflight' );
v24_assert( false !== strpos( $m, 'DROP INDEX `provider_external`' ), 'legacy external-record uniqueness corrected' );
v24_assert( false !== strpos( $b, "HE_V24_Future_Schema', 'deactivate" ), 'v2.4 deactivation hook' );
foreach ( array( 'he_v23_future_maintenance','he_v24_future_maintenance','researcher_ids','he_v24_future_version' ) as $token ) { v24_assert( false !== strpos( $u, $token ), 'guarded purge coverage ' . $token ); }

/* Canonical ownership boundaries. */
foreach ( array( "'identity_authority'=> 'file-00'", "'layout_owner'      => 'file-20'", "'visual_owner'      => 'file-25'", "'search_owner'      => 'file-26'", "'assurance_owner'   => 'file-24'", "'notification_owner'=> 'file-19'" ) as $token ) { v24_assert( false !== strpos( $b, $token ), 'ownership boundary ' . $token ); }
foreach ( array( 'file-05','file-12','file-15','file-16','file-21','file-26' ) as $consumer ) { v24_assert( false !== strpos( $b, "'{$consumer}'" ), 'consumer boundary ' . $consumer ); }

/* Future mutation defense-in-depth. */
foreach ( array( 'HE_V2_Schema::OPTION_SAFE_MODE','HE_V2_Auth::require_nonce','HE_V2_Domain::rate_allow','Idempotency-Key','idempotent_begin','idempotent_finish' ) as $token ) { v24_assert( false !== strpos( $a, $token ), 'future mutation guard ' . $token ); }

if ( $fail ) {
 fwrite( STDERR, "File 06 v2.4 80-round hardening invariants FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
 exit( 1 );
}
echo "File 06 v2.4 80-round hardening invariants passed.\n";

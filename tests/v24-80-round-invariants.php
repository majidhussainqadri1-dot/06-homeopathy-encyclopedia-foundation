<?php
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$files = array(
	'bootstrap' => file_get_contents( $plugin . '/homeopathy-encyclopedia.php' ),
	'schema' => file_get_contents( $plugin . '/includes/class-he-v24-future-schema.php' ),
	'migration' => file_get_contents( $plugin . '/includes/class-he-v24-migration-safety.php' ),
	'api' => file_get_contents( $plugin . '/includes/class-he-v24-future-api.php' ),
	'privacy' => file_get_contents( $plugin . '/includes/class-he-v24-future-privacy.php' ),
	'guard' => file_get_contents( $plugin . '/includes/class-he-v24-future-review-guard.php' ),
	'uninstall' => file_get_contents( $plugin . '/uninstall.php' ),
);
$fail = array();
function v24_check( $condition, $message ) { global $fail; if ( ! $condition ) { $fail[] = $message; } }
function v24_has( $key, $needle, $message ) { global $files; v24_check( false !== strpos( $files[$key], $needle ), $message ); }
function v24_not( $key, $needle, $message ) { global $files; v24_check( false === strpos( $files[$key], $needle ), $message ); }

foreach ( array(
	'Version: 2.4.0', "HE_VERSION', '2.4.0", "HE_SCHEMA_VERSION', 10", "HE_CONTRACT_VERSION', '2.4",
	'class-he-v24-future-schema.php','class-he-v24-migration-safety.php','class-he-v24-future-api.php','class-he-v24-future-privacy.php','class-he-v24-future-review-guard.php',
	'future_v24_ready', "'staging_accepted' => false", "'live_deployed' => false"
) as $token ) { v24_has( 'bootstrap', $token, 'bootstrap/runtime token: ' . $token ); }

foreach ( array( 'version_id','confidence','review_status','reviewed_by','row_version',"c.review_status='approved'",'EXISTS (SELECT 1 FROM ','parent_hash','record_hash','http://www.w3.org/ns/prov#','dedupe_key','dead-letter','acknowledged','priority_score','urgent-review','he_v24_freshness_cursor','he_v24_gap_cursor','claims-without-evidence','missing-safety-fields','stale-reference-links' ) as $token ) { v24_has( 'schema', $token, 'schema token: ' . $token ); }
foreach ( array( 'crossref','pubmed','clinicaltrials','orcid','datacite','mesh','wp_safe_remote_get','limit_response_size','valid_orcid','NCT[0-9]{8}','he_v24_consumer_revalidation_ack' ) as $token ) { v24_has( 'schema', $token, 'provider/reliability token: ' . $token ); }

foreach ( array( 'backfill_provenance','DROP INDEX `provider_external`',"mapping_state='legacy-invalid'","impact_state='retry'",'preflight','postflight' ) as $token ) { v24_has( 'migration', $token, 'migration token: ' . $token ); }

foreach ( array( 'researcher-identities',"array( 'claim','research' )",'grants_platform_privilege','aliases','structured-fields','references','graph-context','source_concept_id','target_concept_id','version_number','summary','change_reason','effective_at','bibliographic-metadata-only; no restricted full text',"'doi'",'$request->has_param( \'active\' )',"'concept' !== \$type","'delivery_owner' => 'file-19'","status='published'",'claims_without_evidence','orphan_concepts','dead_letter_impacts','connector_health','autonomous_high_risk_actions','Idempotency-Key','idempotent_begin','idempotent_finish','HE_V2_Schema::OPTION_SAFE_MODE','HE_V2_Auth::require_nonce','HE_V2_Domain::rate_allow' ) as $token ) { v24_has( 'api', $token, 'API token: ' . $token ); }
v24_not( 'api', 'WHERE source_id=%d OR target_id=%d', 'legacy graph columns in v2.4 API' );
v24_not( 'api', 'SELECT id,version_no,state,created_by', 'legacy time-machine fields in v2.4 API' );
v24_not( 'api', "SELECT * FROM ' . HE_V2_Schema::table( 'references' )", 'raw reference rows in citation API' );

foreach ( array( 'rest_external_review','metadata.reviewed',"status='reviewed' AND review_required=0",'he_future_claim_version_gate','he_future_orcid_scope','he_future_mapping_scope','sanitize_provenance_response','strip_internal_ids',"translation_version=%d AND status='draft'","translation_version=%d AND status='approved'",'independent approval review' ) as $token ) { v24_has( 'guard', $token, 'final review-guard token: ' . $token ); }
foreach ( array( '/future/public/claims/','/future/public/graph/','/future/public/time-machine/','/future/public/freshness/','/future/public/citations/','/future/public/translations/' ) as $route ) { v24_has( 'guard', $route, 'canonical UUID public route: ' . $route ); }

foreach ( array( 'wp_privacy_personal_data_exporters','wp_privacy_personal_data_erasers','he_v2_privacy_legal_hold','watchlists','researcher_ids','translations','provenance',"'actor_id' => 0" ) as $token ) { v24_has( 'privacy', $token, 'privacy token: ' . $token ); }
foreach ( array( 'he_v23_future_maintenance','he_v24_future_maintenance','researcher_ids','he_v24_future_version' ) as $token ) { v24_has( 'uninstall', $token, 'uninstall token: ' . $token ); }

foreach ( array( 'file-00','file-19','file-20','file-24','file-25','file-26','file-05','file-12','file-15','file-16','file-21' ) as $owner ) { v24_has( 'bootstrap', $owner, 'ownership/consumer boundary: ' . $owner ); }

if ( $fail ) {
	fwrite( STDERR, "File 06 v2.4 80-round hardening invariants FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}
echo "File 06 v2.4 80-round hardening invariants passed.\n";

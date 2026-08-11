<?php
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$files = array(
 'bootstrap'=>file_get_contents($plugin.'/homeopathy-encyclopedia.php'),
 'schema'=>file_get_contents($plugin.'/includes/class-he-v24-future-schema.php'),
 'migration'=>file_get_contents($plugin.'/includes/class-he-v24-migration-safety.php'),
 'api'=>file_get_contents($plugin.'/includes/class-he-v24-future-api.php'),
 'privacy'=>file_get_contents($plugin.'/includes/class-he-v24-future-privacy.php'),
 'guard'=>file_get_contents($plugin.'/includes/class-he-v24-future-review-guard.php'),
 'provenance'=>file_get_contents($plugin.'/includes/class-he-v24-public-provenance.php'),
 'uninstall'=>file_get_contents($plugin.'/uninstall.php'),
);
$fail=array();
function v24_has($k,$n,$m){global $files,$fail;if(false===strpos($files[$k],$n)){$fail[]=$m;}}
function v24_not($k,$n,$m){global $files,$fail;if(false!==strpos($files[$k],$n)){$fail[]=$m;}}
foreach(array('Version: 2.4.1',"HE_VERSION', '2.4.1","HE_SCHEMA_VERSION', 10","HE_CONTRACT_VERSION', '2.4.1",'future_requirement_count','future_routes_fail_closed_until_ready',"'staging_accepted' => false","'live_deployed' => false") as $t){v24_has('bootstrap',$t,'bootstrap '.$t);}
foreach(array('version_id','confidence','review_status','reviewed_by','row_version',"c.review_status='approved'",'EXISTS (SELECT 1 FROM ','parent_hash','record_hash','dedupe_key','dead-letter','acknowledged','priority_score','urgent-review','crossref','pubmed','clinicaltrials','orcid','datacite','mesh','wp_safe_remote_get','limit_response_size','valid_orcid','he_v24_consumer_revalidation_ack') as $t){v24_has('schema',$t,'schema '.$t);}
foreach(array('backfill_provenance_batch','backfill_impact_batch','OPTION_PROVENANCE_DONE','OPTION_IMPACT_DONE','OPTION_ORCID_DONE','OPTION_EMITTED_DONE','public static function ready','const BATCH = 100') as $t){v24_has('migration',$t,'migration '.$t);}
foreach(array('researcher-identities',"array( 'claim','research' )",'grants_platform_privilege','source_concept_id','target_concept_id','version_number','change_reason','effective_at','bibliographic-metadata-only; no restricted full text','claims_without_evidence','dead_letter_impacts','connector_health','autonomous_high_risk_actions','Idempotency-Key','idempotent_begin','idempotent_finish') as $t){v24_has('api',$t,'api '.$t);}
v24_not('api','WHERE source_id=%d OR target_id=%d','legacy graph columns');
v24_not('api','SELECT id,version_no,state,created_by','legacy time-machine fields');
foreach(array('rest_external_review','metadata.reviewed',"status='reviewed' AND review_required=0",'he_future_claim_version_gate','he_future_orcid_scope','he_future_mapping_scope',"translation_version=%d AND status='draft'","translation_version=%d AND status='approved'",'independent approval review') as $t){v24_has('guard',$t,'guard '.$t);}
foreach(array('he_public_provenance_scope','he_canonical_public_id_required','prov:specializationOf','strip_internal_ids','source_uri') as $t){v24_has('provenance',$t,'provenance '.$t);}
foreach(array('wp_privacy_personal_data_exporters','wp_privacy_personal_data_erasers','he_v2_privacy_legal_hold','watchlists','researcher_ids','translations','provenance') as $t){v24_has('privacy',$t,'privacy '.$t);}
foreach(array('he_v24_provenance_migration_done','he_v24_impact_migration_done','he_v24_orcid_postflight_done','he_v24_emitted_postflight_done') as $t){v24_has('uninstall',$t,'uninstall '.$t);}
if($fail){fwrite(STDERR,"File 06 v2.4 controls FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo "File 06 first 80-round controls remain enforced under v2.4.1.\n";

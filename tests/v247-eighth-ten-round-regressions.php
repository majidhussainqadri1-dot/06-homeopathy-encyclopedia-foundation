<?php
/** File 06 v2.4.7 eighth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v247_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v247_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$gov=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v241-governance.php');
v247_ok(false!==strpos($gov,'public static function reviewer_assigned'),'R1 reviewer helper visibility');
$runtime=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v241-runtime-guard.php');
v247_ok(false!==strpos($runtime,'HE_V241_Governance::reviewer_assigned') && false!==strpos($runtime,'file06-external-review-research'),'R1 research external-review assignment');
$v22=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22,'case_details_restricted') && false!==strpos($v22,'dataset_payload_public') && false!==strpos($v22,'case_consent_verified'),'R2 authoritative research public privacy');
$v22m=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22m,'relation-provenance-clone-failed') && false!==strpos($v22m,'relation-provenance-invalid') && false!==strpos($v22m,'source_reference_id'),'R3 merge graph provenance rebinding');
$v22idx=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22idx,'SELECT evidence_grade FROM') && false!==strpos($v22idx,'AND version_id=%d'),'R4 secure search current-version evidence');
$domainidx=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v247_ok(false!==strpos($domainidx,'SELECT author,title,publisher,doi,evidence_grade FROM') && false!==strpos($domainidx,'AND version_id=%d'),'R4 inherited search current-version evidence');
$v22review=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22review,'expected_version') && false!==strpos($v22review,'The research record changed after it was loaded for review') && false!==strpos($v22review,'reviewed_row_version'),'R5 research review stale-version guard');
$api=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
v247_ok(false!==strpos($api,'expected_version') && false!==strpos($api,'The entry changed after it was loaded for review'),'R6 entry review stale-version guard');
$integrations=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
v247_ok(false!==strpos($integrations,'dashboard_post_allowed') && false!==strpos($integrations,'HE_V241_Governance::editor_type_allowed') && false!==strpos($integrations,'HE_V241_Governance::reviewer_assigned') && false!==strpos($integrations,'scope_filtered'),'R7 dashboard native scope');
$v22integrity=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22integrity,'The research integrity action must be accepted before it can be applied') && false!==strpos($v22integrity,'row_version=row_version+1') && false!==strpos($v22integrity,"status='accepted'"),'R9 research integrity apply accepted CAS');
/* R8 fresh review: privacy, translation, migration and public-read layers rechecked; no new source defect established. */
$bootstrap=v247_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$runall=v247_read($root.'/tests/run-all.sh');
v247_ok(false!==strpos($bootstrap,' * Version:') && false!==strpos($bootstrap,"define( 'HE_VERSION',") && false!==strpos($bootstrap,"define( 'HE_CONTRACT_VERSION',"),'Historical R10 runtime/contract declarations missing');
v247_ok(false!==strpos($bootstrap,"'future_hardening_version'=>"),'Historical R10 hardening declaration missing');
v247_ok(false!==strpos($runall,'v247-eighth-ten-round-regressions.php'),'R10 eighth-cycle suite absent from aggregate gate');
v247_ok(false!==strpos($runall,'-a.zip') && false!==strpos($runall,'-b.zip'),'Historical R10 deterministic package labels missing');
/*__V247_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.7 eighth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.7 eighth-review regressions: PASS\n";

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
/*__V247_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.7 eighth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.7 eighth-review regressions: PASS\n";

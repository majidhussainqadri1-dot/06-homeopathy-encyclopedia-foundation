<?php
/** File 06 v2.4.10 eleventh fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v2410_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v2410_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$v22=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$legacy_public_gate=false!==strpos($v22,'$public_eligible = $row');
$shared_public_gate=false!==strpos($v22,'HE_V22_Research_Guard::public_surface_eligible( $row )');
v2410_ok(($legacy_public_gate||$shared_public_gate) && false!==strpos($v22,'X-Robots-Tag: noindex, nofollow, noarchive'),'R1 research permanent-ID route lacks fail-closed governed public eligibility');
v2410_ok(false!==strpos($v22,'INSERT INTO {$reviews}') && false!==strpos($v22,'WHERE r.id=%d AND r.row_version=%d') && false!==strpos($v22,'changed while the review decision was being stored'),'R2 research review decision is not atomically bound to expected row version');
$domain=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v2410_ok(substr_count($v22,'case_governance_failed')>=1 && false!==strpos($domain,'case_governance_failed') && false!==strpos($domain,'case_consent_verified') && false!==strpos($domain,'case_anonymized'),'R3 successful-case release can proceed without authoritative consent/anonymization flags');
$integrity=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-integrity.php');
v2410_ok(false!==strpos($integrity,'$object_permission = HE_V2_Auth::rest_permission') && false!==strpos($integrity,"'file06-integrity-apply'") && false!==strpos($integrity,'return self::finish( $reservation, $object_permission )'),'R4 early integrity interceptor bypasses object-bound publish authorization');
v2410_ok(false!==strpos($integrity,"entry_integrity_transaction_start_failed") && false!==strpos($integrity,"integrity-commit-failed") && false!==strpos($integrity,"entry_integrity_commit_failed"),'R5 entry integrity transaction start/commit failures are not fail-closed');
v2410_ok(false!==strpos($v22,'$object_permission = HE_V2_Auth::rest_permission') && false!==strpos($v22,"'file06-research-integrity-apply'") && false!==strpos($v22,'return self::mutation_finish( $reservation, $object_permission, 200 )'),'R6 research integrity apply is authorized globally instead of against its research object');
$domain=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v2410_ok(false!==strpos($domain,'INSERT INTO {$reviews}') && false!==strpos($domain,'WHERE c.id=%d AND c.row_version=%d') && false!==strpos($domain,'changed while the review decision was being stored'),'R7 entry review decision is not atomically bound to expected row version');
v2410_ok(false!==strpos($integrity,"'file06-integrity-transition'") && false!==strpos($integrity,'HE_V2_Auth::CAP_REVIEW, $post_id') && false!==strpos($integrity,'The governed integrity subject is not available.'),'R8 integrity state transitions are authorized globally instead of against their governed subject');
v2410_ok(false!==strpos($domain,'$current_hash = HE_V22_Governance::entry_content_hash') && false!==strpos($domain,'AND content_hash=%s AND reviewer_id<>%d') && false!==strpos($domain,'he_fresh_independent_review_required'),'R9 owner transition command accepts stale historical approval reviews when REST preflight is bypassed');
$bootstrap=v2410_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v2410_read($root.'/homeopathy-encyclopedia/readme.txt');
$runall=v2410_read($root.'/tests/run-all.sh');
v2410_ok(false!==strpos($bootstrap,' * Version: 2.4.10') && false!==strpos($bootstrap,"define( 'HE_VERSION', '2.4.10' );") && false!==strpos($bootstrap,"define( 'HE_CONTRACT_VERSION', '2.4.10' );") && false!==strpos($bootstrap,"'future_hardening_version'=>'2.4.10'"),'R10 runtime/contract/future hardening release truth is not aligned to v2.4.10');
v2410_ok(false!==strpos($readme,'Stable tag: 2.4.10'),'R10 plugin stable tag is not v2.4.10');
v2410_ok(false!==strpos($runall,'v2410-eleventh-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.10-a.zip') && false!==strpos($runall,'file06-v2.4.10-b.zip'),'R10 aggregate/package truth is not aligned to v2.4.10');
/*__V2410_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.10 eleventh-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.10 eleventh-review regressions: PASS\n";

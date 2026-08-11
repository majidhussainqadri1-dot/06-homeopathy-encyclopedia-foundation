<?php
/** File 06 v2.4.10 eleventh fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v2410_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v2410_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$v22=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v2410_ok(false!==strpos($v22,'$public_eligible = $row') && false!==strpos($v22,'case_consent_verified') && false!==strpos($v22,'case_anonymized') && false!==strpos($v22,'X-Robots-Tag: noindex, nofollow, noarchive'),'R1 research permanent-ID route can render restricted/unconsented research content');
v2410_ok(false!==strpos($v22,'INSERT INTO {$reviews}') && false!==strpos($v22,'WHERE r.id=%d AND r.row_version=%d') && false!==strpos($v22,'changed while the review decision was being stored'),'R2 research review decision is not atomically bound to expected row version');
$domain=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v2410_ok(substr_count($v22,'case_governance_failed')>=1 && false!==strpos($domain,'case_governance_failed') && false!==strpos($domain,'case_consent_verified') && false!==strpos($domain,'case_anonymized'),'R3 successful-case release can proceed without authoritative consent/anonymization flags');
$integrity=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-integrity.php');
v2410_ok(false!==strpos($integrity,'$object_permission = HE_V2_Auth::rest_permission') && false!==strpos($integrity,"'file06-integrity-apply'") && false!==strpos($integrity,'return self::finish( $reservation, $object_permission )'),'R4 early integrity interceptor bypasses object-bound publish authorization');
v2410_ok(false!==strpos($integrity,"entry_integrity_transaction_start_failed") && false!==strpos($integrity,"integrity-commit-failed") && false!==strpos($integrity,"entry_integrity_commit_failed"),'R5 entry integrity transaction start/commit failures are not fail-closed');
/*__V2410_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.10 eleventh-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.10 eleventh-review regressions: PASS\n";

<?php
/** File 06 v2.4.11 twelfth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();$upto=(int)(getenv('V2411_ROUND')?:10);
function v2411_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v2411_ok($round,$ok,$m){global $fail,$upto;if($round<=$upto&&!$ok)$fail[]="R{$round} {$m}";}
$guard=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-research-guard.php');
$gov=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$public=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-public-guard.php');
$browse=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-research-browse.php');
$watch=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-watchlist.php');
$corePrivacy=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-privacy.php');
$futurePrivacy=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v24-future-privacy.php');
$authoring=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php');
$lang=v2411_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php');
$bootstrap=v2411_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v2411_read($root.'/homeopathy-encyclopedia/readme.txt');
$runall=v2411_read($root.'/tests/run-all.sh');
v2411_ok(1,false!==strpos($guard,'public static function public_surface_eligible')&&false!==strpos($guard,"'dataset' ===")&&false!==strpos($guard,"array( 'restricted','highly-restricted' )")&&false!==strpos($gov,'HE_V22_Research_Guard::public_surface_eligible( $row )'),'dataset metadata permanent-ID route still conflicts with private-by-default dataset law');
v2411_ok(2,false!==strpos($public,'HE_V22_Research_Guard::public_surface_eligible( $row )')&&false!==strpos($public,'he_public_research.record_type=%s')&&false!==strpos($public,'he_public_research.data_class IN (%s,%s)'),'WordPress research query/title/excerpt surfaces do not enforce nuanced public eligibility');
v2411_ok(3,false!==strpos($browse,'return HE_V22_Research_Guard::public_surface_eligible( $row );'),'REST research browse does not share canonical public eligibility');
v2411_ok(4,false!==strpos($watch,"SELECT * FROM ' . HE_V2_Schema::table( 'research' )")&&false!==strpos($watch,'HE_V22_Research_Guard::public_surface_eligible( $row )'),'watchlist can resolve restricted/non-public research outside public eligibility');
v2411_ok(5,false!==strpos($corePrivacy,"object_type='user' AND object_id=%s")&&false!==strpos($corePrivacy,"File06PrivacyErasureCompleted.v1', 'privacy-request', 0"),'core erasure completion can reintroduce erased user object identity');
v2411_ok(6,false!==strpos($futurePrivacy,'future_privacy_event_object_deidentification_failed')&&false!==strpos($futurePrivacy,"File06FuturePrivacyErasureCompleted.v1', 'privacy-request', 0"),'Future erasure completion can reintroduce erased user object identity');
v2411_ok(7,false!==strpos($guard,'research_completeness_concurrency_conflict')&&false!==strpos($guard,'WHERE id=%d AND row_version=%d'),'research completeness wp-admin writer lacks row-version CAS');
v2411_ok(8,false!==strpos($guard,'$out[] = $item;')&&false!==strpos($authoring,'if ( is_array( $item ) )')&&false!==strpos($authoring,"\$item = \$item['name'] ?? '';")&&false!==strpos($authoring,'$investigators = self::investigators('),'legacy nested investigator JSON can cause Array-to-string runtime warnings');
v2411_ok(9,false!==strpos($lang,'the canonical concept language was restored')&&false!==strpos($lang,"delete_post_meta( \$object_id, '_he_language' )"),'invalid source-language meta can diverge from canonical concept language');
v2411_ok(10,false!==strpos($lang,'$public_source_version')&&substr_count($lang,"'source_version' => \$public_source_version")>=2&&!preg_match("/'source_version'\s*=>\s*\(int\)\s*\$(?:row\['source_version'\]|concept\['current_version'\])/",$lang),'public translation DTO exposes internal version-row identifiers');
v2411_ok(10,preg_match('/ \* Version: 2\.4\.(?:11|12|13|14|15)/',$bootstrap)&&preg_match("/HE_VERSION', '2\.4\.(?:11|12|13|14|15)/",$bootstrap)&&preg_match("/HE_CONTRACT_VERSION', '2\.4\.(?:11|12|13|14|15)/",$bootstrap)&&false!==strpos($bootstrap,"'future_hardening_version'=>"),'historical v2.4.11 release controls do not tolerate a later current v2.4.x candidate');
v2411_ok(10,false!==strpos($runall,'v2411-twelfth-ten-round-regressions.php'),'historical twelfth-cycle regression suite is no longer wired into aggregate QA');
if($fail){fwrite(STDERR,"File 06 v2.4.11 twelfth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.11 twelfth-review regressions through R{$upto}: PASS\n";

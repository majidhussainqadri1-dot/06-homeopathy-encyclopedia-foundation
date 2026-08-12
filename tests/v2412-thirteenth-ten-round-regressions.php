<?php
/** File 06 v2.4.12 thirteenth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();$upto=(int)(getenv('V2412_ROUND')?:10);
function v2412_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v2412_ok($round,$ok,$m){global $fail,$upto;if($round<=$upto&&!$ok)$fail[]="R{$round} {$m}";}
$domain=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
$schedule=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-schedule.php');
$gov=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$lang=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php');
$integrations=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
$authoring=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php');
$third=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-third-audit.php');
$public=v2412_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-public-guard.php');
$bootstrap=v2412_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v2412_read($root.'/homeopathy-encyclopedia/readme.txt');
$runall=v2412_read($root.'/tests/run-all.sh');

v2412_ok(1,false!==strpos($domain,'private static function public_structured_value')&&false!==strpos($domain,'self::public_structured_value( $item, $depth + 1 )')&&false===strpos($domain,'array_map( \'sanitize_text_field\', $structured[ $key ] )'),'nested public structured fields are not recursively sanitized');
v2412_ok(2,false!==strpos($schedule,'$invalidated_row = $wpdb->query')&&false!==strpos($schedule,"'validation-failed-before-publication'")&&false!==strpos($schedule,'1 === (int) $invalidated_row'),'validation-driven scheduled invalidation can clear schedule metadata without winning its CAS');
v2412_ok(3,false!==strpos($gov,'secure_merge_transaction_start_failed')&&false!==strpos($gov,"throw new RuntimeException( 'merge-commit-failed' )")&&false!==strpos($gov,'secure_merge_commit_failed'),'governed merge does not fail closed on transaction start/commit uncertainty');
v2412_ok(4,false!==strpos($gov,'$alias_deleted = $wpdb->delete')&&false!==strpos($gov,'$alias_updated = $wpdb->update')&&false!==strpos($gov,'merge-alias-write-failed')&&false!==strpos($gov,'merge-index-write-failed'),'governed merge can silently continue after alias/index persistence failure');
v2412_ok(5,false!==strpos($lang,'source_language_persistence_failed')&&false!==strpos($lang,'source_language_domain_cas_failed')&&false!==strpos($lang,'WHERE id=%d AND language=%s'),'source-language meta/domain convergence is not fail-closed and CAS-bound');
v2412_ok(6,false!==strpos($domain,'public static function minimize_event_payload')&&false!==strpos($domain,"'[redacted-email]'")&&false!==strpos($integrations,'HE_V2_Domain::minimize_event_payload'),'local/consumed event payloads are not recursively privacy-minimized');
v2412_ok(7,false!==strpos($domain,'public static function sanitize_text_list')&&false!==strpos($domain,"isset( \$value['statement'] )")&&substr_count($domain,'self::sanitize_text_list(')>=2&&false!==strpos($authoring,'HE_V2_Domain::sanitize_text_list( $value )')&&(substr_count($third,'HE_V2_Domain::sanitize_text_list')>=2||false!==strpos($third,'it must never mutate state after idempotency finalization')),'nested investigator/conflict shapes can trigger array-to-string warnings or inconsistent governance records');
v2412_ok(8,false!==strpos($public,"add_filter( 'posts_results', array( __CLASS__, 'research_public_query_results' )")&&false!==strpos($public,'self::is_public_row( $row )'),'WordPress research results can expose rows that pass coarse SQL but fail canonical public eligibility');
v2412_ok(9,false!==strpos($domain,'private static $merge_resolution_stack')&&false!==strpos($domain,'concept_merge_cycle_detected')&&false!==strpos($domain,'count( self::$merge_resolution_stack ) >= 32'),'canonical concept resolution is not bounded against corrupt merge cycles/chains');
v2412_ok(10,preg_match('/ \* Version: 2\.4\.(?:12|13|14|15|16|17)/',$bootstrap)&&preg_match("/HE_VERSION', '2\.4\.(?:12|13|14|15|16|17)/",$bootstrap)&&preg_match("/HE_CONTRACT_VERSION', '2\.4\.(?:12|13|14|15|16|17)/",$bootstrap)&&false!==strpos($bootstrap,"'future_hardening_version'=>"),'historical v2.4.12 release controls do not tolerate a later current v2.4.x candidate');
v2412_ok(10,false!==strpos($runall,'v2412-thirteenth-ten-round-regressions.php'),'historical thirteenth-cycle regression suite is no longer wired into aggregate QA');
if($fail){fwrite(STDERR,"File 06 v2.4.12 thirteenth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.12 thirteenth-review regressions through R{$upto}: PASS\n";

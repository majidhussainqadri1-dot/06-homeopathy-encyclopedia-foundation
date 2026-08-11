<?php
/** File 06 v2.4.13 fourteenth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();$upto=(int)(getenv('V2413_ROUND')?:10);
function v2413_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v2413_ok($round,$ok,$m){global $fail,$upto;if($round<=$upto&&!$ok)$fail[]="R{$round} {$m}";}
$domain=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
$api=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
$gov=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$authoring=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php');
$runtime=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-runtime-corrections.php');
$firstsave=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-admin-first-save.php');
$admin=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-admin.php');
$future=v2413_read($root.'/homeopathy-encyclopedia/includes/class-he-v24-future-schema.php');
$bootstrap=v2413_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v2413_read($root.'/homeopathy-encyclopedia/readme.txt');
$runall=v2413_read($root.'/tests/run-all.sh');

v2413_ok(1,false!==strpos($domain,'private static function sanitize_structured_value')&&false!==strpos($domain,'self::sanitize_structured_value( $item, $depth + 1 )')&&false===strpos($domain,'array_map( \'sanitize_text_field\', $fields[ $key ] )'),'structured writes remain shallow-array sanitized');
v2413_ok(2,false!==strpos($domain,'research_create_persistence_failed')&&false!==strpos($domain,'private static function rollback_new_research')&&false!==strpos($domain,'SELECT public_id,post_id,record_type,title,question,protocol'),'research creation can report success without verified domain persistence/compensation');
v2413_ok(3,false!==strpos($domain,'HE_V22_Research_Guard::public_surface_eligible( $row )')&&false!==strpos($api,"status IN ('published','corrected','retracted')"),'public research DTO/browse does not share canonical governed eligibility');
if($upto>=4){preg_match('/public static function merge_concepts\(.*?\n\t\}/s',$domain,$m);$merge=$m[0]??'';v2413_ok(4,false!==strpos($merge,'HE_V22_Governance::secure_merge')&&false===strpos($merge,'START TRANSACTION')&&false===strpos($merge,'$wpdb->delete'),'REST-reachable domain merge still duplicates unsafe merge transaction logic');}
v2413_ok(5,false!==strpos($domain,'public static function normalize_conflicts')&&false!==strpos($domain,"'conflicts_json' => wp_json_encode( \$conflicts")&&false!==strpos($domain,'$investigators = self::sanitize_text_list')&&false!==strpos($authoring,'HE_V2_Domain::normalize_conflicts')&&false===strpos($authoring,'array_map( \'sanitize_text_field\', (array) $payload[\'conflicts\']'),'research conflict/investigator governance is not canonical before success');
if($upto>=6){preg_match('/public static function verify_research_create_normalization\(.*?\n\t\}/s',$runtime,$m);$verify=$m[0]??'';v2413_ok(6,false===strpos($runtime,"add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'verify_research_create_normalization'")&&false===strpos($verify,'UPDATE ')&&false!==strpos($verify,'must never mutate state after route/idempotency success'),'post-success REST normalization can still mutate domain state after idempotency finalization');}
v2413_ok(7,false!==strpos($authoring,'research_composer_rollback_start_failed')&&false!==strpos($authoring,"throw new RuntimeException( 'research-rollback-commit-failed' )"),'research composer rollback does not verify transaction start/commit');
v2413_ok(8,false!==strpos($runtime,'composer_rollback_start_failed')&&false!==strpos($runtime,'relation-delete-failed')&&false!==strpos($runtime,'child-delete-failed')&&false!==strpos($runtime,"throw new RuntimeException( 'entry-rollback-commit-failed' )"),'entry composer rollback can silently continue after transaction/child-write failure');
v2413_ok(9,false!==strpos($firstsave,'WHERE id=%d AND row_version=%d')&&false!==strpos($firstsave,'research_first_save_cas_failed')&&false!==strpos($admin,'legacy_research_admin_cas_failed')&&false!==strpos($gov,"status='published' AND row_version=%d")&&false!==strpos($future,'impact_queue_ack_write_failed')&&false!==strpos($future,'impact_queue_dead_letter_write_failed')&&false!==strpos($future,'impact_queue_retry_write_failed'),'research admin/normalization or Future impact transitions remain unchecked/non-CAS');
v2413_ok(10,false!==strpos($bootstrap,' * Version: 2.4.13')&&false!==strpos($bootstrap,"define( 'HE_VERSION', '2.4.13' );")&&false!==strpos($bootstrap,"define( 'HE_CONTRACT_VERSION', '2.4.13' );")&&false!==strpos($bootstrap,"'future_hardening_version'=>'2.4.13'"),'runtime/contract/future hardening truth is not v2.4.13');
v2413_ok(10,false!==strpos($readme,'Stable tag: 2.4.13')&&false!==strpos($runall,'v2413-fourteenth-ten-round-regressions.php')&&false!==strpos($runall,'file06-v2.4.13-a.zip')&&false!==strpos($runall,'All File 06 v2.4.13 automated checks'),'aggregate/package release truth is not v2.4.13');
if($fail){fwrite(STDERR,"File 06 v2.4.13 fourteenth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.13 fourteenth-review regressions through R{$upto}: PASS\n";

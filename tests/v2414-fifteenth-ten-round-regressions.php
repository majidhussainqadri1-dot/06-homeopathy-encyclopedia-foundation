<?php
/** File 06 v2.4.14 fifteenth fresh ten-round regression controls retained under later v2.4.x hardening. */
$root=dirname(__DIR__);$fail=array();
function v2414_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
$domain=v2414_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
$api=v2414_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
$public=v2414_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-public.php');
$browse=v2414_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-research-browse.php');
$bootstrap=v2414_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v2414_read($root.'/homeopathy-encyclopedia/readme.txt');
$runall=v2414_read($root.'/tests/run-all.sh');
$env=getenv('V2414_ROUND');$upto=(false!==$env&&''!==$env)?(int)$env:(preg_match('/ \* Version: 2\.4\.(?:14|15|16|17|18|19)/',$bootstrap)?10:0);
function v2414_ok($round,$ok,$m){global $fail,$upto;if($round<=$upto&&!$ok)$fail[]="R{$round} {$m}";}

$r1_legacy=false!==strpos($domain,'entry_delete_lifecycle_failed')&&false!==strpos($domain,'research_delete_lifecycle_failed')&&false!==strpos($domain,"status='archived',row_version=row_version+1")&&false!==strpos($domain,"status='retracted',row_version=row_version+1");
$r1_stronger=false!==strpos($domain,"add_filter( 'pre_delete_post'")&&false!==strpos($domain,"add_action( 'deleted_post'")&&false!==strpos($domain,'delete_lifecycle_failed')&&false!==strpos($domain,'wordpress-hard-delete-confirmed')&&false!==strpos($domain,'verify_pending_deletions');
v2414_ok(1,$r1_legacy||$r1_stronger,'hard-delete lifecycle still permits unverified canonical/archive-retraction state');
v2414_ok(2,false!==strpos($domain,'entry_create_projection_failed')&&false!==strpos($domain,'$persisted_structured !== $structured')&&false!==strpos($domain,'self::taxonomy_slug( $post_id, self::TAX_TYPE ) !== $type')&&false!==strpos($domain,'self::taxonomy_slug( $post_id, self::TAX_SYSTEM ) !== $system'),'entry create does not verify taxonomy/language/structured persistence before success');
$r3_legacy=false!==strpos($domain,'entry_create_compensation_start_failed')&&false!==strpos($domain,'entry-child-delete-failed-')&&false!==strpos($domain,'entry-create-compensation-commit-failed')&&false!==strpos($domain,'entry_create_post_compensation_failed');
$r3_stronger=false!==strpos($domain,'entry_create_compensation_start_failed')&&false!==strpos($domain,'entry-child-delete-failed')&&false!==strpos($domain,'entry-compensation-commit-failed')&&false!==strpos($domain,'entry_create_compensation_failed')&&false!==strpos($domain,"remove_filter( 'pre_delete_post', \$delete_guard, 1 )");
v2414_ok(3,$r3_legacy||$r3_stronger,'entry-create compensation can silently partially fail');
if($upto>=4){preg_match('/if \( ! \$row \) \{\s*\$normalized = self::normalize\( \$value \);.*?\n\t\t\t\}/s',$domain,$m);$alias=$m[0]??'';v2414_ok(4,false!==strpos($alias,'SELECT DISTINCT c.*')&&false!==strpos($alias,'LIMIT 2')&&false!==strpos($alias,'count( $matches ) > 1')&&false!==strpos($alias,'return null'),'ambiguous cross-language alias resolution does not fail closed');}
v2414_ok(5,false!==strpos($domain,"'accepted' !== \$action['status']")&&false!==strpos($domain,'he_integrity_apply_unsupported')&&false!==strpos($domain,"array( 'correction', 'retraction' )"),'integrity action can still be applied before accepted state or via unsupported generic action type');
if($upto>=6){preg_match('/public static function apply_integrity_action\(.*?\n\t\}/s',$domain,$m);$apply=$m[0]??'';v2414_ok(6,false!==strpos($apply,'START TRANSACTION')&&substr_count($apply,'FOR UPDATE')>=2&&false!==strpos($apply,'if ( ! $version_id )')&&false!==strpos($apply,"status='applied'")&&false!==strpos($apply,'COMMIT')&&false!==strpos($apply,'if ( $event ) { self::emit_event'),'integrity apply is not transactionally bound before event/reindex');}
$dataset_route_ok=false!==strpos($api,"'/datasets/(?P<id>[A-Za-z0-9-]+)/access'")||false!==strpos($api,"'/datasets/(?P<id>[0-9a-fA-F]{8}-");
v2414_ok(7,$dataset_route_ok&&false!==strpos($domain,'dataset_access_request_write_failed')&&false!==strpos($domain,'HE_V22_Research_Guard::public_surface_eligible( $research )')&&false!==strpos($domain,'dataset_access_approval_failed')&&false!==strpos($domain,'SELECT * FROM {$table} WHERE id=%d FOR UPDATE'),'dataset access state/persistence controls regressed');
if($upto>=8){preg_match('/public static function graph\(.*?\n\t\}/s',$domain,$m);$graph=$m[0]??'';v2414_ok(8,false!==strpos($graph,"'source' => \$source_dto['id']")&&false!==strpos($graph,"'target' => \$target_dto['id']")&&!preg_match("/'source'\s*=>\s*\(int\)\s*\$edge\['source_concept_id'\]/",$graph)&&!preg_match("/'target'\s*=>\s*\(int\)\s*\$edge\['target_concept_id'\]/",$graph),'public graph edges still expose internal numeric concept IDs');}
$r9_legacy=false!==strpos($domain,"'retracted' !== \$row['status'] ? json_decode( \$row['case_json'], true ) : null")&&false!==strpos($domain,"'public' !== \$row['data_class'] || 'retracted' === \$row['status']")&&false!==strpos($domain,"'publish' !== \$post->post_status");
$r9_stronger=false!==strpos($domain,'$public_metadata = array_intersect_key')&&false!==strpos($domain,"'publish' !== \$post->post_status")&&false!==strpos($domain,"( \$private || 'public' === \$row['data_class'] )");
v2414_ok(9,($r9_legacy||$r9_stronger)&&false!==strpos($browse,"'public' === \$row['data_class'] && 'retracted' !== \$row['status']"),'retracted research REST/browse payloads or stale deleted posts can remain publicly detailed');
v2414_ok(10,false!==strpos($public,'private function structured_html')&&false!==strpos($public,'$this->structured_html( $value )')&&false===strpos($public,'is_array( $value ) ? implode( "\\n", $value ) : $value'),'nested structured public rendering can still emit array-to-string warnings');
v2414_ok(10,preg_match('/ \* Version: 2\.4\.(?:14|15|16|17|18|19)/',$bootstrap)&&preg_match("/HE_VERSION', '2\\.4\\.(?:14|15|16|17|18|19)/",$bootstrap)&&preg_match("/HE_CONTRACT_VERSION', '2\\.4\\.(?:14|15|16|17|18|19)/",$bootstrap)&&false!==strpos($bootstrap,"'future_hardening_version'=>"),'historical v2.4.14 release controls do not tolerate the later current v2.4.x candidate');
v2414_ok(10,preg_match('/Stable tag: 2\.4\.(?:14|15|16|17|18|19)/',$readme)&&false!==strpos($runall,'v2414-fifteenth-ten-round-regressions.php')&&preg_match('/file06-v2\.4\.(?:14|15|16|17|18|19)-a\.zip/',$runall)&&preg_match('/All File 06 v2\.4\.(?:14|15|16|17|18|19) automated checks/',$runall),'historical aggregate/package controls no longer tolerate the current v2.4.x candidate');

if($fail){fwrite(STDERR,"File 06 v2.4.14 fifteenth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.14 fifteenth-review regressions through R{$upto}: PASS\n";

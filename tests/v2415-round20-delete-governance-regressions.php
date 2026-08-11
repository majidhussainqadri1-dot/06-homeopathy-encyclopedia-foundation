<?php
/** R20 reconciliation between governed hard-delete prevention and pristine composer compensation. */
$root=dirname(__DIR__);$p=$root.'/homeopathy-encyclopedia/includes';$fail=array();
function v2415del_read($f){$v=file_get_contents($f);if(false===$v)throw new RuntimeException($f);return $v;}
function v2415del_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$third=v2415del_read($p.'/class-he-v242-third-audit.php');
$runtime=v2415del_read($p.'/class-he-v242-runtime-corrections.php');
$domain=v2415del_read($p.'/class-he-v2-domain.php');
v2415del_ok(false!==strpos($third,"add_filter( 'pre_delete_post', array( __CLASS__, 'guard_hard_delete' ), 1, 3 )")&&false!==strpos($third,'return $exists ? false : $delete'),'canonical WordPress hard-delete guard is not enforced before lower-priority lifecycle handlers');
v2415del_ok(false!==strpos($runtime,"$domain_pre_delete = array( 'HE_V2_Domain', 'pre_delete_post' )")&&false!==strpos($runtime,"$domain_deleted = array( 'HE_V2_Domain', 'on_deleted_post' )"),'pristine rollback does not identify the current domain deletion lifecycle hooks');
v2415del_ok(false!==strpos($runtime,"remove_filter( 'pre_delete_post', $delete_guard, 1 )")&&false!==strpos($runtime,"remove_filter( 'pre_delete_post', $domain_pre_delete, 10 )")&&false!==strpos($runtime,"remove_action( 'deleted_post', $domain_deleted, 10 )"),'pristine rollback does not suppress both governed hard-delete prevention and archive/retraction lifecycle hooks before its physical compensation delete');
v2415del_ok(false!==strpos($runtime,"add_filter( 'pre_delete_post', $delete_guard, 1, 3 )")&&false!==strpos($runtime,"add_filter( 'pre_delete_post', $domain_pre_delete, 10, 3 )")&&false!==strpos($runtime,"add_action( 'deleted_post', $domain_deleted, 10, 2 )"),'pristine rollback does not restore deletion governance hooks after compensation');
v2415del_ok(false===strpos($runtime,"array( 'HE_V2_Domain', 'on_delete_post' )")&&false===strpos($runtime,"add_action( 'before_delete_post', $domain_delete"),'stale pre-v2.4.15 deletion callback wiring remains in runtime rollback');
v2415del_ok(false!==strpos($domain,"add_filter( 'pre_delete_post', array( __CLASS__, 'pre_delete_post' ), 10, 3 )")&&false!==strpos($domain,"add_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 10, 2 )"),'lower-level owner lifecycle hooks are not registered consistently');
if($fail){fwrite(STDERR,"File 06 v2.4.15 R20 delete-governance regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.15 R20 delete-governance regressions: PASS\n";

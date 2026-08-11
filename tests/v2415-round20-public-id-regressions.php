<?php
/** Final R20 public-identifier contract for File 06 v2.4.15. */
$root=dirname(__DIR__);$p=$root.'/homeopathy-encyclopedia';$fail=array();
function v2415r20_read($f){$v=file_get_contents($f);if(false===$v)throw new RuntimeException($f);return $v;}
function v2415r20_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$api=v2415r20_read($p.'/includes/class-he-v2-api.php');
$domain=v2415r20_read($p.'/includes/class-he-v2-domain.php');
$bootstrap=v2415r20_read($p.'/homeopathy-encyclopedia.php');
v2415r20_ok(false!==strpos($api,"'/dataset-access/(?P<id>[A-Za-z0-9_-]+\\.[a-f0-9]{64})/approve'"),'dataset-access approval route is not an opaque signed-token route');
v2415r20_ok(false===strpos($api,"'/dataset-access/(?P<id>\\d+)/approve'"),'raw numeric dataset-access approval route remains exposed');
v2415r20_ok(false!==strpos($api,"HE_V2_Domain::approve_dataset_access( sanitize_text_field( (string) \$request['id'] )"),'approval callback does not preserve opaque request token');
v2415r20_ok(false!==strpos($domain,"decode_public_cursor( 'dataset-access', \$access_identifier )")&&false!==strpos($domain,"encode_public_cursor( 'dataset-access', (int) \$existing['id'] )")&&false!==strpos($domain,"encode_public_cursor( 'dataset-access', \$access_id )"),'dataset-access request/approval path is not tokenized end-to-end');
v2415r20_ok(false!==strpos($domain,"'request_id' => \$request_id")&&false!==strpos($domain,"'status' => 'requested'"),'dataset-access request does not return its public opaque request identifier');
v2415r20_ok(false!==strpos($bootstrap,"'dataset_access_approval_identifier'=>'opaque-signed-token'"),'contract descriptor does not state the dataset-access approval identifier boundary');
if($fail){fwrite(STDERR,"File 06 v2.4.15 R20 public-ID regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.15 R20 public-ID regressions: PASS\n";

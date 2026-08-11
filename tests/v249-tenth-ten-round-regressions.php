<?php
/** File 06 v2.4.9 tenth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v249_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v249_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
$api=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v249_ok(false!==strpos($domain,'he_version_conflict') && false!==strpos($domain,'content_hash') && false!==strpos($domain,'reviewed_row_version'),'R1 entry review is not bound at the owning insert to the expected reviewed state');
v249_ok(false!==strpos($api,'expected_version') && false===strpos($gov,'self::bind_latest_entry_review( $row )'),'R1 after-callback rebind can attach a review to a newer concurrent entry state');
$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v249_ok(false!==strpos($gov,'version_id=0 OR version_id=%d'),'R2 entry review hash includes superseded historical references instead of current/draft provenance only');
/*__V249_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.9 tenth-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.9 tenth-review regressions: PASS
";

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
$api=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v249_ok(substr_count($api,'he_idempotency_finalize_failed')>=2 && substr_count($gov,'he_idempotency_finalize_failed')>=2,'R3 mutation helpers ignore idempotency finalization failure and can report unsafe success');
v249_ok(false!==strpos($domain,'idempotency_finish_stale_lease'),'R3 stale/reclaimed idempotency finalization is not surfaced as an operational failure');
$r4files=array('class-he-v24-future-api.php','class-he-v241-governance.php','class-he-v241-research-governance.php','class-he-v24-future-review-guard.php','class-he-v22-integrity.php','class-he-v242-watchlist.php','class-he-v242-multilingual.php');
foreach($r4files as $r4file){$src=v249_read($root.'/homeopathy-encyclopedia/includes/'.$r4file);v249_ok(false!==strpos($src,'he_idempotency_finalize_failed'),'R4 idempotency finalization failure remains silently ignored in '.$r4file);}
/*__V249_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.9 tenth-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.9 tenth-review regressions: PASS
";

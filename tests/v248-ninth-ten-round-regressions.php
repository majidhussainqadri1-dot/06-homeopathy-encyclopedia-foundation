<?php
/** File 06 v2.4.8 ninth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v248_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v248_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$domain=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v248_ok(false!==strpos($domain,"rate_limit_write_failed") && false!==strpos($domain,"rate_limit_read_failed"),'R1 rate limiter does not fail closed on DB write/read failure');
$domain=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v248_ok(false!==strpos($domain,'private static $idempotency_leases') && false!==strpos($domain,'AND response_code=0 AND created_at=%s') && false!==strpos($domain,'reclaimed') && false!==strpos($domain,'idempotency_finish_failed'),'R2 reclaimed idempotency reservations are not fenced against stale-worker completion');
$integrations=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
v248_ok(false!==strpos($integrations,"status='processing'") && false!==strpos($integrations,"stale-processing-recovered") && false!==strpos($integrations,"AND status=%s AND attempts=%d"),'R3 outbox delivery lacks a CAS processing lease and stale-worker recovery');
$domain=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v248_ok(false!==strpos($domain,'SELECT @@session.in_transaction') && false!==strpos($domain,'event_outbox_atomic_write_failed') && false!==strpos($domain,"ROLLBACK") && false!==strpos($domain,"COMMIT"),'R4 event/outbox pair is not persisted atomically');
/*__V248_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.8 ninth-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.8 ninth-review regressions: PASS
";

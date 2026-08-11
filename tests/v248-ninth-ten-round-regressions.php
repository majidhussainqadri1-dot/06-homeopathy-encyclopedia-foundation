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
$v22=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v248_ok(false!==strpos($v22,'INSERT IGNORE INTO {$table}') && false!==strpos($v22,'outbox_reconciliation_write_failed'),'R5 outbox reconciliation still has a check-then-insert duplicate race');
$v22=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v248_ok(false!==strpos($v22,'failed_concept_id') && false!==strpos($v22,'reindex_row_failed') && false!==strpos($v22,'update_option( self::REINDEX_CURSOR, $last, false )'),'R6 reindex cursor can advance past an index persistence failure');
$v22=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v248_ok(false===strpos($v22,"if ( current_user_can( 'activate_plugins' ) )") && false!==strpos($v22,"HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR, 0, 'file06-background-maintenance' )"),'R7 admin-init maintenance bypasses File00-backed File06 repair authorization');
$integrations=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
v248_ok(false!==strpos($integrations,'INSERT IGNORE INTO {$table}') && false!==strpos($integrations,'consumed_event_write_failed') && false===strpos($integrations,"SELECT id FROM ' . HE_V2_Schema::table( 'events' ) . ' WHERE event_id=%s"),'R8 consumed-event idempotency still uses a check-then-insert race');
$guard=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-public-guard.php');
v248_ok(false!==strpos($guard,'research_public_query_where') && false!==strpos($guard,'he_public_research.status IN') && false===strpos($guard,"get_post_type( $post_id ) || ! is_singular") && false===strpos($guard,"$post->post_type || ! is_singular"),'R9 WordPress archive/search paths can expose non-public domain research or stale WP title/excerpt metadata');
/*__V248_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.8 ninth-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.8 ninth-review regressions: PASS
";

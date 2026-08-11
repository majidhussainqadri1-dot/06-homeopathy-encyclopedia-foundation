#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
TEST = ROOT / 'tests' / 'v248-ninth-ten-round-regressions.php'
MARKER = '/*__V248_MORE__*/'

def p(rel): return ROOT / rel

def read(rel): return p(rel).read_text()

def write(rel, text): p(rel).write_text(text)

def replace_once(rel, old, new):
    s = read(rel)
    if old not in s:
        raise SystemExit(f'marker missing in {rel}: {old[:160]}')
    write(rel, s.replace(old, new, 1))

def replace_block(rel, start, end, new):
    s = read(rel)
    a = s.find(start)
    if a < 0: raise SystemExit(f'start marker missing in {rel}: {start}')
    b = s.find(end, a)
    if b < 0: raise SystemExit(f'end marker missing in {rel}: {end}')
    write(rel, s[:a] + new.rstrip() + '\n\n' + s[b:])

def init_test():
    if TEST.exists(): return
    TEST.write_text("""<?php
/** File 06 v2.4.8 ninth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v248_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v248_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
/*__V248_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.8 ninth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.8 ninth-review regressions: PASS\n";
""")

def append_test(block):
    init_test(); s=TEST.read_text()
    if MARKER not in s: raise SystemExit('v248 marker missing')
    TEST.write_text(s.replace(MARKER, block.rstrip()+'\n'+MARKER, 1))

def round1():
    rel='homeopathy-encyclopedia/includes/class-he-v2-domain.php'
    new='''\tpublic static function rate_allow( $key, $limit, $window_seconds ) {
\t\tglobal $wpdb;
\t\t$table = HE_V2_Schema::table( 'rate_limits' );
\t\t$key = hash( 'sha256', (string) $key );
\t\t$now = current_time( 'mysql', true );
\t\t$expiry = gmdate( 'Y-m-d H:i:s', time() + max( 1, absint( $window_seconds ) ) );
\t\t$write = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (rate_key,window_start,hit_count,expires_at) VALUES (%s,%s,1,%s) ON DUPLICATE KEY UPDATE hit_count=IF(expires_at<=UTC_TIMESTAMP(),1,hit_count+1),window_start=IF(expires_at<=UTC_TIMESTAMP(),VALUES(window_start),window_start),expires_at=IF(expires_at<=UTC_TIMESTAMP(),VALUES(expires_at),expires_at)", $key, $now, $expiry ) );
\t\tif ( false === $write ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'rate_limit_write_failed', 'The File 06 rate-limit counter could not be persisted; protected mutations are failing closed.' );
\t\t\treturn false;
\t\t}
\t\t$count = $wpdb->get_var( $wpdb->prepare( "SELECT hit_count FROM {$table} WHERE rate_key=%s", $key ) );
\t\tif ( null === $count || '' !== (string) $wpdb->last_error ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'rate_limit_read_failed', 'The File 06 rate-limit counter could not be verified; protected mutations are failing closed.' );
\t\t\treturn false;
\t\t}
\t\treturn (int) $count <= max( 1, absint( $limit ) );
\t}'''
    replace_block(rel, '\tpublic static function rate_allow(', '\tpublic static function publish_due(', new)
    append_test('''$domain=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v248_ok(false!==strpos($domain,"rate_limit_write_failed") && false!==strpos($domain,"rate_limit_read_failed"),'R1 rate limiter does not fail closed on DB write/read failure');''')

def round2():
    rel='homeopathy-encyclopedia/includes/class-he-v2-domain.php'
    replace_once(rel, "\tconst TAX_TOPIC = 'he_topic';\n", "\tconst TAX_TOPIC = 'he_topic';\n\tprivate static $idempotency_leases = array();\n")
    new='''\tpublic static function idempotent_begin( $actor_id, $operation, $key, $request_body ) {
\t\tglobal $wpdb;
\t\t$key = sanitize_text_field( $key );
\t\tif ( ! $key || strlen( $key ) < 8 || strlen( $key ) > 128 ) {
\t\t\treturn new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\t$request_hash = hash( 'sha256', wp_json_encode( self::canonicalize_idempotency_value( $request_body ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
\t\t$table = HE_V2_Schema::table( 'idempotency' );
\t\t$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_id=%d AND operation=%s AND idempotency_key=%s", absint( $actor_id ), sanitize_key( $operation ), $key ), ARRAY_A );
\t\tif ( $row ) {
\t\t\tif ( ! hash_equals( $row['request_hash'], $request_hash ) ) {
\t\t\t\treturn new WP_Error( 'he_idempotency_conflict', __( 'This idempotency key was already used for a different request.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t\t}
\t\t\tif ( $row['response_code'] ) {
\t\t\t\treturn array( 'replay' => true, 'code' => (int) $row['response_code'], 'body' => json_decode( $row['response_json'], true ) );
\t\t\t}
\t\t\t$now = current_time( 'mysql', true );
\t\t\t$expiry = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
\t\t\t$reclaimed = $wpdb->query( $wpdb->prepare(
\t\t\t\t"UPDATE {$table} SET created_at=%s,expires_at=%s WHERE id=%d AND response_code=0 AND request_hash=%s AND created_at=%s AND created_at<=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)",
\t\t\t\t$now, $expiry, (int) $row['id'], $request_hash, (string) $row['created_at']
\t\t\t) );
\t\t\tif ( 1 === (int) $reclaimed ) {
\t\t\t\tself::$idempotency_leases[ (int) $row['id'] ] = $now;
\t\t\t\treturn array( 'replay' => false, 'id' => (int) $row['id'], 'reclaimed' => true );
\t\t\t}
\t\t\treturn new WP_Error( 'he_request_in_progress', __( 'An identical request is still being processed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\t$created_at = current_time( 'mysql', true );
\t\t$ok = $wpdb->insert( $table, array(
\t\t\t'actor_id' => absint( $actor_id ), 'operation' => sanitize_key( $operation ), 'idempotency_key' => $key, 'request_hash' => $request_hash,
\t\t\t'response_code' => 0, 'response_json' => '', 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'created_at' => $created_at,
\t\t), array( '%d','%s','%s','%s','%d','%s','%s','%s' ) );
\t\tif ( ! $ok ) {
\t\t\treturn new WP_Error( 'he_idempotency_write_failed', __( 'The request could not be reserved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t}
\t\t$id = (int) $wpdb->insert_id;
\t\tself::$idempotency_leases[ $id ] = $created_at;
\t\treturn array( 'replay' => false, 'id' => $id );
\t}

\tpublic static function idempotent_finish( $id, $code, $body ) {
\t\tglobal $wpdb;
\t\t$id = absint( $id );
\t\t$lease = isset( self::$idempotency_leases[ $id ] ) ? (string) self::$idempotency_leases[ $id ] : '';
\t\tif ( ! $id || ! $lease ) { return false; }
\t\t$updated = $wpdb->query( $wpdb->prepare(
\t\t\t'UPDATE ' . HE_V2_Schema::table( 'idempotency' ) . ' SET response_code=%d,response_json=%s WHERE id=%d AND response_code=0 AND created_at=%s',
\t\t\tabsint( $code ), wp_json_encode( $body ), $id, $lease
\t\t) );
\t\tunset( self::$idempotency_leases[ $id ] );
\t\tif ( false === $updated ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'idempotency_finish_failed', 'The reserved File 06 response could not be persisted.' );
\t\t\treturn false;
\t\t}
\t\treturn 1 === (int) $updated;
\t}'''
    replace_block(rel, '\tpublic static function idempotent_begin(', '\tpublic static function maintenance(', new)
    append_test('''$domain=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v248_ok(false!==strpos($domain,'private static $idempotency_leases') && false!==strpos($domain,"AND response_code=0 AND created_at=%s") && false!==strpos($domain,"self::$idempotency_leases[ (int) $row['id'] ] = $now"),'R2 reclaimed idempotency reservations are not fenced against stale-worker completion');''')

def round3():
    rel='homeopathy-encyclopedia/includes/class-he-v2-integrations.php'
    new='''\tpublic static function process_outbox( $limit = 50 ) {
\t\tglobal $wpdb;
\t\t$limit = min( 100, max( 1, absint( $limit ) ) );
\t\t$table = HE_V2_Schema::table( 'outbox' );
\t\t$wpdb->query( "UPDATE {$table} SET status='retry',next_attempt_at=UTC_TIMESTAMP(),last_error='stale-processing-recovered',updated_at=UTC_TIMESTAMP() WHERE status='processing' AND updated_at<=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('pending','retry') AND next_attempt_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT %d", $limit ), ARRAY_A );
\t\t$processed = 0;
\t\tforeach ( $rows as $row ) {
\t\t\t$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='processing',attempts=attempts+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status=%s AND attempts=%d AND next_attempt_at<=UTC_TIMESTAMP()", (int) $row['id'], (string) $row['status'], (int) $row['attempts'] ) );
\t\t\tif ( 1 !== (int) $claimed ) { continue; }
\t\t\t$processed++;
\t\t\t$attempts = (int) $row['attempts'] + 1;
\t\t\t$payload = json_decode( $row['payload_json'], true );
\t\t\ttry {
\t\t\t\tdo_action( 'he_v2_outbox_event', $row['event_name'], is_array( $payload ) ? $payload : array(), $row['event_id'] );
\t\t\t\t$done = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='delivered',last_error='',updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='processing' AND attempts=%d", (int) $row['id'], $attempts ) );
\t\t\t\tif ( 1 !== (int) $done ) { HE_V2_Schema::record_runtime_failure( 'outbox_delivery_finalize_failed', 'A File 06 outbox delivery lost its processing lease before finalization.' ); }
\t\t\t} catch ( Throwable $error ) {
\t\t\t\t$status = $attempts >= 5 ? 'dead-letter' : 'retry';
\t\t\t\t$delay = min( DAY_IN_SECONDS, 60 * ( 2 ** min( 8, $attempts ) ) );
\t\t\t\t$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,next_attempt_at=%s,last_error=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='processing' AND attempts=%d", $status, gmdate( 'Y-m-d H:i:s', time() + $delay ), sanitize_text_field( $error->getMessage() ), (int) $row['id'], $attempts ) );
\t\t\t}
\t\t}
\t\treturn $processed;
\t}'''
    s=read(rel); start='\tpublic static function process_outbox('
    a=s.find(start)
    if a<0: raise SystemExit('process_outbox missing')
    b=s.rfind('\n}')
    if b<a: raise SystemExit('class end missing')
    write(rel,s[:a]+new+'\n'+s[b:])
    append_test('''$integrations=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
v248_ok(false!==strpos($integrations,"status='processing'") && false!==strpos($integrations,"stale-processing-recovered") && false!==strpos($integrations,"AND status=%s AND attempts=%d"),'R3 outbox delivery lacks a CAS processing lease and stale-worker recovery');''')

def round4():
    rel='homeopathy-encyclopedia/includes/class-he-v2-domain.php'
    new='''\tpublic static function emit_event( $name, $object_type, $object_id, $payload ) {
\t\tglobal $wpdb;
\t\t$event_id = wp_generate_uuid4();
\t\t$trace_id = self::trace_id();
\t\t$payload = is_array( $payload ) ? $payload : array();
\t\t$payload['owner'] = 'file-06';
\t\t$payload['contract_version'] = HE_CONTRACT_VERSION;
\t\t$payload['occurred_at'] = gmdate( 'c' );
\t\t$json = wp_json_encode( $payload );
\t\t$now = current_time( 'mysql', true );
\t\t$already_in_transaction = (bool) $wpdb->get_var( 'SELECT @@session.in_transaction' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
\t\tif ( ! $already_in_transaction ) { $wpdb->query( 'START TRANSACTION' ); }
\t\t$event_ok = $wpdb->insert( HE_V2_Schema::table( 'events' ), array(
\t\t\t'event_id' => $event_id, 'event_name' => sanitize_text_field( $name ), 'object_type' => sanitize_key( $object_type ), 'object_id' => absint( $object_id ),
\t\t\t'actor_id' => get_current_user_id(), 'trace_id' => $trace_id, 'payload_json' => $json, 'created_at' => $now,
\t\t), array( '%s','%s','%s','%d','%d','%s','%s','%s' ) );
\t\t$outbox_ok = $wpdb->insert( HE_V2_Schema::table( 'outbox' ), array(
\t\t\t'event_id' => $event_id, 'event_name' => sanitize_text_field( $name ), 'payload_json' => $json, 'status' => 'pending', 'attempts' => 0,
\t\t\t'next_attempt_at' => $now, 'last_error' => '', 'created_at' => $now, 'updated_at' => $now,
\t\t), array( '%s','%s','%s','%s','%d','%s','%s','%s','%s' ) );
\t\tif ( ! $event_ok || ! $outbox_ok ) {
\t\t\tif ( ! $already_in_transaction ) { $wpdb->query( 'ROLLBACK' ); }
\t\t\tHE_V2_Schema::record_runtime_failure( 'event_outbox_atomic_write_failed', 'A File 06 domain event and its outbox record could not be persisted as one atomic unit.' );
\t\t\tthrow new RuntimeException( 'File 06 event/outbox atomic persistence failed.' );
\t\t}
\t\tif ( ! $already_in_transaction ) { $wpdb->query( 'COMMIT' ); }
\t\tdo_action( 'he_v2_event', $name, $payload, $event_id, $trace_id );
\t\treturn array( 'event_id' => $event_id, 'trace_id' => $trace_id );
\t}'''
    replace_block(rel,'\tpublic static function emit_event(','\tprivate static function canonicalize_idempotency_value(',new)
    append_test('''$domain=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v248_ok(false!==strpos($domain,'SELECT @@session.in_transaction') && false!==strpos($domain,'event_outbox_atomic_write_failed') && false!==strpos($domain,"ROLLBACK") && false!==strpos($domain,"COMMIT"),'R4 event/outbox pair is not persisted atomically');''')

def round5():
    rel='homeopathy-encyclopedia/includes/class-he-v22-governance.php'
    new='''\tpublic static function reconcile_outbox( $limit = self::BATCH_SIZE ) {
\t\tglobal $wpdb;
\t\t$limit = min( 100, max( 1, absint( $limit ) ) );
\t\t$sql = 'SELECT e.event_id,e.event_name,e.payload_json,e.created_at FROM ' . HE_V2_Schema::table( 'events' ) . ' e LEFT JOIN ' . HE_V2_Schema::table( 'outbox' ) . ' o ON o.event_id=e.event_id WHERE o.event_id IS NULL ORDER BY e.id ASC LIMIT %d';
\t\t$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A );
\t\t$created = 0;
\t\t$table = HE_V2_Schema::table( 'outbox' );
\t\tforeach ( $rows as $row ) {
\t\t\t$now = current_time( 'mysql', true );
\t\t\t$inserted = $wpdb->query( $wpdb->prepare(
\t\t\t\t"INSERT IGNORE INTO {$table} (event_id,event_name,payload_json,status,attempts,next_attempt_at,last_error,created_at,updated_at) VALUES (%s,%s,%s,'pending',0,%s,'',%s,%s)",
\t\t\t\t$row['event_id'], $row['event_name'], $row['payload_json'], $now, $row['created_at'], $now
\t\t\t) );
\t\t\tif ( false === $inserted ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'outbox_reconciliation_write_failed', 'File 06 could not reconcile a missing outbox row.' );
\t\t\t\tbreak;
\t\t\t}
\t\t\t$created += 1 === (int) $inserted ? 1 : 0;
\t\t}
\t\treturn array( 'recreated' => $created, 'checked' => count( $rows ) );
\t}'''
    replace_block(rel,'\tpublic static function reconcile_outbox(','\tpublic static function maintenance(',new)
    append_test('''$v22=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v248_ok(false!==strpos($v22,'INSERT IGNORE INTO {$table}') && false!==strpos($v22,'outbox_reconciliation_write_failed'),'R5 outbox reconciliation still has a check-then-insert duplicate race');''')

def round6():
    rel='homeopathy-encyclopedia/includes/class-he-v22-governance.php'
    new1='''\tpublic static function reindex_concept_secure( $concept_id ) {
\t\tglobal $wpdb;
\t\t$row = HE_V2_Domain::concept_by_id( absint( $concept_id ), true );
\t\tif ( ! $row || ! HE_V2_Domain::is_public_concept( $row ) || ! $row['current_version'] ) {
\t\t\treturn false !== $wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => absint( $concept_id ) ), array( '%d' ) );
\t\t}
\t\t$version = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', (int) $row['current_version'], (int) $row['id'] ), ARRAY_A );
\t\tif ( ! $version ) {
\t\t\treturn false !== $wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $row['id'] ), array( '%d' ) );
\t\t}
\t\t$aliases = $wpdb->get_col( $wpdb->prepare( 'SELECT alias FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d ORDER BY id ASC', (int) $row['id'] ) );
\t\t$grades = $wpdb->get_col( $wpdb->prepare( 'SELECT evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', (int) $row['id'], (int) $row['current_version'] ) );
\t\t$best_grade = 'ungraded'; $best_rank = 0;
\t\tforeach ( $grades as $grade ) { $rank = self::evidence_rank( $grade ); if ( $rank > $best_rank ) { $best_rank = $rank; $best_grade = sanitize_key( $grade ); } }
\t\t$search_text = HE_V2_Domain::normalize( implode( ' ', array_merge( array( $version['title'], $version['summary'], wp_strip_all_tags( $version['body'] ) ), $aliases ) ) );
\t\t$first = mb_substr( HE_V2_Domain::normalize( $version['title'] ), 0, 1, 'UTF-8' );
\t\t$data = array(
\t\t\t'concept_id' => (int) $row['id'], 'first_letter' => $first, 'type_slug' => $row['type_slug'],
\t\t\t'body_system' => HE_V2_Domain::taxonomy_slug( (int) $row['post_id'], HE_V2_Domain::TAX_SYSTEM ), 'language' => $row['language'],
\t\t\t'source_grade' => $best_grade, 'review_status' => $row['review_status'], 'safety_status' => $row['safety_status'],
\t\t\t'search_text' => $search_text, 'updated_at' => current_time( 'mysql', true ),
\t\t);
\t\treturn false !== $wpdb->replace( HE_V2_Schema::table( 'search_index' ), $data );
\t}'''
    new2='''\tpublic static function reindex_batch( $cursor = 0, $limit = self::BATCH_SIZE ) {
\t\tglobal $wpdb;
\t\t$limit = min( 100, max( 1, absint( $limit ) ) );
\t\t$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id>%d ORDER BY id ASC LIMIT %d', absint( $cursor ), $limit ) );
\t\t$processed = 0; $last = absint( $cursor ); $failed = 0;
\t\tforeach ( $rows as $id ) {
\t\t\t$id = absint( $id );
\t\t\tif ( ! self::reindex_concept_secure( $id ) ) { $failed = $id; break; }
\t\t\t$last = $id; $processed++;
\t\t\tupdate_option( self::REINDEX_CURSOR, $last, false );
\t\t}
\t\tif ( $failed ) {
\t\t\tupdate_option( self::REINDEX_REQUIRED, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'reindex_row_failed', 'File 06 retained the last successful reindex cursor so the failed concept can be retried.' );
\t\t\treturn array( 'processed' => $processed, 'next_cursor' => $last, 'done' => false, 'failed_concept_id' => $failed );
\t\t}
\t\t$more = $processed === $limit && (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id>%d ORDER BY id ASC LIMIT 1', $last ) );
\t\tupdate_option( self::REINDEX_CURSOR, $more ? $last : 0, false );
\t\tif ( ! $more ) { delete_option( self::REINDEX_REQUIRED ); }
\t\treturn array( 'processed' => $processed, 'next_cursor' => $more ? $last : null, 'done' => ! $more );
\t}'''
    replace_block(rel,'\tpublic static function reindex_concept_secure(','\tpublic static function reindex_batch(',new1)
    replace_block(rel,'\tpublic static function reindex_batch(','\tpublic static function migrate_legacy_batch(',new2)
    append_test('''$v22=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v248_ok(false!==strpos($v22,'failed_concept_id') && false!==strpos($v22,'reindex_row_failed') && false!==strpos($v22,'update_option( self::REINDEX_CURSOR, $last, false )'),'R6 reindex cursor can advance past an index persistence failure');''')

def round7():
    rel='homeopathy-encyclopedia/includes/class-he-v22-governance.php'
    old="""\tpublic static function resume_background_work() {\n\t\tif ( current_user_can( 'activate_plugins' ) ) {\n\t\t\tself::maintenance();\n\t\t}\n\t}\n"""
    new="""\tpublic static function resume_background_work() {\n\t\tif ( HE_V2_Auth::provider_ready() && HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR, 0, 'file06-background-maintenance' ) ) {\n\t\t\tself::maintenance();\n\t\t}\n\t}\n"""
    replace_once(rel,old,new)
    append_test('''$v22=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v248_ok(false===strpos($v22,"if ( current_user_can( 'activate_plugins' ) )") && false!==strpos($v22,"HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR, 0, 'file06-background-maintenance' )"),'R7 admin-init maintenance bypasses File00-backed File06 repair authorization');''')

def round8():
    rel='homeopathy-encyclopedia/includes/class-he-v2-integrations.php'
    new='''\tprivate function record_consumed_event( $name, $payload, $event_id ) {
\t\tglobal $wpdb;
\t\t$event_id = $event_id && preg_match( '/^[a-f0-9-]{16,64}$/i', $event_id ) ? $event_id : wp_generate_uuid4();
\t\t$table = HE_V2_Schema::table( 'events' );
\t\t$inserted = $wpdb->query( $wpdb->prepare(
\t\t\t"INSERT IGNORE INTO {$table} (event_id,event_name,object_type,object_id,actor_id,trace_id,payload_json,created_at) VALUES (%s,%s,'external',0,0,%s,%s,%s)",
\t\t\t$event_id, sanitize_text_field( $name ), HE_V2_Domain::trace_id(), wp_json_encode( is_array( $payload ) ? $payload : array() ), current_time( 'mysql', true )
\t\t) );
\t\tif ( false === $inserted ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'consumed_event_write_failed', 'A File 06 consumed-domain-event audit row could not be persisted.' );
\t\t}
\t}'''
    replace_block(rel,'\tprivate function record_consumed_event(','\tpublic function forward_local_event(',new)
    append_test('''$integrations=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
v248_ok(false!==strpos($integrations,'INSERT IGNORE INTO {$table}') && false!==strpos($integrations,'consumed_event_write_failed') && false===strpos($integrations,"SELECT id FROM ' . HE_V2_Schema::table( 'events' ) . ' WHERE event_id=%s"),'R8 consumed-event idempotency still uses a check-then-insert race');''')

def round9():
    rel='homeopathy-encyclopedia/includes/class-he-v22-public-guard.php'
    replace_once(rel,"\t\tadd_filter( 'get_the_excerpt', array( __CLASS__, 'research_excerpt' ), 98, 2 );\n","\t\tadd_filter( 'get_the_excerpt', array( __CLASS__, 'research_excerpt' ), 98, 2 );\n\t\tadd_filter( 'posts_where', array( __CLASS__, 'research_public_query_where' ), 99, 2 );\n")
    insert='''\tpublic static function research_public_query_where( $where, $query ) {
\t\tif ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) { return $where; }
\t\tglobal $wpdb;
\t\t$research = HE_V2_Schema::table( 'research' );
\t\treturn $where . $wpdb->prepare(
\t\t\t" AND ({$wpdb->posts}.post_type<>%s OR EXISTS (SELECT 1 FROM {$research} he_public_research WHERE he_public_research.post_id={$wpdb->posts}.ID AND he_public_research.status IN (%s,%s,%s)))",
\t\t\tHE_V2_Domain::RESEARCH_TYPE, 'published', 'corrected', 'retracted'
\t\t);
\t}
'''
    s=read(rel); marker='\tpublic static function research_title('
    idx=s.find(marker)
    if idx<0: raise SystemExit('research_title missing')
    write(rel,s[:idx]+insert+'\n'+s[idx:])
    s=read(rel)
    s=s.replace("\t\tif ( ! $post_id || HE_V2_Domain::RESEARCH_TYPE !== get_post_type( $post_id ) || ! is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {\n", "\t\tif ( ! $post_id || HE_V2_Domain::RESEARCH_TYPE !== get_post_type( $post_id ) ) {\n",1)
    s=s.replace("\t\tif ( ! $post || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type || ! is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {\n", "\t\tif ( ! $post || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type ) {\n",1)
    write(rel,s)
    append_test('''$guard=v248_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-public-guard.php');
v248_ok(false!==strpos($guard,'research_public_query_where') && false!==strpos($guard,'he_public_research.status IN') && false===strpos($guard,"get_post_type( $post_id ) || ! is_singular") && false===strpos($guard,"$post->post_type || ! is_singular"),'R9 WordPress archive/search paths can expose non-public domain research or stale WP title/excerpt metadata');''')

def main():
    init_test()
    if len(sys.argv)!=2: raise SystemExit('round number required')
    n=int(sys.argv[1])
    fn=globals().get(f'round{n}')
    if not fn: raise SystemExit('unsupported round')
    fn()

if __name__=='__main__': main()

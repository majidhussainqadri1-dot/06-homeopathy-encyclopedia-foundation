<?php
/**
 * Disposable-only proof: governed health dead-letter metrics must match
 * the canonical File06 worker status. Contains only synthetic records.
 */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI ||
    wp_get_environment_type() !== 'staging' ||
    wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'file06-file26-ci.local' ||
    getenv('FILE06_DISPOSABLE_QA') !== '1') {
    fwrite(STDERR, "REFUSED non-disposable dead-letter health test.\n");
    exit(2);
}
global $wpdb;
$checks = 0; $errors = [];
$check = static function($condition,$label) use (&$checks,&$errors) {
    $checks++;
    if($condition) { echo "PASS deadletter-health: $label\n"; }
    else { $errors[]=$label; fwrite(STDERR,"FAIL deadletter-health: $label\n"); }
};
$event=HE_V2_Domain::emit_event('File06SyntheticDeadLetterHealth.v1','concept',0,[
    'source'=>'Synthetic disposable DB health probe; no clinical or person data'
]);
$eid=(string)($event['event_id']??'');
$table=HE_V2_Schema::table('outbox');
$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='dead-letter'");
$mutated=$wpdb->update($table,[
    'status'=>'dead-letter','attempts'=>5,'last_error'=>'synthetic-ci-retry-exhausted',
    'updated_at'=>current_time('mysql',true)
],['event_id'=>$eid],['%s','%d','%s','%s'],['%s']);
$after=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='dead-letter'");
$health=HE_V22_Governance::health();
$observed=(int)($health['dead_letter']??-1);
echo "DEADLETTER_HEALTH_EVIDENCE before=$before db_count=$after reported=$observed mutated=$mutated\n";
$check(wp_is_uuid($eid),'Emitted synthetic canonical event ID is a UUID');
$check($mutated===1&&$after===$before+1,'Synthetic exhaustion used real worker dead-letter status');
$check(isset($health['dead_letter'])&&$observed===$after,
    'Governance health uses actual dead-letter status and reports current queue backlog');
if($errors){fwrite(STDERR,count($errors)." DEADLETTER HEALTH ASSERTIONS FAILED.\n");exit(1);}
echo "Governed dead-letter health: $checks checks PASS.\n";

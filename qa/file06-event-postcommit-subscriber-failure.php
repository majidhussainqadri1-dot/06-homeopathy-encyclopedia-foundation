<?php
/** Disposable-only regression: a throwing synchronous event hook must not
 * misreport an already-committed domain-event/outbox as failed. */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI ||
    wp_get_environment_type() !== 'staging' ||
    wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'file06-file26-ci.local' ||
    getenv('FILE06_DISPOSABLE_QA') !== '1') {
    fwrite(STDERR,"REFUSED non-disposable event fault injection.\n");
    exit(2);
}
global $wpdb;
$checks=0; $failures=[];
$check=static function($ok,$label)use(&$checks,&$failures) {
    $checks++;
    if($ok){echo "PASS eventpostcommit: $label\n";}
    else{$failures[]=$label;fwrite(STDERR,"FAIL eventpostcommit: $label\n");}
};
$event_name='File06SyntheticObserverPostcommit.v1';
$listener_calls=0;
$observer=static function($name) use (&$listener_calls,$event_name){
    if($name===$event_name){
        $listener_calls++;
        throw new RuntimeException('SYNTHETIC_EVENT_OBSERVER_FAILURE');
    }
};
add_action('he_v2_event',$observer,PHP_INT_MAX,1);
try {
    $result=HE_V2_Domain::emit_event($event_name,'concept',0,[
        'nonce'=>wp_generate_uuid4(),'source'=>'synthetic disposable only'
    ]);
} catch(Throwable $error) {
    $result=new WP_Error('event_observer_exception',$error->getMessage());
} finally {
    remove_action('he_v2_event',$observer,PHP_INT_MAX);
}
$events=HE_V2_Schema::table('events');
$outbox=HE_V2_Schema::table('outbox');
$row=$wpdb->get_row($wpdb->prepare(
    "SELECT event_id FROM {$events} WHERE event_name=%s ORDER BY id DESC LIMIT 1",
    $event_name),ARRAY_A);
$event_id=(string)($row['event_id']??'');
$has_outbox=$event_id?(int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$outbox} WHERE event_id=%s",$event_id)):0;
echo 'EVENT_POSTCOMMIT_EVIDENCE returned='.(is_wp_error($result)?$result->get_error_code():gettype($result)).
    " observer_calls=$listener_calls event_id=$event_id outbox_count=$has_outbox\n";
$check($listener_calls===1,'Synthetic observer threw precisely once');
$check((bool)$event_id&&$has_outbox===1,'Canonical event and outbox already committed');
$check(is_array($result)&&isset($result['event_id'])&&$result['event_id']===$event_id,
    'Committed event reports success despite failing optional observer');
if($failures){fwrite(STDERR,count($failures)." EVENT POSTCOMMIT ASSERTIONS FAILED.\n");exit(1);}
echo "Domain-event post-COMMIT failure: $checks checks PASS.\n";

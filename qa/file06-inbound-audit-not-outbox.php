<?php
/**
 * Disposable-only inbound/outbound ownership separation regression.
 * An inbound File05/12/15 event is an audit record, not a File06 outbox command.
 */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI ||
    wp_get_environment_type() !== 'staging' ||
    wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'file06-file26-ci.local' ||
    getenv('FILE06_DISPOSABLE_QA') !== '1') {
    fwrite(STDERR,"REFUSED: non-disposable owner-direction test.\n");
    exit(2);
}
global $wpdb;
$checks=0; $bad=[];
$check=static function($ok,$label)use(&$checks,&$bad){
    $checks++;
    if($ok){echo "PASS direction: $label\n";}
    else{$bad[]=$label;fwrite(STDERR,"FAIL direction: $label\n");}
};
$id=wp_generate_uuid4();
$integrations=new HE_V2_Integrations();
$integrations->consume_learning_event(['synthetic'=>true,'origin'=>'File05 disposable only'],$id);
$events=HE_V2_Schema::table('events');
$outbox=HE_V2_Schema::table('outbox');
$inbound=$wpdb->get_row($wpdb->prepare("SELECT object_type,object_id FROM {$events} WHERE event_id=%s",$id),ARRAY_A);
$pre=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$outbox} WHERE event_id=%s",$id));
$health_before=HE_V22_Governance::health();
$missing_before=(int)($health_before['outbox_reconciliation_missing']??-1);
$result=HE_V22_Governance::reconcile_outbox(100);
$post=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$outbox} WHERE event_id=%s",$id));
$health_after=HE_V22_Governance::health();
$missing=(int)($health_after['outbox_reconciliation_missing']??-1);
echo "OWNER_DIRECTION_EVIDENCE type=".($inbound['object_type']??'MISSING').
     " before=$pre after=$post missing_before=$missing_before missing_health=$missing reconciled=".($result['recreated']??'N/A')."\n";
$check(is_array($inbound)&&$inbound['object_type']==='external'&& (int)$inbound['object_id']===0,
 'An inbound event is recorded as external audit only');
$check($pre===0,'Inbound event has no owner outbox');
$check($missing_before===0,'Owner health does not flag inbound audit as missing outbound event');
$check($post===0,'Owner repair never republishes inbound event into File06 outgoing queue');
$check($missing===0,'Owner outbox missing-health excludes inbound audit records');
if($bad){fwrite(STDERR,count($bad)." INBOUND/OUTBOUND BOUNDARY ASSERTIONS FAILED.\n");exit(1);}
echo "File06 inbound event isolation: $checks checks PASS.\n";

<?php
/**
 * Disposable-only File06 merge / post-COMMIT listener exception regression.
 * No clinical source, real person, or production database may be used.
 */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI ||
    wp_get_environment_type() !== 'staging' ||
    wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'file06-file26-ci.local' ||
    getenv('FILE06_DISPOSABLE_QA') !== '1') {
    fwrite(STDERR, "REFUSED non-disposable post-commit fault injection.\n");
    exit(2);
}
global $wpdb;
$checks=0; $failures=[];
$check=static function($value,$label) use (&$checks,&$failures) {
    $checks++;
    if ($value) { echo "PASS postcommit: $label\n"; }
    else { $failures[]=$label; fwrite(STDERR,"FAIL postcommit: $label\n"); }
};
$s=wp_insert_post([
    'post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
    'post_title'=>'CI synthetic listener source '.wp_generate_uuid4(), 'post_author'=>1,
],true);
$t=wp_insert_post([
    'post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
    'post_title'=>'CI synthetic listener target '.wp_generate_uuid4(), 'post_author'=>1,
],true);
$check(!is_wp_error($s)&&!is_wp_error($t)&&$s&&$t,'Synthetic WordPress entries were created');
if (!is_wp_error($s) && !is_wp_error($t) && $s && $t) {
    $source=HE_V2_Domain::concept_by_post_id((int)$s);
    $target=HE_V2_Domain::concept_by_post_id((int)$t);
    $check(is_array($source)&&is_array($target),'Both canonical concepts exist');
    if (is_array($source)&&is_array($target)) {
        $concepts=HE_V2_Schema::table('concepts');
        $wpdb->update($concepts,['type_slug'=>'remedy'],['id'=>(int)$source['id']]);
        $wpdb->update($concepts,['type_slug'=>'remedy'],['id'=>(int)$target['id']]);
        $listener_calls=0;
        $throw_listener=static function($name) use (&$listener_calls) {
            if ($name === 'KnowledgeConceptMerged.v1') {
                $listener_calls++;
                throw new RuntimeException('SYNTHETIC_DISPOSABLE_POSTCOMMIT_LISTENER_FAILURE');
            }
        };
        add_action('he_v2_event',$throw_listener,PHP_INT_MAX,1);
        try {
            $result=HE_V22_Governance::secure_merge([
                'source_id'=>(int)$source['id'], 'target_id'=>(int)$target['id'],
                'source_version'=>(int)$source['row_version'],
                'target_version'=>(int)$target['row_version'],
                'actor_id'=>1,'reason'=>'synthetic listener failure after durable COMMIT',
            ]);
        } catch (Throwable $error) {
            $result=new WP_Error('unexpected_uncaught_exception',$error->getMessage());
        } finally {
            remove_action('he_v2_event',$throw_listener,PHP_INT_MAX);
        }
        $source_after=HE_V2_Domain::concept_by_post_id((int)$s);
        $target_after=HE_V2_Domain::concept_by_post_id((int)$t);
        $event_table=HE_V2_Schema::table('events');
        $outbox_table=HE_V2_Schema::table('outbox');
        $event_count=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$event_table} WHERE event_name=%s AND object_id=%d",
            'KnowledgeConceptMerged.v1',(int)$target['id']));
        $outbox_count=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$outbox_table} ob INNER JOIN {$event_table} ev ON ev.event_id=ob.event_id WHERE ev.event_name=%s AND ev.object_id=%d",
            'KnowledgeConceptMerged.v1',(int)$target['id']));
        echo 'POSTCOMMIT_EVIDENCE returned='.(is_wp_error($result)?$result->get_error_code():gettype($result)).
            ' source_merged='.((int)($source_after['merged_into_id']??0)===(int)$target['id']?'YES':'NO').
            " listener_calls=$listener_calls events=$event_count outbox=$outbox_count\n";
        $check($listener_calls===1,'Faulting observer ran exactly once after commit');
        $check((int)($source_after['merged_into_id']??0)===(int)$target['id'],'Committed source lineage persisted');
        $check(is_array($result)&&(int)($result['id']??0)===(int)$target['id'],
            'Committed merge returns canonical success, not retryable false failure');
        $check(is_array($target_after)&&(int)$target_after['row_version']===(int)$target['row_version']+1,
            'Committed target row version advanced precisely once');
        $check($event_count===1&&$outbox_count===1,'Durable merge event/outbox remain committed for recovery');
    }
}
if ($failures) { fwrite(STDERR,count($failures)." POSTCOMMIT SAFETY ASSERTIONS FAILED.\n"); exit(1); }
echo "Post-COMMIT synthetic listener exception: $checks checks PASS.\n";

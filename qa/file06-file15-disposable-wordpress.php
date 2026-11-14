<?php
/**
 * File06 → File15 actual source/event/inbox and clinical mapping freeze.
 * Disposable WordPress/MariaDB integration only, NEVER live evidence.
 */
defined('ABSPATH') || exit;
global $wpdb;
$checks=0;$failures=[];
$assert=static function($ok,$label) use (&$checks,&$failures){
    $checks++;
    if(!$ok){$failures[]=$label;fwrite(STDERR,"FAIL File06/15 real DB: {$label}\n");}
    else{echo "PASS File06/15 real DB: {$label}\n";}
};
$assert(class_exists('HE_File15_Bridge')&&class_exists('RSR_Events'),
    'Both native File06 producer and File15 consumer classes loaded');
$assert(function_exists('rsr_accept_platform_event'),
    'File15 verified event API callable');
$assert(false!==has_action('he_v2_outbox_event',['HE_File15_Bridge','deliver']),
    'Native File06 durable-outbox event callback registered');
$source_post=wp_insert_post([
    'post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
    'post_title'=>'Synthetic merge source File06','post_content'=>'No clinical conclusions',
    'post_author'=>1
],true);
$target_post=wp_insert_post([
    'post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
    'post_title'=>'Synthetic merge target File06','post_content'=>'No medical efficacy claim',
    'post_author'=>1
],true);
$assert(!is_wp_error($source_post)&&!is_wp_error($target_post)&&$source_post&&$target_post,
    'Two synthetic actual File06 WordPress posts created');
if(!is_wp_error($source_post)&&!is_wp_error($target_post)){
    $source=HE_V2_Domain::concept_by_post_id((int)$source_post);
    $target=HE_V2_Domain::concept_by_post_id((int)$target_post);
    $assert(is_array($source)&&is_array($target),'Two actual canonical File06 concept identities persisted');
    if(is_array($source)&&is_array($target)){
        $c=HE_V2_Schema::table('concepts');
        $updated_one=$wpdb->update($c,['type_slug'=>'remedy'],['id'=>(int)$source['id']]);
        $updated_two=$wpdb->update($c,['type_slug'=>'remedy'],['id'=>(int)$target['id']]);
        $assert(false!==$updated_one&&false!==$updated_two,'Synthetic remedy-type fixtures persisted');
        $mapping=RSR_DB::table('mappings');
        $seed=$wpdb->insert($mapping,[
            'public_id'=>wp_generate_uuid4(),
            'rubric_hash'=>hash('sha256','file06-15-disposable-rubric'),
            'query_json'=>'{}',
            'remedy_public_id'=>(string)$source['public_id'],
            'source_public_id'=>wp_generate_uuid4(),
            'reference_text'=>'SYNTHETIC CI ONLY — NOT A CLINICAL SOURCE',
            'license_code'=>'synthetic-ci',
            'review_date'=>gmdate('Y-m-d'),
            'status'=>'active',
            'version'=>1,
            'created_at'=>current_time('mysql',true),
            'updated_at'=>current_time('mysql',true)
        ]);
        $assert(1===$seed,'Synthetic File15 mapping seeded without clinical claim');
        if(1===$seed){
            $before_gen=(int)get_option('rsr_comparison_cache_generation',1);
            $payload=[
                'source_id'=>(string)$source['public_id'],
                'target_id'=>(string)$target['public_id'],
                'reason'=>'SYNTHETIC disposable merge event'
            ];
            $emitted=HE_V2_Domain::emit_event(
                'KnowledgeConceptMerged.v1','concept',(int)$target['id'],$payload
            );
            $eid=$emitted['event_id']??'';
            $assert(is_string($eid)&&wp_is_uuid($eid),'File06 native event/outbox emitted stable UUID');
            $owner_event=HE_V2_Schema::table('events');
            $owner_outbox=HE_V2_Schema::table('outbox');
            $owner_rows=(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$owner_event} WHERE event_id=%s",$eid
            ));
            $outbox_rows=(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$owner_outbox} WHERE event_id=%s",$eid
            ));
            $assert($owner_rows===1&&$outbox_rows===1,
                'Canonical File06 event and outbox atomically persisted');
            HE_V2_Integrations::process_outbox(50);
            $owner_status=(string)$wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$owner_outbox} WHERE event_id=%s",$eid
            ));
            $inbox=RSR_DB::table('inbox');
            $consumer_status=(string)$wpdb->get_var($wpdb->prepare(
                "SELECT status FROM {$inbox} WHERE event_id=%s",$eid
            ));
            $assert($owner_status==='delivered'&&$consumer_status==='delivered',
                'File06 actual worker delivered to File15 durable inbox');
            $entry=$wpdb->get_row($wpdb->prepare(
                "SELECT status,version,remedy_public_id FROM {$mapping} WHERE remedy_public_id=%s",
                (string)$source['public_id']
            ),ARRAY_A);
            $assert(is_array($entry)&&$entry['status']==='review_required'&&(int)$entry['version']===2,
                'File15 held synthetic source remedy mapping for independent review');
            $assert((int)get_option('rsr_comparison_cache_generation',1)===$before_gen+1,
                'Clinical comparison cache generation invalidated once');
            $reply=rsr_accept_platform_event('KnowledgeConceptMerged.v1',[
                'event_id'=>$eid,'source_remedy_public_id'=>(string)$source['public_id'],
                'target_remedy_public_id'=>(string)$target['public_id'],
                'contract_version'=>'v1','source_owner'=>'file-06'
            ]);
            $assert(true===$reply,'Exact replay accepted idempotently');
            $assert((int)get_option('rsr_comparison_cache_generation',1)===$before_gen+1,
                'Exact replay does not repeat clinical mapping/cache changes');
            $changed=rsr_accept_platform_event('KnowledgeConceptMerged.v1',[
                'event_id'=>$eid,'source_remedy_public_id'=>(string)$target['public_id'],
                'target_remedy_public_id'=>(string)$source['public_id'],
                'contract_version'=>'v1','source_owner'=>'file-06'
            ]);
            $assert(is_wp_error($changed)&&$changed->get_error_code()==='rsr_event_id_collision',
                'Reused stable event ID with tampered concept IDs rejected');
        }
    }
}
if($failures){fwrite(STDERR,count($failures)." File06/File15 cross-owner checks failed.\n");exit(1);}
echo "File06/File15 real disposable WordPress/MariaDB: {$checks} assertions PASS; live unverified.\n";

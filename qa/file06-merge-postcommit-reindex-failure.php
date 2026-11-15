<?php
/**
 * Disposable-only post-COMMIT search-index failure / durable repair test.
 * A synthetic trigger is installed only after the canonical merge commits.
 */
defined('ABSPATH') || exit;
if (!defined('WP_CLI') || !WP_CLI ||
    wp_get_environment_type() !== 'staging' ||
    wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'file06-file26-ci.local' ||
    getenv('FILE06_DISPOSABLE_QA') !== '1') {
    fwrite(STDERR,"REFUSED non-disposable reindex fault injection.\n");
    exit(2);
}
global $wpdb;
$check_count=0; $bad=[];
$check=static function($ok,$label) use (&$check_count,&$bad){
    $check_count++;
    if($ok){echo "PASS reindexpostcommit: $label\n";}
    else{$bad[]=$label;fwrite(STDERR,"FAIL reindexpostcommit: $label\n");}
};
$s=wp_insert_post(['post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
    'post_title'=>'Synthetic reindex merge source '.wp_generate_uuid4(),'post_author'=>1],true);
$t=wp_insert_post(['post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
    'post_title'=>'Synthetic reindex merge target '.wp_generate_uuid4(),'post_author'=>1],true);
$check(!is_wp_error($s)&&!is_wp_error($t)&&$s&&$t,'Temporary source and target entries created');
if(!is_wp_error($s)&&!is_wp_error($t)&&$s&&$t){
    $source=HE_V2_Domain::concept_by_post_id((int)$s);
    $target=HE_V2_Domain::concept_by_post_id((int)$t);
    $check(is_array($source)&&is_array($target),'Canonical rows created');
    if(is_array($source)&&is_array($target)){
        $concepts=HE_V2_Schema::table('concepts');
        $wpdb->update($concepts,['type_slug'=>'remedy'],['id'=>(int)$source['id']]);
        $wpdb->update($concepts,['type_slug'=>'remedy'],['id'=>(int)$target['id']]);
        $index=HE_V2_Schema::table('search_index');
        $seed=$wpdb->replace($index,[
            'concept_id'=>(int)$target['id'], 'search_text'=>'SYNTHETIC CI ROW',
            'updated_at'=>current_time('mysql',true)
        ]);
        $check(false!==$seed,'Synthetic target index row seeded');
        $trigger='f06_ci_reindex_'.substr(str_replace('-','',wp_generate_uuid4()),0,12);
        $armed=false;
        $inject=static function($name) use (&$armed,$trigger,$index,$wpdb){
            if($name!=='KnowledgeConceptMerged.v1')return;
            $sql="CREATE TRIGGER `$trigger` BEFORE DELETE ON `$index` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_reindex_delete_failure'";
            $armed=false!==$wpdb->query($sql);
        };
        add_action('he_v2_event',$inject,PHP_INT_MAX,1);
        delete_option(HE_V22_Governance::REINDEX_REQUIRED);
        try {
            $result=HE_V22_Governance::secure_merge([
                'source_id'=>(int)$source['id'],'target_id'=>(int)$target['id'],
                'source_version'=>(int)$source['row_version'],
                'target_version'=>(int)$target['row_version'],
                'actor_id'=>1,'reason'=>'synthetic post-commit index retry'
            ]);
        } finally {
            remove_action('he_v2_event',$inject,PHP_INT_MAX);
            $wpdb->query("DROP TRIGGER IF EXISTS `$trigger`");
        }
        $after=HE_V2_Domain::concept_by_post_id((int)$s);
        $row=$wpdb->get_row($wpdb->prepare(
            "SELECT concept_id FROM {$index} WHERE concept_id=%d",(int)$target['id']),ARRAY_A);
        echo 'POSTCOMMIT_REINDEX_EVIDENCE returned='.(is_wp_error($result)?$result->get_error_code():gettype($result)).
            ' armed='.($armed?'YES':'NO').
            ' reindex_required='.(get_option(HE_V22_Governance::REINDEX_REQUIRED)?'YES':'NO').
            ' retained_index='.(is_array($row)?'YES':'NO')."\n";
        $check($armed,'Index fault trigger installed only after COMMIT');
        $check(is_array($after)&&(int)$after['merged_into_id']===(int)$target['id'],
            'Canonical source merge stays committed');
        $check(is_array($result)&&(int)($result['id']??0)===(int)$target['id'],
            'Committed command returns canonical result, not a false rollback');
        $check((bool)get_option(HE_V22_Governance::REINDEX_REQUIRED),
            'Rebuild-required flag persisted for later safe repair');
        $check(is_array($row),'Failed synthetic index delete was not silently represented as rebuilt');
        // Restore clean state so follow-up fixture does not inherit pending index work.
        $wpdb->delete($index,['concept_id'=>(int)$target['id']],['%d']);
        delete_option(HE_V22_Governance::REINDEX_REQUIRED);
    }
}
if($bad){fwrite(STDERR,count($bad)." REINDEX POSTCOMMIT SAFETY FAILURES.\n");exit(1);}
echo "Reindex post-COMMIT failure: $check_count checks PASS.\n";

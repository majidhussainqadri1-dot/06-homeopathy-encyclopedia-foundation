<?php
/**
 * RED-first regression for transactional event AND outbox failure in disposable MariaDB.
 * Must NEVER run on production. All seeded people/content are synthetic.
 */
defined('ABSPATH') || exit;
// Refuse to run against an actual site, including production mistakenly labelled staging.
$qa_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'staging' ||
    $qa_host !== 'file06-file26-ci.local' || getenv('FILE06_DISPOSABLE_QA') !== '1') {
 fwrite(STDERR, "REFUSED: synthetic database fault injection is restricted to disposable CI.\n");
 exit(2);
}
$fault_target = getenv('FILE06_FAULT_TARGET') ?: 'events';
if (!in_array($fault_target, ['events','outbox'], true)) { exit(2); }
global $wpdb;
$errors=[];$passes=0;
$check=static function($yes,$label)use(&$errors,&$passes){
 if($yes){$passes++;echo "PASS fault-injection: $label\n";}
 else{$errors[]=$label;fwrite(STDERR,"FAIL fault-injection: $label\n");}
};
$check(class_exists('HE_V22_Governance')&&class_exists('RSR_Events'),
 'Verified source owner and consumer are loaded');
$posts=[];
foreach(['source','target'] as $n){
 $posts[$n]=wp_insert_post([
 'post_type'=>HE_V2_Domain::ENTRY_TYPE,'post_status'=>'draft',
 'post_title'=>"Disposable fault $n ".wp_generate_uuid4(),
 'post_author'=>1],true);
}
$check(!is_wp_error($posts['source'])&&!is_wp_error($posts['target']),
 'Synthetic isolated source and target created');
if(!is_wp_error($posts['source'])&&!is_wp_error($posts['target'])){
 $s=HE_V2_Domain::concept_by_post_id((int)$posts['source']);
 $t=HE_V2_Domain::concept_by_post_id((int)$posts['target']);
 $check(is_array($s)&&is_array($t),'Canonical owner concept rows found');
 if(is_array($s)&&is_array($t)){
  $concepts=HE_V2_Schema::table('concepts');
  $wpdb->update($concepts,['type_slug'=>'remedy'],['id'=>(int)$s['id']]);
  $wpdb->update($concepts,['type_slug'=>'remedy'],['id'=>(int)$t['id']]);
  $mapping=RSR_DB::table('mappings');
  $insert=$wpdb->insert($mapping,[
   'public_id'=>wp_generate_uuid4(),'rubric_hash'=>hash('sha256',wp_generate_uuid4()),
   'query_json'=>'{}','remedy_public_id'=>(string)$s['public_id'],
   'source_public_id'=>wp_generate_uuid4(),'reference_text'=>'SYNTHETIC NOT CLINICAL',
   'license_code'=>'synthetic','review_date'=>gmdate('Y-m-d'),
   'status'=>'active','version'=>1,
   'created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)]);
  $check(1===$insert,'Synthetic File15 mapping created');
  $events=HE_V2_Schema::table('events');
  $outbox=HE_V2_Schema::table('outbox');
  $trigger='file06_ci_fail_'.substr(str_replace('-','',wp_generate_uuid4()),0,12);
  // Each run injects only one distinct failure point into disposable MariaDB.
  $fault_table = $fault_target === 'outbox' ? $outbox : $events;
  $create="CREATE TRIGGER `$trigger` BEFORE INSERT ON `$fault_table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='disposable_injected_atomic_write_failure'";
  $created=$wpdb->query($create);
  $check(false!==$created,'Disposable MariaDB trigger installed');
  if(false!==$created){
   try{
    $outcome=HE_V22_Governance::secure_merge([
     'source_id'=>(int)$s['id'],'target_id'=>(int)$t['id'],
     'source_version'=>(int)$s['row_version'],'target_version'=>(int)$t['row_version'],
     'actor_id'=>1,'reason'=>'Disposable post-COMMIT failure test'
    ]);
   }finally{
    $wpdb->query("DROP TRIGGER IF EXISTS `$trigger`");
   }
   $after=$wpdb->get_row($wpdb->prepare(
    "SELECT status,merged_into_id FROM {$concepts} WHERE id=%d",(int)$s['id']),ARRAY_A);
   $mapping_state=(string)$wpdb->get_var($wpdb->prepare(
    "SELECT status FROM {$mapping} WHERE remedy_public_id=%s",$s['public_id']));
   $count=(int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$events} WHERE event_name='KnowledgeConceptMerged.v1' AND object_id=%d",
     (int)$t['id']));
   $count_out=(int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$outbox} ob INNER JOIN {$events} ev ON ev.event_id=ob.event_id WHERE ev.object_id=%d AND ev.event_name=%s",
    (int)$t['id'],'KnowledgeConceptMerged.v1'
   ));
   echo "FAULT_TARGET=$fault_target FAULT_EVIDENCE canonical_merge=".((int)($after['merged_into_id']??0)===(int)$t['id']?'YES':'NO')
      ." mapping_status=$mapping_state owner_event_count=$count owner_outbox_count=$count_out"
      ." returned=". (is_wp_error($outcome)?$outcome->get_error_code():gettype($outcome))."\n";
   // This security invariant SHOULD hold. Its failure proves the exact
   // post-COMMIT event-loss window in the candidate source.
   $unsafe=(int)($after['merged_into_id']??0)===(int)$t['id']
      &&$mapping_state==='active'&&$count===0&&$count_out===0;
   $check(!$unsafe,
    'No committed canonical merge with stale active File15 mapping and absent durable event');
   $check(is_wp_error($outcome)&&$outcome->get_error_code()==='he_merge_event_atomic_failed',
    'Atomic event failure returns an accurate fail-closed typed 503 error');
   $check((int)($after['merged_into_id']??0)===0,
    'Owner source lineage was rolled back with event failure');
   $check($mapping_state==='active'&&$count===0&&$count_out===0,
    'No partial consumer mutation or owner event after atomic owner rollback');
  }
 }
}
if($errors){fwrite(STDERR,count($errors)." FAIL-CLOSED SAFETY INVARIANT(S) NOT MET.\n");exit(1);}
echo "Atomic $fault_target failure injection: $passes checks PASS.\n";

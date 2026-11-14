<?php
/**
 * RED-first P1 post-COMMIT outbox fault injection in disposable MariaDB.
 * Must NEVER run on production. All seeded people/content are synthetic.
 */
defined('ABSPATH') || exit;
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
  $trigger='file06_ci_fail_event_'.substr(str_replace('-','',wp_generate_uuid4()),0,12);
  // A trigger affects only the disposable WordPress CI database.
  $create="CREATE TRIGGER `$trigger` BEFORE INSERT ON `$events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='disposable_injected_event_write_failure'";
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
   echo "FAULT_EVIDENCE canonical_merge=".((int)($after['merged_into_id']??0)===(int)$t['id']?'YES':'NO')
      ." mapping_status=$mapping_state owner_event_count=$count owner_outbox_count=$count_out"
      ." returned=". (is_wp_error($outcome)?$outcome->get_error_code():gettype($outcome))."\n";
   // This security invariant SHOULD hold. Its failure proves the exact
   // post-COMMIT event-loss window in the candidate source.
   $unsafe=(int)($after['merged_into_id']??0)===(int)$t['id']
      &&$mapping_state==='active'&&$count===0&&$count_out===0;
   $check(!$unsafe,
    'No committed canonical merge with stale active File15 mapping and absent durable event');
  }
 }
}
if($errors){fwrite(STDERR,count($errors)." FAIL-CLOSED SAFETY INVARIANT(S) NOT MET.\n");exit(1);}
echo "Post-COMMIT failure injection: $passes checks PASS.\n";

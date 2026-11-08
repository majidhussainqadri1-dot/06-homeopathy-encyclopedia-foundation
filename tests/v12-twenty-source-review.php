<?php
/** Master Plan v12: 20 different code-review focal checks; static indicators ONLY. */
declare(strict_types=1);
$root=dirname(__DIR__);
$files=array(
 'boot'=>$root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php',
 'v12'=>$root.'/homeopathy-encyclopedia/includes/class-he-v12-completion.php',
 'evidence'=>$root.'/homeopathy-encyclopedia/includes/class-he-v12-evidence-impact.php',
 'withdraw'=>$root.'/homeopathy-encyclopedia/includes/class-he-v12-research-consent.php',
 'guard'=>$root.'/homeopathy-encyclopedia/includes/class-he-v22-research-guard.php',
 'future'=>$root.'/homeopathy-encyclopedia/includes/class-he-v24-future-schema.php',
 'sitemap'=>$root.'/homeopathy-encyclopedia/includes/class-he-v12-sitemap.php',
 'bridge'=>$root.'/homeopathy-encyclopedia/includes/class-he-file15-bridge.php',
 'runner'=>$root.'/tests/run-all.sh',
);
$src=array();
foreach($files as $name=>$path){$src[$name]=file_get_contents($path);if(!is_string($src[$name])){fwrite(STDERR,"Could not freeze source {$name}\n");exit(1);}}
$rounds=array(
 1=>array('Ownership/bootstrap','boot',array('class-he-v12-completion.php','class-he-v12-evidence-impact.php','class-he-v12-research-consent.php','class-he-v12-sitemap.php')),
 2=>array('Source-research schema isolation','v12',array('HE_V2_Schema::runtime_status()','HE_V24_Migration_Safety::ready()')),
 3=>array('Subject consent independence','v12',array('he_v12_consent_receipt_verification_v1','file-00','consent_evidence_ref')),
 4=>array('Legacy clinical publication exclusion','guard',array('verified_by','verification_id','withdrawn_at_utc')),
 5=>array('Withdrawal nonce and object-level authority','withdraw',array('require_nonce','rest_permission','CAP_RESEARCH')),
 6=>array('Withdrawal durable CAS isolation','withdraw',array('START TRANSACTION','FOR UPDATE','row_version')),
 7=>array('Legacy privacy withdrawal','withdraw',array('legacy_unverified_acknowledged','legacy-unverified')),
 8=>array('Withdrawal safe-mode rollback','withdraw',array('ROLLBACK','OPTION_SAFE_MODE','ResearchRecordRetracted.v1')),
 9=>array('Canonical source provenance','evidence',array('SELECT DISTINCT c.id','c.review_status=\'approved\'','current_version=c.version_id')),
 10=>array('Source-impact transactional atomicity','evidence',array('START TRANSACTION','append_provenance','queue_impact','COMMIT','ROLLBACK')),
 11=>array('Bounded repeatable source impact','evidence',array('MAX_CLAIMS_PER_SCAN = 40','he_v12_evidence_more_claims')),
 12=>array('Retraction cursor owner ACK','future',array('v12_retraction_consumer_queue_incomplete','6!==(int)$queued')),
 13=>array('Safe reference HTTP','v12',array('wp_safe_remote_head',"'redirection' => 0",'wp_http_validate_url')),
 14=>array('Two-event link verdict','v12',array("'suspect'","'broken'",'404 === $code')),
 15=>array('Reference cursor durability','v12',array('v12_link_scan_cursor_failed','LINK_CURSOR')),
 16=>array('Elapsed review-date verification','v12',array('due_timestamp','urgent-review','Human review date unverified')),
 17=>array('Citation URL credential minimization','v12',array('public_source_url','wp_parse_url','parts[\'user\']')),
 18=>array('Scholarly digest includes claims','v12',array('approved_current_claims','record_digest( $dto, $claims )','source-content-conflict')),
 19=>array('Licensed cross-file links and source comparison','v12',array('he_v12_related_resource_owner_attested_v1','original_excerpt','rights-cleared')),
 20=>array('Sitemap and File15 bridge interoperability','sitemap',array('wp_sitemaps_post_types','posts_clauses','merged_into_id=0')),
);
if(count($rounds)!==20){fwrite(STDERR,"Not exactly twenty review topics\n");exit(1);}
foreach($rounds as $n=>$case){
 list($label,$file,$needles)=$case;
 foreach($needles as $needle){
   if(strpos($src[$file],$needle)===false){fwrite(STDERR,sprintf("R%02d FAIL (%s): %s\n",$n,$label,$needle));exit(1);}
 }
 printf("R%02d STATIC-PASS: %s\n",$n,$label);
}
if(strpos($src['bridge'],'rsr_file06_get_remedies')===false ||
 strpos($src['runner'],'tests/v12-sitemap-privacy.php')===false ||
 strpos($src['runner'],'tests/v12-completion-contract.php')===false ||
 strpos($src['runner'],'tests/v12-consent-withdrawal.php')===false){
 fwrite(STDERR,"Fail-closed cross-file and isolated regression gate missing\n");exit(1);
}
echo "20/20 distinct review focus areas have current source indicators; runtime acceptance is separately required.\n";

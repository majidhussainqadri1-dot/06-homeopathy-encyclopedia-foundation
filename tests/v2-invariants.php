<?php
/** Stable core architecture invariants for the current File 06 candidate. */
$root=dirname(__DIR__);$p=$root.'/homeopathy-encyclopedia';$fail=array();
function a($ok,$m){global $fail;if(!$ok)$fail[]=$m;} function rd($f){$v=file_get_contents($f);if(false===$v)throw new RuntimeException($f);return $v;}
$b=rd($p.'/homeopathy-encyclopedia.php');$s=rd($p.'/includes/class-he-v2-schema.php');$d=rd($p.'/includes/class-he-v2-domain.php');$api=rd($p.'/includes/class-he-v2-api.php');$auth=rd($p.'/includes/class-he-v2-auth.php');$g=rd($p.'/includes/class-he-v22-governance.php');$i=rd($p.'/includes/class-he-v22-integrity.php');$sch=rd($p.'/includes/class-he-v22-schedule.php');$search=rd($p.'/includes/class-he-v22-search.php');$types=rd($p.'/includes/class-he-v22-type-schemas.php');$rg=rd($p.'/includes/class-he-v22-research-guard.php');$c=rd($p.'/includes/class-he-v22-consumers.php');$op=rd($p.'/includes/class-he-v22-operations.php');$privacy=rd($p.'/includes/class-he-v2-privacy.php');$pub=rd($p.'/includes/class-he-v22-public-guard.php');
a((bool)preg_match("/HE_VERSION', '2\\.4\\.(?:15|16)/",$b),'version');a(false!==strpos($b,"HE_SCHEMA_VERSION', 10"),'schema');a((bool)preg_match("/HE_CONTRACT_VERSION', '2\\.4\\.(?:15|16)/",$b),'contract');
a((bool)preg_match("/'staging_accepted'\s*=>\s*false/",$b)&&(bool)preg_match("/'live_deployed'\s*=>\s*false/",$b)&&(bool)preg_match("/'operational'\s*=>\s*false/",$b),'release truth');
preg_match_all("/=> __\( '/",$d,$m);a(count($m[0])>=16,'taxonomy');a(16===substr_count($types,"'body_system_required' =>"),'16 type schemas');
foreach(array('concepts','aliases','versions','references','relations','reviews','integrity_actions','research','dataset_access','events','outbox','idempotency','bookmarks','rate_limits','search_index') as $t)a(false!==strpos($s,"table( '".$t."' )"),'table '.$t);
foreach(array('/health','/entries','/versions','/diff','/bookmark','/aliases','/references','/review','/transition','/integrity','/graph','/duplicates','/merge','/autocomplete','/research','/datasets','/repair') as $r)a(false!==strpos($api,$r),'route '.$r);
foreach(array('SMC_Contracts::assertions','provider_ready','he_identity_provider_unavailable') as $x)a(false!==strpos($auth,$x),'auth '.$x);
a(false===strpos($auth,"return (bool) user_can( \$user_id, 'manage_options' )"),'manage_options founder fallback');
foreach(array('content_hash char(64)','reviewed_row_version','migration_quarantine','secure_merge','reindex_concept_secure','he_relation_provenance_invalid') as $x)a(false!==strpos($g,$x),'governance '.$x);
foreach(array('START TRANSACTION','ROLLBACK','FOR UPDATE',"status='accepted'",'he_integrity_acceptance_required') as $x)a(false!==strpos($i,$x),'integrity '.$x);
foreach(array('_he_schedule_content_hash','EncyclopediaEntryScheduleInvalidated.v1','content-or-review-changed-before-publication') as $x)a(false!==strpos($sch,$x),'schedule '.$x);
foreach(array('spelling-recovery','similar_text','exact-phrase-token-alias') as $x)a(false!==strpos($search,$x),'search '.$x);
foreach(array('کامیاب کیس','case_anonymized','case_consent_verified','adverse_events','he_dataset_private_by_default','de_identification','access_policy') as $x)a(false!==strpos($rg.$d,$x),'research '.$x);
foreach(array('file-05','file-12','file-15','file-16','file-21','file-26') as $x)a(false!==strpos($c,"'".$x."'"),'consumer '.$x);
a(false!==strpos($c,"'write_authority' => false")&&false!==strpos($c,"'private_fields' => false"),'read-only consumer boundary');
foreach(array('wp_privacy_personal_data_exporters','wp_privacy_personal_data_erasers','he_v2_privacy_legal_hold') as $x)a(false!==strpos($privacy,$x),'privacy '.$x);
a(false!==strpos($pub,'ScholarlyArticle'),'research structured data');a(false!==strpos($op,"status='dead-letter'"),'dead letter health');
if($fail){fwrite(STDERR,"File 06 core invariants FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 current core invariants passed under current v2.4.x candidate.\n";
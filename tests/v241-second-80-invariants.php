<?php
$root=dirname(__DIR__);$p=$root.'/homeopathy-encyclopedia';
$f=array(
 'b'=>file_get_contents($p.'/homeopathy-encyclopedia.php'),
 'g'=>file_get_contents($p.'/includes/class-he-v241-governance.php'),
 'gp'=>file_get_contents($p.'/includes/class-he-v241-governance-privacy.php'),
 'rg'=>file_get_contents($p.'/includes/class-he-v241-research-governance.php'),
 'r'=>file_get_contents($p.'/includes/class-he-v241-runtime-guard.php'),
 'n'=>file_get_contents($p.'/includes/class-he-v241-before-callback-normalizer.php'),
 'd'=>file_get_contents($p.'/includes/class-he-v241-public-dto-guard.php'),
 'm'=>file_get_contents($p.'/includes/class-he-v24-migration-safety.php'),
 'u'=>file_get_contents($p.'/uninstall.php'),
);
$fail=array();
function t($k,$needle,$msg){global $f,$fail;if(false===strpos($f[$k],$needle))$fail[]=$msg;}
foreach(array('Version: 2.4.8',"HE_VERSION', '2.4.8","HE_CONTRACT_VERSION', '2.4.8",'class-he-v241-governance.php','class-he-v241-governance-privacy.php','class-he-v241-research-governance.php','class-he-v241-runtime-guard.php','class-he-v241-before-callback-normalizer.php','class-he-v241-public-dto-guard.php','native_object_scope_required','editor_type_assignment_required','reviewer_assignment_required','research_reviewer_assignment_required','admin_and_composer_scope_enforced','legacy_unverified_scheduler_disabled','core_maintenance_serialized','future_maintenance_serialized','core_numeric_enumeration_blocked','research_apply_requires_accepted_state') as $x)t('b',$x,'bootstrap '.$x);
foreach(array('META_EDITOR_TYPES','META_REVIEW_ASSIGNMENTS','save_editor_scope','save_reviewer_assignment','editor_type_allowed','reviewer_assigned','file06-integrity-apply','file06-research-transition','file06-dataset-approval','file06-future-claim-edit','file06-future-translation-edit') as $x)t('g',$x,'governance '.$x);
foreach(array('wp_privacy_personal_data_exporters','wp_privacy_personal_data_erasers','META_EDITOR_TYPES','META_REVIEW_ASSIGNMENTS','he_v2_privacy_legal_hold') as $x)t('gp',$x,'governance privacy '.$x);
foreach(array('research-reviewer-assignment','File06ResearchReviewerAssigned.v1','he_reviewer_assignment_required','he_integrity_acceptance_required',"'accepted'!==\$action['status']",'file06-research-integrity-apply','Idempotency-Key') as $x)t('rg',$x,'research governance '.$x);
foreach(array('guard_admin_entry_write','harden_composer_types','composer_create_draft','file06-external-stage-object','file06-external-review-research',"remove_action( 'he_v2_maintenance', array( 'HE_V2_Domain', 'maintenance' )",'HE_V22_Schedule::publish_due_securely','core_maintenance_serialized','CORE_LEASE_OPTION') as $x)t('r',$x,'runtime '.$x);
foreach(array('direct_permission_routes','return null','future/external/lookup','dataset-access') as $x)t('n',$x,'normalizer '.$x);
foreach(array('he_canonical_public_id_required','references','replacement_object_id','replacement_id','versions','public_graph_edges','public_id') as $x)t('d',$x,'dto '.$x);
foreach(array('OPTION_ORCID_CURSOR','OPTION_EMITTED_CURSOR','OPTION_ORCID_DONE','OPTION_EMITTED_DONE','const BATCH = 100','public static function ready','postflight','future_migration_pending') as $x)t('m',$x,'migration '.$x);
foreach(array('_he_editor_type_scope','he_v24_orcid_postflight_done','he_v24_emitted_postflight_done','he_v241_core_maintenance_lease','he_v241_future_maintenance_lease') as $x)t('u',$x,'uninstall '.$x);
if($fail){fwrite(STDERR,"File 06 v2.4.1 second-80 regression controls FAILED under v2.4.8:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.1 second-80 controls remain present under v2.4.8.\n";

<?php
/** File 06 v2.4.17 eighteenth fresh twenty-round regression matrix. */
error_reporting(E_ERROR | E_PARSE);
$root=dirname(__DIR__);$inc=$root.'/homeopathy-encyclopedia/includes';$fail=array();
function r17($p){$v=file_get_contents($p);if(false===$v)throw new RuntimeException($p);return $v;}
function ok17($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
function has17($s,$n){return false!==strpos($s,$n);}
$ledger_path=$root.'/docs/FILE-06-v2.4.17-EIGHTEENTH-TWENTY-ROUND-REVIEW.md';$ledger=file_exists($ledger_path)?r17($ledger_path):'';
$env=getenv('FILE06_REVIEW_ROUND');if(false!==$env&&''!==$env){$round=(int)$env;}else{preg_match_all('/^([0-9]+)\. \*\*(?:DEFECT|CLEAN)\*\*/m',$ledger,$m);$round=!empty($m[1])?max(array_map('intval',$m[1])):0;}
$ref=r17($inc.'/class-he-v242-reference-graph.php');$lang=r17($inc.'/class-he-v242-language-surfaces.php');$third=r17($inc.'/class-he-v242-third-audit.php');$fs=r17($inc.'/class-he-v24-future-schema.php');$mig=r17($inc.'/class-he-v24-migration-safety.php');$api=r17($inc.'/class-he-v24-future-api.php');$gov=r17($inc.'/class-he-v241-governance.php');$norm=r17($inc.'/class-he-v241-before-callback-normalizer.php');$multi=r17($inc.'/class-he-v242-multilingual.php');$runtime=r17($inc.'/class-he-v242-runtime-corrections.php');$v23=r17($inc.'/class-he-v23-future.php');$reviewguard=r17($inc.'/class-he-v24-future-review-guard.php');$boot=r17($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');$readme=r17($root.'/homeopathy-encyclopedia/readme.txt');$runall=r17($root.'/tests/run-all.sh');
if($round>=1){ok17(has17($ref,"decode_public_cursor( 'reference'")&&!has17($ref,'$reference_id = absint( $request->get_param'),'R1 graph guard does not consume opaque reference token');}
if($round>=2){ok17(has17($lang,"status='published' AND review_status='approved' AND safety_status='approved'")&&has17($lang,'HE_V2_Domain::ENTRY_TYPE !== $post->post_type')&&has17($lang,"'publish' !== $post->post_status"),'R2 public translations lack governed/WP public parity');}
if($round>=3){ok17(!has17($third,"add_action( 'added_post_meta', array( __CLASS__, 'language_meta_changed' )")&&!has17($third,"add_action( 'updated_post_meta', array( __CLASS__, 'language_meta_changed' )"),'R3 stale language meta hook still pre-empts canonical owner');}
if($round>=4){ok17(has17($lang,'row_version=row_version+1')&&has17($lang,"review_status='unreviewed',safety_status='unreviewed'")&&has17($lang,'KnowledgeSourceLanguageChanged.v1'),'R4 source-language mutation lacks version/review invalidation');}
if($round>=5){ok17(has17($fs,'public static function required_shape()')&&has17($fs,'public static function schema_complete()')&&has17($fs,'|| ! self::schema_complete()')&&has17($mig,'HE_V24_Future_Schema::schema_complete()'),'R5 Future schema shape is not a readiness gate');}
if($round>=6){ok17(substr_count($fs,'update_option( HE_V2_Schema::OPTION_SCHEMA')===0,'R6 Future installer still writes core schema option');}
if($round>=7){ok17(has17($fs,'future_maintenance_schedule_failed')&&has17($fs,"wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::CRON, array(), true )"),'R7 Future schedule failure remains silent');}
if($round>=8){ok17(has17($fs,'retraction_watch_state_write_failed')&&has17($fs,'retraction_watch_provenance_failed')&&has17($fs,"'checked'=>$processed")&&!has17($fs,"return array( 'checked' => count( $rows ), 'flagged' => $flagged )"),'R8 retraction cursor/checked semantics remain unsafe');}
if($round>=9){ok17(has17($fs,'translation_outdated_maintenance_failed')&&has17($fs,'if ( ! self::mark_outdated_translations() ) { return; }'),'R9 translation-outdated maintenance write is unchecked');}
if($round>=10){ok17(has17($fs,"'future_schema_complete'=>")&&has17($fs,"['status']='degraded'")&&has17($fs,"'maintenance_scheduled'=>(bool)wp_next_scheduled"),'R10 Future health does not fail closed on incomplete shape');}
if($round>=11){$api_compact=preg_replace('/\s+/','',$api);ok17(has17($api,'claim_evidence_atomic_failed')&&has17($api,'START TRANSACTION')&&has17($api,'FOR UPDATE')&&has17($api_compact,"evidence_state='linked',review_status='pending'")&&has17($api_compact,'row_version=row_version+1')&&has17($api,'ROLLBACK')&&has17($api,'COMMIT'),'R11 claim evidence is not atomic/version bound');}
if($round>=12){ok17(has17($api,"/future/claims/(?P<id>[0-9a-fA-F-]{36})/evidence")&&has17($api,'private static function concept_by_public_id')&&has17($api,'WHERE public_id=%s FOR UPDATE')&&has17($api,'version_number')&&has17($gov,"/future/claims/([0-9a-fA-F-]{36})/(evidence|review)"),'R12 claim commands still depend on raw row IDs');}
if($round>=13){ok17((has17($api,"decode_public_cursor('reference'")||has17($api,"decode_public_cursor( 'reference'"))&&has17($api,"encode_public_cursor( 'external-record'")&&has17($api,'private static function resolve_external_binding')&&has17($api,"'public_id'"),'R13 external/reference command IDs are not canonical/opaque');}
if($round>=14){ok17(has17($api,"/(?P<locale>[A-Za-z0-9-]+)/review")&&has17($api,"status='draft'")&&has17($api,"AND status='approved'")&&has17($multi,"/future/translations/(?P<id>[0-9a-fA-F-]{36})")&&has17($gov,"/future/translations/([0-9a-fA-F-]{36})/([A-Za-z0-9-]+)/(review|publish)"),'R14 translation commands/CAS are not canonical and exact-state bound');}
if($round>=15){ok17(has17($api,"/future/impact/(?P<id>[0-9a-fA-F-]{36})")&&substr_count($api,'self::concept_by_public_id(')>=3&&has17($gov,"/future/impact/([0-9a-fA-F-]{36})"),'R15 remaining Future concept mutation IDs are internal');}
if($round>=16){ok17(has17($runtime,'composer_rollback_future_state_unverified')&&has17($runtime,'! HE_V24_Future_Schema::schema_complete()')&&has17($runtime,'! HE_V24_Migration_Safety::ready()'),'R16 composer rollback does not fail closed on unverified Future state');}
if($round>=17){ok17(has17($lang,"INNER JOIN ' . $wpdb->posts")&&has17($lang,"p.post_status='publish'")&&has17($lang,'c.current_version>0'),'R17 public language discovery lacks WP/current-version parity');}
if($round>=18){ok17(has17($ledger,'18. **CLEAN**'),'R18 clean audit record missing');}
if($round>=19){ok17(preg_match('/ \* Version: 2\.4\.(?:17|18|19)/',$boot)&&preg_match("/HE_VERSION', '2\.4\.(?:17|18|19)/",$boot)&&preg_match("/HE_CONTRACT_VERSION', '2\.4\.(?:17|18|19)/",$boot)&&preg_match("/future_hardening_version'=>'2\.4\.(?:17|18|19)/",$boot)&&preg_match('/Stable tag: 2\.4\.(?:17|18|19)/',$readme)&&has17($runall,'v2417-eighteenth-twenty-round-regressions.php')&&preg_match('/All File 06 v2\.4\.(?:17|18|19) automated checks/',$runall),'R19 historical release controls no longer tolerate the later current v2.4.x candidate');}
if($round>=20){
    $r20 = has17($ledger,'20. **DEFECT**')
        && has17($v23,'retire legacy v2.3 runtime surfaces')
        && !has17($v23,"add_action( 'rest_api_init', array( __CLASS__, 'register_routes' )")
        && !has17($v23,"add_action( self::CRON, array( __CLASS__, 'maintenance' )")
        && has17($reviewguard,"decode_public_cursor( 'external-record'")
        && !has17($reviewguard,"'/future/translations/(?P<id>\\\\d+)/review'")
        && !has17($reviewguard,"'/future/translations/(?P<id>\\\\d+)/publish'")
        && has17($gov,"/integrity/([0-9a-fA-F-]{36})/apply")
        && has17($gov,"/research/([0-9a-fA-F-]{36})/transition")
        && has17($gov,"/dataset-access/([A-Za-z0-9_-]+\\.[a-f0-9]{64})/approve")
        && has17($gov,"if ( 'review' === \$match[2] && ! self::reviewer_assigned")
        && has17($gov,"if ( 'review' === \$match[3] && ! self::reviewer_assigned");
    ok17($r20,'R20 residual numeric routes/object-scope reviewer gates remain');
}
for($i=1;$i<=$round;$i++){ok17(has17($ledger,$i.'. **'),'Review ledger missing round '.$i);}
if($fail){fwrite(STDERR,"File 06 v2.4.17 eighteenth-review regressions FAILED through R{$round}:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.17 eighteenth-review regressions through R{$round}: PASS\n";

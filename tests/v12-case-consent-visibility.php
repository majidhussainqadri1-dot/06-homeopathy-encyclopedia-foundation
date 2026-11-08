<?php
/** Isolated current consent vs. legacy, withdrawn, missing File 00 cases. */
declare(strict_types=1);
define('ABSPATH',__DIR__);
class WP_Error {private $code;public function __construct($code,$msg='',$data=array()){$this->code=$code;}public function get_error_code(){return $this->code;}}
class HE_V2_Schema {const OPTION_SAFE_MODE='safe-mode';}
function is_wp_error($v){return $v instanceof WP_Error;}
function __($s,$domain=''){return $s;}
function get_option($s,$default=false){return $GLOBALS['safe']??false;}
function apply_filters($name,$default,...$args){return $name==='he_v12_consent_receipt_current_v1'?($GLOBALS['receipt_current']??false):$default;}
function confirm_case($result,$label){$GLOBALS['tests']++;if(!$result){fwrite(STDERR,"FAIL {$label}\n");exit(1);}}
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-v22-research-guard.php';
$id='11111111-1111-4111-8111-111111111111';
$receipt=array('verified'=>true,'policy_version'=>'case-v1','locale'=>'ur-PK','purpose'=>'patient-story-research',
  'evidence_ref'=>'receipt:00001','verification_id'=>'file00:verify:00001','verified_by'=>'file-00',
  'state'=>'accepted','withdrawn_at_utc'=>null);
$row=array('public_id'=>$id,'record_type'=>'successful-case','status'=>'published','data_class'=>'public',
  'case_tag'=>'کامیاب کیس','case_anonymized'=>1,'case_consent_verified'=>1,
  'investigators_json'=>json_encode(array('Investigator')),
  'ethics_json'=>json_encode(array('approval_reference'=>'ethics-2026')),
  'conflicts_json'=>json_encode(array('recorded'=>true,'statement'=>'No conflict')),
  'consent_json'=>json_encode($receipt),
  'case_json'=>json_encode(array('observation_label'=>'observational case','baseline'=>'baseline collected','intervention'=>'support observed',
    'follow_up'=>'follow-up recorded','adverse_events'=>'no adverse events reported','limitations'=>'observational only')),
  'metadata_json'=>'{}');
$GLOBALS['tests']=0;$GLOBALS['safe']=false;$GLOBALS['receipt_current']=true;
confirm_case(HE_V22_Research_Guard::validate_row($row)===true,'Verified unrevoked File 00 receipt eligible');
$GLOBALS['receipt_current']=false;
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Revoked File 00 receipt rejected immediately');
$GLOBALS['receipt_current']=true;
$row['consent_json']=json_encode(array('verified'=>true,'version'=>'legacy'));
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Legacy boolean alone not consent');
$row['consent_json']=json_encode(array_merge($receipt,array('withdrawn_at_utc'=>'2026-11-08T03:00:00Z')));
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Withdrawn consent rejected');
$row['consent_json']=json_encode(array_merge($receipt,array('verified_by'=>'unverified-actor')));
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Untrusted consent authority denied');
$row['consent_json']=json_encode(array_merge($receipt,array('evidence_ref'=>'')));
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'No immutable evidence token denied');
$row['consent_json']=json_encode(array_merge($receipt,array('purpose'=>'general-treatment')));
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Cross-purpose reuse denied');
$row['consent_json']=json_encode($receipt);
$GLOBALS['safe']=true;
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Privacy safe mode hides case');
$GLOBALS['safe']=false;
$case=json_decode($row['case_json'],true);$case['baseline']='patient email: test@example.org';
$row['case_json']=json_encode($case);
confirm_case(is_wp_error(HE_V22_Research_Guard::validate_row($row)),'Direct identifier withheld');
echo "File 06 v12 current consent/legacy visibility: {$GLOBALS['tests']} isolated checks PASS.\n";

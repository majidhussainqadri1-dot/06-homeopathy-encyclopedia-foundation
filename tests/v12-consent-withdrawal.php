<?php
/** Isolated, adversarial consent-withdrawal command tests. Not WordPress staging. */
declare(strict_types=1);
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A'); define('MINUTE_IN_SECONDS',60);
class WP_Error {
	private $code,$data;
	public function __construct($code,$message='',$data=array()){$this->code=$code;$this->data=$data;}
	public function get_error_code(){return $this->code;}
	public function get_error_data(){return $this->data;}
}
class WP_REST_Response {
	public $data,$status;
	public function __construct($data,$status){$this->data=$data;$this->status=$status;}
}
class WP_REST_Request implements ArrayAccess {
	private $data;public function __construct($data){$this->data=$data;}
	public function offsetExists($offset):bool{return isset($this->data[$offset]);}
	public function offsetGet($offset):mixed{return $this->data[$offset]??null;}
	public function offsetSet($offset,$value):void{$this->data[$offset]=$value;}
	public function offsetUnset($offset):void{unset($this->data[$offset]);}
	public function get_json_params(){return $this->data;}
	public function get_header($name){return $name==='Idempotency-Key'?'v12-test-key-001':'';}
}
class HE_V2_API{const NS='sabri/v2/file-06';}
class HE_V2_Schema{
	const OPTION_SAFE_MODE='he_v2_safe_mode';
	public static function runtime_status(){return 'active';}
	public static function table($name){return 'wp_he_'.$name;}
	public static function record_runtime_failure($code,$reason){$GLOBALS['failures'][]=$code;}
}
class HE_V2_Auth{
	const CAP_RESEARCH='research';
	public static function rest_permission($cap,$post=0,$purpose=''){return $GLOBALS['auth_allowed']===true?true:new WP_Error('forbidden','Denied',array('status'=>403));}
	public static function require_nonce($r){return $GLOBALS['nonce'];}
}
class HE_V2_Domain {
	public static function rate_allow($key,$count,$seconds){return true;}
	public static function idempotent_begin($actor,$name,$key,$data){return array('id'=>17);}
	public static function idempotent_finish($id,$status,$body){$GLOBALS['idempotency_status']=$status;return true;}
	public static function emit_event($event,$type,$id,$payload){$GLOBALS['events'][]=array($event,$type,$id,$payload);return array('event_id'=>'uuid');}
}
class HE_V24_Migration_Safety{public static function ready(){return true;}}
class HE_V24_Future_Schema{
	public static function queue_impact($type,$id,$event,$payload){$GLOBALS['impact'][]=array($type,$id,$event);return 6;}
}
function sanitize_text_field($v){return trim((string)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function absint($v){return abs((int)$v);}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$val,$autoload=false){$GLOBALS['options'][$key]=$val;return true;}
function get_current_user_id(){return 7;}
function wp_json_encode($v){return json_encode($v);}
function wp_update_post($v,$error=false){$GLOBALS['posts'][]=$v;return $GLOBALS['post_ok']?$v['ID']:new WP_Error('post-failed');}
function must($v,$name){$GLOBALS['checks']++;if(!$v){fwrite(STDERR,"FAIL: {$name}\n");exit(1);}}
class DBConsent {
	public $queries=array(),$updates=array(),$reject=false;
	public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
	public function get_row($prepared,$format=null){return $GLOBALS['row'];}
	public function query($sql){
		$this->queries[]=$sql;
		if($this->reject && is_array($sql) && strpos($sql['sql'],'SET consent_json')!==false)return false;
		if(is_array($sql) && strpos($sql['sql'],'SET consent_json')!==false)return 1;
		return true;
	}
}
$wpdb=new DBConsent();
$GLOBALS['nonce']=true;$GLOBALS['auth_allowed']=true;$GLOBALS['options']=array();
$GLOBALS['checks']=0;$GLOBALS['post_ok']=true;$GLOBALS['posts']=array();$GLOBALS['events']=array();$GLOBALS['impact']=array();
$id='11111111-1111-4111-8111-111111111111';
$GLOBALS['row']=array('id'=>17,'post_id'=>77,'record_type'=>'successful-case','status'=>'published',
	'public_id'=>$id,'row_version'=>5,'consent_json'=>json_encode(array('verified'=>true,
	'policy_version'=>'case-policy-v1','locale'=>'ur-PK','purpose'=>'patient-story-research','state'=>'accepted')));
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-v12-research-consent.php';
$payload=array('id'=>$id,'policy_version'=>'case-policy-v1','locale'=>'ur-PK','purpose'=>'patient-story-research',
	'withdrawal_evidence_ref'=>'request:000001','row_version'=>5);
$GLOBALS['nonce']=false;
must(is_wp_error(HE_V12_Research_Consent::withdraw(new WP_REST_Request($payload))),'Reject invalid nonce');
$GLOBALS['nonce']=true;
$GLOBALS['auth_allowed']=false;
must(is_wp_error(HE_V12_Research_Consent::withdraw(new WP_REST_Request($payload))),'Reject unauthorized File00 actor');
$GLOBALS['auth_allowed']=true;
$wrong=$payload;$wrong['policy_version']='another-purpose';
must(is_wp_error(HE_V12_Research_Consent::withdraw(new WP_REST_Request($wrong))),'Reject policy mismatch');
$wrong=$payload;$wrong['row_version']=4;
must(is_wp_error(HE_V12_Research_Consent::withdraw(new WP_REST_Request($wrong))),'Reject stale CAS version');
$good=HE_V12_Research_Consent::withdraw(new WP_REST_Request($payload));
must($good instanceof WP_REST_Response && $good->status===200,'Withdraw subject-attested research');
must($good->data['consent_state']==='withdrawn','Consent withdrawal effective');
must(count($GLOBALS['posts'])===1&&$GLOBALS['posts'][0]['post_status']==='private','Published case is private');
must(count($GLOBALS['events'])===1&&$GLOBALS['events'][0][0]==='ResearchRecordRetracted.v1','Durable domain event emitted');
must(count($GLOBALS['impact'])===1,'Downstream owner-consumer invalidation');
must($GLOBALS['idempotency_status']===200,'Outcome recorded in idempotency ledger');
$wpdb->reject=true;$GLOBALS['post_ok']=true;$GLOBALS['events']=array();
$broken=HE_V12_Research_Consent::withdraw(new WP_REST_Request($payload));
must(is_wp_error($broken)&&$broken->get_error_code()==='he_v12_withdraw_failed','Failed DB mutation does not succeed');
must(count($GLOBALS['events'])===0,'No event on rolled back mutation');
echo "File 06 v12 consent withdrawal: {$GLOBALS['checks']} isolated checks PASS.\n";

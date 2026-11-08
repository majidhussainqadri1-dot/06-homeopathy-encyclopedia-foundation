<?php
/** Isolated File06↔00/05/10/12 exact owner source contracts (not deployed WP). */
declare(strict_types=1);define('ABSPATH',__DIR__);
class WP_Error {private $code;public function __construct($c,$m='',$d=array()){$this->code=$c;}public function get_error_code(){return $this->code;}}
class WP_REST_Request implements ArrayAccess {
	private $d;public function __construct(array $d){$this->d=$d;}
	public function offsetExists($o):bool{return isset($this->d[$o]);}
	public function offsetGet($o):mixed{return $this->d[$o]??null;}
	public function offsetSet($o,$v):void{$this->d[$o]=$v;}
	public function offsetUnset($o):void{unset($this->d[$o]);}
	public function get_json_params(){return $this->d;}
}
class HE_V2_API {const NS='sabri/v2/file-06';}
class HE_V2_Schema{
	const OPTION_SAFE_MODE='safe';
	public static function runtime_status(){return $GLOBALS['runtime'];}
	public static function table($suffix){return 'wp_he_'.$suffix;}
}
class HE_V2_Auth {
	const CAP_EDIT='edit';const CAP_REVIEW='review';
	public static function require_nonce($r){return $GLOBALS['nonce'];}
	public static function membership_allowed($u=0){return $GLOBALS['eligible'];}
	public static function rest_permission($cap,$post=0,$purpose=''){return true;}
	public static function is_founder($user_id=0){return true;}
}
class HE_V2_Domain {
	public static function concept_by_id($id,$private=false){return $GLOBALS['row'];}
	public static function is_public_concept($row){return $row['status']==='published';}
	public static function public_dto($row){return $GLOBALS['dto'];}
}
function absint($x){return abs((int)$x);}
function sanitize_text_field($x){return trim((string)$x);}
function sanitize_textarea_field($x){return trim((string)$x);}
function wp_http_validate_url($x){return 0===strpos((string)$x,'https://');}
function esc_url_raw($x){return (string)$x;}
function rest_ensure_response($x){return $x;}
function is_wp_error($x){return $x instanceof WP_Error;}
function get_current_user_id(){return $GLOBALS['actor'];}
function gmdate_adapter($x){return gmdate($x);}
function get_option($key,$fallback=null){return $GLOBALS['opts'][$key]??$fallback;}
function update_option($key,$value,$autoload=false){$GLOBALS['opts'][$key]=$value;return true;}
function must($v,$label){$GLOBALS['checks']++;if(!$v){fwrite(STDERR,"FAILED {$label}\n");exit(1);}}
function apply_filters($name,$value,...$params){
	if($name==='he_v12_file12_quote_assertion_v1'&&!empty($GLOBALS['proof_on'])){
		$claim=$params[0];
		return array('verified'=>true,'owner_file'=>'file-12',
			'exact'=>$claim['original_excerpt'],'edition_sha256'=>$claim['edition_sha256'],
			'page'=>$claim['page'],'rights_evidence_ref'=>$claim['rights_evidence_ref'],
			'source_title'=>$GLOBALS['source_title'],'edition_label'=>'1st',
			'rights_basis'=>'public-domain','edition_id'=>$claim['edition_id'],
			'citation_url'=>'https://example.org/library/pdf/edition/27');
	}
	return $value;
}
$GLOBALS['checks']=0;$GLOBALS['opts']=array();$GLOBALS['actor']=7;$GLOBALS['nonce']=true;
$GLOBALS['proof_on']=true;$GLOBALS['eligible']=true;$GLOBALS['runtime']='active';$GLOBALS['source_title']='Book A';
$uuid='11111111-1111-4111-8111-111111111111';
$GLOBALS['row']=array('id'=>13,'post_id'=>5,'public_id'=>$uuid,'status'=>'published','created_by'=>5);
$GLOBALS['dto']=array('id'=>$uuid,'version'=>3,'current_version'=>3,'is_historical'=>false,
	'references'=>array(
		array('id'=>901,'title'=>'Book A','edition'=>'1st'),
		array('id'=>902,'title'=>'Book B','edition'=>'2nd')));
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-v12-reviewed-sources.php';
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-v12-case-consent-owner.php';
$assert=HE_V12_Case_Consent_Owner::concept_assertion(null,$uuid,3);
must(is_array($assert)&&$assert['published']===true&&$assert['owner_file']==='file-06','Canonical review and publication attested');
must(null===HE_V12_Case_Consent_Owner::concept_assertion(null,$uuid,4),'Outdated canonical version rejected');
$other='22222222-2222-4222-8222-222222222222';
must(null===HE_V12_Case_Consent_Owner::concept_assertion(null,$other,3),'Unrelated UUID rejected');
$GLOBALS['row']['status']='retracted';
must(null===HE_V12_Case_Consent_Owner::concept_assertion(null,$uuid,3),'Retracted owner knowledge denied');
$GLOBALS['row']['status']='published';
must(HE_V12_Reviewed_Sources::column($GLOBALS['dto'],0)===null,'Source absent without stored approval');
$original='An authentic exact text from an independently rights-reviewed public domain edition.';
$translation='یہ ماخذ سے حاصل کردہ اقتباس کا علمی ترجمہ ہے۔';
$stage=array('id'=>$uuid,'position'=>0,'concept_version'=>3,'reference_id'=>901,
	'original_excerpt'=>$original,'translation'=>$translation,
	'translation_locale'=>'ur-PK','review_note'=>'Original and translation compared',
	'edition_id'=>27,'edition_version'=>4,'edition_sha256'=>str_repeat('a',64),
	'page'=>84,'rights_evidence_ref'=>'rights:public-domain-2026');
$r=HE_V12_Reviewed_Sources::stage(new WP_REST_Request($stage));
must(is_array($r)&&$r['state']==='pending','Source text staged pending independent scientific review');
must(HE_V12_Reviewed_Sources::column($GLOBALS['dto'],0)===null,'Pending quote not public');
$sha=$r['source_hash'];
$approve=new WP_REST_Request(array('id'=>$uuid,'position'=>0,'content_sha256'=>$sha,
	'source_and_translation_compared'=>true,'review_evidence_ref'=>'review:2026-complete'));
$reject=HE_V12_Reviewed_Sources::approve($approve);
must(is_wp_error($reject),'Original submitter may not self-approve translation');
$GLOBALS['actor']=12;
$ok=HE_V12_Reviewed_Sources::approve($approve);
must(is_array($ok)&&$ok['state']==='approved','Independent human review accepted');
$col=HE_V12_Reviewed_Sources::column($GLOBALS['dto'],0);
must(is_array($col)&&$col['original_excerpt']===$original,'Approved original publicly visible with exact rights');
must($col['translation']===$translation&&$col['page']===84,'Human-reviewed translation and citation preserved');
$GLOBALS['proof_on']=false;
must(HE_V12_Reviewed_Sources::column($GLOBALS['dto'],0)===null,'File12 rights/source provider withdrawal removes quote');
$GLOBALS['proof_on']=true;$GLOBALS['source_title']='A different book';
must(HE_V12_Reviewed_Sources::column($GLOBALS['dto'],0)===null,'Different edition cannot inherit unrelated bibliography');
$GLOBALS['source_title']='Book A';$GLOBALS['dto']['version']=4;$GLOBALS['dto']['current_version']=4;
must(HE_V12_Reviewed_Sources::column($GLOBALS['dto'],0)===null,'Changed concept version requires new review');
echo "File06 multi-owner canonical, consent and reviewed quotations: {$GLOBALS['checks']} isolated assertions PASS.\n";

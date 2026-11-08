<?php
/** Isolated source-contract tests for File 06 Master Plan v12 (not live tests). */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HE_VERSION', '2.4.20' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = array() ) { $this->code=$code; }
	public function get_error_code() { return $this->code; }
}
class WP_REST_Request implements ArrayAccess {
	private $data;
	public function __construct( $data ) { $this->data = $data; }
	public function offsetExists( $key ): bool { return isset( $this->data[ $key ] ); }
	public function offsetGet( $key ): mixed { return $this->data[ $key ] ?? null; }
	public function offsetSet( $key, $value ): void { $this->data[ $key ] = $value; }
	public function offsetUnset( $key ): void { unset( $this->data[ $key ] ); }
	public function get_param( $key ) { return $this->data[ $key ] ?? null; }
	public function get_json_params() { return $this->data; }
}
class HE_V2_API { const NS = 'sabri/v2/file-06'; }
class HE_V2_Auth {
	const CAP_EDIT='edit'; const CAP_RESEARCH='research';
	public static function rest_permission( $cap ) { return true; }
	public static function require_nonce( $request ) { return ! empty( $GLOBALS['nonce'] ); }
}
class HE_V2_Schema {
	public static function runtime_status() { return $GLOBALS['runtime'] ?? 'active'; }
	public static function table( $name ) { return 'wp_he_' . $name; }
	public static function record_runtime_failure( $code, $message ) { $GLOBALS['failures'][] = $code; }
}
class HE_V24_Migration_Safety { public static function ready() { return true; } }
class HE_V24_Future_Schema {
	public static function table( $name ) { return 'wp_he_' . $name; }
	public static function public_claims( $id ) { return array( array( 'public_id' => '44444444-4444-4444-8444-444444444444', 'claim_text' => 'Reviewer approved source claim' ) ); }
	public static function append_provenance( $type, $id, $action, $url = '', $metadata = array() ) { $GLOBALS['provenance'][] = [$type,$id,$action]; return 'proof'; }
	public static function queue_impact( $type, $id, $name, $payload = array(), $consumers = array(), $notify = true ) { $GLOBALS['impact'][] = [$type,$id,$name]; return isset( $GLOBALS['impact_failure'] ) && $GLOBALS['impact_failure'] ? 0 : 6; }
}
class HE_V2_Domain {
	const ENTRY_TYPE = 'he_entry';
	public static function concept_by_id( $id, $private = false ) { return $GLOBALS['concept']; }
	public static function concept_by_post_id( $id ) { return $GLOBALS['concept']; }
	public static function is_public_concept( $row ) { return $row['status'] === 'published' && empty( $row['merged_into_id'] ); }
	public static function public_dto( $row ) { return $GLOBALS['dto']; }
}
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function rest_ensure_response( $v ) { return $v; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function esc_url_raw( $v ) { return $v; }
function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function wp_parse_url( $v ) { return parse_url( $v ); }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function do_action( $name, ...$args ) { $GLOBALS['actions'][] = $name; }
function apply_filters( $name, $default, $payload = array(), ...$args ) {
 if ( $name === 'he_v12_consent_receipt_verification_v1' && ! empty( $GLOBALS['verified_receipts'] ) ) {
   return array( 'verified' => true, 'authority' => 'file-00', 'evidence_ref' => $payload['evidence_ref'], 'policy_version' => $payload['policy_version'], 'locale' => $payload['locale'], 'purpose' => $payload['purpose'], 'verification_id' => 'file00-verification-0001' );
 }
 if ( $name === 'he_v12_related_resource_owner_attested_v1' ) { return ! empty( $GLOBALS['resource_authorized'] ); }
 if ( $name === 'he_v12_canonical_related_resources' ) { return $GLOBALS['related_links'] ?? $default; }
 return $default;
}
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $v, $domain = '' ) { return esc_html( $v ); }
function is_singular( $type ) { return true; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = false ) { $GLOBALS['options'][ $name ] = $value; return true; }
function wp_http_validate_url( $url ) { return ! str_contains( $url, 'localhost' ); }
function wp_safe_remote_head( $url, $args ) { return array( 'code' => str_contains( $url, 'missing' ) ? 404 : 200 ); }
function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
function assert_true( $ok, $message ) {
	$GLOBALS['checks']++;
	if ( ! $ok ) { fwrite( STDERR, 'FAILED v12: '.$message.PHP_EOL ); exit( 1 ); }
}
class DBV12 {
	public $ref_rows = array(), $last_sql = '', $updates = array(), $rows = array(), $query_count = 0;
	public function prepare( $sql, ...$args ) { return array( 'sql' => $sql, 'args' => $args ); }
	public function get_results( $sql, $format = null ) {
		$s = is_array( $sql ) ? $sql['sql'] : $sql;
		if ( str_contains( $s, 'SELECT id,url,link_status' ) ) { return $this->ref_rows; }
		if ( str_contains( $s, 'SELECT DISTINCT c.id' ) ) { return $this->rows; }
		return array();
	}
	public function get_row( $sql, $format = null ) {
		if ( str_contains( $sql['sql'], 'freshness' ) ) { return $GLOBALS['freshness_fixture'] ?? array( 'freshness_state'=>'stale', 'risk_tier'=>'normal', 'last_human_review'=>'2025-01-01 00:00:00','review_due_at'=>'2026-01-01 00:00:00', 'updated_at'=>'2026-01-01' ); }
		return null;
	}
	public function update( $table, $changes, $where, $format = array(), $wformat = array() ) {
		$this->updates[] = [$table,$changes,$where]; return 1;
	}
	public function query( $sql ) { $this->query_count++; return 1; }
}
$wpdb = new DBV12(); $post = (object) array( 'ID' => 19 );
$GLOBALS['checks']=0; $GLOBALS['options']=array(); $GLOBALS['nonce']=true; $GLOBALS['verified_receipts']=true;
$GLOBALS['concept']=array('id'=>7,'public_id'=>'11111111-1111-4111-8111-111111111111','status'=>'published','merged_into_id'=>0);
$GLOBALS['dto']=array('id'=>$GLOBALS['concept']['public_id'],'type'=>'remedy','language'=>'ur','title'=>'Source-linked remedy','summary'=>'Public summary',
	'canonical_url'=>'https://example.org/encyclopedia/entry/example','version'=>3,'current_version'=>3,'is_historical'=>false,
	'freshness'=>array('updated_at'=>'2026-11-08 00:00:00'),
	'references'=>array(
		array('id'=>901,'title'=>'Book A','edition'=>'1st','page_locator'=>'12','rights_status'=>'citation-only','url'=>'https://example.org/a?access_token=secret#section','source_type'=>'classical-primary'),
		array('id'=>902,'title'=>'Book B','edition'=>'2nd','page_locator'=>'77','rights_status'=>'citation-only','url'=>'https://example.org/b','source_type'=>'classical-secondary')
	));
require dirname( __DIR__ ) . '/homeopathy-encyclopedia/includes/class-he-v12-completion.php';
require dirname( __DIR__ ) . '/homeopathy-encyclopedia/includes/class-he-v12-evidence-impact.php';
$id=$GLOBALS['concept']['public_id'];
$att=HE_V12_Completion::consent_attestation(array('consent_version'=>'policy-v3','consent_locale'=>'ur-PK','consent_evidence_ref'=>'receipt:00001'),11,true,'successful-case');
assert_true(is_array($att),'Valid consent attestation');
assert_true($att['purpose']==='patient-story-research','Purpose bound consent');
assert_true($att['recorded_by']===11 && $att['state']==='accepted','Consent actor/state');
assert_true($att['verified_by']==='file-00' && $att['evidence_ref']==='receipt:00001','File 00 proof attribution');
$GLOBALS['verified_receipts']=false;
assert_true(is_wp_error(HE_V12_Completion::consent_attestation(array('consent_version'=>'policy-v3','consent_locale'=>'ur-PK','consent_evidence_ref'=>'receipt:00001'),11,true,'successful-case')),'Self-asserted consent rejected without independent owner');
$GLOBALS['verified_receipts']=true;
assert_true(is_wp_error(HE_V12_Completion::consent_attestation(array('consent_locale'=>'en-US'),11,true,'successful-case')),'Missing policy version rejected');
assert_true(is_wp_error(HE_V12_Completion::consent_attestation(array('consent_version'=>'v3'),11,true,'successful-case')),'Missing locale rejected');
assert_true(HE_V12_Completion::consent_attestation(array(),11,false,'proposal')['state']==='unverified','No inferred blanket consent');
$exp=HE_V12_Completion::export_entry(new WP_REST_Request(array('id'=>$id)));
assert_true($exp['format']==='file06-scholarly-export-v1','Versioned public export');
assert_true($exp['canonical_public_id']===$id && $exp['version']===3,'Canonical provenance retained');
assert_true(count($exp['sources'])===2,'Bibliographic sources exported');
assert_true(count($exp['approved_current_claims'])===1,'Reviewed claim-level evidence included');
assert_true(!array_key_exists('id',$exp['sources'][0]),'Private/internal reference keys excluded');
assert_true(!array_key_exists('body',$exp),'No unrestricted manuscript export');
assert_true($exp['sources'][0]['url']==='https://example.org/a','Bibliographic export strips query tokens');
$conflict=HE_V12_Completion::import_preview(new WP_REST_Request(array('format'=>$exp['format'],'canonical_public_id'=>$id,'version'=>3,'record_digest'=>str_repeat('0',64))));
assert_true($conflict['mapping']==='source-content-conflict','Equal version cannot hide different source content');
$cmp=HE_V12_Completion::compare_references(new WP_REST_Request(array('id'=>$id,'left'=>0,'right'=>1)));
assert_true($cmp['columns'][0]['source']['edition']==='1st','Edition 1 identity');
assert_true($cmp['columns'][1]['source']['page_locator']==='77','Edition 2 page identity');
assert_true($cmp['columns'][0]['original_excerpt']===null,'No fabricated source text');
assert_true(is_wp_error(HE_V12_Completion::compare_references(new WP_REST_Request(array('id'=>$id,'left'=>0,'right'=>0)))),'Same reference rejected');
$map=HE_V12_Completion::import_preview(new WP_REST_Request(array('format'=>$exp['format'],'canonical_public_id'=>$id,'version'=>3,'record_digest'=>$exp['record_digest'])));
assert_true($map['mapping']==='same-canonical-version' && $map['write_allowed']===false,'Reimport cannot overwrite owner');
$GLOBALS['nonce']=false;
assert_true(is_wp_error(HE_V12_Completion::import_preview(new WP_REST_Request($exp))),'Import map preview requires nonce');
$GLOBALS['nonce']=true;
$enriched=HE_V12_Completion::public_entry_enrichment('<article data-he-entry-id="'.$id.'"><header><h2>Example</h2></header><div>Body</div></article>');
assert_true(str_contains($enriched,'Knowledge freshness'),'Public review freshness displayed');
assert_true(str_contains($enriched,'Outdated review'),'Staleness disclosed');
assert_true(str_contains($enriched,'aria-label="Knowledge review freshness"'),'Accessible notice');
$GLOBALS['freshness_fixture']=array('freshness_state'=>'current','risk_tier'=>'critical','last_human_review'=>'2025-01-01 00:00:00','review_due_at'=>'2026-01-01 00:00:00');
$overdue=HE_V12_Completion::public_entry_enrichment('<article data-he-entry-id="'.$id.'"><header>Title</header></article>');
assert_true(str_contains($overdue,'Urgent human review required'),'Expired critical review cannot be falsely current');
unset($GLOBALS['freshness_fixture']);
$GLOBALS['related_links']=array(array('eligible'=>true,'rights_ok'=>true,'owner_file'=>'file-12','source_version'=>3,'url'=>'https://example.org/pdf','label'=>'Verified PDF Edition'));
$GLOBALS['resource_authorized']=false;
assert_true(!str_contains(HE_V12_Completion::public_entry_enrichment('<article data-he-entry-id="'.$id.'"><header>Title</header></article>'),'Verified PDF Edition'),'Related link without owner attestation denied');
$GLOBALS['resource_authorized']=true;
assert_true(str_contains(HE_V12_Completion::public_entry_enrichment('<article data-he-entry-id="'.$id.'"><header>Title</header></article>'),'Verified PDF Edition'),'Owner-attested current PDF link allowed');
$GLOBALS['related_links']=array();
$GLOBALS['concept']['status']='retracted';
assert_true(is_wp_error(HE_V12_Completion::export_entry(new WP_REST_Request(array('id'=>$id)))),'Retracted export excluded');
$GLOBALS['concept']['status']='published';
$GLOBALS['dto']['is_historical']=true;
assert_true(is_wp_error(HE_V12_Completion::export_entry(new WP_REST_Request(array('id'=>$id)))),'Historical export excluded');
$GLOBALS['dto']['is_historical']=false;
$wpdb->ref_rows=array(array('id'=>3,'url'=>'https://example.org/missing','link_status'=>'unchecked'),array('id'=>4,'url'=>'https://example.org/available','link_status'=>'unchecked'));
assert_true(HE_V12_Completion::check_reference_links(2)===2,'Bounded link scan');
assert_true($wpdb->updates[0][1]['link_status']==='suspect','First HEAD 404 marked suspect, not proven broken');
$wpdb->ref_rows=array(array('id'=>3,'url'=>'https://example.org/missing','link_status'=>'suspect'));
assert_true(HE_V12_Completion::check_reference_links(1)===1,'Second status check processed');
assert_true(end($wpdb->updates)[1]['link_status']==='broken','Repeated 404 confirms broken source');
assert_true($wpdb->updates[1][1]['link_status']==='ok','200 marked OK');
assert_true(get_option('he_v12_reference_link_cursor')===4,'Resumable scan checkpoint');
$wpdb->rows=array(array('id'=>11,'concept_id'=>7,'version_id'=>4,'review_status'=>'approved','row_version'=>2,'public_id'=>$id));
$GLOBALS['provenance']=array();$GLOBALS['impact']=array();
$result=HE_V12_Evidence_Impact::revalidate_crossref(array('id'=>51,'provider'=>'crossref','external_id'=>'10.1234/example'),array('retraction'));
assert_true($result===true,'Exact linked claim revalidation');
assert_true($wpdb->query_count>=1 && count($GLOBALS['provenance'])===1,'Claim invalidation and provenance');
assert_true(count($GLOBALS['impact'])===1,'Downstream consumers queued');
$wpdb->rows=array_fill(0,101,array('id'=>11,'concept_id'=>7,'version_id'=>4,'review_status'=>'approved','row_version'=>2,'public_id'=>$id));
assert_true(is_wp_error(HE_V12_Evidence_Impact::revalidate_crossref(array('id'=>51,'provider'=>'crossref','external_id'=>'10.1234/example'),array('retraction'))),'Large DOI fan-out is bounded and retried');
echo "File 06 v12 isolated safety, source, export, compare and claim-impact assertions PASS ({$GLOBALS['checks']}).\n";

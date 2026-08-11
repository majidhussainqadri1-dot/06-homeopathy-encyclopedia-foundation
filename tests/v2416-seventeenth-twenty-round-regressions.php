<?php
/** File 06 v2.4.16 — seventeenth fresh twenty-round regression matrix. */
$root = dirname(__DIR__);
$inc = $root . '/homeopathy-encyclopedia/includes';
$round = (int) ( getenv('FILE06_REVIEW_ROUND') ?: 20 );
$fail = array();
function v2416_read($path){$v=file_get_contents($path);if(false===$v){throw new RuntimeException($path);}return $v;}
function v2416_ok($ok,$message){global $fail;if(!$ok)$fail[]=$message;}
function v2416_has($haystack,$needle){return false!==strpos($haystack,$needle);}
$gov=v2416_read($inc.'/class-he-v22-governance.php');
$third=v2416_read($inc.'/class-he-v242-third-audit.php');
$schema=v2416_read($inc.'/class-he-v2-schema.php');
$admin=v2416_read($inc.'/class-he-v2-admin.php');
$domain=v2416_read($inc.'/class-he-v2-domain.php');
$api=v2416_read($inc.'/class-he-v2-api.php');
$privacy=v2416_read($inc.'/class-he-v2-privacy.php');
$boot=v2416_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v2416_read($root.'/homeopathy-encyclopedia/readme.txt');
$review=v2416_read($root.'/docs/FILE-06-v2.4.16-SEVENTEENTH-TWENTY-ROUND-REVIEW.md');
if($round>=1){
 v2416_ok(!preg_match("#/research/\(\?P<id>\\\\d\+\)/(?:review|integrity)#",$gov),'R1 numeric v2.2 research route remains');
 v2416_ok(v2416_has($gov,"WHERE public_id=%s")&&v2416_has($gov,"research-integrity/(?P<id>' . $uuid . ')/apply"),'R1 canonical v2.2 research governance contract missing');
 v2416_ok(v2416_has($gov,"'id' => $action_public_id")&&v2416_has($gov,"HE_V2_Domain::encode_public_cursor( 'review'"),'R1 internal review/integrity identifiers still exposed');
}
if($round>=2){
 v2416_ok(v2416_has($third,'it must never mutate state after idempotency finalization')&&!v2416_has($third,"'row_version' => (int) $row['row_version'] + 1"),'R2 post-success research mutation remains');
}
if($round>=3){
 v2416_ok(v2416_has($schema,'public static function required_columns()')&&v2416_has($schema,'SHOW COLUMNS FROM')&&v2416_has($schema,'$current >= HE_SCHEMA_VERSION && self::schema_complete()'),'R3 schema-shape readiness missing');
}
if($round>=4){
 v2416_ok(v2416_has($admin,'Safe mode remains active because verified repair did not establish a healthy runtime.')&&!v2416_has($admin,'update_option( HE_V2_Schema::OPTION_SAFE_MODE, $enabled ? 1 : 0'),'R4 direct safe-mode disable bypass remains');
}
if($round>=5){
 v2416_ok(v2416_has($admin,"guard_entry_admin_write")&&v2416_has($admin,"entry_admin_concurrency_conflict")&&v2416_has($admin,"review_status='unreviewed',safety_status='unreviewed'"),'R5 entry admin concurrency fencing missing');
}
if($round>=6){
 v2416_ok(v2416_has($admin,'$loaded_expected = isset( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ]')&&v2416_has($admin,"$expected = $loaded_expected ?: (int) $row['row_version'];"),'R6 research editor-loaded CAS missing');
}
if($round>=7){
 v2416_ok(v2416_has($domain,'entry_create_compensation_start_failed')&&v2416_has($domain,'research_create_compensation_start_failed')&&v2416_has($domain,"remove_filter( 'pre_delete_post', $delete_guard, 1 )"),'R7 transactional create compensation missing');
}
if($round>=8){
 v2416_ok(v2416_has($domain,"SELECT id,concept_id,alias_type,is_primary")&&v2416_has($domain,"SET is_primary=0 WHERE concept_id=%d AND id<>%d"),'R8 canonical alias promotion persistence missing');
}
if($round>=9){
 v2416_ok(v2416_has($domain,"INNER JOIN ' . $wpdb->posts . \" p ON p.ID=c.post_id AND p.post_type=%s AND p.post_status='publish' WHERE \"")&&v2416_has($domain,'$params = array( self::ENTRY_TYPE, $cursor );'),'R9 public search WordPress parity missing');
}
if($round>=10){
 v2416_ok(v2416_has($domain,"$public_metadata = array_intersect_key")&&v2416_has($domain,"( $private || 'public' === $row['data_class'] )")&&v2416_has($domain,"home_url( '/research/' . rawurlencode( $row['public_id'] )"),'R10 public research DTO minimization missing');
}
if($round>=11){
 v2416_ok(substr_count($domain,'he_entry_post_missing')>=2,'R11 missing authoritative-entry fail-closed checks incomplete');
}
if($round>=12){
 v2416_ok(v2416_has($third,"$next = $approved_reviews > 0 ? 'peer_review' : 'proposal';")&&v2416_has($third,'authoritative-wordpress-post-not-published'),'R12 research WordPress/domain parity reconciliation missing');
}
if($round>=13){
 v2416_ok(v2416_has($privacy,'Canonical draft hard-delete is governance-blocked')&&v2416_has($privacy,'unpublished governed draft could not be de-identified')&&!v2416_has($privacy,"wp_delete_post( $post_id, true );\n\t\t\t\t$removed = true;"),'R13 privacy erasure still claims blocked draft deletion');
}
if($round>=14){
 v2416_ok(v2416_has($api,"encode_public_cursor( 'reference'")&&v2416_has($api,"decode_public_cursor( 'reference'")&&v2416_has($api,'he_reference_public_id_required'),'R14 opaque reference command identifiers missing');
}
if($round>=15){
 v2416_ok(v2416_has($admin,"$notice = array( 'type' => 'error', 'message' => $result->get_error_message() )"),'R15 admin repair error signaling missing');
}
if($round>=16){v2416_ok(v2416_has($review,'16. **CLEAN**'),'R16 clean review record missing');}
if($round>=17){v2416_ok(v2416_has($review,'17. **CLEAN**'),'R17 clean review record missing');}
if($round>=18){v2416_ok(v2416_has($review,'18. **CLEAN**'),'R18 clean review record missing');}
if($round>=19){
 v2416_ok(v2416_has($boot,' * Version: 2.4.16')&&v2416_has($boot,"define( 'HE_VERSION', '2.4.16' )")&&v2416_has($boot,"define( 'HE_CONTRACT_VERSION', '2.4.16' )")&&v2416_has($boot,"'future_hardening_version'=>'2.4.16'")&&v2416_has($readme,'Stable tag: 2.4.16'),'R19 release truth not aligned to 2.4.16');
}
if($round>=20){v2416_ok(v2416_has($review,'20. **CLEAN**'),'R20 final clean review record missing');}
for($i=1;$i<=$round;$i++){v2416_ok(v2416_has($review,$i.'. **'),'Review ledger missing round '.$i);}
if($fail){fwrite(STDERR,"File 06 v2.4.16 seventeenth twenty-round regressions FAILED at round {$round}:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.16 seventeenth twenty-round regressions through round {$round}: PASS\n";

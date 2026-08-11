<?php
/** File 06 v2.4.6 seventh fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v246_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v246_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$domain=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v246_ok(false!==strpos($domain,"'status' => 'publish' === \$post->post_status ? 'published' : 'proposal',"),'R1 research first-save state must fail closed');
v246_ok(false===strpos($domain,"'status' => 'draft' === \$post->post_status ? 'proposal' : 'published',"),'R1 non-draft must not imply published');
$schema=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v24-future-schema.php');
$future_api=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v24-future-api.php');
v246_ok(false!==strpos($schema,'refresh_freshness( $concept_id, $persist = true )'),'R2 freshness persistence switch missing');
v246_ok(false!==strpos($future_api,"refresh_freshness( \$concept['id'], false )"),'R2 public freshness GET still persists');
$core_api=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
v246_ok(false!==strpos($core_api,"/health") && false!==strpos($core_api,"HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR )"),'R3 health route bypasses File00-backed authorization');
v246_ok(false===strpos($core_api,"return current_user_can( 'activate_plugins' )"),'R3 legacy health capability bypass remains');
$domain=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v246_ok(0===substr_count($domain,"'show_in_rest' => true,"),'R4 File06 taxonomies still expose uncontrolled core REST writes');
v246_ok(substr_count($domain,"'show_in_rest' => false,")>=5,'R4 controlled REST ownership marker missing');
$public_guard=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-public-guard.php');
v246_ok(false!==strpos($public_guard,"'public' === \$row['data_class']") && false!==strpos($public_guard,"case_anonymized") && false!==strpos($public_guard,"case_consent_verified"),'R5 successful-case public governance gate incomplete');
v246_ok(false===strpos($public_guard,"'successful-case' === \$row['record_type'] ? json_decode"),'R5 unconditional successful-case rendering remains');
$public_guard=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-public-guard.php');
v246_ok(false!==strpos($public_guard,"Research record unavailable"),'R6 non-public research title still leaks');
v246_ok(false!==strpos($public_guard,"if ( ! self::is_public_row( \$row ) )") && false!==strpos($public_guard,"['noarchive'] = true"),'R6 non-public research robots fail-closed gate missing');
$dto=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v241-public-dto-guard.php');
v246_ok(substr_count($dto,"status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0")>=2,'R7 public DTO concept lookups are not publication-gated');
/*__V246_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.6 seventh-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.6 seventh-review regressions: PASS
";

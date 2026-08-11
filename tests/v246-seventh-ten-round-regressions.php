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
/*__V246_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.6 seventh-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.6 seventh-review regressions: PASS
";

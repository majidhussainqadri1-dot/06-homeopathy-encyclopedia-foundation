<?php
/** File 06 v2.4.6 seventh fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v246_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v246_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$domain=v246_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v246_ok(false!==strpos($domain,"'status' => 'publish' === \$post->post_status ? 'published' : 'proposal',"),'R1 research first-save state must fail closed');
v246_ok(false===strpos($domain,"'status' => 'draft' === \$post->post_status ? 'proposal' : 'published',"),'R1 non-draft must not imply published');
/*__V246_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.6 seventh-review regressions FAILED:
- ".implode("
- ",$fail)."
");exit(1);}echo "File 06 v2.4.6 seventh-review regressions: PASS
";

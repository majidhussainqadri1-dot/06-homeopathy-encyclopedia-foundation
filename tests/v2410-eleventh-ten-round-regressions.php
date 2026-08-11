<?php
/** File 06 v2.4.10 eleventh fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v2410_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v2410_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$v22=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v2410_ok(false!==strpos($v22,'$public_eligible = $row') && false!==strpos($v22,'case_consent_verified') && false!==strpos($v22,'case_anonymized') && false!==strpos($v22,'X-Robots-Tag: noindex, nofollow, noarchive'),'R1 research permanent-ID route can render restricted/unconsented research content');
/*__V2410_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.10 eleventh-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.10 eleventh-review regressions: PASS\n";

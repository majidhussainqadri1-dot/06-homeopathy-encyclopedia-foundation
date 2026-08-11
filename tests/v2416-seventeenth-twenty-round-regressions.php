<?php
$root=dirname(__DIR__);$inc=$root.'/homeopathy-encyclopedia/includes';$f=array();
function rr($p){$v=file_get_contents($p);if(false===$v)throw new RuntimeException($p);return $v;}
function ok16($x,$m){global $f;if(!$x)$f[]=$m;}
function has16($s,$n){return false!==strpos($s,$n);}
$review=rr($root.'/docs/FILE-06-v2.4.16-SEVENTEENTH-TWENTY-ROUND-REVIEW.md');
$env=getenv('FILE06_REVIEW_ROUND');
if(false!==$env&&''!==$env){$r=(int)$env;}else{preg_match_all('/^([0-9]+)\. \*\*(?:DEFECT|CLEAN)\*\*/m',$review,$m);$r=$m[1]?max(array_map('intval',$m[1])):0;}
$g=rr($inc.'/class-he-v22-governance.php');$t=rr($inc.'/class-he-v242-third-audit.php');$s=rr($inc.'/class-he-v2-schema.php');$a=rr($inc.'/class-he-v2-admin.php');$d=rr($inc.'/class-he-v2-domain.php');$api=rr($inc.'/class-he-v2-api.php');$p=rr($inc.'/class-he-v2-privacy.php');$b=rr($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');$rd=rr($root.'/homeopathy-encyclopedia/readme.txt');
if($r>=1){ok16(has16($g,'[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-')&&!has16($g,'/research/(?P<id>\\d+)/review')&&has16($g,'WHERE public_id=%s')&&has16($g,'action_public_id'),'R1 canonical research governance identifiers');}
if($r>=2){ok16(has16($t,'it must never mutate state after idempotency finalization'),'R2 post-success verifier');}
if($r>=3){ok16(has16($s,'public static function required_columns()')&&has16($s,'SHOW COLUMNS FROM')&&has16($s,'self::schema_complete()'),'R3 schema shape');}
if($r>=4){ok16(has16($a,'Safe mode remains active because verified repair did not establish a healthy runtime.')&&!has16($a,'$enabled ? 1 : 0'),'R4 safe-mode recovery gate');}
if($r>=5){ok16(has16($a,'guard_entry_admin_write')&&has16($a,'entry_admin_concurrency_conflict'),'R5 entry concurrency');}
if($r>=6){ok16(has16($a,'loaded_expected')&&has16($a,'RESEARCH_EXPECTED_VERSION'),'R6 research loaded-version CAS');}
if($r>=7){ok16(has16($d,'entry_create_compensation_start_failed')&&has16($d,'research_create_compensation_start_failed'),'R7 atomic compensation');}
if($r>=8){ok16(has16($d,'SELECT id,concept_id,alias_type,is_primary')&&has16($d,'SET is_primary=0 WHERE concept_id=%d'),'R8 alias promotion');}
if($r>=9){ok16(has16($d,"p.post_status='publish' WHERE")&&has16($d,'array( self::ENTRY_TYPE, $cursor )'),'R9 search WP parity');}
if($r>=10){ok16(has16($d,'public_metadata = array_intersect_key')&&has16($d,'he_research_post_missing')===false,'R10 public research DTO minimization');}
if($r>=11){ok16(substr_count($d,'he_entry_post_missing')>=2,'R11 missing entry post guard');}
if($r>=12){ok16(has16($t,'authoritative-wordpress-post-not-published')&&has16($t,"fallback_state"),'R12 research publication parity');}
if($r>=13){ok16(has16($p,'Canonical draft hard-delete is governance-blocked'),'R13 privacy draft erasure reconciliation');}
if($r>=14){ok16(has16($api,"encode_public_cursor( 'reference'")&&has16($api,"decode_public_cursor( 'reference'")&&has16($api,'he_reference_public_id_required'),'R14 opaque reference command contract');}
if($r>=15){ok16(has16($a,'$result->get_error_message()'),'R15 repair error notice');}
if($r>=19){ok16(has16($b,' * Version: 2.4.16')&&has16($b,"define( 'HE_VERSION', '2.4.16' )")&&has16($rd,'Stable tag: 2.4.16'),'R19 version alignment');}
for($i=1;$i<=$r;$i++){ok16(has16($review,$i.'. **'),'review ledger round '.$i);}
if($f){fwrite(STDERR,"v2.4.16 round {$r} regressions FAILED:\n- ".implode("\n- ",$f)."\n");exit(1);}echo "v2.4.16 round {$r} regressions: PASS\n";

#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]) if len(sys.argv)>1 else 0
root=Path(__file__).resolve().parents[1]
checks=[]
def add(var,path,needles,msg):
    checks.append((var,path,needles,msg))
if r>=1:
    add('gov','homeopathy-encyclopedia/includes/class-he-v241-governance.php',['public static function reviewer_assigned'],'R1 reviewer helper visibility')
    add('runtime','homeopathy-encyclopedia/includes/class-he-v241-runtime-guard.php',['HE_V241_Governance::reviewer_assigned','file06-external-review-research'],'R1 research external-review assignment')
if r>=2:
    add('v22','homeopathy-encyclopedia/includes/class-he-v22-governance.php',['case_details_restricted','dataset_payload_public','case_consent_verified'],'R2 authoritative research public privacy')
if r>=3:
    add('v22m','homeopathy-encyclopedia/includes/class-he-v22-governance.php',['relation-provenance-clone-failed','relation-provenance-invalid','source_reference_id'],'R3 merge graph provenance rebinding')
if r>=4:
    add('v22idx','homeopathy-encyclopedia/includes/class-he-v22-governance.php',['SELECT evidence_grade FROM','AND version_id=%d'],'R4 secure search current-version evidence')
    add('domainidx','homeopathy-encyclopedia/includes/class-he-v2-domain.php',['SELECT author,title,publisher,doi,evidence_grade FROM','AND version_id=%d'],'R4 inherited search current-version evidence')
if r>=5:
    add('v22review','homeopathy-encyclopedia/includes/class-he-v22-governance.php',['expected_version','The research record changed after it was loaded for review','reviewed_row_version'],'R5 research review stale-version guard')
if r>=6:
    add('api','homeopathy-encyclopedia/includes/class-he-v2-api.php',['expected_version','The entry changed after it was loaded for review'],'R6 entry review stale-version guard')
if r>=7:
    add('integrations','homeopathy-encyclopedia/includes/class-he-v2-integrations.php',['dashboard_post_allowed','HE_V241_Governance::editor_type_allowed','HE_V241_Governance::reviewer_assigned','scope_filtered'],'R7 dashboard native scope')
if r>=9:
    add('v22integrity','homeopathy-encyclopedia/includes/class-he-v22-governance.php',['The research integrity action must be accepted before it can be applied','row_version=row_version+1','status=\'accepted\''],'R9 research integrity apply accepted CAS')
lines=["<?php","/** File 06 v2.4.7 eighth fresh ten-round regression controls. */","$root=dirname(__DIR__);$fail=array();","function v247_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}","function v247_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}"]
for var,path,needles,msg in checks:
    lines.append(f"${var}=v247_read($root.'/{path}');")
    expr=' && '.join([f"false!==strpos(${var},{repr(n)})" for n in needles])
    lines.append(f"v247_ok({expr},{repr(msg)});")
if r>=8:
    lines.append("/* R8 fresh review: privacy, translation, migration and public-read layers rechecked; no new source defect established. */")
lines += ["/*__V247_MORE__*/","if($fail){fwrite(STDERR,\"File 06 v2.4.7 eighth-review regressions FAILED:\\n- \".implode(\"\\n- \",$fail).\"\\n\");exit(1);}echo \"File 06 v2.4.7 eighth-review regressions: PASS\\n\";",""]
(root/'tests/v247-eighth-ten-round-regressions.php').write_text('\n'.join(lines))

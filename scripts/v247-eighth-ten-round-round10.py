#!/usr/bin/env python3
from pathlib import Path
import json
root=Path(__file__).resolve().parents[1]

def replace_once(path,old,new):
    p=root/path;s=p.read_text()
    if old not in s: raise SystemExit(f'marker missing in {path}: {old[:120]}')
    p.write_text(s.replace(old,new,1))

# Runtime / contract truth.
p=root/'homeopathy-encyclopedia/homeopathy-encyclopedia.php';s=p.read_text()
if '2.4.6' not in s: raise SystemExit('bootstrap prior-version marker missing')
p.write_text(s.replace('2.4.6','2.4.7'))

# WordPress plugin readme.
p=root/'homeopathy-encyclopedia/readme.txt';s=p.read_text()
s=s.replace('Stable tag: 2.4.6','Stable tag: 2.4.7',1)
s=s.replace('The 2.4.6 candidate','The 2.4.7 candidate',1)
marker='== Changelog ==\n\n'
entry="= 2.4.7 =\n* Eighth ten-round corrective candidate: research external-review assignment binding, authoritative public research privacy, provenance-safe graph merges, current-snapshot search evidence, stale-review concurrency guards, native dashboard scope, and accepted-state monotonic research-integrity apply.\n\n"
if '= 2.4.7 =' not in s:
    if marker not in s: raise SystemExit('readme changelog marker missing')
    s=s.replace(marker,marker+entry,1)
p.write_text(s)

# Current-candidate invariant suites.
for name in ['tests/v2-invariants.php','tests/v2-source-invariants.sh','tests/v23-future-invariants.php','tests/v24-80-round-invariants.php','tests/v241-second-80-invariants.php','tests/v242-third-80-final.php']:
    p=root/name;s=p.read_text()
    if '2.4.6' not in s: raise SystemExit('current version marker missing in '+name)
    p.write_text(s.replace('2.4.6','2.4.7'))

# Seventh-cycle suite becomes historical: preserve behavioral assertions, loosen only exact release label checks.
p=root/'tests/v246-seventh-ten-round-regressions.php';s=p.read_text()
reps=[
("v246_ok(false!==strpos($bootstrap,' * Version: 2.4.6') && false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.6' );\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.6' );\"),'R10 runtime/contract version truth not 2.4.6');","v246_ok(false!==strpos($bootstrap,' * Version:') && false!==strpos($bootstrap,\"define( 'HE_VERSION',\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION',\"),'Historical R10 runtime/contract declarations missing');"),
("v246_ok(false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.6'\"),'R10 hardening version drift');","v246_ok(false!==strpos($bootstrap,\"'future_hardening_version'=>\"),'Historical R10 hardening declaration missing');"),
("v246_ok(false!==strpos($runall,'file06-v2.4.6-a.zip') && false!==strpos($runall,'file06-v2.4.6-b.zip'),'R10 package labels not 2.4.6');","v246_ok(false!==strpos($runall,'-a.zip') && false!==strpos($runall,'-b.zip'),'Historical R10 deterministic package labels missing');")]
for old,new in reps:
    if old not in s: raise SystemExit('historical v246 marker missing')
    s=s.replace(old,new,1)
p.write_text(s)

# Aggregate gate owns this cycle and current deterministic package label.
p=root/'tests/run-all.sh';s=p.read_text()
marker='php "$root/tests/v246-seventh-ten-round-regressions.php"\n'
if 'v247-eighth-ten-round-regressions.php' not in s:
    if marker not in s: raise SystemExit('run-all v246 marker missing')
    s=s.replace(marker,marker+'php "$root/tests/v247-eighth-ten-round-regressions.php"\n',1)
s=s.replace('file06-v2.4.6-a.zip','file06-v2.4.7-a.zip').replace('file06-v2.4.6-b.zip','file06-v2.4.7-b.zip')
s=s.replace('All File 06 v2.4.6 automated checks, inherited review matrices, seventh ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.7 automated checks, inherited review matrices, eighth ten-round regressions and deterministic package comparison passed.')
p.write_text(s)

# Round-10 exact-current assertions.
p=root/'tests/v247-eighth-ten-round-regressions.php';s=p.read_text();marker='/*__V247_MORE__*/'
block="""$bootstrap=v247_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$runall=v247_read($root.'/tests/run-all.sh');
v247_ok(false!==strpos($bootstrap,' * Version: 2.4.7') && false!==strpos($bootstrap,"define( 'HE_VERSION', '2.4.7' );") && false!==strpos($bootstrap,"define( 'HE_CONTRACT_VERSION', '2.4.7' );"),'R10 runtime/contract version truth not 2.4.7');
v247_ok(false!==strpos($bootstrap,"'future_hardening_version'=>'2.4.7'"),'R10 hardening contract version drift');
v247_ok(false!==strpos($runall,'v247-eighth-ten-round-regressions.php'),'R10 eighth-cycle suite absent from aggregate gate');
v247_ok(false!==strpos($runall,'file06-v2.4.7-a.zip') && false!==strpos($runall,'file06-v2.4.7-b.zip'),'R10 package labels not 2.4.7');
"""
if marker not in s: raise SystemExit('v247 test marker missing')
p.write_text(s.replace(marker,block+marker,1))

# Repository release-state documentation. Exact final hashes/run are emitted by final exact-head CI, not self-embedded.
(root/'README.md').write_text("""# File 06 — Homeopathy Encyclopedia 2.4.7

Eighth fresh ten-round review/fix repository candidate for File 06. Repository evidence is not staging or live evidence.

## Candidate truth
- Branch: `audit/file-06-eighth-ten-round-v2.4.7`
- Plugin / contract: `2.4.7`
- Global schema: `10`
- V24 Future schema: `2`
- REST namespace: `sabri/v2/file-06`
- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 9, 10`
- Clean round: `8`

Corrections: research-bound external-evidence reviewer assignment; V22 authoritative browse privacy; merge-edge provenance rebinding; current-version-only public search evidence; explicit expected-version review binding for research and entries; native dashboard scope; and accepted-state/row-version CAS for research integrity application.

Run `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.
""")
(root/'STATUS.md').write_text("""# File 06 Status — 2.4.7 Eighth Fresh Ten-Round Candidate

| Status | Evidence |
|---|---|
| Specified | File 06 governing plan + applicable later platform governance |
| Coded | `audit/file-06-eighth-ten-round-v2.4.7` |
| Reviewed | 10 sequential review → immediate fix/retest rounds |
| Defect rounds | `1, 2, 3, 4, 5, 6, 7, 9, 10` |
| Clean rounds | `8` |
| Runtime | `2.4.7 / schema 10 / contract 2.4.7 / Future schema 2` |
| Automated QA | Authoritative only from completed final exact-head v2.4.7 workflow |
| Staging accepted | **No / unverified** |
| Live deployed | **No / unverified** |
| Operational | **No / unverified** |

Repository, staging and live are separate realities.
""")
ch=root/'CHANGELOG.md';old=ch.read_text();body=old[len('# Changelog\n\n'):] if old.startswith('# Changelog\n\n') else old
entry="""# Changelog

## 2.4.7 — Eighth fresh ten-round corrective candidate

- Completed ten sequential review → fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 9 and 10; round 8 clean.
- Bound research external scholarly review to explicit File 06 reviewer assignment.
- Closed the earlier V22 public research browse path that shadowed later successful-case/dataset privacy guards.
- Rebound outgoing graph provenance atomically when merging concepts.
- Restricted public search evidence grade/text references to the current immutable snapshot.
- Required optimistic expected-version binding for research and entry human reviews.
- Applied native editor/reviewer scope to publishing-dashboard inventory/item reads.
- Made research-integrity apply own accepted-state CAS and increment action row version.
- Staging/live/operational states remain unverified.

"""
ch.write_text(entry+body)

sbom={
 'bomFormat':'CycloneDX','specVersion':'1.5','version':5,
 'metadata':{'timestamp':'2026-08-11T10:35:00Z','component':{'type':'application','name':'File 06 Homeopathy Encyclopedia','version':'2.4.7','purl':'pkg:wordpress/homeopathy-encyclopedia@2.4.7'}},
 'components':[{'type':'framework','name':'WordPress','version':'>=6.1; final CI target 7.0.1','scope':'required'},{'type':'platform','name':'PHP','version':'>=7.4; final CI target 8.3','scope':'required'},{'type':'external','name':'File 00 identity/current-claim contract','version':'versioned platform contract','scope':'required'}],
 'release':{'file':'06-homeopathy-encyclopedia-foundation-2.4.7.zip','sha256':None,'bytes':None,'source_tree_sha256':None,'evidence_policy':'Exact values are emitted by the final exact-head workflow; not self-embedded in source.','schema':10,'future_schema':2,'contract':'2.4.7','eighth_review_rounds':10,'defect_rounds':[1,2,3,4,5,6,7,9,10],'clean_rounds':[8],'staging_accepted':False,'live_deployed':False,'operational':False}}
(root/'SBOM.json').write_text(json.dumps(sbom,ensure_ascii=False,indent=2)+'\n')
(root/'docs/RELEASE-NOTES.md').write_text("""# File 06 — Release Notes 2.4.7

Eighth fresh ten-round corrective repository candidate. Defects were found and corrected in rounds `1, 2, 3, 4, 5, 6, 7, 9, 10`; round `8` was clean.

Primary corrections cover reviewer-assignment binding, public research privacy, graph-merge provenance, snapshot-scoped search evidence, stale human-review concurrency, publishing-dashboard native scope, and research-integrity accepted-state CAS/versioning.

Final exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.
""")
(root/'docs/TEST-REPORT.md').write_text("""# File 06 v2.4.7 Automated Test Report

Candidate: `2.4.7 / schema 10 / contract 2.4.7 / Future schema 2`
Branch: `audit/file-06-eighth-ten-round-v2.4.7`
Review result: defect rounds `1,2,3,4,5,6,7,9,10`; clean round `8`.

The authoritative automated result is the final exact-head workflow created after this release-truth commit. Required gates: PHP 7.4/8.3 syntax, complete inherited + v2.4.7 aggregate suite, deterministic double packaging, and WordPress 7.0.1/PHP 8.3/MySQL 8 lifecycle smoke with strict runtime-error log checks.

This repository report is not staging or live acceptance.
""")
(root/'docs/RELEASE-SIGNOFF.md').write_text("""# Release Sign-off Record

- Module: File 06 — Homeopathy Encyclopedia
- Candidate: `2.4.7`
- Schema: `10`; Future schema: `2`; Contract: `2.4.7`
- Branch: `audit/file-06-eighth-ten-round-v2.4.7`
- Review rounds: `10`; defects: `1,2,3,4,5,6,7,9,10`; clean: `8`
- Automated exact-head acceptance: pending final workflow at source-writing time
- Staging acceptance: unverified
- Deployed version/parity: unverified
- Live DB/migration state: unverified
- Live verification: not performed
""")

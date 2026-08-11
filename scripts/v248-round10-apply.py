#!/usr/bin/env python3
from pathlib import Path
import json

ROOT=Path(__file__).resolve().parents[1]

def rw(rel, fn):
    p=ROOT/rel
    p.write_text(fn(p.read_text()))

# Runtime / contract current-candidate truth.
rw('homeopathy-encyclopedia/homeopathy-encyclopedia.php', lambda s:s.replace('2.4.7','2.4.8'))
rw('homeopathy-encyclopedia/readme.txt', lambda s:s.replace('Stable tag: 2.4.7','Stable tag: 2.4.8'))

# Current invariant suites track the current candidate.
for rel in [
    'tests/v2-invariants.php','tests/v2-source-invariants.sh','tests/v23-future-invariants.php',
    'tests/v24-80-round-invariants.php','tests/v241-second-80-invariants.php','tests/v242-third-80-final.php'
]:
    rw(rel, lambda s:s.replace('2.4.7','2.4.8'))

# Historical v2.4.7 suite: retain behavioral assertions, remove exact-current-version pinning.
p=ROOT/'tests/v247-eighth-ten-round-regressions.php'
out=[]
for line in p.read_text().splitlines():
    if 'R10 runtime/contract version truth not 2.4.7' in line:
        out.append("v247_ok(false!==strpos($bootstrap,' * Version:') && false!==strpos($bootstrap,\"define( 'HE_VERSION',\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION',\"),'Historical R10 runtime/contract declarations missing');")
    elif 'R10 hardening contract version drift' in line:
        out.append("v247_ok(false!==strpos($bootstrap,\"'future_hardening_version'=>\"),'Historical R10 hardening declaration missing');")
    elif 'R10 package labels not 2.4.7' in line:
        out.append("v247_ok(false!==strpos($runall,'-a.zip') && false!==strpos($runall,'-b.zip'),'Historical R10 deterministic package labels missing');")
    else:
        out.append(line)
p.write_text('\n'.join(out)+'\n')

# Historical v2.4.6 suite emitted interpolation warnings; make the source needle literal/safe.
p=ROOT/'tests/v246-seventh-ten-round-regressions.php'
out=[]
for line in p.read_text().splitlines():
    if 'maintenance scan still pre-advances cursor' in line:
        out.append("v246_ok(false===strpos($schema,'end( $rows )'),'R9 maintenance scan still pre-advances cursor');")
    else:
        out.append(line)
p.write_text('\n'.join(out)+'\n')

# The Round-9 assertion itself must not interpolate PHP variables while inspecting source.
p=ROOT/'tests/v248-ninth-ten-round-regressions.php'
out=[]
for line in p.read_text().splitlines():
    if 'R9 WordPress archive/search paths can expose non-public domain research or stale WP title/excerpt metadata' in line:
        out.append("v248_ok(false!==strpos($guard,'research_public_query_where') && false!==strpos($guard,'he_public_research.status IN'),'R9 WordPress archive/search paths can expose non-public domain research or stale WP title/excerpt metadata');")
    else:
        out.append(line)
p.write_text('\n'.join(out)+'\n')

# Aggregate gate and deterministic package labels.
p=ROOT/'tests/run-all.sh'
s=p.read_text()
if 'v248-ninth-ten-round-regressions.php' not in s:
    s=s.replace('php "$root/tests/v247-eighth-ten-round-regressions.php"','php "$root/tests/v247-eighth-ten-round-regressions.php"\nphp "$root/tests/v248-ninth-ten-round-regressions.php"')
s=s.replace('file06-v2.4.7-a.zip','file06-v2.4.8-a.zip').replace('file06-v2.4.7-b.zip','file06-v2.4.8-b.zip')
s=s.replace('All File 06 v2.4.7 automated checks, inherited review matrices, eighth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.8 automated checks, inherited review matrices, ninth ten-round regressions and deterministic package comparison passed.')
p.write_text(s)

# Round-10 regression contract.
p=ROOT/'tests/v248-ninth-ten-round-regressions.php'
s=p.read_text(); marker='/*__V248_MORE__*/'
block='''$bootstrap=v248_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$runall=v248_read($root.'/tests/run-all.sh');
v248_ok(false!==strpos($bootstrap,' * Version: 2.4.8') && false!==strpos($bootstrap,"define( 'HE_VERSION', '2.4.8' );") && false!==strpos($bootstrap,"define( 'HE_CONTRACT_VERSION', '2.4.8' );"),'R10 runtime/contract version truth not 2.4.8');
v248_ok(false!==strpos($bootstrap,"'future_hardening_version'=>'2.4.8'"),'R10 future hardening version drift');
v248_ok(false!==strpos($runall,'v248-ninth-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.8-a.zip') && false!==strpos($runall,'file06-v2.4.8-b.zip'),'R10 aggregate/package truth not aligned to 2.4.8');
$v246=v248_read($root.'/tests/v246-seventh-ten-round-regressions.php');
v248_ok(false===strpos($v246,'update_option( $option'),'R10 inherited v2.4.6 regression interpolation warning not corrected');'''
if block not in s:
    if marker not in s:
        raise SystemExit('v248 marker missing')
    s=s.replace(marker,block+'\n'+marker,1)
p.write_text(s)

# Current release documentation. Exact final hashes remain external workflow evidence.
changelog=ROOT/'CHANGELOG.md'
old=changelog.read_text()
if old.startswith('# Changelog\n\n'):
    old=old[len('# Changelog\n\n'):]
section='''# Changelog\n\n## 2.4.8 — Ninth fresh ten-round corrective candidate\n\n- Completed ten sequential review → immediate-fix → regression rounds.\n- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10.\n- Rate-limit storage failures now fail closed instead of silently authorizing mutations.\n- Reclaimed idempotency reservations are fenced against stale-worker completion.\n- Outbox workers claim deliveries with recoverable CAS processing leases.\n- Event/audit and outbox pair persistence is atomic and fails closed on partial writes.\n- Outbox reconciliation and consumed-event recording are concurrency-safe.\n- Reindex cursors advance only after successful row persistence.\n- Background maintenance uses File00-backed File06 repair authority.\n- Front-end research archive/search queries are constrained by File06 domain publication state.\n- Runtime, current invariants, aggregate QA, deterministic package labels and release documentation are aligned to 2.4.8.\n- Staging/live/operational states remain unverified.\n\n'''
changelog.write_text(section+old)

(ROOT/'README.md').write_text('''# File 06 — Homeopathy Encyclopedia 2.4.8\n\nNinth fresh ten-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.\n\n## Candidate truth\n- Branch: `audit/file-06-ninth-ten-round-v2.4.8`\n- Plugin / contract: `2.4.8`\n- Global schema: `10`\n- V24 Future schema: `2`\n- REST namespace: `sabri/v2/file-06`\n- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`\n\nCorrections cover fail-closed rate limiting, idempotency fencing, outbox concurrency/reconciliation, atomic event+outbox pair persistence, retry-safe reindex cursors, File00-backed maintenance authority, consumed-event race safety, public research query eligibility, and v2.4.8 release/QA truth.\n\nRun `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.\n''')
(ROOT/'STATUS.md').write_text('''# File 06 Status — 2.4.8 Ninth Fresh Ten-Round Candidate\n\n| Status | Evidence |\n|---|---|\n| Specified | File 06 governing plan + applicable later platform governance |\n| Coded | `audit/file-06-ninth-ten-round-v2.4.8` |\n| Reviewed | 10 sequential review → immediate fix/retest rounds |\n| Defect rounds | `1, 2, 3, 4, 5, 6, 7, 8, 9, 10` |\n| Runtime | `2.4.8 / schema 10 / contract 2.4.8 / Future schema 2` |\n| Automated QA | Authoritative only from completed final exact-head v2.4.8 workflow |\n| Staging accepted | **No / unverified** |\n| Live deployed | **No / unverified** |\n| Operational | **No / unverified** |\n\nRepository, staging and live are separate realities.\n''')
(ROOT/'docs/RELEASE-NOTES.md').write_text('''# File 06 — Release Notes 2.4.8\n\nNinth fresh ten-round corrective repository candidate. Defects were found and corrected in rounds `1–10`.\n\nPrimary corrections cover mutation fail-closed reliability, idempotency fencing, outbox CAS delivery/reconciliation, event/outbox pair atomicity, retry-safe reindexing, File00-backed maintenance authorization, consumed-event concurrency safety, front-end research publication eligibility, and current release/QA truth.\n\nFinal exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.\n''')
(ROOT/'docs/RELEASE-SIGNOFF.md').write_text('''# Release Sign-off Record\n\n- Module: File 06 — Homeopathy Encyclopedia\n- Candidate version: `2.4.8`\n- Global schema: `10`\n- Future schema: `2`\n- Contract: `2.4.8`\n- Branch: `audit/file-06-ninth-ten-round-v2.4.8`\n- Ninth fresh ten-round review: completed\n- Defect rounds corrected: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`\n- Dedicated v2.4.8 regression suite: present and wired into aggregate QA\n- Repository automated sign-off: **authoritative only after the completed final exact-head workflow**\n- Package/source digests: **take from the final exact-head workflow; not self-embedded here**\n- Staging acceptance: **pending / unverified**\n- Founder production approval: **pending explicit dated sign-off after staging**\n- Deployed version/parity: **unverified**\n- Live DB/migration state: **unverified**\n- Live verification: **not performed**\n''')
(ROOT/'docs/TEST-REPORT.md').write_text('''# File 06 v2.4.8 Automated Test Report\n\nCandidate branch: `audit/file-06-ninth-ten-round-v2.4.8`\n\nTen sequential corrective review rounds completed. Defect rounds: `1–10`. The aggregate gate includes inherited File 06 matrices plus the dedicated v2.4.8 ninth-cycle regressions and deterministic double-package comparison.\n\nThe final exact-head workflow is the authoritative repository automated-QA evidence. This repository report is not staging or live acceptance.\n''')

sbom_path=ROOT/'SBOM.json'
sb=json.loads(sbom_path.read_text())
sb['version']=int(sb.get('version',0))+1
sb.setdefault('metadata',{}).setdefault('component',{})['version']='2.4.8'
sb['metadata']['component']['purl']='pkg:wordpress/homeopathy-encyclopedia@2.4.8'
rel=sb.setdefault('release',{})
rel.update({'file':'06-homeopathy-encyclopedia-foundation-2.4.8.zip','sha256':None,'bytes':None,'source_tree_sha256':None,'contract':'2.4.8','ninth_review_rounds':10,'defect_rounds':[1,2,3,4,5,6,7,8,9,10],'clean_rounds':[],'staging_accepted':False,'live_deployed':False,'operational':False})
rel.pop('eighth_review_rounds',None)
sbom_path.write_text(json.dumps(sb,ensure_ascii=False,indent=2)+'\n')

from pathlib import Path

# Runtime truth.
p=Path('homeopathy-encyclopedia/homeopathy-encyclopedia.php'); s=p.read_text()
for old,new in [
    (' * Version: 2.4.8',' * Version: 2.4.9'),
    ("define( 'HE_VERSION', '2.4.8' );","define( 'HE_VERSION', '2.4.9' );"),
    ("define( 'HE_CONTRACT_VERSION', '2.4.8' );","define( 'HE_CONTRACT_VERSION', '2.4.9' );"),
    ("'future_hardening_version'=>'2.4.8'","'future_hardening_version'=>'2.4.9'"),
]:
    if old not in s: raise SystemExit('R10 bootstrap marker missing: '+old)
    s=s.replace(old,new,1)
p.write_text(s)

p=Path('homeopathy-encyclopedia/readme.txt'); s=p.read_text()
if 'Stable tag: 2.4.8' not in s: raise SystemExit('R10 stable tag marker missing')
s=s.replace('Stable tag: 2.4.8','Stable tag: 2.4.9',1)
s=s.replace('The 2.4.7 candidate also requires explicit editor knowledge-type assignment and reviewer assignment for governed review decisions.','The 2.4.9 candidate also requires explicit editor knowledge-type assignment and reviewer assignment for governed review decisions.',1)
p.write_text(s)

# Current aggregate gate and deterministic package labels.
p=Path('tests/run-all.sh'); s=p.read_text()
if 'php "$root/tests/v249-tenth-ten-round-regressions.php"' not in s:
    anchor='php "$root/tests/v248-ninth-ten-round-regressions.php"\n'
    if anchor not in s: raise SystemExit('R10 run-all v248 anchor missing')
    s=s.replace(anchor,anchor+'php "$root/tests/v249-tenth-ten-round-regressions.php"\n',1)
s=s.replace('file06-v2.4.8-a.zip','file06-v2.4.9-a.zip').replace('file06-v2.4.8-b.zip','file06-v2.4.9-b.zip')
s=s.replace('All File 06 v2.4.8 automated checks, inherited review matrices, ninth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.9 automated checks, inherited review matrices, tenth ten-round regressions and deterministic package comparison passed.')
p.write_text(s)

# Current candidate invariants: these files intentionally track the current runtime while retaining older behavior matrices.
for name in ['tests/v2-invariants.php','tests/v2-source-invariants.sh','tests/v23-future-invariants.php','tests/v24-80-round-invariants.php','tests/v241-second-80-invariants.php','tests/v242-third-80-final.php']:
    p=Path(name); s=p.read_text(); s=s.replace('2.4.8','2.4.9'); p.write_text(s)

# Ninth-cycle regression must remain historical and future-candidate-safe rather than pinning the current runtime/package label.
p=Path('tests/v248-ninth-ten-round-regressions.php'); s=p.read_text()
old="""v248_ok(false!==strpos($bootstrap,' * Version: 2.4.8') && false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.8' );\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.8' );\"),'R10 runtime/contract version truth not 2.4.8');
v248_ok(false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.8'\"),'R10 future hardening version drift');
v248_ok(false!==strpos($runall,'v248-ninth-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.8-a.zip') && false!==strpos($runall,'file06-v2.4.8-b.zip'),'R10 aggregate/package truth not aligned to 2.4.8');
"""
new="""$v248version='';if(preg_match('/\\* Version: ([0-9.]+)/',$bootstrap,$v248m)){$v248version=$v248m[1];}
v248_ok($v248version && version_compare($v248version,'2.4.8','>=') && false!==strpos($bootstrap,\"define( 'HE_VERSION', '\".$v248version.\"' );\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '\".$v248version.\"' );\"),'R10 runtime/contract must preserve or advance the v2.4.8 candidate contract');
v248_ok(false!==strpos($bootstrap,\"'future_hardening_version'=>'\".$v248version.\"'\"),'R10 future hardening version must track the current runtime');
v248_ok(false!==strpos($runall,'v248-ninth-ten-round-regressions.php'),'R10 ninth-cycle regression suite disappeared from the inherited aggregate gate');
"""
if old not in s: raise SystemExit('R10 v248 historical exact-version block missing')
p.write_text(s.replace(old,new,1))

# Add exact v2.4.9 release-truth assertions to the current cycle regression file.
p=Path('tests/v249-tenth-ten-round-regressions.php'); s=p.read_text(); marker='/*__V249_MORE__*/'
block="""$bootstrap=v249_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
$readme=v249_read($root.'/homeopathy-encyclopedia/readme.txt');
$runall=v249_read($root.'/tests/run-all.sh');
v249_ok(false!==strpos($bootstrap,' * Version: 2.4.9') && false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.9' );\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.9' );\") && false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.9'\"),'R10 runtime/contract/future hardening release truth is not aligned to v2.4.9');
v249_ok(false!==strpos($readme,'Stable tag: 2.4.9'),'R10 plugin stable tag is not v2.4.9');
v249_ok(false!==strpos($runall,'v249-tenth-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.9-a.zip') && false!==strpos($runall,'file06-v2.4.9-b.zip'),'R10 aggregate/package truth is not aligned to v2.4.9');"""
if block not in s:
    if marker not in s: raise SystemExit('R10 v249 test marker missing')
    s=s.replace(marker,block+'\n'+marker,1)
p.write_text(s)

# Current release documentation. Keep exact-head workflow/hash claims explicitly deferred until final QA.
Path('README.md').write_text("""# File 06 — Homeopathy Encyclopedia 2.4.9

Tenth fresh ten-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.

## Candidate truth
- Branch: `audit/file-06-tenth-ten-round-v2.4.9`
- Plugin / contract: `2.4.9`
- Global schema: `10`
- V24 Future schema: `2`
- REST namespace: `sabri/v2/file-06`
- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`

Tenth-cycle corrections bind entry reviews atomically to the reviewed state, restrict review hashes to current/draft provenance, fail closed on idempotency-finalization uncertainty across all mutation helpers, make direct and scheduled entry publication atomic, compensate snapshot provenance failures, make research publication atomic, lock/confirm research-integrity application transactions, and align v2.4.9 release/QA truth.

Run `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.
""")
Path('STATUS.md').write_text("""# File 06 Status — 2.4.9 Tenth Fresh Ten-Round Candidate

| Status | Evidence |
|---|---|
| Specified | File 06 governing plan + applicable later platform governance |
| Coded | `audit/file-06-tenth-ten-round-v2.4.9` |
| Reviewed | 10 sequential review → immediate fix/retest rounds |
| Defect rounds | `1, 2, 3, 4, 5, 6, 7, 8, 9, 10` |
| Runtime | `2.4.9 / schema 10 / contract 2.4.9 / Future schema 2` |
| Automated QA | Authoritative only from completed final exact-head v2.4.9 workflow |
| Staging accepted | **No / unverified** |
| Live deployed | **No / unverified** |
| Operational | **No / unverified** |

Repository, staging and live are separate realities.
""")
Path('docs/RELEASE-NOTES.md').write_text("""# File 06 — Release Notes 2.4.9

Tenth fresh ten-round corrective repository candidate. Defects were found and corrected in rounds `1–10`.

Primary corrections cover atomic human-review binding, current-reference review fingerprints, fail-closed idempotency finalization across mutation surfaces, atomic direct/scheduled entry publication, compensating snapshot provenance, atomic research publication, locked research-integrity application, and current v2.4.9 release/QA truth.

Final exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.
""")
Path('docs/RELEASE-SIGNOFF.md').write_text("""# Release Sign-off Record

- Module: File 06 — Homeopathy Encyclopedia
- Candidate version: `2.4.9`
- Global schema: `10`
- Future schema: `2`
- Contract: `2.4.9`
- Branch: `audit/file-06-tenth-ten-round-v2.4.9`
- Tenth fresh ten-round review: completed
- Defect rounds corrected: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`
- Dedicated v2.4.9 regression suite: present and wired into aggregate QA
- Repository automated sign-off: **authoritative only after the completed final exact-head workflow**
- Package/source digests: **take from the final exact-head workflow; not self-embedded here**
- Staging acceptance: **pending / unverified**
- Founder production approval: **pending explicit dated sign-off after staging**
- Deployed version/parity: **unverified**
- Live DB/migration state: **unverified**
- Live verification: **not performed**
""")
Path('docs/TEST-REPORT.md').write_text("""# File 06 v2.4.9 Automated Test Report

Candidate branch: `audit/file-06-tenth-ten-round-v2.4.9`

Ten sequential corrective review rounds completed. Defect rounds: `1–10`. The aggregate gate includes inherited File 06 matrices plus the dedicated v2.4.9 tenth-cycle regressions and deterministic double-package comparison.

The final exact-head workflow is the authoritative repository automated-QA evidence. This repository report is not staging or live acceptance.
""")

# Add new changelog entry without rewriting historical entries.
p=Path('CHANGELOG.md'); s=p.read_text()
entry="""## 2.4.9 — Tenth fresh ten-round corrective candidate

- Completed ten sequential review → immediate-fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10.
- Bound entry review decisions atomically to the exact reviewed row version/content/reference state.
- Restricted review evidence fingerprints to current immutable and pending-draft references rather than superseded historical provenance.
- Made idempotency response finalization fail closed across all governed mutation helpers, including reclaimed/stale reservation fencing.
- Made direct and scheduled entry publication atomic across domain state, snapshot, WordPress publication and commit confirmation.
- Added compensating rollback for partial snapshot reference/provenance binding failure.
- Made research publication atomic across File 06 domain state and its governed WordPress publication object.
- Locked research-integrity action/research rows and confirmed transaction start/commit before reporting application success.
- Runtime, contract, current invariants, aggregate QA, deterministic package labels and release documentation are aligned to 2.4.9.
- Staging/live/operational states remain unverified.

"""
if entry not in s:
    if not s.startswith('# Changelog\n\n'): raise SystemExit('R10 changelog header unexpected')
    s=s.replace('# Changelog\n\n','# Changelog\n\n'+entry,1)
p.write_text(s)

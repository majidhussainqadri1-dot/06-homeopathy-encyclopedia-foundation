from pathlib import Path
root=Path('.')
# Runtime/plugin metadata.
for rel in ['homeopathy-encyclopedia/homeopathy-encyclopedia.php','homeopathy-encyclopedia/readme.txt']:
    p=root/rel; s=p.read_text(); s=s.replace('2.4.9','2.4.10'); p.write_text(s)
# Current-candidate invariant suites follow the current runtime truth.
for rel in ['tests/v23-future-invariants.php','tests/v2-source-invariants.sh','tests/v2-invariants.php','tests/v242-third-80-final.php','tests/v241-second-80-invariants.php','tests/v24-80-round-invariants.php']:
    p=root/rel; s=p.read_text(); s=s.replace('2.4.9','2.4.10'); p.write_text(s)
# Aggregate gate: wire eleventh suite and move package labels/current output to 2.4.10.
p=root/'tests/run-all.sh'; s=p.read_text()
needle='php "$root/tests/v249-tenth-ten-round-regressions.php"\n'
if 'v2410-eleventh-ten-round-regressions.php' not in s:
    if needle not in s: raise SystemExit('run-all v249 anchor missing')
    s=s.replace(needle,needle+'php "$root/tests/v2410-eleventh-ten-round-regressions.php"\n',1)
s=s.replace('file06-v2.4.9-a.zip','file06-v2.4.10-a.zip').replace('file06-v2.4.9-b.zip','file06-v2.4.10-b.zip')
s=s.replace('All File 06 v2.4.9 automated checks, inherited review matrices, tenth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.10 automated checks, inherited review matrices, eleventh ten-round regressions and deterministic package comparison passed.')
p.write_text(s)
# Historical v2.4.9 behavior suite must remain valid on later hardened candidates, without pinning current release metadata.
p=root/'tests/v249-tenth-ten-round-regressions.php'; s=p.read_text()
old="""v249_ok(false!==strpos($bootstrap,' * Version: 2.4.9') && false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.9' );\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.9' );\") && false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.9'\"),'R10 runtime/contract/future hardening release truth is not aligned to v2.4.9');
v249_ok(false!==strpos($readme,'Stable tag: 2.4.9'),'R10 plugin stable tag is not v2.4.9');
v249_ok(false!==strpos($runall,'v249-tenth-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.9-a.zip') && false!==strpos($runall,'file06-v2.4.9-b.zip'),'R10 aggregate/package truth is not aligned to v2.4.9');
"""
new="""preg_match('/\\* Version: ([0-9.]+)/',$bootstrap,$v249_plugin); preg_match('/HE_VERSION', 'x');
$plugin_version=$v249_plugin[1]??'0.0.0';
preg_match('/Stable tag: ([0-9.]+)/',$readme,$v249_tag); $stable_version=$v249_tag[1]??'0.0.0';
v249_ok(version_compare($plugin_version,'2.4.9','>=') && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION'\") && false!==strpos($bootstrap,"'future_hardening_version'=>"),'R10 later candidate no longer preserves at least v2.4.9 runtime/contract hardening truth');
v249_ok(version_compare($stable_version,'2.4.9','>='),'R10 later plugin stable tag regressed below v2.4.9');
v249_ok(false!==strpos($runall,'v249-tenth-ten-round-regressions.php'),'R10 later aggregate gate dropped the v2.4.9 behavior regression suite');
"""
# Correct an accidental no-op helper expression in new before writing.
new=new.replace(" preg_match('/HE_VERSION', 'x');",'')
if old not in s: raise SystemExit('v249 R10 block missing')
s=s.replace(old,new,1); p.write_text(s)
# Eleventh-cycle R10 release assertions.
p=root/'tests/v2410-eleventh-ten-round-regressions.php'; s=p.read_text(); marker='/*__V2410_MORE__*/'
add="""$bootstrap=v2410_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');\n$readme=v2410_read($root.'/homeopathy-encyclopedia/readme.txt');\n$runall=v2410_read($root.'/tests/run-all.sh');\nv2410_ok(false!==strpos($bootstrap,' * Version: 2.4.10') && false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.10' );\") && false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.10' );\") && false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.10'\"),'R10 runtime/contract/future hardening release truth is not aligned to v2.4.10');\nv2410_ok(false!==strpos($readme,'Stable tag: 2.4.10'),'R10 plugin stable tag is not v2.4.10');\nv2410_ok(false!==strpos($runall,'v2410-eleventh-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.10-a.zip') && false!==strpos($runall,'file06-v2.4.10-b.zip'),'R10 aggregate/package truth is not aligned to v2.4.10');\n"""
if marker not in s: raise SystemExit('v2410 marker missing')
s=s.replace(marker,add+marker,1); p.write_text(s)
# Current repository truth documents.
(root/'README.md').write_text("""# File 06 — Homeopathy Encyclopedia 2.4.10

Eleventh fresh ten-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.

## Candidate truth
- Branch: `audit/file-06-eleventh-ten-round-v2.4.10`
- Plugin / contract: `2.4.10`
- Global schema: `10`
- V24 Future schema: `2`
- REST namespace: `sabri/v2/file-06`
- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`

Eleventh-cycle corrections close restricted/unconsented permanent research rendering, atomically bind research and entry reviews to expected versions, recheck successful-case consent/anonymization at release, make entry/research integrity authorization object-bound, fail closed entry-integrity transaction uncertainty, and make the owner transition command enforce fresh current-content independent approval.

Run `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.
""")
(root/'STATUS.md').write_text("""# File 06 Status — 2.4.10 Eleventh Fresh Ten-Round Candidate

| Status | Evidence |
|---|---|
| Specified | File 06 governing plan + applicable later platform governance |
| Coded | `audit/file-06-eleventh-ten-round-v2.4.10` |
| Reviewed | 10 sequential review → immediate fix/retest rounds |
| Defect rounds | `1, 2, 3, 4, 5, 6, 7, 8, 9, 10` |
| Runtime | `2.4.10 / schema 10 / contract 2.4.10 / Future schema 2` |
| Automated QA | Authoritative only from completed final exact-head v2.4.10 workflow |
| Staging accepted | **No / unverified** |
| Live deployed | **No / unverified** |
| Operational | **No / unverified** |

Repository, staging and live are separate realities.
""")
(root/'docs/RELEASE-NOTES.md').write_text("""# File 06 — Release Notes 2.4.10

Eleventh fresh ten-round corrective repository candidate. Defects were found and corrected in rounds `1–10`.

Primary corrections cover public research eligibility, human-review CAS binding, successful-case release governance, object-bound integrity authorization, transaction certainty for entry integrity application, current-content review enforcement in owner commands, and current v2.4.10 release/QA truth.

Final exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.
""")
(root/'docs/RELEASE-SIGNOFF.md').write_text("""# Release Sign-off Record

- Module: File 06 — Homeopathy Encyclopedia
- Candidate version: `2.4.10`
- Global schema: `10`
- Future schema: `2`
- Contract: `2.4.10`
- Branch: `audit/file-06-eleventh-ten-round-v2.4.10`
- Eleventh fresh ten-round review: completed
- Defect rounds corrected: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`
- Dedicated v2.4.10 regression suite: present and wired into aggregate QA
- Repository automated sign-off: **authoritative only after the completed final exact-head workflow**
- Package/source digests: **take from the final exact-head workflow; not self-embedded here**
- Staging acceptance: **pending / unverified**
- Founder production approval: **pending explicit dated sign-off after staging**
- Deployed version/parity: **unverified**
- Live DB/migration state: **unverified**
- Live verification: **not performed**
""")
(root/'docs/TEST-REPORT.md').write_text("""# File 06 v2.4.10 Automated Test Report

Candidate branch: `audit/file-06-eleventh-ten-round-v2.4.10`

Ten sequential corrective review rounds completed. Defect rounds: `1–10`. The aggregate gate includes inherited File 06 matrices plus the dedicated v2.4.10 eleventh-cycle regressions and deterministic double-package comparison.

The final exact-head workflow is the authoritative repository automated-QA evidence. This repository report is not staging or live acceptance.
""")
# Preserve history; prepend a new changelog section.
p=root/'CHANGELOG.md'; s=p.read_text()
entry="""## 2.4.10 — Eleventh fresh ten-round corrective candidate

- Completed ten sequential review → immediate-fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10.
- Restricted/unconsented research permanent-ID requests now fail closed; successful-case public rendering independently requires anonymization and verified consent.
- Research and entry human-review records are atomically bound to the expected row version.
- Successful-case release rechecks both consent metadata and authoritative consent/anonymization flags.
- Entry and research integrity application plus integrity state transitions enforce object-bound File 00/File 06 authorization.
- Entry-integrity transactions fail closed when transaction start or commit certainty is unavailable.
- The owner transition command itself requires a fresh independent approval bound to current content instead of relying only on REST preflight.
- Runtime, contract, current invariants, aggregate QA, deterministic package labels and release documentation are aligned to 2.4.10.
- Staging/live/operational states remain unverified.

"""
if '## 2.4.10 —' not in s:
    anchor='# Changelog\n\n'
    if anchor not in s: raise SystemExit('changelog anchor missing')
    s=s.replace(anchor,anchor+entry,1)
p.write_text(s)

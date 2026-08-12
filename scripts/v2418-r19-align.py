#!/usr/bin/env python3
from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]

def replace_once(text, old, new, label):
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    return text.replace(old, new, 1)

boot = root / 'homeopathy-encyclopedia' / 'homeopathy-encyclopedia.php'
s = boot.read_text(encoding='utf-8')
for old, new, label in [
    (' * Version: 2.4.17', ' * Version: 2.4.18', 'plugin header'),
    ("define( 'HE_VERSION', '2.4.17' );", "define( 'HE_VERSION', '2.4.18' );", 'runtime version'),
    ("define( 'HE_CONTRACT_VERSION', '2.4.17' );", "define( 'HE_CONTRACT_VERSION', '2.4.18' );", 'contract version'),
    ("'future_hardening_version'=>'2.4.17'", "'future_hardening_version'=>'2.4.18'", 'future hardening version'),
]:
    s = replace_once(s, old, new, label)
boot.write_text(s, encoding='utf-8')

plugin_readme = root / 'homeopathy-encyclopedia' / 'readme.txt'
r = plugin_readme.read_text(encoding='utf-8')
r = replace_once(r, 'Stable tag: 2.4.17', 'Stable tag: 2.4.18', 'stable tag')
r = r.replace('The 2.4.15 candidate also requires', 'The 2.4.18 candidate also requires', 1)
marker = '== Changelog ==\n\n'
entry = (
    '= 2.4.18 =\n'
    '* Nineteenth fresh twenty-round corrective candidate: canonicalized research/integrity/external governance routes, opaque research pagination and version metadata, provenance-atomic Future scholarly/claim/translation/mapping writes, serialized assignment updates, durable version-specific translation impact queues, and canonical-only public Future read registration. Round 20 exact-head QA remains the final repository gate.\n\n'
)
if marker not in r:
    raise SystemExit('plugin readme changelog marker missing')
r = r.replace(marker, marker + entry, 1)
plugin_readme.write_text(r, encoding='utf-8')

ledger = root / 'docs' / 'FILE-06-v2.4.18-NINETEENTH-TWENTY-ROUND-REVIEW.md'
ledger.write_text('''# File 06 v2.4.18 — Nineteenth Fresh Twenty-Round Review

Repository-only corrective record. Staging/live/operational status is not established by this review.

1. **DEFECT** — Research reviewer assignment still accepted a raw research row ID. The assignment route now requires the canonical research UUID and resolves only by public_id.
2. **DEFECT** — The reviewer-assignment enforcement guard watched a numeric research-review route while the real route was canonical UUID, allowing assignment enforcement to be skipped. The guard now matches the canonical review route.
3. **DEFECT** — External scholarly staging/review object-scope guards still treated governed object IDs as numeric. Staging now resolves canonical concept/claim/research UUIDs and review decodes the opaque external-record token before object authorization.
4. **DEFECT** — Integrity transition/apply governance still retained numeric route assumptions; canonical UUID apply could bypass the stronger atomic entry-integrity interceptor. Transition/apply are canonical UUID-bound and the atomic apply path is restored.
5. **DEFECT** — Canonical integrity review decisions were not covered by the legacy numeric reviewer-assignment guard. A canonical UUID transition gate now requires an active reviewer assignment for review/accept/reject decisions.
6. **DEFECT** — Public research pagination returned the internal research table ID as next_cursor. It now uses a scope-bound opaque signed cursor and rejects altered cursors.
7. **DEFECT** — Public version history returned the immutable versions-table primary key even though version_number is the public handle. Internal version row IDs were removed from the public version list.
8. **CLEAN** — Fresh dataset-access review found canonical research UUID requests, opaque approval tokens, published/WP eligibility rechecks, lawful-purpose binding, row locks and checked commit handling; no new actionable defect was proven.
9. **DEFECT** — External scholarly staging could commit authoritative metadata while provenance persistence failed. External-record state and staged provenance now commit atomically after the bounded provider lookup.
10. **DEFECT** — Claim create/update could persist without claim.saved provenance. Concept/version/claim state and provenance are now locked, CAS-bound and committed atomically.
11. **DEFECT** — Claim review could persist approval/rejection before unchecked provenance. Claim review state, evidence gate and provenance now commit atomically under a claim row lock.
12. **DEFECT** — Translation save used an absint-derived UUID idempotency suffix and split state from provenance. Canonical UUID operation identity, source-version locking, translation CAS and provenance are now atomic.
13. **DEFECT** — Translation review/outdated transitions committed state before provenance. Source-currentness, review/outdated state and provenance now commit in the same transaction.
14. **DEFECT** — Translation publication committed before provenance and its impact dedupe payload omitted translation_version, so later versions could fail to enqueue consumers. Publication, provenance and six durable version-specific impact rows are atomic; external queue notification fires only after commit.
15. **DEFECT** — Concept-vocabulary and ORCID researcher mappings could persist while provenance failed. Both mapping families now lock, persist and append provenance atomically; ORCID ownership races fail closed.
16. **DEFECT** — Editor/reviewer assignment meta writes were not checked before success/event emission. No-op editor scope is distinguished from persistence failure; reviewer writes now fail closed on storage failure.
17. **DEFECT** — Serialized reviewer/editor assignment metadata had a read-modify-write lost-update race. Owner user/concept/research rows now serialize assignment writes, caches are refreshed under lock, commit is checked and events emit only after commit.
18. **DEFECT** — Legacy numeric graph/time-machine/freshness/citation public read routes remained registered even though blocked by a later guard. Numeric registrations and numeric public-read fallback were retired; canonical public UUID routes are the only registered Future intelligence reads.
19. **DEFECT** — Corrective source changes left runtime, stable tag, aggregate package labels, SBOM and repository status on earlier candidate truth. Current repository candidate truth is aligned to 2.4.18; core schema remains 10 and Future schema remains 2.
20. **PENDING** — Final fresh cross-cutting exact-head review and runtime/package QA will determine the final round result.
''', encoding='utf-8')

(root / 'README.md').write_text('''# File 06 — Homeopathy Encyclopedia 2.4.18

Nineteenth fresh twenty-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.

## Candidate truth
- Branch: `audit/file-06-nineteenth-twenty-round-v2.4.18`
- Plugin / contract: `2.4.18`
- Global schema: `10`
- V24 Future schema: `2`
- REST namespace: `sabri/v2/file-06`
- Review progress: `19/20 finalized; R20 pending exact-head final review`
- Defect rounds through R19: `1–7, 9–19`
- Clean rounds through R19: `8`

This cycle canonicalizes residual research/integrity/external governance identifiers, removes public internal pagination/version identifiers, makes key Future scholarly/claim/translation/mapping state-provenance pairs transactional, serializes governance assignment writes, makes translation impact version-specific, and retires registered numeric public Future read surfaces.

Run `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the completed final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.
''', encoding='utf-8')

(root / 'STATUS.md').write_text('''# File 06 Status — 2.4.18 Nineteenth Fresh Twenty-Round Candidate

| Status | Evidence |
|---|---|
| Specified | File 06 governing plan + applicable later platform governance |
| Coded | `audit/file-06-nineteenth-twenty-round-v2.4.18` |
| Reviewed | `19/20` sequential review → immediate fix/retest rounds finalized; R20 pending |
| Defect rounds through R19 | `1–7, 9–19` |
| Clean rounds through R19 | `8` |
| Runtime | `2.4.18 / schema 10 / contract 2.4.18 / Future schema 2` |
| Automated QA | Per-round inherited gates green through R19; authoritative final exact-head QA still pending R20 |
| Staging accepted | **No / unverified** |
| Live deployed | **No / unverified** |
| Operational | **No / unverified** |

Repository, staging and live are separate realities.
''', encoding='utf-8')

changelog = root / 'CHANGELOG.md'
cs = changelog.read_text(encoding='utf-8')
if '## 2.4.18 — Nineteenth fresh twenty-round candidate' not in cs:
    cs = cs.replace(
        '# Changelog\n\n',
        '# Changelog\n\n'
        '## 2.4.18 — Nineteenth fresh twenty-round candidate through R19\n'
        '- Nineteen sequential review/fix/retest rounds finalized; defects corrected in rounds 1–7 and 9–19; round 8 was clean.\n'
        '- Canonicalized residual governance identifiers, removed public internal pagination/version identifiers, made key Future state/provenance transitions atomic, serialized assignment updates and retired numeric public Future read registration.\n'
        '- Round 20 exact-head final repository QA remains pending; staging/live/operational evidence remains unverified.\n\n',
        1,
    )
changelog.write_text(cs, encoding='utf-8')

sbom = {
    'bomFormat': 'CycloneDX',
    'specVersion': '1.5',
    'version': 9,
    'metadata': {
        'timestamp': '2026-08-12T11:12:00Z',
        'component': {
            'type': 'application',
            'name': 'File 06 Homeopathy Encyclopedia',
            'version': '2.4.18',
            'purl': 'pkg:wordpress/homeopathy-encyclopedia@2.4.18',
        },
    },
    'components': [
        {'type': 'framework', 'name': 'WordPress', 'version': '>=6.1; final CI target 7.0.1', 'scope': 'required'},
        {'type': 'platform', 'name': 'PHP', 'version': '>=7.4; final CI target 8.3', 'scope': 'required'},
        {'type': 'external', 'name': 'File 00 identity/current-claim contract', 'version': 'versioned platform contract', 'scope': 'required'},
    ],
    'release': {
        'file': '06-homeopathy-encyclopedia-foundation-2.4.18.zip',
        'sha256': None,
        'bytes': None,
        'source_tree_sha256': None,
        'evidence_policy': 'Exact values are emitted by the final exact-head workflow; not self-embedded in source.',
        'schema': 10,
        'future_schema': 2,
        'contract': '2.4.18',
        'review_rounds_completed': 19,
        'defect_rounds': [1,2,3,4,5,6,7,9,10,11,12,13,14,15,16,17,18,19],
        'clean_rounds': [8],
        'pending_rounds': [20],
        'staging_accepted': False,
        'live_deployed': False,
        'operational': False,
    },
}
(root / 'SBOM.json').write_text(json.dumps(sbom, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

hist = root / 'tests' / 'v2417-eighteenth-twenty-round-regressions.php'
hs = hist.read_text(encoding='utf-8')
a = hs.find('if($round>=19){')
b = hs.find('\nif($round>=20){', a)
if a < 0 or b < 0:
    raise SystemExit('historical v2417 R19 markers missing')
historical = "if($round>=19){preg_match(\"/define\\( 'HE_VERSION', '([^']+)' \\)/\",$boot,$vm);$current=$vm[1]??'';ok17($current&&version_compare($current,'2.4.17','>=')&&has17($runall,'v2417-eighteenth-twenty-round-regressions.php'),'R19 historical v2.4.17 release controls are not preserved under the current candidate');}"
hist.write_text(hs[:a] + historical + hs[b:], encoding='utf-8')

test = root / 'tests' / 'v2418-nineteenth-twenty-round-regressions.php'
test.write_text(r'''<?php
/** File 06 v2.4.18 nineteenth fresh twenty-round regression matrix. */
error_reporting(E_ERROR | E_PARSE);
$root=dirname(__DIR__);$inc=$root.'/homeopathy-encyclopedia/includes';$fail=array();
function r18($p){$v=file_get_contents($p);if(false===$v)throw new RuntimeException($p);return $v;}
function ok18($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
function has18($s,$n){return false!==strpos($s,$n);}
$ledger=r18($root.'/docs/FILE-06-v2.4.18-NINETEENTH-TWENTY-ROUND-REVIEW.md');preg_match_all('/^([0-9]+)\. \*\*(?:DEFECT|CLEAN)\*\*/m',$ledger,$lm);$round=!empty($lm[1])?max(array_map('intval',$lm[1])):0;
$boot=r18($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');$readme=r18($root.'/homeopathy-encyclopedia/readme.txt');$runall=r18($root.'/tests/run-all.sh');$api=r18($inc.'/class-he-v24-future-api.php');$schema=r18($inc.'/class-he-v24-future-schema.php');$gov=r18($inc.'/class-he-v241-governance.php');$rg=r18($inc.'/class-he-v241-research-governance.php');$runtime=r18($inc.'/class-he-v241-runtime-guard.php');$integrity=r18($inc.'/class-he-v22-integrity.php');$corr=r18($inc.'/class-he-v2418-corrections.php');$domain=r18($inc.'/class-he-v2-domain.php');
if($round>=1)ok18(has18($rg,'research-reviewer-assignment/(?P<id>')&&has18($rg,'WHERE public_id=%s'),'R1 canonical research reviewer target missing');
if($round>=2)ok18(has18($rg,"/research/('.$uuid.')/review")&&has18($rg,'he_reviewer_assignment_required'),'R2 canonical research review assignment gate missing');
if($round>=3)ok18(has18($runtime,"decode_public_cursor( 'external-record'")&&has18($runtime,'concept_from_claim_public_id'),'R3 canonical external object-scope guard missing');
if($round>=4)ok18(has18($integrity,'apply_entry_atomic')&&has18($integrity,'WHERE public_id=%s'),'R4 canonical integrity transition/apply ownership missing');
if($round>=5)ok18(has18($corr,'he_reviewer_assignment_required')&&has18($boot,'HE_V2418_Corrections::hooks()'),'R5 canonical integrity reviewer gate missing');
if($round>=6)ok18(has18($api,"decode_public_cursor( 'research-browse'")&&has18($api,"encode_public_cursor( 'research-browse'"),'R6 opaque research pagination missing');
if($round>=7)ok18(has18($domain,'SELECT version_number,status,title,content_hash')&&!has18($domain,'SELECT id,version_number,status,title,content_hash'),'R7 internal version row ID remains public');
if($round>=8)ok18(has18($ledger,'8. **CLEAN**'),'R8 clean dataset review record missing');
if($round>=9)ok18(has18($api,'external_stage_atomic_failed')&&has18($api,'metadata.staged'),'R9 external staging/provenance atomicity missing');
if($round>=10)ok18(has18($api,'claim_save_atomic_failed')&&has18($api,'claim.saved'),'R10 claim save/provenance atomicity missing');
if($round>=11)ok18(has18($api,'claim_review_atomic_failed')&&has18($api,'claim.reviewed'),'R11 claim review/provenance atomicity missing');
if($round>=12)ok18(has18($api,'translation_save_atomic_failed')&&has18($api,'translation.saved'),'R12 translation save atomicity missing');
if($round>=13)ok18(has18($api,'translation_review_atomic_failed')&&has18($api,'translation.outdated')&&has18($api,'translation.reviewed'),'R13 translation review provenance atomicity missing');
if($round>=14)ok18(has18($api,'translation_publish_atomic_failed')&&has18($api,"'translation_version' => $expected")&&has18($schema,'$notify = true')&&has18($schema,'$queued && $notify'),'R14 translation publication/impact atomicity missing');
if($round>=15)ok18(has18($api,'concept_mapping_atomic_failed')&&has18($api,'researcher_identity_atomic_failed'),'R15 mapping/provenance atomicity missing');
if($round>=16)ok18(has18($gov,'he_editor_scope_write_failed')&&has18($gov,'he_reviewer_assignment_write_failed')&&has18($rg,'he_research_reviewer_assignment_write_failed'),'R16 assignment persistence failure gates missing');
if($round>=17)ok18(substr_count($gov,'START TRANSACTION')>=2&&has18($gov,'clean_user_cache')&&has18($gov,'clean_post_cache')&&has18($rg,'FOR UPDATE')&&has18($rg,'clean_post_cache'),'R17 serialized assignment updates missing');
if($round>=18)ok18(has18($api,'/future/public/graph/(?P<id>[a-fA-F0-9-]{36})')&&!has18($api,"'/future/graph/(?P<id>\\\\d+)'")&&!has18($api,"'/future/time-machine/(?P<id>\\\\d+)'")&&!has18($api,"'/future/freshness/(?P<id>\\\\d+)'")&&!has18($api,"'/future/citations/(?P<id>\\\\d+)")&&!has18($api,'ctype_digit( $identifier )'),'R18 numeric public Future read registrations remain');
if($round>=19)ok18(has18($boot,' * Version: 2.4.18')&&has18($boot,"define( 'HE_VERSION', '2.4.18' )")&&has18($boot,"define( 'HE_CONTRACT_VERSION', '2.4.18' )")&&has18($boot,"'future_hardening_version'=>'2.4.18'")&&has18($readme,'Stable tag: 2.4.18')&&has18($runall,'v2418-nineteenth-twenty-round-regressions.php')&&has18($runall,'All File 06 v2.4.18 automated checks'),'R19 v2.4.18 release truth not aligned');
if($round>=20)ok18((bool)preg_match('/^20\. \*\*(?:DEFECT|CLEAN)\*\*/m',$ledger),'R20 final review result is not finalized');
for($i=1;$i<=$round;$i++)ok18(has18($ledger,$i.'. **'),'Review ledger missing round '.$i);
if($fail){fwrite(STDERR,"File 06 v2.4.18 nineteenth-review regressions FAILED through R{$round}:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.18 nineteenth-review regressions through R{$round}: PASS\n";
''', encoding='utf-8')

runall = root / 'tests' / 'run-all.sh'
rs = runall.read_text(encoding='utf-8')
anchor = 'php "$root/tests/v2417-eighteenth-twenty-round-regressions.php"\n'
if rs.count(anchor) != 1:
    raise SystemExit('run-all historical anchor mismatch')
rs = rs.replace(anchor, anchor + 'php "$root/tests/v2418-nineteenth-twenty-round-regressions.php"\n', 1)
rs = rs.replace('file06-v2.4.17-a.zip', 'file06-v2.4.18-a.zip').replace('file06-v2.4.17-b.zip', 'file06-v2.4.18-b.zip')
old_line = 'All File 06 v2.4.17 automated checks, inherited review matrices, seventeenth twenty-round regressions and deterministic package comparison passed.'
rs = replace_once(rs, old_line, 'All File 06 v2.4.18 automated checks, inherited review matrices, nineteenth twenty-round regressions and deterministic package comparison passed.', 'aggregate label')
runall.write_text(rs, encoding='utf-8')

print('v2418-r19-alignment-applied')

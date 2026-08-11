from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_required(text, old, new, label):
    if old not in text:
        raise SystemExit(f'round9 target missing: {label}')
    return text.replace(old, new, 1)

# Runtime version / contract truth. Target only release-bearing declarations so
# historical comments or test prose cannot make the updater brittle.
p = ROOT / 'homeopathy-encyclopedia/homeopathy-encyclopedia.php'
text = p.read_text(encoding='utf-8')
text = replace_required(text, ' * Version: 2.4.3\n', ' * Version: 2.4.4\n', 'plugin header version')
text = replace_required(text, "define( 'HE_VERSION', '2.4.3' );", "define( 'HE_VERSION', '2.4.4' );", 'HE_VERSION')
text = replace_required(text, "define( 'HE_CONTRACT_VERSION', '2.4.3' );", "define( 'HE_CONTRACT_VERSION', '2.4.4' );", 'HE_CONTRACT_VERSION')
p.write_text(text, encoding='utf-8')

# Stable core invariant contract must follow the active candidate version.
p = ROOT / 'tests/v2-invariants.php'
text = p.read_text(encoding='utf-8')
text = replace_required(text, "HE_VERSION', '2.4.3", "HE_VERSION', '2.4.4", 'core invariant version')
text = replace_required(text, "HE_CONTRACT_VERSION', '2.4.3", "HE_CONTRACT_VERSION', '2.4.4", 'core invariant contract')
text = replace_required(text, 'passed under v2.4.3.', 'passed under v2.4.4.', 'core invariant result label')
p.write_text(text, encoding='utf-8')

# WordPress package readme.
p = ROOT / 'homeopathy-encyclopedia/readme.txt'
text = p.read_text(encoding='utf-8')
text = replace_required(text, 'Stable tag: 2.4.1', 'Stable tag: 2.4.4', 'stable tag')
text = replace_required(text, 'The 2.4.1 candidate also requires', 'The 2.4.4 candidate also requires', 'candidate prose')
marker = '== Changelog ==\n\n'
entry = "= 2.4.4 =\n* Fifth fresh ten-round corrective candidate: fail-closed Future routes during migration, minimized public research DTOs, deterministic research save concurrency, canonical source-language ownership, research reviewer privacy lifecycle coverage, unconditional published-research admin immutability, and refreshed exact-head QA.\n\n"
if marker not in text:
    raise SystemExit('round9 readme changelog marker missing')
text = text.replace(marker, marker + entry, 1)
p.write_text(text, encoding='utf-8')

# Human repository overview: remove stale package claims rather than relabeling old evidence.
p = ROOT / 'README.md'
text = p.read_text(encoding='utf-8')
text = replace_required(text, '# File 06 — Homeopathy Encyclopedia 2.4.2', '# File 06 — Homeopathy Encyclopedia 2.4.4', 'README heading')
text = replace_required(text, 'This branch is the third fresh 80-round review/fix candidate', 'This branch is the fifth fresh ten-round review/fix candidate', 'README cycle label')
text = replace_required(text, '- Plugin version: `2.4.2`', '- Plugin version: `2.4.4`', 'README plugin version')
text = replace_required(text, '- Contract: `2.4.2`', '- Contract: `2.4.4`', 'README contract version')
text = replace_required(text, '06-homeopathy-encyclopedia-foundation-2.4.2.zip', '06-homeopathy-encyclopedia-foundation-2.4.4.zip', 'README build filename')
start = 'Final reviewed package evidence:\n\n'
end = '\n## Release truth\n'
if start not in text or end not in text:
    raise SystemExit('round9 README package-evidence block missing')
before, rest = text.split(start, 1)
_, after = rest.split(end, 1)
text = before + 'Final reviewed package evidence: **pending the round-10 exact-head reproducible-package gate.**\n' + end + after
p.write_text(text, encoding='utf-8')

# Status must not carry forward old v2.4.2 package hashes as if they described the new candidate.
p = ROOT / 'STATUS.md'
text = p.read_text(encoding='utf-8')
replacements = [
    ('# File 06 Status — 2.4.2 Third Fresh 80-Round Candidate', '# File 06 Status — 2.4.4 Fifth Fresh Ten-Round Candidate', 'STATUS heading'),
    ('| Coded | `audit/file-06-third-80-round-v2.4.2` |', '| Coded | `audit/file-06-fifth-ten-round-v2.4.4` — exact final HEAD pending round 10 |', 'STATUS branch'),
    ('| Reviewed | Third fresh 80-round review/fix cycle completed; two separate post-final-code reviews are recorded in `docs/REVIEW-V242-ROUND-1.md` and `docs/REVIEW-V242-ROUND-2.md` |', '| Reviewed | Fifth fresh ten-round corrective cycle in progress; rounds 1–9 completed, final QA round pending |', 'STATUS reviewed'),
    ('| Packaged | Deterministic double build PASS |', '| Packaged | Pending round-10 exact-head deterministic double build |', 'STATUS packaged'),
    ('| Automated QA | GitHub Actions run `31454206508`: PHP 7.4 PASS, PHP 8.3 PASS, core/first-80/Future-18/second-80/third-80 invariants PASS, reproducible package PASS, WordPress 7.0.1 + PHP 8.3 fresh-install/plugin-lifecycle smoke PASS |', '| Automated QA | Per-round source validation/regression suites green; final exact-head matrix pending round 10 |', 'STATUS automated QA'),
    ('| Package SHA-256 | `b031e5bfec3130713fe812cf14614a83c43d35ed92c130f02e98b0c98fd7975a` |', '| Package SHA-256 | Pending round 10 |', 'STATUS package SHA'),
    ('| Package bytes | `183423` |', '| Package bytes | Pending round 10 |', 'STATUS bytes'),
    ('| Source-tree SHA-256 | `4e36b9f8ecd6346861b17f44b5eded0fa0d2210bbb16178030d8ff111100829a` |', '| Source-tree SHA-256 | Pending round 10 |', 'STATUS source SHA'),
    ('- Plugin: `2.4.2`', '- Plugin: `2.4.4`', 'STATUS plugin'),
    ('- Contract: `2.4.2`', '- Contract: `2.4.4`', 'STATUS contract'),
]
for old, new, label in replacements:
    text = replace_required(text, old, new, label)
p.write_text(text, encoding='utf-8')

print('Applied File 06 v2.4.4 round-9 runtime/release-truth metadata correction')

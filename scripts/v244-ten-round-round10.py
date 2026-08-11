from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_required(text, old, new, label):
    if old not in text:
        raise SystemExit(f'round10 target missing: {label}')
    return text.replace(old, new, 1)

# Aggregate runner previously omitted both the prior v2.4.3 ten-round regression
# file and the new v2.4.4 suite, and still emitted v2.4.3 package labels.
p = ROOT / 'tests/run-all.sh'
text = p.read_text(encoding='utf-8')
text = replace_required(
    text,
    'php "$root/tests/v242-third-80-final.php"\n',
    'php "$root/tests/v242-third-80-final.php"\nphp "$root/tests/v243-ten-round-regressions.php"\nphp "$root/tests/v244-ten-round-regressions.php"\n',
    'aggregate regression wiring',
)
text = replace_required(text, 'file06-v2.4.3-a.zip', 'file06-v2.4.4-a.zip', 'package A label')
text = replace_required(text, 'file06-v2.4.3-b.zip', 'file06-v2.4.4-b.zip', 'package B label')
text = replace_required(
    text,
    'All File 06 v2.4.3 automated checks, corrected third fresh 80-round matrix and deterministic package comparison passed.',
    'All File 06 v2.4.4 automated checks, inherited review matrices, fifth ten-round regressions and deterministic package comparison passed.',
    'aggregate result label',
)
p.write_text(text, encoding='utf-8')

# The v2.4.3 regression file is historical defect coverage. Its two candidate
# identity assertions would incorrectly reject every legitimate later release;
# current release identity is now owned by v2.4.4/core gates.
p = ROOT / 'tests/v243-ten-round-regressions.php'
text = p.read_text(encoding='utf-8')
old = "$bootstrap = $read( 'homeopathy-encyclopedia/homeopathy-encyclopedia.php' );\n$has( $bootstrap, \"define( 'HE_VERSION', '2.4.3' );\", 'candidate version 2.4.3' );\n$has( $bootstrap, \"define( 'HE_CONTRACT_VERSION', '2.4.3' );\", 'contract version 2.4.3' );\n\n"
new = "$bootstrap = $read( 'homeopathy-encyclopedia/homeopathy-encyclopedia.php' );\n$has( $bootstrap, \"define( 'HE_SCHEMA_VERSION', 10 );\", 'schema lineage retained for v2.4.3 regressions' );\n\n"
text = replace_required(text, old, new, 'historical v243 candidate identity assertions')
p.write_text(text, encoding='utf-8')

# Repository documentation must describe this cycle rather than carrying the
# previous 80-round counters/hashes forward as current evidence.
p = ROOT / 'STATUS.md'
text = p.read_text(encoding='utf-8')
replacements = [
    ('| Coded | `audit/file-06-fifth-ten-round-v2.4.4` — exact final HEAD pending round 10 |', '| Coded | `audit/file-06-fifth-ten-round-v2.4.4`; exact final HEAD is the commit evaluated by the v2.4.4 final workflow |', 'STATUS coded'),
    ('| Reviewed | Fifth fresh ten-round corrective cycle in progress; rounds 1–9 completed, final QA round pending |', '| Reviewed | Fifth fresh ten-round review/fix cycle completed; defects found and corrected in rounds `1, 2, 3, 4, 6, 8, 9, 10`; rounds `5, 7` clean |', 'STATUS reviewed'),
    ('| Packaged | Pending round-10 exact-head deterministic double build |', '| Packaged | Deterministic double-build evidence is emitted by the final exact-head workflow; digest is deliberately not embedded in-source |', 'STATUS packaged'),
    ('| Automated QA | Per-round source validation/regression suites green; final exact-head matrix pending round 10 |', '| Automated QA | Final status is authoritative only from the completed `File 06 v2.4.4 Fifth Ten-Round Final QA` run on the exact branch HEAD |', 'STATUS automated QA'),
    ('| Package SHA-256 | Pending round 10 |', '| Package SHA-256 | See final exact-head workflow log; not embedded here to avoid digest self-reference/drift |', 'STATUS package SHA'),
    ('| Package bytes | Pending round 10 |', '| Package bytes | See final exact-head workflow log |', 'STATUS bytes'),
    ('| Source-tree SHA-256 | Pending round 10 |', '| Source-tree SHA-256 | See final exact-head workflow log |', 'STATUS source SHA'),
    ('| Third-cycle defect rounds | `4, 5, 7, 11, 17, 18, 19, 20, 21, 22, 28, 29, 30, 31, 32, 38, 39, 58, 61, 72, 73, 74, 75, 78` |\n| Clean rounds | `56 / 80` |', '| Fifth ten-round defect rounds | `1, 2, 3, 4, 6, 8, 9, 10` |\n| Fifth ten-round clean rounds | `5, 7` |', 'STATUS cycle counters'),
    ('## Third-80 hardening highlights', '## Inherited hardening and fifth ten-round corrections', 'STATUS hardening heading'),
]
for old, new, label in replacements:
    text = replace_required(text, old, new, label)
insert_after = '- CI corrected to use the current third-80 matrix and a working WordPress/WP-CLI runtime lifecycle smoke test.\n'
current = (
    '\nFifth ten-round corrections additionally enforce fail-closed Future routes during migration; public research DTO minimization; multi-writer research save concurrency; canonical BCP-47 source-language ownership; research reviewer privacy export/erasure; nonce-shape-independent published research immutability; truthful v2.4.4 release metadata; and a dedicated aggregate v2.4.4 regression gate.\n'
)
text = replace_required(text, insert_after, insert_after + current, 'STATUS current-cycle summary')
p.write_text(text, encoding='utf-8')

p = ROOT / 'README.md'
text = p.read_text(encoding='utf-8')
text = replace_required(
    text,
    'Final reviewed package evidence: **pending the round-10 exact-head reproducible-package gate.**',
    'Final package digest, byte count and source-tree digest are emitted by the exact-head v2.4.4 final workflow; historical hashes are not reused as current evidence.',
    'README package evidence truth',
)
text = replace_required(text, '## Third-80 hardening', '## Inherited hardening plus fifth ten-round corrections', 'README hardening heading')
marker = '- PHP 7.4/8.3 lint, deterministic packaging and WordPress 7.0.1/PHP 8.3 fresh-install + activation/deactivation/reactivation CI smoke are enforced.\n'
addition = (
    '- fifth-ten-round corrections add migration-ready route gating, minimized public research DTOs, deterministic research save concurrency, canonical source-language ownership, research-reviewer privacy coverage, unconditional immutable-state admin protection, v2.4.4 release-truth alignment and dedicated regression coverage.\n'
)
text = replace_required(text, marker, marker + addition, 'README current-cycle bullet')
p.write_text(text, encoding='utf-8')

print('Applied File 06 v2.4.4 round-10 aggregate QA/release-truth correction')

#!/usr/bin/env python3
"""Fail-closed V12.3 traceability inventory validation.

This verifies identifier coverage and evidence slots only; never interprets
source indicators as runtime/staging/live acceptance.
"""
import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
doc = root / "docs" / "FILE-06-V12.3-TRACEABILITY-2026-11-14.md"
text = doc.read_text(encoding="utf-8")
pattern = re.compile(r"^\| `(F06-(?:FR|NFR|CEN|FUT)-\d{2,3})` \| (.+) \| (.+) \| (.+) \| (UNVERIFIED) \|$", re.M)
rows = pattern.findall(text)
expected = (
    {f"F06-FR-{i:03d}" for i in range(1,20)}
    | {f"F06-NFR-{i:03d}" for i in range(1,11)}
    | {f"F06-CEN-{i:02d}" for i in range(1,3)}
    | {f"F06-FUT-{i:03d}" for i in range(1,19)}
)
actual = [row[0] for row in rows]
missing = sorted(expected - set(actual))
extra = sorted(set(actual) - expected)
duplicates = sorted({value for value in actual if actual.count(value) > 1})
incomplete = [row[0] for row in rows if not all(cell.strip() for cell in row[1:4])]
if len(rows) != 49 or missing or extra or duplicates or incomplete:
    raise SystemExit(
        "V12.3 inventory FAILED: "
        f"count={len(rows)}, missing={missing}, extra={extra}, "
        f"duplicates={duplicates}, incomplete={incomplete}"
    )
# Existence is weaker than semantic acceptance, but a fictional path must
# never be used as evidence of a requirement's implementation.
source_paths = {}
bad_paths = []
for requirement_id, _, source_cell, _, _ in rows:
    match = re.fullmatch(r"`(homeopathy-encyclopedia/[A-Za-z0-9_./-]+)`", source_cell.strip())
    if not match:
        bad_paths.append((requirement_id, source_cell.strip()))
        continue
    source = root / match.group(1)
    source_paths[requirement_id] = source
    if not source.is_file():
        bad_paths.append((requirement_id, match.group(1)))
if bad_paths or len(source_paths) != 49:
    raise SystemExit("V12.3 inventory invalid/nonexistent source paths: " + repr(bad_paths))
if "Deployed Version | UNVERIFIED" not in text or "Live Verification Status | UNVERIFIED" not in text:
    raise SystemExit("Release truth missing: no deployed/live evidence has been established.")
print("PASS: 49/49 IDs and 49 existing source-path references; implementation/live acceptance unverified.")

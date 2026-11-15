#!/usr/bin/env python3
"""Fail closed if the authorized read-only live freeze omits a required File06 DB table.

Compares the literal source schema registries, not a copy of a manually
maintained table count. Does not connect to any website or database.
"""
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
core = (ROOT / "homeopathy-encyclopedia/includes/class-he-v2-schema.php").read_text()
future = (ROOT / "homeopathy-encyclopedia/includes/class-he-v24-future-schema.php").read_text()
freeze = (ROOT / "scripts/file06-live-reality-freeze-readonly.sh").read_text()
extension = (ROOT / "homeopathy-encyclopedia/includes/class-he-v22-governance.php").read_text()
def between(text, start, end):
    assert start in text and end in text, (start, end)
    return text.split(start, 1)[1].split(end, 1)[0]
core_def = between(core, "public static function required_tables()", "public static function required_columns()")
core_tables = set(re.findall(r"'([a-z_]+)'", between(core_def, "return array(", ");")))
future_def = between(future, "public static function required_shape()", "public static function schema_complete()")
future_tables = set(re.findall(r"(?m)^\s*'([a-z_]+)'\s*=>\s*array", future_def))
assert len(core_tables) == 15, f"Unexpected core table inventory: {sorted(core_tables)}"
assert len(future_tables) == 12, f"Unexpected Future schema inventory: {sorted(future_tables)}"
assert not (core_tables & future_tables), "Schema registries must not overlap"
ext_def = between(extension, "public static function install_extensions()", "public static function register_rewrites()")
extension_tables = set(re.findall(r"HE_V2_Schema::table\(\s*'([a-z_]+)'\s*\)", ext_def)) - core_tables
assert extension_tables == {'migration_quarantine'}, (
    f"Unexpected File06 extension SQL table registry: {sorted(extension_tables)}"
)
required_tables = core_tables | future_tables | extension_tables
match = re.search(r"(?m)^FILE06_SCHEMA_SUFFIXES=\(([^)]*)\)", freeze)
assert match, "Freeze script missing literal, inspectable FILE06_SCHEMA_SUFFIXES array"
listed = match.group(1).split()
assert len(listed) == len(set(listed)), "Freeze repeats a table suffix"
missing = required_tables - set(listed)
extra = set(listed) - required_tables
assert not missing and not extra, f"Freeze schema drift: missing={sorted(missing)} extra={sorted(extra)}"
assert 'ACK_READONLY_FREEZE' in freeze and 'I_HAVE_AUTHORIZATION' in freeze
assert 'wp plugin list' in freeze and 'sha256sum' in freeze
assert 'SHOW COLUMNS' in freeze
print(f"File06 live-freeze source inventory: {len(core_tables)} core + {len(future_tables)} Future + {len(extension_tables)} extension = {len(listed)}/{len(required_tables)} exact schema tables (source-only).")

#!/usr/bin/env python3
import argparse, os, stat, zipfile
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--source', required=True)
parser.add_argument('--output', required=True)
args = parser.parse_args()
source = Path(args.source).resolve()
output = Path(args.output).resolve()
output.parent.mkdir(parents=True, exist_ok=True)
root_name = source.name
fixed = (2026, 8, 6, 0, 0, 0)
with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(p for p in source.rglob('*') if p.is_file()):
        rel = Path(root_name) / path.relative_to(source)
        info = zipfile.ZipInfo(rel.as_posix(), fixed)
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 3
        mode = 0o755 if os.access(path, os.X_OK) else 0o644
        info.external_attr = (stat.S_IFREG | mode) << 16
        archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
print(output)

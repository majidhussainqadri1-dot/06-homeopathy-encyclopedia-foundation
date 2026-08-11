#!/usr/bin/env python3
"""Build a byte-reproducible WordPress plugin ZIP."""
from __future__ import annotations
import argparse
from pathlib import Path
import zipfile

FIXED_TIME = (1980, 1, 1, 0, 0, 0)


def build(source: Path, output: Path) -> None:
    source = source.resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    files = sorted(p for p in source.rglob('*') if p.is_file())
    with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files:
            rel = path.relative_to(source.parent).as_posix()
            info = zipfile.ZipInfo(rel, FIXED_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (0o100644 & 0xFFFF) << 16
            info.create_system = 3
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    if not args.source.is_dir():
        raise SystemExit(f'Not a directory: {args.source}')
    build(args.source, args.output)


if __name__ == '__main__':
    main()

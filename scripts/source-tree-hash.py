#!/usr/bin/env python3
"""Print a deterministic SHA-256 for a source tree.

Algorithm: for every regular file in lexicographic POSIX path order, hash
relative_path + NUL + SHA256(file_bytes).hexdigest() + LF.
"""
from __future__ import annotations
import argparse
import hashlib
from pathlib import Path


def tree_hash(root: Path) -> str:
    digest = hashlib.sha256()
    files = sorted(p for p in root.rglob('*') if p.is_file())
    for path in files:
        rel = path.relative_to(root.parent).as_posix()
        file_hash = hashlib.sha256(path.read_bytes()).hexdigest()
        digest.update(rel.encode('utf-8'))
        digest.update(b'\0')
        digest.update(file_hash.encode('ascii'))
        digest.update(b'\n')
    return digest.hexdigest()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('source', type=Path)
    args = parser.parse_args()
    if not args.source.is_dir():
        raise SystemExit(f'Not a directory: {args.source}')
    print(tree_hash(args.source.resolve()))


if __name__ == '__main__':
    main()

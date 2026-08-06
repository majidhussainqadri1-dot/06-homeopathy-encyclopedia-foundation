#!/usr/bin/env python3
import argparse, hashlib
from pathlib import Path

def tree_hash(root: Path) -> str:
    digest = hashlib.sha256()
    for path in sorted(p for p in root.rglob('*') if p.is_file()):
        rel = path.relative_to(root).as_posix().encode()
        data = path.read_bytes()
        digest.update(len(rel).to_bytes(8, 'big'))
        digest.update(rel)
        digest.update(len(data).to_bytes(8, 'big'))
        digest.update(data)
    return digest.hexdigest()

parser = argparse.ArgumentParser()
parser.add_argument('root')
args = parser.parse_args()
print(tree_hash(Path(args.root)))

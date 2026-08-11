#!/usr/bin/env python3
import argparse, hashlib
from pathlib import Path
parser = argparse.ArgumentParser()
parser.add_argument('--root', required=True)
parser.add_argument('--manifest', required=True)
args = parser.parse_args()
root = Path(args.root)
errors=[]
for line in Path(args.manifest).read_text().splitlines():
    if not line.strip(): continue
    expected, rel = line.split('  ', 1)
    path = root / rel
    if not path.is_file(): errors.append(f'missing {rel}'); continue
    actual = hashlib.sha256(path.read_bytes()).hexdigest()
    if actual != expected: errors.append(f'hash mismatch {rel}')
if errors:
    raise SystemExit('\n'.join(errors))
print('Manifest verification passed.')

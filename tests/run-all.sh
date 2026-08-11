#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$root/homeopathy-encyclopedia" "$root/tests" -type f -name '*.php' -print0 | sort -z)
node --check "$root/homeopathy-encyclopedia/assets/js/encyclopedia-v2.js"
php "$root/tests/v2-invariants.php"
bash "$root/tests/v2-source-invariants.sh"
php "$root/tests/v23-future-invariants.php"
php "$root/tests/v24-80-round-invariants.php"
php "$root/tests/v241-second-80-invariants.php"
python3 -m py_compile "$root/scripts/build-release.py" "$root/scripts/source-tree-hash.py" "$root/scripts/verify-manifest.py"
a="${TMPDIR:-/tmp}/file06-v2.4.1-a.zip"
b="${TMPDIR:-/tmp}/file06-v2.4.1-b.zip"
python3 "$root/scripts/build-release.py" --source "$root/homeopathy-encyclopedia" --output "$a"
python3 "$root/scripts/build-release.py" --source "$root/homeopathy-encyclopedia" --output "$b"
cmp -s "$a" "$b"
sha256sum "$a"
python3 "$root/scripts/source-tree-hash.py" "$root/homeopathy-encyclopedia"
echo "All File 06 v2.4.1 automated checks and deterministic package comparison passed."

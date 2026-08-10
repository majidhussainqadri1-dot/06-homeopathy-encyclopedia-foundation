#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$root/homeopathy-encyclopedia" "$root/tests" -type f -name '*.php' -print0 | sort -z)
node --check "$root/homeopathy-encyclopedia/assets/js/encyclopedia-v2.js"
bash "$root/tests/v2-source-invariants.sh"
php "$root/tests/v23-future-invariants.php"
python3 -m py_compile "$root/scripts/build-release.py" "$root/scripts/source-tree-hash.py" "$root/scripts/verify-manifest.py"
echo "All File 06 v2.3 source and Future-18 checks passed."

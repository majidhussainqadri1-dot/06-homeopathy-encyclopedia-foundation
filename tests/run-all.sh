#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$root/homeopathy-encyclopedia" "$root/tests" -type f -name '*.php' -print0 | sort -z)
node --check "$root/homeopathy-encyclopedia/assets/js/encyclopedia-v2.js"
php "$root/tests/v23-regression-invariants.php"
php "$root/tests/v23-future-invariants.php"
bash "$root/tests/v2-source-invariants.sh"
echo "All File 06 v2.3 automated source checks passed."

#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$root/homeopathy-encyclopedia" "$root/tests" -type f -name '*.php' -print0 | sort -z)
node --check "$root/homeopathy-encyclopedia/assets/js/encyclopedia-v2.js"
php "$root/tests/v2-invariants.php"
bash "$root/tests/v2-source-invariants.sh"
python3 "$root/scripts/verify-manifest.py" --root "$root" --manifest "$root/V2-CHECKSUMS.sha256"
echo "All File 06 v2 automated checks passed."

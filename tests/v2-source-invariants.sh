#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
plugin="$root/homeopathy-encyclopedia"

test -f "$plugin/homeopathy-encyclopedia.php"
test "$(find "$plugin" -type f -name '*.php' | wc -l | tr -d ' ')" -ge 10
test "$(find "$plugin/includes" -type f -name 'class-he-v2-*.php' | wc -l | tr -d ' ')" -eq 8
! find "$plugin/includes" -type f -name 'class-he-*.php' ! -name 'class-he-v2-*.php' | grep -q .
! grep -R --line-number --include='*.php' --include='*.js' --include='*.css' -E '(AKIA[0-9A-Z]{16}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY)' "$plugin"
grep -q "Version: 2.0.0" "$plugin/homeopathy-encyclopedia.php"
grep -q "HE_SCHEMA_VERSION', 7" "$plugin/homeopathy-encyclopedia.php"
grep -q "sabri/v2/file-06" "$plugin/includes/class-he-v2-api.php"
grep -q "کامیاب کیس" "$plugin/includes/class-he-v2-domain.php"
grep -q -- "--sabri-primary:#16823b" "$plugin/assets/css/encyclopedia-v2.css"
! grep -R --line-number -- '--he-orange' "$plugin"
echo "File 06 v2 source-tree invariants passed."

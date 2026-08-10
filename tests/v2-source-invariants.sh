#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
plugin="$root/homeopathy-encyclopedia"

test -f "$plugin/homeopathy-encyclopedia.php"
test "$(find "$plugin" -type f -name '*.php' | wc -l | tr -d ' ')" -ge 13
test "$(find "$plugin/includes" -type f -name 'class-he-v2*.php' | wc -l | tr -d ' ')" -ge 11
! grep -R --line-number --include='*.php' --include='*.js' --include='*.css' -E '(AKIA[0-9A-Z]{16}|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY)' "$plugin"
grep -q "Version: 2.2.0" "$plugin/homeopathy-encyclopedia.php"
grep -q "HE_SCHEMA_VERSION', 8" "$plugin/homeopathy-encyclopedia.php"
grep -q "HE_CONTRACT_VERSION', '2.2" "$plugin/homeopathy-encyclopedia.php"
grep -q "sabri/v2/file-06" "$plugin/includes/class-he-v2-api.php"
grep -q "کامیاب کیس" "$plugin/includes/class-he-v2-domain.php"
grep -q "SMC_Contracts::assertions" "$plugin/includes/class-he-v2-auth.php"
grep -q "migration_quarantine" "$plugin/includes/class-he-v22-governance.php"
grep -q "he_integrity_acceptance_required" "$plugin/includes/class-he-v22-integrity.php"
grep -q "adverse_events" "$plugin/includes/class-he-v22-governance.php"
grep -q -- "--he-primary:var(--sabri-color-primary" "$plugin/assets/css/encyclopedia-v2.css"
! grep -q -- "--sabri-primary:#" "$plugin/assets/css/encyclopedia-v2.css"
! grep -R --line-number -- '--he-orange' "$plugin"
grep -q "default='06-homeopathy-encyclopedia-foundation'" "$root/scripts/build-release.py"
echo "File 06 v2.2 source-tree invariants passed."

#!/usr/bin/env bash
set -euo pipefail
root="homeopathy-encyclopedia"
test -f "$root/homeopathy-encyclopedia.php"
test "$(find "$root" -type f | wc -l)" -eq 19
test "$(find "$root" -type f -name '*.php' | wc -l)" -eq 15
grep -Fq "Version: 1.0.0" "$root/homeopathy-encyclopedia.php"
grep -Fq "define( 'HE_SCHEMA_VERSION', 2 );" "$root/homeopathy-encyclopedia.php"
grep -Fq "class-he-dependencies.php" "$root/homeopathy-encyclopedia.php"
grep -Fq "class-he-database.php" "$root/homeopathy-encyclopedia.php"
grep -Fq "SABRI_SHELL_VERSION" "$root/includes/class-he-dependencies.php"
grep -Fq "SLC_VERSION" "$root/includes/class-he-dependencies.php"
grep -Fq "capability_type'  => array( 'he_entry', 'he_entries' )" "$root/includes/class-he-content.php"
grep -Fq "HE_Permissions::post_type_caps()" "$root/includes/class-he-content.php"
grep -Fq "he_search_index" "$root/includes/class-he-database.php"
grep -Fq "row_version=row_version+1" "$root/includes/class-he-database.php"
grep -Fq "HE_PURGE_ON_UNINSTALL" "$root/uninstall.php"
! grep -R -Fq "option_comment_registration" "$root"
! grep -R -Fq "SPD_Helpers" "$root"
! grep -R -Fq "sabri_doctor_verified" "$root"
! grep -R -Fq "<main" "$root"
! grep -R -Fq "innerHTML" "$root/assets/js"
! grep -R -Fq "actions/checkout@v" .github/workflows || true

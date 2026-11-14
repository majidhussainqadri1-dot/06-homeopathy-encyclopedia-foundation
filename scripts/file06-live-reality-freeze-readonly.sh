#!/usr/bin/env bash
# File 06: STRICTLY READ-ONLY WordPress/DB/deployed-source reality freeze.
# Must run only with authorized Hostinger SSH/terminal access.
# Outputs metadata/schema/file hashes, never patient rows, secret option values,
# WordPress DB credentials, raw error logs or private uploads.
set -euo pipefail
: "${WP_PATH:?Set WP_PATH to the authorized WordPress installation directory}"
: "${ACK_READONLY_FREEZE:?Set ACK_READONLY_FREEZE=I_HAVE_AUTHORIZATION}"
if [[ "$ACK_READONLY_FREEZE" != "I_HAVE_AUTHORIZATION" ]]; then
  echo 'BLOCKED: explicit authorization acknowledgement absent' >&2; exit 2
fi
if [[ ! -d "$WP_PATH" ]] || [[ ! -f "$WP_PATH/wp-config.php" ]]; then
  echo 'BLOCKED: expected WordPress installation was not found' >&2; exit 2
fi
if ! command -v wp >/dev/null 2>&1; then
  echo 'BLOCKED: WP-CLI executable is unavailable' >&2; exit 2
fi
WP_PATH="$(cd "$WP_PATH" && pwd -P)"
flags=(--path="$WP_PATH" --skip-plugins --skip-themes)
echo 'FILE06_LIVE_REALITY_FREEZE_V12_3'
echo "UTC_UTCNOW=$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo 'LIVE_EVIDENCE_NOT_A_RELEASE_APPROVAL'
echo "[wordpress-core-version]"
wp core version "${flags[@]}"
echo "[php-runtime-version]"
php -r 'echo PHP_VERSION, PHP_EOL;'
echo "[wp-cli-runtime-version]"
wp cli version
echo "[installed-plugin-identities-no-bootstrap]"
wp plugin list "${flags[@]}" --fields=name,status,version --format=table

plugin_path="${FILE06_PLUGIN_PATH:-$WP_PATH/wp-content/plugins/homeopathy-encyclopedia}"
if [[ -d "$plugin_path" ]]; then
  echo "[file06-deployed-file-inventory-and-sha256]"
  # No symlink following, no file content, no export of raw PHP/config/log values.
  find "$plugin_path" -type f -not -path '*/.git/*' -print0 |
    LC_ALL=C sort -z |
    while IFS= read -r -d '' filename; do
      digest="$(sha256sum "$filename" | cut -d ' ' -f 1)"
      relative="${filename#"$plugin_path"/}"
      printf '%s  %s\n' "$digest" "$relative"
    done
else
  echo '[file06-plugin-directory] NOT_FOUND (custom installation path may differ)'
fi

echo "[file06-selected-nonsensitive-option-values]"
for key in \
  he_schema_version he_v24_future_version \
  he_v2_safe_mode he_v24_migration_pending \
  he_v24_provenance_migration_done he_v24_impact_migration_done \
  he_v24_orcid_postflight_done he_v24_emitted_postflight_done
do
  # Reading the above *known numeric/status markers only*. Never dump wp_options.
  if value="$(wp option get "$key" "${flags[@]}" 2>/dev/null)"; then
    if [[ "$value" =~ ^[0-9A-Za-z_.:-]{1,48}$ ]]; then
      printf '%s=%s\n' "$key" "$value"
    else
      printf '%s=REDACTED_NONSCALAR\n' "$key"
    fi
  else
    printf '%s=MISSING_OR_UNREADABLE\n' "$key"
  fi
done

prefix="$(wp db prefix "${flags[@]}")"
if [[ ! "$prefix" =~ ^[A-Za-z0-9_]{1,48}$ ]]; then
  echo 'BLOCKED: unsupported database table prefix' >&2; exit 2
fi
echo "[file06-db-table-names-and-structure]"
for suffix in concepts aliases versions references relations reviews \
  research dataset_access events outbox search_index \
  claims claim_evidence provenance impact_queue translations
do
  table="${prefix}he_${suffix}"
  echo "TABLE_NAME=$table"
  # SHOW COLUMNS is metadata only; no raw rows, no patient data.
  if ! wp db query "SHOW COLUMNS FROM \`$table\`;" "${flags[@]}" --skip-column-names 2>/dev/null |
      awk '{print $1 " " $2 " " $4}' ; then
    echo 'STRUCTURE_UNAVAILABLE'
  fi
done

echo '[safety-evidence-boundary]'
echo 'NEVER_INCLUDE_RAW_PATIENT_ROWS_IDENTIFIERS_UPLOADS_LOGS_OR_SECRETS'
echo 'REPOSITORY_MAIN_STAGING_AND_DEPLOYMENT_REMAIN_SEPARATE'
echo 'PASS_READONLY_METADATA_CAPTURE_ONLY'

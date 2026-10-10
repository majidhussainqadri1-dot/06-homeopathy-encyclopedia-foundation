<?php
/**
 * File 06: 20 separately reported, source-level governing-plan/companion parity checks.
 * These are static safeguards, not a substitute for WordPress runtime/staging acceptance
 * or manual semantic code review. One test group corresponds to one review focus.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$plugin = $root . '/homeopathy-encyclopedia';
$checks = [
    'R01 Fixed sixteen-type knowledge taxonomy' => [
        ['includes/class-he-v22-type-schemas.php', "'body_system_required' =>", 16],
        ['homeopathy-encyclopedia.php', "'fixed_type_count'"],
    ],
    'R02 Type-specific schema validation' => [
        ['includes/class-he-v22-type-schemas.php', 'he_type_schema_validation_failed'],
    ],
    'R03 Canonical identity, atomic aliases' => [
        ['includes/class-he-v2-domain.php', 'alias_atomic_write_failed'],
        ['includes/class-he-v2-domain.php', 'public_id'],
    ],
    'R04 Search, spelling recovery, public index' => [
        ['includes/class-he-v22-search.php', 'spelling-recovery'],
        ['includes/class-he-v22-search.php', 'published'],
    ],
    'R05 Reference versions, rights and graph provenance' => [
        ['includes/class-he-v242-third-audit.php', 'he_reference_version_scope'],
        ['includes/class-he-v242-reference-graph.php', 'he_relation_provenance_required'],
    ],
    'R06 Safety and clinical restrictions' => [
        ['includes/class-he-v2-public.php', 'red_flag'],
        ['includes/class-he-v22-research-guard.php', 'case_consent_verified'],
    ],
    'R07 Editor and reviewer authority' => [
        ['includes/class-he-v241-governance.php', 'META_REVIEW_ASSIGNMENTS'],
        ['homeopathy-encyclopedia.php', 'submit_entry_review'],
    ],
    'R08 Version integrity and published research immutability' => [
        ['includes/class-he-v22-governance.php', 'reviewed_row_version'],
        ['includes/class-he-v242-research-immutability.php', 'Published, corrected and retracted research records are immutable'],
    ],
    'R09 Corrections, retractions, accepted-only application' => [
        ['includes/class-he-v22-integrity.php', 'he_integrity_acceptance_required'],
        ['homeopathy-encyclopedia.php', 'File06IntegrityStateChanged.v1'],
    ],
    'R10 Secure merge, relation reconciliation' => [
        ['includes/class-he-v22-governance.php', 'secure_merge'],
        ['includes/class-he-v242-third-audit.php', 'he_merge_reason_required'],
    ],
    'R11 Research proposals, reviewer conflicts' => [
        ['includes/class-he-v242-research-authoring.php', 'he_v242_conflict_statement'],
        ['includes/class-he-v241-research-governance.php', 'research-reviewer-assignment'],
    ],
    'R12 Successful case consent and adverse-event context' => [
        ['includes/class-he-v22-research-guard.php', 'کامیاب کیس'],
        ['includes/class-he-v22-research-guard.php', 'adverse_events'],
        ['includes/class-he-v22-research-guard.php', 'case_anonymized'],
    ],
    'R13 Restricted datasets and de-identification' => [
        ['includes/class-he-v22-research-guard.php', 'he_dataset_private_by_default'],
        ['includes/class-he-v22-research-guard.php', 'de_identification'],
    ],
    'R14 Future-18 evidence, immutable provenance' => [
        ['includes/class-he-v23-future.php', 'F06-FUT-018'],
        ['includes/class-he-v24-future-schema.php', 'record_hash'],
        ['homeopathy-encyclopedia.php', "'future_requirement_count'=>18"],
    ],
    'R15 External bibliographic/advisory integrations' => [
        ['includes/class-he-v23-future.php', 'ORCID'],
        ['includes/class-he-v23-future.php', 'DataCite'],
        ['includes/class-he-v23-future.php', 'MeSH'],
        ['includes/class-he-v23-future.php', 'wp_safe_remote_get'],
    ],
    'R16 Governed translations and legacy language migration' => [
        ['includes/class-he-v242-multilingual.php', 'SSH-XPLAN-MLSEO-2026-v1.0'],
        ['includes/class-he-v242-language-migration.php', 'legacy_locale'],
        ['homeopathy-encyclopedia.php', 'get_governed_public_translations'],
    ],
    'R17 File 00 current claims; native fail-closed authorization' => [
        ['includes/class-he-v2-auth.php', 'SMC_Contracts::assertions'],
        ['includes/class-he-v2-auth.php', 'he_identity_provider_unavailable'],
        ['homeopathy-encyclopedia.php', "'native_object_scope_required'=>true"],
    ],
    'R18 Read-only File 05/12/15/16/21/26 consumers' => [
        ['includes/class-he-v22-consumers.php', "'file-05'"],
        ['includes/class-he-v22-consumers.php', "'file-12'"],
        ['includes/class-he-v22-consumers.php', "'file-15'"],
        ['includes/class-he-v22-consumers.php', "'file-16'"],
        ['includes/class-he-v22-consumers.php', "'file-21'"],
        ['includes/class-he-v22-consumers.php', "'file-26'"],
        ['includes/class-he-v22-consumers.php', "'write_authority' => false"],
    ],
    'R19 File 19/20/24/25/26 shared ownership' => [
        ['homeopathy-encyclopedia.php', "'notification_owner'=>'file-19'"],
        ['homeopathy-encyclopedia.php', "'layout_owner'=>'file-20'"],
        ['homeopathy-encyclopedia.php', "'assurance_owner'=>'file-24'"],
        ['homeopathy-encyclopedia.php', "'visual_owner'=>'file-25'"],
        ['homeopathy-encyclopedia.php', "'search_owner'=>'file-26'"],
    ],
    'R20 Exact candidate release truth and traceability' => [
        ['homeopathy-encyclopedia.php', 'Version: 2.4.20'],
        ['homeopathy-encyclopedia.php', "HE_CONTRACT_VERSION', '2.4.20'"],
        ['readme.txt', 'Stable tag: 2.4.20'],
        ['../STATUS.md', 'Staging accepted'],
        ['../tests/v2420-release-truth-regressions.php', '2.4.20'],
    ],
];

$failed = 0;
foreach ($checks as $label => $requirements) {
    $errors = [];
    foreach ($requirements as $rule) {
        [$path, $needle] = $rule;
        $filename = $plugin . '/' . $path;
        if (!is_file($filename)) {
            $errors[] = "missing source {$path}";
            continue;
        }
        $contents = file_get_contents($filename);
        if (false === $contents) {
            $errors[] = "unreadable {$path}";
        } elseif (isset($rule[2]) && substr_count($contents, $needle) !== $rule[2]) {
            $errors[] = "{$path}: expected {$rule[2]} occurrences of {$needle}";
        } elseif (false === strpos($contents, $needle)) {
            $errors[] = "{$path}: missing {$needle}";
        }
    }
    if ($errors) {
        ++$failed;
        fwrite(STDERR, "DEFECT {$label}: " . implode('; ', $errors) . PHP_EOL);
    } else {
        echo "STATIC-PASS {$label}" . PHP_EOL;
    }
}
if (count($checks) !== 20) {
    fwrite(STDERR, 'Incomplete 20-part parity matrix.' . PHP_EOL);
    exit(1);
}
if ($failed) {
    fwrite(STDERR, "Plan-parity static checks failed in {$failed}/20 areas." . PHP_EOL);
    exit(1);
}
echo "Twenty File 06 plan-parity source-indicator groups passed; staging/runtime acceptance remains unverified." . PHP_EOL;

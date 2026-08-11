<?php
/** File 06 v2.4.4 dedicated regressions for the fifth fresh ten-round review/fix cycle. */
$root = dirname( __DIR__ );
$read = static function( $path ) use ( $root ) {
    $data = file_get_contents( $root . '/' . $path );
    if ( false === $data ) { throw new RuntimeException( 'Missing regression target: ' . $path ); }
    return $data;
};
$has = static function( $haystack, $needle, $label ) {
    if ( false === strpos( $haystack, $needle ) ) { throw new RuntimeException( 'Regression invariant failed: ' . $label ); }
};
$not = static function( $haystack, $needle, $label ) {
    if ( false !== strpos( $haystack, $needle ) ) { throw new RuntimeException( 'Regression invariant failed: ' . $label ); }
};

$bootstrap = $read( 'homeopathy-encyclopedia/homeopathy-encyclopedia.php' );
$has( $bootstrap, ' * Version: 2.4.4', 'round 9 plugin header version' );
$has( $bootstrap, "define( 'HE_VERSION', '2.4.4' );", 'round 9 runtime version' );
$has( $bootstrap, "define( 'HE_SCHEMA_VERSION', 10 );", 'schema 10 remains current' );
$has( $bootstrap, "define( 'HE_CONTRACT_VERSION', '2.4.4' );", 'round 9 contract version' );

// Round 1 — all Future-table route overrides remain fail-closed until migration readiness.
foreach ( array(
    'homeopathy-encyclopedia/includes/class-he-v242-multilingual.php',
    'homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php',
    'homeopathy-encyclopedia/includes/class-he-v242-translation-compat.php',
    'homeopathy-encyclopedia/includes/class-he-v242-watchlist.php',
) as $path ) {
    $has( $read( $path ), "HE_V24_Migration_Safety::ready()", 'round 1 route readiness guard: ' . $path );
}
$language_migration = $read( 'homeopathy-encyclopedia/includes/class-he-v242-language-migration.php' );
$has( $language_migration, 'HE_V24_Migration_Safety::table_exists( $translations )', 'round 1 translations table preflight' );

// Round 2 — public research DTOs minimize restricted case/dataset data.
$research_browse = $read( 'homeopathy-encyclopedia/includes/class-he-v242-research-browse.php' );
$has( $research_browse, "case_details_restricted", 'round 2 restricted successful-case marker' );
$has( $research_browse, "dataset_payload_public", 'round 2 dataset payload remains non-public' );
$has( $research_browse, "array( 'description','de_identification','lawful_basis','access_policy' )", 'round 2 dataset public metadata allowlist' );
$not( $research_browse, "$out['case'] = json_decode", 'round 2 no unconditional public case JSON dump' );
$not( $research_browse, "$out['dataset_metadata'] = json_decode", 'round 2 no unconditional dataset metadata dump' );

// Round 3 — wp-admin optimistic concurrency accounts for every earlier verified writer.
$research_authoring = $read( 'homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php' );
$has( $research_authoring, "he_v2_research_nonce", 'round 3 v2 writer accounted' );
$has( $research_authoring, "he_v22_research_nonce", 'round 3 v22 writer accounted' );
$has( $research_authoring, '++$expected_now', 'round 3 same-request version increments are explicit' );

// Round 4 — one canonical BCP-47 source-language writer; legacy three-locale field cannot transiently reset it.
$admin = $read( 'homeopathy-encyclopedia/includes/class-he-v2-admin.php' );
$has( $admin, 'HE_V242_Language_Surfaces::NONCE_FIELD', 'round 4 legacy saver defers to language-surfaces owner' );
$has( $admin, 'Original source language box', 'round 4 canonical source-language UI guidance' );
$not( $admin, '<select name="he_v2_language">', 'round 4 legacy three-locale source select removed' );

// Round 5 was clean: retain fail-closed File 00 authorization evidence.
$auth = $read( 'homeopathy-encyclopedia/includes/class-he-v2-auth.php' );
$has( $auth, 'SMC_Contracts::assertions', 'round 5 File 00 assertions remain required' );
$has( $auth, 'he_identity_provider_unavailable', 'round 5 provider outage remains fail-closed' );

// Round 6 — reviewer personal-data lifecycle includes both knowledge entries and research posts.
$privacy = $read( 'homeopathy-encyclopedia/includes/class-he-v241-governance-privacy.php' );
$has( $privacy, 'array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE )', 'round 6 entry+research reviewer privacy coverage' );
$has( $privacy, "'object_public_id'", 'round 6 generic public object identifier' );
$has( $privacy, "'object_type'", 'round 6 exported object type' );

// Round 7 was clean: retain external-evidence target re-resolution and object authorization.
$future_api = $read( 'homeopathy-encyclopedia/includes/class-he-v24-future-api.php' );
$has( $future_api, 'resolve_external_binding', 'round 7 governed external binding' );
$has( $future_api, 'external_binding_permission', 'round 7 target authorization' );
$has( $future_api, 'HE_V2_Auth::rest_permission', 'round 7 native object permission revalidation' );

// Round 8 — immutable research cannot be bypassed by omitting one optional meta-box nonce.
$immutability = $read( 'homeopathy-encyclopedia/includes/class-he-v242-research-immutability.php' );
$has( $immutability, "array( 'published','corrected','retracted' )", 'round 8 immutable states' );
$not( $immutability, "he_v2_research_nonce", 'round 8 immutability is nonce-shape independent' );

// Round 9 — package/repository metadata is truthful for the corrected candidate.
$plugin_readme = $read( 'homeopathy-encyclopedia/readme.txt' );
$has( $plugin_readme, 'Stable tag: 2.4.4', 'round 9 stable tag' );
$repository_readme = $read( 'README.md' );
$has( $repository_readme, '# File 06 — Homeopathy Encyclopedia 2.4.4', 'round 9 repository candidate label' );
$status = $read( 'STATUS.md' );
$has( $status, '# File 06 Status — 2.4.4 Fifth Fresh Ten-Round Candidate', 'round 9 status label' );

// Round 10 — the aggregate gate itself must retain all prior and current regressions.
$run_all = $read( 'tests/run-all.sh' );
$has( $run_all, 'v243-ten-round-regressions.php', 'round 10 previous ten-round regression suite retained' );
$has( $run_all, 'v244-ten-round-regressions.php', 'round 10 current ten-round regression suite wired into aggregate gate' );
$has( $run_all, 'file06-v2.4.4-a.zip', 'round 10 deterministic package label A' );
$has( $run_all, 'file06-v2.4.4-b.zip', 'round 10 deterministic package label B' );

echo "PASS File 06 v2.4.4 fifth fresh ten-round regression invariants\n";

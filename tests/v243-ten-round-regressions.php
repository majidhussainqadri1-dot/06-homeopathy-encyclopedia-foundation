<?php
/** File 06 v2.4.3 dedicated regression assertions for the fresh ten-round review. */
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
$has( $bootstrap, "define( 'HE_VERSION', '2.4.3' );", 'candidate version 2.4.3' );
$has( $bootstrap, "define( 'HE_CONTRACT_VERSION', '2.4.3' );", 'contract version 2.4.3' );

$domain = $read( 'homeopathy-encyclopedia/includes/class-he-v2-domain.php' );
$has( $domain, 'bind_references_to_snapshot', 'snapshot reference binding exists' );
$has( $domain, 'SET source_reference_id=%d,row_version=row_version+1', 'relations remap to cloned snapshot reference' );
$has( $domain, 'AND version_id=%d ORDER BY id ASC', 'public references are exact-version bound' );

$governance = $read( 'homeopathy-encyclopedia/includes/class-he-v241-governance.php' );
$has( $governance, 'option_value=%s', 'maintenance stale lease uses compare-and-delete' );
$has( $governance, 'maybe_serialize( $existing )', 'maintenance stale lease compares exact serialized owner' );

$translation_guard = $read( 'homeopathy-encyclopedia/includes/class-he-v242-public-translation-guard.php' );
$has( $translation_guard, "array( 'translations', 'items' )", 'public translation collections are both sanitized' );
$has( $translation_guard, "unset( $item['source_version'] )", 'internal translation source-version id is stripped' );

$language = $read( 'homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php' );
$has( $language, "HE_V2_Schema::table( 'concepts' )", 'source-language metadata syncs canonical concept truth' );
$has( $language, "status='translation-outdated'", 'source-language changes invalidate governed translations' );
$has( $language, 'KnowledgeTranslationOutdated.v1', 'source-language change queues translation impact' );

$privacy = $read( 'homeopathy-encyclopedia/includes/class-he-v241-governance-privacy.php' );
$has( $privacy, 'assigned_posts_page( 1 )', 'mutating erasure always consumes first remaining batch' );

$first_save = $read( 'homeopathy-encyclopedia/includes/class-he-v22-admin-first-save.php' );
foreach ( array( 'he_v2_research_type', 'he_v2_data_class', 'he_v2_ethics_reference', 'he_v2_consent_verified', 'he_v2_case_anonymized', 'he_v2_case_baseline' ) as $field ) {
    $has( $first_save, $field, 'canonical research first-save field ' . $field );
}
$not( $first_save, "\$_POST['he_record_type']", 'legacy research type field removed' );

$integrations = $read( 'homeopathy-encyclopedia/includes/class-he-v2-integrations.php' );
$has( $integrations, 'he_composer_actor_mismatch', 'composer actor mismatch is rejected' );
$has( $integrations, '$actor_id !== $current_id', 'composer actor is bound to authenticated user' );
$has( $integrations, "'file06-composer-create-entry', \$actor_id", 'entry composer checks File 00 claims for bound actor' );
$has( $integrations, "'file06-composer-create-research', \$actor_id", 'research composer checks File 00 claims for bound actor' );

$future_api = $read( 'homeopathy-encyclopedia/includes/class-he-v24-future-api.php' );
$has( $future_api, 'external_evidence_token_parts', 'provider-qualified external evidence tokens are parsed' );
$has( $future_api, 'external_provider', 'external evidence requires provider identity' );
$has( $future_api, 'AND version_id=%d', 'claim evidence binds internal reference to version' );

$historical_ci = $read( '.github/workflows/file06-v2-complete.yml' );
$has( $historical_ci, '(Historical)', 'v2.4.2 workflow is explicitly historical' );
$not( $historical_ci, 'pull_request:', 'historical v2.4.2 workflow no longer competes on current PRs' );

echo "PASS File 06 v2.4.3 ten-round regression invariants\n";

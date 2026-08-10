<?php
/** Deterministic source and architecture invariants for File 06 v2.2. */
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$failures = array();

function he_test_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}
function he_read( $path ) {
	$content = file_get_contents( $path );
	if ( false === $content ) {
		throw new RuntimeException( 'Cannot read ' . $path );
	}
	return $content;
}

$bootstrap = he_read( $plugin . '/homeopathy-encyclopedia.php' );
$schema = he_read( $plugin . '/includes/class-he-v2-schema.php' );
$domain = he_read( $plugin . '/includes/class-he-v2-domain.php' );
$api = he_read( $plugin . '/includes/class-he-v2-api.php' );
$auth = he_read( $plugin . '/includes/class-he-v2-auth.php' );
$integrations = he_read( $plugin . '/includes/class-he-v2-integrations.php' );
$privacy = he_read( $plugin . '/includes/class-he-v2-privacy.php' );
$public = he_read( $plugin . '/includes/class-he-v2-public.php' );
$governance = he_read( $plugin . '/includes/class-he-v22-governance.php' );
$public_guard = he_read( $plugin . '/includes/class-he-v22-public-guard.php' );
$integrity = he_read( $plugin . '/includes/class-he-v22-integrity.php' );
$rest_guard = he_read( $plugin . '/includes/class-he-v22-rest-guard.php' );
$schedule = he_read( $plugin . '/includes/class-he-v22-schedule.php' );
$search = he_read( $plugin . '/includes/class-he-v22-search.php' );
$type_schemas = he_read( $plugin . '/includes/class-he-v22-type-schemas.php' );
$consumers = he_read( $plugin . '/includes/class-he-v22-consumers.php' );
$operations = he_read( $plugin . '/includes/class-he-v22-operations.php' );
$css = he_read( $plugin . '/assets/css/encyclopedia-v2.css' );
$js = he_read( $plugin . '/assets/js/encyclopedia-v2.js' );
$builder = he_read( $root . '/scripts/build-release.py' );
$all = implode( "\n", array( $bootstrap, $schema, $domain, $api, $auth, $integrations, $privacy, $public, $governance, $public_guard, $integrity, $rest_guard, $schedule, $search, $type_schemas, $consumers, $operations ) );

he_test_assert( false !== strpos( $bootstrap, "define( 'HE_VERSION', '2.2.0' )" ), 'Plugin version is not 2.2.0.' );
he_test_assert( false !== strpos( $bootstrap, "define( 'HE_SCHEMA_VERSION', 8 )" ), 'Schema version is not 8.' );
he_test_assert( false !== strpos( $bootstrap, "HE_CONTRACT_VERSION', '2.2" ), 'Contract version is not 2.2.' );
he_test_assert( false !== strpos( $bootstrap, "'staging_accepted' => false" ) && false !== strpos( $bootstrap, "'live_deployed' => false" ), 'Release truth must keep staging/live separate from coded candidate.' );
foreach ( array( 'class-he-v22-governance.php', 'class-he-v22-public-guard.php', 'class-he-v22-integrity.php', 'class-he-v22-rest-guard.php', 'class-he-v22-schedule.php', 'class-he-v22-search.php', 'class-he-v22-type-schemas.php', 'class-he-v22-consumers.php', 'class-he-v22-operations.php' ) as $file ) {
	he_test_assert( false !== strpos( $bootstrap, $file ), 'Bootstrap does not load ' . $file );
}

preg_match_all( "/=> __\( '/", $domain, $matches );
he_test_assert( count( $matches[0] ) >= 16, 'The governed taxonomy does not expose at least sixteen types.' );
he_test_assert( 16 === substr_count( $type_schemas, "'body_system_required' =>" ), 'The type-specific schema contract does not define all sixteen fixed types.' );
he_test_assert( false !== strpos( $type_schemas, 'he_type_schema_validation_failed' ), 'Type-specific publish validation is missing.' );

$required_tables = array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'reviews', 'integrity_actions', 'research', 'dataset_access', 'events', 'outbox', 'idempotency', 'bookmarks', 'rate_limits', 'search_index' );
foreach ( $required_tables as $table ) {
	he_test_assert( false !== strpos( $schema, "table( '" . $table . "' )" ), 'Missing schema table: ' . $table );
}
he_test_assert( false !== strpos( $governance, "table( 'migration_quarantine' )" ), 'Missing resumable migration quarantine.' );
he_test_assert( false !== strpos( $governance, 'content_hash char(64)' ) && false !== strpos( $governance, 'reviewed_row_version' ), 'Fresh reviews are not bound to exact content/version.' );

$required_routes = array( '/health', '/entries', '/versions', '/diff', '/bookmark', '/aliases', '/references', '/review', '/transition', '/integrity', '/graph', '/duplicates', '/merge', '/autocomplete', '/research', '/datasets', '/repair' );
foreach ( $required_routes as $route ) {
	he_test_assert( false !== strpos( $api, $route ), 'Missing REST route token: ' . $route );
}
foreach ( array( '/research/(?P<id>', '/research-integrity/', '/operations/reindex', '/integrity/(?P<id>', '/schemas' ) as $route ) {
	he_test_assert( false !== strpos( $governance . $integrity . $type_schemas, $route ), 'Missing v2.2 governance route: ' . $route );
}

$security_tokens = array( 'X-WP-Nonce', 'Idempotency-Key', 'he_rate_limited', 'he_safe_mode', 'row_version', 'he_version_conflict', 'CAP_PUBLISH', 'CAP_TAXONOMY', 'rest_permission' );
foreach ( $security_tokens as $token ) {
	he_test_assert( false !== strpos( $all, $token ), 'Missing security invariant: ' . $token );
}
he_test_assert( false !== strpos( $auth, 'SMC_Contracts::assertions' ) && false !== strpos( $auth, 'provider_ready' ), 'File 00 versioned assertion authority is missing.' );
he_test_assert( false !== strpos( $auth, 'he_identity_provider_unavailable' ), 'Protected actions do not fail closed when File 00 is unavailable.' );
he_test_assert( false === strpos( $auth, "return (bool) user_can( $user_id, 'manage_options' )" ), 'Legacy manage_options founder fallback remains.' );
he_test_assert( false !== strpos( $governance, 'add_option( self::LOCK_OPTION' ), 'Atomic add_option migration/upgrade lock is missing.' );
he_test_assert( false !== strpos( $governance, 'current-session-only' ), 'Composer actor/session binding is missing.' );
he_test_assert( false !== strpos( $rest_guard, "'published' === \$state" ) && false !== strpos( $rest_guard, 'HE_V2_Auth::CAP_PUBLISH' ), 'Research public publication is not reauthorized through File 00/publish capability.' );

$knowledge_tokens = array( 'wp_generate_uuid4', 'normalized_alias', 'evidence_grade', 'quotation_word_count', 'content_hash', 'wp_text_diff', 'relation_types', 'get_related_graph', 'source_grade' );
foreach ( $knowledge_tokens as $token ) {
	he_test_assert( false !== strpos( $all, $token ), 'Missing knowledge invariant: ' . $token );
}
he_test_assert( false !== strpos( $governance, 'secure_merge' ) && false !== strpos( $governance, '$tv' ) && false !== strpos( $governance, 'alias-third-party-collision' ), 'Merge does not protect both row versions and alias ownership.' );
he_test_assert( false !== strpos( $governance, 'reindex_concept_secure' ) && false !== strpos( $governance, "table( 'versions' )" ), 'Public search is not rebuilt from immutable governed versions.' );
he_test_assert( false !== strpos( $governance, 'find_duplicates' ) && false !== strpos( $governance, 'c.id<>%d' ), 'Scoped duplicate detection against other concepts is missing.' );
he_test_assert( false !== strpos( $governance, 'he_relation_provenance_invalid' ), 'Relationship provenance ownership validation is missing.' );
he_test_assert( false !== strpos( $search, 'spelling-recovery' ) && false !== strpos( $search, 'similar_text' ), 'Bounded spelling recovery is missing.' );
he_test_assert( false !== strpos( $search, 'exact-phrase-token-alias' ), 'Exact/phrase/token/alias search semantics are missing.' );

$integrity_tokens = array( 'EncyclopediaEntryCorrected.v1', 'EncyclopediaEntryRetracted.v1', 'KnowledgeConceptMerged.v1', 'replacement_object_id', 'appeal_status', 'he_integrity_acceptance_required', 'File06IntegrityStateChanged.v1', 'ResearchPublicationCorrected.v1' );
foreach ( $integrity_tokens as $token ) {
	he_test_assert( false !== strpos( $all, $token ), 'Missing integrity invariant: ' . $token );
}
he_test_assert( false !== strpos( $governance, 'he_integrity_workflow_required' ), 'Direct corrected/retracted transitions are not blocked.' );
he_test_assert( false !== strpos( $integrity, "SELECT * FROM {$actions}" ) || false !== strpos( $integrity, 'FOR UPDATE' ), 'Atomic integrity apply row locking is missing.' );
he_test_assert( false !== strpos( $integrity, "status='accepted'" ) && false !== strpos( $integrity, 'START TRANSACTION' ) && false !== strpos( $integrity, 'ROLLBACK' ), 'Integrity apply is not accepted-only and transactional.' );

$research_tokens = array( 'ethics_review', 'peer_review', 'successful-case', 'کامیاب کیس', 'case_anonymized', 'case_consent_verified', 'adverse_events', 'dataset_access', 'lawful_basis', 'expires_at', 'he_case_pii_detected', 'dataset_description', 'de_identification', 'access_policy', 'he_fresh_independent_review_required' );
foreach ( $research_tokens as $token ) {
	he_test_assert( false !== strpos( $all, $token ), 'Missing research invariant: ' . $token );
}
he_test_assert( false !== strpos( $public_guard, "array( 'published', 'corrected', 'retracted' )" ), 'Corrected/retracted research public integrity metadata is not preserved.' );
he_test_assert( false !== strpos( $public_guard, "'public' ===" ), 'Restricted research protocol public guard is missing.' );
he_test_assert( false !== strpos( $public_guard, 'ScholarlyArticle' ) && false !== strpos( $public_guard, '<link rel="canonical"' ), 'Research canonical/structured-data output is missing.' );

he_test_assert( false !== strpos( $schedule, '_he_schedule_content_hash' ) && false !== strpos( $schedule, 'EncyclopediaEntryScheduleInvalidated.v1' ), 'Scheduled publication does not revalidate exact reviewed content.' );
he_test_assert( false !== strpos( $schedule, 'content-or-review-changed-before-publication' ), 'Scheduled publication fail-closed invalidation is missing.' );

$contract_tokens = array( 'sabri_composer_content_types', 'sabri_publishing_dashboard_providers', 'sabri_search_connectors', 'sabri_security_assurance_providers', 'sabri_shell_routes', 'sabri_public_component_registry', 'native_enforcement_preserved', 'visibility_recheck' );
foreach ( $contract_tokens as $token ) {
	he_test_assert( false !== strpos( $integrations, $token ), 'Missing cross-file contract: ' . $token );
}
foreach ( array( "'identity_authority'=> 'file-00'", "'layout_owner'      => 'file-20'", "'visual_owner'      => 'file-25'", "'search_owner'      => 'file-26'", "'assurance_owner'   => 'file-24'" ) as $token ) {
	he_test_assert( false !== strpos( $bootstrap, $token ), 'Missing canonical cross-file owner declaration: ' . $token );
}
foreach ( array( 'file-05', 'file-12', 'file-15', 'file-16', 'file-21', 'file-26' ) as $consumer ) {
	he_test_assert( false !== strpos( $consumers, "'" . $consumer . "'" ), 'Missing explicit File 06 consumer contract: ' . $consumer );
}
he_test_assert( false !== strpos( $consumers, "'write_authority' => false" ) && false !== strpos( $consumers, "'private_fields' => false" ), 'Consumer contracts are not explicitly read-only/public-safe.' );
he_test_assert( false !== strpos( $consumers, "'query' => array( __CLASS__, 'query' )" ) && false !== strpos( $consumers, "'rebuild_is_bounded' => true" ), 'File 26 is not routed to the v2.2 bounded search connector.' );
he_test_assert( false !== strpos( $consumers, "'token_ownership'] = 'file-25'" ), 'File 25 visual token ownership is not declared.' );

$privacy_tokens = array( 'wp_privacy_personal_data_exporters', 'wp_privacy_personal_data_erasers', 'he_v2_privacy_legal_hold', 'PAGE_SIZE', 'dataset_access', 'noindex', 'nocache_headers', 'explicit allowlists' );
foreach ( $privacy_tokens as $token ) {
	he_test_assert( false !== strpos( $privacy . $domain . $public . $public_guard, $token ), 'Missing privacy invariant: ' . $token );
}
he_test_assert( false !== strpos( $privacy, "'done' => $done" ), 'Privacy erasure is not resumable to a proven completion state.' );

he_test_assert( false !== strpos( $operations, "status='dead-letter'" ) && false !== strpos( $operations, "status IN ('pending','retry')" ), 'Operational health does not reflect actual outbox/dead-letter states.' );
he_test_assert( false !== strpos( $rest_guard, 'HE_V22_Operations::health()' ), 'REST health does not use corrected operational evidence.' );

he_test_assert( false !== strpos( $css, '--he-primary:var(--sabri-color-primary,var(--sabri-primary,#087A4E))' ), 'File 06 does not consume File 25/shared primary token with a safe fallback.' );
he_test_assert( false === strpos( $css, '--sabri-primary:#' ), 'File 06 still declares global Sabri primary token ownership.' );
he_test_assert( false !== strpos( $css, ':focus-visible' ), 'Visible focus style is missing.' );
he_test_assert( false !== strpos( $css, 'prefers-reduced-motion' ), 'Reduced-motion style is missing.' );
he_test_assert( false !== strpos( $css, '[dir="rtl"]' ), 'RTL style is missing.' );
he_test_assert( false !== strpos( $css, 'forced-colors' ), 'Forced-colors support is missing.' );
he_test_assert( false !== strpos( $js, 'AbortController' ), 'Cancelable query behavior is missing.' );
he_test_assert( false !== strpos( $js, 'aria-busy' ), 'Accessible loading state is missing.' );

he_test_assert( false !== strpos( $builder, "default='06-homeopathy-encyclopedia-foundation'" ), 'Canonical package top-level folder does not match the new File 06 plan.' );
he_test_assert( false !== strpos( $governance, 'BATCH_SIZE = 50' ) && false !== strpos( $governance, 'migration_quarantine' ) && false !== strpos( $governance, 'reconcile_outbox' ), 'Bounded migration/reconciliation/repair controls are incomplete.' );

$all_php = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin ) ) as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$all_php .= he_read( $file->getPathname() );
	}
}
he_test_assert( 0 === preg_match( '/\b(eval|create_function)\s*\(/i', $all_php ), 'Unsafe dynamic code execution found.' );
he_test_assert( substr_count( $all_php, 'wp_unslash( $_POST' ) >= 10, 'Admin request handling is not explicitly unslashed and sanitized.' );
he_test_assert( false === strpos( $all_php, 'wp_remote_get( $_' ), 'Unvalidated outbound URL fetch found.' );

if ( $failures ) {
	fwrite( STDERR, "File 06 v2.2 invariant failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "File 06 v2.2 source, dual-plan and architecture invariants passed.\n";

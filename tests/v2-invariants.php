<?php
/** Deterministic source and architecture invariants for File 06 v2. */
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
$css = he_read( $plugin . '/assets/css/encyclopedia-v2.css' );
$js = he_read( $plugin . '/assets/js/encyclopedia-v2.js' );

he_test_assert( false !== strpos( $bootstrap, "define( 'HE_VERSION', '2.0.0' )" ), 'Plugin version is not 2.0.0.' );
he_test_assert( false !== strpos( $bootstrap, "define( 'HE_SCHEMA_VERSION', 7 )" ), 'Schema version is not 7.' );
he_test_assert( false !== strpos( $bootstrap, "HE_CONTRACT_VERSION', '2.0" ), 'Contract version is not 2.0.' );

preg_match_all( "/=> __\( '/", $domain, $matches );
he_test_assert( count( $matches[0] ) >= 16, 'The governed taxonomy does not expose at least sixteen types.' );

$required_tables = array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'reviews', 'integrity_actions', 'research', 'dataset_access', 'events', 'outbox', 'idempotency', 'bookmarks', 'rate_limits', 'search_index' );
foreach ( $required_tables as $table ) {
	he_test_assert( false !== strpos( $schema, "table( '" . $table . "' )" ), 'Missing schema table: ' . $table );
}

$required_routes = array( '/health', '/entries', '/versions', '/diff', '/bookmark', '/aliases', '/references', '/review', '/transition', '/integrity', '/graph', '/duplicates', '/merge', '/autocomplete', '/research', '/datasets', '/repair' );
foreach ( $required_routes as $route ) {
	he_test_assert( false !== strpos( $api, $route ), 'Missing REST route token: ' . $route );
}

$security_tokens = array( 'X-WP-Nonce', 'Idempotency-Key', 'he_rate_limited', 'he_safe_mode', 'row_version', 'he_version_conflict', 'membership_allowed', 'CAP_PUBLISH', 'CAP_TAXONOMY', 'rest_permission' );
foreach ( $security_tokens as $token ) {
	he_test_assert( false !== strpos( $api . $auth . $domain, $token ), 'Missing security invariant: ' . $token );
}

$knowledge_tokens = array( 'wp_generate_uuid4', 'normalized_alias', 'evidence_grade', 'quotation_word_count', 'content_hash', 'wp_text_diff', 'find_duplicates', 'merge_concepts', 'relation_types', 'get_related_graph', 'source_grade' );
foreach ( $knowledge_tokens as $token ) {
	he_test_assert( false !== strpos( $domain . $schema . $api . $bootstrap . $integrations, $token ), 'Missing knowledge invariant: ' . $token );
}

$integrity_tokens = array( 'EncyclopediaEntryCorrected.v1', 'EncyclopediaEntryRetracted.v1', 'KnowledgeConceptMerged.v1', 'replacement_object_id', 'appeal_status' );
foreach ( $integrity_tokens as $token ) {
	he_test_assert( false !== strpos( $domain . $schema . $integrations, $token ), 'Missing integrity invariant: ' . $token );
}

$research_tokens = array( 'ethics_review', 'peer_review', 'successful-case', 'کامیاب کیس', 'case_anonymized', 'case_consent_verified', 'adverse_events', 'dataset_access', 'lawful_basis', 'expires_at', 'he_case_pii_detected' );
foreach ( $research_tokens as $token ) {
	he_test_assert( false !== strpos( $domain . $schema, $token ), 'Missing research invariant: ' . $token );
}

$contract_tokens = array( 'sabri_composer_content_types', 'sabri_publishing_dashboard_providers', 'sabri_search_connectors', 'sabri_security_assurance_providers', 'sabri_shell_routes', 'sabri_public_component_registry', 'native_enforcement_preserved', 'visibility_recheck' );
foreach ( $contract_tokens as $token ) {
	he_test_assert( false !== strpos( $integrations, $token ), 'Missing cross-file contract: ' . $token );
}

$privacy_tokens = array( 'wp_privacy_personal_data_exporters', 'wp_privacy_personal_data_erasers', 'bookmarks', 'dataset_access', 'noindex', 'nocache_headers', 'explicit allowlists' );
foreach ( $privacy_tokens as $token ) {
	he_test_assert( false !== strpos( $privacy . $domain . $public, $token ), 'Missing privacy invariant: ' . $token );
}

he_test_assert( false !== strpos( $css, '--sabri-primary:#16823b' ), 'Green primary token is missing.' );
he_test_assert( false !== strpos( $css, ':focus-visible' ), 'Visible focus style is missing.' );
he_test_assert( false !== strpos( $css, 'prefers-reduced-motion' ), 'Reduced-motion style is missing.' );
he_test_assert( false !== strpos( $css, '[dir="rtl"]' ), 'RTL style is missing.' );
he_test_assert( false !== strpos( $css, 'forced-colors' ), 'Forced-colors support is missing.' );
he_test_assert( false === strpos( strtolower( $css ), '--he-orange' ), 'Deprecated orange identity token remains.' );
he_test_assert( false !== strpos( $js, 'AbortController' ), 'Cancelable query behavior is missing.' );
he_test_assert( false !== strpos( $js, 'aria-busy' ), 'Accessible loading state is missing.' );

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
	fwrite( STDERR, "File 06 v2 invariant failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "File 06 v2 source and architecture invariants passed.\n";

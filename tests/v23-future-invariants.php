<?php
/** Deterministic source invariants for F06-FUT-001..018. */
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$future = file_get_contents( $plugin . '/includes/class-he-v23-future-intelligence.php' );
$bootstrap = file_get_contents( $plugin . '/homeopathy-encyclopedia.php' );
$failures = array();
function f06v23_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) { $failures[] = $message; }
}

f06v23_assert( false !== strpos( $bootstrap, "define( 'HE_VERSION', '2.3.0' )" ), 'Runtime is not 2.3.0.' );
f06v23_assert( false !== strpos( $bootstrap, "define( 'HE_SCHEMA_VERSION', 9 )" ), 'Core schema target is not 9.' );
f06v23_assert( false !== strpos( $bootstrap, "HE_CONTRACT_VERSION', '2.3" ), 'Contract is not 2.3.' );
f06v23_assert( false !== strpos( $bootstrap, 'class-he-v23-future-intelligence.php' ), 'Future intelligence class is not loaded.' );
f06v23_assert( false !== strpos( $bootstrap, 'HE_V23_Future_Intelligence::hooks()' ), 'Future intelligence hooks are not started.' );

for ( $i = 1; $i <= 18; $i++ ) {
	$id = sprintf( 'F06-FUT-%03d', $i );
	f06v23_assert( false !== strpos( $future, $id ), 'Missing future feature id: ' . $id );
}
foreach ( array( 'claims','claim_evidence','provenance','external_sources','watchers','translations','freshness','impact_queue','research_gaps' ) as $table ) {
	f06v23_assert( false !== strpos( $future, "table( '" . $table . "' )" ), 'Missing future table: ' . $table );
}
foreach ( array( '/future/capabilities','/claims','/evidence','/transition','/provenance','/external/resolve','/semantic-duplicates','/graph-explorer','/time-machine','/watch','/translations','/citation-bundle','/future/research-gaps','/future/integrity-command-center','/future/impact/reconcile' ) as $route ) {
	f06v23_assert( false !== strpos( $future, $route ), 'Missing future route token: ' . $route );
}
foreach ( array( 'api.crossref.org/works/', 'eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi', 'clinicaltrials.gov/api/v2/studies/', 'pub.orcid.org/v3.0/', 'api.datacite.org/dois/', 'id.nlm.nih.gov/mesh/' ) as $provider ) {
	f06v23_assert( false !== strpos( $future, $provider ), 'Missing governed external connector: ' . $provider );
}
f06v23_assert( false !== strpos( $future, "array( 'supports','contradicts','uncertain','historical' )" ) || false !== strpos( $future, "array( 'supports', 'contradicts', 'uncertain', 'historical' )" ), 'Claim evidence relations are incomplete.' );
f06v23_assert( false !== strpos( $future, 'he_claim_evidence_required' ), 'Claim publication does not fail closed without evidence.' );
f06v23_assert( false !== strpos( $future, 'http://www.w3.org/ns/prov#' ), 'W3C PROV-compatible JSON-LD export is missing.' );
f06v23_assert( false !== strpos( $future, 'update-to' ) && false !== strpos( $future, 'updated-by' ) && false !== strpos( $future, "'retracted'" ), 'Retraction/correction monitoring is incomplete.' );
f06v23_assert( false !== strpos( $future, 'similar_text' ) && false !== strpos( $future, "'auto_merge'=>false" ), 'Semantic duplicate intelligence must remain advisory.' );
f06v23_assert( false !== strpos( $future, 'sabri_file06_impact_delivery_ack' ), 'Cross-platform impact delivery lacks explicit consumer acknowledgement.' );
f06v23_assert( false !== strpos( $future, "'transport_owner'=>'file-19'" ) || false !== strpos( $future, "'transport_owner' => 'file-19'" ), 'Watchlist notifications do not preserve File 19 ownership.' );
f06v23_assert( false !== strpos( $future, "'presentation_owner' => 'file-25'" ) || false !== strpos( $future, "'presentation_owner'=>'file-25'" ), 'Knowledge visualization does not preserve File 25 ownership.' );
f06v23_assert( false !== strpos( $future, "'global_search_owner' => 'file-26'" ) || false !== strpos( $future, "'global_search_owner'=>'file-26'" ), 'Global search ownership boundary is missing.' );
f06v23_assert( false !== strpos( $future, 'outdated' ) && false !== strpos( $future, 'source_version' ), 'Translation freshness/version binding is missing.' );
f06v23_assert( false !== strpos( $future, 'urgent-review' ) && false !== strpos( $future, 'priority_score' ), 'Living freshness/research-priority engine is missing.' );
f06v23_assert( false !== strpos( $future, "'RIS'" ) && false !== strpos( $future, "'BibTeX'" ) && false !== strpos( $future, "'Citeproc JSON'" ) && false !== strpos( $future, 'https://schema.org' ), 'Citation laboratory export formats are incomplete.' );
f06v23_assert( false !== strpos( $future, 'claims_without_evidence' ) && false !== strpos( $future, 'impact_dead_letter' ) && false !== strpos( $future, 'translation_outdated' ), 'Integrity command center is incomplete.' );
f06v23_assert( false !== strpos( $future, 'Idempotency-Key' ) && false !== strpos( $future, 'X-WP-Nonce' ), 'Future mutations lack security/idempotency guards.' );
f06v23_assert( false !== strpos( $future, "'external_auto_publish'=>false" ) || false !== strpos( $future, "'external_content_auto_publish' => false" ), 'External evidence must never auto-publish.' );
f06v23_assert( false === strpos( $future, 'wp_remote_get( $_' ), 'Unsafe unvalidated outbound request found.' );

if ( $failures ) {
	fwrite( STDERR, "File 06 v2.3 future-intelligence invariant failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "File 06 F06-FUT-001..018 source invariants passed.\n";

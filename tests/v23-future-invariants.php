<?php
$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/homeopathy-encyclopedia/homeopathy-encyclopedia.php' );
$future = file_get_contents( $root . '/homeopathy-encyclopedia/includes/class-he-v23-future-intelligence.php' );
$fail = array();
function he23_assert( $ok, $message ) { global $fail; if ( ! $ok ) { $fail[] = $message; } }
he23_assert( false !== strpos( $bootstrap, "define( 'HE_VERSION', '2.3.0' )" ), 'Runtime is not 2.3.0.' );
he23_assert( false !== strpos( $bootstrap, "define( 'HE_SCHEMA_VERSION', 9 )" ), 'Schema is not 9.' );
he23_assert( false !== strpos( $bootstrap, "HE_CONTRACT_VERSION', '2.3" ), 'Contract is not 2.3.' );
he23_assert( false !== strpos( $bootstrap, 'class-he-v23-future-intelligence.php' ), 'Future Intelligence bootstrap missing.' );
$requirements = array(
'F06-FUT-001'=>'claims','F06-FUT-002'=>'provenance','F06-FUT-003'=>'crossref','F06-FUT-004'=>'pubmed','F06-FUT-005'=>'clinicaltrials','F06-FUT-006'=>'orcid','F06-FUT-007'=>'datacite','F06-FUT-008'=>'mesh','F06-FUT-009'=>'semantic_duplicates','F06-FUT-010'=>'graph','F06-FUT-011'=>'time_machine','F06-FUT-012'=>'impact_queue','F06-FUT-013'=>'freshness_for','F06-FUT-014'=>'evidence_gaps','F06-FUT-015'=>'citations','F06-FUT-016'=>'KnowledgeWatchTriggered.v1','F06-FUT-017'=>'translation-outdated','F06-FUT-018'=>'command_center'
);
foreach ( $requirements as $id => $token ) { he23_assert( false !== strpos( $future, $token ), $id . ' implementation token missing: ' . $token ); }
foreach ( array( 'claims','provenance','external_links','translations','watches','impact_queue','evidence_gaps' ) as $table ) { he23_assert( false !== strpos( $future, "table( '" . $table . "' )" ) || false !== strpos( $future, "table('" . $table . "')" ), 'Future table missing: ' . $table ); }
he23_assert( false !== strpos( $future, "'file-19'" ) && false !== strpos( $future, 'delivery_owner' ), 'File 19 notification ownership boundary missing.' );
he23_assert( false !== strpos( $future, "'presentation_owner'=>'file-25'" ), 'File 25 graph presentation boundary missing.' );
he23_assert( false !== strpos( $future, "'security_owner'=>'file-24'" ), 'File 24 security ownership boundary missing.' );
he23_assert( false !== strpos( $future, "'file-05','file-12','file-15','file-16','file-21','file-26'" ), 'Consumer impact set is incomplete.' );
he23_assert( false !== strpos( $future, "'auto_merge'=>false" ) || false !== strpos( $bootstrap, "'auto_merge'=>false" ), 'Semantic duplicate auto-merge must remain disabled.' );
he23_assert( false !== strpos( $bootstrap, "'auto_publish_external'=>false" ), 'External evidence must never auto-publish.' );
he23_assert( false === strpos( $future, 'api.crossref.org' ) && false === strpos( $future, 'eutils.ncbi.nlm.nih.gov' ), 'Provider secrets/network endpoints must remain adapter-owned, not hardcoded.' );
he23_assert( false !== strpos( $future, "apply_filters('he_future_external_lookup'" ), 'Versioned external lookup adapter hook missing.' );
he23_assert( false !== strpos( $future, "HE_V2_Auth::rest_permission" ), 'Protected future actions are not fail-closed through File 00 authority.' );
he23_assert( false !== strpos( $future, "'autonomous_high_risk_actions'=>false" ), 'Command center must not perform autonomous high-risk actions.' );
if ( $fail ) { fwrite( STDERR, implode( "\n", $fail ) . "\n" ); exit( 1 ); }
echo "File 06 v2.3 Future-18 invariants passed.\n";

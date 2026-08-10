<?php
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$f = file_get_contents( $plugin . '/includes/class-he-v23-future.php' );
$b = file_get_contents( $plugin . '/homeopathy-encyclopedia.php' );
$fail = array();
function fut_assert( $ok, $msg ) { global $fail; if ( ! $ok ) { $fail[] = $msg; } }

fut_assert( false !== strpos( $b, "define( 'HE_VERSION', '2.3.0' )" ), 'runtime version' );
fut_assert( false !== strpos( $b, "define( 'HE_SCHEMA_VERSION', 9 )" ), 'schema version' );
fut_assert( false !== strpos( $b, "HE_CONTRACT_VERSION', '2.3" ), 'contract version' );
fut_assert( false !== strpos( $b, 'class-he-v23-future.php' ), 'bootstrap future layer' );
fut_assert( false !== strpos( $b, "'future_requirement_count' => 18" ), '18 requirement count' );

$requirements = array(
 'F06-FUT-001'=>'claims','F06-FUT-002'=>'provenance','F06-FUT-003'=>'retraction-watch','F06-FUT-004'=>'pubmed',
 'F06-FUT-005'=>'clinicaltrials','F06-FUT-006'=>'orcid','F06-FUT-007'=>'datacite','F06-FUT-008'=>'mesh',
 'F06-FUT-009'=>'duplicates/scan','F06-FUT-010'=>'future/graph','F06-FUT-011'=>'time-machine',
 'F06-FUT-012'=>'impact_queue','F06-FUT-013'=>'freshness','F06-FUT-014'=>'research_gaps',
 'F06-FUT-015'=>'citations','F06-FUT-016'=>'watchlists','F06-FUT-017'=>'translations','F06-FUT-018'=>'command-center'
);
foreach ( $requirements as $id => $token ) {
 fut_assert( false !== strpos( $f, $token ), $id . ' missing token ' . $token );
}
foreach ( array('crossref','pubmed','clinicaltrials','orcid','datacite','mesh') as $provider ) {
 fut_assert( false !== strpos( $f, "'{$provider}'" ), 'provider missing ' . $provider );
}
foreach ( array('claims','claim_evidence','provenance','external_records','concept_mappings','similarity','freshness','impact_queue','research_gaps','watchlists','translations') as $table ) {
 fut_assert( false !== strpos( $f, "table( '{$table}' )" ) || false !== strpos( $f, "table('{$table}')" ), 'future table missing ' . $table );
}
fut_assert( false !== strpos( $f, "delivery_owner'=>'file-19'" ), 'File 19 delivery ownership' );
fut_assert( false !== strpos( $f, "visual_owner'=>'file-25'" ), 'File 25 graph visual ownership' );
fut_assert( false !== strpos( $f, "assurance_owner'=>'file-24'" ), 'File 24 assurance ownership' );
fut_assert( false !== strpos( $f, "'file-26'" ), 'File 26 consumer boundary' );
fut_assert( false !== strpos( $f, "'review_required'=>true" ) || false !== strpos( $f, "'review_required' => true" ), 'external metadata human review gate' );
fut_assert( false !== strpos( $f, "status'=>'draft'" ), 'translation human-review draft gate' );
fut_assert( false !== strpos( $f, "state'=>'candidate'" ), 'duplicate candidates are advisory' );

if ( $fail ) {
 fwrite( STDERR, "File 06 v2.3 future invariants FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
 exit( 1 );
}
echo "File 06 v2.3 F06-FUT-001..018 invariants passed.\n";

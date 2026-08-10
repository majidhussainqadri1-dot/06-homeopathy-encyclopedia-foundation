<?php
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$f = file_get_contents( $plugin . '/includes/class-he-v23-future.php' );
$h = file_get_contents( $plugin . '/includes/class-he-v24-audit80-hardening.php' );
$g = file_get_contents( $plugin . '/includes/class-he-v24-final-guard.php' );
$b = file_get_contents( $plugin . '/homeopathy-encyclopedia.php' );
$fail = array();
function fut_assert( $ok, $msg ) { global $fail; if ( ! $ok ) { $fail[] = $msg; } }

fut_assert( false !== strpos( $b, "define( 'HE_VERSION', '2.4.0' )" ), 'runtime version' );
fut_assert( false !== strpos( $b, "define( 'HE_SCHEMA_VERSION', 10 )" ), 'schema version' );
fut_assert( false !== strpos( $b, "HE_CONTRACT_VERSION', '2.4" ), 'contract version' );
fut_assert( false !== strpos( $b, 'class-he-v23-future.php' ), 'bootstrap future layer' );
fut_assert( false !== strpos( $b, 'class-he-v24-audit80-hardening.php' ), 'bootstrap audit hardening layer' );
fut_assert( false !== strpos( $b, 'class-he-v24-final-guard.php' ), 'bootstrap final guard layer' );
fut_assert( false !== strpos( $b, "'future_requirement_count' => 18" ), '18 requirement count' );
fut_assert( false !== strpos( $b, "'audit_review_rounds' => 80" ), '80 review count' );

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
fut_assert( false !== strpos( $f, "'review_required'=>true" ) || false !== strpos( $f, "'review_required'=>1" ), 'external metadata human review gate' );
fut_assert( false !== strpos( $f, "status'=>'draft'" ), 'translation draft gate' );
fut_assert( false !== strpos( $f, "state'=>'candidate'" ), 'duplicate candidates advisory' );

// Audit-80 corrections must remain present.
fut_assert( false !== strpos( $h, 'source_concept_id' ) && false !== strpos( $h, 'target_concept_id' ), 'graph schema correction' );
fut_assert( false !== strpos( $h, 'version_number,status,title,summary,content_hash' ), 'time-machine schema correction' );
fut_assert( false !== strpos( $h, "object_type='concept' AND object_id=%d" ), 'freshness review schema correction' );
fut_assert( false !== strpos( $h, 'he_future_evidence_source_required' ), 'source-free claim evidence rejection' );
fut_assert( false !== strpos( $h, 'limit_response_size' ), 'provider response bound' );
fut_assert( false !== strpos( $h, 'CURSOR_OPTION' ), 'rotating corpus cursor' );
fut_assert( false !== strpos( $h, 'he_future_translation_source_changed' ), 'translation source revalidation' );
fut_assert( false !== strpos( $g, 'fully_public_concept' ), 'public projection guard' );
fut_assert( false !== strpos( $g, 'Idempotency-Key' ), 'Future-18 idempotency gate' );
fut_assert( false !== strpos( $g, 'unreviewed_claims' ) && false !== strpos( $g, 'dead_letter_impacts' ), 'command center completeness' );

if ( $fail ) {
 fwrite( STDERR, "File 06 v2.4 future invariants FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
 exit( 1 );
}
echo "File 06 v2.4 F06-FUT-001..018 + Audit-80 invariants passed.\n";

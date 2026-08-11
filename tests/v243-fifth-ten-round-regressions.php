<?php
/** File 06 v2.4.3 fifth fresh ten-round regression assertions. */
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
$has( $bootstrap, "require_once HE_DIR . 'includes/class-he-v243-fifth-audit.php';", 'fifth audit source is bootstrapped' );
$has( $bootstrap, 'HE_V243_Fifth_Audit::hooks();', 'fifth audit hooks execute' );

$first = $read( 'homeopathy-encyclopedia/includes/class-he-v22-admin-first-save.php' );
$has( $first, "'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'save_research_meta' ), 40, 2", 'first-save replay runs after domain materialization and before completeness saver' );
$has( $first, "\$_POST['he_v2_research_nonce']", 'first-save uses canonical research nonce' );
$has( $first, "'he_v2_save_research'", 'first-save uses canonical nonce action' );
$has( $first, "1 !== (int) \$row['row_version']", 'first-save replay is restricted to newly materialized row' );
$has( $first, "array( 'id' => (int) \$row['id'], 'row_version' => 1 )", 'first-save write is compare-and-update bound' );
$not( $first, 'he_v2_research_meta_nonce', 'legacy nonexistent first-save nonce removed' );

$fifth = $read( 'homeopathy-encyclopedia/includes/class-he-v243-fifth-audit.php' );
$has( $fifth, "array( 'proposal','protocol','publication','successful-case','dataset' )", 'research type allow-list exists' );
$has( $fifth, "array( 'public','restricted','highly-restricted' )", 'research data-class allow-list exists' );
$has( $fifth, "'dataset' === \$type && 'public' === \$data_class", 'dataset private-by-default creation/admin gate exists' );
$has( $fifth, "wp_remove_object_terms( \$post_id, array( 'کامیاب کیس' )", 'stale successful-case topic is removed after type change' );
$has( $fifth, "'published' !== \$row['status'] || 'publish' !== get_post_status", 'dataset access hides unpublished domain/post targets' );
$has( $fifth, "DELETE FROM {\$table} WHERE actor_id=%d LIMIT 250", 'idempotency erasure is bounded' );
$has( $fifth, "SELECT COUNT(*) FROM {\$table} WHERE actor_id=%d", 'idempotency erasure verifies remaining rows' );
$has( $fifth, "'done' => 0 === \$remaining", 'idempotency erasure remains resumable until empty' );
$has( $fifth, "WHERE concept_id=%d AND version_id=%d", 'public search evidence grade binds to current version references' );
$has( $fifth, "\$connectors['file-06']['rebuild'] = array( __CLASS__, 'secure_rebuild' )", 'File26 rebuild uses corrected evidence reconciliation' );

$runtime = $read( 'homeopathy-encyclopedia/includes/class-he-v241-runtime-guard.php' );
$has( $runtime, 'option_value=%s', 'core stale lease takeover is compare-and-delete' );
$has( $runtime, 'maybe_serialize( $existing )', 'core stale lease compares exact serialized owner' );

$schedule = $read( 'homeopathy-encyclopedia/includes/class-he-v22-schedule.php' );
$has( $schedule, "'validation-failed-before-publication'", 'validation failure has explicit schedule invalidation reason' );
$needle = "if ( 1 === (int) \$updated ) {\n\t\t\t\t\tself::clear_schedule_meta( (int) \$row['post_id'] );";
$has( $schedule, $needle, 'schedule metadata clears only after successful CAS update' );

$governance = $read( 'homeopathy-encyclopedia/includes/class-he-v22-governance.php' );
$has( $governance, "foreach ( array( 'baseline', 'intervention', 'follow_up', 'adverse_events', 'limitations' ) as \$field )", 'inherited successful-case REST validation remains complete' );
$has( $governance, "\$connectors['file-06']['rebuild'] = array( __CLASS__, 'reindex_batch' )", 'inherited bounded rebuild exists before fifth-audit override' );

echo "PASS File 06 v2.4.3 fifth fresh ten-round regression invariants\n";

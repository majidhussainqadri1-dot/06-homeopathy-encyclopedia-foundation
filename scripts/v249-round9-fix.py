from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
start=s.index("\tpublic static function rest_apply_research_integrity( WP_REST_Request $request ) {")
end=s.index("\n\tpublic static function rest_reindex_batch", start)
old=s[start:end]
new="""\tpublic static function rest_apply_research_integrity( WP_REST_Request $request ) {
\t\t$reservation = self::mutation_guard( $request, 'research-integrity-apply-' . absint( $request['id'] ) );
\t\tif ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
\t\t\treturn self::mutation_finish( $reservation, null, 200 );
\t\t}
\t\tglobal $wpdb;
\t\t$data = (array) $request->get_json_params();
\t\t$expected = absint( $data['expected_version'] ?? 0 );
\t\t$actions = HE_V2_Schema::table( 'integrity_actions' );
\t\t$research_table = HE_V2_Schema::table( 'research' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_integrity_transaction_start_failed', 'File 06 could not start the research-integrity apply transaction.' );
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_integrity_apply_failed', __( 'The research integrity action could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ), 200 );
\t\t}
\t\ttry {
\t\t\t$action = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$actions} WHERE id=%d AND object_type='research' FOR UPDATE\", absint( $request['id'] ) ), ARRAY_A );
\t\t\tif ( ! $action || 'accepted' !== $action['status'] ) { throw new RuntimeException( 'acceptance-required' ); }
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$research_table} WHERE id=%d FOR UPDATE\", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $research ) { throw new RuntimeException( 'research-not-found' ); }
\t\t\tif ( ! $expected || $expected !== (int) $research['row_version'] ) { throw new RuntimeException( 'research-version-conflict' ); }
\t\t\t$to = 'retraction' === $action['action_type'] ? 'retracted' : 'corrected';
\t\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$research_table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $to, $research['id'], $expected ) );
\t\t\t$action_updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$actions} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='accepted'\", get_current_user_id(), (int) $action['id'], (int) $action['row_version'] ) );
\t\t\tif ( 1 !== (int) $updated || 1 !== (int) $action_updated ) { throw new RuntimeException( 'integrity-version-conflict' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'integrity-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t$message = $error->getMessage();
\t\t\tif ( 'research-not-found' === $message ) {
\t\t\t\t$result = new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t\t} elseif ( 'acceptance-required' === $message ) {
\t\t\t\t$result = new WP_Error( 'he_integrity_acceptance_required', __( 'The research integrity action must be accepted before it can be applied.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t\t} elseif ( in_array( $message, array( 'research-version-conflict', 'integrity-version-conflict' ), true ) ) {
\t\t\t\t$result = new WP_Error( 'he_version_conflict', __( 'The research or integrity record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t\t} else {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'research_integrity_atomic_failed', 'File 06 rolled back or could not confirm the research-integrity transaction commit.' );
\t\t\t\t$result = new WP_Error( 'he_integrity_apply_failed', __( 'The research integrity action could not be applied atomically.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\treturn self::mutation_finish( $reservation, $result, 200 );
\t\t}
\t\t$event = 'retracted' === $to ? 'ResearchRecordRetracted.v1' : 'ResearchPublicationCorrected.v1';
\t\tHE_V2_Domain::emit_event( $event, 'research', (int) $research['id'], array( 'reason' => $action['reason'], 'integrity_action' => $action['public_id'] ) );
\t\treturn self::mutation_finish( $reservation, self::research_public_or_private_dto( (int) $research['id'], true ), 200 );
\t}
"""
s=s[:start]+new+s[end:]
p.write_text(s)

t=Path('tests/v249-tenth-ten-round-regressions.php'); ts=t.read_text(); marker='/*__V249_MORE__*/'
block="""$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v249_ok(false!==strpos($gov,'research_integrity_atomic_failed') && false!==strpos($gov,"object_type='research' FOR UPDATE") && false!==strpos($gov,'integrity-commit-failed'),'R9 research-integrity apply ignores transaction start/commit certainty and reads mutable action state before locking');"""
if block not in ts:
    if marker not in ts: raise SystemExit('R9 test marker missing')
    ts=ts.replace(marker,block+'\n'+marker,1)
t.write_text(ts)

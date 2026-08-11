from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-schedule.php')
s=p.read_text()
old="""\t\t\t$version_id = HE_V2_Domain::snapshot_version( (int) $row['id'], 'Scheduled publication', 'published', absint( get_post_meta( (int) $row['post_id'], '_he_schedule_actor', true ) ) );
\t\t\tif ( ! $version_id ) {
\t\t\t\tcontinue;
\t\t\t}
\t\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='published',review_status='approved',safety_status='approved',current_version=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d\", $version_id, (int) $row['id'], (int) $row['row_version'] ) );
\t\t\tif ( 1 !== (int) $updated ) {
\t\t\t\t/* Snapshot is immutable but not current; keeping it is safer than publishing a stale row. */
\t\t\t\tcontinue;
\t\t\t}
\t\t\twp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
\t\t\tself::clear_schedule_meta( (int) $row['post_id'] );
\t\t\tHE_V22_Governance::reindex_concept_secure( (int) $row['id'] );
\t\t\tHE_V2_Domain::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', (int) $row['id'], array( 'version_id' => $version_id, 'scheduled' => true, 'content_hash' => $fingerprint ) );
\t\t\t$published++;
"""
new="""\t\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'scheduled_publish_transaction_start_failed', 'File 06 could not start the scheduled publication transaction.' );
\t\t\t\tcontinue;
\t\t\t}
\t\t\ttry {
\t\t\t\t$locked = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d FOR UPDATE\", (int) $row['id'] ), ARRAY_A );
\t\t\t\tif ( ! $locked || 'scheduled' !== $locked['status'] || (int) $locked['row_version'] !== (int) $row['row_version'] ) {
\t\t\t\t\tthrow new RuntimeException( 'scheduled-row-changed' );
\t\t\t\t}
\t\t\t\t$locked_fingerprint = self::fingerprint( $locked );
\t\t\t\tif ( ! $locked_fingerprint || ! hash_equals( $fingerprint, $locked_fingerprint ) || ! self::approved_for_current_content( $locked, $locked_fingerprint ) ) {
\t\t\t\t\tthrow new RuntimeException( 'scheduled-approval-changed' );
\t\t\t\t}
\t\t\t\t$validation = HE_V2_Domain::validate_for_review( (int) $locked['id'] );
\t\t\t\tif ( is_wp_error( $validation ) ) { throw new RuntimeException( 'scheduled-validation-failed' ); }
\t\t\t\t$version_id = HE_V2_Domain::snapshot_version( (int) $locked['id'], 'Scheduled publication', 'published', absint( get_post_meta( (int) $locked['post_id'], '_he_schedule_actor', true ) ) );
\t\t\t\tif ( ! $version_id ) { throw new RuntimeException( 'scheduled-snapshot-failed' ); }
\t\t\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='published',review_status='approved',safety_status='approved',current_version=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d\", $version_id, (int) $locked['id'], (int) $locked['row_version'] ) );
\t\t\t\tif ( 1 !== (int) $updated ) { throw new RuntimeException( 'scheduled-version-conflict' ); }
\t\t\t\t$post_result = wp_update_post( array( 'ID' => (int) $locked['post_id'], 'post_status' => 'publish' ), true );
\t\t\t\tif ( is_wp_error( $post_result ) || ! $post_result ) { throw new RuntimeException( 'scheduled-wordpress-publish-failed' ); }
\t\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'scheduled-commit-failed' ); }
\t\t\t} catch ( Throwable $error ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'scheduled_publish_atomic_failed', 'File 06 rolled back a scheduled publication because current approval, snapshot, domain state, WordPress publication, or commit could not complete atomically.' );
\t\t\t\tcontinue;
\t\t\t}
\t\t\tself::clear_schedule_meta( (int) $row['post_id'] );
\t\t\tHE_V22_Governance::reindex_concept_secure( (int) $row['id'] );
\t\t\tHE_V2_Domain::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', (int) $row['id'], array( 'version_id' => $version_id, 'scheduled' => true, 'content_hash' => $fingerprint ) );
\t\t\t$published++;
"""
if old not in s: raise SystemExit('R7 scheduled publication marker missing')
p.write_text(s.replace(old,new,1))

t=Path('tests/v249-tenth-ten-round-regressions.php'); ts=t.read_text(); marker='/*__V249_MORE__*/'
block="""$schedule=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-schedule.php');
v249_ok(false!==strpos($schedule,'scheduled_publish_atomic_failed') && false!==strpos($schedule,'scheduled-wordpress-publish-failed') && false!==strpos($schedule,'FOR UPDATE'),'R7 scheduled publication can leave published domain state or orphan snapshots when WordPress/CAS publication fails');"""
if block not in ts:
    if marker not in ts: raise SystemExit('R7 test marker missing')
    ts=ts.replace(marker,block+'\n'+marker,1)
t.write_text(ts)

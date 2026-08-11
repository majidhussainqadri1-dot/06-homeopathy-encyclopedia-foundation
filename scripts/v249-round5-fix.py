from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old="""\t\t$table = HE_V2_Schema::table( 'concepts' );
\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $to_state, $row['id'], absint( $expected_version ) ) );
\t\tif ( 1 !== (int) $result ) {
\t\t\treturn new WP_Error( 'he_version_conflict', __( 'The entry changed in another session. Reload and try again.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\tif ( 'scheduled' === $to_state ) {
\t\t\tupdate_post_meta( (int) $row['post_id'], '_he_scheduled_at', $scheduled_at );
\t\t\tself::emit_event( 'EncyclopediaEntryScheduled.v1', 'concept', $row['id'], array( 'effective_at' => $scheduled_at ) );
\t\t} elseif ( in_array( $to_state, array( 'draft', 'published', 'archived' ), true ) ) {
\t\t\tdelete_post_meta( (int) $row['post_id'], '_he_scheduled_at' );
\t\t}
\t\tif ( 'published' === $to_state ) {
\t\t\t$version_id = self::snapshot_version( $row['id'], $note ?: 'Published version', 'published', $actor_id );
\t\t\t$wpdb->update( $table, array( 'current_version' => $version_id, 'review_status' => 'approved', 'safety_status' => 'approved' ), array( 'id' => $row['id'] ), array( '%d','%s','%s' ), array( '%d' ) );
\t\t\twp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
\t\t\tself::reindex_concept( $row['id'] );
\t\t\tself::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', $row['id'], array( 'version_id' => $version_id ) );
\t\t}
\t\treturn self::concept_by_id( $row['id'], true );
"""
new="""\t\t$table = HE_V2_Schema::table( 'concepts' );
\t\tif ( 'published' === $to_state ) {
\t\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_publish_transaction_start_failed', 'File 06 could not start the entry publish transaction.' );
\t\t\t\treturn new WP_Error( 'he_publish_failed', __( 'The entry could not enter the publish transaction safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\ttry {
\t\t\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='published',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $row['id'], absint( $expected_version ) ) );
\t\t\t\tif ( 1 !== (int) $result ) { throw new RuntimeException( 'version-conflict' ); }
\t\t\t\t$version_id = self::snapshot_version( $row['id'], $note ?: 'Published version', 'published', $actor_id );
\t\t\t\tif ( ! $version_id ) { throw new RuntimeException( 'snapshot-failed' ); }
\t\t\t\t$finalized = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET current_version=%d,review_status='approved',safety_status='approved',updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $version_id, $row['id'], absint( $expected_version ) + 1 ) );
\t\t\t\tif ( 1 !== (int) $finalized ) { throw new RuntimeException( 'publish-finalize-conflict' ); }
\t\t\t\t$post_result = wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ), true );
\t\t\t\tif ( is_wp_error( $post_result ) || ! $post_result ) { throw new RuntimeException( 'wordpress-publish-failed' ); }
\t\t\t\tdelete_post_meta( (int) $row['post_id'], '_he_scheduled_at' );
\t\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'commit-failed' ); }
\t\t\t} catch ( Throwable $error ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\t$code = 'version-conflict' === $error->getMessage() ? 'he_version_conflict' : 'he_publish_failed';
\t\t\t\t$status = 'version-conflict' === $error->getMessage() ? 409 : 503;
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_publish_atomic_failed', 'File 06 rolled back an entry publish transition because its state, snapshot, WordPress publication, or commit could not complete atomically.' );
\t\t\t\treturn new WP_Error( $code, __( 'The entry publication could not be completed atomically. No successful publication should be assumed.', 'homeopathy-encyclopedia' ), array( 'status' => $status ) );
\t\t\t}
\t\t\tself::reindex_concept( $row['id'] );
\t\t\tself::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', $row['id'], array( 'version_id' => $version_id ) );
\t\t\treturn self::concept_by_id( $row['id'], true );
\t\t}
\n\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $to_state, $row['id'], absint( $expected_version ) ) );
\t\tif ( 1 !== (int) $result ) {
\t\t\treturn new WP_Error( 'he_version_conflict', __( 'The entry changed in another session. Reload and try again.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\tif ( 'scheduled' === $to_state ) {
\t\t\tupdate_post_meta( (int) $row['post_id'], '_he_scheduled_at', $scheduled_at );
\t\t\tself::emit_event( 'EncyclopediaEntryScheduled.v1', 'concept', $row['id'], array( 'effective_at' => $scheduled_at ) );
\t\t} elseif ( in_array( $to_state, array( 'draft', 'archived' ), true ) ) {
\t\t\tdelete_post_meta( (int) $row['post_id'], '_he_scheduled_at' );
\t\t}
\t\treturn self::concept_by_id( $row['id'], true );
"""
if old not in s: raise SystemExit('R5 transition publish block marker missing')
p.write_text(s.replace(old,new,1))

t=Path('tests/v249-tenth-ten-round-regressions.php'); s=t.read_text(); marker='/*__V249_MORE__*/'
block="""$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v249_ok(false!==strpos($domain,'entry_publish_atomic_failed') && false!==strpos($domain,'wordpress-publish-failed') && false!==strpos($domain,'publish-finalize-conflict'),'R5 entry publish transition can expose published state when snapshot or WordPress publication fails');"""
if block not in s:
    if marker not in s: raise SystemExit('R5 test marker missing')
    s=s.replace(marker,block+'\n'+marker,1)
t.write_text(s)

from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old="""\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $to_state, $row['id'], absint( $expected_version ) ) );
\t\tif ( 1 !== (int) $result ) {
\t\t\treturn new WP_Error( 'he_version_conflict', __( 'The research record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\tif ( 'published' === $to_state && $row['post_id'] ) {
\t\t\twp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
\t\t\tself::emit_event( 'ResearchPublicationPublished.v1', 'research', $row['id'], array( 'record_type' => $row['record_type'] ) );
\t\t}
\t\tif ( 'retracted' === $to_state ) {
\t\t\tself::emit_event( 'ResearchRecordRetracted.v1', 'research', $row['id'], array( 'reason' => sanitize_textarea_field( $note ) ) );
\t\t}
\t\treturn self::research_dto( $row['id'], true );
"""
new="""\t\tif ( 'published' === $to_state ) {
\t\t\tif ( empty( $row['post_id'] ) ) {
\t\t\t\treturn new WP_Error( 'he_research_publish_post_missing', __( 'The research record has no governed WordPress publication object.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t\t}
\t\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'research_publish_transaction_start_failed', 'File 06 could not start the research publication transaction.' );
\t\t\t\treturn new WP_Error( 'he_research_publish_failed', __( 'Research publication could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\ttry {
\t\t\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='published',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $row['id'], absint( $expected_version ) ) );
\t\t\t\tif ( 1 !== (int) $result ) { throw new RuntimeException( 'research-version-conflict' ); }
\t\t\t\t$post_result = wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ), true );
\t\t\t\tif ( is_wp_error( $post_result ) || ! $post_result ) { throw new RuntimeException( 'research-wordpress-publish-failed' ); }
\t\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'research-commit-failed' ); }
\t\t\t} catch ( Throwable $error ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\t$code = 'research-version-conflict' === $error->getMessage() ? 'he_version_conflict' : 'he_research_publish_failed';
\t\t\t\t$status = 'research-version-conflict' === $error->getMessage() ? 409 : 503;
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'research_publish_atomic_failed', 'File 06 rolled back research publication because domain state, WordPress publication, or transaction commit could not complete atomically.' );
\t\t\t\treturn new WP_Error( $code, __( 'Research publication could not be completed atomically.', 'homeopathy-encyclopedia' ), array( 'status' => $status ) );
\t\t\t}
\t\t\tself::emit_event( 'ResearchPublicationPublished.v1', 'research', $row['id'], array( 'record_type' => $row['record_type'] ) );
\t\t\treturn self::research_dto( $row['id'], true );
\t\t}
\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $to_state, $row['id'], absint( $expected_version ) ) );
\t\tif ( 1 !== (int) $result ) {
\t\t\treturn new WP_Error( 'he_version_conflict', __( 'The research record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\tif ( 'retracted' === $to_state ) {
\t\t\tself::emit_event( 'ResearchRecordRetracted.v1', 'research', $row['id'], array( 'reason' => sanitize_textarea_field( $note ) ) );
\t\t}
\t\treturn self::research_dto( $row['id'], true );
"""
if old not in s: raise SystemExit('R8 research publish transition marker missing')
p.write_text(s.replace(old,new,1))

t=Path('tests/v249-tenth-ten-round-regressions.php'); ts=t.read_text(); marker='/*__V249_MORE__*/'
block="""$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v249_ok(false!==strpos($domain,'research_publish_atomic_failed') && false!==strpos($domain,'research-wordpress-publish-failed') && false!==strpos($domain,'he_research_publish_post_missing'),'R8 research publication can commit domain published state when the governed WordPress publication fails or is missing');"""
if block not in ts:
    if marker not in ts: raise SystemExit('R8 test marker missing')
    ts=ts.replace(marker,block+'\n'+marker,1)
t.write_text(ts)

from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
start=s.index("\tprivate static function bind_references_to_snapshot(")
end=s.index("\n\tpublic static function snapshot_version(", start)
old=s[start:end]
new="""\tprivate static function bind_references_to_snapshot( $concept_id, $previous_version_id, $new_version_id, $actor_id ) {
\t\tglobal $wpdb;
\t\t$table = HE_V2_Schema::table( 'references' );
\t\t$relations = HE_V2_Schema::table( 'relations' );
\t\t$concept_id = absint( $concept_id ); $previous_version_id = absint( $previous_version_id ); $new_version_id = absint( $new_version_id );
\t\tif ( ! $concept_id || ! $new_version_id ) { return false; }
\t\t$draft_ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE concept_id=%d AND version_id=0 ORDER BY id ASC", $concept_id ) ) );
\t\t$created_ids = array();
\t\t$relation_rewrites = array();
\t\t$rollback = static function() use ( $wpdb, $table, $relations, $concept_id, $new_version_id, &$draft_ids, &$created_ids, &$relation_rewrites ) {
\t\t\tforeach ( array_reverse( $relation_rewrites ) as $rewrite ) {
\t\t\t\t$wpdb->query( $wpdb->prepare( "UPDATE {$relations} SET source_reference_id=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE source_concept_id=%d AND source_reference_id=%d", $rewrite['old'], $concept_id, $rewrite['new'] ) );
\t\t\t}
\t\t\tif ( $created_ids ) {
\t\t\t\t$ids = implode( ',', array_map( 'absint', $created_ids ) );
\t\t\t\t$wpdb->query( "DELETE FROM {$table} WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t\t}
\t\t\tif ( $draft_ids ) {
\t\t\t\t$ids = implode( ',', array_map( 'absint', $draft_ids ) );
\t\t\t\t$wpdb->query( "UPDATE {$table} SET version_id=0 WHERE id IN ({$ids}) AND concept_id=" . absint( $concept_id ) . " AND version_id=" . absint( $new_version_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t\t}
\t\t};
\t\t$moved = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET version_id=%d WHERE concept_id=%d AND version_id=0", $new_version_id, $concept_id ) );
\t\tif ( false === $moved ) { return false; }
\t\tif ( ! $previous_version_id || $previous_version_id === $new_version_id ) { return true; }
\t\t$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND version_id=%d ORDER BY id ASC", $concept_id, $previous_version_id ), ARRAY_A );
\t\tif ( ! is_array( $rows ) ) { $rollback(); return false; }
\t\tforeach ( $rows as $ref ) {
\t\t\tif ( ! is_array( $ref ) ) { $rollback(); return false; }
\t\t\t$old_reference_id = absint( $ref['id'] ?? 0 );
\t\t\t$new_reference_id = (int) $wpdb->get_var( $wpdb->prepare(
\t\t\t\t"SELECT id FROM {$table} WHERE concept_id=%d AND version_id=%d AND source_type=%s AND title=%s AND edition=%s AND page_locator=%s AND url=%s AND doi=%s LIMIT 1",
\t\t\t\t$concept_id, $new_version_id, $ref['source_type'], $ref['title'], $ref['edition'], $ref['page_locator'], $ref['url'], $ref['doi']
\t\t\t) );
\t\t\tif ( ! $new_reference_id ) {
\t\t\t\tunset( $ref['id'] );
\t\t\t\t$ref['version_id'] = $new_version_id;
\t\t\t\t$ref['created_by'] = absint( $actor_id );
\t\t\t\t$ref['created_at'] = current_time( 'mysql', true );
\t\t\t\tif ( ! $wpdb->insert( $table, $ref ) ) { $rollback(); return false; }
\t\t\t\t$new_reference_id = (int) $wpdb->insert_id;
\t\t\t\t$created_ids[] = $new_reference_id;
\t\t\t}
\t\t\tif ( $old_reference_id && $new_reference_id && $old_reference_id !== $new_reference_id ) {
\t\t\t\t$updated = $wpdb->query( $wpdb->prepare(
\t\t\t\t\t"UPDATE {$relations} SET source_reference_id=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE source_concept_id=%d AND source_reference_id=%d",
\t\t\t\t\t$new_reference_id, $concept_id, $old_reference_id
\t\t\t\t) );
\t\t\t\tif ( false === $updated ) { $rollback(); return false; }
\t\t\t\tif ( $updated ) { $relation_rewrites[] = array( 'old' => $old_reference_id, 'new' => $new_reference_id ); }
\t\t\t}
\t\t}
\t\treturn true;
\t}
"""
s=s[:start]+new+s[end:]
old2="""\t\t$new_version_id = (int) $wpdb->insert_id;
\t\tself::bind_references_to_snapshot( $row['id'], (int) $row['current_version'], $new_version_id, $actor_id );
\t\treturn $new_version_id;
"""
new2="""\t\t$new_version_id = (int) $wpdb->insert_id;
\t\tif ( ! self::bind_references_to_snapshot( $row['id'], (int) $row['current_version'], $new_version_id, $actor_id ) ) {
\t\t\t$wpdb->delete( HE_V2_Schema::table( 'versions' ), array( 'id' => $new_version_id, 'concept_id' => (int) $row['id'] ), array( '%d','%d' ) );
\t\t\tHE_V2_Schema::record_runtime_failure( 'snapshot_reference_binding_failed', 'File 06 discarded a new snapshot because its reference/provenance binding could not be completed safely.' );
\t\t\treturn 0;
\t\t}
\t\treturn $new_version_id;
"""
if old2 not in s: raise SystemExit('R6 snapshot bind call marker missing')
p.write_text(s.replace(old2,new2,1))

t=Path('tests/v249-tenth-ten-round-regressions.php'); ts=t.read_text(); marker='/*__V249_MORE__*/'
block="""$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v249_ok(false!==strpos($domain,'snapshot_reference_binding_failed') && false!==strpos($domain,'$relation_rewrites') && false!==strpos($domain,'$draft_ids'),'R6 snapshot publication can silently accept partial reference/provenance binding failure');"""
if block not in ts:
    if marker not in ts: raise SystemExit('R6 test marker missing')
    ts=ts.replace(marker,block+'\n'+marker,1)
t.write_text(ts)

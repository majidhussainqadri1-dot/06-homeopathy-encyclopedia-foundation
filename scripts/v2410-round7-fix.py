from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old="""\t\t$content_hash = HE_V22_Governance::entry_content_hash( $row );
\t\t$ok = $wpdb->insert( HE_V2_Schema::table( 'reviews' ), array(
\t\t\t'object_type' => 'concept',
\t\t\t'object_id' => $row['id'],
\t\t\t'reviewer_id' => absint( $reviewer_id ),
\t\t\t'scope' => $scope,
\t\t\t'decision' => $decision,
\t\t\t'conflict_declared' => $conflict ? 1 : 0,
\t\t\t'note' => sanitize_textarea_field( $note ),
\t\t\t'content_hash' => $content_hash,
\t\t\t'reviewed_row_version' => (int) $row['row_version'],
\t\t\t'review_subject_author' => $post ? (int) $post->post_author : 0,
\t\t\t'created_at' => current_time( 'mysql', true ),
\t\t), array( '%s','%d','%d','%s','%s','%d','%s','%s','%d','%d','%s' ) );
\t\tif ( $ok ) {
\t\t\tself::emit_event( 'EncyclopediaEntryReviewed.v1', 'concept', $row['id'], array( 'scope' => $scope, 'decision' => $decision ) );
\t\t}
\t\treturn $ok ? (int) $wpdb->insert_id : new WP_Error( 'he_review_write_failed', __( 'Review could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
"""
new="""\t\t$content_hash = HE_V22_Governance::entry_content_hash( $row );
\t\t$reviews = HE_V2_Schema::table( 'reviews' );
\t\t$concepts = HE_V2_Schema::table( 'concepts' );
\t\t$ok = $wpdb->query( $wpdb->prepare(
\t\t\t\"INSERT INTO {$reviews} (object_type,object_id,reviewer_id,scope,decision,conflict_declared,note,content_hash,reviewed_row_version,review_subject_author,created_at) SELECT 'concept',c.id,%d,%s,%s,%d,%s,%s,c.row_version,%d,%s FROM {$concepts} c WHERE c.id=%d AND c.row_version=%d\",
\t\t\tabsint( $reviewer_id ), $scope, $decision, $conflict ? 1 : 0, sanitize_textarea_field( $note ), $content_hash,
\t\t\t$post ? (int) $post->post_author : 0, current_time( 'mysql', true ), (int) $row['id'], $expected_version
\t\t) );
\t\tif ( false === $ok ) {
\t\t\treturn new WP_Error( 'he_review_write_failed', __( 'Review could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t}
\t\tif ( 1 !== (int) $ok ) {
\t\t\treturn new WP_Error( 'he_version_conflict', __( 'The entry changed while the review decision was being stored. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\t$review_id = (int) $wpdb->insert_id;
\t\tself::emit_event( 'EncyclopediaEntryReviewed.v1', 'concept', $row['id'], array( 'scope' => $scope, 'decision' => $decision ) );
\t\treturn $review_id;
"""
if old not in s: raise SystemExit('R7 target not found')
s=s.replace(old,new,1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""$domain=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');\nv2410_ok(false!==strpos($domain,'INSERT INTO {$reviews}') && false!==strpos($domain,'WHERE c.id=%d AND c.row_version=%d') && false!==strpos($domain,'changed while the review decision was being stored'),'R7 entry review decision is not atomically bound to expected row version');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)

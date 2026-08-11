from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
old="""\t\t$hash = self::research_hash( $row );
\t\t$ok = $wpdb->insert( HE_V2_Schema::table( 'reviews' ), array(
\t\t\t'object_type' => 'research',
\t\t\t'object_id' => (int) $row['id'],
\t\t\t'reviewer_id' => $reviewer,
\t\t\t'scope' => sanitize_key( $data['scope'] ?? 'scientific' ),
\t\t\t'decision' => $decision,
\t\t\t'conflict_declared' => $conflict ? 1 : 0,
\t\t\t'note' => sanitize_textarea_field( $data['note'] ?? '' ),
\t\t\t'content_hash' => $hash,
\t\t\t'reviewed_row_version' => (int) $row['row_version'],
\t\t\t'review_subject_author' => $post ? (int) $post->post_author : 0,
\t\t\t'created_at' => current_time( 'mysql', true ),
\t\t) );
\t\tif ( ! $ok ) {
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_review_write_failed', __( 'The review could not be stored.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ), 201 );
\t\t}
"""
new="""\t\t$hash = self::research_hash( $row );
\t\t$reviews = HE_V2_Schema::table( 'reviews' );
\t\t$research = HE_V2_Schema::table( 'research' );
\t\t$ok = $wpdb->query( $wpdb->prepare(
\t\t\t\"INSERT INTO {$reviews} (object_type,object_id,reviewer_id,scope,decision,conflict_declared,note,content_hash,reviewed_row_version,review_subject_author,created_at) SELECT 'research',r.id,%d,%s,%s,%d,%s,%s,r.row_version,%d,%s FROM {$research} r WHERE r.id=%d AND r.row_version=%d\",
\t\t\t$reviewer,
\t\t\tsanitize_key( $data['scope'] ?? 'scientific' ),
\t\t\t$decision,
\t\t\t$conflict ? 1 : 0,
\t\t\tsanitize_textarea_field( $data['note'] ?? '' ),
\t\t\t$hash,
\t\t\t$post ? (int) $post->post_author : 0,
\t\t\tcurrent_time( 'mysql', true ),
\t\t\t(int) $row['id'],
\t\t\t$expected
\t\t) );
\t\tif ( false === $ok ) {
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_review_write_failed', __( 'The review could not be stored.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ), 201 );
\t\t}
\t\tif ( 1 !== (int) $ok ) {
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The research record changed while the review decision was being stored. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 201 );
\t\t}
"""
if old not in s: raise SystemExit('R2 target not found')
s=s.replace(old,new,1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text()
marker="/*__V2410_MORE__*/"
add="""v2410_ok(false!==strpos($v22,'INSERT INTO {$reviews}') && false!==strpos($v22,'WHERE r.id=%d AND r.row_version=%d') && false!==strpos($v22,'changed while the review decision was being stored'),'R2 research review decision is not atomically bound to expected row version');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1)
t.write_text(ts)

from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old="""\t\tif ( in_array( $to_state, array( 'approved', 'scheduled', 'published' ), true ) ) {
\t\t\t$reviews = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . HE_V2_Schema::table( 'reviews' ) . \" WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0\", $row['id'] ) );
\t\t\tif ( $reviews < 1 && ! HE_V2_Auth::is_founder( $actor_id ) ) {
\t\t\t\treturn new WP_Error( 'he_review_required', __( 'An independent approved review is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t\t}
\t\t}
"""
new="""\t\tif ( in_array( $to_state, array( 'approved', 'scheduled', 'published' ), true ) && ! HE_V2_Auth::is_founder( $actor_id ) ) {
\t\t\t$current_hash = HE_V22_Governance::entry_content_hash( $row );
\t\t\t$post = get_post( (int) $row['post_id'] );
\t\t\t$author_id = $post ? (int) $post->post_author : 0;
\t\t\t$review = $wpdb->get_var( $wpdb->prepare(
\t\t\t\t\"SELECT id FROM \" . HE_V2_Schema::table( 'reviews' ) . \" WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0 AND content_hash=%s AND reviewer_id<>%d ORDER BY id DESC LIMIT 1\",
\t\t\t\t$row['id'], $current_hash, $author_id
\t\t\t) );
\t\t\tif ( ! $current_hash || ! $review ) {
\t\t\t\treturn new WP_Error( 'he_fresh_independent_review_required', __( 'A fresh independent approval review bound to the current entry content is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t\t}
\t\t}
"""
if old not in s: raise SystemExit('R9 target not found')
s=s.replace(old,new,1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""v2410_ok(false!==strpos($domain,'$current_hash = HE_V22_Governance::entry_content_hash') && false!==strpos($domain,'AND content_hash=%s AND reviewer_id<>%d') && false!==strpos($domain,'he_fresh_independent_review_required'),'R9 owner transition command accepts stale historical approval reviews when REST preflight is bypassed');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)

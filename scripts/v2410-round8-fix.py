from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-integrity.php')
s=p.read_text()
old="""\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d\", absint( $request['id'] ) ), ARRAY_A );
\t\tif ( ! $row ) {
\t\t\treturn self::finish( $reservation, new WP_Error( 'he_not_found', __( 'The integrity record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
\t\t}
\t\t$data = (array) $request->get_json_params();
"""
new="""\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d\", absint( $request['id'] ) ), ARRAY_A );
\t\tif ( ! $row ) {
\t\t\treturn self::finish( $reservation, new WP_Error( 'he_not_found', __( 'The integrity record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
\t\t}
\t\t$post_id = 0;
\t\tif ( 'concept' === $row['object_type'] ) {
\t\t\t$concept = HE_V2_Domain::concept_by_id( (int) $row['object_id'], true );
\t\t\t$post_id = $concept ? (int) $concept['post_id'] : 0;
\t\t} elseif ( 'research' === $row['object_type'] ) {
\t\t\t$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', (int) $row['object_id'] ) );
\t\t}
\t\tif ( ! $post_id ) {
\t\t\treturn self::finish( $reservation, new WP_Error( 'he_not_found', __( 'The governed integrity subject is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) );
\t\t}
\t\t$object_permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW, $post_id, 'file06-integrity-transition' );
\t\tif ( is_wp_error( $object_permission ) ) {
\t\t\treturn self::finish( $reservation, $object_permission );
\t\t}
\t\t$data = (array) $request->get_json_params();
"""
if old not in s: raise SystemExit('R8 target not found')
s=s.replace(old,new,1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""v2410_ok(false!==strpos($integrity,"'file06-integrity-transition'") && false!==strpos($integrity,'HE_V2_Auth::CAP_REVIEW, $post_id') && false!==strpos($integrity,'The governed integrity subject is not available.'),'R8 integrity state transitions are authorized globally instead of against their governed subject');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)

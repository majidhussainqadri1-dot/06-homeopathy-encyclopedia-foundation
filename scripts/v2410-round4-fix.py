from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-integrity.php')
s=p.read_text()
old="""\t\t\t$concept = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$concepts} WHERE id=%d FOR UPDATE\", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $concept || ! in_array( $concept['status'], array( 'published', 'corrected', 'retracted' ), true ) ) {
\t\t\t\tthrow new RuntimeException( 'concept-unavailable' );
\t\t\t}
"""
new="""\t\t\t$concept = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$concepts} WHERE id=%d FOR UPDATE\", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $concept || ! in_array( $concept['status'], array( 'published', 'corrected', 'retracted' ), true ) ) {
\t\t\t\tthrow new RuntimeException( 'concept-unavailable' );
\t\t\t}
\t\t\t$object_permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $concept['post_id'], 'file06-integrity-apply' );
\t\t\tif ( is_wp_error( $object_permission ) ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\treturn self::finish( $reservation, $object_permission );
\t\t\t}
"""
if old not in s: raise SystemExit('R4 target not found')
s=s.replace(old,new,1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""$integrity=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-integrity.php');\nv2410_ok(false!==strpos($integrity,"HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $concept['post_id'], 'file06-integrity-apply' )") && false!==strpos($integrity,'return self::finish( $reservation, $object_permission )'),'R4 early integrity interceptor bypasses object-bound publish authorization');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)

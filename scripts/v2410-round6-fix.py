from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
old="""\t\t\t$research = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$research_table} WHERE id=%d FOR UPDATE\", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $research ) { throw new RuntimeException( 'research-not-found' ); }
\t\t\tif ( ! $expected || $expected !== (int) $research['row_version'] ) { throw new RuntimeException( 'research-version-conflict' ); }
"""
new="""\t\t\t$research = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$research_table} WHERE id=%d FOR UPDATE\", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $research ) { throw new RuntimeException( 'research-not-found' ); }
\t\t\t$object_permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $research['post_id'], 'file06-research-integrity-apply' );
\t\t\tif ( is_wp_error( $object_permission ) ) {
\t\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t\treturn self::mutation_finish( $reservation, $object_permission, 200 );
\t\t\t}
\t\t\tif ( ! $expected || $expected !== (int) $research['row_version'] ) { throw new RuntimeException( 'research-version-conflict' ); }
"""
if old not in s: raise SystemExit('R6 target not found')
s=s.replace(old,new,1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""v2410_ok(false!==strpos($v22,'$object_permission = HE_V2_Auth::rest_permission') && false!==strpos($v22,"'file06-research-integrity-apply'") && false!==strpos($v22,'return self::mutation_finish( $reservation, $object_permission, 200 )'),'R6 research integrity apply is authorized globally instead of against its research object');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)

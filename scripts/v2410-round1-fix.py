from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
old="""\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id,status,data_class FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\tif ( ! $row || ! in_array( $row['status'], array( 'published', 'corrected', 'retracted' ), true ) ) {
\t\t\tstatus_header( 404 );
\t\t\t$wp_query->set_404();
\t\t\treturn;
\t\t}
"""
new="""\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id,status,data_class,record_type,case_anonymized,case_consent_verified FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\t$public_eligible = $row && 'public' === $row['data_class'] && in_array( $row['status'], array( 'published', 'corrected', 'retracted' ), true );
\t\tif ( $public_eligible && 'successful-case' === $row['record_type'] ) {
\t\t\t$public_eligible = ! empty( $row['case_anonymized'] ) && ! empty( $row['case_consent_verified'] );
\t\t}
\t\tif ( ! $public_eligible ) {
\t\t\tstatus_header( 404 );
\t\t\tnocache_headers();
\t\t\theader( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
\t\t\t$wp_query->set_404();
\t\t\treturn;
\t\t}
"""
if old not in s: raise SystemExit('R1 target not found')
s=s.replace(old,new,1)
s=s.replace("\n\t\tif ( 'public' !== $row['data_class'] ) {\n\t\t\tnocache_headers();\n\t\t}\n", "\n", 1)
p.write_text(s)
t=Path('tests/v2410-eleventh-ten-round-regressions.php')
t.write_text("""<?php\n/** File 06 v2.4.10 eleventh fresh ten-round regression controls. */\n$root=dirname(__DIR__);$fail=array();\nfunction v2410_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}\nfunction v2410_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}\n$v22=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');\nv2410_ok(false!==strpos($v22,'$public_eligible = $row') && false!==strpos($v22,'case_consent_verified') && false!==strpos($v22,'case_anonymized') && false!==strpos($v22,'X-Robots-Tag: noindex, nofollow, noarchive'),'R1 research permanent-ID route can render restricted/unconsented research content');\n/*__V2410_MORE__*/\nif($fail){fwrite(STDERR,\"File 06 v2.4.10 eleventh-review regressions FAILED:\\n- \".implode(\"\\n- \",$fail).\"\\n\");exit(1);}echo \"File 06 v2.4.10 eleventh-review regressions: PASS\\n\";\n""")

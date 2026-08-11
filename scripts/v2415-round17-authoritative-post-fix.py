#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'homeopathy-encyclopedia'; domain=P/'includes/class-he-v2-domain.php'; public=P/'includes/class-he-v2-public.php'
def rd(p): return Path(p).read_text(encoding='utf-8')
def wr(p,s): Path(p).write_text(s,encoding='utf-8')

d=rd(domain)
marker='\tpublic static function concept_by_id( $identifier, $include_private = false ) {'
helper='''\tpublic static function concept_by_post_id( $post_id ) {
\t\tglobal $wpdb;
\t\treturn $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ), ARRAY_A );
\t}

'''
if 'public static function concept_by_post_id( $post_id )' not in d:
    i=d.find(marker)
    if i<0: raise SystemExit('R17 concept lookup insertion marker missing')
    d=d[:i]+helper+d[i:]
wr(domain,d)

s=rd(public)
old="\t\t$row = HE_V2_Domain::concept_by_id( $post->post_name );\n\t\t$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;"
new="\t\t$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;\n\t\t$row = $raw ? HE_V2_Domain::concept_by_id( (int) $raw['id'] ) : null;\n\t\t$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;"
count=s.count(old)
if count<2: raise SystemExit(f'R17 expected entry/head public binding twice, found {count}')
s=s.replace(old,new)
old_redirect="\t\t\t$row = $post ? HE_V2_Domain::concept_by_id( $post->post_name, true ) : null;"
if old_redirect not in s: raise SystemExit('R17 merge redirect binding marker missing')
s=s.replace(old_redirect,"\t\t\t$row = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;",1)
old_robots="\t\t\tif ( ! $post || ! HE_V2_Domain::concept_by_id( $post->post_name ) ) {"
if old_robots not in s: raise SystemExit('R17 robots binding marker missing')
s=s.replace(old_robots,"\t\t\t$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;\n\t\t\t$row = $raw ? HE_V2_Domain::concept_by_id( (int) $raw['id'] ) : null;\n\t\t\tif ( ! $row ) {",1)
if 'HE_V2_Domain::concept_by_id( $post->post_name' in s:
    raise SystemExit('R17 mutable post_name concept binding still remains')
wr(public,s)
print('File 06 v2.4.15 round 17 authoritative post binding correction applied')

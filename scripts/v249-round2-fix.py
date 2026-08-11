from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
old="$payload['references'] = $wpdb->get_results( $wpdb->prepare( 'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,quotation_word_count FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d ORDER BY id ASC', (int) $row['id'] ), ARRAY_A );"
new="$payload['references'] = $wpdb->get_results( $wpdb->prepare( 'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,quotation_word_count FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND (version_id=0 OR version_id=%d) ORDER BY id ASC', (int) $row['id'], (int) $row['current_version'] ), ARRAY_A );"
if old not in s: raise SystemExit('R2 entry reference hash query marker missing')
p.write_text(s.replace(old,new,1))
t=Path('tests/v249-tenth-ten-round-regressions.php')
s=t.read_text(); marker='/*__V249_MORE__*/'
block="$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');\nv249_ok(false!==strpos($gov,'version_id=0 OR version_id=%d'),'R2 entry review hash includes superseded historical references instead of current/draft provenance only');"
if block not in s:
    if marker not in s: raise SystemExit('R2 test marker missing')
    s=s.replace(marker,block+'\n'+marker,1)
t.write_text(s)

from pathlib import Path
import re, sys, json

ROOT=Path('.')

def read(path): return (ROOT/path).read_text()
def write(path,s): (ROOT/path).write_text(s)
def replace_once(path, old, new):
    s=read(path)
    if s.count(old)!=1:
        raise SystemExit(f'{path}: expected one target, found {s.count(old)}')
    write(path,s.replace(old,new,1))
def sub_once(path, pattern, repl):
    s=read(path)
    out,n=re.subn(pattern, lambda m: repl, s, count=1, flags=re.S)
    if n!=1: raise SystemExit(f'{path}: regex target count {n}')
    write(path,out)
def replace_if(path, old, new):
    p=ROOT/path
    if not p.exists(): return
    s=p.read_text()
    if old in s: p.write_text(s.replace(old,new))

def round1():
    path='homeopathy-encyclopedia/includes/class-he-v22-research-guard.php'
    marker="\tpublic static function validate_transition( $response, $handler, $request ) {"
    helper="""\t/** Canonical public-research eligibility shared by every File 06 public surface.\n\t * Dataset payloads remain restricted/highly-restricted while their governed metadata may be public.\n\t */\n\tpublic static function public_surface_eligible( $row ) {\n\t\tif ( ! is_array( $row ) || ! in_array( $row['status'], array( 'published','corrected','retracted' ), true ) ) {\n\t\t\treturn false;\n\t\t}\n\t\t$valid = self::validate_row( $row );\n\t\tif ( is_wp_error( $valid ) ) {\n\t\t\treturn false;\n\t\t}\n\t\tif ( 'dataset' === $row['record_type'] ) {\n\t\t\treturn in_array( $row['data_class'], array( 'restricted','highly-restricted' ), true );\n\t\t}\n\t\treturn 'public' === $row['data_class'];\n\t}\n\n"""+marker
    replace_once(path,marker,helper)
    gov='homeopathy-encyclopedia/includes/class-he-v22-governance.php'
    replace_once(gov,"SELECT post_id,status,data_class,record_type,case_anonymized,case_consent_verified FROM ' . HE_V2_Schema::table( 'research' )","SELECT * FROM ' . HE_V2_Schema::table( 'research' )")
    sub_once(gov,r"\t\t\$public_eligible = \$row && 'public' === \$row\['data_class'\].*?\n\t\tif \( ! \$public_eligible \) \{",
             "\t\t$public_eligible = HE_V22_Research_Guard::public_surface_eligible( $row );\n\t\tif ( ! $public_eligible ) {")

def round2():
    p='homeopathy-encyclopedia/includes/class-he-v22-public-guard.php'
    replace_once(p,"\tprivate static function is_public_row( $row ) {\n\t\treturn is_array( $row ) && in_array( $row['status'], array( 'published', 'corrected', 'retracted' ), true );\n\t}",
                 "\tprivate static function is_public_row( $row ) {\n\t\treturn HE_V22_Research_Guard::public_surface_eligible( $row );\n\t}")
    old="""\t\treturn $where . $wpdb->prepare(\n\t\t\t\" AND ({$wpdb->posts}.post_type<>%s OR EXISTS (SELECT 1 FROM {$research} he_public_research WHERE he_public_research.post_id={$wpdb->posts}.ID AND he_public_research.status IN (%s,%s,%s)))\",\n\t\t\tHE_V2_Domain::RESEARCH_TYPE, 'published', 'corrected', 'retracted'\n\t\t);"""
    new="""\t\treturn $where . $wpdb->prepare(\n\t\t\t\" AND ({$wpdb->posts}.post_type<>%s OR EXISTS (SELECT 1 FROM {$research} he_public_research WHERE he_public_research.post_id={$wpdb->posts}.ID AND he_public_research.status IN (%s,%s,%s) AND ((he_public_research.record_type=%s AND he_public_research.data_class IN (%s,%s)) OR (he_public_research.record_type<>%s AND he_public_research.data_class=%s)) AND (he_public_research.record_type<>%s OR (he_public_research.case_anonymized=1 AND he_public_research.case_consent_verified=1))))\",\n\t\t\tHE_V2_Domain::RESEARCH_TYPE, 'published', 'corrected', 'retracted', 'dataset', 'restricted', 'highly-restricted', 'dataset', 'public', 'successful-case'\n\t\t);"""
    replace_once(p,old,new)

def round3():
    p='homeopathy-encyclopedia/includes/class-he-v242-research-browse.php'
    replace_once(p,"\t\treturn ! is_wp_error( HE_V22_Research_Guard::validate_row( $row ) );",
                 "\t\treturn HE_V22_Research_Guard::public_surface_eligible( $row );")

def round4():
    p='homeopathy-encyclopedia/includes/class-he-v242-watchlist.php'
    replace_once(p,"SELECT public_id,post_id,status FROM ' . HE_V2_Schema::table( 'research' )",
                 "SELECT * FROM ' . HE_V2_Schema::table( 'research' )")
    old="return $row && $post && 'publish' === $post->post_status && in_array( $row['status'], array( 'published','corrected','retracted' ), true ) ? array( 'type' => 'research', 'id' => $row['public_id'], 'label' => get_the_title( $post ) ) : null;"
    new="return $row && $post && 'publish' === $post->post_status && HE_V22_Research_Guard::public_surface_eligible( $row ) ? array( 'type' => 'research', 'id' => $row['public_id'], 'label' => get_the_title( $post ) ) : null;"
    replace_once(p,old,new)

def round5():
    p='homeopathy-encyclopedia/includes/class-he-v2-privacy.php'
    marker="\t\t$remaining = 0;"
    block="""\t\t/* Successful erasure must not leave/recreate the erased user as an event object identifier. */\n\t\t$events_table = HE_V2_Schema::table( 'events' );\n\t\t$event_objects = $wpdb->query( $wpdb->prepare( \"UPDATE {$events_table} SET object_id='0' WHERE object_type='user' AND object_id=%s\", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared\n\t\tif ( false === $event_objects ) {\n\t\t\tHE_V2_Schema::record_runtime_failure( 'privacy_event_object_deidentification_failed', 'File 06 could not de-identify user-bound event object identifiers.' );\n\t\t} elseif ( (int) $event_objects > 0 ) {\n\t\t\t$removed = true; $retained = true;\n\t\t}\n\n"""+marker
    replace_once(p,marker,block)
    needle="\t\t$remaining += count( get_posts( array( 'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), 'post_status' => array( 'draft', 'pending', 'publish', 'private', 'future' ), 'author' => $uid, 'posts_per_page' => 1, 'fields' => 'ids' ) ) );"
    replace_once(p,needle,needle+"\n\t\t$remaining += (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$events_table} WHERE object_type='user' AND object_id=%s\", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared")
    replace_once(p,"HE_V2_Domain::emit_event( 'File06PrivacyErasureCompleted.v1', 'user', $uid, array( 'published_records_retained' => $retained ) );",
                 "HE_V2_Domain::emit_event( 'File06PrivacyErasureCompleted.v1', 'privacy-request', 0, array( 'published_records_retained' => $retained ) );")

def round6():
    p='homeopathy-encyclopedia/includes/class-he-v24-future-privacy.php'
    marker="\t\t$remaining = 0;"
    block="""\t\t/* Future erasure must not re-identify a completed privacy request through the shared event object binding. */\n\t\t$core_events = HE_V2_Schema::table( 'events' );\n\t\t$event_objects = $wpdb->query( $wpdb->prepare( \"UPDATE {$core_events} SET object_id='0' WHERE object_type='user' AND object_id=%s\", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared\n\t\tif ( false === $event_objects ) {\n\t\t\tHE_V2_Schema::record_runtime_failure( 'future_privacy_event_object_deidentification_failed', 'Future privacy erasure could not de-identify user-bound event object identifiers.' );\n\t\t} elseif ( (int) $event_objects > 0 ) { $removed = true; $retained = true; }\n\n"""+marker
    replace_once(p,marker,block)
    marker2="\t\tforeach ( $checks as $check ) {\n\t\t\t$table = HE_V24_Future_Schema::table( $check[0] );\n\t\t\t$remaining += (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$table} WHERE {$check[1]}\", $check[2] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared\n\t\t}"
    replace_once(p,marker2,marker2+"\n\t\t$remaining += (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$core_events} WHERE object_type='user' AND object_id=%s\", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared")
    replace_once(p,"HE_V2_Domain::emit_event( 'File06FuturePrivacyErasureCompleted.v1', 'user', $uid, array( 'deidentified_integrity_records_retained' => $retained ) );",
                 "HE_V2_Domain::emit_event( 'File06FuturePrivacyErasureCompleted.v1', 'privacy-request', 0, array( 'deidentified_integrity_records_retained' => $retained ) );")

def round7():
    p='homeopathy-encyclopedia/includes/class-he-v22-research-guard.php'
    old="""\t\t$wpdb->update( HE_V2_Schema::table( 'research' ), array(\n\t\t\t'investigators_json' => wp_json_encode( $investigators, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\t'conflicts_json' => wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\t'case_json' => wp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\t'metadata_json' => wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\t'row_version' => (int) $row['row_version'] + 1,\n\t\t\t'updated_at' => current_time( 'mysql', true ),\n\t\t), array( 'id' => (int) $row['id'] ), array( '%s','%s','%s','%s','%d','%s' ), array( '%d' ) );"""
    new="""\t\t$table = HE_V2_Schema::table( 'research' );\n\t\t$updated = $wpdb->query( $wpdb->prepare(\n\t\t\t\"UPDATE {$table} SET investigators_json=%s,conflicts_json=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\",\n\t\t\twp_json_encode( $investigators, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\twp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\twp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\twp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),\n\t\t\t(int) $row['id'], (int) $row['row_version']\n\t\t) );\n\t\tif ( 1 !== (int) $updated ) {\n\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );\n\t\t\tHE_V2_Schema::record_runtime_failure( 'research_completeness_concurrency_conflict', 'Research completeness fields were not stored because the row changed concurrently.' );\n\t\t}\n"""
    replace_once(p,old,new)

def round8():
    p='homeopathy-encyclopedia/includes/class-he-v22-research-guard.php'
    replace_once(p,"\t\t\t\t$out[] = array( 'name' => $item );","\t\t\t\t$out[] = $item;")
    a='homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php'
    old="""\tprivate static function investigators( $value ) {\n\t\tif ( is_array( $value ) ) {\n\t\t\t$parts = $value;\n\t\t} else {\n\t\t\t$parts = preg_split( '/[\\r\\n,;]+/u', (string) $value );\n\t\t}\n\t\treturn array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $parts ) ) ) );\n\t}"""
    new="""\tprivate static function investigators( $value ) {\n\t\t$parts = is_array( $value ) ? $value : preg_split( '/[\\r\\n,;]+/u', (string) $value );\n\t\t$out = array();\n\t\tforeach ( (array) $parts as $item ) {\n\t\t\tif ( is_array( $item ) ) { $item = $item['name'] ?? ''; }\n\t\t\t$item = sanitize_text_field( (string) $item );\n\t\t\tif ( '' !== $item ) { $out[] = $item; }\n\t\t}\n\t\treturn array_values( array_unique( $out ) );\n\t}"""
    replace_once(a,old,new)
    replace_once(a,"\t\t$investigators = $row ? json_decode( (string) $row['investigators_json'], true ) : array();",
                 "\t\t$investigators = self::investigators( $row ? json_decode( (string) $row['investigators_json'], true ) : array() );")

def round9():
    p='homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php'
    old="""\t\tif ( ! $canonical ) {\n\t\t\tHE_V2_Schema::record_runtime_failure( 'invalid_source_language', 'An invalid source-language code was rejected during metadata normalization.' );\n\t\t\treturn;\n\t\t}"""
    new="""\t\tif ( ! $canonical ) {\n\t\t\tglobal $wpdb;\n\t\t\t$current = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT language FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $object_id ) ) );\n\t\t\tself::$normalizing = true;\n\t\t\tif ( HE_V242_Multilingual::canonical_locale( $current ) ) { update_post_meta( $object_id, '_he_language', $current ); }\n\t\t\telse { delete_post_meta( $object_id, '_he_language' ); }\n\t\t\tself::$normalizing = false;\n\t\t\tHE_V2_Schema::record_runtime_failure( 'invalid_source_language', 'An invalid source-language code was rejected and the canonical concept language was restored.' );\n\t\t\treturn;\n\t\t}"""
    replace_once(p,old,new)

def round10():
    p='homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php'
    marker="\t\tglobal $wpdb;\n\t\t$table = HE_V24_Future_Schema::table( 'translations' );"
    repl="""\t\tglobal $wpdb;\n\t\t$public_source_version = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT version_number FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', (int) $concept['current_version'], (int) $concept['id'] ) );\n\t\tif ( ! $public_source_version ) {\n\t\t\treturn new WP_Error( 'he_not_found', __( 'The public source version is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );\n\t\t}\n\t\t$table = HE_V24_Future_Schema::table( 'translations' );"""
    replace_once(p,marker,repl)
    replace_once(p,"'source_version' => (int) $row['source_version'],","'source_version' => $public_source_version,")
    replace_once(p,"'source_version' => (int) $concept['current_version'],","'source_version' => $public_source_version,")

    # Runtime/package/current release truth.
    for path in ['homeopathy-encyclopedia/homeopathy-encyclopedia.php','homeopathy-encyclopedia/readme.txt','tests/v2-invariants.php','tests/v2-source-invariants.sh','V2-MANIFEST.md','docs/CONTRACTS.md','docs/SCHEMA-MANIFEST.md','docs/TEST-REPORT.md']:
        replace_if(path,'2.4.10','2.4.11')
    run='tests/run-all.sh'; s=read(run)
    if 'v2411-twelfth-ten-round-regressions.php' not in s:
        s=s.replace('php "$root/tests/v2410-eleventh-ten-round-regressions.php"','php "$root/tests/v2410-eleventh-ten-round-regressions.php"\nphp "$root/tests/v2411-twelfth-ten-round-regressions.php"')
    s=s.replace('file06-v2.4.10-a.zip','file06-v2.4.11-a.zip').replace('file06-v2.4.10-b.zip','file06-v2.4.11-b.zip')
    s=s.replace('All File 06 v2.4.10 automated checks, inherited review matrices, eleventh ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.11 automated checks, inherited review matrices, twelfth ten-round regressions and deterministic package comparison passed.')
    write(run,s)

    # Preserve historical v2.4.10 behavioral regression while making its release assertion future-compatible.
    hist='tests/v2410-eleventh-ten-round-regressions.php'; h=read(hist)
    h=re.sub(r"v2410_ok\(false!==strpos\(\$bootstrap,' \\* Version: 2\\.4\\.10'.*?R10 runtime/contract/future hardening release truth is not aligned to v2\.4\.10'\);",
             "v2410_ok(false!==strpos($bootstrap,\"define( 'HE_SCHEMA_VERSION', 10 );\") && false!==strpos($bootstrap,\"'future_hardening_version'=>\") && false!==strpos($bootstrap,\"'release_state'=>array('coded_candidate'=>true,'staging_accepted'=>false,'live_deployed'=>false,'operational'=>false)\"),'R10 historical release-state/schema contract regressed');",h,flags=re.S)
    h=h.replace("v2410_ok(false!==strpos($readme,'Stable tag: 2.4.10'),'R10 plugin stable tag is not v2.4.10');","v2410_ok(false!==strpos($readme,'Stable tag: '),'R10 plugin stable-tag contract missing');")
    h=h.replace("v2410_ok(false!==strpos($runall,'v2410-eleventh-ten-round-regressions.php') && false!==strpos($runall,'file06-v2.4.10-a.zip') && false!==strpos($runall,'file06-v2.4.10-b.zip'),'R10 aggregate/package truth is not aligned to v2.4.10');","v2410_ok(false!==strpos($runall,'v2410-eleventh-ten-round-regressions.php'),'R10 historical eleventh regression suite was removed from aggregate QA');")
    write(hist,h)

    write('README.md',"""# File 06 — Homeopathy Encyclopedia 2.4.11\n\nTwelfth fresh ten-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.\n\n## Candidate truth\n- Branch: `audit/file-06-twelfth-ten-round-v2.4.11`\n- Plugin / contract: `2.4.11`\n- Global schema: `10`\n- V24 Future schema: `2`\n- REST namespace: `sabri/v2/file-06`\n- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`\n\nTwelfth-cycle corrections unify public research eligibility (including metadata-only datasets), close restricted-research watch leaks, prevent privacy completion events from re-identifying erased users, CAS-protect research completeness writes, normalize investigator compatibility, restore invalid source-language metadata to canonical truth, and expose semantic rather than internal translation source-version identifiers.\n\nRun `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.\n""")
    write('STATUS.md',"""# File 06 Status — 2.4.11 Twelfth Fresh Ten-Round Candidate\n\n| Status | Evidence |\n|---|---|\n| Specified | File 06 governing plan + applicable later platform governance |\n| Coded | `audit/file-06-twelfth-ten-round-v2.4.11` |\n| Reviewed | 10 sequential review → immediate fix/retest rounds |\n| Defect rounds | `1, 2, 3, 4, 5, 6, 7, 8, 9, 10` |\n| Runtime | `2.4.11 / schema 10 / contract 2.4.11 / Future schema 2` |\n| Automated QA | Authoritative only from completed final exact-head v2.4.11 workflow |\n| Staging accepted | **No / unverified** |\n| Live deployed | **No / unverified** |\n| Operational | **No / unverified** |\n\nRepository, staging and live are separate realities.\n""")
    write('docs/RELEASE-NOTES.md',"""# File 06 — Release Notes 2.4.11\n\nTwelfth fresh ten-round corrective repository candidate. Defects were found and corrected in rounds `1–10`.\n\nPrimary corrections cover nuanced public-research eligibility, cross-surface privacy parity, watchlist eligibility, erasure-event de-identification, CAS-protected research admin governance, investigator-shape compatibility, canonical source-language restoration, public translation identifier hygiene, and current v2.4.11 release/QA truth.\n\nFinal exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.\n""")
    if Path('docs/RELEASE-SIGNOFF.md').exists():
        r=read('docs/RELEASE-SIGNOFF.md').replace('2.4.10','2.4.11').replace('Eleventh','Twelfth').replace('eleventh','twelfth')
        write('docs/RELEASE-SIGNOFF.md',r)
    sbom=Path('SBOM.json')
    if sbom.exists():
        data=json.loads(sbom.read_text())
        data['version']=7
        comp=data.setdefault('metadata',{}).setdefault('component',{})
        comp['version']='2.4.11'; comp['purl']='pkg:wordpress/homeopathy-encyclopedia@2.4.11'
        rel=data.setdefault('release',{})
        rel['file']='06-homeopathy-encyclopedia-foundation-2.4.11.zip'; rel['contract']='2.4.11'; rel['defect_rounds']=list(range(1,11)); rel['clean_rounds']=[]
        rel.pop('ninth_review_rounds',None); rel['twelfth_review_rounds']=10
        sbom.write_text(json.dumps(data,indent=2,ensure_ascii=False)+'\n')
    ch=Path('CHANGELOG.md')
    if ch.exists():
        s=ch.read_text()
        heading='## 2.4.11 — Twelfth fresh ten-round corrected candidate'
        if heading not in s:
            s=s.replace('\n',"\n\n"+heading+"\n- Ten sequential review/fix/retest rounds completed; defects corrected in rounds 1–10.\n- Unified nuanced research public eligibility, privacy erasure object de-identification, research admin CAS, investigator compatibility, language canonicalization and public translation identifier hygiene.\n- Repository candidate only; staging/live/operational evidence remains unverified.\n",1)
            ch.write_text(s)

ROUNDS={1:round1,2:round2,3:round3,4:round4,5:round5,6:round6,7:round7,8:round8,9:round9,10:round10}
if __name__=='__main__':
    n=int(sys.argv[1])
    if n not in ROUNDS: raise SystemExit('round must be 1..10')
    ROUNDS[n]()
    print(f'v2.4.11 twelfth cycle round {n} correction applied')

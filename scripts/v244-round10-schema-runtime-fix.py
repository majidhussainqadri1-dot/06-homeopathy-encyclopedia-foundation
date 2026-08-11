from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path, old, new, label):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    if text.count(old) != 1:
        raise SystemExit(f'{label}: expected one target in {path}, found {text.count(old)}')
    p.write_text(text.replace(old, new, 1), encoding='utf-8')

schema_path = ROOT / 'homeopathy-encyclopedia/includes/class-he-v24-future-schema.php'
text = schema_path.read_text(encoding='utf-8')
text = text.replace("\tconst VERSION = 1;", "\tconst VERSION = 2;", 1)

claims_end = '''\t\t$sql[] = "CREATE TABLE " . self::table( 'claims' ) . " (\n\t\t\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n\t\t\tconcept_id bigint(20) unsigned NOT NULL,\n\t\t\tversion_id bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\t\tpublic_id char(36) NOT NULL,\n\t\t\tclaim_key varchar(120) NOT NULL,\n\t\t\tclaim_text longtext NOT NULL,\n\t\t\tclaim_state varchar(30) NOT NULL DEFAULT 'active',\n\t\t\tevidence_state varchar(30) NOT NULL DEFAULT 'ungraded',\n\t\t\tconfidence decimal(6,5) NOT NULL DEFAULT 0,\n\t\t\treview_status varchar(30) NOT NULL DEFAULT 'pending',\n\t\t\treviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\t\trow_version bigint(20) unsigned NOT NULL DEFAULT 1,\n\t\t\tcreated_by bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\t\tcreated_at datetime NOT NULL,\n\t\t\tupdated_at datetime NOT NULL,\n\t\t\tPRIMARY KEY(id),\n\t\t\tUNIQUE KEY public_id(public_id),\n\t\t\tUNIQUE KEY concept_claim(concept_id,claim_key),\n\t\t\tKEY concept_review(concept_id,review_status),\n\t\t\tKEY version_id(version_id),\n\t\t\tKEY claim_state(claim_state)\n\t\t) {$c};";\n'''
if text.count(claims_end) != 1:
    raise SystemExit('round10 schema claims block target missing')
legacy_final_tables = '''\n\t\t$sql[] = "CREATE TABLE " . self::table( 'claim_evidence' ) . " (\n\t\t\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n\t\t\tclaim_id bigint(20) unsigned NOT NULL,\n\t\t\treference_id bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\t\texternal_id varchar(191) NOT NULL DEFAULT '',\n\t\t\trelation varchar(24) NOT NULL,\n\t\t\tweight decimal(5,2) NOT NULL DEFAULT 0,\n\t\t\tnote text NOT NULL,\n\t\t\tcreated_by bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\t\tcreated_at datetime NOT NULL,\n\t\t\tPRIMARY KEY  (id),\n\t\t\tUNIQUE KEY claim_source (claim_id,reference_id,external_id(96),relation),\n\t\t\tKEY claim_id (claim_id)\n\t\t) {$c};";\n\n\t\t$sql[] = "CREATE TABLE " . self::table( 'concept_mappings' ) . " (\n\t\t\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n\t\t\tconcept_id bigint(20) unsigned NOT NULL,\n\t\t\tvocabulary varchar(30) NOT NULL,\n\t\t\texternal_id varchar(191) NOT NULL,\n\t\t\tpreferred_label text NOT NULL,\n\t\t\tmapping_state varchar(30) NOT NULL DEFAULT 'proposed',\n\t\t\treviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\t\tcreated_at datetime NOT NULL,\n\t\t\tupdated_at datetime NOT NULL,\n\t\t\tPRIMARY KEY  (id),\n\t\t\tUNIQUE KEY concept_vocab (concept_id,vocabulary,external_id(100)),\n\t\t\tKEY vocabulary (vocabulary)\n\t\t) {$c};";\n\n\t\t$sql[] = "CREATE TABLE " . self::table( 'similarity' ) . " (\n\t\t\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n\t\t\tconcept_a bigint(20) unsigned NOT NULL,\n\t\t\tconcept_b bigint(20) unsigned NOT NULL,\n\t\t\tscore decimal(6,5) NOT NULL DEFAULT 0,\n\t\t\treason_json longtext NOT NULL,\n\t\t\tstate varchar(24) NOT NULL DEFAULT 'candidate',\n\t\t\tcreated_at datetime NOT NULL,\n\t\t\tupdated_at datetime NOT NULL,\n\t\t\tPRIMARY KEY  (id),\n\t\t\tUNIQUE KEY pair (concept_a,concept_b),\n\t\t\tKEY score (score),\n\t\t\tKEY state (state)\n\t\t) {$c};";\n'''
text = text.replace(claims_end, claims_end + legacy_final_tables, 1)

# dbDelta requires field/index definitions on their own lines and whitespace before
# index-column parentheses. V24 already has one definition per line; normalize all
# its index definitions to WordPress-compatible syntax.
text = text.replace('PRIMARY KEY(', 'PRIMARY KEY  (')
text = re.sub(r'UNIQUE KEY ([A-Za-z0-9_]+)\(', r'UNIQUE KEY \1 (', text)
text = re.sub(r'(?<!UNIQUE )KEY ([A-Za-z0-9_]+)\(', r'KEY \1 (', text)

old_loop = '''\t\tforeach ( $sql as $statement ) {\n\t\t\tdbDelta( $statement );\n\t\t}\n\t\tself::verify_schema();\n'''
new_loop = '''\t\tforeach ( $sql as $statement ) {\n\t\t\t$wpdb->last_error = '';\n\t\t\tdbDelta( $statement );\n\t\t\tif ( '' !== (string) $wpdb->last_error ) {\n\t\t\t\tthrow new RuntimeException( 'File 06 Future schema dbDelta failed: ' . $wpdb->last_error );\n\t\t\t}\n\t\t}\n\t\tself::verify_schema();\n'''
if text.count(old_loop) != 1:
    raise SystemExit('round10 dbDelta loop target missing')
text = text.replace(old_loop, new_loop, 1)

old_required = "\t\t\t'claims' => array( 'version_id','confidence','review_status','reviewed_by','row_version' ),\n\t\t\t'provenance' =>"
new_required = "\t\t\t'claims' => array( 'version_id','confidence','review_status','reviewed_by','row_version' ),\n\t\t\t'claim_evidence' => array( 'claim_id','reference_id','external_id','relation' ),\n\t\t\t'concept_mappings' => array( 'concept_id','vocabulary','external_id','mapping_state' ),\n\t\t\t'similarity' => array( 'concept_a','concept_b','score','state' ),\n\t\t\t'provenance' =>"
if text.count(old_required) != 1:
    raise SystemExit('round10 schema verification target missing')
text = text.replace(old_required, new_required, 1)
schema_path.write_text(text, encoding='utf-8')

# V24 is the current schema authority. Never re-run the older V23 dbDelta schema
# against V24 tables during activation or startup upgrades.
main_path = ROOT / 'homeopathy-encyclopedia/homeopathy-encyclopedia.php'
main = main_path.read_text(encoding='utf-8')
main = main.replace("\tHE_V23_Future::install();\n\tHE_V24_Migration_Safety::activate();", "\tHE_V24_Migration_Safety::activate();", 1)
main = main.replace("\t\tif ( (int) get_option( HE_V24_Future_Schema::OPTION_VERSION, 0 ) < HE_V24_Future_Schema::VERSION ) { HE_V23_Future::maybe_upgrade(); }\n\t\tHE_V24_Migration_Safety::maybe_upgrade();", "\t\tHE_V24_Migration_Safety::maybe_upgrade();", 1)
main = main.replace("'future_hardening_version'=>'2.4.3'", "'future_hardening_version'=>'2.4.4'", 1)
main_path.write_text(main, encoding='utf-8')

# Dedicated current-cycle regression coverage for the hidden-green schema failure.
reg_path = ROOT / 'tests/v244-ten-round-regressions.php'
reg = reg_path.read_text(encoding='utf-8')
anchor = "// Round 10 — the aggregate gate itself must retain all prior and current regressions.\n"
addition = '''// Round 10 runtime-log audit — V24 owns the final Future schema and old V23 dbDelta is never replayed.\n$future_schema = $read( 'homeopathy-encyclopedia/includes/class-he-v24-future-schema.php' );\n$has( $future_schema, 'const VERSION = 2;', 'round 10 future schema internal upgrade version' );\n$has( $future_schema, "self::table( 'claim_evidence' )", 'round 10 V24 owns claim_evidence final schema' );\n$has( $future_schema, "self::table( 'concept_mappings' )", 'round 10 V24 owns concept_mappings final schema' );\n$has( $future_schema, "self::table( 'similarity' )", 'round 10 V24 owns similarity final schema' );\n$has( $future_schema, 'Future schema dbDelta failed', 'round 10 dbDelta errors fail closed' );\n$not( $bootstrap, "HE_V23_Future::install();", 'round 10 activation never replays obsolete V23 schema' );\n$not( $bootstrap, "HE_V23_Future::maybe_upgrade();", 'round 10 startup never replays obsolete V23 schema' );\n$has( $bootstrap, "'future_hardening_version'=>'2.4.4'", 'round 10 contract hardening version aligned' );\n\n'''
if reg.count(anchor) != 1:
    raise SystemExit('round10 regression anchor missing')
reg = reg.replace(anchor, addition + anchor, 1)
reg_path.write_text(reg, encoding='utf-8')

print('Applied File 06 round-10 hidden dbDelta/runtime schema correction')

#!/usr/bin/env python3
from pathlib import Path
import re

ROOT=Path(__file__).resolve().parents[1]
INC=ROOT/'homeopathy-encyclopedia'/'includes'

def read(p): return Path(p).read_text(encoding='utf-8')
def write(p,s): Path(p).write_text(s,encoding='utf-8')
def sub1(s,pat,repl,label,flags=0):
    out,n=re.subn(pat,repl,s,count=1,flags=flags)
    if n!=1: raise SystemExit(f'{label}: expected one replacement, found {n}')
    return out

# Retire the v2.3 runtime endpoints/cron under the v2.4+ owner while preserving contract catalogs.
p=INC/'class-he-v23-future.php'; s=read(p)
s=sub1(s,r"\tpublic static function hooks\(\) \{.*?\n\t\}","""\tpublic static function hooks() {
\t\t/* v2.4+ owns Future REST and maintenance; retire legacy v2.3 runtime surfaces. */
\t\twp_clear_scheduled_hook( self::CRON );
\t\tadd_filter( 'sabri_platform_contracts', array( __CLASS__, 'extend_contract' ), 120 );
\t\tadd_filter( 'sabri_notification_event_catalog', array( __CLASS__, 'notification_events' ), 120 );
\t\tadd_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance' ), 160 );
\t}""",'v23 hooks',re.S)
s=sub1(s,r"\tpublic static function activate\(\) \{.*?\n\t\}","""\tpublic static function activate() {
\t\tif ( class_exists( 'HE_V24_Migration_Safety' ) ) {
\t\t\twp_clear_scheduled_hook( self::CRON );
\t\t\treturn;
\t\t}
\t\tself::install();
\t\tif ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::CRON ); }
\t}""",'v23 activate',re.S)
s=s.replace("\n\t\tupdate_option( HE_V2_Schema::OPTION_SCHEMA, HE_SCHEMA_VERSION, false );",'',1)
write(p,s)

# Canonical/opaque review guard.
p=INC/'class-he-v24-future-review-guard.php'; s=read(p)
s=s.replace("'/future/external/(?P<id>\\\\d+)/review'","'/future/external/(?P<id>[A-Za-z0-9_-]+\\.[a-f0-9]{64})/review'",1)
s=sub1(s,r"\n\t\tregister_rest_route\( \$ns, '/future/translations/\(\?P<id>\\\\d\+\)/review'.*?\n\t\t\) \);",'', 'remove numeric translation review',re.S)
s=sub1(s,r"\n\t\tregister_rest_route\( \$ns, '/future/translations/\(\?P<id>\\\\d\+\)/publish'.*?\n\t\t\) \);",'', 'remove numeric translation publish',re.S)
s=s.replace("/future/claims/(\\\\d+)/review$#", "/future/claims/([0-9a-fA-F-]{36})/review$#",1)
s=s.replace("return self::claim_approval_gate( absint( $match[1] ), $response );","return self::claim_approval_gate( strtolower( sanitize_text_field( $match[1] ) ), $response );",1)
s=sub1(s,r"\tprivate static function claim_approval_gate\( \$claim_id, \$response \) \{\n\t\tglobal \$wpdb;\n\t\t\$claim = \$wpdb->get_row\( \$wpdb->prepare\( 'SELECT \* FROM ' \. HE_V24_Future_Schema::table\( 'claims' \) \. ' WHERE id=%d', \$claim_id \), ARRAY_A \);", """\tprivate static function claim_approval_gate( $claim_public_id, $response ) {
\t\tglobal $wpdb;
\t\t$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE public_id=%s', $claim_public_id ), ARRAY_A );
\t\t$claim_id = $claim ? (int) $claim['id'] : 0;""", 'claim approval canonical lookup')
start=s.find('\tpublic static function rest_external_review( WP_REST_Request $request ) {')
end=s.find('\tprivate static function translation_row(',start)
if start<0 or end<0: raise SystemExit('external review markers missing')
external="""\tpublic static function rest_external_review( WP_REST_Request $request ) {
\t\t$token = sanitize_text_field( (string) $request['id'] );
\t\t$record_id = HE_V2_Domain::decode_public_cursor( 'external-record', $token );
\t\tif ( null === $record_id || ! $record_id ) { return new WP_Error( 'he_not_found', __( 'External scholarly record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t$reservation = self::guard( $request, 'external-review-' . substr( hash( 'sha256', $token ), 0, 24 ), HE_V2_Auth::CAP_REVIEW );
\t\tif ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null ); }
\t\tglobal $wpdb; $table = HE_V24_Future_Schema::table( 'external_records' );
\t\t$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", $record_id ), ARRAY_A );
\t\tif ( ! $row ) { return self::finish( $reservation, new WP_Error( 'he_not_found', __( 'External scholarly record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
\t\t$decision = sanitize_key( $request->get_param( 'decision' ) );
\t\tif ( ! in_array( $decision, array( 'approved','rejected' ), true ) ) { return self::finish( $reservation, new WP_Error( 'he_future_external_review_invalid', __( 'External scholarly review decision must be approved or rejected.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
\t\t$status = 'approved' === $decision ? 'reviewed' : 'rejected';
\t\t$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,review_required=0,checked_at=checked_at WHERE id=%d AND review_required=1", $status, $row['id'] ) );
\t\tif ( 1 !== (int) $updated ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'This external scholarly record was already reviewed or changed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
\t\t$provenance = HE_V24_Future_Schema::append_provenance( 'external-record', (string) $row['id'], 'metadata.reviewed', '', array( 'decision' => $decision, 'provider' => $row['provider'], 'external_id' => $row['external_id'] ) );
\t\tif ( ! $provenance ) { HE_V2_Schema::record_runtime_failure( 'external_review_provenance_failed', 'External review state was saved but its provenance append failed; further mutations are paused.' ); update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); return self::finish( $reservation, new WP_Error( 'he_future_external_review_failed', __( 'The review state was saved but its audit provenance could not be completed safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) ); }
\t\treturn self::finish( $reservation, array( 'id' => $token, 'status' => $status, 'review_required' => false ), 200 );
\t}

"""
s=s[:start]+external+s[end:]
write(p,s)

# Object-scope guards and match-index correctness.
p=INC/'class-he-v241-governance.php'; s=read(p)
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/(\\d+)/apply$#', $route, $match ) ) {
\t\t\t$concept_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT object_id FROM " . HE_V2_Schema::table( 'integrity_actions' ) . " WHERE id=%d AND object_type='concept'", absint( $match[1] ) ) );"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/([0-9a-fA-F-]{36})/apply$#', $route, $match ) ) {
\t\t\t$concept_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT object_id FROM " . HE_V2_Schema::table( 'integrity_actions' ) . " WHERE public_id=%s AND object_type='concept'", strtolower( sanitize_text_field( $match[1] ) ) ) );"""
if old not in s: raise SystemExit('integrity guard old block missing')
s=s.replace(old,new,1)
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/(\\d+)/transition$#', $route, $match ) ) {
\t\t\t$research = self::research_row( $match[1] );"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/([0-9a-fA-F-]{36})/transition$#', $route, $match ) ) {
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( 'SELECT id,post_id,created_by,status,record_type FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( $match[1] ) ) ), ARRAY_A );"""
if old not in s: raise SystemExit('research guard old block missing')
s=s.replace(old,new,1)
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/(\\d+)/approve$#', $route, $match ) ) {
\t\t\t$research_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT research_id FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE id=%d', absint( $match[1] ) ) );"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/([A-Za-z0-9_-]+\\.[a-f0-9]{64})/approve$#', $route, $match ) ) {
\t\t\t$access_id = HE_V2_Domain::decode_public_cursor( 'dataset-access', $match[1] );
\t\t\t$research_id = $access_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT research_id FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE id=%d', $access_id ) ) : 0;"""
if old not in s: raise SystemExit('dataset guard old block missing')
s=s.replace(old,new,1)
s=s.replace("if ( 'review' === $match[3] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id ) ) {","if ( 'review' === $match[2] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id ) ) {",1)
s=s.replace("if ( 'review' === $match[2] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id, 'language' ) ) {","if ( 'review' === $match[3] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id, 'language' ) ) {",1)
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/(\\d+)/review$#', $route, $match ) ) {
\t\t\t$concept = self::concept_for_external_record( $match[1] );"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/([A-Za-z0-9_-]+\\.[a-f0-9]{64})/review$#', $route, $match ) ) {
\t\t\t$record_id = HE_V2_Domain::decode_public_cursor( 'external-record', $match[1] );
\t\t\t$concept = $record_id ? self::concept_for_external_record( $record_id ) : null;"""
if old not in s: raise SystemExit('external guard old block missing')
s=s.replace(old,new,1)
write(p,s)

# Successful object-scope results normalize back to callbacks for current canonical routes.
p=INC/'class-he-v241-before-callback-normalizer.php'; s=read(p)
s=s.replace("'/integrity/\\d+/apply$#'","'/integrity/[0-9a-fA-F-]{36}/apply$#'",1)
s=s.replace("'/research/\\d+/transition$#'","'/research/[0-9a-fA-F-]{36}/transition$#'",1)
s=s.replace("'/dataset-access/\\d+/approve$#'","'/dataset-access/[A-Za-z0-9_-]+\\.[a-f0-9]{64}/approve$#'",1)
s=s.replace("'/future/external/\\d+/review$#'","'/future/external/[A-Za-z0-9_-]+\\.[a-f0-9]{64}/review$#'",1)
write(p,s)

# R20 ledger correction.
p=ROOT/'docs'/'FILE-06-v2.4.17-EIGHTEENTH-TWENTY-ROUND-REVIEW.md'; s=read(p)
s=sub1(s,r"^20\. \*\*CLEAN\*\* — .*?$","20. **DEFECT** — Final exact-head WordPress route smoke exposed residual v2.3 numeric mutation routes plus stale v2.4.1/review-guard numeric object-scope patterns that could bypass canonical-ID reviewer/object gates. Legacy v2.3 REST/maintenance runtime is retired under v2.4+, external review now uses an opaque token, core integrity/research/dataset/external guards use current canonical IDs/tokens, and claim/translation reviewer-match indices are corrected; exact-head final QA must pass after this correction.",'ledger R20',re.M)
write(p,s)

# Strengthen current-cycle regression gate.
p=ROOT/'tests'/'v2417-eighteenth-twenty-round-regressions.php'; s=read(p)
s=s.replace("$runtime=r17($inc.'/class-he-v242-runtime-corrections.php');","$runtime=r17($inc.'/class-he-v242-runtime-corrections.php');$v23=r17($inc.'/class-he-v23-future.php');$reviewguard=r17($inc.'/class-he-v24-future-review-guard.php');",1)
s=s.replace("if($round>=20){ok17(has17($ledger,'20. **CLEAN**'),'R20 final clean audit record missing');}","if($round>=20){ok17(has17($ledger,'20. **DEFECT**')&&has17($v23,'retire legacy v2.3 runtime surfaces')&&!has17($v23,\"add_action( 'rest_api_init'\")&&!has17($v23,\"add_action( self::CRON\")&&has17($reviewguard,\"/future/external/(?P<id>[A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/review\")&&!has17($reviewguard,\"/future/translations/(?P<id>\\\\d+)/review\")&&has17($gov,\"/integrity/([0-9a-fA-F-]{36})/apply\")&&has17($gov,\"/dataset-access/([A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/approve\")&&has17($gov,\"'review' === $match[2]\")&&has17($gov,\"'review' === $match[3]\"),'R20 residual numeric routes/object-scope reviewer gates remain');}",1)
write(p,s)

# Final workflow recognizes corrected R20 and validates opaque external review route.
p=ROOT/'.github'/'workflows'/'file06-v2417-eighteenth-twenty-round-final.yml'; s=read(p)
s=s.replace("('R20 ledger','20. **CLEAN**',review)","('R20 ledger','20. **DEFECT**',review)",1)
s=s.replace('foreach(array("/future/claims/(?P<id>[0-9a-fA-F-]{36})/evidence","/future/translations/(?P<id>[0-9a-fA-F-]{36})","/future/impact/(?P<id>[0-9a-fA-F-]{36})") as $needle)', 'foreach(array("/future/claims/(?P<id>[0-9a-fA-F-]{36})/evidence","/future/translations/(?P<id>[0-9a-fA-F-]{36})","/future/impact/(?P<id>[0-9a-fA-F-]{36})","/future/external/(?P<id>[A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/review") as $needle)',1)
s=s.replace('||strpos($routes,"/future/impact/(?P<id>\\\\d+)")!==false)', '||strpos($routes,"/future/impact/(?P<id>\\\\d+)")!==false||strpos($routes,"/future/external/(?P<id>\\\\d+)/review")!==false)',1)
write(p,s)
print('v2417-r20-final-correction-applied')

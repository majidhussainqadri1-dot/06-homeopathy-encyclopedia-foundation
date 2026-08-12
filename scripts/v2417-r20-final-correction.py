#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INC = ROOT / 'homeopathy-encyclopedia' / 'includes'

def read(path): return Path(path).read_text(encoding='utf-8')
def write(path, text): Path(path).write_text(text, encoding='utf-8')
def replace_once(text, old, new, label):
    count=text.count(old)
    if count!=1: raise SystemExit(f'{label}: expected one match, found {count}')
    return text.replace(old,new,1)
def replace_between(text,start,end,replacement,label):
    a=text.find(start); b=text.find(end,a+len(start)) if a>=0 else -1
    if a<0 or b<0: raise SystemExit(f'{label}: markers missing')
    return text[:a]+replacement+text[b:]
def remove_route_block(text, marker, label):
    a=text.find(marker)
    if a<0: raise SystemExit(f'{label}: marker missing')
    line_start=text.rfind('\n',0,a)+1
    b=text.find('\n\t\t), true );',a)
    if b<0: raise SystemExit(f'{label}: end missing')
    b+=len('\n\t\t), true );')
    return text[:line_start]+text[b:]

p=INC/'class-he-v23-future.php'; s=read(p)
old="""\tpublic static function hooks() {
\t\tadd_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 120 );
\t\tadd_action( self::CRON, array( __CLASS__, 'maintenance' ) );
\t\tadd_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 120 );
\t\tadd_filter( 'sabri_platform_contracts', array( __CLASS__, 'extend_contract' ), 120 );
\t\tadd_filter( 'sabri_notification_event_catalog', array( __CLASS__, 'notification_events' ), 120 );
\t\tadd_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance' ), 160 );
\t}
"""
new="""\tpublic static function hooks() {
\t\t/* v2.4+ owns Future REST and maintenance; retire legacy v2.3 runtime surfaces. */
\t\twp_clear_scheduled_hook( self::CRON );
\t\tadd_filter( 'sabri_platform_contracts', array( __CLASS__, 'extend_contract' ), 120 );
\t\tadd_filter( 'sabri_notification_event_catalog', array( __CLASS__, 'notification_events' ), 120 );
\t\tadd_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance' ), 160 );
\t}
"""
s=replace_once(s,old,new,'v23 hooks')
old="""\tpublic static function activate() {
\t\tself::install();
\t\tif ( ! wp_next_scheduled( self::CRON ) ) {
\t\t\twp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::CRON );
\t\t}
\t}
"""
new="""\tpublic static function activate() {
\t\tif ( class_exists( 'HE_V24_Migration_Safety' ) ) { wp_clear_scheduled_hook( self::CRON ); return; }
\t\tself::install();
\t\tif ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'twicedaily', self::CRON ); }
\t}
"""
s=replace_once(s,old,new,'v23 activate')
s=replace_once(s,"\t\tupdate_option( HE_V2_Schema::OPTION_SCHEMA, HE_SCHEMA_VERSION, false );\n",'', 'v23 core schema ownership')
write(p,s)

p=INC/'class-he-v24-future-review-guard.php'; s=read(p)
s=replace_once(s,"'/future/external/(?P<id>\\\\d+)/review'","'/future/external/(?P<id>[A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/review'",'external route')
s=remove_route_block(s,"register_rest_route( $ns, '/future/translations/(?P<id>\\\\d+)/review'",'numeric translation review')
s=remove_route_block(s,"register_rest_route( $ns, '/future/translations/(?P<id>\\\\d+)/publish'",'numeric translation publish')
s=replace_once(s,"/future/claims/(\\\\d+)/review$#","/future/claims/([0-9a-fA-F-]{36})/review$#",'claim route guard')
s=replace_once(s,"return self::claim_approval_gate( absint( $match[1] ), $response );","return self::claim_approval_gate( strtolower( sanitize_text_field( $match[1] ) ), $response );",'claim caller')
old="""\tprivate static function claim_approval_gate( $claim_id, $response ) {
\t\tglobal $wpdb;
\t\t$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE id=%d', $claim_id ), ARRAY_A );
"""
new="""\tprivate static function claim_approval_gate( $claim_public_id, $response ) {
\t\tglobal $wpdb;
\t\t$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE public_id=%s', $claim_public_id ), ARRAY_A );
\t\t$claim_id = $claim ? (int) $claim['id'] : 0;
"""
s=replace_once(s,old,new,'claim canonical lookup')
external="""\tpublic static function rest_external_review( WP_REST_Request $request ) {
\t\t$token = sanitize_text_field( (string) $request['id'] );
\t\t$record_id = HE_V2_Domain::decode_public_cursor( 'external-record', $token );
\t\tif ( null === $record_id || ! $record_id ) { return new WP_Error( 'he_not_found', __( 'External scholarly record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t$reservation = self::guard( $request, 'external-review-' . substr( hash( 'sha256', $token ), 0, 24 ), HE_V2_Auth::CAP_REVIEW );
\t\tif ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null ); }
\t\tglobal $wpdb; $table = HE_V24_Future_Schema::table( 'external_records' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) { return self::finish( $reservation, new WP_Error( 'he_future_external_review_failed', __( 'The external review could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) ); }
\t\ttry {
\t\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d FOR UPDATE\", $record_id ), ARRAY_A );
\t\t\tif ( ! $row || 1 !== (int) $row['review_required'] ) { throw new RuntimeException( 'version-conflict' ); }
\t\t\t$decision = sanitize_key( $request->get_param( 'decision' ) );
\t\t\tif ( ! in_array( $decision, array( 'approved','rejected' ), true ) ) { throw new RuntimeException( 'invalid-decision' ); }
\t\t\t$status = 'approved' === $decision ? 'reviewed' : 'rejected';
\t\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status=%s,review_required=0 WHERE id=%d AND review_required=1\", $status, $record_id ) );
\t\t\tif ( 1 !== (int) $updated ) { throw new RuntimeException( 'version-conflict' ); }
\t\t\tif ( ! HE_V24_Future_Schema::append_provenance( 'external-record', (string) $record_id, 'metadata.reviewed', '', array( 'decision'=>$decision, 'provider'=>$row['provider'], 'external_id'=>$row['external_id'] ) ) ) { throw new RuntimeException( 'provenance-failed' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tif ( 'invalid-decision' === $error->getMessage() ) { return self::finish( $reservation, new WP_Error( 'he_future_external_review_invalid', __( 'External scholarly review decision must be approved or rejected.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
\t\t\tif ( 'version-conflict' === $error->getMessage() ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'This external scholarly record was already reviewed or changed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
\t\t\tHE_V2_Schema::record_runtime_failure( 'external_review_atomic_failed', 'External scholarly review state and provenance could not be committed atomically.' );
\t\t\treturn self::finish( $reservation, new WP_Error( 'he_future_external_review_failed', __( 'The external scholarly review could not be saved atomically.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) );
\t\t}
\t\treturn self::finish( $reservation, array( 'id'=>$token, 'status'=>$status, 'review_required'=>false ), 200 );
\t}

"""
s=replace_between(s,"\tpublic static function rest_external_review( WP_REST_Request $request ) {","\tprivate static function translation_row( $id ) {",external,'external review method')
write(p,s)

p=INC/'class-he-v241-governance.php'; s=read(p)
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/(\\d+)/apply$#', $route, $match ) ) {
\t\t\t$concept_id = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT object_id FROM \" . HE_V2_Schema::table( 'integrity_actions' ) . \" WHERE id=%d AND object_type='concept'\", absint( $match[1] ) ) );
"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/integrity/([0-9a-fA-F-]{36})/apply$#', $route, $match ) ) {
\t\t\t$concept_id = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT object_id FROM \" . HE_V2_Schema::table( 'integrity_actions' ) . \" WHERE public_id=%s AND object_type='concept'\", strtolower( sanitize_text_field( $match[1] ) ) ) );
"""
s=replace_once(s,old,new,'integrity guard')
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/(\\d+)/transition$#', $route, $match ) ) {
\t\t\t$research = self::research_row( $match[1] );
"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/([0-9a-fA-F-]{36})/transition$#', $route, $match ) ) {
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( 'SELECT id,post_id,created_by,status,record_type FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( $match[1] ) ) ), ARRAY_A );
"""
s=replace_once(s,old,new,'research guard')
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/(\\d+)/approve$#', $route, $match ) ) {
\t\t\t$research_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT research_id FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE id=%d', absint( $match[1] ) ) );
"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/([A-Za-z0-9_-]+\\.[a-f0-9]{64})/approve$#', $route, $match ) ) {
\t\t\t$access_id = HE_V2_Domain::decode_public_cursor( 'dataset-access', $match[1] );
\t\t\t$research_id = $access_id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT research_id FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE id=%d', $access_id ) ) : 0;
"""
s=replace_once(s,old,new,'dataset guard')
s=replace_once(s,"if ( 'review' === $match[3] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id ) ) {","if ( 'review' === $match[2] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id ) ) {",'claim reviewer index')
s=replace_once(s,"if ( 'review' === $match[2] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id, 'language' ) ) {","if ( 'review' === $match[3] && ! self::reviewer_assigned( (int) $concept['post_id'], $user_id, 'language' ) ) {",'translation reviewer index')
old="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/(\\d+)/review$#', $route, $match ) ) {
\t\t\t$concept = self::concept_for_external_record( $match[1] );
"""
new="""\t\tif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/future/external/([A-Za-z0-9_-]+\\.[a-f0-9]{64})/review$#', $route, $match ) ) {
\t\t\t$record_id = HE_V2_Domain::decode_public_cursor( 'external-record', $match[1] );
\t\t\t$concept = $record_id ? self::concept_for_external_record( $record_id ) : null;
"""
s=replace_once(s,old,new,'external guard')
write(p,s)

p=INC/'class-he-v241-before-callback-normalizer.php'; s=read(p)
for old,new,label in [
("'/integrity/\\d+/apply$#'","'/integrity/[0-9a-fA-F-]{36}/apply$#'",'normalizer integrity'),
("'/research/\\d+/transition$#'","'/research/[0-9a-fA-F-]{36}/transition$#'",'normalizer research'),
("'/dataset-access/\\d+/approve$#'","'/dataset-access/[A-Za-z0-9_-]+\\\\.[a-f0-9]{64}/approve$#'",'normalizer dataset'),
("'/future/external/\\d+/review$#'","'/future/external/[A-Za-z0-9_-]+\\\\.[a-f0-9]{64}/review$#'",'normalizer external')]:
    s=replace_once(s,old,new,label)
write(p,s)

p=ROOT/'docs'/'FILE-06-v2.4.17-EIGHTEENTH-TWENTY-ROUND-REVIEW.md'; s=read(p)
s=replace_once(s,"20. **CLEAN** — Final fresh cross-cutting review after all corrections found no additional repository-level defect. Exact-head automated QA remains the release gate; staging/live remain separate evidence states.","20. **DEFECT** — Final exact-head WordPress route smoke exposed residual v2.3 numeric mutation routes plus stale v2.4.1/review-guard numeric object-scope patterns that could bypass canonical-ID reviewer/object gates. Legacy v2.3 REST/maintenance runtime is retired under v2.4+, external review now uses an opaque token, core integrity/research/dataset/external guards use current canonical IDs/tokens, and claim/translation reviewer-match indices are corrected; exact-head final QA must pass after this correction.",'R20 ledger')
write(p,s)

p=ROOT/'tests'/'v2417-eighteenth-twenty-round-regressions.php'; s=read(p)
s=replace_once(s,"$runtime=r17($inc.'/class-he-v242-runtime-corrections.php');","$runtime=r17($inc.'/class-he-v242-runtime-corrections.php');$v23=r17($inc.'/class-he-v23-future.php');$reviewguard=r17($inc.'/class-he-v24-future-review-guard.php');",'R20 test imports')
s=replace_once(s,"if($round>=20){ok17(has17($ledger,'20. **CLEAN**'),'R20 final clean audit record missing');}","if($round>=20){ok17(has17($ledger,'20. **DEFECT**')&&has17($v23,'retire legacy v2.3 runtime surfaces')&&!has17($v23,\"add_action( 'rest_api_init'\")&&!has17($v23,\"add_action( self::CRON\")&&has17($reviewguard,\"/future/external/(?P<id>[A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/review\")&&!has17($reviewguard,\"/future/translations/(?P<id>\\\\d+)/review\")&&has17($gov,\"/integrity/([0-9a-fA-F-]{36})/apply\")&&has17($gov,\"/dataset-access/([A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/approve\")&&has17($gov,\"'review' === $match[2]\")&&has17($gov,\"'review' === $match[3]\"),'R20 residual numeric routes/object-scope reviewer gates remain');}",'R20 test assertion')
write(p,s)

p=ROOT/'.github'/'workflows'/'file06-v2417-eighteenth-twenty-round-final.yml'; s=read(p)
s=replace_once(s,"('R20 ledger','20. **CLEAN**',review)","('R20 ledger','20. **DEFECT**',review)",'final R20 marker')
old='foreach(array("/future/claims/(?P<id>[0-9a-fA-F-]{36})/evidence","/future/translations/(?P<id>[0-9a-fA-F-]{36})","/future/impact/(?P<id>[0-9a-fA-F-]{36})") as $needle)'
new='foreach(array("/future/claims/(?P<id>[0-9a-fA-F-]{36})/evidence","/future/translations/(?P<id>[0-9a-fA-F-]{36})","/future/impact/(?P<id>[0-9a-fA-F-]{36})","/future/external/(?P<id>[A-Za-z0-9_-]+\\\\.[a-f0-9]{64})/review") as $needle)'
s=replace_once(s,old,new,'final route list')
s=replace_once(s,'||strpos($routes,"/future/impact/(?P<id>\\\\d+)")!==false)','||strpos($routes,"/future/impact/(?P<id>\\\\d+)")!==false||strpos($routes,"/future/external/(?P<id>\\\\d+)/review")!==false)','final numeric reject')
needle="run_clean wp eval '$t=HE_V2_Domain::encode_public_cursor(\"reference\",123);if(!$t||HE_V2_Domain::decode_public_cursor(\"reference\",$t)!==123){throw new Exception(\"opaque reference token mismatch\");}echo \"v2417-reference-token-ok\\n\";'"
s=replace_once(s,needle,needle+"\n          run_clean wp eval 'if(wp_next_scheduled(HE_V23_Future::CRON)){throw new Exception(\"legacy v2.3 cron remains scheduled\");}echo \"v2417-v23-runtime-retired-ok\\n\";'",'v23 runtime smoke')
write(p,s)
print('v2417-r20-final-correction-applied')

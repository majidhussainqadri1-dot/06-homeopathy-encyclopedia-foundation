#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'homeopathy-encyclopedia'
T=ROOT/'tests'
D=ROOT/'docs'
round_no=int(sys.argv[1]) if len(sys.argv)>1 else 0
if round_no not in range(1,11): raise SystemExit('round must be 1..10')

def read(p): return Path(p).read_text(encoding='utf-8')
def write(p,s): Path(p).write_text(s,encoding='utf-8')
def replace_once(p,old,new,label):
    p=Path(p); s=read(p); n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected exactly one match, found {n} in {p}')
    write(p,s.replace(old,new,1))
def regex_once(p,pat,repl,label,flags=re.S):
    p=Path(p); s=read(p); out,n=re.subn(pat,lambda m: repl,s,count=1,flags=flags)
    if n!=1: raise SystemExit(f'{label}: expected exactly one regex match, found {n} in {p}')
    write(p,out)

domain=P/'includes/class-he-v2-domain.php'
api=P/'includes/class-he-v2-api.php'
gov=P/'includes/class-he-v22-governance.php'
authoring=P/'includes/class-he-v242-research-authoring.php'
runtime=P/'includes/class-he-v242-runtime-corrections.php'
firstsave=P/'includes/class-he-v22-admin-first-save.php'
admin=P/'includes/class-he-v2-admin.php'
future=P/'includes/class-he-v24-future-schema.php'
bootstrap=P/'homeopathy-encyclopedia.php'
readme=P/'readme.txt'

if round_no==1:
    pat=r"\tpublic static function sanitize_structured\( \$fields \) \{.*?\n\t\}\n\n\tprivate static function rollback_new_entry"
    repl="""\tprivate static function sanitize_structured_value( $value, $depth = 0 ) {
\t\tif ( $depth > 6 ) { return ''; }
\t\tif ( is_array( $value ) ) {
\t\t\t$out = array();
\t\t\tforeach ( $value as $key => $item ) {
\t\t\t\t$safe_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
\t\t\t\t$out[ $safe_key ] = self::sanitize_structured_value( $item, $depth + 1 );
\t\t\t}
\t\t\treturn $out;
\t\t}
\t\tif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { return $value; }
\t\tif ( null === $value ) { return ''; }
\t\tif ( ! is_scalar( $value ) ) { return ''; }
\t\treturn sanitize_textarea_field( (string) $value );
\t}

\tpublic static function sanitize_structured( $fields ) {
\t\t$fields = is_array( $fields ) ? $fields : array();
\t\t$output = array();
\t\t$allowed = array( 'source', 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'limitations', 'emergency_boundary', 'evidence_summary' );
\t\tforeach ( $allowed as $key ) {
\t\t\tif ( array_key_exists( $key, $fields ) ) {
\t\t\t\t$output[ $key ] = self::sanitize_structured_value( $fields[ $key ] );
\t\t\t}
\t\t}
\t\treturn $output;
\t}

\tprivate static function rollback_new_entry"""
    regex_once(domain,pat,repl,'R1 recursive structured write sanitizer')

elif round_no==2:
    old="""\t\tif ( $existing_id ) {
\t\t\t$wpdb->update( HE_V2_Schema::table( 'research' ), $payload, array( 'id' => $existing_id ) );
\t\t\t$research_id = $existing_id;
\t\t} else {
\t\t\t$wpdb->insert( HE_V2_Schema::table( 'research' ), $payload );
\t\t\t$research_id = (int) $wpdb->insert_id;
\t\t}
\t\tif ( ! $research_id ) {
\t\t\twp_delete_post( $post_id, true );
\t\t\treturn new WP_Error( 'he_research_write_failed', __( 'Research record could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t}
"""
    new="""\t\t$write_ok = false;
\t\tif ( $existing_id ) {
\t\t\t$written = $wpdb->update( HE_V2_Schema::table( 'research' ), $payload, array( 'id' => $existing_id ) );
\t\t\t$research_id = $existing_id;
\t\t\t$write_ok = false !== $written;
\t\t} else {
\t\t\t$written = $wpdb->insert( HE_V2_Schema::table( 'research' ), $payload );
\t\t\t$research_id = $written ? (int) $wpdb->insert_id : 0;
\t\t\t$write_ok = false !== $written && $research_id > 0;
\t\t}
\t\t$persisted = $research_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT public_id,post_id,record_type,title,question,protocol FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', $research_id ), ARRAY_A ) : null;
\t\t$write_ok = $write_ok && is_array( $persisted ) && (string) $persisted['public_id'] === (string) $payload['public_id'] && (int) $persisted['post_id'] === (int) $post_id && (string) $persisted['record_type'] === (string) $type;
\t\tif ( ! $write_ok ) {
\t\t\tself::rollback_new_research( $research_id ?: $existing_id, $post_id );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_create_persistence_failed', 'A newly created research post could not be bound to a verified File 06 research row.' );
\t\t\treturn new WP_Error( 'he_research_write_failed', __( 'Research record could not be saved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t}
"""
    replace_once(domain,old,new,'R2 research create persistence check')
    anchor="\n\tprivate static function contains_direct_identifiers( $text ) {\n"
    helper="""
\tprivate static function rollback_new_research( $research_id, $post_id ) {
\t\tglobal $wpdb;
\t\t$ok = true;
\t\t$research_id = absint( $research_id );
\t\t$post_id = absint( $post_id );
\t\tif ( $research_id ) {
\t\t\t$deleted = $wpdb->delete( HE_V2_Schema::table( 'research' ), array( 'id' => $research_id ), array( '%d' ) );
\t\t\tif ( false === $deleted ) { $ok = false; }
\t\t}
\t\tif ( $ok && $post_id && get_post( $post_id ) && ! wp_delete_post( $post_id, true ) ) { $ok = false; }
\t\tif ( ! $ok ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_create_compensation_failed', 'File 06 could not fully compensate a failed research create operation; mutations were paused.' );
\t\t}
\t\treturn $ok;
\t}
"""
    replace_once(domain,anchor,helper+anchor,'R2 research create compensation')

elif round_no==3:
    old="""\t\tif ( ! $private && 'published' !== $row['status'] ) {
\t\t\treturn null;
\t\t}
"""
    new="""\t\tif ( ! $private && ( ! class_exists( 'HE_V22_Research_Guard' ) || ! HE_V22_Research_Guard::public_surface_eligible( $row ) ) ) {
\t\t\treturn null;
\t\t}
"""
    replace_once(domain,old,new,'R3 canonical public research DTO eligibility')
    replace_once(api,"WHERE status='published' AND id>%d ORDER BY id ASC LIMIT %d","WHERE status IN ('published','corrected','retracted') AND id>%d ORDER BY id ASC LIMIT %d",'R3 legacy research browse states')

elif round_no==4:
    pat=r"\tpublic static function merge_concepts\( \$source_id, \$target_id, \$expected_source_version, \$expected_target_version, \$actor_id, \$reason \) \{.*?\n\t\}\n\n\tpublic static function sanitize_text_list"
    repl="""\tpublic static function merge_concepts( $source_id, $target_id, $expected_source_version, $expected_target_version, $actor_id, $reason ) {
\t\tif ( ! class_exists( 'HE_V22_Governance' ) ) {
\t\t\treturn new WP_Error( 'he_merge_failed', __( 'Governed merge service is unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\treturn HE_V22_Governance::secure_merge( array(
\t\t\t'source_id' => $source_id,
\t\t\t'target_id' => $target_id,
\t\t\t'source_version' => absint( $expected_source_version ),
\t\t\t'target_version' => absint( $expected_target_version ),
\t\t\t'actor_id' => absint( $actor_id ),
\t\t\t'reason' => sanitize_textarea_field( $reason ),
\t\t) );
\t}

\tpublic static function sanitize_text_list"""
    regex_once(domain,pat,repl,'R4 delegate legacy merge to governed merge')

elif round_no==5:
    anchor="\n\tpublic static function create_research( $data, $actor_id ) {\n"
    helper="""
\tpublic static function normalize_conflicts( $value ) {
\t\t$parts = self::sanitize_text_list( $value );
\t\tif ( ! $parts ) { return array(); }
\t\t$statement = sanitize_textarea_field( implode( '; ', $parts ) );
\t\t$none = (bool) preg_match( '/\\b(?:no|none)\\s+(?:conflict|conflicts)\\b/i', $statement );
\t\treturn array( 'recorded' => true, 'statement' => $statement, 'none_declared' => $none );
\t}
"""
    replace_once(domain,anchor,helper+anchor,'R5 canonical conflict helper')
    old="""\t\tif ( ! $title || ! $question || ! $protocol ) {
\t\t\treturn new WP_Error( 'he_research_required_fields', __( 'Research title, question and protocol are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\t$consent = ! empty( $data['consent_verified'] );
"""
    new="""\t\tif ( ! $title || ! $question || ! $protocol ) {
\t\t\treturn new WP_Error( 'he_research_required_fields', __( 'Research title, question and protocol are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\t$investigators = self::sanitize_text_list( $data['investigators'] ?? array() );
\t\t$conflicts = self::normalize_conflicts( $data['conflicts'] ?? array() );
\t\tif ( ! $investigators || ! $conflicts ) {
\t\t\treturn new WP_Error( 'he_research_governance_required', __( 'At least one investigator and an explicit conflict-of-interest statement are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t}
\t\t$consent = ! empty( $data['consent_verified'] );
"""
    replace_once(domain,old,new,'R5 domain research governance required')
    replace_once(domain,"'investigators_json' => wp_json_encode( self::sanitize_text_list( $data['investigators'] ?? array() ) ),","'investigators_json' => wp_json_encode( $investigators, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),",'R5 canonical investigator storage')
    replace_once(domain,"'conflicts_json' => wp_json_encode( self::sanitize_text_list( $data['conflicts'] ?? array() ) ),","'conflicts_json' => wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),",'R5 canonical conflict storage')
    oldv="""\t\tif ( ! self::investigators( $data['investigators'] ?? array() ) || ! self::conflicts( implode( '; ', (array) ( $data['conflicts'] ?? array() ) ) ) ) { return new WP_Error( 'he_research_governance_required', __( 'At least one investigator and an explicit conflict-of-interest statement are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
"""
    newv="""\t\tif ( ! HE_V2_Domain::sanitize_text_list( $data['investigators'] ?? array() ) || ! HE_V2_Domain::normalize_conflicts( $data['conflicts'] ?? array() ) ) { return new WP_Error( 'he_research_governance_required', __( 'At least one investigator and an explicit conflict-of-interest statement are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
"""
    replace_once(authoring,oldv,newv,'R5 composer canonical governance validation')
    pat=r"\t\t/\* Normalize the conflict structure immediately; composer does not traverse REST after-callback normalization\. \*/.*?\t\treturn HE_V2_Domain::research_dto\( \(int\) \$row\['id'\], true \);"
    repl="""\t\t/* Domain creation now persists the canonical conflict structure before success. Verify it without a second mutation. */
\t\tglobal $wpdb;
\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id,conflicts_json FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $result['id'] ?? '' ), ARRAY_A );
\t\t$expected_conflicts = HE_V2_Domain::normalize_conflicts( $payload['conflicts'] ?? array() );
\t\t$stored_conflicts = $row ? json_decode( (string) $row['conflicts_json'], true ) : null;
\t\tif ( ! $row || $stored_conflicts !== $expected_conflicts ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_conflict_canonicalization_failed', 'Research creation did not persist the canonical conflict disclosure shape.' );
\t\t\treturn new WP_Error( 'he_research_normalization_conflict', __( 'Research governance normalization could not be verified; mutations have been paused.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t}
\t\treturn HE_V2_Domain::research_dto( (int) $row['id'], true );"""
    regex_once(authoring,pat,repl,'R5 composer verify canonical conflict')

elif round_no==6:
    replace_once(runtime,"\t\tadd_filter( 'rest_request_after_callbacks', array( __CLASS__, 'verify_research_create_normalization' ), 365, 3 );\n",'', 'R6 remove post-success normalization hook')
    pat=r"\tpublic static function verify_research_create_normalization\( \$response, \$handler, \$request \) \{.*?\n\t\}\n\n\tpublic static function research_create_response_parity"
    repl="""\tpublic static function verify_research_create_normalization( $response, $handler, $request ) {
\t\t/* Retained as a verification helper for diagnostics; it must never mutate state after route/idempotency success. */
\t\tif ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response || is_wp_error( $response ) || 'POST' !== $request->get_method() || '/' . HE_V2_API::NS . '/research' !== $request->get_route() ) { return $response; }
\t\t$public_id = self::response_public_id( $response );
\t\tif ( ! $public_id ) { return $response; }
\t\tglobal $wpdb;
\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT conflicts_json FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\t$input = (array) $request->get_json_params();
\t\t$expected = HE_V2_Domain::normalize_conflicts( $input['conflicts'] ?? array() );
\t\t$stored = $row ? json_decode( (string) $row['conflicts_json'], true ) : null;
\t\tif ( ! $row || ! $expected || $stored !== $expected ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_conflict_postsuccess_invariant_failed', 'A completed research create response failed canonical conflict verification; no post-success mutation was attempted.' );
\t\t}
\t\treturn $response;
\t}

\tpublic static function research_create_response_parity"""
    regex_once(runtime,pat,repl,'R6 verification-only after-callback helper')

elif round_no==7:
    old="""\t\t$guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' );
\t\t$wpdb->query( 'START TRANSACTION' );
\t\tremove_filter( 'pre_delete_post', $guard, 1 );
\t\ttry {
\t\t\tif ( ! wp_delete_post( (int) $row['post_id'], true ) ) { throw new RuntimeException( 'post-delete-failed' ); }
\t\t\tif ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'research' ), array( 'id' => (int) $row['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'research-delete-failed' ); }
\t\t\t$wpdb->query( 'COMMIT' );
"""
    new="""\t\t$guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_composer_rollback_start_failed', 'File 06 could not start the research composer rollback transaction.' );
\t\t\treturn false;
\t\t}
\t\tremove_filter( 'pre_delete_post', $guard, 1 );
\t\ttry {
\t\t\tif ( ! wp_delete_post( (int) $row['post_id'], true ) ) { throw new RuntimeException( 'post-delete-failed' ); }
\t\t\tif ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'research' ), array( 'id' => (int) $row['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'research-delete-failed' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'research-rollback-commit-failed' ); }
"""
    replace_once(authoring,old,new,'R7 research rollback transaction certainty')

elif round_no==8:
    old="""\t\t$delete_guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' );
\t\t$domain_delete = array( 'HE_V2_Domain', 'on_delete_post' );
\t\t$wpdb->query( 'START TRANSACTION' );
\t\tremove_filter( 'pre_delete_post', $delete_guard, 1 );
\t\tremove_action( 'before_delete_post', $domain_delete, 10 );
\t\ttry {
\t\t\t/* Delete the WordPress post inside the same DB transaction; if any later cleanup fails, rollback restores both sides. */
\t\t\t$deleted_post = wp_delete_post( $post_id, true );
\t\t\tif ( ! $deleted_post ) {
\t\t\t\tthrow new RuntimeException( 'post-delete-failed' );
\t\t\t}
\t\t\t$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d', $concept_id, $concept_id ) );
\t\t\tforeach ( array( 'aliases','references','versions','search_index','bookmarks' ) as $suffix ) {
\t\t\t\t$wpdb->delete( HE_V2_Schema::table( $suffix ), array( 'concept_id' => $concept_id ), array( '%d' ) );
\t\t\t}
\t\t\t$wpdb->delete( HE_V2_Schema::table( 'reviews' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) );
\t\t\t$wpdb->delete( HE_V2_Schema::table( 'integrity_actions' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) );
\t\t\tif ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) ) ) {
\t\t\t\tthrow new RuntimeException( 'concept-delete-failed' );
\t\t\t}
\t\t\t$wpdb->query( 'COMMIT' );
"""
    new="""\t\t$delete_guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' );
\t\t$domain_delete = array( 'HE_V2_Domain', 'on_delete_post' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'composer_rollback_start_failed', 'File 06 could not start the entry composer rollback transaction.' );
\t\t\treturn false;
\t\t}
\t\tremove_filter( 'pre_delete_post', $delete_guard, 1 );
\t\tremove_action( 'before_delete_post', $domain_delete, 10 );
\t\ttry {
\t\t\t/* Delete the WordPress post inside the same DB transaction; if any later cleanup fails, rollback restores both sides. */
\t\t\t$deleted_post = wp_delete_post( $post_id, true );
\t\t\tif ( ! $deleted_post ) { throw new RuntimeException( 'post-delete-failed' ); }
\t\t\t$relation_deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d', $concept_id, $concept_id ) );
\t\t\tif ( false === $relation_deleted ) { throw new RuntimeException( 'relation-delete-failed' ); }
\t\t\tforeach ( array( 'aliases','references','versions','search_index','bookmarks' ) as $suffix ) {
\t\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( $suffix ), array( 'concept_id' => $concept_id ), array( '%d' ) ) ) { throw new RuntimeException( 'child-delete-failed' ); }
\t\t\t}
\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'reviews' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'review-delete-failed' ); }
\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'integrity_actions' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'integrity-delete-failed' ); }
\t\t\tif ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) ) ) { throw new RuntimeException( 'concept-delete-failed' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'entry-rollback-commit-failed' ); }
"""
    replace_once(runtime,old,new,'R8 entry rollback write certainty')

elif round_no==9:
    old="""\t\t$wpdb->update( $table, array(
\t\t\t'record_type' => $record_type,
\t\t\t'question' => sanitize_textarea_field( wp_unslash( $_POST['he_question'] ?? $row['question'] ) ),
\t\t\t'protocol' => wp_kses_post( wp_unslash( $_POST['he_protocol'] ?? $row['protocol'] ) ),
\t\t\t'ethics_json' => wp_json_encode( $ethics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\t'consent_json' => wp_json_encode( $consent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\t'data_class' => $data_class,
\t\t\t'case_anonymized' => ! empty( $_POST['he_v2_case_anonymized'] ) ? 1 : 0,
\t\t\t'case_consent_verified' => ! empty( $_POST['he_v2_consent_verified'] ) ? 1 : 0,
\t\t\t'case_tag' => 'successful-case' === $record_type ? 'کامیاب کیس' : '',
\t\t\t'case_json' => wp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\t'metadata_json' => wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\t'row_version' => (int) $row['row_version'] + 1,
\t\t\t'updated_at' => current_time( 'mysql', true ),
\t\t), array( 'id' => (int) $row['id'] ) );
"""
    new="""\t\t$updated = $wpdb->query( $wpdb->prepare(
\t\t\t\"UPDATE {$table} SET record_type=%s,question=%s,protocol=%s,ethics_json=%s,consent_json=%s,data_class=%s,case_anonymized=%d,case_consent_verified=%d,case_tag=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\",
\t\t\t$record_type,
\t\t\tsanitize_textarea_field( wp_unslash( $_POST['he_question'] ?? $row['question'] ) ),
\t\t\twp_kses_post( wp_unslash( $_POST['he_protocol'] ?? $row['protocol'] ) ),
\t\t\twp_json_encode( $ethics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\twp_json_encode( $consent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\t$data_class,
\t\t\t! empty( $_POST['he_v2_case_anonymized'] ) ? 1 : 0,
\t\t\t! empty( $_POST['he_v2_consent_verified'] ) ? 1 : 0,
\t\t\t'successful-case' === $record_type ? 'کامیاب کیس' : '',
\t\t\twp_json_encode( $case, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\twp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
\t\t\t(int) $row['id'], (int) $row['row_version']
\t\t) );
\t\tif ( 1 !== (int) $updated ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_first_save_cas_failed', 'Research first-save governance fields could not be persisted against the expected row version.' );
\t\t}
"""
    replace_once(firstsave,old,new,'R9 first-save research CAS')
    old2="""\t\t$wpdb->update( $table, array( 'status' => 'proposal', 'row_version' => (int) $row['row_version'] + 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s','%d','%s' ), array( '%d' ) );
"""
    new2="""\t\t$normalized = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='proposal',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='published' AND row_version=%d\", (int) $row['id'], (int) $row['row_version'] ) );
\t\t\tif ( false === $normalized ) { HE_V2_Schema::record_runtime_failure( 'manual_research_state_normalization_failed', 'Legacy manual research state normalization could not be persisted safely.' ); }
"""
    replace_once(gov,old2,new2,'R9 manual research state CAS')
    # Legacy admin save: make write fail closed without changing same-request hook ordering.
    old3="""\t\t$wpdb->update( $table, array(
\t\t\t'record_type' => $type,
\t\t\t'title' => $post->post_title,
\t\t\t'question' => $post->post_excerpt,
\t\t\t'protocol' => $post->post_content,
\t\t\t'ethics_json' => wp_json_encode( $ethics ),
\t\t\t'consent_json' => wp_json_encode( $consent ),
\t\t\t'data_class' => $data_class,
\t\t\t'case_anonymized' => $anonymized ? 1 : 0,
\t\t\t'case_consent_verified' => $consent['verified'] ? 1 : 0,
\t\t\t'case_tag' => $case_tag,
\t\t\t'case_json' => wp_json_encode( $case ),
\t\t\t'metadata_json' => wp_json_encode( $metadata ),
\t\t\t'row_version' => (int) $row['row_version'] + 1,
\t\t\t'updated_at' => current_time( 'mysql', true ),
\t\t), array( 'id' => $row['id'] ) );
\t\tif ( $case_tag ) {
\t\t\twp_set_object_terms( $post_id, array( $case_tag ), HE_V2_Domain::TAX_TOPIC, false );
\t\t}
"""
    new3="""\t\tif ( ! in_array( $type, array( 'proposal','protocol','publication','successful-case','dataset' ), true ) ) { $type = 'proposal'; }
\t\tif ( ! in_array( $data_class, array( 'public','restricted','highly-restricted' ), true ) || ( 'dataset' === $type && 'public' === $data_class ) ) { $data_class = 'restricted'; }
\t\t$updated = $wpdb->query( $wpdb->prepare(
\t\t\t\"UPDATE {$table} SET record_type=%s,title=%s,question=%s,protocol=%s,ethics_json=%s,consent_json=%s,data_class=%s,case_anonymized=%d,case_consent_verified=%d,case_tag=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\",
\t\t\t$type, $post->post_title, $post->post_excerpt, $post->post_content, wp_json_encode( $ethics ), wp_json_encode( $consent ), $data_class,
\t\t\t$anonymized ? 1 : 0, $consent['verified'] ? 1 : 0, $case_tag, wp_json_encode( $case ), wp_json_encode( $metadata ), (int) $row['id'], (int) $row['row_version']
\t\t) );
\t\tif ( 1 !== (int) $updated ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'legacy_research_admin_cas_failed', 'Legacy research admin metadata could not be persisted against the expected row version.' );
\t\t\treturn;
\t\t}
\t\tif ( $case_tag ) { wp_set_object_terms( $post_id, array( $case_tag ), HE_V2_Domain::TAX_TOPIC, false ); }
"""
    replace_once(admin,old3,new3,'R9 legacy research admin CAS')
    # Future impact queue transitions: every serialized worker state write must be confirmed.
    replace_once(future,"\t\t\t\t$wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'acknowledged', 'attempts' => $attempts, 'last_error' => '', 'acknowledged_at' => current_time( 'mysql', true ), 'next_attempt_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );\n\t\t\t\tcontinue;","\t\t\t\t$written = $wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'acknowledged', 'attempts' => $attempts, 'last_error' => '', 'acknowledged_at' => current_time( 'mysql', true ), 'next_attempt_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );\n\t\t\t\tif ( 1 !== (int) $written ) { HE_V2_Schema::record_runtime_failure( 'impact_queue_ack_write_failed', 'A consumer acknowledgement could not be persisted; queue processing stopped for this run.' ); break; }\n\t\t\t\tcontinue;",'R9 impact ack certainty')
    replace_once(future,"\t\t\t\t$wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'dead-letter', 'attempts' => $attempts, 'last_error' => 'consumer acknowledgement not received', 'next_attempt_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );\n\t\t\t\tcontinue;","\t\t\t\t$written = $wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'dead-letter', 'attempts' => $attempts, 'last_error' => 'consumer acknowledgement not received', 'next_attempt_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );\n\t\t\t\tif ( 1 !== (int) $written ) { HE_V2_Schema::record_runtime_failure( 'impact_queue_dead_letter_write_failed', 'A dead-letter transition could not be persisted; queue processing stopped for this run.' ); break; }\n\t\t\t\tcontinue;",'R9 impact dead-letter certainty')
    replace_once(future,"\t\t\t$wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'retry', 'attempts' => $attempts, 'last_error' => 'consumer acknowledgement not received', 'next_attempt_at' => $next, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );","\t\t\t$written = $wpdb->update( self::table( 'impact_queue' ), array( 'impact_state' => 'retry', 'attempts' => $attempts, 'last_error' => 'consumer acknowledgement not received', 'next_attempt_at' => $next, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );\n\t\t\tif ( 1 !== (int) $written ) { HE_V2_Schema::record_runtime_failure( 'impact_queue_retry_write_failed', 'A retry transition could not be persisted; queue processing stopped for this run.' ); break; }",'R9 impact retry certainty')

elif round_no==10:
    write(bootstrap,read(bootstrap).replace('2.4.12','2.4.13'))
    s=read(readme).replace('Stable tag: 2.4.12','Stable tag: 2.4.13').replace('The 2.4.12 candidate','The 2.4.13 candidate')
    if '= 2.4.13 =' not in s:
        s=s.replace('== Changelog ==\n','== Changelog ==\n\n= 2.4.13 =\n* Fourteenth ten-round corrective candidate: recursive structured-write safety, verified research persistence/compensation, canonical public research eligibility, single governed merge implementation, canonical research conflict persistence, post-success immutability, rollback transaction certainty, CAS-safe admin writes, impact-queue persistence checks, and refreshed exact-head release truth.\n')
    write(readme,s)
    for p in (T/'v2-invariants.php',T/'v2-source-invariants.sh'):
        write(p,read(p).replace('2.4.12','2.4.13'))
    hist=T/'v2412-thirteenth-ten-round-regressions.php'; hs=read(hist)
    old="v2412_ok(10,false!==strpos($bootstrap,' * Version: 2.4.12')&&false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.12' );\")&&false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.12' );\")&&false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.12'\"),'runtime/contract/future hardening truth is not v2.4.12');\nv2412_ok(10,false!==strpos($readme,'Stable tag: 2.4.12')&&false!==strpos($runall,'v2412-thirteenth-ten-round-regressions.php')&&false!==strpos($runall,'file06-v2.4.12-a.zip')&&false!==strpos($runall,'All File 06 v2.4.12 automated checks'),'aggregate/package release truth is not v2.4.12');"
    new="v2412_ok(10,preg_match('/ \\* Version: 2\\.4\\.(?:12|13)/',$bootstrap)&&preg_match(\"/HE_VERSION', '2\\.4\\.(?:12|13)/\",$bootstrap)&&preg_match(\"/HE_CONTRACT_VERSION', '2\\.4\\.(?:12|13)/\",$bootstrap)&&false!==strpos($bootstrap,\"'future_hardening_version'=>\"),'historical v2.4.12 release controls do not tolerate a later current v2.4.x candidate');\nv2412_ok(10,false!==strpos($runall,'v2412-thirteenth-ten-round-regressions.php'),'historical thirteenth-cycle regression suite is no longer wired into aggregate QA');"
    if old not in hs: raise SystemExit('R10 historical v2412 exact-current assertion not found')
    write(hist,hs.replace(old,new,1))
    run=T/'run-all.sh'; rs=read(run)
    if 'v2413-fourteenth-ten-round-regressions.php' not in rs:
        rs=rs.replace('php "$root/tests/v2412-thirteenth-ten-round-regressions.php"\n','php "$root/tests/v2412-thirteenth-ten-round-regressions.php"\nphp "$root/tests/v2413-fourteenth-ten-round-regressions.php"\n')
    rs=rs.replace('file06-v2.4.12-a.zip','file06-v2.4.13-a.zip').replace('file06-v2.4.12-b.zip','file06-v2.4.13-b.zip')
    rs=rs.replace('All File 06 v2.4.12 automated checks, inherited review matrices, thirteenth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.13 automated checks, inherited review matrices, fourteenth ten-round regressions and deterministic package comparison passed.')
    write(run,rs)
    (D/'RELEASE-NOTES.md').write_text("""# File 06 — Release Notes 2.4.13

Fourteenth fresh ten-round corrective repository candidate. Repository defects were found and corrected in rounds `1–9`; round `10` reconciles runtime, contract, package, tests and current release documentation after those source corrections.

Corrections cover recursive structured-write safety; verified research-row persistence with compensation; canonical published/corrected/retracted research eligibility; one governed merge implementation; canonical investigator/conflict persistence before success; removal of post-success research normalization writes; transaction-certain composer rollbacks; CAS-safe research admin/state normalization; checked Future impact-queue transitions; and exact v2.4.13 release truth.

Staging acceptance, deployed parity and live operation remain separate evidence gates.
""",encoding='utf-8')
    sign=D/'RELEASE-SIGNOFF.md'
    if sign.exists():
        sign.write_text("""# File 06 — v2.4.13 Repository Candidate Signoff

- Repository candidate: v2.4.13
- Base DB schema: 10
- Future internal schema: 2
- Automated QA: pending final exact-head workflow at this source commit
- Staging acceptance: unverified
- Live deployed version: unverified
- Live DB/migration state: unverified
- Operational status: not established

No staging/live claim is implied by repository automation.
""",encoding='utf-8')

print(f'File 06 v2.4.13 fourteenth cycle round {round_no} correction applied')

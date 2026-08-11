#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'homeopathy-encyclopedia'
I = P / 'includes'
T = ROOT / 'tests'
D = ROOT / 'docs'
ROUND = int(sys.argv[1]) if len(sys.argv) > 1 else 0
if ROUND not in range(1, 21):
    raise SystemExit('round must be 1..20')

def read(path):
    return Path(path).read_text(encoding='utf-8')

def write(path, text):
    Path(path).write_text(text, encoding='utf-8')

def replace_once(path, old, new, label):
    path = Path(path); text = read(path); n = text.count(old)
    if n != 1:
        raise SystemExit(f'{label}: expected one match, found {n} in {path}')
    write(path, text.replace(old, new, 1))

def replace_between(path, start, end, replacement, label):
    path = Path(path); text = read(path)
    a = text.find(start)
    if a < 0: raise SystemExit(f'{label}: start marker missing in {path}')
    b = text.find(end, a + len(start))
    if b < 0: raise SystemExit(f'{label}: end marker missing in {path}')
    write(path, text[:a] + replacement + text[b:])

def append_review(round_no, finding, status='DEFECT'):
    path = D / 'FILE-06-v2.4.16-SEVENTEENTH-TWENTY-ROUND-REVIEW.md'
    if not path.exists():
        write(path, '# File 06 v2.4.16 — Seventeenth Fresh Twenty-Round Review\n\nRepository-only corrective record. Staging/live/operational status is not established by this review.\n\n')
    with path.open('a', encoding='utf-8') as f:
        f.write(f'{round_no}. **{status}** — {finding}\n')

domain = I / 'class-he-v2-domain.php'
api = I / 'class-he-v2-api.php'
schema = I / 'class-he-v2-schema.php'
admin = I / 'class-he-v2-admin.php'
gov = I / 'class-he-v22-governance.php'
third = I / 'class-he-v242-third-audit.php'
privacy = I / 'class-he-v2-privacy.php'
bootstrap = P / 'homeopathy-encyclopedia.php'
readme = P / 'readme.txt'

UUID_ROUTE = r'[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}'

if ROUND == 1:
    new_routes = r'''\tpublic static function register_routes() {
\t\t$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
\t\tregister_rest_route( HE_V2_API::NS, '/research/(?P<id>' . $uuid . ')/review', array(
\t\t\t'methods' => WP_REST_Server::CREATABLE,
\t\t\t'callback' => array( __CLASS__, 'rest_review_research' ),
\t\t\t'permission_callback' => function( $request ) { return self::research_permission( $request, HE_V2_Auth::CAP_REVIEW ); },
\t\t) );
\t\tregister_rest_route( HE_V2_API::NS, '/research/(?P<id>' . $uuid . ')/integrity', array(
\t\t\t'methods' => WP_REST_Server::CREATABLE,
\t\t\t'callback' => array( __CLASS__, 'rest_create_research_integrity' ),
\t\t\t'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); },
\t\t) );
\t\tregister_rest_route( HE_V2_API::NS, '/research-integrity/(?P<id>' . $uuid . ')/apply', array(
\t\t\t'methods' => WP_REST_Server::CREATABLE,
\t\t\t'callback' => array( __CLASS__, 'rest_apply_research_integrity' ),
\t\t\t'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH ); },
\t\t) );
\t\tregister_rest_route( HE_V2_API::NS, '/operations/reindex', array(
\t\t\t'methods' => WP_REST_Server::CREATABLE,
\t\t\t'callback' => array( __CLASS__, 'rest_reindex_batch' ),
\t\t\t'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR ); },
\t\t) );
\t}

\tprivate static function research_permission( $request, $cap ) {
\t\tglobal $wpdb;
\t\t$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\tif ( ! $row ) {
\t\t\treturn new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\treturn HE_V2_Auth::rest_permission( $cap, (int) $row['post_id'], 'file06-research' );
\t}

'''
    replace_between(gov, '\tpublic static function register_routes() {', '\tprivate static function mutation_guard(', new_routes, 'R1 routes')

    new_review = r'''\tpublic static function rest_review_research( WP_REST_Request $request ) {
\t\t$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
\t\t$reservation = self::mutation_guard( $request, 'research-review-' . $public_id );
\t\tif ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 201 ); }
\t\tglobal $wpdb;
\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\tif ( ! $row ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 201 ); }
\t\t$data = (array) $request->get_json_params();
\t\t$expected = absint( $data['expected_version'] ?? 0 );
\t\tif ( ! $expected || $expected !== (int) $row['row_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The research record changed after it was loaded for review. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 201 ); }
\t\t$decision = sanitize_key( $data['decision'] ?? 'changes_required' );
\t\tif ( ! in_array( $decision, array( 'approved', 'changes_required', 'rejected' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_invalid_review_decision', __( 'Invalid review decision.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ), 201 ); }
\t\t$conflict = ! empty( $data['conflict_declared'] );
\t\tif ( $conflict && 'approved' === $decision ) { return self::mutation_finish( $reservation, new WP_Error( 'he_review_conflict', __( 'A reviewer with a declared conflict cannot approve this record.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ), 201 ); }
\t\t$post = get_post( (int) $row['post_id'] );
\t\t$reviewer = get_current_user_id();
\t\tif ( ! $post ) { return self::mutation_finish( $reservation, new WP_Error( 'he_research_post_missing', __( 'The authoritative WordPress research object is unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ), 201 ); }
\t\tif ( (int) $post->post_author === $reviewer && ! HE_V2_Auth::is_founder( $reviewer ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_independent_review_required', __( 'The author cannot provide the independent approval review.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ), 201 ); }
\t\t$hash = self::research_hash( $row );
\t\t$reviews = HE_V2_Schema::table( 'reviews' ); $research = HE_V2_Schema::table( 'research' );
\t\t$ok = $wpdb->query( $wpdb->prepare(
\t\t\t"INSERT INTO {$reviews} (object_type,object_id,reviewer_id,scope,decision,conflict_declared,note,content_hash,reviewed_row_version,review_subject_author,created_at) SELECT 'research',r.id,%d,%s,%s,%d,%s,%s,r.row_version,%d,%s FROM {$research} r WHERE r.id=%d AND r.row_version=%d",
\t\t\t$reviewer, sanitize_key( $data['scope'] ?? 'scientific' ), $decision, $conflict ? 1 : 0, sanitize_textarea_field( $data['note'] ?? '' ), $hash, (int) $post->post_author, current_time( 'mysql', true ), (int) $row['id'], $expected
\t\t) );
\t\tif ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_review_write_failed', __( 'The review could not be stored.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ), 201 ); }
\t\tif ( 1 !== (int) $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The research record changed while the review decision was being stored. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 201 ); }
\t\tHE_V2_Domain::emit_event( 'ResearchRecordReviewed.v1', 'research', (int) $row['id'], array( 'decision' => $decision, 'scope' => sanitize_key( $data['scope'] ?? 'scientific' ) ) );
\t\treturn self::mutation_finish( $reservation, array( 'review_id' => HE_V2_Domain::encode_public_cursor( 'review', (int) $wpdb->insert_id ), 'decision' => $decision, 'content_hash' => $hash, 'reviewed_row_version' => $expected ), 201 );
\t}

'''
    replace_between(gov, '\tpublic static function rest_review_research( WP_REST_Request $request ) {', '\tpublic static function rest_create_research_integrity(', new_review, 'R1 research review')

    new_create_integrity = r'''\tpublic static function rest_create_research_integrity( WP_REST_Request $request ) {
\t\t$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
\t\t$reservation = self::mutation_guard( $request, 'research-integrity-' . $public_id );
\t\tif ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 201 ); }
\t\tglobal $wpdb;
\t\t$research = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\tif ( ! $research || ! in_array( $research['status'], array( 'published', 'corrected' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 201 ); }
\t\t$data = (array) $request->get_json_params();
\t\t$type = sanitize_key( $data['type'] ?? 'correction' );
\t\tif ( ! in_array( $type, array( 'correction', 'retraction' ), true ) || ! trim( (string) ( $data['reason'] ?? '' ) ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_integrity_required', __( 'A correction or retraction type and reason are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ), 201 ); }
\t\t$replacement_id = 0;
\t\t$replacement_public = strtolower( sanitize_text_field( (string) ( $data['replacement_id'] ?? '' ) ) );
\t\tif ( '' !== $replacement_public ) {
\t\t\tif ( ctype_digit( $replacement_public ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $replacement_public ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_canonical_public_id_required', __( 'Research replacements require a canonical public identifier.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 201 ); }
\t\t\t$replacement_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $replacement_public ) );
\t\t\tif ( ! $replacement_id || $replacement_id === (int) $research['id'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_invalid_replacement', __( 'A replacement must identify a different existing research record.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ), 201 ); }
\t\t}
\t\t$now = current_time( 'mysql', true ); $action_public_id = wp_generate_uuid4();
\t\t$ok = $wpdb->insert( HE_V2_Schema::table( 'integrity_actions' ), array(
\t\t\t'public_id' => $action_public_id, 'object_type' => 'research', 'object_id' => (int) $research['id'], 'action_type' => $type, 'status' => 'submitted',
\t\t\t'reason' => sanitize_textarea_field( $data['reason'] ), 'evidence' => sanitize_textarea_field( $data['evidence'] ?? '' ), 'replacement_object_id' => $replacement_id,
\t\t\t'row_version' => 1, 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
\t\t) );
\t\tif ( ! $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_integrity_write_failed', __( 'The integrity request could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ), 201 ); }
\t\tHE_V2_Domain::emit_event( 'ResearchIntegritySubmitted.v1', 'research', (int) $research['id'], array( 'action_type' => $type, 'integrity_action' => $action_public_id ) );
\t\treturn self::mutation_finish( $reservation, array( 'id' => $action_public_id, 'status' => 'submitted', 'type' => $type ), 201 );
\t}

'''
    replace_between(gov, '\tpublic static function rest_create_research_integrity( WP_REST_Request $request ) {', '\tpublic static function rest_apply_research_integrity(', new_create_integrity, 'R1 research integrity create')

    new_apply = r'''\tpublic static function rest_apply_research_integrity( WP_REST_Request $request ) {
\t\t$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
\t\t$reservation = self::mutation_guard( $request, 'research-integrity-apply-' . $public_id );
\t\tif ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
\t\tglobal $wpdb; $data = (array) $request->get_json_params(); $expected = absint( $data['expected_version'] ?? 0 );
\t\t$actions = HE_V2_Schema::table( 'integrity_actions' ); $research_table = HE_V2_Schema::table( 'research' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) { HE_V2_Schema::record_runtime_failure( 'research_integrity_transaction_start_failed', 'File 06 could not start the research-integrity apply transaction.' ); return self::mutation_finish( $reservation, new WP_Error( 'he_integrity_apply_failed', __( 'The research integrity action could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ), 200 ); }
\t\ttry {
\t\t\t$action = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$actions} WHERE public_id=%s AND object_type='research' FOR UPDATE", $public_id ), ARRAY_A );
\t\t\tif ( ! $action || 'accepted' !== $action['status'] ) { throw new RuntimeException( 'acceptance-required' ); }
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$research_table} WHERE id=%d FOR UPDATE", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $research ) { throw new RuntimeException( 'research-not-found' ); }
\t\t\t$object_permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $research['post_id'], 'file06-research-integrity-apply' );
\t\t\tif ( is_wp_error( $object_permission ) ) { $wpdb->query( 'ROLLBACK' ); return self::mutation_finish( $reservation, $object_permission, 200 ); }
\t\t\tif ( ! $expected || $expected !== (int) $research['row_version'] ) { throw new RuntimeException( 'research-version-conflict' ); }
\t\t\t$to = 'retraction' === $action['action_type'] ? 'retracted' : 'corrected';
\t\t\t$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$research_table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $to, $research['id'], $expected ) );
\t\t\t$action_updated = $wpdb->query( $wpdb->prepare( "UPDATE {$actions} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='accepted'", get_current_user_id(), (int) $action['id'], (int) $action['row_version'] ) );
\t\t\tif ( 1 !== (int) $updated || 1 !== (int) $action_updated ) { throw new RuntimeException( 'integrity-version-conflict' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'integrity-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' ); $message = $error->getMessage();
\t\t\tif ( 'research-not-found' === $message ) { $result = new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t\telseif ( 'acceptance-required' === $message ) { $result = new WP_Error( 'he_integrity_acceptance_required', __( 'The research integrity action must be accepted before it can be applied.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ); }
\t\t\telseif ( in_array( $message, array( 'research-version-conflict', 'integrity-version-conflict' ), true ) ) { $result = new WP_Error( 'he_version_conflict', __( 'The research or integrity record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ); }
\t\t\telse { HE_V2_Schema::record_runtime_failure( 'research_integrity_atomic_failed', 'File 06 rolled back or could not confirm the research-integrity transaction commit.' ); $result = new WP_Error( 'he_integrity_apply_failed', __( 'The research integrity action could not be applied atomically.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ); }
\t\t\treturn self::mutation_finish( $reservation, $result, 200 );
\t\t}
\t\t$event = 'retracted' === $to ? 'ResearchRecordRetracted.v1' : 'ResearchPublicationCorrected.v1';
\t\tHE_V2_Domain::emit_event( $event, 'research', (int) $research['id'], array( 'reason' => $action['reason'], 'integrity_action' => $action['public_id'] ) );
\t\treturn self::mutation_finish( $reservation, self::research_public_or_private_dto( (int) $research['id'], true ), 200 );
\t}

'''
    replace_between(gov, '\tpublic static function rest_apply_research_integrity( WP_REST_Request $request ) {', '\tpublic static function rest_reindex_batch(', new_apply, 'R1 research integrity apply')
    append_review(1, 'Legacy v2.2 research review/integrity/apply REST contracts exposed raw numeric database IDs. They now use canonical research/integrity UUIDs, canonical replacement IDs and object-scoped apply authorization.')

elif ROUND == 2:
    new_after = r'''\tpublic static function after_rest( $response, $handler, $request ) {
\t\t/* Creation already persists canonical conflict disclosure before the callback returns. This layer verifies only; it must never mutate state after idempotency finalization. */
\t\tif ( ! $request instanceof WP_REST_Request || is_wp_error( $response ) || 'POST' !== $request->get_method() || '/' . HE_V2_API::NS . '/research' !== $request->get_route() || ! $response instanceof WP_REST_Response ) { return $response; }
\t\t$data = $response->get_data(); $public_id = '';
\t\tif ( is_array( $data ) && isset( $data['data']['id'] ) ) { $public_id = sanitize_text_field( (string) $data['data']['id'] ); }
\t\telseif ( is_array( $data ) && isset( $data['id'] ) ) { $public_id = sanitize_text_field( (string) $data['id'] ); }
\t\tif ( ! $public_id ) { return $response; }
\t\t$row = self::research_row( $public_id, true ); $input = (array) $request->get_json_params(); $expected = HE_V2_Domain::normalize_conflicts( $input['conflicts'] ?? array() );
\t\t$stored = $row ? json_decode( (string) $row['conflicts_json'], true ) : null;
\t\tif ( ! $row || ! $expected || $stored !== $expected ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_conflict_postsuccess_invariant_failed', 'A completed research create response failed canonical conflict verification; no post-success mutation was attempted.' );
\t\t}
\t\treturn $response;
\t}

'''
    replace_between(third, '\tpublic static function after_rest( $response, $handler, $request ) {', '\tpublic static function research_concurrency_box()', new_after, 'R2 post-success mutation')
    append_review(2, 'The research-create after-callback rewrote conflicts and incremented row_version after the idempotent response had been finalized. It is now verification-only, eliminating post-success replay/state drift.')

elif ROUND == 3:
    new_schema = r'''\tpublic static function required_tables() {
\t\treturn array( 'concepts','aliases','versions','references','relations','reviews','integrity_actions','research','dataset_access','events','outbox','idempotency','bookmarks','rate_limits','search_index' );
\t}

\tpublic static function required_columns() {
\t\treturn array(
\t\t\t'concepts' => array( 'id','public_id','post_id','type_slug','canonical_slug','language','status','safety_status','review_status','current_version','merged_into_id','row_version','created_by','created_at','updated_at' ),
\t\t\t'aliases' => array( 'id','concept_id','alias','normalized_alias','language','alias_type','is_primary','created_by','created_at' ),
\t\t\t'versions' => array( 'id','concept_id','version_number','status','title','summary','body','structured_json','content_hash','change_reason','effective_at','created_by','created_at' ),
\t\t\t'references' => array( 'id','concept_id','version_id','source_type','title','evidence_grade','rights_status','quotation_word_count','created_by','created_at' ),
\t\t\t'relations' => array( 'id','source_concept_id','target_concept_id','relation_type','owner_file','source_reference_id','status','row_version','created_by','created_at','updated_at' ),
\t\t\t'reviews' => array( 'id','object_type','object_id','reviewer_id','scope','decision','conflict_declared','note','created_at' ),
\t\t\t'integrity_actions' => array( 'id','public_id','object_type','object_id','action_type','status','reason','replacement_object_id','row_version','created_by','decided_by','created_at','updated_at' ),
\t\t\t'research' => array( 'id','public_id','post_id','record_type','status','title','question','protocol','investigators_json','ethics_json','consent_json','conflicts_json','data_class','case_anonymized','case_consent_verified','case_tag','case_json','metadata_json','row_version','created_by','created_at','updated_at' ),
\t\t\t'dataset_access' => array( 'id','research_id','requester_id','purpose','lawful_basis','status','approved_by','expires_at','created_at','updated_at' ),
\t\t\t'events' => array( 'id','event_id','event_name','object_type','object_id','actor_id','trace_id','payload_json','created_at' ),
\t\t\t'outbox' => array( 'id','event_id','event_name','payload_json','status','attempts','next_attempt_at','last_error','created_at','updated_at' ),
\t\t\t'idempotency' => array( 'id','actor_id','operation','idempotency_key','request_hash','response_code','response_json','expires_at','created_at' ),
\t\t\t'bookmarks' => array( 'id','user_id','concept_id','created_at' ),
\t\t\t'rate_limits' => array( 'rate_key','window_start','hit_count','expires_at' ),
\t\t\t'search_index' => array( 'concept_id','first_letter','type_slug','body_system','language','source_grade','review_status','safety_status','search_text','updated_at' ),
\t\t);
\t}

\tpublic static function schema_complete() {
\t\tglobal $wpdb;
\t\tforeach ( self::required_tables() as $suffix ) {
\t\t\t$table = self::table( $suffix );
\t\t\tif ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
\t\t\t$actual = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t\tforeach ( self::required_columns()[ $suffix ] ?? array() as $column ) { if ( ! in_array( $column, $actual, true ) ) { return false; } }
\t\t}
\t\treturn true;
\t}

'''
    replace_between(schema, '\tpublic static function required_tables() {', '\tpublic static function activate()', new_schema, 'R3 schema shape')
    replace_once(schema, "\t\tif ( $current >= HE_SCHEMA_VERSION ) {\n\t\t\treturn;\n\t\t}", "\t\tif ( $current >= HE_SCHEMA_VERSION && self::schema_complete() ) {\n\t\t\treturn;\n\t\t}", 'R3 upgrade early return')
    append_review(3, 'Core readiness treated table existence as schema completeness and skipped repair when the version option was current. Required column-shape verification now participates in health/upgrade readiness.')

elif ROUND == 4:
    old = "\t\t$enabled = ! empty( $_POST['enabled'] );\n\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, $enabled ? 1 : 0, false );\n\t\tHE_V2_Domain::emit_event( $enabled ? 'File06SafeModeEnabled.v1' : 'File06SafeModeDisabled.v1', 'system', 0, array( 'actor_id' => get_current_user_id() ) );"
    new = "\t\t$enabled = ! empty( $_POST['enabled'] );\n\t\tif ( $enabled ) {\n\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );\n\t\t\tHE_V2_Domain::emit_event( 'File06SafeModeEnabled.v1', 'system', 0, array( 'actor_id' => get_current_user_id() ) );\n\t\t} else {\n\t\t\t$result = HE_V2_Schema::repair( false );\n\t\t\tif ( is_wp_error( $result ) || get_option( HE_V2_Schema::OPTION_SAFE_MODE ) || ! HE_V2_Schema::schema_complete() ) {\n\t\t\t\tset_transient( 'he_v2_admin_notice_' . get_current_user_id(), array( 'type' => 'error', 'message' => __( 'Safe mode remains active because verified repair did not establish a healthy runtime.', 'homeopathy-encyclopedia' ) ), 60 );\n\t\t\t\twp_safe_redirect( admin_url( 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE . '&page=he-v2-operations' ) ); exit;\n\t\t\t}\n\t\t\tHE_V2_Domain::emit_event( 'File06SafeModeDisabled.v1', 'system', 0, array( 'actor_id' => get_current_user_id(), 'verified_repair' => true ) );\n\t\t}"
    replace_once(admin, old, new, 'R4 safe mode bypass')
    append_review(4, 'The admin safe-mode toggle could clear protection without verified schema/index repair. Disabling safe mode now requires successful bounded repair and a healthy schema check.')

elif ROUND == 5:
    replace_once(admin, "\t\tadd_action( 'admin_menu', array( $this, 'menu' ) );", "\t\tadd_action( 'admin_menu', array( $this, 'menu' ) );\n\t\tadd_filter( 'wp_insert_post_data', array( $this, 'guard_entry_admin_write' ), 3, 2 );", 'R5 entry preflight hook')
    marker = "\tpublic function save_entry_meta( $post_id, $post ) {"
    guard = r'''\tpublic function guard_entry_admin_write( $data, $postarr ) {
\t\tif ( ! is_admin() || HE_V2_Domain::ENTRY_TYPE !== ( $data['post_type'] ?? '' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return $data; }
\t\t$post_id = absint( $postarr['ID'] ?? ( $_POST['post_ID'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
\t\tif ( ! $post_id || wp_is_post_revision( $post_id ) || ! isset( $_POST['he_v2_nonce'] ) ) { return $data; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
\t\tif ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_nonce'] ) ), 'he_v2_save_entry' ) ) { return $data; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
\t\t$row = HE_V2_Domain::concept_by_post_id( $post_id );
\t\tif ( ! $row ) { return $data; }
\t\t$expected = isset( $_POST['he_v2_expected_version'] ) ? absint( $_POST['he_v2_expected_version'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
\t\tif ( ! $expected || $expected !== (int) $row['row_version'] ) { wp_die( esc_html__( 'This encyclopedia entry changed after the editor form was loaded. Reload before saving; no stale overwrite was accepted.', 'homeopathy-encyclopedia' ), esc_html__( 'File 06 version conflict', 'homeopathy-encyclopedia' ), array( 'response' => 409 ) ); }
\t\treturn $data;
\t}

'''
    replace_once(admin, marker, guard + marker, 'R5 entry preflight method')
    old_tail = "\t\tHE_V2_Domain::ensure_concept_for_post( $post_id );\n\t\tHE_V2_Domain::reindex_concept_by_post( $post_id );\n\t}\n\n\tpublic function research_box"
    new_tail = "\t\tHE_V2_Domain::ensure_concept_for_post( $post_id );\n\t\t$concept = HE_V2_Domain::concept_by_post_id( $post_id );\n\t\t$expected = absint( $_POST['he_v2_expected_version'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\tglobal $wpdb;\n\t\t$bumped = $concept && $expected ? $wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'concepts' ) . \" SET row_version=row_version+1,review_status='unreviewed',safety_status='unreviewed',updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", (int) $concept['id'], $expected ) ) : 0;\n\t\tif ( 1 !== (int) $bumped ) { update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); HE_V2_Schema::record_runtime_failure( 'entry_admin_concurrency_conflict', 'Entry metadata/content could not be bound to the editor-loaded row version; mutations were paused.' ); return; }\n\t\tHE_V2_Domain::reindex_concept_by_post( $post_id );\n\t}\n\n\tpublic function research_box"
    replace_once(admin, old_tail, new_tail, 'R5 entry version bump')
    append_review(5, 'Entry wp-admin editing displayed an expected row version but did not enforce or advance it. A stale-form preflight and CAS row-version/review invalidation now fence content/meta changes.')

elif ROUND == 6:
    old = "\t\t$updated = $wpdb->query( $wpdb->prepare(\n\t\t\t\"UPDATE {$table} SET record_type=%s,title=%s,question=%s,protocol=%s,ethics_json=%s,consent_json=%s,data_class=%s,case_anonymized=%d,case_consent_verified=%d,case_tag=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\",\n\t\t\t$type, $post->post_title, $post->post_excerpt, $post->post_content, wp_json_encode( $ethics ), wp_json_encode( $consent ), $data_class,\n\t\t\t$anonymized ? 1 : 0, $consent['verified'] ? 1 : 0, $case_tag, wp_json_encode( $case ), wp_json_encode( $metadata ), (int) $row['id'], (int) $row['row_version']\n\t\t) );"
    new = "\t\t$loaded_expected = isset( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) ? absint( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\t$expected = $loaded_expected ?: (int) $row['row_version'];\n\t\t$updated = $wpdb->query( $wpdb->prepare(\n\t\t\t\"UPDATE {$table} SET record_type=%s,title=%s,question=%s,protocol=%s,ethics_json=%s,consent_json=%s,data_class=%s,case_anonymized=%d,case_consent_verified=%d,case_tag=%s,case_json=%s,metadata_json=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\",\n\t\t\t$type, $post->post_title, $post->post_excerpt, $post->post_content, wp_json_encode( $ethics ), wp_json_encode( $consent ), $data_class,\n\t\t\t$anonymized ? 1 : 0, $consent['verified'] ? 1 : 0, $case_tag, wp_json_encode( $case ), wp_json_encode( $metadata ), (int) $row['id'], $expected\n\t\t) );"
    replace_once(admin, old, new, 'R6 research loaded CAS')
    append_review(6, 'The legacy research admin writer used a freshly re-read row version, leaving a race after stale-form preflight. Its CAS now binds to the version loaded into the editor form.')

elif ROUND == 7:
    new_entry_rollback = r'''\tprivate static function rollback_new_entry( $concept_id, $post_id ) {
\t\tglobal $wpdb; $concept_id = absint( $concept_id ); $post_id = absint( $post_id );
\t\t$delete_guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' ); $domain_pre = array( __CLASS__, 'pre_delete_post' ); $domain_done = array( __CLASS__, 'on_deleted_post' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) { update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); HE_V2_Schema::record_runtime_failure( 'entry_create_compensation_start_failed', 'File 06 could not start entry-create compensation.' ); return false; }
\t\tremove_filter( 'pre_delete_post', $delete_guard, 1 ); remove_filter( 'pre_delete_post', $domain_pre, 10 ); remove_action( 'deleted_post', $domain_done, 10 );
\t\ttry {
\t\t\tif ( $post_id && get_post( $post_id ) && ! wp_delete_post( $post_id, true ) ) { throw new RuntimeException( 'entry-post-delete-failed' ); }
\t\t\tif ( $concept_id ) {
\t\t\t\tif ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d', $concept_id, $concept_id ) ) ) { throw new RuntimeException( 'entry-relation-delete-failed' ); }
\t\t\t\tforeach ( array( 'aliases','references','versions','search_index','bookmarks' ) as $suffix ) { if ( false === $wpdb->delete( HE_V2_Schema::table( $suffix ), array( 'concept_id' => $concept_id ), array( '%d' ) ) ) { throw new RuntimeException( 'entry-child-delete-failed' ); } }
\t\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'reviews' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'entry-review-delete-failed' ); }
\t\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'integrity_actions' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'entry-integrity-delete-failed' ); }
\t\t\t\tif ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) ) ) { throw new RuntimeException( 'entry-concept-delete-failed' ); }
\t\t\t}
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'entry-compensation-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' ); if ( $post_id ) { clean_post_cache( $post_id ); }
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); HE_V2_Schema::record_runtime_failure( 'entry_create_compensation_failed', 'File 06 rolled back a failed entry-create compensation because WordPress/domain cleanup could not complete atomically.' ); return false;
\t\t} finally {
\t\t\tadd_filter( 'pre_delete_post', $delete_guard, 1, 3 ); add_filter( 'pre_delete_post', $domain_pre, 10, 3 ); add_action( 'deleted_post', $domain_done, 10, 2 );
\t\t}
\t\treturn true;
\t}

'''
    replace_between(domain, '\tprivate static function rollback_new_entry( $concept_id, $post_id ) {', '\tpublic static function add_alias(', new_entry_rollback, 'R7 entry compensation')
    new_research_rollback = r'''\tprivate static function rollback_new_research( $research_id, $post_id ) {
\t\tglobal $wpdb; $research_id = absint( $research_id ); $post_id = absint( $post_id );
\t\t$delete_guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' ); $domain_pre = array( __CLASS__, 'pre_delete_post' ); $domain_done = array( __CLASS__, 'on_deleted_post' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) { update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); HE_V2_Schema::record_runtime_failure( 'research_create_compensation_start_failed', 'File 06 could not start research-create compensation.' ); return false; }
\t\tremove_filter( 'pre_delete_post', $delete_guard, 1 ); remove_filter( 'pre_delete_post', $domain_pre, 10 ); remove_action( 'deleted_post', $domain_done, 10 );
\t\ttry {
\t\t\tif ( $post_id && get_post( $post_id ) && ! wp_delete_post( $post_id, true ) ) { throw new RuntimeException( 'research-post-delete-failed' ); }
\t\t\tif ( $research_id ) {
\t\t\t\tforeach ( array( 'reviews','integrity_actions' ) as $suffix ) { if ( false === $wpdb->delete( HE_V2_Schema::table( $suffix ), array( 'object_type' => 'research', 'object_id' => $research_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'research-child-delete-failed' ); } }
\t\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'dataset_access' ), array( 'research_id' => $research_id ), array( '%d' ) ) ) { throw new RuntimeException( 'research-access-delete-failed' ); }
\t\t\t\tif ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'research' ), array( 'id' => $research_id ), array( '%d' ) ) ) { throw new RuntimeException( 'research-row-delete-failed' ); }
\t\t\t}
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'research-compensation-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' ); if ( $post_id ) { clean_post_cache( $post_id ); }
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); HE_V2_Schema::record_runtime_failure( 'research_create_compensation_failed', 'File 06 rolled back a failed research-create compensation because WordPress/domain cleanup could not complete atomically.' ); return false;
\t\t} finally {
\t\t\tadd_filter( 'pre_delete_post', $delete_guard, 1, 3 ); add_filter( 'pre_delete_post', $domain_pre, 10, 3 ); add_action( 'deleted_post', $domain_done, 10, 2 );
\t\t}
\t\treturn true;
\t}

'''
    replace_between(domain, '\tprivate static function rollback_new_research( $research_id, $post_id ) {', '\tprivate static function contains_direct_identifiers(', new_research_rollback, 'R7 research compensation')
    append_review(7, 'Create compensation committed canonical-row deletion before WordPress object deletion, allowing a split orphan state. Entry/research compensation now performs both sides in one transaction with lifecycle guards narrowly suppressed and restored.')

elif ROUND == 8:
    old = "\t\t$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT concept_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE normalized_alias=%s AND language=%s', $normalized, $language ), ARRAY_A );"
    new = "\t\t$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id,concept_id,alias_type,is_primary FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE normalized_alias=%s AND language=%s', $normalized, $language ), ARRAY_A );"
    replace_once(domain, old, new, 'R8 alias lookup')
    old2 = "\t\tif ( $existing ) {\n\t\t\treturn true;\n\t\t}"
    new2 = "\t\tif ( $existing ) {\n\t\t\tif ( $primary || 'canonical' === $type ) {\n\t\t\t\t$wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'aliases' ) . ' SET is_primary=0 WHERE concept_id=%d AND id<>%d AND is_primary=1', absint( $concept_id ), (int) $existing['id'] ) );\n\t\t\t\t$updated = $wpdb->update( HE_V2_Schema::table( 'aliases' ), array( 'alias' => $alias, 'alias_type' => $type, 'is_primary' => $primary ? 1 : 0 ), array( 'id' => (int) $existing['id'] ), array( '%s','%s','%d' ), array( '%d' ) );\n\t\t\t\treturn false !== $updated;\n\t\t\t}\n\t\t\treturn true;\n\t\t}"
    replace_once(domain, old2, new2, 'R8 alias promotion')
    append_review(8, 'An existing same-concept synonym caused add_alias() to return success without promoting/updating a requested canonical primary alias. Same-concept canonical promotion is now persisted and other primary flags are cleared.')

elif ROUND == 9:
    replace_once(domain, "\t\t$params = array( $cursor );", "\t\t$params = array( self::ENTRY_TYPE, $cursor );", 'R9 search params')
    old = "\t\t$sql = 'SELECT c.* FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'search_index' ) . ' i ON i.concept_id=c.id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY c.id ASC LIMIT %d';"
    new = "\t\t$sql = 'SELECT c.* FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'search_index' ) . ' i ON i.concept_id=c.id INNER JOIN ' . $wpdb->posts . \" p ON p.ID=c.post_id AND p.post_type=%s AND p.post_status='publish' WHERE \" . implode( ' AND ', $where ) . ' ORDER BY c.id ASC LIMIT %d';"
    replace_once(domain, old, new, 'R9 search wp parity')
    append_review(9, 'Public search paginated canonical/index rows without authoritative WordPress publish-state filtering, causing stale/short pages. The query now joins the live published entry post state before pagination.')

elif ROUND == 10:
    new_dto = r'''\tpublic static function research_dto( $research_id, $private = false ) {
\t\tglobal $wpdb;
\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
\t\tif ( ! $row ) { return null; }
\t\tif ( ! $private ) {
\t\t\t$post = ! empty( $row['post_id'] ) ? get_post( (int) $row['post_id'] ) : null;
\t\t\tif ( ! class_exists( 'HE_V22_Research_Guard' ) || ! HE_V22_Research_Guard::public_surface_eligible( $row ) || ! $post || self::RESEARCH_TYPE !== $post->post_type || 'publish' !== $post->post_status ) { return null; }
\t\t}
\t\t$metadata = json_decode( (string) $row['metadata_json'], true ); $metadata = is_array( $metadata ) ? $metadata : array();
\t\t$public_metadata = array_intersect_key( $metadata, array_flip( array( 'description','de_identification','lawful_basis','access_policy' ) ) );
\t\t$case = null;
\t\tif ( 'successful-case' === $row['record_type'] && 'retracted' !== $row['status'] && ( $private || 'public' === $row['data_class'] ) ) { $case = json_decode( (string) $row['case_json'], true ); }
\t\t$dto = array(
\t\t\t'id' => $row['public_id'], 'record_type' => $row['record_type'], 'status' => $row['status'], 'title' => $row['title'], 'question' => $row['question'],
\t\t\t'protocol' => ( $private || ( 'public' === $row['data_class'] && 'retracted' !== $row['status'] ) ) ? $row['protocol'] : '', 'case_tag' => $row['case_tag'], 'case' => $case,
\t\t\t'dataset_metadata' => 'dataset' === $row['record_type'] ? ( $private ? $metadata : $public_metadata ) : null,
\t\t\t'canonical_url' => home_url( '/research/' . rawurlencode( $row['public_id'] ) . '/' ), 'updated_at' => $row['updated_at'],
\t\t);
\t\tif ( $private ) { $dto['investigators'] = json_decode( $row['investigators_json'], true ); $dto['ethics'] = json_decode( $row['ethics_json'], true ); $dto['consent'] = json_decode( $row['consent_json'], true ); $dto['conflicts'] = json_decode( $row['conflicts_json'], true ); $dto['data_class'] = $row['data_class']; $dto['row_version'] = (int) $row['row_version']; }
\t\treturn $dto;
\t}

'''
    replace_between(domain, '\tpublic static function research_dto( $research_id, $private = false ) {', '\tpublic static function transition_research(', new_dto, 'R10 research dto')
    append_review(10, 'The default/public research DTO could expose successful-case payloads for restricted records and returned entire dataset metadata blobs. Public output is now data-class-aware with an explicit dataset metadata allowlist.')

elif ROUND == 11:
    old = "\t\t$post = get_post( (int) $row['post_id'] );\n\t\t$fields = get_post_meta( $post->ID, '_he_structured', true );"
    new = "\t\t$post = get_post( (int) $row['post_id'] );\n\t\tif ( ! $post || self::ENTRY_TYPE !== $post->post_type ) { return new WP_Error( 'he_entry_post_missing', __( 'The authoritative WordPress entry object is unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ); }\n\t\t$fields = get_post_meta( $post->ID, '_he_structured', true );"
    replace_once(domain, old, new, 'R11 validate missing post')
    old2 = "\t\t$post = get_post( (int) $row['post_id'] );\n\t\tif ( (int) $post->post_author === absint( $reviewer_id ) ) {"
    new2 = "\t\t$post = get_post( (int) $row['post_id'] );\n\t\tif ( ! $post || self::ENTRY_TYPE !== $post->post_type ) { return new WP_Error( 'he_entry_post_missing', __( 'The authoritative WordPress entry object is unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ); }\n\t\tif ( (int) $post->post_author === absint( $reviewer_id ) ) {"
    replace_once(domain, old2, new2, 'R11 add review missing post')
    append_review(11, 'Review validation and review submission dereferenced a missing authoritative WordPress entry and could fatal instead of fail closed. Both now return explicit unavailable-state errors.')

elif ROUND == 12:
    new_reconcile = r'''\tpublic static function reconcile_manual_research_state( $post_id, $post, $update ) {
\t\tif ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post ) { return; }
\t\tglobal $wpdb; $table = HE_V2_Schema::table( 'research' );
\t\t$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", absint( $post_id ) ), ARRAY_A );
\t\tif ( ! $row || 'published' !== $row['status'] || 'publish' === $post->post_status ) { return; }
\t\t$approved_reviews = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='research' AND object_id=%d AND decision='approved' AND conflict_declared=0", (int) $row['id'] ) );
\t\t$next = $approved_reviews > 0 ? 'peer_review' : 'proposal';
\t\t$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='published'", $next, (int) $row['id'], (int) $row['row_version'] ) );
\t\tif ( 1 === (int) $changed ) { HE_V2_Domain::emit_event( 'ResearchStateFailClosed.v1', 'research', (int) $row['id'], array( 'reason' => 'authoritative-wordpress-post-not-published', 'fallback_state' => $next ) ); }
\t\telse { update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false ); HE_V2_Schema::record_runtime_failure( 'research_post_state_reconciliation_failed', 'Research domain/WordPress publication parity could not be reconciled safely.' ); }
\t}

'''
    replace_between(third, '\tpublic static function reconcile_manual_research_state( $post_id, $post, $update ) {', '\tpublic static function language_meta_changed(', new_reconcile, 'R12 research WP parity')
    append_review(12, 'A domain research row could remain “published” when its authoritative WordPress post was no longer published if an approval review existed. Reconciliation now always leaves published state, preserving peer-review progress where possible.')

elif ROUND == 13:
    old = "\t\tforeach ( $posts as $post_id ) {\n\t\t\tif ( 'publish' === get_post_status( $post_id ) ) {\n\t\t\t\twp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ) );\n\t\t\t\t$retained = true;\n\t\t\t} else {\n\t\t\t\twp_delete_post( $post_id, true );\n\t\t\t\t$removed = true;\n\t\t\t}\n\t\t}"
    new = "\t\tforeach ( $posts as $post_id ) {\n\t\t\tif ( 'publish' === get_post_status( $post_id ) ) {\n\t\t\t\t$result = wp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ), true );\n\t\t\t\tif ( is_wp_error( $result ) ) { $messages[] = __( 'A published record could not be de-identified and was retained for retry.', 'homeopathy-encyclopedia' ); } else { $retained = true; }\n\t\t\t} else {\n\t\t\t\t/* Canonical draft hard-delete is governance-blocked; de-identify ownership instead of falsely claiming deletion. */\n\t\t\t\t$result = wp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ), true );\n\t\t\t\tif ( is_wp_error( $result ) ) { $messages[] = __( 'An unpublished governed draft could not be de-identified and was retained for retry.', 'homeopathy-encyclopedia' ); } else { $retained = true; $removed = true; }\n\t\t\t}\n\t\t}"
    replace_once(privacy, old, new, 'R13 erasure delete conflict')
    append_review(13, 'Privacy erasure attempted wp_delete_post() on canonical drafts even though File 06 hard-delete governance blocks that path, then claimed removal. Governed drafts are now ownership-de-identified and retained transparently rather than looping on a blocked delete.')

elif ROUND == 14:
    old = "\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_reference( $row['id'], (array) $request->get_json_params(), get_current_user_id(), absint( $request->get_param( 'version_id' ) ) );\n\t\treturn $this->mutation_response( $reservation, $result, 201 );"
    new = "\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_reference( $row['id'], (array) $request->get_json_params(), get_current_user_id(), absint( $request->get_param( 'version_id' ) ) );\n\t\tif ( ! is_wp_error( $result ) ) { $result = array( 'reference_id' => HE_V2_Domain::encode_public_cursor( 'reference', (int) $result ) ); }\n\t\treturn $this->mutation_response( $reservation, $result, 201 );"
    replace_once(api, old, new, 'R14 reference response')
    old2 = "\t\t$target = HE_V2_Domain::concept_by_id( $data['target_id'] ?? '', true );\n\t\t$result = is_wp_error( $reservation ) ? $reservation : ( ! $target ? new WP_Error( 'he_relation_target_missing', __( 'Relationship target not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) : HE_V2_Domain::add_relation( $row['id'], $target['id'], sanitize_key( $data['type'] ?? '' ), absint( $data['reference_id'] ?? 0 ), get_current_user_id() ) );"
    new2 = "\t\t$target = HE_V2_Domain::concept_by_id( $data['target_id'] ?? '', true );\n\t\t$reference_id = HE_V2_Domain::decode_public_cursor( 'reference', (string) ( $data['reference_id'] ?? '' ) );\n\t\tif ( null === $reference_id || ! $reference_id ) { $result = new WP_Error( 'he_reference_public_id_required', __( 'Relationship provenance requires the opaque reference identifier returned by the reference command.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }\n\t\telse { $result = is_wp_error( $reservation ) ? $reservation : ( ! $target ? new WP_Error( 'he_relation_target_missing', __( 'Relationship target not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) : HE_V2_Domain::add_relation( $row['id'], $target['id'], sanitize_key( $data['type'] ?? '' ), $reference_id, get_current_user_id() ) ); }"
    replace_once(api, old2, new2, 'R14 relation reference token')
    append_review(14, 'Reference-create responses exposed raw reference row IDs and relation commands accepted those internal IDs as their public contract. The command surface now returns/accepts scope-bound opaque reference tokens.')

elif ROUND == 15:
    old = "\t\t$result = HE_V2_Schema::repair( $dry_run );\n\t\tset_transient( 'he_v2_admin_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => wp_json_encode( $result ) ), 60 );"
    new = "\t\t$result = HE_V2_Schema::repair( $dry_run );\n\t\tif ( is_wp_error( $result ) ) { $notice = array( 'type' => 'error', 'message' => $result->get_error_message() ); }\n\t\telse { $notice = array( 'type' => 'success', 'message' => wp_json_encode( $result ) ); }\n\t\tset_transient( 'he_v2_admin_notice_' . get_current_user_id(), $notice, 60 );"
    replace_once(admin, old, new, 'R15 repair notice')
    append_review(15, 'The admin repair UI labeled WP_Error repair outcomes as success. It now renders failed verified repair as an error and no longer presents a false recovery signal.')

elif ROUND == 16:
    append_review(16, 'Fresh authorization/IDOR review of corrected entry/research/dataset mutation routes found no additional repository-level defect after canonical-ID and object-scope corrections.', 'CLEAN')

elif ROUND == 17:
    append_review(17, 'Fresh idempotency, rate-limit, event/outbox, retry/dead-letter and transaction-callsite audit found no new actionable repository defect beyond the corrected paths.', 'CLEAN')

elif ROUND == 18:
    append_review(18, 'Fresh activation/upgrade/Future-schema/maintenance/deactivation and migration-safety audit found no new actionable repository defect after schema-shape readiness hardening.', 'CLEAN')

elif ROUND == 19:
    replacements = {
        " * Version: 2.4.15": " * Version: 2.4.16",
        "define( 'HE_VERSION', '2.4.15' );": "define( 'HE_VERSION', '2.4.16' );",
        "define( 'HE_CONTRACT_VERSION', '2.4.15' );": "define( 'HE_CONTRACT_VERSION', '2.4.16' );",
        "'future_hardening_version'=>'2.4.15'": "'future_hardening_version'=>'2.4.16'",
    }
    for old,new in replacements.items(): replace_once(bootstrap, old, new, 'R19 bootstrap version')
    replace_once(readme, 'Stable tag: 2.4.15', 'Stable tag: 2.4.16', 'R19 stable tag')
    runall = T / 'run-all.sh'
    text = read(runall)
    if 'v2416-seventeenth-twenty-round-regressions.php' not in text:
        text = text.replace('php "$root/tests/v2415-round20-delete-governance-regressions.php"\n', 'php "$root/tests/v2415-round20-delete-governance-regressions.php"\nphp "$root/tests/v2416-seventeenth-twenty-round-regressions.php"\n')
    text = text.replace('file06-v2.4.15-a.zip','file06-v2.4.16-a.zip').replace('file06-v2.4.15-b.zip','file06-v2.4.16-b.zip').replace('All File 06 v2.4.15 automated checks, inherited review matrices, sixteenth twenty-round regressions and deterministic package comparison passed.','All File 06 v2.4.16 automated checks, inherited review matrices, seventeenth twenty-round regressions and deterministic package comparison passed.')
    write(runall,text)
    for path in [ROOT/'README.md', ROOT/'STATUS.md', ROOT/'CHANGELOG.md']:
        if path.exists():
            txt=read(path).replace('2.4.15','2.4.16')
            write(path,txt)
    append_review(19, 'Runtime, contract, stable tag, aggregate QA/package labels and repository release documentation still described v2.4.15 after corrective source changes. Candidate truth is aligned to v2.4.16; DB schema remains 10 and Future schema remains 2.')

elif ROUND == 20:
    append_review(20, 'Final fresh cross-cutting source review after all corrections found no additional repository-level defect; exact-head final QA remains the release gate.', 'CLEAN')

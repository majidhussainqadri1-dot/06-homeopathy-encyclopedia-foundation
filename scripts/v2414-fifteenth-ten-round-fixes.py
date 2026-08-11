#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'homeopathy-encyclopedia'
T=ROOT/'tests'
D=ROOT/'docs'
round_no=int(sys.argv[1]) if len(sys.argv)>1 else 0
if round_no not in range(1,11):
    raise SystemExit('round must be 1..10')

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
public=P/'includes/class-he-v2-public.php'
browse=P/'includes/class-he-v242-research-browse.php'
bootstrap=P/'homeopathy-encyclopedia.php'
readme=P/'readme.txt'
runall=T/'run-all.sh'
hist=T/'v2413-fourteenth-ten-round-regressions.php'

if round_no==1:
    pat=r"\tpublic static function on_delete_post\( \$post_id \) \{.*?\n\t\}\n\n\tpublic static function ensure_concept_for_post"
    repl="""\tpublic static function on_delete_post( $post_id ) {
\t\tglobal $wpdb;
\t\t$post_type = get_post_type( $post_id );
\t\tif ( self::ENTRY_TYPE === $post_type ) {
\t\t\t$table = HE_V2_Schema::table( 'concepts' );
\t\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT id,row_version FROM {$table} WHERE post_id=%d\", $post_id ), ARRAY_A );
\t\t\tif ( $row ) {
\t\t\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='archived',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", (int) $row['id'], (int) $row['row_version'] ) );
\t\t\t\t$index_deleted = $wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $row['id'] ), array( '%d' ) );
\t\t\t\tif ( 1 !== (int) $updated || false === $index_deleted ) {
\t\t\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_delete_lifecycle_failed', 'File 06 could not persist the canonical archive/search lifecycle before a WordPress entry deletion.' );
\t\t\t\t\treturn;
\t\t\t\t}
\t\t\t\tself::emit_event( 'EncyclopediaEntryArchived.v1', 'concept', (int) $row['id'], array( 'post_id' => $post_id, 'reason' => 'wordpress-hard-delete' ) );
\t\t\t}
\t\t\treturn;
\t\t}
\t\tif ( self::RESEARCH_TYPE === $post_type ) {
\t\t\t$table = HE_V2_Schema::table( 'research' );
\t\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT id,row_version,status,record_type FROM {$table} WHERE post_id=%d\", $post_id ), ARRAY_A );
\t\t\tif ( $row ) {
\t\t\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='retracted',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", (int) $row['id'], (int) $row['row_version'] ) );
\t\t\t\tif ( 1 !== (int) $updated ) {
\t\t\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\t\t\tHE_V2_Schema::record_runtime_failure( 'research_delete_lifecycle_failed', 'File 06 could not persist a research retraction tombstone before the WordPress research object was deleted.' );
\t\t\t\t\treturn;
\t\t\t\t}
\t\t\t\tself::emit_event( 'ResearchRecordRetracted.v1', 'research', (int) $row['id'], array( 'record_type' => $row['record_type'], 'reason' => 'wordpress-hard-delete' ) );
\t\t\t}
\t\t}
\t}

\tpublic static function ensure_concept_for_post"""
    regex_once(domain,pat,repl,'R1 deletion lifecycle parity')

elif round_no==2:
    old="""\t\twp_set_object_terms( $post_id, array( $type ), self::TAX_TYPE, false );
\t\twp_set_object_terms( $post_id, array( $system ), self::TAX_SYSTEM, false );
\t\tupdate_post_meta( $post_id, '_he_language', $language );
\t\tupdate_post_meta( $post_id, '_he_structured', self::sanitize_structured( $data['fields'] ?? array() ) );
\t\t$concept_id = self::ensure_concept_for_post( $post_id );
"""
    new="""\t\t$structured = self::sanitize_structured( $data['fields'] ?? array() );
\t\t$type_result = wp_set_object_terms( $post_id, array( $type ), self::TAX_TYPE, false );
\t\t$system_result = wp_set_object_terms( $post_id, array( $system ), self::TAX_SYSTEM, false );
\t\tupdate_post_meta( $post_id, '_he_language', $language );
\t\tupdate_post_meta( $post_id, '_he_structured', $structured );
\t\t$persisted_structured = get_post_meta( $post_id, '_he_structured', true );
\t\tif ( is_wp_error( $type_result ) || is_wp_error( $system_result ) || self::taxonomy_slug( $post_id, self::TAX_TYPE ) !== $type || self::taxonomy_slug( $post_id, self::TAX_SYSTEM ) !== $system || (string) get_post_meta( $post_id, '_he_language', true ) !== (string) $language || $persisted_structured !== $structured ) {
\t\t\t$concept_id = self::ensure_concept_for_post( $post_id );
\t\t\tif ( $concept_id ) { self::rollback_new_entry( $concept_id, $post_id ); } else { wp_delete_post( $post_id, true ); }
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_create_projection_failed', 'File 06 could not verify taxonomy/language/structured entry state before canonical creation completed.' );
\t\t\treturn new WP_Error( 'he_entry_write_failed', __( 'Entry draft metadata could not be saved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t}
\t\t$concept_id = self::ensure_concept_for_post( $post_id );
"""
    replace_once(domain,old,new,'R2 entry create persistence verification')

elif round_no==3:
    pat=r"\tprivate static function rollback_new_entry\( \$concept_id, \$post_id \) \{.*?\n\t\}\n\n\tpublic static function add_alias"
    repl="""\tprivate static function rollback_new_entry( $concept_id, $post_id ) {
\t\tglobal $wpdb;
\t\t$concept_id = absint( $concept_id );
\t\t$post_id = absint( $post_id );
\t\t$ok = false;
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_create_compensation_start_failed', 'File 06 could not start entry-create compensation safely.' );
\t\t\treturn false;
\t\t}
\t\ttry {
\t\t\tforeach ( array( 'aliases', 'references', 'relations', 'reviews', 'versions', 'search_index' ) as $suffix ) {
\t\t\t\t$table = HE_V2_Schema::table( $suffix );
\t\t\t\tif ( 'relations' === $suffix ) {
\t\t\t\t\t$result = $wpdb->query( $wpdb->prepare( \"DELETE FROM {$table} WHERE source_concept_id=%d OR target_concept_id=%d\", $concept_id, $concept_id ) );
\t\t\t\t} elseif ( 'reviews' === $suffix ) {
\t\t\t\t\t$result = $wpdb->delete( $table, array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s', '%d' ) );
\t\t\t\t} else {
\t\t\t\t\t$result = $wpdb->delete( $table, array( 'concept_id' => $concept_id ), array( '%d' ) );
\t\t\t\t}
\t\t\t\tif ( false === $result ) { throw new RuntimeException( 'entry-child-delete-failed-' . $suffix ); }
\t\t\t}
\t\t\t$deleted = $wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) );
\t\t\tif ( 1 !== (int) $deleted ) { throw new RuntimeException( 'entry-concept-delete-failed' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'entry-create-compensation-commit-failed' ); }
\t\t\t$ok = true;
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_create_compensation_failed', 'File 06 could not fully compensate a failed entry create operation; mutations were paused.' );
\t\t\treturn false;
\t\t}
\t\tif ( $post_id && get_post( $post_id ) && ! wp_delete_post( $post_id, true ) ) {
\t\t\t$ok = false;
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_create_post_compensation_failed', 'File 06 removed the failed canonical entry graph but could not remove its WordPress draft; mutations were paused.' );
\t\t}
\t\treturn $ok;
\t}

\tpublic static function add_alias"""
    regex_once(domain,pat,repl,'R3 entry create compensation reliability')

elif round_no==4:
    old="""\t\t\tif ( ! $row ) {
\t\t\t\t$normalized = self::normalize( $value );
\t\t\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT c.* FROM {$table} c INNER JOIN \" . HE_V2_Schema::table( 'aliases' ) . \" a ON a.concept_id=c.id WHERE a.normalized_alias=%s LIMIT 1\", $normalized ), ARRAY_A );
\t\t\t}
"""
    new="""\t\t\tif ( ! $row ) {
\t\t\t\t$normalized = self::normalize( $value );
\t\t\t\t$matches = $wpdb->get_results( $wpdb->prepare( \"SELECT DISTINCT c.* FROM {$table} c INNER JOIN \" . HE_V2_Schema::table( 'aliases' ) . \" a ON a.concept_id=c.id WHERE a.normalized_alias=%s ORDER BY c.id ASC LIMIT 2\", $normalized ), ARRAY_A );
\t\t\t\tif ( is_array( $matches ) && 1 === count( $matches ) ) {
\t\t\t\t\t$row = $matches[0];
\t\t\t\t} elseif ( is_array( $matches ) && count( $matches ) > 1 ) {
\t\t\t\t\treturn null;
\t\t\t\t}
\t\t\t}
"""
    replace_once(domain,old,new,'R4 ambiguous alias fail closed')

elif round_no==5:
    old="""\t\tif ( ! $action ) {
\t\t\treturn new WP_Error( 'he_not_found', __( 'Integrity action not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status IN ('submitted','triaged','under_review','accepted')\", absint( $actor_id ), $action['id'], absint( $expected_version ) ) );
"""
    new="""\t\tif ( ! $action ) {
\t\t\treturn new WP_Error( 'he_not_found', __( 'Integrity action not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\tif ( 'accepted' !== $action['status'] ) {
\t\t\treturn new WP_Error( 'he_integrity_not_accepted', __( 'Only an accepted integrity action may be applied.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\tif ( ! in_array( $action['action_type'], array( 'correction', 'retraction' ), true ) ) {
\t\t\treturn new WP_Error( 'he_integrity_apply_unsupported', __( 'Merge and appeal actions must use their dedicated governed workflows.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t}
\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='accepted'\", absint( $actor_id ), $action['id'], absint( $expected_version ) ) );
"""
    replace_once(domain,old,new,'R5 accepted-only integrity apply')

elif round_no==6:
    pat=r"\tpublic static function apply_integrity_action\( \$action_id, \$expected_version, \$actor_id \) \{.*?\n\t\}\n\n\tpublic static function add_relation"
    repl="""\tpublic static function apply_integrity_action( $action_id, $expected_version, $actor_id ) {
\t\tglobal $wpdb;
\t\t$table = HE_V2_Schema::table( 'integrity_actions' );
\t\t$concepts = HE_V2_Schema::table( 'concepts' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'integrity_apply_transaction_start_failed', 'File 06 could not start the integrity-apply transaction.' );
\t\t\treturn new WP_Error( 'he_integrity_apply_failed', __( 'The integrity action could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\t$event = null;
\t\t$concept_id = 0;
\t\ttry {
\t\t\t$action = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d FOR UPDATE\", absint( $action_id ) ), ARRAY_A );
\t\t\tif ( ! $action ) { throw new RuntimeException( 'integrity-not-found' ); }
\t\t\tif ( (int) $action['row_version'] !== absint( $expected_version ) ) { throw new RuntimeException( 'integrity-version-conflict' ); }
\t\t\tif ( 'accepted' !== $action['status'] ) { throw new RuntimeException( 'integrity-not-accepted' ); }
\t\t\tif ( ! in_array( $action['action_type'], array( 'correction', 'retraction' ), true ) ) { throw new RuntimeException( 'integrity-unsupported-type' ); }
\t\t\t$concept = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$concepts} WHERE id=%d FOR UPDATE\", (int) $action['object_id'] ), ARRAY_A );
\t\t\tif ( ! $concept ) { throw new RuntimeException( 'concept-not-found' ); }
\t\t\t$concept_id = (int) $concept['id'];
\t\t\tif ( 'retraction' === $action['action_type'] ) {
\t\t\t\t$changed = $wpdb->query( $wpdb->prepare( \"UPDATE {$concepts} SET status='retracted',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $concept_id, (int) $concept['row_version'] ) );
\t\t\t\tif ( 1 !== (int) $changed ) { throw new RuntimeException( 'concept-version-conflict' ); }
\t\t\t\t$event = array( 'EncyclopediaEntryRetracted.v1', array( 'reason' => $action['reason'], 'replacement_id' => $action['replacement_object_id'] ) );
\t\t\t} else {
\t\t\t\t$version_id = self::snapshot_version( $concept_id, $action['reason'], 'corrected', $actor_id );
\t\t\t\tif ( ! $version_id ) { throw new RuntimeException( 'correction-snapshot-failed' ); }
\t\t\t\t$changed = $wpdb->query( $wpdb->prepare( \"UPDATE {$concepts} SET status='published',current_version=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d\", $version_id, $concept_id, (int) $concept['row_version'] ) );
\t\t\t\tif ( 1 !== (int) $changed ) { throw new RuntimeException( 'concept-version-conflict' ); }
\t\t\t\t$event = array( 'EncyclopediaEntryCorrected.v1', array( 'version_id' => $version_id, 'reason' => $action['reason'] ) );
\t\t\t}
\t\t\t$applied = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='accepted'\", absint( $actor_id ), (int) $action['id'], (int) $action['row_version'] ) );
\t\t\tif ( 1 !== (int) $applied ) { throw new RuntimeException( 'integrity-version-conflict' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'integrity-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t$map = array(
\t\t\t\t'integrity-not-found' => array( 'he_not_found', 404 ),
\t\t\t\t'concept-not-found' => array( 'he_not_found', 404 ),
\t\t\t\t'integrity-version-conflict' => array( 'he_version_conflict', 409 ),
\t\t\t\t'concept-version-conflict' => array( 'he_version_conflict', 409 ),
\t\t\t\t'integrity-not-accepted' => array( 'he_integrity_not_accepted', 409 ),
\t\t\t\t'integrity-unsupported-type' => array( 'he_integrity_apply_unsupported', 409 ),
\t\t\t);
\t\t\tlist( $code, $status ) = $map[ $error->getMessage() ] ?? array( 'he_integrity_apply_failed', 503 );
\t\t\tif ( 503 === $status ) { HE_V2_Schema::record_runtime_failure( 'integrity_apply_atomic_failed', 'File 06 rolled back an integrity action because its canonical mutation or transaction commit could not complete atomically.' ); }
\t\t\treturn new WP_Error( $code, __( 'The integrity action could not be applied safely.', 'homeopathy-encyclopedia' ), array( 'status' => $status ) );
\t\t}
\t\tif ( $event ) { self::emit_event( $event[0], 'concept', $concept_id, $event[1] ); }
\t\tself::reindex_concept( $concept_id );
\t\treturn true;
\t}

\tpublic static function add_relation"""
    regex_once(domain,pat,repl,'R6 atomic integrity apply')

elif round_no==7:
    replace_once(api,"'/datasets/(?P<id>\\d+)/access'","'/datasets/(?P<id>[A-Za-z0-9-]+)/access'",'R7 canonical dataset request route')
    replace_once(api,"HE_V2_Domain::request_dataset_access( absint( $request['id'] ),","HE_V2_Domain::request_dataset_access( sanitize_text_field( $request['id'] ),",'R7 canonical dataset request identifier')
    pat=r"\tpublic static function request_dataset_access\( \$research_id, \$purpose, \$lawful_basis, \$requester_id \) \{.*?\n\t\}\n\n\tpublic static function approve_dataset_access\( \$access_id, \$expires_at, \$actor_id \) \{.*?\n\t\}\n\n\tpublic static function search"
    repl="""\tpublic static function request_dataset_access( $research_identifier, $purpose, $lawful_basis, $requester_id ) {
\t\tglobal $wpdb;
\t\t$research_table = HE_V2_Schema::table( 'research' );
\t\tif ( is_numeric( $research_identifier ) ) {
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$research_table} WHERE id=%d\", absint( $research_identifier ) ), ARRAY_A );
\t\t} else {
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$research_table} WHERE public_id=%s\", sanitize_text_field( (string) $research_identifier ) ), ARRAY_A );
\t\t}
\t\t$post = $research && ! empty( $research['post_id'] ) ? get_post( (int) $research['post_id'] ) : null;
\t\tif ( ! $research || 'dataset' !== $research['record_type'] || ! in_array( $research['status'], array( 'published','corrected' ), true ) || ! $post || self::RESEARCH_TYPE !== $post->post_type || 'publish' !== $post->post_status || ! class_exists( 'HE_V22_Research_Guard' ) || ! HE_V22_Research_Guard::public_surface_eligible( $research ) ) {
\t\t\treturn new WP_Error( 'he_dataset_not_found', __( 'Dataset metadata could not be found or is not currently eligible for access requests.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\tif ( ! trim( $purpose ) || ! trim( $lawful_basis ) ) {
\t\t\treturn new WP_Error( 'he_dataset_purpose_required', __( 'Purpose and lawful basis are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\t$table = HE_V2_Schema::table( 'dataset_access' );
\t\t$requester_id = absint( $requester_id );
\t\t$existing = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE research_id=%d AND requester_id=%d\", (int) $research['id'], $requester_id ), ARRAY_A );
\t\tif ( $existing && 'approved' === $existing['status'] && ! empty( $existing['expires_at'] ) && strtotime( $existing['expires_at'] . ' UTC' ) > time() ) { return true; }
\t\t$now = current_time( 'mysql', true );
\t\tif ( $existing ) {
\t\t\t$result = $wpdb->update( $table, array( 'purpose' => sanitize_textarea_field( $purpose ), 'lawful_basis' => sanitize_key( $lawful_basis ), 'status' => 'requested', 'approved_by' => 0, 'expires_at' => null, 'updated_at' => $now ), array( 'id' => (int) $existing['id'] ) );
\t\t} else {
\t\t\t$result = $wpdb->insert( $table, array( 'research_id' => (int) $research['id'], 'requester_id' => $requester_id, 'purpose' => sanitize_textarea_field( $purpose ), 'lawful_basis' => sanitize_key( $lawful_basis ), 'status' => 'requested', 'created_at' => $now, 'updated_at' => $now ) );
\t\t}
\t\tif ( false === $result ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'dataset_access_request_write_failed', 'File 06 could not persist a governed dataset-access request.' );
\t\t\treturn new WP_Error( 'he_dataset_access_write_failed', __( 'Dataset access request could not be saved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\treturn true;
\t}

\tpublic static function approve_dataset_access( $access_id, $expires_at, $actor_id ) {
\t\tglobal $wpdb;
\t\t$timestamp = strtotime( $expires_at );
\t\tif ( ! $timestamp || $timestamp <= time() || $timestamp > time() + YEAR_IN_SECONDS ) {
\t\t\treturn new WP_Error( 'he_invalid_expiry', __( 'A future access expiry within one year is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\treturn new WP_Error( 'he_dataset_access_write_failed', __( 'Dataset access approval could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\ttry {
\t\t\t$table = HE_V2_Schema::table( 'dataset_access' );
\t\t\t$research_table = HE_V2_Schema::table( 'research' );
\t\t\t$access = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$table} WHERE id=%d FOR UPDATE\", absint( $access_id ) ), ARRAY_A );
\t\t\tif ( ! $access ) { throw new RuntimeException( 'dataset-access-not-found' ); }
\t\t\tif ( 'requested' !== $access['status'] ) { throw new RuntimeException( 'dataset-access-state-conflict' ); }
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$research_table} WHERE id=%d FOR UPDATE\", (int) $access['research_id'] ), ARRAY_A );
\t\t\t$post = $research && ! empty( $research['post_id'] ) ? get_post( (int) $research['post_id'] ) : null;
\t\t\tif ( ! $research || 'dataset' !== $research['record_type'] || ! in_array( $research['status'], array( 'published','corrected' ), true ) || ! $post || self::RESEARCH_TYPE !== $post->post_type || 'publish' !== $post->post_status || ! HE_V22_Research_Guard::public_surface_eligible( $research ) ) { throw new RuntimeException( 'dataset-not-eligible' ); }
\t\t\t$result = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='approved',approved_by=%d,expires_at=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='requested'\", absint( $actor_id ), gmdate( 'Y-m-d H:i:s', $timestamp ), (int) $access['id'] ) );
\t\t\tif ( 1 !== (int) $result ) { throw new RuntimeException( 'dataset-access-state-conflict' ); }
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'dataset-access-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tif ( 'dataset-access-not-found' === $error->getMessage() || 'dataset-not-eligible' === $error->getMessage() ) { return new WP_Error( 'he_dataset_not_found', __( 'Dataset access record or governed dataset is unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t\tif ( 'dataset-access-state-conflict' === $error->getMessage() ) { return new WP_Error( 'he_dataset_access_conflict', __( 'Dataset access state changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ); }
\t\t\tHE_V2_Schema::record_runtime_failure( 'dataset_access_approval_failed', 'File 06 rolled back a dataset-access approval because persistence or transaction commit failed.' );
\t\t\treturn new WP_Error( 'he_dataset_access_write_failed', __( 'Dataset access approval could not be completed safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\treturn true;
\t}

\tpublic static function search"""
    regex_once(domain,pat,repl,'R7 dataset access governed persistence')

elif round_no==8:
    pat=r"\tpublic static function graph\( \$concept_id, \$depth = 1, \$limit = 50 \) \{.*?\n\t\}\n\n\tpublic static function merge_concepts"
    repl="""\tpublic static function graph( $concept_id, $depth = 1, $limit = 50 ) {
\t\tglobal $wpdb;
\t\t$depth = min( 2, max( 1, absint( $depth ) ) );
\t\t$limit = min( 100, max( 1, absint( $limit ) ) );
\t\t$visited = array();
\t\t$queue = array( array( absint( $concept_id ), 0 ) );
\t\t$nodes = array();
\t\t$edges = array();
\t\twhile ( $queue && count( $edges ) < $limit ) {
\t\t\tlist( $current, $level ) = array_shift( $queue );
\t\t\tif ( isset( $visited[ $current ] ) || $level > $depth ) { continue; }
\t\t\t$visited[ $current ] = true;
\t\t\t$row = self::concept_by_id( $current );
\t\t\tif ( ! $row ) { continue; }
\t\t\t$dto = self::public_dto( $row );
\t\t\tif ( ! $dto ) { continue; }
\t\t\t$nodes[] = array( 'id' => $dto['id'], 'title' => $dto['title'], 'type' => $dto['type'], 'url' => $dto['canonical_url'] );
\t\t\tif ( $level >= $depth ) { continue; }
\t\t\t$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT r.* FROM ' . HE_V2_Schema::table( 'relations' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' sc ON sc.id=r.source_concept_id INNER JOIN ' . HE_V2_Schema::table( 'references' ) . \" ref ON ref.id=r.source_reference_id AND ref.concept_id=r.source_concept_id AND ref.version_id=sc.current_version WHERE r.status='active' AND sc.current_version>0 AND (r.source_concept_id=%d OR r.target_concept_id=%d) LIMIT %d\", $current, $current, $limit ), ARRAY_A );
\t\t\tforeach ( $rows as $edge ) {
\t\t\t\t$source = self::concept_by_id( (int) $edge['source_concept_id'] );
\t\t\t\t$target = self::concept_by_id( (int) $edge['target_concept_id'] );
\t\t\t\tif ( ! $source || ! $target ) { continue; }
\t\t\t\t$source_dto = self::public_dto( $source );
\t\t\t\t$target_dto = self::public_dto( $target );
\t\t\t\tif ( ! $source_dto || ! $target_dto ) { continue; }
\t\t\t\t$edges[] = array( 'source' => $source_dto['id'], 'target' => $target_dto['id'], 'type' => $edge['relation_type'], 'owner' => $edge['owner_file'], 'version' => (int) $edge['row_version'] );
\t\t\t\t$other = (int) $edge['source_concept_id'] === $current ? (int) $edge['target_concept_id'] : (int) $edge['source_concept_id'];
\t\t\t\t$queue[] = array( $other, $level + 1 );
\t\t\t\tif ( count( $edges ) >= $limit ) { break; }
\t\t\t}
\t\t}
\t\treturn array( 'nodes' => $nodes, 'edges' => $edges, 'bounded_depth' => $depth, 'bounded_limit' => $limit );
\t}

\tpublic static function merge_concepts"""
    regex_once(domain,pat,repl,'R8 public graph canonical ids')

elif round_no==9:
    old="""\t\tif ( ! $private && ( ! class_exists( 'HE_V22_Research_Guard' ) || ! HE_V22_Research_Guard::public_surface_eligible( $row ) ) ) {
\t\t\treturn null;
\t\t}
"""
    new="""\t\tif ( ! $private ) {
\t\t\t$post = ! empty( $row['post_id'] ) ? get_post( (int) $row['post_id'] ) : null;
\t\t\tif ( ! class_exists( 'HE_V22_Research_Guard' ) || ! HE_V22_Research_Guard::public_surface_eligible( $row ) || ! $post || self::RESEARCH_TYPE !== $post->post_type || 'publish' !== $post->post_status ) { return null; }
\t\t}
"""
    replace_once(domain,old,new,'R9 public research post-state parity')
    replace_once(domain,"'case' => 'successful-case' === $row['record_type'] ? json_decode( $row['case_json'], true ) : null,","'case' => 'successful-case' === $row['record_type'] && 'retracted' !== $row['status'] ? json_decode( $row['case_json'], true ) : null,",'R9 retracted case suppression')
    replace_once(domain,"\t\tif ( ! $private && 'public' !== $row['data_class'] ) {\n\t\t\t$dto['protocol'] = '';\n\t\t}\n","\t\tif ( ! $private && ( 'public' !== $row['data_class'] || 'retracted' === $row['status'] ) ) {\n\t\t\t$dto['protocol'] = '';\n\t\t}\n",'R9 retracted protocol suppression')
    oldb="""\t\tif ( 'successful-case' === $row['record_type'] ) {
\t\t\t$case = json_decode( (string) $row['case_json'], true );
\t\t\tif ( 'public' === $row['data_class'] ) {
\t\t\t\t$out['case'] = is_array( $case ) ? $case : array();
\t\t\t} else {
\t\t\t\t$out['case_details_restricted'] = true;
\t\t\t}
\t\t}
"""
    newb="""\t\tif ( 'successful-case' === $row['record_type'] ) {
\t\t\t$case = json_decode( (string) $row['case_json'], true );
\t\t\tif ( 'public' === $row['data_class'] && 'retracted' !== $row['status'] ) {
\t\t\t\t$out['case'] = is_array( $case ) ? $case : array();
\t\t\t} else {
\t\t\t\t$out['case_details_restricted'] = true;
\t\t\t}
\t\t}
"""
    replace_once(browse,oldb,newb,'R9 browse retracted case suppression')

elif round_no==10:
    anchor="""\tprivate function icon( $name ) {
"""
    helper="""\tprivate function structured_html( $value, $depth = 0 ) {
\t\tif ( $depth > 6 ) { return ''; }
\t\tif ( is_array( $value ) ) {
\t\t\t$items = array();
\t\t\tforeach ( $value as $key => $item ) {
\t\t\t\t$child = $this->structured_html( $item, $depth + 1 );
\t\t\t\tif ( '' === $child ) { continue; }
\t\t\t\t$label = is_int( $key ) ? '' : '<strong>' . esc_html( ucwords( str_replace( array( '_','-' ), ' ', (string) $key ) ) ) . ':</strong> ';
\t\t\t\t$items[] = '<li>' . $label . $child . '</li>';
\t\t\t}
\t\t\treturn $items ? '<ul class=\"he-v2__structured-list\">' . implode( '', $items ) . '</ul>' : '';
\t\t}
\t\tif ( is_bool( $value ) ) { return esc_html( $value ? __( 'Yes', 'homeopathy-encyclopedia' ) : __( 'No', 'homeopathy-encyclopedia' ) ); }
\t\tif ( null === $value || ! is_scalar( $value ) ) { return ''; }
\t\treturn wp_kses_post( wpautop( (string) $value ) );
\t}

"""
    replace_once(public,anchor,helper+anchor,'R10 nested structured renderer helper')
    replace_once(public,"<?php echo wp_kses_post( wpautop( $dto['fields']['red_flags'] ) ); ?>","<?php echo $this->structured_html( $dto['fields']['red_flags'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>",'R10 red flags nested renderer')
    replace_once(public,"<?php echo wp_kses_post( wpautop( $dto['fields']['emergency_boundary'] ) ); ?>","<?php echo $this->structured_html( $dto['fields']['emergency_boundary'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>",'R10 emergency nested renderer')
    replace_once(public,"<?php echo wp_kses_post( wpautop( is_array( $value ) ? implode( \"\\n\", $value ) : $value ) ); ?>","<?php echo $this->structured_html( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>",'R10 panel nested renderer')

    # Current release truth.
    s=read(bootstrap)
    for old,new in [(" * Version: 2.4.13"," * Version: 2.4.14"),("define( 'HE_VERSION', '2.4.13' );","define( 'HE_VERSION', '2.4.14' );"),("define( 'HE_CONTRACT_VERSION', '2.4.13' );","define( 'HE_CONTRACT_VERSION', '2.4.14' );"),("'future_hardening_version'=>'2.4.13'","'future_hardening_version'=>'2.4.14'")]:
        if old not in s: raise SystemExit(f'R10 bootstrap marker missing: {old}')
        s=s.replace(old,new,1)
    write(bootstrap,s)

    s=read(readme)
    s=s.replace('Stable tag: 2.4.13','Stable tag: 2.4.14',1)
    s=s.replace('The 2.4.13 candidate','The 2.4.14 candidate',1)
    marker='== Changelog ==\n\n'
    if marker not in s: raise SystemExit('R10 readme changelog marker missing')
    s=s.replace(marker,marker+"= 2.4.14 =\n* Fifteenth ten-round corrective candidate: deletion lifecycle certainty, verified entry-create projections and compensation, ambiguous-alias fail-closed resolution, accepted/atomic integrity apply, governed canonical dataset-access requests, public graph UUID edges, retracted-research payload parity, nested structured rendering safety, and refreshed exact-head release truth.\n\n",1)
    write(readme,s)

    s=read(hist)
    s=s.replace("false!==strpos($bootstrap,' * Version: 2.4.13')&&false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.13' );\")&&false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.13' );\")&&false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.13'\")","preg_match('/ \\* Version: 2\\.4\\.(?:13|14)/',$bootstrap)&&preg_match(\"/HE_VERSION', '2\\.4\\.(?:13|14)/\",$bootstrap)&&preg_match(\"/HE_CONTRACT_VERSION', '2\\.4\\.(?:13|14)/\",$bootstrap)&&preg_match(\"/future_hardening_version'=>'2\\.4\\.(?:13|14)/\",$bootstrap)")
    s=s.replace("false!==strpos($readme,'Stable tag: 2.4.13')&&false!==strpos($runall,'v2413-fourteenth-ten-round-regressions.php')&&false!==strpos($runall,'file06-v2.4.13-a.zip')&&false!==strpos($runall,'All File 06 v2.4.13 automated checks')","preg_match('/Stable tag: 2\\.4\\.(?:13|14)/',$readme)&&false!==strpos($runall,'v2413-fourteenth-ten-round-regressions.php')&&preg_match('/file06-v2.4.(?:13|14)-a\\.zip/',$runall)&&preg_match('/All File 06 v2.4.(?:13|14) automated checks/',$runall)")
    write(hist,s)

    s=read(runall)
    if 'v2414-fifteenth-ten-round-regressions.php' not in s:
        s=s.replace('php "$root/tests/v2413-fourteenth-ten-round-regressions.php"\n','php "$root/tests/v2413-fourteenth-ten-round-regressions.php"\nphp "$root/tests/v2414-fifteenth-ten-round-regressions.php"\n',1)
    s=s.replace('file06-v2.4.13-a.zip','file06-v2.4.14-a.zip').replace('file06-v2.4.13-b.zip','file06-v2.4.14-b.zip')
    s=s.replace('All File 06 v2.4.13 automated checks, inherited review matrices, fourteenth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.14 automated checks, inherited review matrices, fifteenth ten-round regressions and deterministic package comparison passed.')
    write(runall,s)

    write(ROOT/'README.md',"""# File 06 — Homeopathy Encyclopedia 2.4.14

Fifteenth fresh ten-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.

## Candidate truth
- Branch: `audit/file-06-fifteenth-ten-round-v2.4.14`
- Plugin / contract: `2.4.14`
- Global schema: `10`
- V24 Future schema: `2`
- REST namespace: `sabri/v2/file-06`
- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`

Fifteenth-cycle corrections harden hard-delete lifecycle persistence, entry-create write verification and compensation, ambiguous alias resolution, accepted/atomic integrity application, governed dataset-access requests, canonical public graph identifiers, retracted research privacy parity, and recursive public structured rendering.

Run `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.
""")
    write(ROOT/'STATUS.md',"""# File 06 Status — 2.4.14 Fifteenth Fresh Ten-Round Candidate

| Status | Evidence |
|---|---|
| Specified | File 06 governing plan + applicable later platform governance |
| Coded | `audit/file-06-fifteenth-ten-round-v2.4.14` |
| Reviewed | 10 sequential review → immediate fix/retest rounds |
| Defect rounds | `1, 2, 3, 4, 5, 6, 7, 8, 9, 10` |
| Runtime | `2.4.14 / schema 10 / contract 2.4.14 / Future schema 2` |
| Automated QA | Authoritative only from completed final exact-head v2.4.14 workflow |
| Staging accepted | **No / unverified** |
| Live deployed | **No / unverified** |
| Operational | **No / unverified** |

Repository, staging and live are separate realities.
""")
    write(D/'FILE-06-v2.4.14-FIFTEENTH-TEN-ROUND-REVIEW.md',"""# File 06 v2.4.14 — Fifteenth Fresh Ten-Round Review

Repository-only corrective record. It does not establish staging or live deployment.

Defect rounds: 1–10.

1. Hard-delete lifecycle persistence and event ordering.
2. Entry-create taxonomy/language/structured persistence verification.
3. Entry-create compensation certainty and safe-mode escalation.
4. Ambiguous cross-language alias resolution fails closed.
5. Integrity application restricted to accepted correction/retraction actions.
6. Integrity application made transactionally atomic before event/reindex.
7. Dataset access uses governed eligible dataset state, canonical public request IDs and fail-closed persistence.
8. Public graph edges expose canonical public UUIDs, not internal database IDs.
9. Retracted research REST/browse payloads suppress protocol/case detail and require a live published WordPress object.
10. Nested structured public rendering is recursive/warning-safe; release truth aligned to 2.4.14.

Schema remains 10; Future schema remains 2. Staging, live and operational status remain unverified.
""")

print(f'File 06 v2.4.14 fifteenth cycle round {round_no} correction applied')

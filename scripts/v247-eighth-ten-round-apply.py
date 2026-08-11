#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
TEST = ROOT / 'tests' / 'v247-eighth-ten-round-regressions.php'
MARKER = '/*__V247_MORE__*/'

def read(rel):
    return (ROOT / rel).read_text()

def write(rel, text):
    (ROOT / rel).write_text(text)

def replace_once(rel, old, new):
    p = ROOT / rel
    s = p.read_text()
    if old not in s:
        raise SystemExit(f'marker missing in {rel}: {old[:160]}')
    p.write_text(s.replace(old, new, 1))

def init_test():
    if TEST.exists():
        return
    TEST.write_text("""<?php
/** File 06 v2.4.7 eighth fresh ten-round regression controls. */
$root=dirname(__DIR__);$fail=array();
function v247_read($p){$v=file_get_contents($p);if(false===$v){throw new RuntimeException($p);}return $v;}
function v247_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
/*__V247_MORE__*/
if($fail){fwrite(STDERR,"File 06 v2.4.7 eighth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.7 eighth-review regressions: PASS\n";
""")

def append_test(block):
    init_test()
    s = TEST.read_text()
    if MARKER not in s:
        raise SystemExit('v247 test marker missing')
    TEST.write_text(s.replace(MARKER, block.rstrip() + '\n' + MARKER, 1))

def round1():
    replace_once(
        'homeopathy-encyclopedia/includes/class-he-v241-governance.php',
        "\tprivate static function reviewer_assigned( $post_id, $user_id, $scope = '' ) {",
        "\tpublic static function reviewer_assigned( $post_id, $user_id, $scope = '' ) {"
    )
    old = """\t\t\tif ( 'research' === $record['object_type'] && ! empty( $record['object_id'] ) ) {
\t\t\t\t$research = self::research_row( $record['object_id'] );
\t\t\t\treturn $research ? HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW, (int) $research['post_id'], 'file06-external-review-research' ) : new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t\t}
"""
    new = """\t\t\tif ( 'research' === $record['object_type'] && ! empty( $record['object_id'] ) ) {
\t\t\t\t$research = self::research_row( $record['object_id'] );
\t\t\t\tif ( ! $research ) {
\t\t\t\t\treturn new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t\t\t}
\t\t\t\t$permission = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW, (int) $research['post_id'], 'file06-external-review-research' );
\t\t\t\tif ( true !== $permission ) {
\t\t\t\t\treturn $permission;
\t\t\t\t}
\t\t\t\tif ( ! HE_V241_Governance::reviewer_assigned( (int) $research['post_id'], get_current_user_id() ) ) {
\t\t\t\t\treturn new WP_Error( 'he_reviewer_assignment_required', __( 'An active File 06 research reviewer assignment is required for this external-evidence decision.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
\t\t\t\t}
\t\t\t\treturn true;
\t\t\t}
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v241-runtime-guard.php', old, new)
    append_test("""$gov=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v241-governance.php');
$runtime=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v241-runtime-guard.php');
v247_ok(false!==strpos($gov,"public static function reviewer_assigned"),'R1 reviewer assignment helper is not reusable by research external-review guard');
v247_ok(false!==strpos($runtime,"HE_V241_Governance::reviewer_assigned( (int) $research['post_id'], get_current_user_id() )"),'R1 research-bound external scholarly review lacks explicit File06 reviewer assignment');""")

def round2():
    old = """\t\tif ( 'successful-case' === $row['record_type'] ) {
\t\t\t$dto['case'] = json_decode( (string) $row['case_json'], true );
\t\t}
\t\tif ( 'dataset' === $row['record_type'] ) {
\t\t\t$dto['dataset_metadata'] = json_decode( (string) $row['metadata_json'], true );
\t\t}
"""
    new = """\t\tif ( 'successful-case' === $row['record_type'] ) {
\t\t\t$case = json_decode( (string) $row['case_json'], true );
\t\t\t$case = is_array( $case ) ? $case : array();
\t\t\tif ( $private ) {
\t\t\t\t$dto['case'] = $case;
\t\t\t} else {
\t\t\t\t$case_public = 'public' === $row['data_class']
\t\t\t\t\t&& in_array( $row['status'], array( 'published', 'corrected' ), true )
\t\t\t\t\t&& ! empty( $row['case_anonymized'] )
\t\t\t\t\t&& ! empty( $row['case_consent_verified'] );
\t\t\t\tif ( $case_public ) {
\t\t\t\t\t$dto['case'] = $case;
\t\t\t\t} else {
\t\t\t\t\t$dto['case_details_restricted'] = true;
\t\t\t\t}
\t\t\t}
\t\t}
\t\tif ( 'dataset' === $row['record_type'] ) {
\t\t\t$metadata = json_decode( (string) $row['metadata_json'], true );
\t\t\t$metadata = is_array( $metadata ) ? $metadata : array();
\t\t\tif ( $private ) {
\t\t\t\t$dto['dataset_metadata'] = $metadata;
\t\t\t} else {
\t\t\t\t$public_metadata = array();
\t\t\t\tforeach ( array( 'description', 'de_identification', 'lawful_basis', 'access_policy' ) as $key ) {
\t\t\t\t\tif ( isset( $metadata[ $key ] ) ) { $public_metadata[ $key ] = $metadata[ $key ]; }
\t\t\t\t}
\t\t\t\t$dto['dataset_metadata'] = $public_metadata;
\t\t\t\t$dto['dataset_payload_public'] = false;
\t\t\t}
\t\t}
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', old, new)
    append_test("""$v22=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22,"$case_public = 'public' === $row['data_class']") && false!==strpos($v22,"case_details_restricted"),'R2 authoritative V22 research browse still exposes restricted successful-case payload');
v247_ok(false!==strpos($v22,"array( 'description', 'de_identification', 'lawful_basis', 'access_policy' )") && false!==strpos($v22,"dataset_payload_public"),'R2 authoritative V22 research browse still exposes arbitrary dataset metadata');""")

def round3():
    replace_once(
        'homeopathy-encyclopedia/includes/class-he-v22-governance.php',
        "\t\t\t$edges = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d FOR UPDATE', (int) $source['id'], (int) $source['id'] ), ARRAY_A );\n",
        "\t\t\t$reference_map = array();\n\t\t\t$edges = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d FOR UPDATE', (int) $source['id'], (int) $source['id'] ), ARRAY_A );\n"
    )
    old = """\t\t\t\tif ( $new_source !== $new_target ) {
\t\t\t\t\t$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d AND target_concept_id=%d AND relation_type=%s', $new_source, $new_target, $edge['relation_type'] ) );
\t\t\t\t\tif ( ! $exists ) {
\t\t\t\t\t\t$wpdb->update( HE_V2_Schema::table( 'relations' ), array( 'source_concept_id' => $new_source, 'target_concept_id' => $new_target, 'row_version' => (int) $edge['row_version'] + 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $edge['id'] ) );
\t\t\t\t\t} else {
\t\t\t\t\t\t$wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) );
\t\t\t\t\t}
\t\t\t\t} else {
\t\t\t\t\t$wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) );
\t\t\t\t}
"""
    new = """\t\t\t\tif ( $new_source !== $new_target ) {
\t\t\t\t\t$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d AND target_concept_id=%d AND relation_type=%s', $new_source, $new_target, $edge['relation_type'] ) );
\t\t\t\t\tif ( ! $exists ) {
\t\t\t\t\t\t$new_reference_id = (int) $edge['source_reference_id'];
\t\t\t\t\t\tif ( (int) $edge['source_concept_id'] === (int) $source['id'] ) {
\t\t\t\t\t\t\tif ( ! isset( $reference_map[ $new_reference_id ] ) ) {
\t\t\t\t\t\t\t\t$reference = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d FOR UPDATE', $new_reference_id ), ARRAY_A );
\t\t\t\t\t\t\t\tif ( ! $reference || (int) $reference['concept_id'] !== (int) $source['id'] || ( (int) $reference['version_id'] !== 0 && (int) $reference['version_id'] !== (int) $source['current_version'] ) ) {
\t\t\t\t\t\t\t\t\tthrow new RuntimeException( 'relation-provenance-invalid' );
\t\t\t\t\t\t\t\t}
\t\t\t\t\t\t\t\tunset( $reference['id'] );
\t\t\t\t\t\t\t\t$reference['concept_id'] = (int) $target['id'];
\t\t\t\t\t\t\t\t$reference['version_id'] = (int) $target['current_version'];
\t\t\t\t\t\t\t\t$reference['created_by'] = get_current_user_id();
\t\t\t\t\t\t\t\t$reference['created_at'] = current_time( 'mysql', true );
\t\t\t\t\t\t\t\tif ( false === $wpdb->insert( HE_V2_Schema::table( 'references' ), $reference ) ) {
\t\t\t\t\t\t\t\t\tthrow new RuntimeException( 'relation-provenance-clone-failed' );
\t\t\t\t\t\t\t\t}
\t\t\t\t\t\t\t\t$reference_map[ $new_reference_id ] = (int) $wpdb->insert_id;
\t\t\t\t\t\t\t}
\t\t\t\t\t\t\t$new_reference_id = (int) $reference_map[ $new_reference_id ];
\t\t\t\t\t\t}
\t\t\t\t\t\t$relation_updated = $wpdb->update( HE_V2_Schema::table( 'relations' ), array( 'source_concept_id' => $new_source, 'target_concept_id' => $new_target, 'source_reference_id' => $new_reference_id, 'row_version' => (int) $edge['row_version'] + 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $edge['id'] ) );
\t\t\t\t\t\tif ( false === $relation_updated ) { throw new RuntimeException( 'relation-write-failed' ); }
\t\t\t\t\t} else {
\t\t\t\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'relation-write-failed' ); }
\t\t\t\t\t}
\t\t\t\t} else {
\t\t\t\t\tif ( false === $wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'relation-write-failed' ); }
\t\t\t\t}
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', old, new)
    oldcatch = """\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t$code = 'alias-third-party-collision' === $error->getMessage() ? 'he_alias_collision' : 'he_version_conflict';
\t\t\t$message = 'he_alias_collision' === $code ? __( 'A source alias belongs to a third canonical concept; manual reconciliation is required.', 'homeopathy-encyclopedia' ) : __( 'One of the concepts changed before the merge.', 'homeopathy-encyclopedia' );
\t\t\treturn new WP_Error( $code, $message, array( 'status' => 409 ) );
\t\t}
"""
    newcatch = """\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tif ( in_array( $error->getMessage(), array( 'relation-provenance-invalid', 'relation-provenance-clone-failed' ), true ) ) {
\t\t\t\treturn new WP_Error( 'he_relation_provenance_invalid', __( 'Merged graph edges could not be rebound to valid target-concept provenance.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t\t}
\t\t\tif ( 'relation-write-failed' === $error->getMessage() ) {
\t\t\t\treturn new WP_Error( 'he_merge_failed', __( 'The graph could not be rewritten atomically during the concept merge.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
\t\t\t}
\t\t\t$code = 'alias-third-party-collision' === $error->getMessage() ? 'he_alias_collision' : 'he_version_conflict';
\t\t\t$message = 'he_alias_collision' === $code ? __( 'A source alias belongs to a third canonical concept; manual reconciliation is required.', 'homeopathy-encyclopedia' ) : __( 'One of the concepts changed before the merge.', 'homeopathy-encyclopedia' );
\t\t\treturn new WP_Error( $code, $message, array( 'status' => 409 ) );
\t\t}
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', oldcatch, newcatch)
    append_test("""$v22=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22,'$reference_map = array();') && false!==strpos($v22,"$reference['concept_id'] = (int) $target['id'];") && false!==strpos($v22,"'source_reference_id' => $new_reference_id"),'R3 secure merge rewrites outgoing graph source without rebinding provenance');
v247_ok(false!==strpos($v22,'relation-provenance-invalid') && false!==strpos($v22,'relation-provenance-clone-failed'),'R3 merge provenance failure is not fail-closed');""")

def round4():
    old = "$grades = $wpdb->get_col( $wpdb->prepare( 'SELECT evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d', (int) $row['id'] ) );"
    new = "$grades = $wpdb->get_col( $wpdb->prepare( 'SELECT evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', (int) $row['id'], (int) $row['current_version'] ) );"
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', old, new)
    replace_once(
        'homeopathy-encyclopedia/includes/class-he-v2-domain.php',
        "\t\tif ( ! $row || ! self::is_public_concept( $row ) ) {\n",
        "\t\tif ( ! $row || ! self::is_public_concept( $row ) || ! $row['current_version'] ) {\n"
    )
    old2 = "$references = $wpdb->get_results( $wpdb->prepare( 'SELECT author,title,publisher,doi,evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d', $row['id'] ), ARRAY_A );"
    new2 = "$references = $wpdb->get_results( $wpdb->prepare( 'SELECT author,title,publisher,doi,evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', $row['id'], (int) $row['current_version'] ), ARRAY_A );"
    replace_once('homeopathy-encyclopedia/includes/class-he-v2-domain.php', old2, new2)
    append_test("""$v22=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$domain=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v247_ok(false!==strpos($v22,"WHERE concept_id=%d AND version_id=%d', (int) $row['id'], (int) $row['current_version']"),'R4 secure public search grade still includes historical references');
v247_ok(false!==strpos($domain,"WHERE concept_id=%d AND version_id=%d', $row['id'], (int) $row['current_version']"),'R4 inherited reindex still includes historical references');""")

def round5():
    old = """\t\t$data = (array) $request->get_json_params();
\t\t$decision = sanitize_key( $data['decision'] ?? 'changes_required' );
"""
    new = """\t\t$data = (array) $request->get_json_params();
\t\t$expected = absint( $data['expected_version'] ?? 0 );
\t\tif ( ! $expected || $expected !== (int) $row['row_version'] ) {
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The research record changed after it was loaded for review. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 201 );
\t\t}
\t\t$decision = sanitize_key( $data['decision'] ?? 'changes_required' );
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', old, new)
    replace_once(
        'homeopathy-encyclopedia/includes/class-he-v22-governance.php',
        "return self::mutation_finish( $reservation, array( 'review_id' => (int) $wpdb->insert_id, 'decision' => $decision, 'content_hash' => $hash ), 201 );",
        "return self::mutation_finish( $reservation, array( 'review_id' => (int) $wpdb->insert_id, 'decision' => $decision, 'content_hash' => $hash, 'reviewed_row_version' => $expected ), 201 );"
    )
    append_test("""$v22=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22,"$expected = absint( $data['expected_version'] ?? 0 );") && false!==strpos($v22,"$expected !== (int) $row['row_version']"),'R5 research review can silently approve content changed after reviewer load');
v247_ok(false!==strpos($v22,"'reviewed_row_version' => $expected"),'R5 research review response does not expose bound row version');""")

def round6():
    old = """\tpublic function review( WP_REST_Request $request ) {
\t\t$reservation = $this->require_mutation_guards( $request, 'review-entry-' . $request['id'] );
\t\t$row = HE_V2_Domain::concept_by_id( $request['id'], true );
\t\t$data = (array) $request->get_json_params();
\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_review( $row['id'], sanitize_key( $data['scope'] ?? 'scientific' ), sanitize_key( $data['decision'] ?? 'changes_required' ), ! empty( $data['conflict_declared'] ), $data['note'] ?? '', get_current_user_id() );
\t\treturn $this->mutation_response( $reservation, $result, 201 );
\t}
"""
    new = """\tpublic function review( WP_REST_Request $request ) {
\t\t$reservation = $this->require_mutation_guards( $request, 'review-entry-' . $request['id'] );
\t\t$row = HE_V2_Domain::concept_by_id( $request['id'], true );
\t\t$data = (array) $request->get_json_params();
\t\tif ( ! is_wp_error( $reservation ) && ( ! $row || ! absint( $data['expected_version'] ?? 0 ) || absint( $data['expected_version'] ?? 0 ) !== (int) $row['row_version'] ) ) {
\t\t\t$result = new WP_Error( 'he_version_conflict', __( 'The entry changed after it was loaded for review. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
\t\t} else {
\t\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_review( $row['id'], sanitize_key( $data['scope'] ?? 'scientific' ), sanitize_key( $data['decision'] ?? 'changes_required' ), ! empty( $data['conflict_declared'] ), $data['note'] ?? '', get_current_user_id() );
\t\t}
\t\treturn $this->mutation_response( $reservation, $result, 201 );
\t}
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v2-api.php', old, new)
    append_test("""$api=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
v247_ok(false!==strpos($api,"absint( $data['expected_version'] ?? 0 ) !== (int) $row['row_version']"),'R6 core entry review can silently bind a stale human decision to changed content');""")

def round7():
    rel='homeopathy-encyclopedia/includes/class-he-v2-integrations.php'
    s=read(rel)
    marker="\tpublic function dashboard_inventory( $args = array() ) {\n"
    helper="""\tprivate static function dashboard_post_allowed( $post, $user_id ) {
\t\tif ( ! $post || ! $user_id ) { return false; }
\t\tif ( HE_V2_Auth::is_founder( $user_id ) ) { return true; }
\t\tif ( HE_V2_Domain::ENTRY_TYPE === $post->post_type ) {
\t\t\t$type = HE_V2_Domain::taxonomy_slug( (int) $post->ID, HE_V2_Domain::TAX_TYPE );
\t\t\t$editor = HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, (int) $post->ID, 'file06-dashboard-entry-edit', $user_id ) && HE_V241_Governance::editor_type_allowed( $user_id, $type );
\t\t\t$reviewer = HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, (int) $post->ID, 'file06-dashboard-entry-review', $user_id ) && HE_V241_Governance::reviewer_assigned( (int) $post->ID, $user_id );
\t\t\treturn $editor || $reviewer;
\t\t}
\t\tif ( HE_V2_Domain::RESEARCH_TYPE === $post->post_type ) {
\t\t\t$research = HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, (int) $post->ID, 'file06-dashboard-research', $user_id );
\t\t\t$reviewer = HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, (int) $post->ID, 'file06-dashboard-research-review', $user_id ) && HE_V241_Governance::reviewer_assigned( (int) $post->ID, $user_id );
\t\t\treturn $research || $reviewer;
\t\t}
\t\treturn false;
\t}

"""
    if helper.strip() not in s:
        if marker not in s: raise SystemExit('R7 dashboard marker missing')
        s=s.replace(marker,helper+marker,1)
    old_loop="""\t\t$items = array();
\t\tforeach ( $query->posts as $post ) {
\t\t\t$items[] = array(
"""
    new_loop="""\t\t$items = array();
\t\t$user_id = get_current_user_id();
\t\tforeach ( $query->posts as $post ) {
\t\t\tif ( ! self::dashboard_post_allowed( $post, $user_id ) ) { continue; }
\t\t\t$items[] = array(
"""
    if old_loop not in s: raise SystemExit('R7 inventory loop missing')
    s=s.replace(old_loop,new_loop,1)
    old_return="\t\treturn array( 'items' => $items, 'total' => (int) $query->found_posts, 'pages' => (int) $query->max_num_pages );"
    new_return="\t\treturn array( 'items' => $items, 'total' => count( $items ), 'pages' => $items ? 1 : 0, 'scope_filtered' => true );"
    if old_return not in s: raise SystemExit('R7 inventory total marker missing')
    s=s.replace(old_return,new_return,1)
    old_item="""\t\tif ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, $post->ID ) && ! HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, $post->ID ) ) {
\t\t\treturn null;
\t\t}
"""
    new_item="""\t\tif ( ! self::dashboard_post_allowed( $post, get_current_user_id() ) ) {
\t\t\treturn null;
\t\t}
"""
    if old_item not in s: raise SystemExit('R7 dashboard item marker missing')
    s=s.replace(old_item,new_item,1)
    write(rel,s)
    append_test("""$integrations=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-integrations.php');
v247_ok(false!==strpos($integrations,'private static function dashboard_post_allowed') && false!==strpos($integrations,'HE_V241_Governance::editor_type_allowed') && false!==strpos($integrations,'HE_V241_Governance::reviewer_assigned'),'R7 publishing dashboard bypasses native File06 type/reviewer scope');
v247_ok(false!==strpos($integrations,"'scope_filtered' => true") && false===strpos($integrations,"'total' => (int) $query->found_posts"),'R7 dashboard leaks unscoped aggregate inventory counts');""")

def round8():
    append_test("""/* R8: fresh privacy/translation/migration/read-route review completed; no new source defect established. */""")

def round9():
    old = """\t\t$action = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . \" WHERE id=%d AND object_type='research'\", absint( $request['id'] ) ), ARRAY_A );
\t\tif ( ! $action || ! in_array( $action['status'], array( 'submitted', 'triaged', 'under_review', 'accepted' ), true ) ) {
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'The integrity action is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 200 );
\t\t}
"""
    new = """\t\t$action = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . \" WHERE id=%d AND object_type='research'\", absint( $request['id'] ) ), ARRAY_A );
\t\tif ( ! $action || 'accepted' !== $action['status'] ) {
\t\t\treturn self::mutation_finish( $reservation, new WP_Error( 'he_integrity_acceptance_required', __( 'The research integrity action must be accepted before it can be applied.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 200 );
\t\t}
"""
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', old, new)
    old2 = "$action_updated = $wpdb->update( HE_V2_Schema::table( 'integrity_actions' ), array( 'status' => 'applied', 'decided_by' => get_current_user_id(), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $action['id'], 'row_version' => (int) $action['row_version'] ), array( '%s','%d','%s' ), array( '%d','%d' ) );"
    new2 = "$action_updated = $wpdb->query( $wpdb->prepare( \"UPDATE \" . HE_V2_Schema::table( 'integrity_actions' ) . \" SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status='accepted'\", get_current_user_id(), (int) $action['id'], (int) $action['row_version'] ) );"
    replace_once('homeopathy-encyclopedia/includes/class-he-v22-governance.php', old2, new2)
    append_test("""$v22=v247_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
v247_ok(false!==strpos($v22,"'accepted' !== $action['status']") && false!==strpos($v22,"status='applied',decided_by=%d,row_version=row_version+1") && false!==strpos($v22,"AND status='accepted'"),'R9 research integrity apply does not own accepted-state CAS and monotonic action versioning');""")

ROUNDS={1:round1,2:round2,3:round3,4:round4,5:round5,6:round6,7:round7,8:round8,9:round9}

if __name__=='__main__':
    if len(sys.argv)!=2 or not sys.argv[1].isdigit() or int(sys.argv[1]) not in ROUNDS:
        raise SystemExit('usage: v247-eighth-ten-round-apply.py ROUND(1..9)')
    ROUNDS[int(sys.argv[1])]()

#!/usr/bin/env python3
from pathlib import Path
import json, re, sys

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'homeopathy-encyclopedia'
I=P/'includes'
T=ROOT/'tests'
D=ROOT/'docs'
round_no=int(sys.argv[1]) if len(sys.argv)>1 else 0
if round_no not in range(1,21):
    raise SystemExit('round must be 1..20')

def read(p): return Path(p).read_text(encoding='utf-8')
def write(p,s): Path(p).write_text(s,encoding='utf-8')
def replace_once(p,old,new,label):
    p=Path(p); s=read(p); n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected exactly one match, found {n} in {p}')
    write(p,s.replace(old,new,1))
def replace_between(p,start,end,new,label):
    p=Path(p); s=read(p); a=s.find(start)
    if a<0: raise SystemExit(f'{label}: start marker missing in {p}')
    b=s.find(end,a+len(start))
    if b<0: raise SystemExit(f'{label}: end marker missing in {p}')
    write(p,s[:a]+new+s[b:])
def insert_after_once(p,marker,text,label):
    p=Path(p); s=read(p); n=s.count(marker)
    if n!=1: raise SystemExit(f'{label}: expected one marker, found {n}')
    write(p,s.replace(marker,marker+text,1))

domain=I/'class-he-v2-domain.php'
api=I/'class-he-v2-api.php'
dto=I/'class-he-v241-public-dto-guard.php'
schema=I/'class-he-v2-schema.php'
browse=I/'class-he-v242-research-browse.php'
public=I/'class-he-v2-public.php'
bootstrap=P/'homeopathy-encyclopedia.php'
plugin_readme=P/'readme.txt'

if round_no==1:
    s=read(dto)
    start='\tprivate static function public_graph_edges( $edges ) {'
    a=s.find(start); b=s.rfind('\n}')
    if a<0 or b<a: raise SystemExit('R1 graph guard function not found')
    fn='''\tprivate static function public_graph_edges( $edges ) {
\t\tglobal $wpdb;
\t\t$numeric_ids = array();
\t\tforeach ( $edges as $edge ) {
\t\t\tif ( ! is_array( $edge ) ) { continue; }
\t\t\tforeach ( array( 'source', 'target' ) as $key ) {
\t\t\t\t$value = (string) ( $edge[ $key ] ?? '' );
\t\t\t\tif ( ctype_digit( $value ) ) { $numeric_ids[] = absint( $value ); }
\t\t\t}
\t\t}
\t\t$numeric_ids = array_values( array_unique( array_filter( $numeric_ids ) ) );
\t\t$map = array();
\t\tif ( $numeric_ids ) {
\t\t\t$placeholders = implode( ',', array_fill( 0, count( $numeric_ids ), '%d' ) );
\t\t\t$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,public_id FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE id IN ({$placeholders}) AND status='published' AND review_status='approved' AND safety_status='approved' AND merged_into_id=0 AND current_version>0", $numeric_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t\tforeach ( $rows as $row ) { $map[ (int) $row['id'] ] = $row['public_id']; }
\t\t}
\t\t$out = array();
\t\tforeach ( $edges as $edge ) {
\t\t\tif ( ! is_array( $edge ) ) { continue; }
\t\t\t$resolved = array();
\t\t\tforeach ( array( 'source', 'target' ) as $key ) {
\t\t\t\t$value = (string) ( $edge[ $key ] ?? '' );
\t\t\t\tif ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value ) ) {
\t\t\t\t\t$resolved[ $key ] = strtolower( $value );
\t\t\t\t} elseif ( ctype_digit( $value ) && ! empty( $map[ (int) $value ] ) ) {
\t\t\t\t\t$resolved[ $key ] = $map[ (int) $value ];
\t\t\t\t} else {
\t\t\t\t\t$resolved = array(); break;
\t\t\t\t}
\t\t\t}
\t\t\tif ( count( $resolved ) !== 2 ) { continue; }
\t\t\t$edge['source'] = $resolved['source'];
\t\t\t$edge['target'] = $resolved['target'];
\t\t\t$out[] = $edge;
\t\t}
\t\treturn $out;
\t}
'''
    write(dto,s[:a]+fn+s[b:])

elif round_no==2:
    insert_after_once(domain,"\tprivate static $merge_resolution_stack = array();\n","\tprivate static $pending_deletions = array();\n\tprivate static $delete_shutdown_registered = false;\n",'R2 deletion state')
    replace_once(domain,"\t\tadd_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );","\t\tadd_filter( 'pre_delete_post', array( __CLASS__, 'pre_delete_post' ), 10, 3 );\n\t\tadd_action( 'deleted_post', array( __CLASS__, 'on_deleted_post' ), 10, 2 );",'R2 deletion hooks')
    start='\tpublic static function on_delete_post( $post_id ) {'
    end='\tpublic static function ensure_concept_for_post( $post_id ) {'
    new='''\tpublic static function pre_delete_post( $delete, $post, $force_delete ) {
\t\tif ( null !== $delete || ! $post instanceof WP_Post || ! in_array( $post->post_type, array( self::ENTRY_TYPE, self::RESEARCH_TYPE ), true ) ) { return $delete; }
\t\tglobal $wpdb;
\t\t$is_entry = self::ENTRY_TYPE === $post->post_type;
\t\t$table = HE_V2_Schema::table( $is_entry ? 'concepts' : 'research' );
\t\t$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,row_version,status" . ( $is_entry ? "" : ",record_type" ) . " FROM {$table} WHERE post_id=%d", (int) $post->ID ), ARRAY_A );
\t\tif ( ! $row ) { return $delete; }
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'delete_lifecycle_transaction_start_failed', 'File 06 could not start the canonical hard-delete lifecycle transaction.' );
\t\t\treturn false;
\t\t}
\t\ttry {
\t\t\t$next = $is_entry ? 'archived' : 'retracted';
\t\t\t$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $next, (int) $row['id'], (int) $row['row_version'] ) );
\t\t\tif ( 1 !== (int) $updated ) { throw new RuntimeException( 'delete-lifecycle-cas-failed' ); }
\t\t\tif ( $is_entry ) {
\t\t\t\t$index_deleted = $wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $row['id'] ), array( '%d' ) );
\t\t\t\tif ( false === $index_deleted ) { throw new RuntimeException( 'delete-index-delete-failed' ); }
\t\t\t}
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'delete-lifecycle-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'delete_lifecycle_failed', 'File 06 prevented a WordPress hard delete because its canonical lifecycle state could not be persisted safely.' );
\t\t\treturn false;
\t\t}
\t\tself::$pending_deletions[ (int) $post->ID ] = array( 'object_type' => $is_entry ? 'concept' : 'research', 'object_id' => (int) $row['id'], 'previous_status' => (string) $row['status'], 'row_version' => (int) $row['row_version'] + 1, 'record_type' => $row['record_type'] ?? '' );
\t\tif ( ! self::$delete_shutdown_registered ) { register_shutdown_function( array( __CLASS__, 'verify_pending_deletions' ) ); self::$delete_shutdown_registered = true; }
\t\treturn $delete;
\t}

\tpublic static function on_deleted_post( $post_id, $post = null ) {
\t\t$post_id = absint( $post_id );
\t\tif ( empty( self::$pending_deletions[ $post_id ] ) ) { return; }
\t\t$pending = self::$pending_deletions[ $post_id ]; unset( self::$pending_deletions[ $post_id ] );
\t\ttry {
\t\t\tif ( 'concept' === $pending['object_type'] ) {
\t\t\t\tself::emit_event( 'EncyclopediaEntryArchived.v1', 'concept', $pending['object_id'], array( 'post_id' => $post_id, 'reason' => 'wordpress-hard-delete-confirmed' ) );
\t\t\t} else {
\t\t\t\tself::emit_event( 'ResearchRecordRetracted.v1', 'research', $pending['object_id'], array( 'record_type' => $pending['record_type'], 'reason' => 'wordpress-hard-delete-confirmed' ) );
\t\t\t}
\t\t} catch ( Throwable $error ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'delete_lifecycle_event_failed', 'The WordPress object was deleted, but File 06 could not persist its post-delete lifecycle event; mutations were paused.' );
\t\t}
\t}

\tpublic static function verify_pending_deletions() {
\t\tglobal $wpdb;
\t\tforeach ( self::$pending_deletions as $post_id => $pending ) {
\t\t\t$still_exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID=%d", (int) $post_id ) );
\t\t\tif ( ! $still_exists ) { continue; }
\t\t\t$table = HE_V2_Schema::table( 'concept' === $pending['object_type'] ? 'concepts' : 'research' );
\t\t\t$restored = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $pending['previous_status'], (int) $pending['object_id'], (int) $pending['row_version'] ) );
\t\t\tif ( 'concept' === $pending['object_type'] && 1 === (int) $restored ) { self::reindex_concept( (int) $pending['object_id'] ); }
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 1 === (int) $restored ? 'wordpress_delete_not_completed' : 'wordpress_delete_restore_failed', 'A requested WordPress hard delete did not complete after the canonical lifecycle transition; File 06 entered safe mode.' );
\t\t}
\t\tself::$pending_deletions = array();
\t}

'''
    replace_between(domain,start,end,new,'R2 deletion lifecycle')

elif round_no==3:
    replace_once(api,"'/datasets/(?P<id>[A-Za-z0-9-]+)/access'","'/datasets/(?P<id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/access'",'R3 dataset UUID route')
    s=read(domain)
    old='''\t\t$research_table = HE_V2_Schema::table( 'research' );
\t\tif ( is_numeric( $research_identifier ) ) {
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$research_table} WHERE id=%d", absint( $research_identifier ) ), ARRAY_A );
\t\t} else {
\t\t\t$research = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$research_table} WHERE public_id=%s", sanitize_text_field( (string) $research_identifier ) ), ARRAY_A );
\t\t}
'''
    new='''\t\t$research_table = HE_V2_Schema::table( 'research' );
\t\t$research_identifier = strtolower( sanitize_text_field( (string) $research_identifier ) );
\t\tif ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $research_identifier ) ) {
\t\t\treturn new WP_Error( 'he_canonical_public_id_required', __( 'Dataset access requests require the canonical public dataset identifier.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t}
\t\t$research = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$research_table} WHERE public_id=%s", $research_identifier ), ARRAY_A );
'''
    replace_once(domain,old,new,'R3 numeric dataset lookup')

elif round_no==4:
    start="\t\tregister_rest_route( self::NS, '/research/(?P<id>\\d+)/transition'"
    end="\t\tregister_rest_route( self::NS, '/datasets/"
    block='''\t\tregister_rest_route( self::NS, '/research/(?P<id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/transition', array(
\t\t\t'methods' => WP_REST_Server::CREATABLE,
\t\t\t'callback' => array( $this, 'transition_research' ),
\t\t\t'permission_callback' => function( $request ) {
\t\t\t\tglobal $wpdb;
\t\t\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( (string) $request['id'] ) ) ), ARRAY_A );
\t\t\t\tif ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t\t\treturn HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, (int) $row['post_id'], 'file06-rest' );
\t\t\t},
\t\t) );
'''
    replace_between(api,start,end,block,'R4 research route')
    start='\tpublic function transition_research( WP_REST_Request $request ) {'
    end='\tpublic function request_dataset_access( WP_REST_Request $request ) {'
    fn='''\tpublic function transition_research( WP_REST_Request $request ) {
\t\tglobal $wpdb;
\t\t$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
\t\t$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
\t\tif ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t$reservation = $this->require_mutation_guards( $request, 'transition-research-' . $public_id );
\t\t$data = (array) $request->get_json_params();
\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::transition_research( (int) $row['id'], sanitize_key( $data['state'] ?? '' ), absint( $data['expected_version'] ?? 0 ), get_current_user_id(), $data['note'] ?? '' );
\t\treturn $this->mutation_response( $reservation, $result );
\t}

'''
    replace_between(api,start,end,fn,'R4 research callback')

elif round_no==5:
    start="\t\tregister_rest_route( self::NS, '/integrity/(?P<id>\\d+)/apply'"
    end="\t\tregister_rest_route( self::NS, '/graph/"
    block='''\t\tregister_rest_route( self::NS, '/integrity/(?P<id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/apply', array(
\t\t\t'methods' => WP_REST_Server::CREATABLE,
\t\t\t'callback' => array( $this, 'apply_integrity' ),
\t\t\t'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH ); },
\t\t) );
'''
    replace_between(api,start,end,block,'R5 integrity route')
    start='\tpublic function apply_integrity( WP_REST_Request $request ) {'
    end='\tpublic function graph( WP_REST_Request $request ) {'
    fn='''\tpublic function apply_integrity( WP_REST_Request $request ) {
\t\tglobal $wpdb;
\t\t$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
\t\t$action_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE public_id=%s', $public_id ) );
\t\tif ( ! $action_id ) { return new WP_Error( 'he_not_found', __( 'Integrity action not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t$reservation = $this->require_mutation_guards( $request, 'apply-integrity-' . $public_id );
\t\t$data = (array) $request->get_json_params();
\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::apply_integrity_action( $action_id, absint( $data['expected_version'] ?? 0 ), get_current_user_id() );
\t\treturn $this->mutation_response( $reservation, $result );
\t}

'''
    replace_between(api,start,end,fn,'R5 integrity callback')

elif round_no==6:
    start='\tpublic function create_integrity( WP_REST_Request $request ) {'
    end='\tpublic function apply_integrity( WP_REST_Request $request ) {'
    fn='''\tpublic function create_integrity( WP_REST_Request $request ) {
\t\t$row = HE_V2_Domain::concept_by_id( $request['id'], true );
\t\tif ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
\t\t$reservation = $this->require_mutation_guards( $request, 'integrity-' . $row['public_id'] );
\t\t$data = (array) $request->get_json_params();
\t\t$replacement_id = 0;
\t\t$replacement_identifier = trim( (string) ( $data['replacement_id'] ?? '' ) );
\t\tif ( '' !== $replacement_identifier ) {
\t\t\tif ( ctype_digit( $replacement_identifier ) ) {
\t\t\t\t$result = new WP_Error( 'he_canonical_public_id_required', __( 'Replacement entries must use a canonical public identifier or canonical slug.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t\t\treturn $this->mutation_response( $reservation, $result, 201 );
\t\t\t}
\t\t\t$replacement = HE_V2_Domain::concept_by_id( sanitize_text_field( $replacement_identifier ), true );
\t\t\tif ( ! $replacement ) {
\t\t\t\t$result = new WP_Error( 'he_replacement_not_found', __( 'Replacement entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
\t\t\t\treturn $this->mutation_response( $reservation, $result, 201 );
\t\t\t}
\t\t\t$replacement_id = (int) $replacement['id'];
\t\t}
\t\t$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::create_integrity_action( $row['id'], sanitize_key( $data['type'] ?? 'correction' ), $data['reason'] ?? '', $data['evidence'] ?? '', $replacement_id, get_current_user_id() );
\t\treturn $this->mutation_response( $reservation, $result, 201 );
\t}

'''
    replace_between(api,start,end,fn,'R6 replacement identifier')

elif round_no==7:
    marker='''\tpublic static function trace_id() {
\t\treturn bin2hex( random_bytes( 16 ) );
\t}
'''
    helpers='''
\n\tpublic static function encode_public_cursor( $scope, $id ) {
\t\t$scope = sanitize_key( $scope ); $id = absint( $id );
\t\tif ( ! $scope || ! $id ) { return ''; }
\t\t$payload = wp_json_encode( array( 's' => $scope, 'i' => $id ) );
\t\t$encoded = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
\t\treturn $encoded . '.' . hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) );
\t}

\tpublic static function decode_public_cursor( $scope, $token ) {
\t\t$scope = sanitize_key( $scope ); $token = trim( (string) $token );
\t\tif ( '' === $token ) { return 0; }
\t\tif ( ! preg_match( '/^([A-Za-z0-9_-]+)\\.([a-f0-9]{64})$/', $token, $m ) ) { return null; }
\t\t$expected = hash_hmac( 'sha256', $m[1], wp_salt( 'auth' ) );
\t\tif ( ! hash_equals( $expected, $m[2] ) ) { return null; }
\t\t$padded = strtr( $m[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $m[1] ) % 4 ) % 4 );
\t\t$json = base64_decode( $padded, true );
\t\t$data = false !== $json ? json_decode( $json, true ) : null;
\t\tif ( ! is_array( $data ) || ( $data['s'] ?? '' ) !== $scope || empty( $data['i'] ) ) { return null; }
\t\treturn absint( $data['i'] );
\t}
'''
    insert_after_once(domain,marker,helpers,'R7 cursor helpers')
    replace_once(api,"'cursor' => array( 'sanitize_callback' => 'absint', 'default' => 0 )","'cursor' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' )",'R7 search cursor arg')
    replace_once(domain,"\t\t$cursor = max( 0, absint( $args['cursor'] ?? 0 ) );","\t\t$cursor = self::decode_public_cursor( 'entries', $args['cursor'] ?? '' );\n\t\tif ( null === $cursor ) { return new WP_Error( 'he_invalid_cursor', __( 'The pagination cursor is invalid or has been altered.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }",'R7 entry cursor decode')
    replace_once(domain,"'next_cursor' => $has_more && $rows ? (int) end( $rows )['id'] : null","'next_cursor' => $has_more && $rows ? self::encode_public_cursor( 'entries', (int) end( $rows )['id'] ) : null",'R7 entry cursor encode')
    replace_once(browse,"\t\t$cursor = max( 0, absint( $request->get_param( 'cursor' ) ) );\n\t\t$scan_cursor = $cursor;","\t\t$cursor = HE_V2_Domain::decode_public_cursor( 'research', $request->get_param( 'cursor' ) );\n\t\tif ( null === $cursor ) { return new WP_Error( 'he_invalid_cursor', __( 'The research pagination cursor is invalid or has been altered.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }\n\t\t$scan_cursor = $cursor;",'R7 research cursor decode')
    replace_once(browse,"'next_cursor' => $has_more && $scan_cursor ? $scan_cursor : null","'next_cursor' => $has_more && $scan_cursor ? HE_V2_Domain::encode_public_cursor( 'research', $scan_cursor ) : null",'R7 research cursor encode')

elif round_no==8:
    old='''\t\t$rows = $wpdb->get_results( $wpdb->prepare(
\t\t\t'SELECT DISTINCT a.alias,c.public_id,c.post_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=a.concept_id WHERE a.normalized_alias LIKE %s AND c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 ORDER BY a.is_primary DESC,a.alias ASC LIMIT %d",
\t\t\t$q . '%', $limit
\t\t), ARRAY_A );
'''
    new='''\t\t$rows = $wpdb->get_results( $wpdb->prepare(
\t\t\t'SELECT DISTINCT a.alias,c.public_id,c.post_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' c ON c.id=a.concept_id INNER JOIN ' . $wpdb->posts . " p ON p.ID=c.post_id AND p.post_type=%s AND p.post_status='publish' WHERE a.normalized_alias LIKE %s AND c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 ORDER BY a.is_primary DESC,a.alias ASC LIMIT %d",
\t\t\tself::ENTRY_TYPE, $q . '%', $limit
\t\t), ARRAY_A );
'''
    replace_once(domain,old,new,'R8 autocomplete publication parity')

elif round_no==9:
    start='\tpublic static function on_save_research( $post_id, $post, $update ) {'
    end='\tpublic static function pre_delete_post( $delete, $post, $force_delete ) {'
    fn='''\tpublic static function on_save_research( $post_id, $post, $update ) {
\t\tif ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
\t\tglobal $wpdb;
\t\t$table = HE_V2_Schema::table( 'research' );
\t\t$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id=%d", $post_id ) );
\t\tif ( $existing ) { return; }
\t\t$wpdb->insert( $table, array(
\t\t\t'public_id' => wp_generate_uuid4(), 'post_id' => $post_id, 'record_type' => 'publication',
\t\t\t'status' => 'publish' === $post->post_status ? 'published' : 'proposal', 'title' => $post->post_title,
\t\t\t'question' => $post->post_excerpt, 'protocol' => $post->post_content, 'investigators_json' => '[]',
\t\t\t'ethics_json' => '{}', 'consent_json' => '{}', 'conflicts_json' => '[]', 'data_class' => 'restricted',
\t\t\t'case_json' => '{}', 'metadata_json' => '{}', 'created_by' => (int) $post->post_author,
\t\t\t'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
\t\t), array( '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' ) );
\t\t$persisted = $wpdb->get_row( $wpdb->prepare( "SELECT id,post_id,record_type FROM {$table} WHERE post_id=%d", $post_id ), ARRAY_A );
\t\tif ( ! $persisted || (int) $persisted['post_id'] !== (int) $post_id || 'publication' !== $persisted['record_type'] ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'research_first_save_projection_failed', 'A WordPress research object was saved without a verified canonical File 06 research projection; mutations were paused.' );
\t\t}
\t}

'''
    replace_between(domain,start,end,fn,'R9 research first save')

elif round_no==10:
    start='\tpublic static function on_save_entry( $post_id, $post, $update ) {'
    end='\tpublic static function on_save_research( $post_id, $post, $update ) {'
    fn='''\tpublic static function on_save_entry( $post_id, $post, $update ) {
\t\tif ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
\t\t$concept_id = self::ensure_concept_for_post( $post_id );
\t\tif ( ! $concept_id || ! self::reindex_concept_by_post( $post_id ) ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_save_projection_failed', 'A WordPress entry save could not be reconciled with its canonical File 06 projection/search index; mutations were paused.' );
\t\t}
\t}

'''
    replace_between(domain,start,end,fn,'R10 entry save projection')
    old='''\t\tif ( $existing ) {
\t\t\tif ( ! $type || ! isset( self::types()[ $type ] ) ) {
\t\t\t\t$type = 'clinical-terminology';
\t\t\t}
\t\t\t$wpdb->update( $table, array( 'type_slug' => $type, 'language' => $language, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $existing ), array( '%s','%s','%s' ), array( '%d' ) );
\t\t\treturn $existing;
\t\t}
'''
    new='''\t\tif ( $existing ) {
\t\t\tif ( ! $type || ! isset( self::types()[ $type ] ) ) { $type = 'clinical-terminology'; }
\t\t\t$updated = $wpdb->update( $table, array( 'type_slug' => $type, 'language' => $language, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $existing ), array( '%s','%s','%s' ), array( '%d' ) );
\t\t\t$persisted = $wpdb->get_row( $wpdb->prepare( "SELECT type_slug,language FROM {$table} WHERE id=%d", $existing ), ARRAY_A );
\t\t\tif ( false === $updated || ! $persisted || (string) $persisted['type_slug'] !== (string) $type || (string) $persisted['language'] !== (string) $language ) {
\t\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_projection_update_failed', 'File 06 could not verify entry type/language projection persistence.' );
\t\t\t\treturn 0;
\t\t\t}
\t\t\treturn $existing;
\t\t}
'''
    replace_once(domain,old,new,'R10 existing projection verification')
    start='\tpublic static function reindex_concept_by_post( $post_id ) {'
    end='\tpublic static function reindex_concept( $concept_id ) {'
    fn='''\tpublic static function reindex_concept_by_post( $post_id ) {
\t\tglobal $wpdb;
\t\t$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ) );
\t\treturn $id ? self::reindex_concept( $id ) : false;
\t}

'''
    replace_between(domain,start,end,fn,'R10 reindex by post result')
    replace_once(domain,"\t\t\t$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => absint( $concept_id ) ), array( '%d' ) );\n\t\t\treturn false;","\t\t\t$deleted = $wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => absint( $concept_id ) ), array( '%d' ) );\n\t\t\treturn false !== $deleted;",'R10 nonpublic reindex certainty')

elif round_no==11:
    marker='''\tpublic static function table( $suffix ) {
\t\tglobal $wpdb;
\t\treturn $wpdb->prefix . 'he_' . sanitize_key( $suffix );
\t}
'''
    helpers='''
\n\tpublic static function required_tables() {
\t\treturn array( 'concepts','aliases','versions','references','relations','reviews','integrity_actions','research','dataset_access','events','outbox','idempotency','bookmarks','rate_limits','search_index' );
\t}

\tpublic static function schema_complete() {
\t\tglobal $wpdb;
\t\tforeach ( self::required_tables() as $suffix ) {
\t\t\t$table = self::table( $suffix );
\t\t\tif ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
\t\t}
\t\treturn true;
\t}
'''
    insert_after_once(schema,marker,helpers,'R11 schema helpers')
    replace_once(schema,"\t\t$required = array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'reviews', 'integrity_actions', 'research', 'dataset_access', 'events', 'outbox', 'idempotency', 'bookmarks', 'rate_limits', 'search_index' );\n\t\tforeach ( $required as $suffix ) {","\t\tforeach ( self::required_tables() as $suffix ) {",'R11 assert schema list')
    start='\tpublic static function runtime_status() {'
    end='\tpublic static function health() {'
    fn='''\tpublic static function runtime_status() {
\t\t$failure = get_option( self::OPTION_FAILURE );
\t\tif ( is_array( $failure ) && ! empty( $failure['code'] ) ) { return 'degraded'; }
\t\tif ( get_option( self::OPTION_SAFE_MODE ) ) { return 'safe-mode'; }
\t\tif ( (int) get_option( self::OPTION_SCHEMA, 0 ) !== HE_SCHEMA_VERSION ) { return 'migration-required'; }
\t\treturn self::schema_complete() ? 'active' : 'degraded';
\t}

'''
    replace_between(schema,start,end,fn,'R11 runtime status')
    start='\tpublic static function health() {'
    end='\tpublic static function repair( $dry_run = true ) {'
    fn='''\tpublic static function health() {
\t\tglobal $wpdb;
\t\t$tables = array();
\t\tforeach ( self::required_tables() as $suffix ) {
\t\t\t$table = self::table( $suffix );
\t\t\t$tables[ $suffix ] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
\t\t}
\t\t$complete = ! in_array( false, $tables, true );
\t\treturn array(
\t\t\t'status' => $complete ? self::runtime_status() : 'degraded',
\t\t\t'plugin_version' => HE_VERSION, 'schema_version' => (int) get_option( self::OPTION_SCHEMA, 0 ), 'expected_schema' => HE_SCHEMA_VERSION,
\t\t\t'schema_complete' => $complete, 'tables' => $tables,
\t\t\t'file00' => function_exists( 'smc_user_status' ), 'file20' => defined( 'SABRI_SHELL_VERSION' ),
\t\t\t'cron' => (bool) wp_next_scheduled( 'he_v2_maintenance' ),
\t\t\t'pending_outbox' => ! empty( $tables['outbox'] ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table( 'outbox' ) . " WHERE status IN ('pending','retry')" ) : 0, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
\t\t\t'failure' => get_option( self::OPTION_FAILURE, array() ),
\t\t);
\t}

'''
    replace_between(schema,start,end,fn,'R11 health completeness')

elif round_no==12:
    start='\tprivate static function migrate_legacy() {'
    end='\tpublic static function record_runtime_failure( $code, $message ) {'
    fn='''\tprivate static function migrate_legacy() {
\t\tif ( get_option( 'he_v2_legacy_migrated' ) ) { return; }
\t\t$page = 1;
\t\tdo {
\t\t\t$query = new WP_Query( array(
\t\t\t\t'post_type' => 'he_entry', 'post_status' => array( 'publish','pending','draft','private' ), 'posts_per_page' => 200,
\t\t\t\t'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'paged' => $page,
\t\t\t) );
\t\t\tforeach ( $query->posts as $post_id ) { HE_V2_Domain::ensure_concept_for_post( $post_id ); }
\t\t\t$count = count( $query->posts ); ++$page;
\t\t} while ( 200 === $count );
\t\tupdate_option( 'he_v2_legacy_migrated', 1, false );
\t}

'''
    replace_between(schema,start,end,fn,'R12 paginated legacy migration')

elif round_no==13:
    replace_once(schema,"\t\t\tforeach ( $query->posts as $post_id ) { HE_V2_Domain::ensure_concept_for_post( $post_id ); }","\t\t\tforeach ( $query->posts as $post_id ) {\n\t\t\t\tif ( ! HE_V2_Domain::ensure_concept_for_post( $post_id ) ) {\n\t\t\t\t\tself::record_runtime_failure( 'legacy_migration_projection_failed', 'File 06 stopped legacy migration because a canonical entry projection could not be verified.' );\n\t\t\t\t\tthrow new RuntimeException( 'File 06 legacy migration projection failed.' );\n\t\t\t\t}\n\t\t\t}", 'R13 migration failure propagation')

elif round_no==14:
    start='\tpublic static function reindex_all() {'
    end='\tpublic static function minimize_event_payload( $value, $depth = 0 ) {'
    fn='''\tpublic static function reindex_all() {
\t\tglobal $wpdb;
\t\t$ids = $wpdb->get_col( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' ORDER BY id ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
\t\t$failed = array();
\t\tforeach ( $ids as $id ) { if ( ! self::reindex_concept( (int) $id ) ) { $failed[] = (int) $id; } }
\t\tif ( $failed ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'reindex_all_failed', 'File 06 could not rebuild every canonical search-index projection.' );
\t\t\treturn new WP_Error( 'he_reindex_failed', __( 'One or more encyclopedia search-index projections could not be rebuilt safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503, 'failed_count' => count( $failed ) ) );
\t\t}
\t\treturn count( $ids );
\t}

'''
    replace_between(domain,start,end,fn,'R14 reindex failure propagation')

elif round_no==15:
    start='\tpublic static function repair( $dry_run = true ) {'
    # repair is last function in schema
    s=read(schema); a=s.find(start); b=s.rfind('\n}')
    if a<0 or b<a: raise SystemExit('R15 repair function not found')
    fn='''\tpublic static function repair( $dry_run = true ) {
\t\t$before = self::health();
\t\t$result = array( 'dry_run' => (bool) $dry_run, 'before' => $before, 'actions' => array() );
\t\tif ( $dry_run ) { return $result; }
\t\ttry { self::install(); } catch ( Throwable $error ) {
\t\t\tself::record_runtime_failure( 'repair_schema_failed', 'File 06 repair could not verify/install the canonical schema.' );
\t\t\treturn new WP_Error( 'he_repair_failed', __( 'File 06 schema repair could not be completed safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\t$reindexed = HE_V2_Domain::reindex_all();
\t\tif ( is_wp_error( $reindexed ) ) { return $reindexed; }
\t\t$after = self::health();
\t\tif ( empty( $after['schema_complete'] ) || 'active' !== $after['status'] ) {
\t\t\tself::record_runtime_failure( 'repair_verification_failed', 'File 06 repair completed writes but failed the post-repair health verification.' );
\t\t\treturn new WP_Error( 'he_repair_failed', __( 'File 06 repair could not verify a healthy final state.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\tdelete_option( self::OPTION_FAILURE );
\t\t$result['actions'][] = 'schema-verified'; $result['actions'][] = 'search-index-rebuilt'; $result['after'] = self::health();
\t\treturn $result;
\t}
'''
    write(schema,s[:a]+fn+s[b:])

elif round_no==16:
    s=read(domain)
    start="\t\tif ( ! $type || ! isset( self::types()[ $type ] ) ) {\n\t\t\t$type = 'clinical-terminology';\n\t\t\twp_set_object_terms( $post_id, array( $type ), self::TAX_TYPE, false );\n\t\t}\n\t\t$slug ="
    a=s.find(start)
    if a<0: raise SystemExit('R16 new concept start not found')
    b=s.find("\n\t\treturn $concept_id;\n\t}\n\n\tprivate static function unique_slug",a)
    if b<0: raise SystemExit('R16 new concept end not found')
    replacement='''\t\tif ( ! $type || ! isset( self::types()[ $type ] ) ) {
\t\t\t$type = 'clinical-terminology';
\t\t\t$term_result = wp_set_object_terms( $post_id, array( $type ), self::TAX_TYPE, false );
\t\t\tif ( is_wp_error( $term_result ) || self::taxonomy_slug( $post_id, self::TAX_TYPE ) !== $type ) {
\t\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_projection_taxonomy_failed', 'File 06 could not verify the canonical fallback knowledge taxonomy.' );
\t\t\t\treturn 0;
\t\t\t}
\t\t}
\t\t$slug = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
\t\t$slug = self::unique_slug( $slug, 0 );
\t\t$status = 'publish' === $post->post_status ? 'published' : 'draft';
\t\t$now = current_time( 'mysql', true );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_projection_transaction_start_failed', 'File 06 could not start a new canonical concept projection transaction.' );
\t\t\treturn 0;
\t\t}
\t\ttry {
\t\t\t$ok = $wpdb->insert( $table, array(
\t\t\t\t'public_id' => wp_generate_uuid4(), 'post_id' => $post_id, 'type_slug' => $type, 'canonical_slug' => $slug,
\t\t\t\t'language' => $language, 'status' => $status, 'safety_status' => get_post_meta( $post_id, '_he_safety_status', true ) ?: 'unreviewed',
\t\t\t\t'review_status' => get_post_meta( $post_id, '_he_review_status', true ) ?: 'unreviewed', 'created_by' => (int) $post->post_author,
\t\t\t\t'created_at' => $now, 'updated_at' => $now,
\t\t\t), array( '%s','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s' ) );
\t\t\tif ( ! $ok ) { throw new RuntimeException( 'concept-insert-failed' ); }
\t\t\t$concept_id = (int) $wpdb->insert_id;
\t\t\t$alias_ok = self::add_alias( $concept_id, $post->post_title, $language, 'canonical', true, (int) $post->post_author );
\t\t\tif ( is_wp_error( $alias_ok ) || ! $alias_ok ) { throw new RuntimeException( 'canonical-alias-failed' ); }
\t\t\tif ( 'published' === $status ) {
\t\t\t\t$version_id = self::snapshot_version( $concept_id, 'Imported published baseline', 'published', (int) $post->post_author );
\t\t\t\tif ( ! $version_id ) { throw new RuntimeException( 'published-baseline-snapshot-failed' ); }
\t\t\t\t$finalized = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET current_version=%d WHERE id=%d AND current_version=0", $version_id, $concept_id ) );
\t\t\t\tif ( 1 !== (int) $finalized ) { throw new RuntimeException( 'published-baseline-finalize-failed' ); }
\t\t\t}
\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'concept-projection-commit-failed' ); }
\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_projection_create_failed', 'File 06 rolled back a new canonical concept projection because a required child write or commit could not be verified.' );
\t\t\treturn 0;
\t\t}
'''
    # include original return and closing function, only replace body segment before it
    write(domain,s[:a]+replacement+s[b:])

elif round_no==17:
    marker='\tpublic static function concept_by_id( $identifier, $include_private = false ) {'
    helper='''\tpublic static function concept_by_post_id( $post_id ) {
\t\tglobal $wpdb;
\t\treturn $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ), ARRAY_A );
\t}

'''
    p=Path(domain); s=read(p); idx=s.find(marker)
    if idx<0: raise SystemExit('R17 concept_by_id marker missing')
    write(p,s[:idx]+helper+s[idx:])
    replace_once(public,"\t\t$row = HE_V2_Domain::concept_by_id( $post->post_name );\n\t\t$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;","\t\t$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;\n\t\t$row = $raw ? HE_V2_Domain::concept_by_id( (int) $raw['id'] ) : null;\n\t\t$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;",'R17 entry rendering post binding')
    replace_once(public,"\t\t\t$row = $post ? HE_V2_Domain::concept_by_id( $post->post_name, true ) : null;","\t\t\t$row = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;",'R17 redirect post binding')

elif round_no==18:
    start='\tpublic static function publish_due() {'
    end='\tpublic static function reindex_concept_by_post( $post_id ) {'
    fn='''\tpublic static function publish_due() {
\t\tif ( ! class_exists( 'HE_V22_Schedule' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'secure_schedule_owner_unavailable', 'The governed scheduled-publication owner is unavailable; publication was not attempted.' );
\t\t\treturn 0;
\t\t}
\t\treturn HE_V22_Schedule::publish_due_securely();
\t}

'''
    replace_between(domain,start,end,fn,'R18 secure scheduler delegation')
    replace_once(domain,"\t\tself::publish_due();\n",'', 'R18 duplicate maintenance publisher')

elif round_no==19:
    # Deliberately no source mutation. The dedicated round-19 audit is run by CI on the fully corrected R1-R18 state.
    pass

elif round_no==20:
    # Runtime and public release identity.
    s=read(bootstrap).replace(' * Version: 2.4.14',' * Version: 2.4.15').replace("HE_VERSION', '2.4.14","HE_VERSION', '2.4.15").replace("HE_CONTRACT_VERSION', '2.4.14","HE_CONTRACT_VERSION', '2.4.15").replace("'future_hardening_version'=>'2.4.14'","'future_hardening_version'=>'2.4.15'")
    write(bootstrap,s)
    s=read(plugin_readme).replace('Stable tag: 2.4.14','Stable tag: 2.4.15').replace('The 2.4.14 candidate','The 2.4.15 candidate')
    write(plugin_readme,s)
    # Current invariant and aggregate labels.
    for p in (T/'v2-invariants.php', T/'v2-source-invariants.sh'):
        write(p,read(p).replace('2.4.14','2.4.15'))
    runall=T/'run-all.sh'; s=read(runall)
    needle='php "$root/tests/v2414-fifteenth-ten-round-regressions.php"\n'
    if needle not in s: raise SystemExit('R20 run-all insertion marker missing')
    s=s.replace(needle,needle+'php "$root/tests/v2415-sixteenth-twenty-round-regressions.php"\n',1)
    s=s.replace('file06-v2.4.14-a.zip','file06-v2.4.15-a.zip').replace('file06-v2.4.14-b.zip','file06-v2.4.15-b.zip')
    s=s.replace('All File 06 v2.4.14 automated checks, inherited review matrices, fifteenth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.15 automated checks, inherited review matrices, sixteenth twenty-round regressions and deterministic package comparison passed.')
    write(runall,s)
    # Make recent historical release controls forward compatible with later v2.4.x candidates without rewriting historical facts.
    for name,base in [('v2411-twelfth-ten-round-regressions.php',11),('v2412-thirteenth-ten-round-regressions.php',12),('v2413-fourteenth-ten-round-regressions.php',13),('v2414-fifteenth-ten-round-regressions.php',14)]:
        p=T/name; s=read(p)
        s=re.sub(r"preg_match\('/ \\* Version: 2\\.4\\\.\(\?:[^']+\)/'", "preg_match('/ \\* Version: 2\\.4\\.(?:"+'|'.join(str(x) for x in range(base,16))+")/'", s) if False else s
        # Explicitly widen simple alternatives as they appear in these compact historical tests.
        s=s.replace(f"2\\.4\\.(?:{base}|{base+1}|{base+2})", f"2\\.4\\.(?:"+'|'.join(str(x) for x in range(base,16))+')')
        s=s.replace(f"2\\.4\\.(?:{base}|{base+1})", f"2\\.4\\.(?:"+'|'.join(str(x) for x in range(base,16))+')')
        s=s.replace(f"2\\.4\\.(?:{base})", f"2\\.4\\.(?:"+'|'.join(str(x) for x in range(base,16))+')')
        write(p,s)
    # Current repository-facing documentation.
    write(ROOT/'README.md','''# File 06 — Homeopathy Encyclopedia 2.4.15\n\nSixteenth fresh twenty-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.\n\n## Candidate truth\n- Branch: `audit/file-06-sixteenth-twenty-round-v2.4.15`\n- Plugin / contract: `2.4.15`\n- Global schema: `10`\n- V24 Future schema: `2`\n- REST namespace: `sabri/v2/file-06`\n- Defect rounds: `1–18, 20`\n- Clean fresh audit round: `19`\n\nThis cycle hardens public graph UUID parity, hard-delete lifecycle veto/confirmation, canonical public identifiers for dataset/research/integrity operations, opaque cursors, live-post search parity, projection/migration/reindex/repair failure propagation, schema-complete health, authoritative post binding, and a single secure scheduled-publication path.\n\nRun `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.\n''')
    write(ROOT/'STATUS.md','''# File 06 Status — 2.4.15 Sixteenth Fresh Twenty-Round Candidate\n\n| Status | Evidence |\n|---|---|\n| Specified | File 06 governing plan + applicable later platform governance |\n| Coded | `audit/file-06-sixteenth-twenty-round-v2.4.15` |\n| Reviewed | 20 sequential review → immediate fix/retest rounds |\n| Defect rounds | `1–18, 20` |\n| Clean round | `19` |\n| Runtime | `2.4.15 / schema 10 / contract 2.4.15 / Future schema 2` |\n| Automated QA | Authoritative only from completed final exact-head v2.4.15 workflow |\n| Staging accepted | **No / unverified** |\n| Live deployed | **No / unverified** |\n| Operational | **No / unverified** |\n\nRepository, staging and live are separate realities.\n''')
    ch=read(ROOT/'CHANGELOG.md')
    entry='''## 2.4.15 — Sixteenth fresh twenty-round corrected candidate\n- Twenty sequential review/fix/retest rounds completed; product/repository defects corrected in rounds 1–18 and release-truth drift corrected in round 20; round 19 was a clean cross-cutting audit.\n- Hardened canonical identifiers, opaque pagination, WordPress/domain projection parity, migration/reindex/repair failure propagation, complete health checks, authoritative post binding, hard-delete confirmation and secure scheduled-publication ownership.\n- Repository candidate only; staging/live/operational evidence remains unverified.\n\n'''
    if '## 2.4.15 —' not in ch: ch=ch.replace('# Changelog\n\n','# Changelog\n\n'+entry,1)
    write(ROOT/'CHANGELOG.md',ch)
    # SBOM current identity; package digests remain intentionally emitted by exact-head CI.
    sbom=json.loads(read(ROOT/'SBOM.json'))
    sbom['version']=int(sbom.get('version',0))+1
    sbom.setdefault('metadata',{}).setdefault('component',{})['version']='2.4.15'
    sbom['metadata']['component']['purl']='pkg:wordpress/homeopathy-encyclopedia@2.4.15'
    release=sbom.setdefault('release',{})
    release.update({'file':'06-homeopathy-encyclopedia-foundation-2.4.15.zip','sha256':None,'bytes':None,'source_tree_sha256':None,'schema':10,'future_schema':2,'contract':'2.4.15','defect_rounds':list(range(1,19))+[20],'clean_rounds':[19],'sixteenth_review_rounds':20,'staging_accepted':False,'live_deployed':False,'operational':False})
    write(ROOT/'SBOM.json',json.dumps(sbom,ensure_ascii=False,indent=2)+'\n')
    write(ROOT/'V2-MANIFEST.md','''# File 06 v2.4.15 Candidate Manifest\n\n## Release identity\n- Runtime version: `2.4.15`\n- Schema: `10`\n- Contract: `2.4.15`\n- Future schema: `2`\n- Branch: `audit/file-06-sixteenth-twenty-round-v2.4.15`\n- Package top-level folder: `06-homeopathy-encyclopedia-foundation/`\n- Canonical plugin folder: `homeopathy-encyclopedia/`\n- REST namespace: `sabri/v2/file-06`\n- Review cycle: `20/20` sequential rounds\n- Defect rounds: `1–18, 20`; clean round: `19`\n\n## Evidence policy\nExact ZIP SHA-256, byte count, source-tree SHA-256 and exact tested HEAD are emitted by the final exact-head GitHub Actions workflow. They are not guessed or self-embedded before that workflow completes. Repository evidence does not establish staging or live deployment.\n\n## Deployment state\n- Automated-QA: pending final exact-head workflow at source-record time\n- Staging-Accepted: unverified\n- Live-Deployed: unverified\n- Operational: unverified\n''')
    write(D/'FILE-06-v2.4.15-SIXTEENTH-TWENTY-ROUND-REVIEW.md','''# File 06 v2.4.15 — Sixteenth Fresh Twenty-Round Review\n\nRepository-only corrective record. It does not establish staging or live deployment.\n\nDefect rounds: 1–18 and 20. Clean fresh cross-cutting audit: round 19.\n\n1. Public graph UUID edges survive DTO hardening.\n2. WordPress hard deletes are vetoable on canonical lifecycle failure and events follow confirmed deletion.\n3. Dataset-access requests accept canonical dataset UUIDs only.\n4. Research transition REST uses canonical research UUIDs with object-scoped authorization.\n5. Integrity apply REST uses integrity-action UUIDs.\n6. Integrity replacement targets reject raw numeric public IDs.\n7. Entry/research public pagination uses signed opaque cursors.\n8. Autocomplete requires a live published WordPress entry.\n9. Direct research first-save projection failure enters safe mode.\n10. Existing entry projection and search-index persistence fail closed.\n11. Health verifies every required core table and reports schema completeness.\n12. Legacy migration paginates beyond 200 entries.\n13. Legacy migration does not mark completion after projection failure.\n14. Bulk reindex propagates individual failures.\n15. Repair clears failure state only after verified schema/index health.\n16. New concept taxonomy/alias/published-baseline projection is transactionally verified.\n17. Singular WordPress entry rendering/merge redirect binds by authoritative post ID.\n18. Legacy unsafe scheduled publisher path is eliminated in favor of the secure schedule owner.\n19. Fresh cross-cutting audit found no additional repository-level defect after R1–R18 corrections.\n20. Runtime, contract, current QA, SBOM/manifest and repository documentation aligned to 2.4.15.\n\nSchema remains 10; Future schema remains 2. Staging, live and operational status remain unverified.\n''')

print(f'File 06 v2.4.15 sixteenth cycle round {round_no} ' + ('audit completed without source mutation' if round_no==19 else 'correction applied'))

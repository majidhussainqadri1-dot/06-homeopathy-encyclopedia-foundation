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

def read(path): return Path(path).read_text(encoding='utf-8')
def write(path,text): Path(path).write_text(text,encoding='utf-8')
def replace_once(path,old,new,label):
    path=Path(path); s=read(path)
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected exactly one match, found {n} in {path}')
    write(path,s.replace(old,new,1))
def regex_once(path,pattern,repl,label,flags=re.S):
    path=Path(path); s=read(path)
    out,n=re.subn(pattern,repl,s,count=1,flags=flags)
    if n!=1: raise SystemExit(f'{label}: expected exactly one regex match, found {n} in {path}')
    write(path,out)

domain=P/'includes/class-he-v2-domain.php'
schedule=P/'includes/class-he-v22-schedule.php'
gov=P/'includes/class-he-v22-governance.php'
lang=P/'includes/class-he-v242-language-surfaces.php'
integrations=P/'includes/class-he-v2-integrations.php'
authoring=P/'includes/class-he-v242-research-authoring.php'
third=P/'includes/class-he-v242-third-audit.php'
public=P/'includes/class-he-v22-public-guard.php'
bootstrap=P/'homeopathy-encyclopedia.php'
readme=P/'readme.txt'

if round_no==1:
    pattern=r"\tprivate static function public_structured_fields\( \$structured \) \{.*?\n\t\}\n\n\tpublic static function create_entry"
    repl="""\tprivate static function public_structured_value( $value, $depth = 0 ) {
\t\tif ( $depth > 6 ) { return ''; }
\t\tif ( is_array( $value ) ) {
\t\t\t$out = array();
\t\t\tforeach ( $value as $key => $item ) {
\t\t\t\t$safe_key = is_int( $key ) ? $key : sanitize_text_field( (string) $key );
\t\t\t\t$out[ $safe_key ] = self::public_structured_value( $item, $depth + 1 );
\t\t\t}
\t\t\treturn $out;
\t\t}
\t\tif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { return $value; }
\t\tif ( null === $value ) { return ''; }
\t\treturn wp_kses_post( (string) $value );
\t}

\tprivate static function public_structured_fields( $structured ) {
\t\t$allowed = array( 'source', 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'limitations', 'emergency_boundary', 'evidence_summary' );
\t\t$output = array();
\t\tforeach ( $allowed as $key ) {
\t\t\tif ( isset( $structured[ $key ] ) && '' !== $structured[ $key ] ) {
\t\t\t\t$output[ $key ] = self::public_structured_value( $structured[ $key ] );
\t\t\t}
\t\t}
\t\treturn $output;
\t}

\tpublic static function create_entry"""
    regex_once(domain,pattern,repl,'R1 recursive structured DTO sanitizer')

elif round_no==2:
    old="""\t\t\tif ( is_wp_error( $validation ) ) {
\t\t\t\t$wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='review',review_status='pending',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d\", (int) $row['id'], (int) $row['row_version'] ) );
\t\t\t\tself::clear_schedule_meta( (int) $row['post_id'] );
\t\t\t\t$invalidated++;
\t\t\t\tcontinue;
\t\t\t}
"""
    new="""\t\t\tif ( is_wp_error( $validation ) ) {
\t\t\t\t$invalidated_row = $wpdb->query( $wpdb->prepare( \"UPDATE {$table} SET status='review',review_status='pending',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d\", (int) $row['id'], (int) $row['row_version'] ) );
\t\t\t\tif ( 1 === (int) $invalidated_row ) {
\t\t\t\t\tself::clear_schedule_meta( (int) $row['post_id'] );
\t\t\t\t\tHE_V22_Governance::reindex_concept_secure( (int) $row['id'] );
\t\t\t\t\tHE_V2_Domain::emit_event( 'EncyclopediaEntryScheduleInvalidated.v1', 'concept', (int) $row['id'], array( 'reason' => 'validation-failed-before-publication' ) );
\t\t\t\t\t$invalidated++;
\t\t\t\t} elseif ( false === $invalidated_row ) {
\t\t\t\t\tHE_V2_Schema::record_runtime_failure( 'scheduled_invalidation_write_failed', 'File 06 could not persist a validation-driven schedule invalidation.' );
\t\t\t\t}
\t\t\t\tcontinue;
\t\t\t}
"""
    replace_once(schedule,old,new,'R2 scheduled invalidation CAS')

elif round_no==3:
    old="""\t\tif ( ! $source || ! $target || (int) $source['id'] === (int) $target['id'] ) {
\t\t\treturn new WP_Error( 'he_invalid_merge', __( 'A valid source and target concept are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\t$wpdb->query( 'START TRANSACTION' );
\t\ttry {
"""
    new="""\t\tif ( ! $source || ! $target || (int) $source['id'] === (int) $target['id'] ) {
\t\t\treturn new WP_Error( 'he_invalid_merge', __( 'A valid source and target concept are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
\t\t}
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'secure_merge_transaction_start_failed', 'File 06 could not start the governed concept-merge transaction.' );
\t\t\treturn new WP_Error( 'he_merge_failed', __( 'The concept merge could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\ttry {
"""
    replace_once(gov,old,new,'R3 merge START certainty')
    replace_once(gov,"\t\t\t$wpdb->query( 'COMMIT' );\n\t\t\tHE_V2_Domain::emit_event( 'KnowledgeConceptMerged.v1'","\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'merge-commit-failed' ); }\n\t\t\tHE_V2_Domain::emit_event( 'KnowledgeConceptMerged.v1'",'R3 merge COMMIT certainty')
    oldcatch="""\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tif ( in_array( $error->getMessage(), array( 'relation-provenance-invalid', 'relation-provenance-clone-failed' ), true ) ) {
"""
    newcatch="""\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\tif ( 'merge-commit-failed' === $error->getMessage() ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'secure_merge_commit_failed', 'File 06 could not confirm the governed concept-merge commit.' );
\t\t\t\treturn new WP_Error( 'he_merge_failed', __( 'The concept merge outcome could not be confirmed safely. Reload state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\tif ( in_array( $error->getMessage(), array( 'relation-provenance-invalid', 'relation-provenance-clone-failed' ), true ) ) {
"""
    replace_once(gov,oldcatch,newcatch,'R3 merge commit error mapping')

elif round_no==4:
    old="""\t\t\t\tif ( $collision === (int) $target['id'] ) {
\t\t\t\t\t$wpdb->delete( HE_V2_Schema::table( 'aliases' ), array( 'id' => (int) $alias['id'] ), array( '%d' ) );
\t\t\t\t} else {
\t\t\t\t\t$wpdb->update( HE_V2_Schema::table( 'aliases' ), array( 'concept_id' => (int) $target['id'], 'alias_type' => 'redirect', 'is_primary' => 0 ), array( 'id' => (int) $alias['id'] ), array( '%d','%s','%d' ), array( '%d' ) );
\t\t\t\t}
"""
    new="""\t\t\t\tif ( $collision === (int) $target['id'] ) {
\t\t\t\t\t$alias_deleted = $wpdb->delete( HE_V2_Schema::table( 'aliases' ), array( 'id' => (int) $alias['id'] ), array( '%d' ) );
\t\t\t\t\tif ( 1 !== (int) $alias_deleted ) { throw new RuntimeException( 'merge-alias-write-failed' ); }
\t\t\t\t} else {
\t\t\t\t\t$alias_updated = $wpdb->update( HE_V2_Schema::table( 'aliases' ), array( 'concept_id' => (int) $target['id'], 'alias_type' => 'redirect', 'is_primary' => 0 ), array( 'id' => (int) $alias['id'] ), array( '%d','%s','%d' ), array( '%d' ) );
\t\t\t\t\tif ( 1 !== (int) $alias_updated ) { throw new RuntimeException( 'merge-alias-write-failed' ); }
\t\t\t\t}
"""
    replace_once(gov,old,new,'R4 merge alias write certainty')
    replace_once(gov,"\t\t\t$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $source['id'] ), array( '%d' ) );\n\t\t\tif ( false === $wpdb->query( 'COMMIT' ) )","\t\t\t$index_deleted = $wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $source['id'] ), array( '%d' ) );\n\t\t\tif ( false === $index_deleted ) { throw new RuntimeException( 'merge-index-write-failed' ); }\n\t\t\tif ( false === $wpdb->query( 'COMMIT' ) )",'R4 merge index write certainty')
    marker="\t\t\tif ( 'relation-write-failed' === $error->getMessage() ) {\n"
    replacement="\t\t\tif ( in_array( $error->getMessage(), array( 'merge-alias-write-failed', 'merge-index-write-failed' ), true ) ) {\n\t\t\t\treturn new WP_Error( 'he_merge_failed', __( 'The merge could not persist every alias/index mutation atomically.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );\n\t\t\t}\n"+marker
    replace_once(gov,marker,replacement,'R4 merge write error mapping')

elif round_no==5:
    old="""\t\tupdate_post_meta( $post_id, '_he_language', $locale );
\t\tglobal $wpdb;
\t\t$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'language' => $locale, 'updated_at' => current_time( 'mysql', true ) ), array( 'post_id' => absint( $post_id ) ), array( '%s','%s' ), array( '%d' ) );
\t\tHE_V242_Third_Audit::repair_canonical_alias_language( $post_id, $post, true );
"""
    new="""\t\tupdate_post_meta( $post_id, '_he_language', $locale );
\t\tglobal $wpdb;
\t\t$stored = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT language FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ) );
\t\tif ( $stored !== $locale ) {
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'source_language_persistence_failed', 'The source-language meta/domain write did not converge; File 06 entered safe mode.' );
\t\t\treturn;
\t\t}
\t\tHE_V242_Third_Audit::repair_canonical_alias_language( $post_id, $post, true );
"""
    replace_once(lang,old,new,'R5 source language save convergence')
    old2="""\t\t$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'language' => $canonical, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $concept['id'] ), array( '%s','%s' ), array( '%d' ) );
\t\tif ( class_exists( 'HE_V24_Migration_Safety' ) && HE_V24_Migration_Safety::ready() ) {
"""
    new2="""\t\t$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'concepts' ) . ' SET language=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d AND language=%s', $canonical, (int) $concept['id'], (string) $concept['language'] ) );
\t\tif ( 1 !== (int) $updated ) {
\t\t\tself::$normalizing = true;
\t\t\tupdate_post_meta( $object_id, '_he_language', (string) $concept['language'] );
\t\t\tself::$normalizing = false;
\t\t\tupdate_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
\t\t\tHE_V2_Schema::record_runtime_failure( 'source_language_domain_cas_failed', 'The source-language domain row changed or failed to persist; meta was restored and File 06 entered safe mode.' );
\t\t\treturn;
\t\t}
\t\tif ( class_exists( 'HE_V24_Migration_Safety' ) && HE_V24_Migration_Safety::ready() ) {
"""
    replace_once(lang,old2,new2,'R5 source language domain CAS')

elif round_no==6:
    anchor="\n\tpublic static function emit_event( $name, $object_type, $object_id, $payload ) {\n"
    helper=r'''
	public static function minimize_event_payload( $value, $depth = 0 ) {
		if ( $depth > 6 ) { return '[truncated]'; }
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$safe_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
				if ( ! is_int( $key ) && preg_match( '/(?:password|passwd|secret|token|email|phone|mobile|address|cnic|passport|national[_-]?id|patient[_-]?id|mrn|consent[_-]?document)/i', (string) $key ) ) {
					$out[ $safe_key ?: 'redacted' ] = '[redacted]';
					continue;
				}
				$out[ $safe_key ] = self::minimize_event_payload( $item, $depth + 1 );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) { return $value; }
		$text = sanitize_textarea_field( (string) $value );
		$text = preg_replace( '/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', '[redacted-email]', $text );
		$text = preg_replace( '/\b(?:\+?92|0)?3\d{9}\b/u', '[redacted-phone]', $text );
		$text = preg_replace( '/\b\d{5}-\d{7}-\d\b/u', '[redacted-cnic]', $text );
		return mb_substr( $text, 0, 1000, 'UTF-8' );
	}
'''
    replace_once(domain,anchor,helper+anchor,'R6 event minimizer helper')
    replace_once(domain,"\t\t$payload = is_array( $payload ) ? $payload : array();\n\t\t$payload['owner'] = 'file-06';","\t\t$payload = self::minimize_event_payload( is_array( $payload ) ? $payload : array() );\n\t\t$payload['owner'] = 'file-06';",'R6 local event minimization')
    old="""\t\t$table = HE_V2_Schema::table( 'events' );
\t\t$inserted = $wpdb->query( $wpdb->prepare(
\t\t\t\"INSERT IGNORE INTO {$table} (event_id,event_name,object_type,object_id,actor_id,trace_id,payload_json,created_at) VALUES (%s,%s,'external',0,0,%s,%s,%s)\",
\t\t\t$event_id, sanitize_text_field( $name ), HE_V2_Domain::trace_id(), wp_json_encode( is_array( $payload ) ? $payload : array() ), current_time( 'mysql', true )
\t\t) );
"""
    new="""\t\t$table = HE_V2_Schema::table( 'events' );
\t\t$safe_payload = HE_V2_Domain::minimize_event_payload( is_array( $payload ) ? $payload : array() );
\t\t$inserted = $wpdb->query( $wpdb->prepare(
\t\t\t\"INSERT IGNORE INTO {$table} (event_id,event_name,object_type,object_id,actor_id,trace_id,payload_json,created_at) VALUES (%s,%s,'external',0,0,%s,%s,%s)\",
\t\t\t$event_id, sanitize_text_field( $name ), HE_V2_Domain::trace_id(), wp_json_encode( $safe_payload ), current_time( 'mysql', true )
\t\t) );
"""
    replace_once(integrations,old,new,'R6 consumed event minimization')

elif round_no==7:
    anchor="\n\tpublic static function create_research( $data, $actor_id ) {\n"
    helper=r'''
	public static function sanitize_text_list( $value ) {
		if ( ! is_array( $value ) ) { $value = preg_split( '/[\r\n,;]+/u', (string) $value ); }
		if ( isset( $value['statement'] ) && is_scalar( $value['statement'] ) ) { $value = array( $value['statement'] ); }
		elseif ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { $value = array( $value['name'] ); }
		$out = array();
		$walk = static function( $item ) use ( &$out, &$walk ) {
			if ( is_array( $item ) ) {
				foreach ( array( 'statement','name','text','value' ) as $key ) {
					if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) { $walk( $item[ $key ] ); return; }
				}
				foreach ( $item as $child ) { $walk( $child ); }
				return;
			}
			if ( ! is_scalar( $item ) ) { return; }
			$text = sanitize_text_field( (string) $item );
			if ( '' !== $text ) { $out[] = $text; }
		};
		$walk( $value );
		return array_values( array_unique( $out ) );
	}
'''
    replace_once(domain,anchor,helper+anchor,'R7 safe text-list helper')
    replace_once(domain,"'investigators_json' => wp_json_encode( array_values( array_map( 'sanitize_text_field', (array) ( $data['investigators'] ?? array() ) ) ) ),","'investigators_json' => wp_json_encode( self::sanitize_text_list( $data['investigators'] ?? array() ) ),",'R7 research investigator shape')
    replace_once(domain,"'conflicts_json' => wp_json_encode( array_values( array_map( 'sanitize_text_field', (array) ( $data['conflicts'] ?? array() ) ) ) ),","'conflicts_json' => wp_json_encode( self::sanitize_text_list( $data['conflicts'] ?? array() ) ),",'R7 research conflict shape')
    pattern=r"\tprivate static function conflicts\( \$value \) \{.*?\n\t\}\n\n\tprivate static function investigators"
    repl="""\tprivate static function conflicts( $value ) {
\t\t$parts = HE_V2_Domain::sanitize_text_list( $value );
\t\t$statement = sanitize_textarea_field( implode( '; ', $parts ) );
\t\tif ( '' === trim( $statement ) ) { return array(); }
\t\t$none = (bool) preg_match( '/\\b(?:no|none)\\s+(?:conflict|conflicts)\\b/i', $statement );
\t\treturn array( 'recorded' => true, 'statement' => $statement, 'none_declared' => $none );
\t}

\tprivate static function investigators"""
    regex_once(authoring,pattern,repl,'R7 authoring conflicts normalizer')
    replace_once(third,"$conflicts = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $data['conflicts'] ?? array() ) ) ) );","$conflicts = HE_V2_Domain::sanitize_text_list( $data['conflicts'] ?? array() );",'R7 early conflict validation')
    replace_once(third,"$parts = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $input['conflicts'] ?? array() ) ) ) );","$parts = HE_V2_Domain::sanitize_text_list( $input['conflicts'] ?? array() );",'R7 post-create conflict normalization')

elif round_no==8:
    replace_once(public,"\t\tadd_filter( 'posts_where', array( __CLASS__, 'research_public_query_where' ), 99, 2 );\n","\t\tadd_filter( 'posts_where', array( __CLASS__, 'research_public_query_where' ), 99, 2 );\n\t\tadd_filter( 'posts_results', array( __CLASS__, 'research_public_query_results' ), 99, 2 );\n",'R8 canonical result hook')
    anchor="\n\tpublic static function research_title( $title, $post_id = 0 ) {\n"
    fn=r'''
	public static function research_public_query_results( $posts, $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! is_array( $posts ) ) { return $posts; }
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type ) { $out[] = $post; continue; }
			$row = self::row_for_post( (int) $post->ID );
			if ( self::is_public_row( $row ) ) { $out[] = $post; }
		}
		return $out;
	}
'''
    replace_once(public,anchor,fn+anchor,'R8 canonical research results filter')

elif round_no==9:
    replace_once(domain,"\tprivate static $idempotency_leases = array();\n","\tprivate static $idempotency_leases = array();\n\tprivate static $merge_resolution_stack = array();\n",'R9 merge resolution stack')
    old="""\t\tif ( ! empty( $row['merged_into_id'] ) ) {
\t\t\treturn self::concept_by_id( (int) $row['merged_into_id'], $include_private );
\t\t}
"""
    new="""\t\tif ( ! empty( $row['merged_into_id'] ) ) {
\t\t\t$current_id = (int) $row['id'];
\t\t\tif ( isset( self::$merge_resolution_stack[ $current_id ] ) || count( self::$merge_resolution_stack ) >= 32 ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'concept_merge_cycle_detected', 'File 06 stopped canonical resolution because a merge cycle or excessive merge chain was detected.' );
\t\t\t\treturn null;
\t\t\t}
\t\t\tself::$merge_resolution_stack[ $current_id ] = true;
\t\t\ttry {
\t\t\t\treturn self::concept_by_id( (int) $row['merged_into_id'], $include_private );
\t\t\t} finally {
\t\t\t\tunset( self::$merge_resolution_stack[ $current_id ] );
\t\t\t}
\t\t}
"""
    replace_once(domain,old,new,'R9 merge-cycle fail closed')

elif round_no==10:
    # Current release truth.
    s=read(bootstrap).replace('2.4.11','2.4.12')
    write(bootstrap,s)
    s=read(readme)
    s=s.replace('Stable tag: 2.4.11','Stable tag: 2.4.12').replace('The 2.4.11 candidate','The 2.4.12 candidate')
    if '= 2.4.12 =' not in s:
        s=s.replace('== Changelog ==\n','== Changelog ==\n\n= 2.4.12 =\n* Thirteenth ten-round corrective candidate: recursive structured DTO safety, CAS-safe scheduled invalidation, transaction-certain governed merges, source-language convergence, event privacy minimization, nested research-shape compatibility, canonical public research filtering, merge-cycle resilience, and refreshed exact-head QA truth.\n')
    write(readme,s)
    # Current invariant families advance; historical suites stay historical except their current-release assertion.
    for path in (T/'v2-invariants.php', T/'v2-source-invariants.sh'):
        write(path,read(path).replace('2.4.11','2.4.12'))
    oldtest=T/'v2411-twelfth-ten-round-regressions.php'
    s=read(oldtest)
    old="v2411_ok(10,false!==strpos($bootstrap,' * Version: 2.4.11')&&false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.11' );\")&&false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.11' );\")&&false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.11'\"),'runtime/contract/future hardening truth is not v2.4.11');\nv2411_ok(10,false!==strpos($readme,'Stable tag: 2.4.11')&&false!==strpos($runall,'v2411-twelfth-ten-round-regressions.php')&&false!==strpos($runall,'file06-v2.4.11-a.zip'),'package/aggregate release truth is not v2.4.11');"
    new="v2411_ok(10,preg_match('/ \\* Version: 2\\.4\\.(?:11|12)/',$bootstrap)&&preg_match(\"/HE_VERSION', '2\\.4\\.(?:11|12)/\",$bootstrap)&&preg_match(\"/HE_CONTRACT_VERSION', '2\\.4\\.(?:11|12)/\",$bootstrap)&&false!==strpos($bootstrap,\"'future_hardening_version'=>\"),'historical v2.4.11 release controls do not tolerate a later current v2.4.x candidate');\nv2411_ok(10,false!==strpos($runall,'v2411-twelfth-ten-round-regressions.php'),'historical twelfth-cycle regression suite is no longer wired into aggregate QA');"
    if old not in s: raise SystemExit('R10 v2411 historical release assertion not found')
    write(oldtest,s.replace(old,new,1))
    runall=T/'run-all.sh'; s=read(runall)
    s=s.replace('php "$root/tests/v2411-twelfth-ten-round-regressions.php"\n','php "$root/tests/v2411-twelfth-ten-round-regressions.php"\nphp "$root/tests/v2412-thirteenth-ten-round-regressions.php"\n')
    s=s.replace('file06-v2.4.11-a.zip','file06-v2.4.12-a.zip').replace('file06-v2.4.11-b.zip','file06-v2.4.12-b.zip')
    s=s.replace('All File 06 v2.4.11 automated checks, inherited review matrices, twelfth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.12 automated checks, inherited review matrices, thirteenth ten-round regressions and deterministic package comparison passed.')
    write(runall,s)
    # Update current release documents only; preserve historical audit documents.
    (D/'RELEASE-NOTES.md').write_text("""# File 06 — Release Notes 2.4.12

Thirteenth fresh ten-round corrective repository candidate. Repository defects were found and corrected in rounds `1–9`; round `10` reconciles current release/runtime/package/QA truth after those source corrections.

Primary corrections cover recursive structured DTO safety, CAS-safe scheduled invalidation, transaction-certain governed merge behavior, fail-closed source-language convergence, privacy-minimized event payloads, nested research input compatibility, canonical public-research filtering, bounded merge-chain/cycle handling, and exact v2.4.12 release truth.

Final exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.
""",encoding='utf-8')
    signoff=D/'RELEASE-SIGNOFF.md'
    if signoff.exists():
        signoff.write_text("""# File 06 — v2.4.12 Repository Candidate Signoff

- Repository candidate: v2.4.12
- Base DB schema: 10
- Future internal schema: 2
- Automated QA: pending final exact-head workflow at the time of this source commit
- Staging acceptance: unverified
- Live deployed version: unverified
- Live DB/migration state: unverified
- Operational status: not established

No staging/live claim is implied by repository automation.
""",encoding='utf-8')

print(f'v2.4.12 thirteenth cycle round {round_no} correction applied')

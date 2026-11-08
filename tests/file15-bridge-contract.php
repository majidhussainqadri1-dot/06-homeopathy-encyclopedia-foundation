<?php
/** Isolated File 06 -> File 15 contract tests. No WordPress/Hostinger claim. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HE_CONTRACT_VERSION', '2.4.20' );

class HE_V2_Schema {
	public static function runtime_status() { return $GLOBALS['runtime_state']; }
	public static function table( $name ) { return 'wp_he_' . $name; }
}
class HE_V2_Domain {
	const ENTRY_TYPE = 'he_entry';
	public static function concept_by_id( $id, $private = false ) { return $GLOBALS['concept']; }
	public static function is_public_concept( $row ) {
		return $row['status'] === 'published' && $row['review_status'] === 'approved'
			&& $row['safety_status'] === 'approved' && empty( $row['merged_into_id'] );
	}
	public static function public_dto( $row ) { return $GLOBALS['dto']; }
}
class HE_V22_Governance {
	public static function entry_content_hash( $row ) { return str_repeat( 'a', 64 ); }
}
class BridgeDB {
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function get_row( $query, $format = null ) {
		if ( strpos( $query[0], 'wp_he_reviews' ) !== false ) { return $GLOBALS['review']; }
		if ( strpos( $query[0], 'wp_he_events' ) !== false ) { return $GLOBALS['event']; }
		if ( strpos( $query[0], 'wp_he_concepts' ) !== false ) {
			return strpos( $query[0], 'WHERE id=%d' ) !== false ? $GLOBALS['target'] : $GLOBALS['source'];
		}
		throw new RuntimeException( 'unexpected-query' );
	}
}
function get_post( $id ) { return (object) array( 'post_author' => 10, 'post_type' => 'he_entry', 'post_status' => 'publish' ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function esc_url_raw( $value ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { public function get_error_code() { return 'rejected'; } }
function rsr_accept_platform_event( $name, $payload ) {
	$GLOBALS['received'][] = array( $name, $payload );
	return $GLOBALS['ack'];
}
function test( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL ); exit( 1 ); }
}
$wpdb = new BridgeDB();
require_once dirname( __DIR__ ) . '/homeopathy-encyclopedia/includes/class-he-file15-bridge.php';

$id = '11111111-1111-4111-8111-111111111111';
$other = '22222222-2222-4222-8222-222222222222';
$evt = '33333333-3333-4333-8333-333333333333';
$GLOBALS['runtime_state'] = 'active';
$GLOBALS['concept'] = array(
	'id' => 7, 'public_id' => $id, 'post_id' => 77, 'type_slug' => 'remedy',
	'status' => 'published', 'review_status' => 'approved', 'safety_status' => 'approved',
	'merged_into_id' => 0, 'current_version' => 12,
);
$GLOBALS['dto'] = array(
	'id' => $id, 'type' => 'remedy', 'title' => 'Test Remedy', 'summary' => 'Source-linked',
	'canonical_url' => 'https://example.org/encyclopedia/entry/test',
	'version' => 3, 'current_version' => 3, 'record_status' => 'published',
	'is_historical' => false, 'references' => array( array( 'title' => 'Primary source' ) ),
);
$GLOBALS['review'] = array( 'created_at' => '2026-11-08 10:00:00' );
$GLOBALS['event'] = array( 'object_type' => 'concept', 'object_id' => 7 );
$GLOBALS['target'] = array( 'id' => 7, 'public_id' => $id, 'type_slug' => 'remedy' );
$GLOBALS['source'] = array( 'public_id' => $other, 'type_slug' => 'remedy' );
$GLOBALS['ack'] = true;
$GLOBALS['received'] = array();

$eligible = HE_File15_Bridge::remedies( array(), array( $id, '7', $other ), 'v1' );
test( count( $eligible ) === 1 && isset( $eligible[ $id ] ), 'Only exact canonical ID eligible' );
test( $eligible[ $id ]['published'] === true && $eligible[ $id ]['eligible'] === true, 'File 15 eligibility flags' );
test( $eligible[ $id ]['version'] === '3' && $eligible[ $id ]['review_date'] === '2026-11-08', 'Current version and review date' );

$GLOBALS['concept']['status'] = 'retracted';
test( HE_File15_Bridge::remedies( array(), array( $id ), 'v1' ) === array(), 'Retracted remedy denied' );
$GLOBALS['concept']['status'] = 'published';
$GLOBALS['concept']['review_status'] = 'pending';
test( HE_File15_Bridge::remedies( array(), array( $id ), 'v1' ) === array(), 'Unreviewed remedy denied' );
$GLOBALS['concept']['review_status'] = 'approved';
$GLOBALS['review'] = null;
test( HE_File15_Bridge::remedies( array(), array( $id ), 'v1' ) === array(), 'Missing fresh review denied' );
$GLOBALS['review'] = array( 'created_at' => '2026-11-08 10:00:00' );
$GLOBALS['dto']['references'] = array();
test( HE_File15_Bridge::remedies( array(), array( $id ), 'v1' ) === array(), 'Unsourced remedy denied' );
$GLOBALS['dto']['references'] = array( array( 'title' => 'Primary source' ) );
$GLOBALS['runtime_state'] = 'migration-required';
test( HE_File15_Bridge::remedies( array(), array( $id ), 'v1' ) === array(), 'Stale schema fails closed' );
$GLOBALS['runtime_state'] = 'active';

HE_File15_Bridge::deliver( 'EncyclopediaEntryRetracted.v1', array(), $evt );
test( count( $GLOBALS['received'] ) === 1, 'Retraction delivered once' );
test( $GLOBALS['received'][0][1]['remedy_public_id'] === $id && $GLOBALS['received'][0][1]['event_id'] === $evt, 'Stable remedy and event identity' );
HE_File15_Bridge::deliver( 'KnowledgeConceptMerged.v1', array( 'source_id' => $other, 'target_id' => $id ), $evt );
test( $GLOBALS['received'][1][1]['source_remedy_public_id'] === $other, 'Merge source preserved' );
test( $GLOBALS['received'][1][1]['target_remedy_public_id'] === $id, 'Merge target preserved' );

$GLOBALS['event'] = null;
$thrown = false;
try { HE_File15_Bridge::deliver( 'EncyclopediaEntryCorrected.v1', array(), $evt ); } catch ( RuntimeException $error ) { $thrown = true; }
test( $thrown, 'Unpersisted event rejected' );
$GLOBALS['event'] = array( 'object_type' => 'concept', 'object_id' => 7 );
$GLOBALS['target']['type_slug'] = 'pathology';
$before = count( $GLOBALS['received'] );
HE_File15_Bridge::deliver( 'EncyclopediaEntryPublished.v1', array(), $evt );
test( $before === count( $GLOBALS['received'] ), 'Non-remedy knowledge never delivered' );
$GLOBALS['target']['type_slug'] = 'remedy';
$GLOBALS['ack'] = new WP_Error();
$thrown = false;
try { HE_File15_Bridge::deliver( 'EncyclopediaEntryPublished.v1', array(), $evt ); } catch ( RuntimeException $error ) { $thrown = true; }
test( $thrown, 'File 15 rejected delivery causes retry' );
echo "File 06/File 15 contract: 14 isolated safety and identity checks PASS.\n";

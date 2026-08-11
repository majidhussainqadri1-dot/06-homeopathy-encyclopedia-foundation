#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def text(path):
    return (ROOT / path).read_text(encoding='utf-8')


def write(path, data):
    (ROOT / path).write_text(data, encoding='utf-8')


def replace_once(data, old, new, label):
    count = data.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one occurrence, found {count}')
    return data.replace(old, new, 1)


def replace_block(data, start, end, replacement, label):
    i = data.find(start)
    if i < 0:
        raise SystemExit(f'{label}: start marker missing')
    j = data.find(end, i + len(start))
    if j < 0:
        raise SystemExit(f'{label}: end marker missing')
    return data[:i] + replacement.rstrip() + '\n\n\t' + data[j:]


# 1) Candidate identity: source fixes are a new contract revision; schema remains 10.
p = 'homeopathy-encyclopedia/homeopathy-encyclopedia.php'
s = text(p)
for old, new, label in [
    ('Version: 2.4.2', 'Version: 2.4.3', 'bootstrap header'),
    ("define( 'HE_VERSION', '2.4.2' );", "define( 'HE_VERSION', '2.4.3' );", 'runtime version'),
    ("define( 'HE_CONTRACT_VERSION', '2.4.2' );", "define( 'HE_CONTRACT_VERSION', '2.4.3' );", 'contract version'),
    ("'future_hardening_version'=>'2.4.2'", "'future_hardening_version'=>'2.4.3'", 'contract hardening version'),
]:
    s = replace_once(s, old, new, label)
write(p, s)

# 2) Core migration option lock: atomic acquisition, CAS stale takeover, token-aware release.
p = 'homeopathy-encyclopedia/includes/class-he-v2-schema.php'
s = text(p)
if 'private static $migration_lock_token' not in s:
    s = replace_once(s, "\tconst OPTION_MIGRATION_LOCK = 'he_v2_migration_lock';\n", "\tconst OPTION_MIGRATION_LOCK = 'he_v2_migration_lock';\n\tprivate static $migration_lock_token = '';\n", 'core lock token property')
core_lock = r'''private static function acquire_lock() {
		global $wpdb;
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'time' => time() );
		if ( add_option( self::OPTION_MIGRATION_LOCK, $value, '', false ) ) {
			self::$migration_lock_token = $token;
			return true;
		}
		$existing = get_option( self::OPTION_MIGRATION_LOCK );
		if ( ! is_array( $existing ) || empty( $existing['time'] ) || ( time() - (int) $existing['time'] ) <= 300 ) {
			return false;
		}
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
			self::OPTION_MIGRATION_LOCK,
			maybe_serialize( $existing )
		) );
		if ( 1 !== (int) $deleted || ! add_option( self::OPTION_MIGRATION_LOCK, $value, '', false ) ) {
			return false;
		}
		self::$migration_lock_token = $token;
		return true;
	}

	private static function release_lock() {
		global $wpdb;
		if ( ! self::$migration_lock_token ) {
			return;
		}
		$current = get_option( self::OPTION_MIGRATION_LOCK );
		if ( is_array( $current ) && ! empty( $current['token'] ) && hash_equals( (string) $current['token'], self::$migration_lock_token ) ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
				self::OPTION_MIGRATION_LOCK,
				maybe_serialize( $current )
			) );
		}
		self::$migration_lock_token = '';
	}'''
s = replace_block(s, 'private static function acquire_lock()', 'public static function maybe_upgrade()', core_lock, 'core migration lock')
write(p, s)

# 3) v2.2 upgrade lock: same stale-owner protection; previous unconditional release could delete a successor lock.
p = 'homeopathy-encyclopedia/includes/class-he-v22-governance.php'
s = text(p)
if 'private static $upgrade_lock_token' not in s:
    s = replace_once(s, "\tconst BATCH_SIZE = 50;\n", "\tconst BATCH_SIZE = 50;\n\tprivate static $upgrade_lock_token = '';\n", 'v22 lock token property')
v22_lock = r'''/** Atomic option insertion plus compare-and-delete stale takeover prevents one worker from deleting another worker's lease. */
	private static function acquire_lock() {
		global $wpdb;
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'time' => time() );
		if ( add_option( self::LOCK_OPTION, $value, '', false ) ) {
			self::$upgrade_lock_token = $token;
			return true;
		}
		$existing = get_option( self::LOCK_OPTION );
		if ( ! is_array( $existing ) || empty( $existing['time'] ) || time() - (int) $existing['time'] <= 600 ) {
			return false;
		}
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
			self::LOCK_OPTION,
			maybe_serialize( $existing )
		) );
		if ( 1 !== (int) $deleted || ! add_option( self::LOCK_OPTION, $value, '', false ) ) {
			return false;
		}
		self::$upgrade_lock_token = $token;
		return true;
	}

	private static function release_lock() {
		global $wpdb;
		if ( ! self::$upgrade_lock_token ) {
			return;
		}
		$current = get_option( self::LOCK_OPTION );
		if ( is_array( $current ) && ! empty( $current['token'] ) && hash_equals( (string) $current['token'], self::$upgrade_lock_token ) ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
				self::LOCK_OPTION,
				maybe_serialize( $current )
			) );
		}
		self::$upgrade_lock_token = '';
	}'''
s = replace_block(s, '/** Atomic option insertion avoids the read-then-update migration-lock race. */\n\tprivate static function acquire_lock()', 'private static function column_exists', v22_lock, 'v22 upgrade lock')
write(p, s)

# 4) Future migration pre/postflight is itself serialized, with token-safe stale takeover/release.
p = 'homeopathy-encyclopedia/includes/class-he-v24-migration-safety.php'
s = text(p)
if "OPTION_LEASE = 'he_v24_migration_lease'" not in s:
    s = replace_once(s, "\tconst OPTION_PENDING = 'he_v24_migration_pending';\n", "\tconst OPTION_PENDING = 'he_v24_migration_pending';\n\tconst OPTION_LEASE = 'he_v24_migration_lease';\n\tconst LEASE_TTL = 15 * MINUTE_IN_SECONDS;\n\tprivate static $lease_token = '';\n", 'v24 migration lease constants')
lease_helpers = r'''private static function acquire_lease() {
		global $wpdb;
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'time' => time() );
		if ( add_option( self::OPTION_LEASE, $value, '', false ) ) {
			self::$lease_token = $token;
			return true;
		}
		$existing = get_option( self::OPTION_LEASE );
		if ( ! is_array( $existing ) || empty( $existing['time'] ) || time() - (int) $existing['time'] <= self::LEASE_TTL ) {
			return false;
		}
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
			self::OPTION_LEASE,
			maybe_serialize( $existing )
		) );
		if ( 1 !== (int) $deleted || ! add_option( self::OPTION_LEASE, $value, '', false ) ) {
			return false;
		}
		self::$lease_token = $token;
		return true;
	}

	private static function release_lease() {
		global $wpdb;
		if ( ! self::$lease_token ) { return; }
		$current = get_option( self::OPTION_LEASE );
		if ( is_array( $current ) && ! empty( $current['token'] ) && hash_equals( (string) $current['token'], self::$lease_token ) ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",
				self::OPTION_LEASE,
				maybe_serialize( $current )
			) );
		}
		self::$lease_token = '';
	}

	public static function activate() {
		if ( ! self::acquire_lease() ) {
			return false;
		}
		try {
			if ( ! self::preflight() ) {
				update_option( self::OPTION_PENDING, 1, false );
				HE_V2_Schema::record_runtime_failure( 'future_migration_pending', 'File 06 Future-18 preflight migration is progressing in bounded batches; Future-18 routes remain fail-closed.' );
				return false;
			}
			if ( (int) get_option( HE_V24_Future_Schema::OPTION_VERSION, 0 ) < HE_V24_Future_Schema::VERSION ) {
				HE_V24_Future_Schema::install();
			}
			if ( ! self::postflight() ) {
				update_option( self::OPTION_PENDING, 1, false );
				HE_V2_Schema::record_runtime_failure( 'future_migration_pending', 'File 06 Future-18 postflight reconciliation is progressing in bounded batches; Future-18 routes remain fail-closed.' );
				return false;
			}
			delete_option( self::OPTION_PENDING );
			$failure = get_option( HE_V2_Schema::OPTION_FAILURE, array() );
			if ( is_array( $failure ) && 'future_migration_pending' === ( $failure['code'] ?? '' ) ) { delete_option( HE_V2_Schema::OPTION_FAILURE ); }
			return true;
		} finally {
			self::release_lease();
		}
	}'''
s = replace_block(s, 'public static function activate()', 'public static function maybe_upgrade()', lease_helpers, 'v24 serialized migration')
write(p, s)

# 5) Public/current-version reference truth and immutable snapshot reference binding.
p = 'homeopathy-encyclopedia/includes/class-he-v2-domain.php'
s = text(p)
s = replace_once(s,
    "' WHERE concept_id=%d AND (version_id=0 OR version_id=%d) ORDER BY id ASC'",
    "' WHERE concept_id=%d AND version_id=%d ORDER BY id ASC'",
    'public DTO exact-version references')
s = replace_once(s,
    "'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d', $row['id']",
    "'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND (version_id=0 OR version_id=%d)', $row['id'], (int) $row['current_version']",
    'review reference set')
reference_snapshot = r'''private static function bind_references_to_snapshot( $concept_id, $previous_version_id, $new_version_id, $actor_id ) {
		global $wpdb;
		$table = HE_V2_Schema::table( 'references' );
		$concept_id = absint( $concept_id ); $previous_version_id = absint( $previous_version_id ); $new_version_id = absint( $new_version_id );
		if ( ! $concept_id || ! $new_version_id ) { return; }
		/* Pending draft references become immutable members of the new snapshot first. */
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET version_id=%d WHERE concept_id=%d AND version_id=0", $new_version_id, $concept_id ) );
		if ( ! $previous_version_id || $previous_version_id === $new_version_id ) { return; }
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND version_id=%d ORDER BY id ASC", $concept_id, $previous_version_id ), ARRAY_A );
		foreach ( $rows as $ref ) {
			if ( ! is_array( $ref ) ) { continue; }
			$exists = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE concept_id=%d AND version_id=%d AND source_type=%s AND title=%s AND edition=%s AND page_locator=%s AND url=%s AND doi=%s LIMIT 1",
				$concept_id, $new_version_id, $ref['source_type'], $ref['title'], $ref['edition'], $ref['page_locator'], $ref['url'], $ref['doi']
			) );
			if ( $exists ) { continue; }
			unset( $ref['id'] );
			$ref['version_id'] = $new_version_id;
			$ref['created_by'] = absint( $actor_id );
			$ref['created_at'] = current_time( 'mysql', true );
			$wpdb->insert( $table, $ref );
		}
	}

	public static function snapshot_version( $concept_id, $reason, $status, $actor_id ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) { return 0; }
		$post = get_post( (int) $row['post_id'] );
		$structured = get_post_meta( $post->ID, '_he_structured', true );
		$structured = is_array( $structured ) ? $structured : array();
		$version_number = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version_number) FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE concept_id=%d', $row['id'] ) );
		$body = (string) $post->post_content;
		$hash = hash( 'sha256', wp_json_encode( array( $post->post_title, $post->post_excerpt, $body, $structured ) ) );
		$ok = $wpdb->insert( HE_V2_Schema::table( 'versions' ), array(
			'concept_id' => $row['id'], 'version_number' => $version_number, 'status' => sanitize_key( $status ),
			'title' => $post->post_title, 'summary' => $post->post_excerpt, 'body' => $body,
			'structured_json' => wp_json_encode( $structured ), 'content_hash' => $hash,
			'change_reason' => sanitize_textarea_field( $reason ), 'effective_at' => current_time( 'mysql', true ),
			'created_by' => absint( $actor_id ), 'created_at' => current_time( 'mysql', true ),
		) );
		if ( ! $ok ) { return 0; }
		$new_version_id = (int) $wpdb->insert_id;
		self::bind_references_to_snapshot( $row['id'], (int) $row['current_version'], $new_version_id, $actor_id );
		return $new_version_id;
	}'''
s = replace_block(s, 'public static function snapshot_version', 'public static function create_integrity_action', reference_snapshot, 'snapshot reference binding')

# Domain relation command is the invariant owner, including internal merge paths.
relation_block = r'''public static function add_relation( $source_id, $target_id, $type, $reference_id, $actor_id ) {
		global $wpdb;
		$type = sanitize_key( $type );
		$source_id = absint( $source_id ); $target_id = absint( $target_id ); $reference_id = absint( $reference_id );
		if ( ! in_array( $type, self::relation_types(), true ) || $source_id === $target_id ) {
			return new WP_Error( 'he_invalid_relation', __( 'Invalid knowledge relationship.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$source = self::concept_by_id( $source_id, true ); $target = self::concept_by_id( $target_id, true );
		if ( ! $source || ! $target ) {
			return new WP_Error( 'he_relation_target_missing', __( 'Relationship concepts could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		if ( ! $reference_id ) {
			return new WP_Error( 'he_relation_provenance_required', __( 'Every knowledge relationship requires source-version provenance.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$reference = $wpdb->get_row( $wpdb->prepare( 'SELECT id,concept_id,version_id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d', $reference_id ), ARRAY_A );
		if ( ! $reference || (int) $reference['concept_id'] !== $source_id || ( (int) $reference['version_id'] !== 0 && (int) $reference['version_id'] !== (int) $source['current_version'] ) || ( ! $source['current_version'] && (int) $reference['version_id'] !== 0 ) ) {
			return new WP_Error( 'he_relation_provenance_invalid', __( 'Relationship provenance must be pending for the next source snapshot or bound to the current source version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$now = current_time( 'mysql', true );
		$ok = $wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . HE_V2_Schema::table( 'relations' ) . ' (source_concept_id,target_concept_id,relation_type,owner_file,source_reference_id,status,row_version,created_by,created_at,updated_at) VALUES (%d,%d,%s,%s,%d,%s,1,%d,%s,%s) ON DUPLICATE KEY UPDATE source_reference_id=VALUES(source_reference_id),status=\'active\',row_version=row_version+1,updated_at=VALUES(updated_at)',
			$source_id, $target_id, $type, 'file-06', $reference_id, 'active', absint( $actor_id ), $now, $now
		) );
		return false !== $ok ? true : new WP_Error( 'he_relation_write_failed', __( 'The knowledge relationship could not be stored.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
	}

	public static function graph( $concept_id, $depth = 1, $limit = 50 ) {'''
s = replace_block(s, 'public static function add_relation', 'public static function graph( $concept_id, $depth = 1, $limit = 50 ) {', relation_block, 'domain relation provenance')
# Only current-version-provenance edges can leave the domain as current graph truth.
s = replace_once(s,
    "'SELECT * FROM ' . HE_V2_Schema::table( 'relations' ) . \" WHERE status='active' AND (source_concept_id=%d OR target_concept_id=%d) LIMIT %d\"",
    "'SELECT r.* FROM ' . HE_V2_Schema::table( 'relations' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' sc ON sc.id=r.source_concept_id INNER JOIN ' . HE_V2_Schema::table( 'references' ) . \" ref ON ref.id=r.source_reference_id AND ref.concept_id=r.source_concept_id AND ref.version_id=sc.current_version WHERE r.status='active' AND sc.current_version>0 AND (r.source_concept_id=%d OR r.target_concept_id=%d) LIMIT %d\"",
    'core graph current provenance')
s = replace_once(s,
    "\t\t\t\t\tself::add_relation( $new_source, $new_target, $edge['relation_type'], $edge['source_reference_id'], $actor_id );\n",
    "\t\t\t\t\t$relation_result = self::add_relation( $new_source, $new_target, $edge['relation_type'], $edge['source_reference_id'], $actor_id );\n\t\t\t\t\tif ( is_wp_error( $relation_result ) ) { throw new RuntimeException( $relation_result->get_error_message() ); }\n",
    'merge relation failure handling')
write(p, s)

# 6) REST reference/version provenance at the early graph guard.
p = 'homeopathy-encyclopedia/includes/class-he-v242-reference-graph.php'
s = text(p)
s = replace_once(s,
    "$belongs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d', $reference_id, (int) $source['id'] ) );\n\t\t\tif ( ! $belongs ) { return new WP_Error( 'he_relation_provenance_invalid', __( 'Relationship provenance must reference a source belonging to the source concept.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }",
    "$reference = $wpdb->get_row( $wpdb->prepare( 'SELECT id,version_id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d', $reference_id, (int) $source['id'] ), ARRAY_A );\n\t\t\tif ( ! $reference || ( (int) $reference['version_id'] !== 0 && (int) $reference['version_id'] !== (int) $source['current_version'] ) || ( ! $source['current_version'] && (int) $reference['version_id'] !== 0 ) ) { return new WP_Error( 'he_relation_provenance_invalid', __( 'Relationship provenance must be pending for the next source snapshot or bound to the current source version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }",
    'REST graph provenance version')
write(p, s)

# 7) Claim evidence gets provider-qualified external identity and current-version reference binding.
p = 'homeopathy-encyclopedia/includes/class-he-v24-future-api.php'
s = text(p)
claim_evidence = r'''private static function external_evidence_token( $provider, $external_id ) {
		$provider = sanitize_key( $provider );
		$external_id = sanitize_text_field( $external_id );
		return $provider && $external_id ? $provider . '|' . rawurlencode( $external_id ) : '';
	}

	public static function external_evidence_token_parts( $token ) {
		$token = (string) $token; $pos = strpos( $token, '|' );
		if ( false === $pos ) { return null; }
		$provider = sanitize_key( substr( $token, 0, $pos ) );
		$external_id = sanitize_text_field( rawurldecode( substr( $token, $pos + 1 ) ) );
		if ( ! $provider || ! $external_id || ! HE_V24_Future_Schema::validate_external_id( $provider, $external_id ) ) { return null; }
		return array( 'provider' => $provider, 'external_id' => $external_id );
	}

	public static function rest_claim_evidence( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-claim-evidence-' . absint( $request['id'] ), HE_V2_Auth::CAP_REVIEW );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 200 ); }
		global $wpdb; $data = self::request_data( $request );
		$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE id=%d', absint( $request['id'] ) ), ARRAY_A );
		if ( ! $claim ) { return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Claim not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $claim['concept_id'], false );
		if ( ! $concept || ! $claim['version_id'] || (int) $claim['version_id'] !== (int) $concept['current_version'] ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_claim_version_gate', __( 'Evidence may be linked only to a claim bound to the current concept version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$relation = sanitize_key( $data['relation'] ?? '' );
		if ( ! in_array( $relation, array( 'supports','contradicts','uncertain','historical' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_relation_invalid', __( 'Invalid claim-evidence relation.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$reference_id = absint( $data['reference_id'] ?? 0 ); $external_id = sanitize_text_field( $data['external_id'] ?? '' ); $external_token = '';
		if ( ! $reference_id && ! $external_id ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_evidence_required', __( 'A governed reference or staged external record is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		if ( $reference_id ) {
			$valid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d AND version_id=%d', $reference_id, $claim['concept_id'], $claim['version_id'] ) );
			if ( ! $valid ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_reference_invalid', __( 'The reference must belong to the same current concept version as the claim.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		}
		$provider = sanitize_key( $data['external_provider'] ?? '' );
		if ( $external_id ) {
			$canonical_external = HE_V24_Future_Schema::validate_external_id( $provider, $external_id );
			if ( ! $provider || ! $canonical_external ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_external_provider_required', __( 'External claim evidence requires an explicit valid provider and provider-specific identifier.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
			$valid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . " WHERE provider=%s AND external_id=%s AND concept_id=%d AND ((object_type='claim' AND object_id=%d) OR object_type='concept') ORDER BY id DESC LIMIT 1", $provider, $canonical_external, $claim['concept_id'], $claim['id'] ) );
			if ( ! $valid ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_external_evidence_invalid', __( 'The exact provider record must first be staged against this claim or its concept.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
			$external_token = self::external_evidence_token( $provider, $canonical_external );
		}
		$weight = max( -1, min( 1, (float) ( $data['weight'] ?? 0 ) ) );
		$ok = $wpdb->replace( HE_V24_Future_Schema::table( 'claim_evidence' ), array( 'claim_id' => (int) $claim['id'], 'reference_id' => $reference_id, 'external_id' => $external_token, 'relation' => $relation, 'weight' => $weight, 'note' => sanitize_textarea_field( $data['note'] ?? '' ), 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_evidence_write_failed', __( 'The evidence link could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		$wpdb->query( $wpdb->prepare( "UPDATE " . HE_V24_Future_Schema::table( 'claims' ) . " SET evidence_state='linked',review_status='pending',reviewed_by=0,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d", $claim['id'] ) );
		HE_V24_Future_Schema::append_provenance( 'claim', (string) $claim['id'], 'evidence.linked', '', array( 'relation' => $relation, 'reference_id' => $reference_id, 'external_provider' => $provider, 'external_id' => $external_id ) );
		return self::mutation_finish( $reservation, array( 'saved' => true, 'claim_id' => $claim['public_id'], 'review_status' => 'pending' ), 201 );
	}'''
s = replace_block(s, 'public static function rest_claim_evidence', 'public static function rest_claim_review', claim_evidence, 'claim evidence provider/version')

# Object-aware authorization for the exact binding happens before provider network I/O.
external_permission = r'''private static function external_binding_permission( $binding ) {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( 'research' === $binding['object_type'] ) {
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', $binding['object_id'] ) );
			return $post_id ? HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, $post_id, 'file06-future-external-stage-research' ) : new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$concept = HE_V24_Future_Schema::concept_row( $binding['concept_id'], false );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		if ( ! HE_V241_Governance::editor_type_allowed( $user_id, $concept['type_slug'] ) ) { return new WP_Error( 'he_editor_type_scope_required', __( 'This actor is not assigned to this knowledge type.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, (int) $concept['post_id'], 'file06-future-external-stage' );
	}

	public static function rest_external_lookup( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'future-external-stage', HE_V2_Auth::CAP_RESEARCH );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::mutation_finish( $reservation, null, 201 ); }
		global $wpdb; $data = self::request_data( $request );
		$provider = sanitize_key( $data['provider'] ?? '' );
		$external_id = HE_V24_Future_Schema::validate_external_id( $provider, $data['external_id'] ?? '' );
		if ( ! $external_id ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_external_id_invalid', __( 'The scholarly identifier is invalid for this provider.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ) ); }
		$binding = self::resolve_external_binding( $data );
		if ( is_wp_error( $binding ) ) { return self::mutation_finish( $reservation, $binding ); }
		$permission = self::external_binding_permission( $binding );
		if ( is_wp_error( $permission ) ) { return self::mutation_finish( $reservation, $permission ); }
		if ( 'clinicaltrials' === $provider && ! in_array( $binding['object_type'], array( 'claim','research' ), true ) ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_trial_binding_invalid', __( 'Clinical-trial evidence must be bound to a claim or research record.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) ); }
		$metadata = HE_V24_Future_Schema::lookup_external( $provider, $external_id );
		if ( is_wp_error( $metadata ) ) { return self::mutation_finish( $reservation, $metadata ); }
		$relation = sanitize_key( $data['relation'] ?? $data['purpose'] ?? 'literature' ); $purpose = sanitize_key( $data['purpose'] ?? 'literature' );
		$table = HE_V24_Future_Schema::table( 'external_records' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE provider=%s AND external_id=%s AND object_type=%s AND object_id=%d", $provider, $external_id, $binding['object_type'], $binding['object_id'] ), ARRAY_A );
		$row = array( 'provider' => $provider, 'external_id' => $external_id, 'concept_id' => $binding['concept_id'], 'object_type' => $binding['object_type'], 'object_id' => $binding['object_id'], 'relation' => $relation, 'purpose' => $purpose, 'status' => 'staged', 'metadata_json' => wp_json_encode( $metadata ), 'checked_at' => current_time( 'mysql', true ), 'review_required' => 1 );
		if ( $existing ) { $ok = $wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) ); $record_id = (int) $existing['id']; } else { $ok = $wpdb->insert( $table, $row ); $record_id = (int) $wpdb->insert_id; }
		if ( false === $ok ) { return self::mutation_finish( $reservation, new WP_Error( 'he_future_external_stage_failed', __( 'The scholarly metadata could not be staged.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		HE_V24_Future_Schema::append_provenance( 'external-record', (string) $record_id, 'metadata.staged', '', array( 'provider' => $provider, 'external_id' => $external_id, 'binding' => $binding, 'source_hash' => hash( 'sha256', wp_json_encode( $metadata ) ) ) );
		return self::mutation_finish( $reservation, array( 'id' => $record_id, 'provider' => $provider, 'external_id' => $external_id, 'binding' => $binding, 'status' => 'staged', 'review_required' => true, 'metadata' => $metadata ), $existing ? 200 : 201 );
	}'''
s = replace_block(s, 'public static function rest_external_lookup', 'public static function rest_retraction_watch', external_permission, 'external staging object authorization')

# Future public graph and citation exports are current-version provenance only.
s = replace_once(s,
    "'SELECT relation_type,owner_file,source_concept_id,target_concept_id,source_reference_id FROM ' . HE_V2_Schema::table( 'relations' ) . \" WHERE (source_concept_id=%d OR target_concept_id=%d) AND status='active' ORDER BY id DESC LIMIT 300\"",
    "'SELECT r.relation_type,r.owner_file,r.source_concept_id,r.target_concept_id,r.source_reference_id FROM ' . HE_V2_Schema::table( 'relations' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' sc ON sc.id=r.source_concept_id INNER JOIN ' . HE_V2_Schema::table( 'references' ) . \" sr ON sr.id=r.source_reference_id AND sr.concept_id=r.source_concept_id AND sr.version_id=sc.current_version WHERE (r.source_concept_id=%d OR r.target_concept_id=%d) AND r.status='active' AND sc.current_version>0 ORDER BY r.id DESC LIMIT 300\"",
    'future graph current provenance')
s = replace_once(s,
    "'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,quotation_word_count,link_status FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d ORDER BY id ASC LIMIT 500'",
    "'SELECT r.source_type,r.author,r.title,r.edition,r.volume,r.page_locator,r.publisher,r.year,r.url,r.doi,r.evidence_grade,r.rights_status,r.quotation_word_count,r.link_status FROM ' . HE_V2_Schema::table( 'references' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' c ON c.id=r.concept_id AND r.version_id=c.current_version WHERE r.concept_id=%d ORDER BY r.id ASC LIMIT 500'",
    'citation current version')
write(p, s)

# 8) Public claims: exact current version, no internal DB version IDs, provider-qualified external evidence only.
p = 'homeopathy-encyclopedia/includes/class-he-v24-future-schema.php'
s = text(p)
public_claims = r'''public static function public_claims( $concept_id ) {
		global $wpdb;
		$concept = self::concept_row( $concept_id, true );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'The requested knowledge record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT c.id AS internal_claim_id,c.public_id,c.claim_key,c.claim_text,c.claim_state,c.evidence_state,c.confidence,c.review_status,v.version_number FROM ' . self::table( 'claims' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'versions' ) . " v ON v.id=c.version_id AND v.concept_id=c.concept_id WHERE c.concept_id=%d AND c.claim_state='active' AND c.review_status='approved' AND c.version_id=%d AND EXISTS (SELECT 1 FROM " . self::table( 'claim_evidence' ) . " e WHERE e.claim_id=c.id) ORDER BY c.id ASC LIMIT 300",
			$concept['id'], $concept['current_version']
		), ARRAY_A );
		$out = array();
		foreach ( $rows as $row ) {
			$claim_id = (int) $row['internal_claim_id']; unset( $row['internal_claim_id'] );
			$evidence = $wpdb->get_results( $wpdb->prepare( 'SELECT relation,reference_id,external_id,weight,note FROM ' . self::table( 'claim_evidence' ) . ' WHERE claim_id=%d ORDER BY id ASC LIMIT 100', $claim_id ), ARRAY_A );
			$safe_evidence = array();
			foreach ( $evidence as $link ) {
				$item = array( 'relation' => $link['relation'], 'weight' => (float) $link['weight'] );
				if ( ! empty( $link['reference_id'] ) ) {
					$ref = $wpdb->get_row( $wpdb->prepare( 'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,link_status FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d AND version_id=%d', absint( $link['reference_id'] ), $concept['id'], $concept['current_version'] ), ARRAY_A );
					if ( $ref ) { $item['reference'] = $ref; }
				} elseif ( ! empty( $link['external_id'] ) ) {
					$parts = HE_V24_Future_API::external_evidence_token_parts( $link['external_id'] );
					if ( $parts ) {
						$reviewed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'external_records' ) . " WHERE provider=%s AND external_id=%s AND concept_id=%d AND ((object_type='claim' AND object_id=%d) OR object_type='concept') AND status='reviewed' AND review_required=0 ORDER BY id DESC LIMIT 1", $parts['provider'], $parts['external_id'], $concept['id'], $claim_id ) );
						if ( $reviewed ) { $item['external'] = array( 'provider' => $parts['provider'], 'external_id' => $parts['external_id'] ); }
					}
				}
				if ( ! empty( $item['reference'] ) || ! empty( $item['external'] ) ) { $safe_evidence[] = $item; }
			}
			if ( ! $safe_evidence ) { continue; }
			$row['version_number'] = (int) $row['version_number']; $row['confidence'] = (float) $row['confidence']; $row['evidence'] = $safe_evidence; $out[] = $row;
		}
		return $out;
	}'''
s = replace_block(s, 'public static function public_claims', 'public static function append_provenance', public_claims, 'safe public claims')

# Tamper-evident provenance chain: serialize parent-read/hash/insert so concurrent writers cannot fork one parent.
provenance = r'''public static function append_provenance( $type, $id, $action, $source_uri = '', $metadata = array(), $actor_id = 0 ) {
		global $wpdb;
		$table = self::table( 'provenance' );
		$lock_name = substr( $wpdb->prefix . 'he_v24_provenance_chain', 0, 64 );
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock_name ) );
		if ( 1 !== $locked ) {
			HE_V2_Schema::record_runtime_failure( 'provenance_chain_busy', 'File 06 could not acquire the provenance-chain serialization lock.' );
			return false;
		}
		try {
			$parent = (string) $wpdb->get_var( "SELECT record_hash FROM {$table} WHERE record_hash<>'' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$created = current_time( 'mysql', true );
			$source_hash = ! empty( $metadata['source_hash'] ) ? preg_replace( '/[^a-f0-9]/i', '', (string) $metadata['source_hash'] ) : '';
			$transform = ! empty( $metadata['transform'] ) ? sanitize_key( $metadata['transform'] ) : '';
			$metadata_json = wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$hash_payload = wp_json_encode( array( 'parent_hash'=>$parent,'object_type'=>sanitize_key($type),'object_id'=>sanitize_text_field($id),'action'=>sanitize_key($action),'source_uri'=>esc_url_raw($source_uri),'source_hash'=>$source_hash,'transform'=>$transform,'metadata_json'=>$metadata_json,'created_at'=>$created ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$record_hash = hash( 'sha256', $hash_payload );
			$ok = $wpdb->insert( $table, array( 'object_type'=>sanitize_key($type),'object_id'=>sanitize_text_field($id),'action'=>sanitize_key($action),'actor_id'=>absint($actor_id ?: get_current_user_id()),'source_uri'=>esc_url_raw($source_uri),'source_hash'=>$source_hash,'transform'=>$transform,'metadata_json'=>$metadata_json,'parent_hash'=>$parent,'record_hash'=>$record_hash,'created_at'=>$created ) );
			return $ok ? $record_hash : false;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}'''
s = replace_block(s, 'public static function append_provenance', 'public static function public_provenance', provenance, 'serialized provenance')

# Gap metrics are about the current published knowledge version, not historical evidence accumulated across versions.
old_gap = "\t\t$refs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d', $concept['id'] ) );\n\t\t$broken = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . \" WHERE concept_id=%d AND link_status IN ('broken','error')\", $concept['id'] ) );\n\t\t$claims = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' WHERE concept_id=%d', $concept['id'] ) );\n\t\t$without_evidence = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' c WHERE c.concept_id=%d AND c.review_status=\\'approved\\' AND NOT EXISTS (SELECT 1 FROM ' . self::table( 'claim_evidence' ) . ' e WHERE e.claim_id=c.id)', $concept['id'] ) );\n\t\t$contradictions = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . self::table( 'claim_evidence' ) . \" e INNER JOIN \" . self::table( 'claims' ) . \" c ON c.id=e.claim_id WHERE c.concept_id=%d AND e.relation='contradicts'\", $concept['id'] ) );"
new_gap = "\t\t$current_version = absint( $concept['current_version'] );\n\t\t$refs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', $concept['id'], $current_version ) );\n\t\t$broken = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . \" WHERE concept_id=%d AND version_id=%d AND link_status IN ('broken','error')\", $concept['id'], $current_version ) );\n\t\t$claims = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' WHERE concept_id=%d AND version_id=%d', $concept['id'], $current_version ) );\n\t\t$without_evidence = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . ' c WHERE c.concept_id=%d AND c.version_id=%d AND c.review_status=\\'approved\\' AND NOT EXISTS (SELECT 1 FROM ' . self::table( 'claim_evidence' ) . ' e WHERE e.claim_id=c.id)', $concept['id'], $current_version ) );\n\t\t$contradictions = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM \" . self::table( 'claim_evidence' ) . \" e INNER JOIN \" . self::table( 'claims' ) . \" c ON c.id=e.claim_id WHERE c.concept_id=%d AND c.version_id=%d AND e.relation='contradicts'\", $concept['id'], $current_version ) );"
s = replace_once(s, old_gap, new_gap, 'current-version research gap metrics')
write(p, s)

# 9) Claim approval gate validates every evidence item against the same current version and exact external provider record.
p = 'homeopathy-encyclopedia/includes/class-he-v24-future-review-guard.php'
s = text(p)
claim_gate = r'''private static function claim_approval_gate( $claim_id, $response ) {
		global $wpdb;
		$claim = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V24_Future_Schema::table( 'claims' ) . ' WHERE id=%d', $claim_id ), ARRAY_A );
		if ( ! $claim ) { return new WP_Error( 'he_not_found', __( 'Claim not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$concept = HE_V24_Future_Schema::concept_row( $claim['concept_id'], false );
		if ( ! $concept || ! $claim['version_id'] || (int) $claim['version_id'] !== (int) $concept['current_version'] || ! HE_V24_Future_Schema::version_belongs( $claim['concept_id'], $claim['version_id'] ) ) {
			return new WP_Error( 'he_future_claim_version_gate', __( 'A public claim must be bound to the current governed concept version before approval.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$links = $wpdb->get_results( $wpdb->prepare( 'SELECT reference_id,external_id FROM ' . HE_V24_Future_Schema::table( 'claim_evidence' ) . ' WHERE claim_id=%d ORDER BY id ASC LIMIT 100', $claim_id ), ARRAY_A );
		if ( ! $links ) { return new WP_Error( 'he_future_claim_evidence_required', __( 'A claim cannot be approved without governed current-version evidence.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		foreach ( $links as $link ) {
			if ( ! empty( $link['reference_id'] ) ) {
				$valid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d AND version_id=%d', absint( $link['reference_id'] ), $claim['concept_id'], $claim['version_id'] ) );
				if ( ! $valid ) { return new WP_Error( 'he_future_reference_version_gate', __( 'Linked internal evidence must belong to the same current concept version as the claim.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
				continue;
			}
			$parts = HE_V24_Future_API::external_evidence_token_parts( $link['external_id'] ?? '' );
			if ( ! $parts ) { return new WP_Error( 'he_future_external_relink_required', __( 'Legacy or ambiguous external evidence must be re-linked with an explicit provider before approval.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
			$reviewed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V24_Future_Schema::table( 'external_records' ) . " WHERE provider=%s AND external_id=%s AND concept_id=%d AND ((object_type='claim' AND object_id=%d) OR object_type='concept') AND status='reviewed' AND review_required=0 ORDER BY id DESC LIMIT 1", $parts['provider'], $parts['external_id'], $claim['concept_id'], $claim_id ) );
			if ( ! $reviewed ) { return new WP_Error( 'he_future_external_review_required', __( 'The exact linked external scholarly record must receive human review before the claim can be approved.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ); }
		}
		return $response;
	}'''
s = replace_block(s, 'private static function claim_approval_gate', 'private static function guard', claim_gate, 'claim approval evidence binding')
write(p, s)

# 10) Explicit destructive uninstall also clears the new Future migration lease.
p = 'homeopathy-encyclopedia/uninstall.php'
s = text(p)
if "'he_v24_migration_lease'" not in s:
    s = replace_once(s, "'he_v241_core_maintenance_lease','he_v241_future_maintenance_lease',", "'he_v241_core_maintenance_lease','he_v241_future_maintenance_lease','he_v24_migration_lease',", 'uninstall migration lease')
write(p, s)

# 11) Version-sensitive automated source checks move to the v2.4.3 contract while historical reports remain untouched.
version_files = [
    'tests/v2-invariants.php', 'tests/v2-source-invariants.sh', 'tests/v23-future-invariants.php',
    'tests/v24-80-round-invariants.php', 'tests/v241-second-80-invariants.php', 'tests/v242-third-80-final.php',
    'tests/run-all.sh', '.github/workflows/file06-v2-complete.yml'
]
for p in version_files:
    s = text(p)
    s = s.replace('2.4.2', '2.4.3')
    write(p, s)

print('File 06 v2.4.3 root-cause source hardening applied.')

<?php
/** Migration preflight/postflight for File 06 v2.4 Future-18 schema hardening. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Migration_Safety {
	const BATCH = 100;
	const OPTION_PROVENANCE_CURSOR = 'he_v24_provenance_migration_cursor';
	const OPTION_IMPACT_CURSOR = 'he_v24_impact_migration_cursor';
	const OPTION_PENDING = 'he_v24_migration_pending';

	public static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) { return false; }
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function index_exists( $table, $index ) {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) { return false; }
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name=%s", $index ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Advance at most one bounded migration batch per legacy table.
	 * Returns true only when every destructive/schema-sensitive preflight is complete.
	 */
	public static function preflight() {
		global $wpdb;
		$external = HE_V24_Future_Schema::table( 'external_records' );
		if ( self::index_exists( $external, 'provider_external' ) ) {
			$wpdb->query( "ALTER TABLE `{$external}` DROP INDEX `provider_external`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$provenance_done = true;
		$provenance = HE_V24_Future_Schema::table( 'provenance' );
		if ( self::table_exists( $provenance ) ) {
			if ( ! self::column_exists( $provenance, 'parent_hash' ) ) {
				$wpdb->query( "ALTER TABLE `{$provenance}` ADD COLUMN `parent_hash` char(64) NOT NULL DEFAULT '' AFTER `metadata_json`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( ! self::column_exists( $provenance, 'record_hash' ) ) {
				$wpdb->query( "ALTER TABLE `{$provenance}` ADD COLUMN `record_hash` char(64) NOT NULL DEFAULT '' AFTER `parent_hash`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$provenance_done = self::backfill_provenance_batch();
		}

		$impact_done = true;
		$impact = HE_V24_Future_Schema::table( 'impact_queue' );
		if ( self::table_exists( $impact ) ) {
			if ( ! self::column_exists( $impact, 'dedupe_key' ) ) {
				$wpdb->query( "ALTER TABLE `{$impact}` ADD COLUMN `dedupe_key` char(64) NULL AFTER `consumer_file`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$impact_done = self::backfill_impact_batch();
		}
		return $provenance_done && $impact_done;
	}

	/** Rebuild the legacy provenance chain deterministically in bounded, resumable ID order. */
	private static function backfill_provenance_batch() {
		global $wpdb;
		$table = HE_V24_Future_Schema::table( 'provenance' );
		$cursor = absint( get_option( self::OPTION_PROVENANCE_CURSOR, 0 ) );
		$parent = '';
		if ( $cursor ) {
			$parent = (string) $wpdb->get_var( $wpdb->prepare( "SELECT record_hash FROM `{$table}` WHERE id=%d", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' === $parent ) {
				/* Lost/partial cursor state: safely restart and overwrite the chain from the beginning. */
				$cursor = 0;
				delete_option( self::OPTION_PROVENANCE_CURSOR );
			}
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id>%d ORDER BY id ASC LIMIT %d", $cursor, self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $rows ) {
			delete_option( self::OPTION_PROVENANCE_CURSOR );
			return true;
		}
		foreach ( $rows as $row ) {
			$payload = wp_json_encode( array(
				'parent_hash' => $parent,
				'object_type' => sanitize_key( $row['object_type'] ),
				'object_id' => sanitize_text_field( $row['object_id'] ),
				'action' => sanitize_key( $row['action'] ),
				'source_uri' => esc_url_raw( $row['source_uri'] ),
				'source_hash' => preg_replace( '/[^a-f0-9]/i', '', (string) $row['source_hash'] ),
				'transform' => sanitize_key( $row['transform'] ),
				'metadata_json' => (string) $row['metadata_json'],
				'created_at' => (string) $row['created_at'],
			), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$hash = hash( 'sha256', $payload );
			$updated = $wpdb->update( $table, array( 'parent_hash' => $parent, 'record_hash' => $hash ), array( 'id' => (int) $row['id'] ) );
			if ( false === $updated ) {
				throw new RuntimeException( 'File 06 provenance migration write failed at row ' . (int) $row['id'] );
			}
			$parent = $hash;
			$cursor = (int) $row['id'];
		}
		$more = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id>%d ORDER BY id ASC LIMIT 1", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $more ) {
			update_option( self::OPTION_PROVENANCE_CURSOR, $cursor, false );
			return false;
		}
		delete_option( self::OPTION_PROVENANCE_CURSOR );
		return true;
	}

	/** Backfill legacy impact idempotency keys without unbounded table materialization. */
	private static function backfill_impact_batch() {
		global $wpdb;
		$table = HE_V24_Future_Schema::table( 'impact_queue' );
		$cursor = absint( get_option( self::OPTION_IMPACT_CURSOR, 0 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,source_type,source_id,event_name,consumer_file,payload_json FROM `{$table}` WHERE id>%d ORDER BY id ASC LIMIT %d", $cursor, self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $rows ) {
			delete_option( self::OPTION_IMPACT_CURSOR );
			return true;
		}
		foreach ( $rows as $row ) {
			$key = hash( 'sha256', 'legacy-v23|' . $row['id'] . '|' . $row['source_type'] . '|' . $row['source_id'] . '|' . $row['event_name'] . '|' . $row['consumer_file'] . '|' . $row['payload_json'] );
			$updated = $wpdb->update( $table, array( 'dedupe_key' => $key ), array( 'id' => (int) $row['id'] ) );
			if ( false === $updated ) {
				throw new RuntimeException( 'File 06 impact migration write failed at row ' . (int) $row['id'] );
			}
			$cursor = (int) $row['id'];
		}
		$more = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id>%d ORDER BY id ASC LIMIT 1", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $more ) {
			update_option( self::OPTION_IMPACT_CURSOR, $cursor, false );
			return false;
		}
		delete_option( self::OPTION_IMPACT_CURSOR );
		return true;
	}

	public static function postflight() {
		global $wpdb;
		$external = HE_V24_Future_Schema::table( 'external_records' );
		if ( self::index_exists( $external, 'provider_external' ) ) {
			$wpdb->query( "ALTER TABLE `{$external}` DROP INDEX `provider_external`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$mappings = HE_V24_Future_Schema::table( 'concept_mappings' );
		if ( self::table_exists( $mappings ) ) {
			$wpdb->query( "UPDATE `{$mappings}` SET mapping_state='legacy-invalid' WHERE vocabulary='orcid' AND mapping_state<>'legacy-invalid'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$impact = HE_V24_Future_Schema::table( 'impact_queue' );
		if ( self::table_exists( $impact ) ) {
			$wpdb->query( "UPDATE `{$impact}` SET impact_state='retry',last_error='legacy v2.3 emission lacked consumer acknowledgement',next_attempt_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE impact_state='emitted'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Advance a bounded upgrade step. A large legacy dataset keeps Future-18
	 * fail-closed until later requests finish the resumable migration.
	 */
	public static function activate() {
		if ( ! self::preflight() ) {
			update_option( self::OPTION_PENDING, 1, false );
			HE_V2_Schema::record_runtime_failure( 'future_migration_pending', 'File 06 Future-18 migration is progressing in bounded batches; Future-18 routes remain fail-closed.' );
			return false;
		}
		HE_V24_Future_Schema::install();
		self::postflight();
		delete_option( self::OPTION_PENDING );
		$failure = get_option( HE_V2_Schema::OPTION_FAILURE, array() );
		if ( is_array( $failure ) && 'future_migration_pending' === ( $failure['code'] ?? '' ) ) {
			delete_option( HE_V2_Schema::OPTION_FAILURE );
		}
		return true;
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( HE_V24_Future_Schema::OPTION_VERSION, 0 ) < HE_V24_Future_Schema::VERSION ) {
			return self::activate();
		}
		return true;
	}
}

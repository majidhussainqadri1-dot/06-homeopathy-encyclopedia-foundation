<?php
/** Migration preflight/postflight for File 06 v2.4+ Future-18 schema hardening. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Migration_Safety {
	const BATCH = 100;
	const OPTION_PROVENANCE_CURSOR = 'he_v24_provenance_migration_cursor';
	const OPTION_IMPACT_CURSOR = 'he_v24_impact_migration_cursor';
	const OPTION_PROVENANCE_DONE = 'he_v24_provenance_migration_done';
	const OPTION_IMPACT_DONE = 'he_v24_impact_migration_done';
	const OPTION_ORCID_CURSOR = 'he_v24_orcid_postflight_cursor';
	const OPTION_EMITTED_CURSOR = 'he_v24_emitted_postflight_cursor';
	const OPTION_ORCID_DONE = 'he_v24_orcid_postflight_done';
	const OPTION_EMITTED_DONE = 'he_v24_emitted_postflight_done';
	const OPTION_PENDING = 'he_v24_migration_pending';
	const OPTION_LEASE = 'he_v24_migration_lease';
	const LEASE_TTL = 15 * MINUTE_IN_SECONDS;
	private static $lease_token = '';

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

	/** Advance at most one bounded migration batch per unfinished legacy table. */
	public static function preflight() {
		global $wpdb;
		$external = HE_V24_Future_Schema::table( 'external_records' );
		if ( self::index_exists( $external, 'provider_external' ) ) {
			$wpdb->query( "ALTER TABLE `{$external}` DROP INDEX `provider_external`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$provenance_done = (bool) get_option( self::OPTION_PROVENANCE_DONE, false );
		$provenance = HE_V24_Future_Schema::table( 'provenance' );
		if ( ! $provenance_done && self::table_exists( $provenance ) ) {
			if ( ! self::column_exists( $provenance, 'parent_hash' ) ) {
				$wpdb->query( "ALTER TABLE `{$provenance}` ADD COLUMN `parent_hash` char(64) NOT NULL DEFAULT '' AFTER `metadata_json`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( ! self::column_exists( $provenance, 'record_hash' ) ) {
				$wpdb->query( "ALTER TABLE `{$provenance}` ADD COLUMN `record_hash` char(64) NOT NULL DEFAULT '' AFTER `parent_hash`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$provenance_done = self::backfill_provenance_batch();
			if ( $provenance_done ) { update_option( self::OPTION_PROVENANCE_DONE, 1, false ); }
		} elseif ( ! self::table_exists( $provenance ) ) {
			$provenance_done = true;
			update_option( self::OPTION_PROVENANCE_DONE, 1, false );
		}

		$impact_done = (bool) get_option( self::OPTION_IMPACT_DONE, false );
		$impact = HE_V24_Future_Schema::table( 'impact_queue' );
		if ( ! $impact_done && self::table_exists( $impact ) ) {
			if ( ! self::column_exists( $impact, 'dedupe_key' ) ) {
				$wpdb->query( "ALTER TABLE `{$impact}` ADD COLUMN `dedupe_key` char(64) NULL AFTER `consumer_file`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$impact_done = self::backfill_impact_batch();
			if ( $impact_done ) { update_option( self::OPTION_IMPACT_DONE, 1, false ); }
		} elseif ( ! self::table_exists( $impact ) ) {
			$impact_done = true;
			update_option( self::OPTION_IMPACT_DONE, 1, false );
		}
		return $provenance_done && $impact_done;
	}

	private static function backfill_provenance_batch() {
		global $wpdb;
		$table = HE_V24_Future_Schema::table( 'provenance' );
		$cursor = absint( get_option( self::OPTION_PROVENANCE_CURSOR, 0 ) );
		$parent = '';
		if ( $cursor ) {
			$parent = (string) $wpdb->get_var( $wpdb->prepare( "SELECT record_hash FROM `{$table}` WHERE id=%d", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( '' === $parent ) {
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
			if ( false === $updated ) { throw new RuntimeException( 'File 06 provenance migration write failed at row ' . (int) $row['id'] ); }
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
			if ( false === $updated ) { throw new RuntimeException( 'File 06 impact migration write failed at row ' . (int) $row['id'] ); }
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

	/** Bounded, resumable reconciliation that must finish before Future routes are considered ready. */
	public static function postflight() {
		global $wpdb;
		$external = HE_V24_Future_Schema::table( 'external_records' );
		if ( self::index_exists( $external, 'provider_external' ) ) {
			$wpdb->query( "ALTER TABLE `{$external}` DROP INDEX `provider_external`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$orcid_done = (bool) get_option( self::OPTION_ORCID_DONE, false );
		$mappings = HE_V24_Future_Schema::table( 'concept_mappings' );
		if ( ! $orcid_done && self::table_exists( $mappings ) ) {
			$cursor = absint( get_option( self::OPTION_ORCID_CURSOR, 0 ) );
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$mappings}` WHERE id>%d AND vocabulary='orcid' AND mapping_state<>'legacy-invalid' ORDER BY id ASC LIMIT %d", $cursor, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $ids as $id ) {
				$updated = $wpdb->update( $mappings, array( 'mapping_state' => 'legacy-invalid' ), array( 'id' => absint( $id ) ) );
				if ( false === $updated ) { throw new RuntimeException( 'File 06 ORCID postflight write failed at row ' . absint( $id ) ); }
				$cursor = absint( $id );
			}
			$more = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$mappings}` WHERE id>%d AND vocabulary='orcid' AND mapping_state<>'legacy-invalid' ORDER BY id ASC LIMIT 1", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $more ) {
				update_option( self::OPTION_ORCID_CURSOR, $cursor, false );
			} else {
				delete_option( self::OPTION_ORCID_CURSOR );
				update_option( self::OPTION_ORCID_DONE, 1, false );
				$orcid_done = true;
			}
		} elseif ( ! self::table_exists( $mappings ) ) {
			$orcid_done = true;
			update_option( self::OPTION_ORCID_DONE, 1, false );
		}

		$emitted_done = (bool) get_option( self::OPTION_EMITTED_DONE, false );
		$impact = HE_V24_Future_Schema::table( 'impact_queue' );
		if ( ! $emitted_done && self::table_exists( $impact ) ) {
			$cursor = absint( get_option( self::OPTION_EMITTED_CURSOR, 0 ) );
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$impact}` WHERE id>%d AND impact_state='emitted' ORDER BY id ASC LIMIT %d", $cursor, self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $ids as $id ) {
				$updated = $wpdb->update( $impact, array( 'impact_state' => 'retry', 'last_error' => 'legacy v2.3 emission lacked consumer acknowledgement', 'next_attempt_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $id ), 'impact_state' => 'emitted' ) );
				if ( false === $updated ) { throw new RuntimeException( 'File 06 impact postflight write failed at row ' . absint( $id ) ); }
				$cursor = absint( $id );
			}
			$more = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$impact}` WHERE id>%d AND impact_state='emitted' ORDER BY id ASC LIMIT 1", $cursor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $more ) {
				update_option( self::OPTION_EMITTED_CURSOR, $cursor, false );
			} else {
				delete_option( self::OPTION_EMITTED_CURSOR );
				update_option( self::OPTION_EMITTED_DONE, 1, false );
				$emitted_done = true;
			}
		} elseif ( ! self::table_exists( $impact ) ) {
			$emitted_done = true;
			update_option( self::OPTION_EMITTED_DONE, 1, false );
		}
		return $orcid_done && $emitted_done;
	}

	public static function ready() {
		return HE_V24_Future_Schema::schema_complete()
			&& (int) get_option( HE_V24_Future_Schema::OPTION_VERSION, 0 ) >= HE_V24_Future_Schema::VERSION
			&& (bool) get_option( self::OPTION_PROVENANCE_DONE, false )
			&& (bool) get_option( self::OPTION_IMPACT_DONE, false )
			&& (bool) get_option( self::OPTION_ORCID_DONE, false )
			&& (bool) get_option( self::OPTION_EMITTED_DONE, false )
			&& ! get_option( self::OPTION_PENDING );
	}

	private static function acquire_lease() {
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
	}

	public static function maybe_upgrade() {
		if ( ! self::ready() ) { return self::activate(); }
		return true;
	}
}

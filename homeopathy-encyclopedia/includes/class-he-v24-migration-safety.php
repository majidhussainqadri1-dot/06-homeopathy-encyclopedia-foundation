<?php
/** Migration preflight/postflight for File 06 v2.4 Future-18 schema hardening. */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Migration_Safety {
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

	public static function preflight() {
		global $wpdb;
		$external = HE_V24_Future_Schema::table( 'external_records' );
		if ( self::index_exists( $external, 'provider_external' ) ) {
			$wpdb->query( "ALTER TABLE `{$external}` DROP INDEX `provider_external`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$provenance = HE_V24_Future_Schema::table( 'provenance' );
		if ( self::table_exists( $provenance ) ) {
			if ( ! self::column_exists( $provenance, 'parent_hash' ) ) {
				$wpdb->query( "ALTER TABLE `{$provenance}` ADD COLUMN `parent_hash` char(64) NOT NULL DEFAULT '' AFTER `metadata_json`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( ! self::column_exists( $provenance, 'record_hash' ) ) {
				$wpdb->query( "ALTER TABLE `{$provenance}` ADD COLUMN `record_hash` char(64) NOT NULL DEFAULT '' AFTER `parent_hash`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			self::backfill_provenance();
		}

		$impact = HE_V24_Future_Schema::table( 'impact_queue' );
		if ( self::table_exists( $impact ) ) {
			if ( ! self::column_exists( $impact, 'dedupe_key' ) ) {
				$wpdb->query( "ALTER TABLE `{$impact}` ADD COLUMN `dedupe_key` char(64) NULL AFTER `consumer_file`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$rows = $wpdb->get_results( "SELECT id,source_type,source_id,event_name,consumer_file,payload_json FROM `{$impact}` WHERE dedupe_key IS NULL OR dedupe_key='' ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rows as $row ) {
				$key = hash( 'sha256', 'legacy-v23|' . $row['id'] . '|' . $row['source_type'] . '|' . $row['source_id'] . '|' . $row['event_name'] . '|' . $row['consumer_file'] . '|' . $row['payload_json'] );
				$wpdb->update( $impact, array( 'dedupe_key' => $key ), array( 'id' => (int) $row['id'] ) );
			}
		}
	}

	private static function backfill_provenance() {
		global $wpdb;
		$table = HE_V24_Future_Schema::table( 'provenance' );
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$parent = '';
		foreach ( $rows as $row ) {
			if ( ! empty( $row['record_hash'] ) ) { $parent = $row['record_hash']; continue; }
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
			$wpdb->update( $table, array( 'parent_hash' => $parent, 'record_hash' => $hash ), array( 'id' => (int) $row['id'] ) );
			$parent = $hash;
		}
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

	public static function activate() {
		self::preflight();
		HE_V24_Future_Schema::install();
		self::postflight();
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( HE_V24_Future_Schema::OPTION_VERSION, 0 ) < HE_V24_Future_Schema::VERSION ) {
			self::activate();
		}
	}
}

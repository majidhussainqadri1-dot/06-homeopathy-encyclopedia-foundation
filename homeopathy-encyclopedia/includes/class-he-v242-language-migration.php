<?php
/** File 06 v2.4.2 bounded migration from legacy ur-PK translation/source codes to canonical ur. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Language_Migration {
	const DONE = 'he_v242_language_migration_done';
	const CONFLICTS = 'he_v242_language_migration_conflicts';
	const CURSOR = 'he_v242_language_migration_cursor';
	const LOCK = 'he_v242_language_migration_lock';
	const BATCH = 50;

	public static function hooks() {
		add_action( 'admin_init', array( __CLASS__, 'run_bounded' ), 15 );
		add_action( 'he_v2_maintenance', array( __CLASS__, 'run_bounded' ), 95 );
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'contract' ), 540 );
	}

	private static function lock() {
		$token = array( 'time' => time(), 'token' => wp_generate_uuid4() );
		if ( add_option( self::LOCK, $token, '', false ) ) { return true; }
		$existing = get_option( self::LOCK );
		if ( is_array( $existing ) && ! empty( $existing['time'] ) && time() - (int) $existing['time'] > 300 ) {
			delete_option( self::LOCK );
			return add_option( self::LOCK, $token, '', false );
		}
		return false;
	}

	private static function unlock() { delete_option( self::LOCK ); }

	public static function ready() { return (bool) get_option( self::DONE, false ); }

	public static function run_bounded() {
		if ( self::ready() || ! class_exists( 'HE_V24_Future_Schema' ) || ! class_exists( 'HE_V24_Migration_Safety' ) ) { return; }
		$translations = HE_V24_Future_Schema::table( 'translations' );
		if ( ! HE_V24_Migration_Safety::table_exists( $translations ) || ! self::lock() ) { return; }
		global $wpdb;
		try {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,concept_id,locale,source_locale FROM {$translations} WHERE (locale='ur-PK' OR source_locale='ur-PK') AND id>%d ORDER BY id ASC LIMIT %d", absint( get_option( self::CURSOR, 0 ) ), self::BATCH ), ARRAY_A );
			$conflicts = (array) get_option( self::CONFLICTS, array() );
			$last = absint( get_option( self::CURSOR, 0 ) );
			foreach ( $rows as $row ) {
				$last = (int) $row['id'];
				if ( 'ur-PK' === $row['locale'] ) {
					$other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$translations} WHERE concept_id=%d AND locale='ur' AND id<>%d", (int) $row['concept_id'], (int) $row['id'] ) );
					if ( $other ) {
						$conflicts[ 'translation:' . $row['id'] ] = array( 'legacy_id' => (int) $row['id'], 'canonical_id' => $other, 'concept_id' => (int) $row['concept_id'] );
						continue;
					}
				}
				$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$translations} SET locale=IF(locale='ur-PK','ur',locale),source_locale=IF(source_locale='ur-PK','ur',source_locale),updated_at=UTC_TIMESTAMP() WHERE id=%d", (int) $row['id'] ) );
				if ( false === $changed ) {
					update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
					HE_V2_Schema::record_runtime_failure( 'language_translation_migration_write_failed', 'A legacy translation locale could not be normalized; the migration cursor was not advanced past the failed row.' );
					return;
				}
			}
			update_option( self::CONFLICTS, $conflicts, false );
			update_option( self::CURSOR, $rows ? $last : 0, false );
			if ( count( $rows ) === self::BATCH ) { return; }

			/* Normalize concept/alias source-language codes only when uniqueness remains valid. */
			$concepts = HE_V2_Schema::table( 'concepts' ); $aliases = HE_V2_Schema::table( 'aliases' );
			$concept_rows = $wpdb->get_results( "SELECT id FROM {$concepts} WHERE language='ur-PK' ORDER BY id ASC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $concept_rows as $concept ) {
				$alias_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,normalized_alias FROM {$aliases} WHERE concept_id=%d AND language='ur-PK'", (int) $concept['id'] ), ARRAY_A );
				$blocked = false;
				foreach ( $alias_rows as $alias ) {
					$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT concept_id FROM {$aliases} WHERE normalized_alias=%s AND language='ur' AND concept_id<>%d LIMIT 1", $alias['normalized_alias'], (int) $concept['id'] ) );
					if ( $owner ) { $conflicts[ 'alias:' . $alias['id'] ] = array( 'legacy_alias_id' => (int) $alias['id'], 'conflicting_concept_id' => $owner, 'concept_id' => (int) $concept['id'] ); $blocked = true; }
				}
				if ( $blocked ) { continue; }
				$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$concepts} WHERE id=%d", (int) $concept['id'] ) );
				if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
					update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
					HE_V2_Schema::record_runtime_failure( 'language_concept_migration_transaction_failed', 'A canonical language migration transaction could not start safely.' );
					return;
				}
				try {
					$alias_changed = $wpdb->update( $aliases, array( 'language' => 'ur' ), array( 'concept_id' => (int) $concept['id'], 'language' => 'ur-PK' ), array( '%s' ), array( '%d','%s' ) );
					if ( false === $alias_changed ) { throw new RuntimeException( 'alias-write' ); }
					$concept_changed = $wpdb->update( $concepts, array( 'language' => 'ur', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $concept['id'] ), array( '%s','%s' ), array( '%d' ) );
					if ( false === $concept_changed ) { throw new RuntimeException( 'concept-write' ); }
					if ( $post_id ) {
						update_post_meta( $post_id, '_he_language', 'ur' );
						clean_post_cache( $post_id );
						if ( 'ur' !== (string) get_post_meta( $post_id, '_he_language', true ) ) { throw new RuntimeException( 'post-meta-write' ); }
					}
					if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'commit' ); }
				} catch ( Throwable $error ) {
					$wpdb->query( 'ROLLBACK' );
					if ( $post_id ) { clean_post_cache( $post_id ); }
					update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
					HE_V2_Schema::record_runtime_failure( 'language_concept_migration_write_failed', 'Concept, alias and post-meta language normalization could not be persisted atomically.' );
					return;
				}
			}
			update_option( self::CONFLICTS, $conflicts, false );
			$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$translations} WHERE locale='ur-PK' OR source_locale='ur-PK'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$concepts} WHERE language='ur-PK'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$remaining += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$aliases} WHERE language='ur-PK'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( 0 === $remaining ) {
				/* Actual database state is authoritative; stale conflict markers must not block completion after manual reconciliation. */
				update_option( self::DONE, 1, false );
				delete_option( self::CURSOR );
				delete_option( self::CONFLICTS );
			} elseif ( $conflicts ) {
				HE_V2_Schema::record_runtime_failure( 'language_migration_conflict', 'Legacy ur-PK normalization found a canonical-language collision; automatic destructive reconciliation was refused.' );
			}
		} finally {
			self::unlock();
		}
	}

	public static function contract( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		if ( isset( $contracts['file-06'] ) && is_array( $contracts['file-06'] ) ) {
			$contracts['file-06']['multilingual_migration'] = array( 'legacy_locale' => 'ur-PK', 'canonical_locale' => 'ur', 'bounded' => true, 'batch' => self::BATCH, 'non_destructive_conflict_quarantine' => true, 'ready' => self::ready() );
		}
		return $contracts;
	}
}

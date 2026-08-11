<?php
/** Canonical schema, migrations, activation, health and repair primitives. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Schema {
	const OPTION_SCHEMA = 'he_schema_version';
	const OPTION_FAILURE = 'he_v2_runtime_failure';
	const OPTION_SAFE_MODE = 'he_v2_safe_mode';
	const OPTION_MIGRATION_LOCK = 'he_v2_migration_lock';
	private static $migration_lock_token = '';

	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'he_' . sanitize_key( $suffix );
	}

	public static function activate() {
		if ( ! self::acquire_lock() ) {
			throw new RuntimeException( 'File 06 activation is already running.' );
		}
		try {
			HE_V2_Domain::register_types();
			self::install();
			HE_V2_Auth::install_caps();
			flush_rewrite_rules( false );
			if ( ! wp_next_scheduled( 'he_v2_maintenance' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'he_v2_maintenance' );
			}
			delete_option( self::OPTION_FAILURE );
		} catch ( Throwable $error ) {
			self::record_runtime_failure( 'activation_failed', $error->getMessage() );
			throw $error;
		} finally {
			self::release_lock();
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'he_v2_maintenance' );
		flush_rewrite_rules( false );
	}

	private static function acquire_lock() {
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
	}

	public static function maybe_upgrade() {
		$current = (int) get_option( self::OPTION_SCHEMA, 0 );
		if ( $current >= HE_SCHEMA_VERSION ) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			return;
		}
		try {
			self::install();
			delete_option( self::OPTION_FAILURE );
		} finally {
			self::release_lock();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$sql = array();
		$sql[] = "CREATE TABLE " . self::table( 'concepts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type_slug varchar(80) NOT NULL,
			canonical_slug varchar(191) NOT NULL,
			language varchar(20) NOT NULL DEFAULT 'en-US',
			status varchar(32) NOT NULL DEFAULT 'draft',
			safety_status varchar(32) NOT NULL DEFAULT 'unreviewed',
			review_status varchar(32) NOT NULL DEFAULT 'unreviewed',
			current_version bigint(20) unsigned NOT NULL DEFAULT 0,
			merged_into_id bigint(20) unsigned NOT NULL DEFAULT 0,
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY canonical_slug (canonical_slug),
			UNIQUE KEY post_id (post_id),
			KEY type_status (type_slug,status),
			KEY language_status (language,status),
			KEY merged_into_id (merged_into_id)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'aliases' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			alias varchar(191) NOT NULL,
			normalized_alias varchar(191) NOT NULL,
			language varchar(20) NOT NULL DEFAULT 'en-US',
			alias_type varchar(32) NOT NULL DEFAULT 'synonym',
			is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY alias_language (normalized_alias,language),
			KEY concept_id (concept_id),
			KEY alias_type (alias_type)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'versions' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			version_number bigint(20) unsigned NOT NULL,
			status varchar(32) NOT NULL,
			title text NOT NULL,
			summary longtext NOT NULL,
			body longtext NOT NULL,
			structured_json longtext NOT NULL,
			content_hash char(64) NOT NULL,
			change_reason text NOT NULL,
			effective_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY concept_version (concept_id,version_number),
			KEY concept_status (concept_id,status),
			KEY content_hash (content_hash)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'references' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			concept_id bigint(20) unsigned NOT NULL,
			version_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_type varchar(40) NOT NULL,
			author varchar(191) NOT NULL DEFAULT '',
			title text NOT NULL,
			edition varchar(100) NOT NULL DEFAULT '',
			volume varchar(40) NOT NULL DEFAULT '',
			page_locator varchar(100) NOT NULL DEFAULT '',
			publisher varchar(191) NOT NULL DEFAULT '',
			year varchar(20) NOT NULL DEFAULT '',
			url text NOT NULL,
			doi varchar(191) NOT NULL DEFAULT '',
			evidence_grade varchar(20) NOT NULL DEFAULT 'ungraded',
			rights_status varchar(40) NOT NULL DEFAULT 'citation-only',
			quotation_word_count int(10) unsigned NOT NULL DEFAULT 0,
			link_status varchar(20) NOT NULL DEFAULT 'unchecked',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY concept_version (concept_id,version_id),
			KEY evidence_grade (evidence_grade),
			KEY doi (doi)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'relations' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_concept_id bigint(20) unsigned NOT NULL,
			target_concept_id bigint(20) unsigned NOT NULL,
			relation_type varchar(60) NOT NULL,
			owner_file varchar(40) NOT NULL DEFAULT 'file-06',
			source_reference_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY relation_unique (source_concept_id,target_concept_id,relation_type),
			KEY target_relation (target_concept_id,relation_type),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'reviews' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(30) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			reviewer_id bigint(20) unsigned NOT NULL,
			scope varchar(40) NOT NULL,
			decision varchar(30) NOT NULL,
			conflict_declared tinyint(1) unsigned NOT NULL DEFAULT 0,
			note longtext NOT NULL,
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY object_scope (object_type,object_id,scope),
			KEY reviewer_id (reviewer_id),
			KEY decision (decision)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'integrity_actions' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			object_type varchar(30) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			action_type varchar(30) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'submitted',
			reason longtext NOT NULL,
			evidence longtext NOT NULL,
			replacement_object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			appeal_status varchar(30) NOT NULL DEFAULT '',
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY public_id (public_id),
			KEY object_action (object_type,object_id,action_type),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'research' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			record_type varchar(40) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'proposal',
			title text NOT NULL,
			question longtext NOT NULL,
			protocol longtext NOT NULL,
			investigators_json longtext NOT NULL,
			ethics_json longtext NOT NULL,
			consent_json longtext NOT NULL,
			conflicts_json longtext NOT NULL,
			data_class varchar(30) NOT NULL DEFAULT 'restricted',
			case_anonymized tinyint(1) unsigned NOT NULL DEFAULT 0,
			case_consent_verified tinyint(1) unsigned NOT NULL DEFAULT 0,
			case_tag varchar(100) NOT NULL DEFAULT '',
			case_json longtext NOT NULL,
			metadata_json longtext NOT NULL,
			row_version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY post_id (post_id),
			KEY type_status (record_type,status),
			KEY data_class (data_class)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'dataset_access' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			research_id bigint(20) unsigned NOT NULL,
			requester_id bigint(20) unsigned NOT NULL,
			purpose text NOT NULL,
			lawful_basis varchar(60) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'requested',
			approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY research_requester (research_id,requester_id),
			KEY status_expiry (status,expires_at)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id char(36) NOT NULL,
			event_name varchar(100) NOT NULL,
			object_type varchar(30) NOT NULL,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			trace_id varchar(64) NOT NULL,
			payload_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY event_id (event_id),
			KEY event_object (event_name,object_type,object_id),
			KEY trace_id (trace_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'outbox' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id char(36) NOT NULL,
			event_name varchar(100) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts int(10) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			last_error text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY event_id (event_id),
			KEY status_next (status,next_attempt_at)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'idempotency' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL,
			operation varchar(80) NOT NULL,
			idempotency_key varchar(128) NOT NULL,
			request_hash char(64) NOT NULL,
			response_code int(10) unsigned NOT NULL DEFAULT 0,
			response_json longtext NOT NULL,
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY actor_operation_key (actor_id,operation,idempotency_key),
			KEY expires_at (expires_at)
		) {$charset};";


		$sql[] = "CREATE TABLE " . self::table( 'bookmarks' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			concept_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY user_concept (user_id,concept_id),
			KEY concept_id (concept_id),
			KEY user_created (user_id,created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'rate_limits' ) . " (
			rate_key char(64) NOT NULL,
			window_start datetime NOT NULL,
			hit_count int(10) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY (rate_key),
			KEY expires_at (expires_at)
		) {$charset};";

		$sql[] = "CREATE TABLE " . self::table( 'search_index' ) . " (
			concept_id bigint(20) unsigned NOT NULL,
			first_letter varchar(8) NOT NULL DEFAULT '',
			type_slug varchar(80) NOT NULL DEFAULT '',
			body_system varchar(80) NOT NULL DEFAULT '',
			language varchar(20) NOT NULL DEFAULT 'en-US',
			source_grade varchar(20) NOT NULL DEFAULT '',
			review_status varchar(32) NOT NULL DEFAULT '',
			safety_status varchar(32) NOT NULL DEFAULT '',
			search_text longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (concept_id),
			KEY first_letter (first_letter),
			KEY type_slug (type_slug),
			KEY body_system (body_system),
			KEY language (language),
			KEY review_status (review_status),
			KEY safety_status (safety_status)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::assert_schema();
		self::migrate_legacy();
		update_option( self::OPTION_SCHEMA, HE_SCHEMA_VERSION, false );
	}

	private static function assert_schema() {
		global $wpdb;
		$required = array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'reviews', 'integrity_actions', 'research', 'dataset_access', 'events', 'outbox', 'idempotency', 'bookmarks', 'rate_limits', 'search_index' );
		foreach ( $required as $suffix ) {
			$table = self::table( $suffix );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				throw new RuntimeException( sprintf( 'Required File 06 table unavailable: %s', $suffix ) );
			}
		}
	}

	private static function migrate_legacy() {
		if ( get_option( 'he_v2_legacy_migrated' ) ) {
			return;
		}
		$query = new WP_Query( array(
			'post_type'      => 'he_entry',
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		) );
		foreach ( $query->posts as $post_id ) {
			HE_V2_Domain::ensure_concept_for_post( $post_id );
		}
		update_option( 'he_v2_legacy_migrated', 1, false );
	}

	public static function record_runtime_failure( $code, $message ) {
		update_option( self::OPTION_FAILURE, array(
			'code' => sanitize_key( $code ),
			'message' => sanitize_text_field( $message ),
			'time' => gmdate( 'c' ),
		), false );
	}

	public static function runtime_status() {
		$failure = get_option( self::OPTION_FAILURE );
		if ( is_array( $failure ) && ! empty( $failure['code'] ) ) {
			return 'degraded';
		}
		if ( get_option( self::OPTION_SAFE_MODE ) ) {
			return 'safe-mode';
		}
		return (int) get_option( self::OPTION_SCHEMA, 0 ) === HE_SCHEMA_VERSION ? 'active' : 'migration-required';
	}

	public static function health() {
		global $wpdb;
		$tables = array();
		foreach ( array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'research', 'outbox', 'bookmarks', 'rate_limits', 'search_index' ) as $suffix ) {
			$table = self::table( $suffix );
			$tables[ $suffix ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}
		return array(
			'status' => self::runtime_status(),
			'plugin_version' => HE_VERSION,
			'schema_version' => (int) get_option( self::OPTION_SCHEMA, 0 ),
			'expected_schema' => HE_SCHEMA_VERSION,
			'tables' => $tables,
			'file00' => function_exists( 'smc_user_status' ),
			'file20' => defined( 'SABRI_SHELL_VERSION' ),
			'cron' => (bool) wp_next_scheduled( 'he_v2_maintenance' ),
			'pending_outbox' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table( 'outbox' ) . " WHERE status IN ('pending','retry')" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'failure' => get_option( self::OPTION_FAILURE, array() ),
		);
	}

	public static function repair( $dry_run = true ) {
		$before = self::health();
		$result = array( 'dry_run' => (bool) $dry_run, 'before' => $before, 'actions' => array() );
		if ( ! $dry_run ) {
			self::install();
			HE_V2_Domain::reindex_all();
			delete_option( self::OPTION_FAILURE );
			$result['actions'][] = 'schema-verified';
			$result['actions'][] = 'search-index-rebuilt';
			$result['after'] = self::health();
		}
		return $result;
	}
}

<?php
/** Database schema and low-level persistence helpers. */

defined( 'ABSPATH' ) || exit;

final class HE_Database {
	const OPTION = 'he_schema_version';

	/** Install or upgrade schema idempotently. */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$wpdb->prefix}he_bookmarks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			entry_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_entry (user_id,entry_id),
			KEY entry_id (entry_id),
			KEY user_created (user_id,created_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}he_feedback (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			kind varchar(20) NOT NULL,
			reason varchar(40) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			disposition varchar(40) NOT NULL DEFAULT '',
			resolution_note text NOT NULL,
			resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			resolved_at datetime NULL,
			row_version int(10) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_status (entry_id,status),
			KEY user_id (user_id),
			KEY status_created (status,created_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}he_audit_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL,
			action varchar(40) NOT NULL,
			from_state varchar(30) NOT NULL DEFAULT '',
			to_state varchar(30) NOT NULL DEFAULT '',
			note text NOT NULL,
			request_id varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_created (entry_id,created_at),
			KEY actor_created (actor_id,created_at),
			KEY action (action)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}he_metrics (
			entry_id bigint(20) unsigned NOT NULL,
			view_count bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (entry_id),
			KEY view_count (view_count)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}he_rate_limits (
			rate_key varchar(191) NOT NULL,
			window_start datetime NOT NULL,
			hit_count int(10) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (rate_key),
			KEY expires_at (expires_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}he_search_index (
			entry_id bigint(20) unsigned NOT NULL,
			first_letter char(1) NOT NULL DEFAULT '',
			type_slug varchar(80) NOT NULL DEFAULT '',
			system_slug varchar(80) NOT NULL DEFAULT '',
			search_text longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (entry_id),
			KEY first_letter (first_letter),
			KEY type_slug (type_slug),
			KEY system_slug (system_slug),
			KEY updated_at (updated_at)
		) {$charset};" );

		self::assert_tables();
		self::migrate_legacy_data();
		HE_Permissions::install_caps();
		update_option( self::OPTION, HE_SCHEMA_VERSION, false );
	}

	/** Run idempotent upgrades. */
	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPTION, 0 ) < HE_SCHEMA_VERSION ) {
			self::install();
		}
	}

	/** Migrate immutable v0.1.0 metadata without inventing review evidence. */
	private static function migrate_legacy_data() {
		global $wpdb;
		$wpdb->query(
			"INSERT INTO {$wpdb->prefix}he_metrics (entry_id,view_count,updated_at)
			 SELECT pm.post_id,MAX(CAST(pm.meta_value AS UNSIGNED)),UTC_TIMESTAMP()
			 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id
			 WHERE pm.meta_key='_he_views' AND p.post_type='he_entry'
			 GROUP BY pm.post_id
			 ON DUPLICATE KEY UPDATE view_count=GREATEST(view_count,VALUES(view_count)),updated_at=UTC_TIMESTAMP()" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$wpdb->query(
			"INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value)
			 SELECT p.ID,'_he_workflow_state',CASE p.post_status WHEN 'publish' THEN 'published' WHEN 'pending' THEN 'submitted' WHEN 'private' THEN 'hidden' ELSE 'rejected' END
			 FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_he_workflow_state'
			 WHERE p.post_type='he_entry' AND pm.meta_id IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$wpdb->query(
			"INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value)
			 SELECT p.ID,'_he_row_version','1' FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_he_row_version'
			 WHERE p.post_type='he_entry' AND pm.meta_id IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$wpdb->query(
			"INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value)
			 SELECT p.ID,'_he_language_reviewed','0' FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_he_language_reviewed'
			 WHERE p.post_type='he_entry' AND pm.meta_id IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/** Migrate free-text legacy body systems into the controlled taxonomy. */
	public static function migrate_legacy_systems() {
		global $wpdb;
		$done = (int) get_option( 'he_legacy_system_migration', 0 );
		if ( $done >= 1 ) {
			return;
		}
		$rows = $wpdb->get_results(
			"SELECT p.ID,pm.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_he_body_system' WHERE p.post_type='he_entry'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		foreach ( $rows as $row ) {
			if ( HE_Content::term( $row->ID, HE_Content::SYSTEM, 'slug' ) ) {
				continue;
			}
			$slug = self::map_legacy_system( (string) $row->meta_value );
			if ( ! HE_Content::assign( $row->ID, $slug, HE_Content::SYSTEM ) ) {
				throw new RuntimeException( sprintf( 'Could not migrate body system for encyclopedia entry %d.', $row->ID ) );
			}
		}
		update_option( 'he_legacy_system_migration', 1, false );
	}

	private static function map_legacy_system( $value ) {
		$value = strtolower( trim( wp_strip_all_tags( $value ) ) );
		$map = array(
			'brain' => 'nervous', 'nervous' => 'nervous', 'neurological' => 'nervous',
			'heart' => 'cardiovascular', 'cardiovascular' => 'cardiovascular', 'circulatory' => 'cardiovascular',
			'lung' => 'respiratory', 'respiratory' => 'respiratory',
			'digestive' => 'digestive', 'gastrointestinal' => 'digestive', 'liver' => 'digestive',
			'bone' => 'musculoskeletal', 'muscle' => 'musculoskeletal', 'musculoskeletal' => 'musculoskeletal',
			'endocrine' => 'endocrine', 'hormone' => 'endocrine',
			'kidney' => 'urinary-renal', 'renal' => 'urinary-renal', 'urinary' => 'urinary-renal',
			'reproductive' => 'reproductive', 'genital' => 'reproductive',
			'skin' => 'integumentary', 'integumentary' => 'integumentary',
			'immume' => 'immune-lymphatic', 'immune' => 'immune-lymphatic', 'lymphatic' => 'immune-lymphatic',
			'eye' => 'sensory', 'ear' => 'sensory', 'sensory' => 'sensory',
			'dental' => 'oral-dental', 'oral' => 'oral-dental', 'mouth' => 'oral-dental',
		);
		foreach ( $map as $needle => $slug ) {
			if ( false !== strpos( $value, $needle ) ) {
				return $slug;
			}
		}
		return $value ? 'general-whole-body' : 'not-applicable';
	}

	/** @throws RuntimeException When a required table is unavailable. */
	private static function assert_tables() {
		global $wpdb;
		foreach ( array( 'bookmarks', 'feedback', 'audit_log', 'metrics', 'rate_limits', 'search_index' ) as $suffix ) {
			$table = $wpdb->prefix . 'he_' . $suffix;
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				throw new RuntimeException( sprintf( 'Required File 06 table was not created: %s', $table ) );
			}
		}
	}

	/** Atomic fixed-window rate limiter. */
	public static function allow( $key, $limit, $window ) {
		global $wpdb;
		$key = substr( hash( 'sha256', (string) $key ), 0, 64 );
		$now = time();
		$start = gmdate( 'Y-m-d H:i:s', $now );
		$expiry = gmdate( 'Y-m-d H:i:s', $now + max( 1, absint( $window ) ) );
		$table = $wpdb->prefix . 'he_rate_limits';
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (rate_key,window_start,hit_count,expires_at) VALUES (%s,%s,1,%s)
				 ON DUPLICATE KEY UPDATE
				 hit_count=IF(expires_at<=UTC_TIMESTAMP(),1,hit_count+1),
				 window_start=IF(expires_at<=UTC_TIMESTAMP(),VALUES(window_start),window_start),
				 expires_at=IF(expires_at<=UTC_TIMESTAMP(),VALUES(expires_at),expires_at)",
				$key,
				$start,
				$expiry
			)
		);
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT hit_count FROM {$table} WHERE rate_key=%s", $key ) );
		return $count <= max( 1, absint( $limit ) );
	}

	/** Set bookmark state idempotently. */
	public static function set_bookmark( $user_id, $entry_id, $active ) {
		global $wpdb;
		$table = $wpdb->prefix . 'he_bookmarks';
		$user_id = absint( $user_id );
		$entry_id = absint( $entry_id );
		if ( $active ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (user_id,entry_id,created_at) VALUES (%d,%d,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE created_at=created_at",
					$user_id,
					$entry_id
				)
			);
			return false !== $result;
		}
		return false !== $wpdb->delete( $table, array( 'user_id' => $user_id, 'entry_id' => $entry_id ), array( '%d', '%d' ) );
	}

	public static function bookmarked( $user_id, $entry_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}he_bookmarks WHERE user_id=%d AND entry_id=%d", absint( $user_id ), absint( $entry_id ) ) );
	}

	/** Insert a correction/report and verify persistence. */
	public static function add_feedback( $user_id, $entry_id, $kind, $reason, $details ) {
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'he_feedback',
			array(
				'entry_id' => absint( $entry_id ),
				'user_id' => absint( $user_id ),
				'kind' => sanitize_key( $kind ),
				'reason' => sanitize_key( $reason ),
				'details' => sanitize_textarea_field( $details ),
				'status' => 'open',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/** Resolve feedback with optimistic locking. */
	public static function resolve_feedback( $feedback_id, $version, $disposition, $note, $actor_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'he_feedback';
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status='resolved',disposition=%s,resolution_note=%s,resolved_by=%d,resolved_at=UTC_TIMESTAMP(),row_version=row_version+1 WHERE id=%d AND status='open' AND row_version=%d",
				sanitize_key( $disposition ),
				sanitize_textarea_field( $note ),
				absint( $actor_id ),
				absint( $feedback_id ),
				absint( $version )
			)
		);
		return 1 === (int) $result;
	}

	/** Write a local immutable workflow/audit event. */
	public static function audit( $entry_id, $action, $from_state = '', $to_state = '', $note = '', $actor_id = 0 ) {
		global $wpdb;
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		$request_id = isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) ) : wp_generate_uuid4();
		$result = $wpdb->insert(
			$wpdb->prefix . 'he_audit_log',
			array(
				'entry_id' => absint( $entry_id ),
				'actor_id' => $actor_id,
				'action' => sanitize_key( $action ),
				'from_state' => sanitize_key( $from_state ),
				'to_state' => sanitize_key( $to_state ),
				'note' => sanitize_textarea_field( $note ),
				'request_id' => substr( $request_id, 0, 64 ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		HE_Dependencies::audit( $action, array( 'entry_id' => absint( $entry_id ), 'user_id' => $actor_id, 'from_state' => $from_state, 'to_state' => $to_state ) );
		return false !== $result;
	}

	/** Atomically increment and return a public entry view count. */
	public static function increment_view( $entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'he_metrics';
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (entry_id,view_count,updated_at) VALUES (%d,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE view_count=view_count+1,updated_at=UTC_TIMESTAMP()",
				absint( $entry_id )
			)
		);
		return self::views( $entry_id );
	}

	public static function views( $entry_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT view_count FROM {$wpdb->prefix}he_metrics WHERE entry_id=%d", absint( $entry_id ) ) );
	}

	/** Rebuild one public search-index row, or remove it when not public. */
	public static function reindex_entry( $entry_id ) {
		global $wpdb;
		$entry_id = absint( $entry_id );
		$table = $wpdb->prefix . 'he_search_index';
		if ( ! HE_Content::publicly_available( $entry_id ) ) {
			$wpdb->delete( $table, array( 'entry_id' => $entry_id ), array( '%d' ) );
			return;
		}

		$post = get_post( $entry_id );
		$type = HE_Content::term( $entry_id, HE_Content::TAX, 'slug' );
		$system = HE_Content::term( $entry_id, HE_Content::SYSTEM, 'slug' );
		$text = array( $post->post_title, $post->post_excerpt, wp_strip_all_tags( $post->post_content ), HE_Content::term( $entry_id, HE_Content::TAX ), HE_Content::term( $entry_id, HE_Content::SYSTEM ) );
		foreach ( array_keys( HE_Content::fields() ) as $key ) {
			$text[] = (string) HE_Content::meta( $entry_id, $key );
		}
		$search = preg_replace( '/\s+/u', ' ', trim( implode( ' ', $text ) ) );
		if ( function_exists( 'mb_strtolower' ) ) {
			$search = mb_strtolower( $search, 'UTF-8' );
		} else {
			$search = strtolower( $search );
		}
		$title = trim( wp_strip_all_tags( $post->post_title ) );
		$first = strtoupper( substr( remove_accents( $title ), 0, 1 ) );
		$first = preg_match( '/^[A-Z]$/', $first ) ? $first : '#';
		$wpdb->replace(
			$table,
			array(
				'entry_id' => $entry_id,
				'first_letter' => $first,
				'type_slug' => $type,
				'system_slug' => $system,
				'search_text' => $search,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/** Reindex all entries in bounded batches. */
	public static function reindex_all() {
		$page = 1;
		do {
			$query = new WP_Query( array( 'post_type' => HE_Content::TYPE, 'post_status' => 'any', 'posts_per_page' => 200, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
			foreach ( $query->posts as $entry_id ) {
				self::reindex_entry( $entry_id );
			}
			$page++;
		} while ( $page <= (int) $query->max_num_pages );
	}

	/** Search catalog with exact filters and true pagination. */
	public static function catalog( array $filters, $page = 1, $per_page = 24, array $fixed_ids = null ) {
		global $wpdb;
		$page = max( 1, absint( $page ) );
		$per_page = min( 60, max( 1, absint( $per_page ) ) );
		$where = array( "p.post_type='he_entry'", "p.post_status='publish'" );
		$params = array();

		if ( ! empty( $filters['search'] ) ) {
			$term = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['search'], 'UTF-8' ) : strtolower( $filters['search'] );
			$where[] = 'i.search_text LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $term ) . '%';
		}
		if ( ! empty( $filters['type'] ) && HE_Content::allowed( $filters['type'], HE_Content::TAX ) ) {
			$where[] = 'i.type_slug=%s';
			$params[] = sanitize_title( $filters['type'] );
		}
		if ( ! empty( $filters['system'] ) && HE_Content::allowed( $filters['system'], HE_Content::SYSTEM ) ) {
			$where[] = 'i.system_slug=%s';
			$params[] = sanitize_title( $filters['system'] );
		}
		if ( ! empty( $filters['letter'] ) && preg_match( '/^[A-Z#]$/', $filters['letter'] ) ) {
			$where[] = 'i.first_letter=%s';
			$params[] = $filters['letter'];
		}
		if ( is_array( $fixed_ids ) ) {
			$fixed_ids = array_values( array_unique( array_filter( array_map( 'absint', $fixed_ids ) ) ) );
			$where[] = $fixed_ids ? 'p.ID IN (' . implode( ',', $fixed_ids ) . ')' : '1=0';
		}

		$base = " FROM {$wpdb->posts} p INNER JOIN {$wpdb->prefix}he_search_index i ON i.entry_id=p.ID LEFT JOIN {$wpdb->prefix}he_metrics m ON m.entry_id=p.ID WHERE " . implode( ' AND ', $where );
		$count_sql = 'SELECT COUNT(*)' . $base;
		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page = min( $page, $pages );
		$offset = ( $page - 1 ) * $per_page;
		$order = ! empty( $filters['sort'] ) && 'popular' === $filters['sort'] ? 'COALESCE(m.view_count,0) DESC,p.post_modified_gmt DESC,p.ID DESC' : 'p.post_modified_gmt DESC,p.ID DESC';
		$ids_sql = 'SELECT p.ID' . $base . " ORDER BY {$order} LIMIT %d OFFSET %d";
		$id_params = array_merge( $params, array( $per_page, $offset ) );
		$ids_sql = $wpdb->prepare( $ids_sql, $id_params );
		$ids = array_map( 'absint', $wpdb->get_col( $ids_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array( 'ids' => $ids, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $per_page );
	}

	/** Delete orphaned per-user and per-entry rows. */
	public static function cleanup_orphans() {
		global $wpdb;
		$wpdb->query( "DELETE b FROM {$wpdb->prefix}he_bookmarks b LEFT JOIN {$wpdb->users} u ON u.ID=b.user_id LEFT JOIN {$wpdb->posts} p ON p.ID=b.entry_id WHERE u.ID IS NULL OR p.ID IS NULL OR p.post_type<>'he_entry'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE f FROM {$wpdb->prefix}he_feedback f LEFT JOIN {$wpdb->posts} p ON p.ID=f.entry_id WHERE p.ID IS NULL OR p.post_type<>'he_entry'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE m FROM {$wpdb->prefix}he_metrics m LEFT JOIN {$wpdb->posts} p ON p.ID=m.entry_id WHERE p.ID IS NULL OR p.post_type<>'he_entry'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE s FROM {$wpdb->prefix}he_search_index s LEFT JOIN {$wpdb->posts} p ON p.ID=s.entry_id WHERE p.ID IS NULL OR p.post_type<>'he_entry'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$wpdb->prefix}he_rate_limits WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}

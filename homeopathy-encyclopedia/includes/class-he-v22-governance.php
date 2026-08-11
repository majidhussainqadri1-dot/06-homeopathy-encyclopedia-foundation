<?php
/**
 * File 06 v2.2 governance hardening for the 2026 consolidated central plan
 * and SSH-F06-PLAN-2026-v1.0. This layer closes fail-closed identity,
 * review binding, migration, public-route, integrity and operability gaps
 * without duplicating another File's canonical domain.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Governance {
	const EXTENSION_OPTION = 'he_v22_extension_version';
	const EXTENSION_VERSION = 2;
	const LOCK_OPTION = 'he_v22_upgrade_lock';
	const LEGACY_CURSOR = 'he_v22_legacy_cursor';
	const LEGACY_DONE = 'he_v22_legacy_done';
	const REINDEX_CURSOR = 'he_v22_reindex_cursor';
	const REINDEX_REQUIRED = 'he_v22_reindex_required';
	const BATCH_SIZE = 50;
	private static $upgrade_lock_token = '';

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 80 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'knowledge_editor_route' ), 0 );
		add_filter( 'post_type_link', array( __CLASS__, 'research_permalink' ), 20, 2 );
		add_action( 'save_post_' . HE_V2_Domain::ENTRY_TYPE, array( __CLASS__, 'secure_reindex_by_post' ), 100, 3 );
		add_action( 'save_post_' . HE_V2_Domain::RESEARCH_TYPE, array( __CLASS__, 'normalize_manual_research_state' ), 100, 3 );
		add_action( 'he_v2_maintenance', array( __CLASS__, 'maintenance' ), 90 );
		add_action( 'admin_init', array( __CLASS__, 'resume_background_work' ), 90 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 90 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest_preflight' ), 90, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'rest_after_callbacks' ), 90, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'rest_post_dispatch' ), 90, 3 );
		add_filter( 'sabri_composer_content_types', array( __CLASS__, 'harden_composer_contracts' ), 100 );
		add_filter( 'sabri_shell_routes', array( __CLASS__, 'complete_shell_routes' ), 100 );
		add_filter( 'sabri_search_connectors', array( __CLASS__, 'bound_search_rebuild' ), 100 );
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'harden_assurance_provider' ), 100 );
		add_filter( 'the_title', array( __CLASS__, 'governed_public_title' ), 99, 2 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'governed_public_excerpt' ), 99, 2 );
	}

	public static function activate() {
		if ( ! self::acquire_lock() ) {
			throw new RuntimeException( 'File 06 v2.2 activation/upgrade is already running.' );
		}
		try {
			HE_V2_Domain::register_types();
			HE_V2_Schema::install();
			self::install_extensions();
			HE_V2_Auth::install_caps();
			self::register_rewrites();
			flush_rewrite_rules( false );
			if ( ! wp_next_scheduled( 'he_v2_maintenance' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'he_v2_maintenance' );
			}
			update_option( self::REINDEX_REQUIRED, 1, false );
			delete_option( HE_V2_Schema::OPTION_FAILURE );
		} catch ( Throwable $error ) {
			HE_V2_Schema::record_runtime_failure( 'v22_activation_failed', $error->getMessage() );
			throw $error;
		} finally {
			self::release_lock();
		}
	}

	public static function maybe_upgrade() {
		$schema = (int) get_option( HE_V2_Schema::OPTION_SCHEMA, 0 );
		$extension = (int) get_option( self::EXTENSION_OPTION, 0 );
		if ( $schema >= HE_SCHEMA_VERSION && $extension >= self::EXTENSION_VERSION ) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			return;
		}
		try {
			HE_V2_Schema::install();
			self::install_extensions();
			update_option( self::REINDEX_REQUIRED, 1, false );
			delete_option( HE_V2_Schema::OPTION_FAILURE );
		} catch ( Throwable $error ) {
			HE_V2_Schema::record_runtime_failure( 'v22_upgrade_failed', $error->getMessage() );
		} finally {
			self::release_lock();
		}
	}

	/** Atomic option insertion plus compare-and-delete stale takeover prevents one worker from deleting another worker's lease. */
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
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function install_extensions() {
		global $wpdb;
		$reviews = HE_V2_Schema::table( 'reviews' );
		if ( ! self::column_exists( $reviews, 'content_hash' ) ) {
			$wpdb->query( "ALTER TABLE {$reviews} ADD content_hash char(64) NOT NULL DEFAULT '' AFTER note" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( ! self::column_exists( $reviews, 'reviewed_row_version' ) ) {
			$wpdb->query( "ALTER TABLE {$reviews} ADD reviewed_row_version bigint(20) unsigned NOT NULL DEFAULT 0 AFTER content_hash" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( ! self::column_exists( $reviews, 'review_subject_author' ) ) {
			$wpdb->query( "ALTER TABLE {$reviews} ADD review_subject_author bigint(20) unsigned NOT NULL DEFAULT 0 AFTER reviewed_row_version" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$charset = $wpdb->get_charset_collate();
		$table = HE_V2_Schema::table( 'migration_quarantine' );
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			legacy_post_id bigint(20) unsigned NOT NULL,
			stage varchar(40) NOT NULL,
			error_code varchar(80) NOT NULL,
			error_message text NOT NULL,
			attempts int(10) unsigned NOT NULL DEFAULT 1,
			resolved tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY legacy_stage (legacy_post_id,stage),
			KEY resolved (resolved),
			KEY error_code (error_code)
		) {$charset};" );

		if ( ! self::column_exists( $reviews, 'content_hash' ) || ! self::column_exists( $reviews, 'reviewed_row_version' ) || ! self::column_exists( $reviews, 'review_subject_author' ) ) {
			throw new RuntimeException( 'File 06 review-binding schema extension is unavailable.' );
		}
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $found !== $table ) {
			throw new RuntimeException( 'File 06 migration quarantine table is unavailable.' );
		}
		update_option( self::EXTENSION_OPTION, self::EXTENSION_VERSION, false );
		update_option( HE_V2_Schema::OPTION_SCHEMA, HE_SCHEMA_VERSION, false );
	}

	public static function register_rewrites() {
		foreach ( array_keys( HE_V2_Domain::types() ) as $type ) {
			add_rewrite_rule( '^encyclopedia/' . preg_quote( $type, '/' ) . '/?$', 'index.php?post_type=' . HE_V2_Domain::ENTRY_TYPE . '&' . HE_V2_Domain::TAX_TYPE . '=' . $type, 'top' );
		}
		add_rewrite_rule( '^knowledge/editor/?$', 'index.php?he_v22_editor=1', 'top' );
		add_rewrite_rule( '^research/([0-9a-fA-F-]{36})/?$', 'index.php?he_v22_research_id=$matches[1]', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'he_v22_editor';
		$vars[] = 'he_v22_research_id';
		return $vars;
	}

	public static function knowledge_editor_route() {
		if ( ! get_query_var( 'he_v22_editor' ) ) {
			$research_id = sanitize_text_field( (string) get_query_var( 'he_v22_research_id' ) );
			if ( $research_id ) {
				self::serve_research_permanent_id( $research_id );
			}
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( '/knowledge/editor/' ) ) );
			exit;
		}
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT ) && ! HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW ) && ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE ) );
		exit;
	}

	private static function serve_research_permanent_id( $public_id ) {
		global $wpdb, $wp_query;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id,status,data_class FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
		if ( ! $row || ! in_array( $row['status'], array( 'published', 'corrected', 'retracted' ), true ) ) {
			status_header( 404 );
			$wp_query->set_404();
			return;
		}
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			status_header( 404 );
			$wp_query->set_404();
			return;
		}
		$wp_query->posts = array( $post );
		$wp_query->post = $post;
		$wp_query->queried_object = $post;
		$wp_query->queried_object_id = $post->ID;
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		$wp_query->is_404 = false;
		$wp_query->is_singular = true;
		$wp_query->is_single = true;
		setup_postdata( $post );
		if ( 'public' !== $row['data_class'] ) {
			nocache_headers();
		}
	}

	public static function research_permalink( $url, $post ) {
		if ( ! $post || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type ) {
			return $url;
		}
		global $wpdb;
		$public_id = $wpdb->get_var( $wpdb->prepare( 'SELECT public_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post->ID ) );
		return $public_id ? home_url( '/research/' . rawurlencode( $public_id ) . '/' ) : $url;
	}

	public static function register_routes() {
		register_rest_route( HE_V2_API::NS, '/research/(?P<id>\\d+)/review', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_review_research' ),
			'permission_callback' => function( $request ) { return self::research_permission( $request, HE_V2_Auth::CAP_REVIEW ); },
		) );
		register_rest_route( HE_V2_API::NS, '/research/(?P<id>\\d+)/integrity', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_create_research_integrity' ),
			'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); },
		) );
		register_rest_route( HE_V2_API::NS, '/research-integrity/(?P<id>\\d+)/apply', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_apply_research_integrity' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH ); },
		) );
		register_rest_route( HE_V2_API::NS, '/operations/reindex', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_reindex_batch' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR ); },
		) );
	}

	private static function research_permission( $request, $cap ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $request['id'] ) ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return HE_V2_Auth::rest_permission( $cap, (int) $row['post_id'], 'file06-research' );
	}

	private static function mutation_guard( WP_REST_Request $request, $operation, $cap = '' ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Public reading remains available, but mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( $cap ) {
			$allowed = HE_V2_Auth::rest_permission( $cap );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Domain::rate_allow( 'v22:' . sanitize_key( $operation ) . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function mutation_finish( $reservation, $result, $success_code = 200 ) {
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( ! empty( $reservation['replay'] ) ) {
			return new WP_REST_Response( $reservation['body'], $reservation['code'] );
		}
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			HE_V2_Domain::idempotent_finish( $reservation['id'], $code, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			return $result;
		}
		HE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $result );
		return new WP_REST_Response( $result, $success_code );
	}

	public static function rest_review_research( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'research-review-' . absint( $request['id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::mutation_finish( $reservation, null, 201 );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $request['id'] ) ), ARRAY_A );
		if ( ! $row ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 201 );
		}
		$data = (array) $request->get_json_params();
		$decision = sanitize_key( $data['decision'] ?? 'changes_required' );
		if ( ! in_array( $decision, array( 'approved', 'changes_required', 'rejected' ), true ) ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_invalid_review_decision', __( 'Invalid review decision.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ), 201 );
		}
		$conflict = ! empty( $data['conflict_declared'] );
		if ( $conflict && 'approved' === $decision ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_review_conflict', __( 'A reviewer with a declared conflict cannot approve this record.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ), 201 );
		}
		$post = get_post( (int) $row['post_id'] );
		$reviewer = get_current_user_id();
		if ( $post && (int) $post->post_author === $reviewer && ! HE_V2_Auth::is_founder( $reviewer ) ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_independent_review_required', __( 'The author cannot provide the independent approval review.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ), 201 );
		}
		$hash = self::research_hash( $row );
		$ok = $wpdb->insert( HE_V2_Schema::table( 'reviews' ), array(
			'object_type' => 'research',
			'object_id' => (int) $row['id'],
			'reviewer_id' => $reviewer,
			'scope' => sanitize_key( $data['scope'] ?? 'scientific' ),
			'decision' => $decision,
			'conflict_declared' => $conflict ? 1 : 0,
			'note' => sanitize_textarea_field( $data['note'] ?? '' ),
			'content_hash' => $hash,
			'reviewed_row_version' => (int) $row['row_version'],
			'review_subject_author' => $post ? (int) $post->post_author : 0,
			'created_at' => current_time( 'mysql', true ),
		) );
		if ( ! $ok ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_review_write_failed', __( 'The review could not be stored.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ), 201 );
		}
		HE_V2_Domain::emit_event( 'ResearchRecordReviewed.v1', 'research', (int) $row['id'], array( 'decision' => $decision, 'scope' => sanitize_key( $data['scope'] ?? 'scientific' ) ) );
		return self::mutation_finish( $reservation, array( 'review_id' => (int) $wpdb->insert_id, 'decision' => $decision, 'content_hash' => $hash ), 201 );
	}

	public static function rest_create_research_integrity( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'research-integrity-' . absint( $request['id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::mutation_finish( $reservation, null, 201 );
		}
		global $wpdb;
		$research = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $request['id'] ) ), ARRAY_A );
		if ( ! $research || ! in_array( $research['status'], array( 'published', 'corrected' ), true ) ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 201 );
		}
		$data = (array) $request->get_json_params();
		$type = sanitize_key( $data['type'] ?? 'correction' );
		if ( ! in_array( $type, array( 'correction', 'retraction' ), true ) || ! trim( (string) ( $data['reason'] ?? '' ) ) ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_integrity_required', __( 'A correction or retraction type and reason are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ), 201 );
		}
		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert( HE_V2_Schema::table( 'integrity_actions' ), array(
			'public_id' => wp_generate_uuid4(), 'object_type' => 'research', 'object_id' => (int) $research['id'],
			'action_type' => $type, 'status' => 'submitted', 'reason' => sanitize_textarea_field( $data['reason'] ),
			'evidence' => sanitize_textarea_field( $data['evidence'] ?? '' ), 'replacement_object_id' => absint( $data['replacement_id'] ?? 0 ),
			'row_version' => 1, 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
		) );
		if ( ! $ok ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_integrity_write_failed', __( 'The integrity request could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ), 201 );
		}
		HE_V2_Domain::emit_event( 'ResearchIntegritySubmitted.v1', 'research', (int) $research['id'], array( 'action_type' => $type ) );
		return self::mutation_finish( $reservation, array( 'id' => (int) $wpdb->insert_id, 'status' => 'submitted', 'type' => $type ), 201 );
	}

	public static function rest_apply_research_integrity( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'research-integrity-apply-' . absint( $request['id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::mutation_finish( $reservation, null, 200 );
		}
		global $wpdb;
		$action = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . " WHERE id=%d AND object_type='research'", absint( $request['id'] ) ), ARRAY_A );
		if ( ! $action || ! in_array( $action['status'], array( 'submitted', 'triaged', 'under_review', 'accepted' ), true ) ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'The integrity action is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 200 );
		}
		$research = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', (int) $action['object_id'] ), ARRAY_A );
		if ( ! $research ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ), 200 );
		}
		$data = (array) $request->get_json_params();
		$expected = absint( $data['expected_version'] ?? 0 );
		if ( $expected !== (int) $research['row_version'] ) {
			return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The research record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 200 );
		}
		$to = 'retraction' === $action['action_type'] ? 'retracted' : 'corrected';
		$wpdb->query( 'START TRANSACTION' );
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'research' ) . ' SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d', $to, $research['id'], $expected ) );
		$action_updated = $wpdb->update( HE_V2_Schema::table( 'integrity_actions' ), array( 'status' => 'applied', 'decided_by' => get_current_user_id(), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $action['id'], 'row_version' => (int) $action['row_version'] ), array( '%s','%d','%s' ), array( '%d','%d' ) );
		if ( 1 !== (int) $updated || 1 !== (int) $action_updated ) {
			$wpdb->query( 'ROLLBACK' );
			return self::mutation_finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The integrity action changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ), 200 );
		}
		$wpdb->query( 'COMMIT' );
		$event = 'retracted' === $to ? 'ResearchRecordRetracted.v1' : 'ResearchPublicationCorrected.v1';
		HE_V2_Domain::emit_event( $event, 'research', (int) $research['id'], array( 'reason' => $action['reason'], 'integrity_action' => $action['public_id'] ) );
		return self::mutation_finish( $reservation, self::research_public_or_private_dto( (int) $research['id'], true ), 200 );
	}

	public static function rest_reindex_batch( WP_REST_Request $request ) {
		$reservation = self::mutation_guard( $request, 'bounded-reindex', HE_V2_Auth::CAP_REPAIR );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
			return self::mutation_finish( $reservation, null, 200 );
		}
		$data = (array) $request->get_json_params();
		$cursor = absint( $data['cursor'] ?? get_option( self::REINDEX_CURSOR, 0 ) );
		return self::mutation_finish( $reservation, self::reindex_batch( $cursor, min( 100, max( 1, absint( $data['limit'] ?? self::BATCH_SIZE ) ) ) ), 200 );
	}

	public static function rest_preflight( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		if ( 0 !== strpos( $route, $prefix ) ) {
			return $response;
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/transition$#', $route, $m ) ) {
			$data = (array) $request->get_json_params();
			$state = sanitize_key( $data['state'] ?? '' );
			if ( in_array( $state, array( 'corrected', 'retracted' ), true ) ) {
				return new WP_Error( 'he_integrity_workflow_required', __( 'Corrections and retractions must use the transparent integrity workflow.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
			}
			if ( in_array( $state, array( 'approved', 'scheduled', 'published' ), true ) ) {
				$row = HE_V2_Domain::concept_by_id( $m[1], true );
				$gate = $row ? self::entry_release_gate( $row ) : new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
				if ( is_wp_error( $gate ) ) {
					return $gate;
				}
			}
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/(\\d+)/transition$#', $route, $m ) ) {
			$data = (array) $request->get_json_params();
			$state = sanitize_key( $data['state'] ?? '' );
			if ( in_array( $state, array( 'corrected', 'retracted' ), true ) ) {
				return new WP_Error( 'he_integrity_workflow_required', __( 'Research corrections and retractions must use the transparent integrity workflow.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
			}
			if ( in_array( $state, array( 'approved', 'active', 'published' ), true ) ) {
				$gate = self::research_release_gate( absint( $m[1] ), 'published' === $state );
				if ( is_wp_error( $gate ) ) {
					return $gate;
				}
			}
		}

		if ( $route === $prefix . '/research' && WP_REST_Server::CREATABLE === $request->get_method() ) {
			$valid = self::validate_research_payload( (array) $request->get_json_params() );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/graph/([^/]+)$#', $route, $m ) && WP_REST_Server::CREATABLE === $request->get_method() ) {
			$data = (array) $request->get_json_params();
			$reference_id = absint( $data['reference_id'] ?? 0 );
			if ( $reference_id ) {
				$source = HE_V2_Domain::concept_by_id( $m[1], true );
				if ( ! $source || ! self::reference_belongs_to_concept( $reference_id, (int) $source['id'] ) ) {
					return new WP_Error( 'he_relation_provenance_invalid', __( 'Relationship provenance must reference a source belonging to the source concept.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
				}
			}
		}

		if ( $route === $prefix . '/duplicates' ) {
			$id = sanitize_text_field( (string) $request->get_param( 'id' ) );
			return rest_ensure_response( self::find_duplicates( $id, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
		}
		if ( $route === $prefix . '/merge' ) {
			$reservation = self::external_mutation_reservation( $request, 'secure-merge' );
			if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
				return self::mutation_finish( $reservation, null, 200 );
			}
			$data = (array) $request->get_json_params();
			$result = self::secure_merge( $data );
			return self::mutation_finish( $reservation, $result, 200 );
		}
		if ( $route === $prefix . '/research' && WP_REST_Server::READABLE === $request->get_method() ) {
			return rest_ensure_response( self::browse_research( $request ) );
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/datasets/(\\d+)/access$#', $route, $m ) ) {
			$gate = self::dataset_request_gate( absint( $m[1] ) );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/(\\d+)/approve$#', $route, $m ) ) {
			$gate = self::dataset_approval_gate( absint( $m[1] ) );
			if ( is_wp_error( $gate ) ) {
				return $gate;
			}
		}
		if ( $route === $prefix . '/repair' ) {
			$reservation = self::external_mutation_reservation( $request, 'bounded-repair' );
			if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) {
				return self::mutation_finish( $reservation, null, 200 );
			}
			$params = (array) $request->get_json_params();
			$dry_run = ! empty( $params['dry_run'] );
			return self::mutation_finish( $reservation, self::repair( $dry_run ), 200 );
		}
		return $response;
	}

	private static function external_mutation_reservation( WP_REST_Request $request, $operation ) {
		/* Existing route permission callbacks already authorize; repeat nonce/rate/idempotency safely. */
		return self::mutation_guard( $request, $operation );
	}

	public static function rest_after_callbacks( $response, $handler, $request ) {
		if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/review$#', $route, $m ) && WP_REST_Server::CREATABLE === $request->get_method() ) {
			$row = HE_V2_Domain::concept_by_id( $m[1], true );
			if ( $row ) {
				self::bind_latest_entry_review( $row );
			}
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/(\\d+)/approve$#', $route, $m ) && WP_REST_Server::CREATABLE === $request->get_method() ) {
			HE_V2_Domain::emit_event( 'ResearchDatasetAccessApproved.v1', 'dataset_access', absint( $m[1] ), array() );
		}
		return $response;
	}

	public static function rest_post_dispatch( $result, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request || 0 !== strpos( $request->get_route(), '/' . HE_V2_API::NS ) ) {
			return $result;
		}
		$trace = HE_V2_Domain::trace_id();
		if ( $result instanceof WP_REST_Response ) {
			$result->header( 'X-Trace-ID', $trace );
			if ( WP_REST_Server::READABLE !== $request->get_method() || false !== strpos( $request->get_route(), '/bookmark' ) || false !== strpos( $request->get_route(), '/dataset' ) || false !== strpos( $request->get_route(), '/health' ) || false !== strpos( $request->get_route(), '/repair' ) || false !== strpos( $request->get_route(), '/operations/' ) ) {
				$result->header( 'Cache-Control', 'no-store, private, max-age=0' );
			}
		}
		return $result;
	}

	private static function entry_content_hash( $row ) {
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post ) {
			return '';
		}
		$payload = array(
			'title' => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'body' => $post->post_content,
			'type' => $row['type_slug'],
			'language' => $row['language'],
			'body_system' => HE_V2_Domain::taxonomy_slug( (int) $row['post_id'], HE_V2_Domain::TAX_SYSTEM ),
			'structured' => get_post_meta( (int) $row['post_id'], '_he_structured', true ),
			'safety' => get_post_meta( (int) $row['post_id'], '_he_safety_status', true ),
		);
		global $wpdb;
		$payload['references'] = $wpdb->get_results( $wpdb->prepare( 'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,quotation_word_count FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d ORDER BY id ASC', (int) $row['id'] ), ARRAY_A );
		return hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	private static function research_hash( $row ) {
		$payload = array_intersect_key( (array) $row, array_flip( array( 'record_type', 'title', 'question', 'protocol', 'investigators_json', 'ethics_json', 'consent_json', 'conflicts_json', 'data_class', 'case_anonymized', 'case_consent_verified', 'case_tag', 'case_json', 'metadata_json' ) ) );
		return hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	private static function bind_latest_entry_review( $row ) {
		global $wpdb;
		$review_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='concept' AND object_id=%d AND reviewer_id=%d ORDER BY id DESC LIMIT 1", (int) $row['id'], get_current_user_id() ) );
		if ( ! $review_id ) {
			return;
		}
		$post = get_post( (int) $row['post_id'] );
		$wpdb->update( HE_V2_Schema::table( 'reviews' ), array(
			'content_hash' => self::entry_content_hash( $row ),
			'reviewed_row_version' => (int) $row['row_version'],
			'review_subject_author' => $post ? (int) $post->post_author : 0,
		), array( 'id' => $review_id ), array( '%s','%d','%d' ), array( '%d' ) );
	}

	private static function entry_release_gate( $row ) {
		if ( HE_V2_Auth::is_founder() ) {
			return true;
		}
		global $wpdb;
		$hash = self::entry_content_hash( $row );
		$post = get_post( (int) $row['post_id'] );
		$review = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0 AND content_hash=%s ORDER BY id DESC LIMIT 1", (int) $row['id'], $hash ), ARRAY_A );
		if ( ! $review || ( $post && (int) $review['reviewer_id'] === (int) $post->post_author ) ) {
			return new WP_Error( 'he_fresh_independent_review_required', __( 'A fresh independent approval review bound to the current content is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		return true;
	}

	private static function research_release_gate( $research_id, $publishing = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$ethics = json_decode( (string) $row['ethics_json'], true );
		$consent = json_decode( (string) $row['consent_json'], true );
		if ( empty( $ethics['approval_reference'] ) || ( 'successful-case' === $row['record_type'] && empty( $consent['verified'] ) ) ) {
			return new WP_Error( 'he_ethics_gate_failed', __( 'Ethics approval and required consent must be documented.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( HE_V2_Auth::is_founder() && ! $publishing ) {
			return true;
		}
		$post = get_post( (int) $row['post_id'] );
		$hash = self::research_hash( $row );
		$review = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='research' AND object_id=%d AND decision='approved' AND conflict_declared=0 AND content_hash=%s ORDER BY id DESC LIMIT 1", (int) $row['id'], $hash ), ARRAY_A );
		if ( ! $review || ( $post && (int) $review['reviewer_id'] === (int) $post->post_author ) ) {
			return new WP_Error( 'he_fresh_independent_review_required', __( 'A fresh independent approval review bound to the current research record is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		return true;
	}

	private static function validate_research_payload( $data ) {
		$type = sanitize_key( $data['record_type'] ?? 'proposal' );
		if ( ! in_array( $type, array( 'proposal', 'protocol', 'publication', 'successful-case', 'dataset' ), true ) ) {
			return new WP_Error( 'he_invalid_research_type', __( 'Invalid research record type.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$data_class = sanitize_key( $data['data_class'] ?? 'restricted' );
		if ( ! in_array( $data_class, array( 'public', 'restricted', 'highly-restricted' ), true ) ) {
			return new WP_Error( 'he_invalid_data_class', __( 'Research data class must be public, restricted or highly restricted.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		if ( empty( (array) ( $data['investigators'] ?? array() ) ) ) {
			return new WP_Error( 'he_investigators_required', __( 'At least one investigator is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		if ( 'successful-case' === $type ) {
			foreach ( array( 'baseline', 'intervention', 'follow_up', 'adverse_events', 'limitations' ) as $field ) {
				if ( '' === trim( (string) ( $data[ $field ] ?? '' ) ) ) {
					return new WP_Error( 'he_case_governance_failed', __( 'A successful case requires baseline, intervention, follow-up, adverse-events status and limitations.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
				}
			}
			if ( empty( $data['consent_verified'] ) || empty( $data['anonymized'] ) ) {
				return new WP_Error( 'he_case_governance_failed', __( 'A successful case requires verified consent and anonymization.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			$text = implode( ' ', array_map( 'strval', array( $data['title'] ?? '', $data['question'] ?? '', $data['protocol'] ?? '', $data['baseline'], $data['intervention'], $data['follow_up'], $data['adverse_events'], $data['limitations'] ) ) );
			if ( self::contains_direct_identifiers( $text ) ) {
				return new WP_Error( 'he_case_pii_detected', __( 'Potential direct personal identifiers were detected in the successful case.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		if ( 'dataset' === $type ) {
			foreach ( array( 'dataset_description', 'de_identification', 'lawful_basis', 'access_policy' ) as $field ) {
				if ( '' === trim( (string) ( $data[ $field ] ?? '' ) ) ) {
					return new WP_Error( 'he_dataset_governance_required', __( 'Dataset metadata requires description, de-identification, lawful basis and access policy.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
				}
			}
		}
		return true;
	}

	private static function contains_direct_identifiers( $text ) {
		$patterns = array(
			'/\\b[\\w.%+-]+@[\\w.-]+\\.[A-Za-z]{2,}\\b/u',
			'/\\b(?:\\+?92|0)?3\\d{9}\\b/u',
			'/\\b\\d{5}-\\d{7}-\\d\\b/u',
			'/\\b(?:CNIC|NIC|passport|phone|mobile|address|email|mrn|patient\\s*id|national\\s*id)\\s*[:#-]?\\s*[A-Za-z0-9@._+\\/-]{4,}\\b/ui',
			'/\\b(?:house|street|road|sector|block)\\s+(?:no\\.?\\s*)?[A-Za-z0-9-]{1,12}\\b/ui',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, (string) $text ) ) {
				return true;
			}
		}
		return false;
	}

	private static function reference_belongs_to_concept( $reference_id, $concept_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d AND concept_id=%d', absint( $reference_id ), absint( $concept_id ) ) );
	}

	public static function find_duplicates( $identifier = '', $limit = 50 ) {
		global $wpdb;
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$source = $identifier ? HE_V2_Domain::concept_by_id( $identifier, true ) : null;
		if ( $identifier && ! $source ) {
			return array();
		}
		if ( ! $source ) {
			$sql = 'SELECT a.normalized_alias,COUNT(DISTINCT a.concept_id) AS concept_count,GROUP_CONCAT(DISTINCT a.concept_id ORDER BY a.concept_id) AS concept_ids FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=a.concept_id WHERE c.merged_into_id=0 GROUP BY a.normalized_alias HAVING concept_count>1 ORDER BY concept_count DESC,a.normalized_alias ASC LIMIT %d";
			return $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A );
		}
		$aliases = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT normalized_alias FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d', (int) $source['id'] ) );
		$aliases[] = HE_V2_Domain::normalize( get_the_title( (int) $source['post_id'] ) );
		$aliases = array_values( array_unique( array_filter( $aliases ) ) );
		if ( ! $aliases ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $aliases ), '%s' ) );
		$params = $aliases;
		$params[] = (int) $source['id'];
		$params[] = $limit;
		$sql = 'SELECT DISTINCT c.id,c.public_id,c.canonical_slug,c.type_slug,c.language,a.normalized_alias FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'aliases' ) . " a ON a.concept_id=c.id WHERE a.normalized_alias IN ({$placeholders}) AND c.id<>%d AND c.merged_into_id=0 ORDER BY c.id ASC LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function secure_merge( $data ) {
		global $wpdb;
		$source = HE_V2_Domain::concept_by_id( $data['source_id'] ?? '', true );
		$target = HE_V2_Domain::concept_by_id( $data['target_id'] ?? '', true );
		$sv = absint( $data['source_version'] ?? 0 );
		$tv = absint( $data['target_version'] ?? 0 );
		if ( ! $source || ! $target || (int) $source['id'] === (int) $target['id'] ) {
			return new WP_Error( 'he_invalid_merge', __( 'A valid source and target concept are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			$source = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d FOR UPDATE', (int) $source['id'] ), ARRAY_A );
			$target = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d FOR UPDATE', (int) $target['id'] ), ARRAY_A );
			if ( ! $source || ! $target || (int) $source['row_version'] !== $sv || (int) $target['row_version'] !== $tv ) {
				throw new RuntimeException( 'version-conflict' );
			}
			$aliases = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d ORDER BY id ASC FOR UPDATE', (int) $source['id'] ), ARRAY_A );
			foreach ( $aliases as $alias ) {
				$collision = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT concept_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE normalized_alias=%s AND language=%s AND concept_id<>%d LIMIT 1', $alias['normalized_alias'], $alias['language'], (int) $source['id'] ) );
				if ( $collision && $collision !== (int) $target['id'] ) {
					throw new RuntimeException( 'alias-third-party-collision' );
				}
				if ( $collision === (int) $target['id'] ) {
					$wpdb->delete( HE_V2_Schema::table( 'aliases' ), array( 'id' => (int) $alias['id'] ), array( '%d' ) );
				} else {
					$wpdb->update( HE_V2_Schema::table( 'aliases' ), array( 'concept_id' => (int) $target['id'], 'alias_type' => 'redirect', 'is_primary' => 0 ), array( 'id' => (int) $alias['id'] ), array( '%d','%s','%d' ), array( '%d' ) );
				}
			}
			$reference_map = array();
			$edges = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d FOR UPDATE', (int) $source['id'], (int) $source['id'] ), ARRAY_A );
			foreach ( $edges as $edge ) {
				$new_source = (int) $edge['source_concept_id'] === (int) $source['id'] ? (int) $target['id'] : (int) $edge['source_concept_id'];
				$new_target = (int) $edge['target_concept_id'] === (int) $source['id'] ? (int) $target['id'] : (int) $edge['target_concept_id'];
				if ( $new_source !== $new_target ) {
					$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d AND target_concept_id=%d AND relation_type=%s', $new_source, $new_target, $edge['relation_type'] ) );
					if ( ! $exists ) {
						$new_reference_id = (int) $edge['source_reference_id'];
						if ( (int) $edge['source_concept_id'] === (int) $source['id'] ) {
							if ( ! isset( $reference_map[ $new_reference_id ] ) ) {
								$reference = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d FOR UPDATE', $new_reference_id ), ARRAY_A );
								if ( ! $reference || (int) $reference['concept_id'] !== (int) $source['id'] || ( (int) $reference['version_id'] !== 0 && (int) $reference['version_id'] !== (int) $source['current_version'] ) ) {
									throw new RuntimeException( 'relation-provenance-invalid' );
								}
								unset( $reference['id'] );
								$reference['concept_id'] = (int) $target['id'];
								$reference['version_id'] = (int) $target['current_version'];
								$reference['created_by'] = get_current_user_id();
								$reference['created_at'] = current_time( 'mysql', true );
								if ( false === $wpdb->insert( HE_V2_Schema::table( 'references' ), $reference ) ) {
									throw new RuntimeException( 'relation-provenance-clone-failed' );
								}
								$reference_map[ $new_reference_id ] = (int) $wpdb->insert_id;
							}
							$new_reference_id = (int) $reference_map[ $new_reference_id ];
						}
						$relation_updated = $wpdb->update( HE_V2_Schema::table( 'relations' ), array( 'source_concept_id' => $new_source, 'target_concept_id' => $new_target, 'source_reference_id' => $new_reference_id, 'row_version' => (int) $edge['row_version'] + 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $edge['id'] ) );
						if ( false === $relation_updated ) { throw new RuntimeException( 'relation-write-failed' ); }
					} else {
						if ( false === $wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'relation-write-failed' ); }
					}
				} else {
					if ( false === $wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) ) ) { throw new RuntimeException( 'relation-write-failed' ); }
				}
			}
			$u1 = $wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'concepts' ) . " SET status='archived',merged_into_id=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", (int) $target['id'], (int) $source['id'], $sv ) );
			$u2 = $wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'concepts' ) . ' SET row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d', (int) $target['id'], $tv ) );
			if ( 1 !== (int) $u1 || 1 !== (int) $u2 ) {
				throw new RuntimeException( 'version-conflict' );
			}
			$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $source['id'] ), array( '%d' ) );
			$wpdb->query( 'COMMIT' );
			HE_V2_Domain::emit_event( 'KnowledgeConceptMerged.v1', 'concept', (int) $target['id'], array( 'source_id' => $source['public_id'], 'target_id' => $target['public_id'], 'reason' => sanitize_textarea_field( $data['reason'] ?? '' ) ) );
			self::reindex_concept_secure( (int) $target['id'] );
			return HE_V2_Domain::concept_by_id( (int) $target['id'], true );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			if ( in_array( $error->getMessage(), array( 'relation-provenance-invalid', 'relation-provenance-clone-failed' ), true ) ) {
				return new WP_Error( 'he_relation_provenance_invalid', __( 'Merged graph edges could not be rebound to valid target-concept provenance.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			if ( 'relation-write-failed' === $error->getMessage() ) {
				return new WP_Error( 'he_merge_failed', __( 'The graph could not be rewritten atomically during the concept merge.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
			}
			$code = 'alias-third-party-collision' === $error->getMessage() ? 'he_alias_collision' : 'he_version_conflict';
			$message = 'he_alias_collision' === $code ? __( 'A source alias belongs to a third canonical concept; manual reconciliation is required.', 'homeopathy-encyclopedia' ) : __( 'One of the concepts changed before the merge.', 'homeopathy-encyclopedia' );
			return new WP_Error( $code, $message, array( 'status' => 409 ) );
		}
	}

	private static function dataset_request_gate( $research_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT record_type,status,metadata_json FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
		if ( ! $row || 'dataset' !== $row['record_type'] || ! in_array( $row['status'], array( 'published', 'corrected' ), true ) ) {
			return new WP_Error( 'he_dataset_not_found', __( 'Dataset metadata could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$meta = json_decode( (string) $row['metadata_json'], true );
		foreach ( array( 'description', 'de_identification', 'lawful_basis', 'access_policy' ) as $key ) {
			if ( empty( $meta[ $key ] ) ) {
				return new WP_Error( 'he_dataset_governance_required', __( 'Dataset governance metadata is incomplete.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		return true;
	}

	private static function dataset_approval_gate( $access_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT a.*,r.record_type,r.status AS research_status FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'research' ) . ' r ON r.id=a.research_id WHERE a.id=%d', absint( $access_id ) ), ARRAY_A );
		if ( ! $row || 'requested' !== $row['status'] || 'dataset' !== $row['record_type'] || ! in_array( $row['research_status'], array( 'published', 'corrected' ), true ) ) {
			return new WP_Error( 'he_dataset_access_not_available', __( 'The dataset access request is not available for approval.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		if ( ! HE_V2_Auth::membership_allowed( (int) $row['requester_id'] ) ) {
			return new WP_Error( 'he_dataset_requester_ineligible', __( 'The dataset requester is no longer eligible.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		return true;
	}

	public static function browse_research( WP_REST_Request $request ) {
		global $wpdb;
		$limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$cursor = max( 0, absint( $request->get_param( 'cursor' ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . " WHERE status IN ('published','corrected','retracted') AND id>%d ORDER BY id ASC LIMIT %d", $cursor, $limit + 1 ), ARRAY_A );
		$has_more = count( $rows ) > $limit;
		$rows = array_slice( $rows, 0, $limit );
		$items = array();
		foreach ( $rows as $row ) {
			$dto = self::research_public_or_private_dto( (int) $row['id'], false );
			if ( $dto ) {
				$items[] = $dto;
			}
		}
		return array( 'items' => $items, 'next_cursor' => $has_more && $rows ? (int) end( $rows )['id'] : null, 'limit' => $limit );
	}

	private static function research_public_or_private_dto( $research_id, $private = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
		if ( ! $row || ( ! $private && ! in_array( $row['status'], array( 'published', 'corrected', 'retracted' ), true ) ) ) {
			return null;
		}
		$dto = array(
			'id' => $row['public_id'], 'record_type' => $row['record_type'], 'status' => $row['status'], 'title' => $row['title'],
			'question' => $row['question'], 'case_tag' => $row['case_tag'], 'canonical_url' => home_url( '/research/' . rawurlencode( $row['public_id'] ) . '/' ),
			'updated_at' => $row['updated_at'], 'freshness' => array( 'contract_version' => HE_CONTRACT_VERSION, 'updated_at' => $row['updated_at'] ),
		);
		if ( 'retracted' === $row['status'] ) {
			$dto['protocol'] = '';
			$dto['notice'] = __( 'This research record has been retracted. Metadata remains visible for correction and citation integrity.', 'homeopathy-encyclopedia' );
		} else {
			$dto['protocol'] = 'public' === $row['data_class'] ? $row['protocol'] : '';
		}
		if ( 'successful-case' === $row['record_type'] ) {
			$case = json_decode( (string) $row['case_json'], true );
			$case = is_array( $case ) ? $case : array();
			if ( $private ) {
				$dto['case'] = $case;
			} else {
				$case_public = 'public' === $row['data_class']
					&& in_array( $row['status'], array( 'published', 'corrected' ), true )
					&& ! empty( $row['case_anonymized'] )
					&& ! empty( $row['case_consent_verified'] );
				if ( $case_public ) {
					$dto['case'] = $case;
				} else {
					$dto['case_details_restricted'] = true;
				}
			}
		}
		if ( 'dataset' === $row['record_type'] ) {
			$metadata = json_decode( (string) $row['metadata_json'], true );
			$metadata = is_array( $metadata ) ? $metadata : array();
			if ( $private ) {
				$dto['dataset_metadata'] = $metadata;
			} else {
				$public_metadata = array();
				foreach ( array( 'description', 'de_identification', 'lawful_basis', 'access_policy' ) as $key ) {
					if ( isset( $metadata[ $key ] ) ) { $public_metadata[ $key ] = $metadata[ $key ]; }
				}
				$dto['dataset_metadata'] = $public_metadata;
				$dto['dataset_payload_public'] = false;
			}
		}
		if ( $private ) {
			$dto['protocol'] = $row['protocol'];
			$dto['investigators'] = json_decode( (string) $row['investigators_json'], true );
			$dto['ethics'] = json_decode( (string) $row['ethics_json'], true );
			$dto['consent'] = json_decode( (string) $row['consent_json'], true );
			$dto['conflicts'] = json_decode( (string) $row['conflicts_json'], true );
			$dto['data_class'] = $row['data_class'];
			$dto['row_version'] = (int) $row['row_version'];
		}
		return $dto;
	}

	public static function normalize_manual_research_state( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d", absint( $post_id ) ), ARRAY_A );
		if ( ! $row ) {
			return;
		}
		/* The legacy save hook could materialize a published domain state from a pending post. */
		$empty_ethics = empty( $row['ethics_json'] ) || '{}' === trim( (string) $row['ethics_json'] );
		if ( 'published' === $row['status'] && 'publish' !== $post->post_status && $empty_ethics ) {
			$wpdb->update( $table, array( 'status' => 'proposal', 'row_version' => (int) $row['row_version'] + 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s','%d','%s' ), array( '%d' ) );
		}
	}

	public static function secure_reindex_by_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		global $wpdb;
		$concept_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ) );
		if ( $concept_id ) {
			self::reindex_concept_secure( $concept_id );
		}
	}

	private static function evidence_rank( $grade ) {
		$map = array(
			'systematic-review' => 8, 'controlled-study' => 7, 'observational-study' => 6, 'classical-primary' => 5,
			'classical-secondary' => 4, 'clinical-observation' => 3, 'expert-consensus' => 2, 'ungraded' => 1,
		);
		return $map[ sanitize_key( $grade ) ] ?? 0;
	}

	public static function reindex_concept_secure( $concept_id ) {
		global $wpdb;
		$row = HE_V2_Domain::concept_by_id( absint( $concept_id ), true );
		if ( ! $row || ! HE_V2_Domain::is_public_concept( $row ) || ! $row['current_version'] ) {
			$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => absint( $concept_id ) ), array( '%d' ) );
			return false;
		}
		$version = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', (int) $row['current_version'], (int) $row['id'] ), ARRAY_A );
		if ( ! $version ) {
			$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => (int) $row['id'] ), array( '%d' ) );
			return false;
		}
		$aliases = $wpdb->get_col( $wpdb->prepare( 'SELECT alias FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d ORDER BY id ASC', (int) $row['id'] ) );
		$grades = $wpdb->get_col( $wpdb->prepare( 'SELECT evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', (int) $row['id'], (int) $row['current_version'] ) );
		$best_grade = 'ungraded';
		$best_rank = 0;
		foreach ( $grades as $grade ) {
			$rank = self::evidence_rank( $grade );
			if ( $rank > $best_rank ) {
				$best_rank = $rank;
				$best_grade = sanitize_key( $grade );
			}
		}
		$search_text = HE_V2_Domain::normalize( implode( ' ', array_merge( array( $version['title'], $version['summary'], wp_strip_all_tags( $version['body'] ) ), $aliases ) ) );
		$first = mb_substr( HE_V2_Domain::normalize( $version['title'] ), 0, 1, 'UTF-8' );
		$data = array(
			'concept_id' => (int) $row['id'], 'first_letter' => $first, 'type_slug' => $row['type_slug'],
			'body_system' => HE_V2_Domain::taxonomy_slug( (int) $row['post_id'], HE_V2_Domain::TAX_SYSTEM ), 'language' => $row['language'],
			'source_grade' => $best_grade, 'review_status' => $row['review_status'], 'safety_status' => $row['safety_status'],
			'search_text' => $search_text, 'updated_at' => current_time( 'mysql', true ),
		);
		return false !== $wpdb->replace( HE_V2_Schema::table( 'search_index' ), $data );
	}

	public static function reindex_batch( $cursor = 0, $limit = self::BATCH_SIZE ) {
		global $wpdb;
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id>%d ORDER BY id ASC LIMIT %d', absint( $cursor ), $limit ) );
		$processed = 0;
		$last = absint( $cursor );
		foreach ( $rows as $id ) {
			$last = absint( $id );
			self::reindex_concept_secure( $last );
			$processed++;
		}
		$more = $processed === $limit && (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id>%d ORDER BY id ASC LIMIT 1', $last ) );
		update_option( self::REINDEX_CURSOR, $more ? $last : 0, false );
		if ( ! $more ) {
			delete_option( self::REINDEX_REQUIRED );
		}
		return array( 'processed' => $processed, 'next_cursor' => $more ? $last : null, 'done' => ! $more );
	}

	public static function migrate_legacy_batch( $limit = self::BATCH_SIZE ) {
		if ( get_option( self::LEGACY_DONE ) ) {
			return array( 'processed' => 0, 'next_cursor' => null, 'done' => true, 'quarantined' => 0 );
		}
		global $wpdb;
		$cursor = absint( get_option( self::LEGACY_CURSOR, 0 ) );
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND ID>%d ORDER BY ID ASC LIMIT %d", HE_V2_Domain::ENTRY_TYPE, $cursor, $limit ) );
		$quarantined = 0;
		$last = $cursor;
		foreach ( $posts as $post_id ) {
			$last = absint( $post_id );
			try {
				$concept = HE_V2_Domain::ensure_concept_for_post( $last );
				if ( ! $concept ) {
					throw new RuntimeException( 'concept-materialization-failed' );
				}
				self::reindex_concept_secure( $concept );
			} catch ( Throwable $error ) {
				$quarantined++;
				self::quarantine( $last, 'legacy-materialization', 'migration_failed', $error->getMessage() );
			}
			update_option( self::LEGACY_CURSOR, $last, false );
		}
		$more = $posts && (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND ID>%d ORDER BY ID ASC LIMIT 1", HE_V2_Domain::ENTRY_TYPE, $last ) );
		if ( ! $more ) {
			update_option( self::LEGACY_DONE, 1, false );
			delete_option( self::LEGACY_CURSOR );
		}
		return array( 'processed' => count( $posts ), 'next_cursor' => $more ? $last : null, 'done' => ! $more, 'quarantined' => $quarantined );
	}

	private static function quarantine( $post_id, $stage, $code, $message ) {
		global $wpdb;
		$table = HE_V2_Schema::table( 'migration_quarantine' );
		$now = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (legacy_post_id,stage,error_code,error_message,attempts,resolved,created_at,updated_at) VALUES (%d,%s,%s,%s,1,0,%s,%s) ON DUPLICATE KEY UPDATE error_code=VALUES(error_code),error_message=VALUES(error_message),attempts=attempts+1,resolved=0,updated_at=VALUES(updated_at)", absint( $post_id ), sanitize_key( $stage ), sanitize_key( $code ), sanitize_text_field( $message ), $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function reconcile_outbox( $limit = self::BATCH_SIZE ) {
		global $wpdb;
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$sql = 'SELECT e.event_id,e.event_name,e.payload_json,e.created_at FROM ' . HE_V2_Schema::table( 'events' ) . ' e LEFT JOIN ' . HE_V2_Schema::table( 'outbox' ) . ' o ON o.event_id=e.event_id WHERE o.event_id IS NULL ORDER BY e.id ASC LIMIT %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit ), ARRAY_A );
		$created = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert( HE_V2_Schema::table( 'outbox' ), array(
				'event_id' => $row['event_id'], 'event_name' => $row['event_name'], 'payload_json' => $row['payload_json'],
				'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => current_time( 'mysql', true ), 'last_error' => '',
				'created_at' => $row['created_at'], 'updated_at' => current_time( 'mysql', true ),
			) );
			$created += $ok ? 1 : 0;
		}
		return array( 'recreated' => $created, 'checked' => count( $rows ) );
	}

	public static function maintenance() {
		self::maybe_upgrade();
		self::migrate_legacy_batch();
		if ( get_option( self::REINDEX_REQUIRED ) || get_option( self::REINDEX_CURSOR ) ) {
			self::reindex_batch( absint( get_option( self::REINDEX_CURSOR, 0 ) ) );
		}
		self::reconcile_outbox();
	}

	public static function resume_background_work() {
		if ( current_user_can( 'activate_plugins' ) ) {
			self::maintenance();
		}
	}

	public static function repair( $dry_run = true ) {
		global $wpdb;
		$quarantine = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'migration_quarantine' ) . ' WHERE resolved=0' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$missing_outbox = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'events' ) . ' e LEFT JOIN ' . HE_V2_Schema::table( 'outbox' ) . ' o ON o.event_id=e.event_id WHERE o.event_id IS NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = array( 'dry_run' => (bool) $dry_run, 'quarantine_unresolved' => $quarantine, 'missing_outbox' => $missing_outbox, 'legacy_done' => (bool) get_option( self::LEGACY_DONE ), 'reindex_required' => (bool) get_option( self::REINDEX_REQUIRED ) );
		if ( ! $dry_run ) {
			self::install_extensions();
			$result['migration'] = self::migrate_legacy_batch();
			$result['outbox'] = self::reconcile_outbox();
			$result['reindex'] = self::reindex_batch( absint( get_option( self::REINDEX_CURSOR, 0 ) ) );
		}
		return $result;
	}

	public static function health() {
		global $wpdb;
		$base = HE_V2_Schema::health();
		$base['file00_authority_ready'] = HE_V2_Auth::provider_ready();
		$base['extension_version'] = (int) get_option( self::EXTENSION_OPTION, 0 );
		$base['legacy_migration'] = array( 'done' => (bool) get_option( self::LEGACY_DONE ), 'cursor' => absint( get_option( self::LEGACY_CURSOR, 0 ) ) );
		$base['quarantine_unresolved'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'migration_quarantine' ) . ' WHERE resolved=0' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base['dead_letter'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . HE_V2_Schema::table( 'outbox' ) . " WHERE status='dead'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base['outbox_reconciliation_missing'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'events' ) . ' e LEFT JOIN ' . HE_V2_Schema::table( 'outbox' ) . ' o ON o.event_id=e.event_id WHERE o.event_id IS NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base['reindex_required'] = (bool) get_option( self::REINDEX_REQUIRED );
		return $base;
	}

	public static function harden_composer_contracts( $types ) {
		$types = is_array( $types ) ? $types : array();
		if ( isset( $types['file06_encyclopedia_entry'] ) ) {
			$types['file06_encyclopedia_entry']['draft_command'] = array( __CLASS__, 'composer_create_entry' );
			$types['file06_encyclopedia_entry']['actor_binding'] = 'current-session-only';
		}
		if ( isset( $types['file06_research_record'] ) ) {
			$types['file06_research_record']['draft_command'] = array( __CLASS__, 'composer_create_research' );
			$types['file06_research_record']['actor_binding'] = 'current-session-only';
			$types['file06_research_record']['fields'] = array( 'record_type', 'title', 'question', 'protocol', 'investigators', 'ethics_reference', 'consent_verified', 'anonymized', 'baseline', 'intervention', 'follow_up', 'adverse_events', 'limitations', 'data_class', 'dataset_description', 'de_identification', 'lawful_basis', 'access_policy' );
		}
		return $types;
	}

	private static function composer_actor( $context ) {
		$current = get_current_user_id();
		$claimed = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : $current;
		return $current && $claimed === $current ? $current : 0;
	}

	public static function composer_create_entry( $payload, $context = array() ) {
		$actor = self::composer_actor( $context );
		if ( ! $actor || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT ) ) {
			return new WP_Error( 'he_composer_forbidden', __( 'File 06 creation is not authorized for this authenticated actor.', 'homeopathy-encyclopedia' ) );
		}
		return HE_V2_Domain::create_entry( is_array( $payload ) ? $payload : array(), $actor );
	}

	public static function composer_create_research( $payload, $context = array() ) {
		$actor = self::composer_actor( $context );
		if ( ! $actor || ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH ) ) {
			return new WP_Error( 'he_composer_forbidden', __( 'File 06 research creation is not authorized for this authenticated actor.', 'homeopathy-encyclopedia' ) );
		}
		$valid = self::validate_research_payload( is_array( $payload ) ? $payload : array() );
		return is_wp_error( $valid ) ? $valid : HE_V2_Domain::create_research( $payload, $actor );
	}

	public static function complete_shell_routes( $routes ) {
		$routes = is_array( $routes ) ? $routes : array();
		$routes['encyclopedia-type'] = array( 'owner' => 'file-06', 'path' => '/encyclopedia/{type}/', 'access' => 'public', 'cache' => 'public', 'index' => true, 'layout_owner' => 'file-20', 'visual_owner' => 'file-25', 'contract_version' => HE_CONTRACT_VERSION );
		$routes['encyclopedia-entry'] = array( 'owner' => 'file-06', 'path' => '/encyclopedia/entry/{canonical_slug}/', 'access' => 'public-eligible', 'cache' => 'public-eligible', 'index' => true, 'layout_owner' => 'file-20', 'visual_owner' => 'file-25', 'contract_version' => HE_CONTRACT_VERSION );
		$routes['knowledge-editor'] = array( 'owner' => 'file-06', 'path' => '/knowledge/editor/', 'access' => 'restricted', 'cache' => 'no-store', 'index' => false, 'layout_owner' => 'file-20', 'visual_owner' => 'file-25', 'contract_version' => HE_CONTRACT_VERSION );
		$routes['research-record'] = array( 'owner' => 'file-06', 'path' => '/research/{permanent_id}/', 'access' => 'public-conditional', 'cache' => 'public-metadata-only', 'index' => true, 'layout_owner' => 'file-20', 'visual_owner' => 'file-25', 'contract_version' => HE_CONTRACT_VERSION );
		return $routes;
	}

	public static function bound_search_rebuild( $connectors ) {
		$connectors = is_array( $connectors ) ? $connectors : array();
		if ( isset( $connectors['file-06'] ) ) {
			$connectors['file-06']['rebuild'] = array( __CLASS__, 'reindex_batch' );
			$connectors['file-06']['rebuild_is_bounded'] = true;
		}
		return $connectors;
	}

	public static function harden_assurance_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		if ( isset( $providers['file-06'] ) ) {
			$providers['file-06']['health'] = array( __CLASS__, 'health' );
			$providers['file-06']['native_enforcement_preserved'] = true;
			$providers['file-06']['identity_authority'] = 'file-00';
			$providers['file-06']['shell_owner'] = 'file-20';
			$providers['file-06']['visual_owner'] = 'file-25';
			$providers['file-06']['search_owner'] = 'file-26';
		}
		return $providers;
	}

	public static function governed_public_title( $title, $post_id = 0 ) {
		if ( ! $post_id || HE_V2_Domain::ENTRY_TYPE !== get_post_type( $post_id ) || ! is_singular( HE_V2_Domain::ENTRY_TYPE ) ) {
			return $title;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT c.*,v.title AS governed_title FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'versions' ) . ' v ON v.id=c.current_version WHERE c.post_id=%d', absint( $post_id ) ), ARRAY_A );
		return $row && HE_V2_Domain::is_public_concept( $row ) ? $row['governed_title'] : $title;
	}

	public static function governed_public_excerpt( $excerpt, $post = null ) {
		$post = is_object( $post ) ? $post : get_post( $post );
		if ( ! $post || HE_V2_Domain::ENTRY_TYPE !== $post->post_type || ! is_singular( HE_V2_Domain::ENTRY_TYPE ) ) {
			return $excerpt;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT c.*,v.summary AS governed_summary FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'versions' ) . ' v ON v.id=c.current_version WHERE c.post_id=%d', absint( $post->ID ) ), ARRAY_A );
		return $row && HE_V2_Domain::is_public_concept( $row ) ? $row['governed_summary'] : $excerpt;
	}
}

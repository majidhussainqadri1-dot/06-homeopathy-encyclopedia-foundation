<?php
/**
 * File 06 v2.4 — Audit-80 runtime hardening.
 *
 * Corrects schema-contract mismatches and closes public-projection, provider,
 * freshness, translation and queue reliability gaps found during the 80-round
 * review. This layer is additive and preserves canonical ownership boundaries.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V24_Audit80_Hardening {
	const OPTION_VERSION = 'he_v24_audit80_version';
	const VERSION = 1;
	const CURSOR_OPTION = 'he_v24_knowledge_cursor';
	const BATCH = 40;
	const MAX_PROVIDER_RESPONSE = 1048576; // 1 MiB metadata ceiling.

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 140 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_pre_dispatch' ), 8, 3 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'rest_before_callbacks' ), 8, 3 );
		add_filter( 'http_request_args', array( __CLASS__, 'bound_provider_http' ), 20, 2 );
		add_action( HE_V23_Future::CRON, array( __CLASS__, 'maintenance' ), 5 );
		add_action( 'he_v23_knowledge_impact_queued', array( __CLASS__, 'dedupe_impact_queue' ), 5, 4 );
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance' ), 180 );
	}

	public static function activate() {
		self::install();
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( self::OPTION_VERSION, 0 ) < self::VERSION ) {
			self::install();
		}
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'he_' . sanitize_key( $name );
	}

	public static function install() {
		global $wpdb;
		$impact = self::table( 'impact_queue' );
		// Extend queue observability without changing the v2.3 producer contract.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$impact}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_array( $columns ) ) {
			if ( ! in_array( 'last_error', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$impact} ADD last_error text NOT NULL AFTER next_attempt_at" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( ! in_array( 'acknowledged_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$impact} ADD acknowledged_at datetime NULL AFTER last_error" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		update_option( self::OPTION_VERSION, self::VERSION, false );
		update_option( HE_V2_Schema::OPTION_SCHEMA, HE_SCHEMA_VERSION, false );
	}

	public static function register_routes() {
		register_rest_route( HE_V2_API::NS, '/future/translations/(?P<id>\\d+)/transition', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'rest_translation_transition' ),
			'permission_callback' => function( WP_REST_Request $request ) {
				$state = sanitize_key( $request->get_param( 'state' ) );
				$cap = 'published' === $state ? HE_V2_Auth::CAP_PUBLISH : HE_V2_Auth::CAP_REVIEW;
				return HE_V2_Auth::rest_permission( $cap );
			},
		) );
	}

	private static function route( WP_REST_Request $request ) {
		return (string) $request->get_route();
	}

	private static function is_public_concept( $concept_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT c.status,c.post_id,p.post_status FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c LEFT JOIN ' . $wpdb->posts . ' p ON p.ID=c.post_id WHERE c.id=%d',
			absint( $concept_id )
		), ARRAY_A );
		return $row && in_array( $row['status'], array( 'published', 'corrected' ), true ) && 'publish' === $row['post_status'];
	}

	private static function concept_or_404( $concept_id ) {
		if ( ! self::is_public_concept( $concept_id ) ) {
			return new WP_Error( 'he_not_found', __( 'The requested knowledge object is not publicly available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * Override the v2.3 read projections that had schema-name mismatches or could
	 * expose draft/internal state. Returning a response here short-circuits the
	 * original callback while preserving the registered public route.
	 */
	public static function rest_pre_dispatch( $result, WP_REST_Server $server, WP_REST_Request $request ) {
		if ( null !== $result ) {
			return $result;
		}
		$route = self::route( $request );
		if ( 0 !== strpos( $route, '/' . HE_V2_API::NS . '/future/' ) ) {
			return $result;
		}

		if ( '/'. HE_V2_API::NS . '/future/claims' === $route && 'GET' === $request->get_method() ) {
			return self::safe_claims( $request );
		}
		if ( preg_match( '#/future/provenance/[^/]+/[^/]+$#', $route ) && 'GET' === $request->get_method() ) {
			if ( ! HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REVIEW ) ) {
				return new WP_Error( 'he_future_provenance_forbidden', __( 'Provenance detail requires reviewer authorization.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
			}
			return self::safe_provenance( $request );
		}
		if ( preg_match( '#/future/graph/(?P<id>\\d+)$#', $route, $m ) && 'GET' === $request->get_method() ) {
			return self::safe_graph( (int) $m['id'] );
		}
		if ( preg_match( '#/future/time-machine/(?P<id>\\d+)$#', $route, $m ) && 'GET' === $request->get_method() ) {
			return self::safe_time_machine( (int) $m['id'] );
		}
		if ( preg_match( '#/future/freshness/(?P<id>\\d+)$#', $route, $m ) && 'GET' === $request->get_method() ) {
			return self::safe_freshness( (int) $m['id'] );
		}
		if ( preg_match( '#/future/citations/(?P<id>\\d+)/(?P<format>[a-z0-9_-]+)$#', $route, $m ) && 'GET' === $request->get_method() ) {
			return self::safe_citations( (int) $m['id'], sanitize_key( $m['format'] ) );
		}
		if ( preg_match( '#/future/translations/(?P<id>\\d+)$#', $route, $m ) && 'GET' === $request->get_method() ) {
			return self::safe_translations( (int) $m['id'] );
		}
		return $result;
	}

	/** Validate mutation inputs and normalize omission-safe defaults before callbacks. */
	public static function rest_before_callbacks( $response, $handler, WP_REST_Request $request ) {
		if ( null !== $response ) {
			return $response;
		}
		$route = self::route( $request );
		if ( preg_match( '#/future/claims/(?P<id>\\d+)/evidence$#', $route ) && 'POST' === $request->get_method() ) {
			$reference_id = absint( $request->get_param( 'reference_id' ) );
			$external_id = trim( (string) $request->get_param( 'external_id' ) );
			if ( ! $reference_id && '' === $external_id ) {
				return new WP_Error( 'he_future_evidence_source_required', __( 'A canonical reference_id or external_id is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
			}
			if ( strlen( $external_id ) > 191 ) {
				return new WP_Error( 'he_future_external_id_too_long', __( 'The external evidence identifier is too long.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
			}
		}
		if ( '/'. HE_V2_API::NS . '/future/external/lookup' === $route && 'POST' === $request->get_method() ) {
			$valid = self::validate_external_identifier( sanitize_key( $request->get_param( 'provider' ) ), trim( (string) $request->get_param( 'external_id' ) ) );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
		if ( '/'. HE_V2_API::NS . '/future/watchlist' === $route && 'POST' === $request->get_method() && null === $request->get_param( 'active' ) ) {
			$request->set_param( 'active', 1 );
		}
		return $response;
	}

	private static function validate_external_identifier( $provider, $id ) {
		$patterns = array(
			'crossref'       => '#^10\\.[0-9]{4,9}/\\S{1,170}$#i',
			'pubmed'         => '#^[0-9]{1,12}$#',
			'clinicaltrials' => '#^NCT[0-9]{8}$#i',
			'orcid'          => '#^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$#i',
			'datacite'       => '#^10\\.[0-9]{4,9}/\\S{1,170}$#i',
			'mesh'           => '#^[A-Z][0-9]{6,9}$#i',
		);
		if ( empty( $patterns[ $provider ] ) || ! preg_match( $patterns[ $provider ], $id ) ) {
			return new WP_Error( 'he_future_external_id_invalid', __( 'The scholarly identifier is invalid for the selected provider.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return true;
	}

	public static function bound_provider_http( $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$allowed = array( 'api.crossref.org', 'eutils.ncbi.nlm.nih.gov', 'clinicaltrials.gov', 'pub.orcid.org', 'api.datacite.org', 'id.nlm.nih.gov' );
		if ( in_array( $host, $allowed, true ) ) {
			$args['timeout'] = min( 12, max( 3, (int) ( $args['timeout'] ?? 12 ) ) );
			$args['redirection'] = min( 2, max( 0, (int) ( $args['redirection'] ?? 2 ) ) );
			$args['limit_response_size'] = self::MAX_PROVIDER_RESPONSE;
			$args['reject_unsafe_urls'] = true;
		}
		return $args;
	}

	private static function safe_claims( WP_REST_Request $request ) {
		global $wpdb;
		$concept_id = absint( $request->get_param( 'concept_id' ) );
		$ok = self::concept_or_404( $concept_id );
		if ( is_wp_error( $ok ) ) { return $ok; }
		$claims = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,public_id,claim_key,claim_text,claim_state,evidence_state,updated_at FROM " . self::table( 'claims' ) . " WHERE concept_id=%d AND claim_state='active' ORDER BY id ASC LIMIT 300",
			$concept_id
		), ARRAY_A );
		foreach ( $claims as &$claim ) {
			$claim['evidence'] = $wpdb->get_results( $wpdb->prepare(
				'SELECT reference_id,external_id,relation,weight FROM ' . self::table( 'claim_evidence' ) . ' WHERE claim_id=%d ORDER BY id ASC LIMIT 300',
				(int) $claim['id']
			), ARRAY_A );
			unset( $claim['id'] );
		}
		return rest_ensure_response( $claims );
	}

	private static function safe_provenance( WP_REST_Request $request ) {
		global $wpdb;
		$type = sanitize_key( $request['type'] );
		$id = sanitize_text_field( $request['id'] );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT id,object_type,object_id,action,actor_id,source_hash,transform,metadata_json,created_at FROM ' . self::table( 'provenance' ) . ' WHERE object_type=%s AND object_id=%s ORDER BY id DESC LIMIT 200',
			$type, $id
		), ARRAY_A );
		return rest_ensure_response( $rows );
	}

	private static function safe_graph( $concept_id ) {
		global $wpdb;
		$ok = self::concept_or_404( $concept_id );
		if ( is_wp_error( $ok ) ) { return $ok; }
		$relations = $wpdb->get_results( $wpdb->prepare(
			"SELECT r.id,r.source_concept_id,r.target_concept_id,r.relation_type,r.owner_file,r.status FROM " . HE_V2_Schema::table( 'relations' ) . " r INNER JOIN " . HE_V2_Schema::table( 'concepts' ) . " s ON s.id=r.source_concept_id INNER JOIN " . HE_V2_Schema::table( 'concepts' ) . " t ON t.id=r.target_concept_id WHERE (r.source_concept_id=%d OR r.target_concept_id=%d) AND r.status='active' AND s.status IN ('published','corrected') AND t.status IN ('published','corrected') ORDER BY r.id DESC LIMIT 300",
			$concept_id, $concept_id
		), ARRAY_A );
		$claims = $wpdb->get_results( $wpdb->prepare(
			"SELECT public_id,claim_key,claim_state,evidence_state FROM " . self::table( 'claims' ) . " WHERE concept_id=%d AND claim_state='active' ORDER BY id ASC LIMIT 300",
			$concept_id
		), ARRAY_A );
		$mappings = $wpdb->get_results( $wpdb->prepare(
			"SELECT vocabulary,external_id,preferred_label,mapping_state FROM " . self::table( 'concept_mappings' ) . " WHERE concept_id=%d AND mapping_state='reviewed' ORDER BY id ASC LIMIT 300",
			$concept_id
		), ARRAY_A );
		return rest_ensure_response( array( 'concept_id' => $concept_id, 'relations' => $relations, 'claims' => $claims, 'mappings' => $mappings, 'visual_owner' => 'file-25' ) );
	}

	private static function safe_time_machine( $concept_id ) {
		global $wpdb;
		$ok = self::concept_or_404( $concept_id );
		if ( is_wp_error( $ok ) ) { return $ok; }
		$versions = $wpdb->get_results( $wpdb->prepare(
			"SELECT version_number,status,title,summary,content_hash,change_reason,effective_at,created_at FROM " . HE_V2_Schema::table( 'versions' ) . " WHERE concept_id=%d AND status IN ('published','corrected','retracted') ORDER BY version_number DESC LIMIT 200",
			$concept_id
		), ARRAY_A );
		return rest_ensure_response( array( 'concept_id' => $concept_id, 'versions' => $versions, 'historical_warning' => 'Historical versions may be superseded, corrected, or retracted. Use the current public version for present guidance.' ) );
	}

	private static function freshness_row( $concept_id ) {
		global $wpdb;
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d', $concept_id ) );
		$risk = $post_id ? sanitize_key( get_post_meta( $post_id, '_he_risk_tier', true ) ) : 'normal';
		$risk = in_array( $risk, array( 'high', 'critical' ), true ) ? $risk : 'normal';
		$days = 'critical' === $risk ? 30 : ( 'high' === $risk ? 90 : 365 );
		$review = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(created_at) FROM " . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='concept' AND object_id=%d AND decision IN ('approved','accept','accepted')",
			$concept_id
		) );
		$now = time();
		$due = $review ? strtotime( $review . ' UTC' ) + $days * DAY_IN_SECONDS : $now;
		$state = $due < $now ? ( in_array( $risk, array( 'high', 'critical' ), true ) ? 'urgent-review' : 'stale' ) : ( $due < $now + 30 * DAY_IN_SECONDS ? 'review-due' : 'current' );
		return array(
			'concept_id' => $concept_id,
			'last_evidence_scan' => gmdate( 'Y-m-d H:i:s' ),
			'last_human_review' => $review ?: null,
			'review_due_at' => gmdate( 'Y-m-d H:i:s', $due ),
			'freshness_state' => $state,
			'risk_tier' => $risk,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	private static function safe_freshness( $concept_id ) {
		global $wpdb;
		$ok = self::concept_or_404( $concept_id );
		if ( is_wp_error( $ok ) ) { return $ok; }
		$row = self::freshness_row( $concept_id );
		$wpdb->replace( self::table( 'freshness' ), $row );
		return rest_ensure_response( $row );
	}

	private static function safe_citations( $concept_id, $format ) {
		global $wpdb;
		$ok = self::concept_or_404( $concept_id );
		if ( is_wp_error( $ok ) ) { return $ok; }
		$formats = array( 'json', 'jsonld', 'bibtex', 'ris', 'csl-json' );
		if ( ! in_array( $format, $formats, true ) ) {
			return new WP_Error( 'he_future_citation_format', __( 'Unsupported citation format.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$refs = $wpdb->get_results( $wpdb->prepare(
			'SELECT id,source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d ORDER BY id ASC LIMIT 500',
			$concept_id
		), ARRAY_A );
		$content = self::format_citations( $refs, $format );
		return new WP_REST_Response( array( 'format' => $format, 'concept_id' => $concept_id, 'content' => $content ), 200 );
	}

	private static function format_citations( $refs, $format ) {
		if ( 'json' === $format || 'csl-json' === $format ) {
			return wp_json_encode( $refs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}
		if ( 'jsonld' === $format ) {
			$items = array();
			foreach ( $refs as $r ) {
				$items[] = array( '@type' => 'CreativeWork', 'name' => $r['title'], 'author' => $r['author'], 'identifier' => $r['doi'], 'url' => $r['url'] );
			}
			return wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $items ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}
		$out = '';
		foreach ( $refs as $r ) {
			$key = 'he' . absint( $r['id'] );
			$title = str_replace( array( '{', '}' ), '', (string) $r['title'] );
			$author = str_replace( array( '{', '}' ), '', (string) $r['author'] );
			if ( 'bibtex' === $format ) {
				$out .= "@misc{{$key},\n  title = {{$title}},\n  author = {{$author}},\n  doi = {" . (string) $r['doi'] . "},\n  url = {" . (string) $r['url'] . "}\n}\n\n";
			} else {
				$out .= "TY  - GEN\nTI  - {$title}\nAU  - {$author}\nDO  - " . (string) $r['doi'] . "\nUR  - " . (string) $r['url'] . "\nER  - \n\n";
			}
		}
		return $out;
	}

	private static function safe_translations( $concept_id ) {
		global $wpdb;
		$can_edit = is_user_logged_in() && ( HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT ) || HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW ) );
		if ( ! $can_edit ) {
			$ok = self::concept_or_404( $concept_id );
			if ( is_wp_error( $ok ) ) { return $ok; }
		}
		$where = $can_edit ? '' : " AND status IN ('published','translation-outdated')";
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT id,concept_id,locale,source_version,translation_version,status,content_hash,updated_at FROM ' . self::table( 'translations' ) . ' WHERE concept_id=%d' . $where . ' ORDER BY locale ASC',
			$concept_id
		), ARRAY_A );
		return rest_ensure_response( $rows );
	}

	public static function rest_translation_transition( WP_REST_Request $request ) {
		global $wpdb;
		$concept_id = absint( $request['id'] );
		$locale = sanitize_text_field( $request->get_param( 'locale' ) );
		$state = sanitize_key( $request->get_param( 'state' ) );
		if ( ! in_array( $state, array( 'review', 'approved', 'published', 'rejected' ), true ) ) {
			return new WP_Error( 'he_future_translation_state_invalid', __( 'Invalid translation transition.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'translations' ) . ' WHERE concept_id=%d AND locale=%s', $concept_id, $locale ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Translation not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$current = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT current_version FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d', $concept_id ) );
		if ( 'published' === $state && ( ! $current || (int) $row['source_version'] !== $current ) ) {
			return new WP_Error( 'he_future_translation_source_changed', __( 'The source version changed; refresh and review the translation before publication.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		$update = array( 'status' => $state, 'updated_at' => current_time( 'mysql', true ) );
		if ( in_array( $state, array( 'approved', 'published', 'rejected' ), true ) ) {
			$update['reviewer_id'] = get_current_user_id();
		}
		$wpdb->update( self::table( 'translations' ), $update, array( 'id' => (int) $row['id'] ) );
		if ( 'published' === $state ) {
			do_action( 'he_v23_consumer_revalidation_required', 'file-19', 'KnowledgeTranslationPublished.v1', array( 'concept_id' => $concept_id, 'locale' => $locale ), array() );
		}
		return rest_ensure_response( array( 'concept_id' => $concept_id, 'locale' => $locale, 'state' => $state, 'source_version' => (int) $row['source_version'] ) );
	}

	/** Rotating scan fixes first-page starvation in freshness and research-gap maintenance. */
	public static function maintenance() {
		global $wpdb;
		$cursor = absint( get_option( self::CURSOR_OPTION, 0 ) );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM " . HE_V2_Schema::table( 'concepts' ) . " WHERE status IN ('published','corrected') AND id>%d ORDER BY id ASC LIMIT %d",
			$cursor, self::BATCH
		) );
		if ( ! $ids ) {
			$cursor = 0;
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM " . HE_V2_Schema::table( 'concepts' ) . " WHERE status IN ('published','corrected') AND id>%d ORDER BY id ASC LIMIT %d",
				$cursor, self::BATCH
			) );
		}
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$wpdb->replace( self::table( 'freshness' ), self::freshness_row( $id ) );
			self::refresh_gap( $id );
			$cursor = max( $cursor, $id );
		}
		update_option( self::CURSOR_OPTION, $cursor, false );
		self::process_impact_queue();
	}

	private static function refresh_gap( $concept_id ) {
		global $wpdb;
		$refs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d', $concept_id ) );
		$claims = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'claims' ) . " WHERE concept_id=%d AND claim_state='active'", $concept_id ) );
		$contradictions = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . self::table( 'claim_evidence' ) . " ce INNER JOIN " . self::table( 'claims' ) . " c ON c.id=ce.claim_id WHERE c.concept_id=%d AND c.claim_state='active' AND ce.relation='contradicts'",
			$concept_id
		) );
		$gap = $refs < 2 ? 'insufficient-references' : ( 0 === $claims ? 'claim-structure-missing' : ( $contradictions > 0 ? 'contradictory-evidence' : '' ) );
		if ( '' === $gap ) {
			$wpdb->update( self::table( 'research_gaps' ), array( 'state' => 'resolved', 'updated_at' => current_time( 'mysql', true ) ), array( 'concept_id' => $concept_id, 'state' => 'open' ) );
			return;
		}
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'research_gaps' ) . ' WHERE concept_id=%d AND gap_type=%s', $concept_id, $gap ), ARRAY_A );
		$now = current_time( 'mysql', true );
		$data = array( 'concept_id' => $concept_id, 'gap_type' => $gap, 'severity' => 0 === $refs ? 'high' : 'medium', 'rationale' => 'Automatically detected governance gap; human review is required before any scientific conclusion.', 'metrics_json' => wp_json_encode( array( 'references' => $refs, 'claims' => $claims, 'contradictions' => $contradictions ) ), 'state' => 'open', 'updated_at' => $now );
		if ( $existing ) {
			$wpdb->update( self::table( 'research_gaps' ), $data, array( 'id' => (int) $existing['id'] ) );
		} else {
			$data['detected_at'] = $now;
			$wpdb->insert( self::table( 'research_gaps' ), $data );
		}
	}

	public static function dedupe_impact_queue( $type, $id, $event, $payload ) {
		global $wpdb;
		$table = self::table( 'impact_queue' );
		$payload_json = wp_json_encode( $payload );
		foreach ( array( 'file-05', 'file-12', 'file-15', 'file-16', 'file-21', 'file-26' ) as $consumer ) {
			$rows = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_type=%s AND source_id=%s AND event_name=%s AND consumer_file=%s AND impact_state='pending' AND payload_json=%s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$type, $id, $event, $consumer, $payload_json
			) );
			if ( count( $rows ) > 1 ) {
				$keep = array_shift( $rows );
				$ids = implode( ',', array_map( 'absint', $rows ) );
				if ( $ids ) {
					$wpdb->query( "DELETE FROM {$table} WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}
		}
	}

	private static function process_impact_queue() {
		global $wpdb;
		$table = self::table( 'impact_queue' );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE impact_state IN ('pending','retry') AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) ORDER BY id ASC LIMIT " . self::BATCH, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			try {
				$payload = json_decode( $row['payload_json'], true );
				do_action( 'he_v23_consumer_revalidation_required', $row['consumer_file'], $row['event_name'], is_array( $payload ) ? $payload : array(), $row );
				$wpdb->update( $table, array( 'impact_state' => 'emitted', 'attempts' => (int) $row['attempts'] + 1, 'last_error' => '', 'acknowledged_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
			} catch ( Throwable $error ) {
				$attempts = (int) $row['attempts'] + 1;
				$dead = $attempts >= 5;
				$wpdb->update( $table, array( 'impact_state' => $dead ? 'dead-letter' : 'retry', 'attempts' => $attempts, 'last_error' => mb_substr( $error->getMessage(), 0, 1000 ), 'next_attempt_at' => $dead ? null : gmdate( 'Y-m-d H:i:s', time() + min( HOUR_IN_SECONDS, ( 2 ** min( $attempts, 6 ) ) * MINUTE_IN_SECONDS ) ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ) );
			}
		}
	}

	public static function assurance( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers['file-06-audit80'] = array(
			'owner' => 'file-06',
			'assurance_owner' => 'file-24',
			'evidence_query' => array( __CLASS__, 'health' ),
			'native_enforcement_preserved' => true,
		);
		return $providers;
	}

	public static function health() {
		global $wpdb;
		return array(
			'version' => HE_VERSION,
			'schema' => HE_SCHEMA_VERSION,
			'audit80_extension' => (int) get_option( self::OPTION_VERSION, 0 ),
			'knowledge_cursor' => absint( get_option( self::CURSOR_OPTION, 0 ) ),
			'impact_retry' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table( 'impact_queue' ) . " WHERE impact_state='retry'" ),
			'impact_dead_letter' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table( 'impact_queue' ) . " WHERE impact_state='dead-letter'" ),
		);
	}
}

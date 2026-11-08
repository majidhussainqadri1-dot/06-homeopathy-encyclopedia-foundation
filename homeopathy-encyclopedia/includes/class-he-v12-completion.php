<?php
/**
 * File 06: Master Plan v12 native coverage, conservative/fail-closed implementation.
 *
 * The source of canonical clinical and bibliographic truth remains File 06;
 * File 05/10/12/16/19/26 own their respective projected experiences.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V12_Completion {
	const LINK_CRON = 'he_v12_reference_link_check';
	const LINK_CURSOR = 'he_v12_reference_link_cursor';
	const LINK_LAST = 'he_v12_reference_link_last_checked';
	const MAX_LINKS = 20;
	const EXPORT_VERSION = 'file06-scholarly-export-v1';

	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( self::LINK_CRON, array( __CLASS__, 'check_reference_links' ) );
		add_action( 'init', array( __CLASS__, 'schedule_links' ), 30 );
		add_filter( 'the_content', array( __CLASS__, 'public_entry_enrichment' ), 25 );
	}

	public static function schedule_links() {
		if ( ! wp_next_scheduled( self::LINK_CRON ) ) {
			$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::LINK_CRON, array(), true );
			if ( is_wp_error( $scheduled ) || ! $scheduled ) {
				HE_V2_Schema::record_runtime_failure( 'v12_link_scan_schedule_failed', 'The governed reference-link scan could not be scheduled.' );
			}
		}
	}

	/**
	 * Bounded, non-privileged, no-follow link checker. Private IP and SSRF
	 * protections are enforced by WordPress's safe HTTP API.
	 */
	public static function check_reference_links( $limit = self::MAX_LINKS ) {
		global $wpdb;
		if ( 'active' !== HE_V2_Schema::runtime_status() ) { return 0; }
		$limit = max( 1, min( self::MAX_LINKS, absint( $limit ) ) );
		$table = HE_V2_Schema::table( 'references' );
		$cursor = absint( get_option( self::LINK_CURSOR, 0 ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id,url,link_status FROM {$table} WHERE id>%d AND url<>'' ORDER BY id ASC LIMIT %d",
			$cursor, $limit
		), ARRAY_A );
		if ( ! $rows && $cursor ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id,url,link_status FROM {$table} WHERE id>0 AND url<>'' ORDER BY id ASC LIMIT %d",
				$limit
			), ARRAY_A );
		}
		$processed = 0;
		foreach ( (array) $rows as $row ) {
			$id = (int) $row['id'];
			$url = (string) $row['url'];
			$previous = (string) ( $row['link_status'] ?? 'unchecked' );
			$state = 'error';
			if ( preg_match( '#^https?://#i', $url ) && wp_http_validate_url( $url ) ) {
				$response = wp_safe_remote_head( $url, array(
					'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 1024,
					'headers' => array( 'User-Agent' => 'Sabri-File06-LinkIntegrity/' . HE_VERSION ),
				) );
				if ( ! is_wp_error( $response ) ) {
					$code = (int) wp_remote_retrieve_response_code( $response );
					// A HEAD method blocked by the publisher does not prove a dead link.
					if ( in_array( $code, array( 403, 405, 429 ), true ) || $code >= 500 ) {
						$state = 'broken' === $previous ? 'broken' : 'unchecked';
					} elseif ( $code >= 200 && $code < 300 ) {
						$state = 'ok';
					} elseif ( 404 === $code || 410 === $code ) {
						// One HEAD 404 can be an anti-bot response. Require a repeat.
						$state = in_array( $previous, array( 'suspect','broken' ), true ) ? 'broken' : 'suspect';
					} else {
						// Do not erase a previously confirmed 404 due to 30x/unknown.
						$state = 'broken' === $previous ? 'broken' : 'unchecked';
					}
				} else {
					$state = 'broken' === $previous ? 'broken' : 'unchecked';
				}
			}
			if ( $state !== $previous ) {
				$written = $wpdb->update( $table, array( 'link_status' => $state ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
				if ( false === $written ) {
					HE_V2_Schema::record_runtime_failure( 'v12_link_scan_write_failed', 'Link status could not be updated safely; the scan cursor was not advanced.' );
					break;
				}
			}
			if ( false === update_option( self::LINK_CURSOR, $id, false )
				&& $id !== absint( get_option( self::LINK_CURSOR, 0 ) ) ) {
				HE_V2_Schema::record_runtime_failure( 'v12_link_scan_cursor_failed',
					'Link scan status was stored but its resume cursor could not be persisted.' );
				break;
			}
			$processed++;
		}
		if ( $processed ) { update_option( self::LINK_LAST, gmdate( 'Y-m-d H:i:s' ), false ); }
		return $processed;
	}

	private static function published_entry( $public_id ) {
		if ( 'active' !== HE_V2_Schema::runtime_status()
			|| ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $public_id ) ) { return null; }
		$row = HE_V2_Domain::concept_by_id( (string) $public_id, true );
		if ( ! is_array( $row ) || ! hash_equals( strtolower( (string) $row['public_id'] ), strtolower( (string) $public_id ) )
			|| 'published' !== $row['status'] || ! HE_V2_Domain::is_public_concept( $row ) ) { return null; }
		$dto = HE_V2_Domain::public_dto( $row );
		if ( ! is_array( $dto ) || ! empty( $dto['is_historical'] )
			|| (int) $dto['version'] !== (int) $dto['current_version'] ) { return null; }
		return array( $row, $dto );
	}

	public static function routes() {
		$ns = HE_V2_API::NS;
		register_rest_route( $ns, '/v12/public/export/(?P<id>[0-9a-fA-F-]{36})', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'export_entry' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( $ns, '/v12/public/compare/(?P<id>[0-9a-fA-F-]{36})', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'compare_references' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( $ns, '/v12/import-map/preview', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'import_preview' ),
			'permission_callback' => static function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT ); },
		) );
	}

	/** External metadata URLs must not leak credentials, tokens or query strings. */
	private static function public_source_url( $raw ) {
		$raw = esc_url_raw( (string) $raw );
		if ( ! $raw ) { return ''; }
		$parts = wp_parse_url( $raw );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] )
			|| ! in_array( strtolower( $parts['scheme'] ), array( 'https','http' ), true )
			|| isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return ''; }
		$url = strtolower( $parts['scheme'] ) . '://' . $parts['host'];
		if ( isset( $parts['port'] ) ) { $url .= ':' . absint( $parts['port'] ); }
		$url .= isset( $parts['path'] ) ? $parts['path'] : '/';
		return wp_http_validate_url( $url ) ? esc_url_raw( $url ) : '';
	}

	private static function public_references( $dto ) {
		$out = array();
		foreach ( array_slice( (array) $dto['references'], 0, 100 ) as $ref ) {
			// Database row keys and original manuscript/full-text are not exportable.
			$fields = array( 'source_type','author','title','edition','volume','page_locator','publisher','year','url','doi','evidence_grade','rights_status','link_status' );
			$entry = array();
			foreach ( $fields as $key ) {
				if ( isset( $ref[ $key ] ) && is_scalar( $ref[ $key ] ) ) {
					$entry[ $key ] = 'url' === $key
						? self::public_source_url( $ref[ $key ] )
						: sanitize_text_field( (string) $ref[ $key ] );
				}
			}
			$out[] = $entry;
		}
		return $out;
	}

	/** Central, current version reviewed claim projection; no raw private evidence. */
	private static function reviewed_claims( $concept_id ) {
		if ( ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Migration_Safety::ready()
			|| ! method_exists( 'HE_V24_Future_Schema', 'public_claims' ) ) {
			return new WP_Error( 'he_v12_export_claims_unavailable', 'Governed claim-evidence schema is unavailable.', array( 'status' => 503 ) );
		}
		$claims = HE_V24_Future_Schema::public_claims( (int) $concept_id );
		if ( ! is_array( $claims ) ) {
			return new WP_Error( 'he_v12_export_claims_unavailable', 'Current approved claims could not be verified.', array( 'status' => 503 ) );
		}
		foreach ( $claims as &$claim ) {
			foreach ( (array) ( $claim['evidence'] ?? array() ) as &$item ) {
				if ( isset( $item['reference']['url'] ) ) {
					$item['reference']['url'] = self::public_source_url( $item['reference']['url'] );
				}
			}
			unset( $item );
		}
		unset( $claim );
		return $claims;
	}

	/** Digest binds bibliographic metadata AND approved claim-level evidence. */
	private static function record_digest( $dto, $claims ) {
		return hash( 'sha256', wp_json_encode( array(
			'id' => (string) $dto['id'], 'version' => (int) $dto['version'],
			'title' => sanitize_text_field( (string) $dto['title'] ),
			'summary' => wp_strip_all_tags( (string) $dto['summary'] ),
			'sources' => self::public_references( $dto ),
			'approved_current_claims' => $claims,
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	/** Rights-scoped, public scholarly record export; no hidden cases or full text. */
	public static function export_entry( WP_REST_Request $request ) {
		$pair = self::published_entry( $request['id'] );
		if ( ! $pair ) { return new WP_Error( 'he_v12_export_unavailable', 'This published scholarly record is not available.', array( 'status' => 404 ) ); }
		list( $row, $dto ) = $pair;
		// File 06's native Future-18 projection enforces approved current claims.
		$claims = self::reviewed_claims( (int) $row['id'] );
		if ( is_wp_error( $claims ) ) { return $claims; }
		return rest_ensure_response( array(
			'format' => self::EXPORT_VERSION,
			'canonical_public_id' => $dto['id'],
			'record_digest' => self::record_digest( $dto, $claims ),
			'canonical_url' => esc_url_raw( (string) $dto['canonical_url'] ),
			'version' => (int) $dto['version'],
			'type' => $dto['type'],
			'language' => $dto['language'],
			'title' => sanitize_text_field( (string) $dto['title'] ),
			'summary' => wp_strip_all_tags( (string) $dto['summary'] ),
			'source_updated_at_utc' => (string) $dto['freshness']['updated_at'],
			'sources' => self::public_references( $dto ),
			'approved_current_claims' => $claims,
			'rights_rule' => 'public-metadata-only; no private notes, withdrawn content, protected full text or patient records',
			'import_mapping' => array(
				'match_on' => 'canonical_public_id',
				'version_policy' => 'never-overwrite; external imported records require fresh reviewer approval',
				'owner' => 'file-06',
			),
		) );
	}

	/** A lawful side-by-side source-identity view; missing quotations stay missing. */
	public static function compare_references( WP_REST_Request $request ) {
		$pair = self::published_entry( $request['id'] );
		if ( ! $pair ) { return new WP_Error( 'he_v12_comparison_unavailable', 'No published concept for this comparison.', array( 'status' => 404 ) ); }
		list( , $dto ) = $pair;
		$sources = self::public_references( $dto );
		$left = filter_var( $request->get_param( 'left' ), FILTER_VALIDATE_INT );
		$right = filter_var( $request->get_param( 'right' ), FILTER_VALIDATE_INT );
		if ( false === $left || false === $right || $left < 0 || $right < 0
			|| $left === $right || ! isset( $sources[ $left ], $sources[ $right ] ) ) {
			return new WP_Error( 'he_v12_compare_indices_invalid', 'Two distinct published reference positions are required.', array( 'status' => 400 ) );
		}
		return rest_ensure_response( array(
			'concept_id' => $dto['id'], 'version' => (int) $dto['version'],
			'columns' => array(
				array( 'source' => $sources[ $left ], 'original_excerpt' => null, 'translation' => null, 'review_note' => null ),
				array( 'source' => $sources[ $right ], 'original_excerpt' => null, 'translation' => null, 'review_note' => null ),
			),
			'notice' => 'Original source text and translation are unavailable unless separately verified, rights-cleared and independently reviewed. Do not invent quotations.',
		) );
	}

	/** Versioned non-mutating map only. External records can never overwrite File 06. */
	public static function import_preview( WP_REST_Request $request ) {
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_v12_nonce_required', 'Valid WordPress request nonce is required.', array( 'status' => 403 ) );
		}
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || (string) ( $data['format'] ?? '' ) !== self::EXPORT_VERSION
			|| empty( $data['canonical_public_id'] ) || ! isset( $data['version'] ) ) {
			return new WP_Error( 'he_v12_import_invalid', 'Unsupported or incomplete public record import mapping.', array( 'status' => 422 ) );
		}
		$id = sanitize_text_field( (string) $data['canonical_public_id'] );
		$pair = self::published_entry( $id );
		if ( ! $pair ) {
			return rest_ensure_response( array( 'mapping' => 'unresolved', 'write_allowed' => false, 'requires_owner_review' => true ) );
		}
		$target = $pair[1];
		$version = filter_var( $data['version'], FILTER_VALIDATE_INT );
		$reported_digest = (string) ( $data['record_digest'] ?? '' );
		$same_version = false !== $version && (int) $version === (int) $target['version'];
		$claims = self::reviewed_claims( (int) $pair[0]['id'] );
		if ( is_wp_error( $claims ) ) { return $claims; }
		$match = $same_version && preg_match( '/^[a-f0-9]{64}$/', $reported_digest )
			&& hash_equals( self::record_digest( $target, $claims ), $reported_digest );
		return rest_ensure_response( array(
			'mapping' => ! $same_version ? 'version-conflict' : ( $match ? 'same-canonical-version' : 'source-content-conflict' ),
			'canonical_public_id' => $target['id'], 'current_version' => (int) $target['version'],
			'write_allowed' => false, 'requires_owner_review' => true,
		) );
	}

	/** Accessible source date and stale/review-due information, never "live" by default. */
	public static function public_entry_enrichment( $content ) {
		if ( ! is_singular( HE_V2_Domain::ENTRY_TYPE ) || ! in_the_loop() || ! is_main_query()
			|| ! is_string( $content ) || false === strpos( $content, 'data-he-entry-id=' ) ) { return $content; }
		global $wpdb, $post;
		$raw = $post ? HE_V2_Domain::concept_by_post_id( (int) $post->ID ) : null;
		if ( ! $raw || ! HE_V2_Domain::is_public_concept( $raw ) || ! empty( $raw['merged_into_id'] ) ) { return $content; }
		$notice = 'Last human verification: unavailable. Do not interpret this page as a live clinical service.';
		if ( class_exists( 'HE_V24_Migration_Safety' ) && HE_V24_Migration_Safety::ready() ) {
			$freshness = $wpdb->get_row( $wpdb->prepare(
				'SELECT freshness_state,risk_tier,last_human_review,review_due_at,updated_at FROM ' . HE_V24_Future_Schema::table( 'freshness' ) . ' WHERE concept_id=%d',
				(int) $raw['id']
			), ARRAY_A );
			if ( $freshness ) {
				$state = (string) $freshness['freshness_state'];
				// A stale cron projection must never label an overdue entry current.
				$due_timestamp = ! empty( $freshness['review_due_at'] ) ? strtotime( (string) $freshness['review_due_at'] . ' UTC' ) : false;
				if ( ! empty( $freshness['last_human_review'] ) && false !== $due_timestamp ) {
					if ( $due_timestamp <= time() ) {
						$state = in_array( (string) ( $freshness['risk_tier'] ?? '' ), array( 'high','critical' ), true ) ? 'urgent-review' : 'stale';
					} elseif ( $due_timestamp <= time() + 30 * DAY_IN_SECONDS && 'current' === $state ) {
						$state = 'review-due';
					}
				} else {
					$state = 'unknown';
				}
				if ( 'retracted' === (string) $raw['status'] ) { $state = 'retracted'; }
				$labels = array( 'current' => 'Current at last recorded review', 'review-due' => 'Review due',
					'stale' => 'Outdated review', 'urgent-review' => 'Urgent human review required',
					'unknown' => 'Human review date unverified', 'retracted' => 'Retracted knowledge; not current' );
				$reviewed = $freshness['last_human_review'] ? substr( (string) $freshness['last_human_review'], 0, 10 ) . ' UTC' : 'unavailable';
				$due = $freshness['review_due_at'] ? substr( (string) $freshness['review_due_at'], 0, 10 ) . ' UTC' : 'not scheduled';
				$notice = ( $labels[ $state ] ?? 'Review status unknown' ) . '. Last human review: ' . $reviewed . '. Review due: ' . $due . '.';
			}
		}
		$markup = '<aside class="he-v12__freshness" role="status" aria-label="Knowledge review freshness"><strong>'
			. esc_html__( 'Knowledge freshness', 'homeopathy-encyclopedia' ) . '</strong> '
			. esc_html( $notice ) . '</aside>';
		// The owner-only supplemental panel must never invent cross-file links.
		$dto = HE_V2_Domain::public_dto( $raw );
		$extra = '';
		if ( is_array( $dto ) && 'published' === $raw['status'] ) {
			$refs = self::public_references( $dto );
			if ( count( $refs ) >= 2 ) {
				$extra .= '<section class="he-v12__comparison" aria-label="Compare bibliographic sources"><h2>Compare sources and editions</h2><div class="he-v12__columns">';
				foreach ( array_slice( $refs, 0, 2 ) as $ref ) {
					$extra .= '<div class="he-v12__source"><h3>' . esc_html( (string) ( $ref['title'] ?? 'Untitled source' ) ) . '</h3>';
					$extra .= '<p>' . esc_html( (string) ( $ref['edition'] ?? 'Edition not recorded' ) ) . '; ' . esc_html( (string) ( $ref['page_locator'] ?? 'Page not recorded' ) ) . '</p>';
					$extra .= '<p>Original text, translation and independent review note unavailable until separately verified and rights-cleared.</p></div>';
				}
				$extra .= '</div></section>';
			}
			// Other owners alone can attest current public URL/ACL/licence/version.
			$links = function_exists( 'apply_filters' ) ? apply_filters( 'he_v12_canonical_related_resources', array(), $dto ) : array();
			$safe_links = array();
			foreach ( array_slice( is_array( $links ) ? $links : array(), 0, 20 ) as $link ) {
				if ( ! is_array( $link ) || true !== ( $link['eligible'] ?? false ) || true !== ( $link['rights_ok'] ?? false )
					|| ! in_array( (string) ( $link['owner_file'] ?? '' ), array( 'file-05','file-10','file-12' ), true )
					|| (int) ( $link['source_version'] ?? 0 ) !== (int) $dto['version'] ) { continue; }
				$url = esc_url_raw( (string) ( $link['url'] ?? '' ) );
				$label = sanitize_text_field( (string) ( $link['label'] ?? '' ) );
				if ( ! $url || ! $label || ! wp_http_validate_url( $url )
					|| ! function_exists( 'apply_filters' )
					|| true !== apply_filters( 'he_v12_related_resource_owner_attested_v1', false, $link, $dto ) ) {
					continue;
				}
				$safe_links[] = '<li><a rel="noopener noreferrer" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
			}
			if ( $safe_links ) {
				$extra .= '<nav class="he-v12__related" aria-label="Verified related scholarly resources"><h2>Related learning and library resources</h2><ul>' . implode( '', $safe_links ) . '</ul></nav>';
			}
			if ( function_exists( 'rest_url' ) ) {
				$version_url = rest_url( HE_V2_API::NS . '/entries/' . rawurlencode( (string) $dto['id'] ) . '/versions' );
				$extra .= '<p class="he-v12__history"><a href="' . esc_url( $version_url ) . '">View version and correction history (metadata)</a></p>';
			}
		}
		// Strict string insertion avoids treating source/link text as a
		// regular-expression replacement string ($1/backreference interpolation).
		$header_pos = strpos( $content, '</header>' );
		if ( false !== $header_pos ) {
			$content = substr_replace( $content, '</header>' . $markup, $header_pos, strlen( '</header>' ) );
		}
		$article_pos = $extra ? strrpos( $content, '</article>' ) : false;
		if ( false !== $article_pos ) {
			$content = substr_replace( $content, $extra . '</article>', $article_pos, strlen( '</article>' ) );
		}
		return $content;
	}

	/** Version and purpose-bound attestation; no personal subject identifiers stored. */
	public static function consent_attestation( $data, $actor_id, $verified, $record_type ) {
		$version = sanitize_text_field( (string) ( $data['consent_version'] ?? '' ) );
		$locale = sanitize_text_field( (string) ( $data['consent_locale'] ?? '' ) );
		$purpose = 'successful-case' === $record_type ? 'patient-story-research' : ( 'dataset' === $record_type ? 'research-dataset' : 'research-processing' );
		$research_public_id = strtolower( sanitize_text_field( (string) ( $data['research_public_id'] ?? '' ) ) );
		$receipt = sanitize_text_field( (string) ( $data['consent_evidence_ref'] ?? '' ) );
		$verification_id = '';
		if ( $verified ) {
			if ( '' === $version || ! preg_match( '/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale )
				|| ! preg_match( '/^[A-Za-z0-9._:-]{8,120}$/', $receipt )
				|| ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $research_public_id ) ) {
				return new WP_Error( 'he_v12_consent_proof_required',
					'Consent policy version, source locale and an evidence receipt ID are mandatory.', array( 'status' => 422 ) );
			}
			// A form checkbox is NOT evidence of subject consent. File 00 or an
			// independently governed subject-consent owner must attest the receipt.
			$proof = function_exists( 'apply_filters' )
				? apply_filters( 'he_v12_consent_receipt_verification_v1', null,
					array( 'evidence_ref' => $receipt, 'research_public_id' => $research_public_id, 'policy_version' => $version,
						'locale' => $locale, 'purpose' => $purpose, 'actor_id' => absint( $actor_id ) ) ) : null;
			if ( ! is_array( $proof ) || true !== ( $proof['verified'] ?? false )
				|| 'file-00' !== ( $proof['authority'] ?? '' )
				|| ! hash_equals( $receipt, (string) ( $proof['evidence_ref'] ?? '' ) )
				|| ! hash_equals( $version, (string) ( $proof['policy_version'] ?? '' ) )
				|| ! hash_equals( $locale, (string) ( $proof['locale'] ?? '' ) )
				|| ! hash_equals( $purpose, (string) ( $proof['purpose'] ?? '' ) )
				|| ! hash_equals( $research_public_id, (string) ( $proof['research_public_id'] ?? '' ) )
				|| empty( $proof['verification_id'] ) ) {
				return new WP_Error( 'he_v12_consent_independent_verification_required',
					'An eligible File 00 verified consent receipt matching the exact purpose, policy and locale is required.', array( 'status' => 503 ) );
			}
			$verification_id = sanitize_text_field( (string) $proof['verification_id'] );
		}
		return array(
			'verified' => (bool) $verified, 'version' => $version,
			'policy_version' => $version, 'locale' => $locale, 'purpose' => $purpose,
			'recorded_by' => absint( $actor_id ), 'recorded_at_utc' => gmdate( 'c' ),
			'evidence_ref' => $verified ? $receipt : '', 'verified_by' => $verified ? 'file-00' : '',
			'verification_id' => $verification_id, 'research_public_id' => $verified ? $research_public_id : '',
			'state' => $verified ? 'accepted' : 'unverified',
			'withdrawn_at_utc' => null, 'withdrawal_policy' => 'requires-subject-authenticated-or-owner-governed-record; no implied blanket consent',
		);
	}
}

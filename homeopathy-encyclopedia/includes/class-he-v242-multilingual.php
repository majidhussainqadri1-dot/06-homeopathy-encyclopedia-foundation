<?php
/**
 * File 06 v2.4.2 multilingual harmonization.
 *
 * The later cross-file Ten-Language publication constitution supersedes the
 * earlier three-locale write restriction for public knowledge articles. File
 * 06 remains the native owner of governed knowledge translation records;
 * localized public URL/SEO/sitemap orchestration remains with the approved
 * cross-file publishing/search owners.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Multilingual {
	const POLICY_VERSION = 'SSH-XPLAN-MLSEO-2026-v1.0';

	public static function hooks() {
		/* Replace only the Future-18 translation collection route so writes use the later language constitution. */
		add_action( 'rest_api_init', array( __CLASS__, 'override_translation_route' ), 500 );
		add_filter( 'sabri_platform_contracts', array( __CLASS__, 'extend_contract' ), 500 );
	}

	private static function core_targets() {
		return array( 'ur', 'en-US', 'ar', 'zh-Hans', 'hi', 'es', 'fr', 'bn', 'pt' );
	}

	private static function fallback_pool() {
		return array( 'ru', 'id', 'de', 'ja', 'tr', 'fa' );
	}

	public static function canonical_locale( $locale ) {
		$locale = str_replace( '_', '-', trim( sanitize_text_field( (string) $locale ) ) );
		if ( '' === $locale ) {
			return '';
		}
		$parts = explode( '-', $locale );
		$primary = strtolower( $parts[0] );
		$aliases = array(
			'en' => 'en-US', 'ur' => 'ur', 'ar' => 'ar', 'zh' => 'zh-Hans',
			'hi' => 'hi', 'es' => 'es', 'fr' => 'fr', 'bn' => 'bn', 'pt' => 'pt',
			'ru' => 'ru', 'id' => 'id', 'de' => 'de', 'ja' => 'ja', 'tr' => 'tr', 'fa' => 'fa',
		);
		if ( isset( $aliases[ $primary ] ) ) {
			if ( 'zh' === $primary && isset( $parts[1] ) && in_array( strtolower( $parts[1] ), array( 'hant','tw','hk','mo' ), true ) ) {
				/* Traditional Chinese remains an optional future variant, not an initial target. Preserve it as source identity. */
				return 'zh-Hant';
			}
			return $aliases[ $primary ];
		}
		/* Preserve a valid author-confirmed BCP-47-like source code even when it is outside the current global target set. */
		return preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale ) ? $locale : '';
	}

	public static function targets_for_source( $source_locale ) {
		$source = self::canonical_locale( $source_locale );
		$targets = self::core_targets();
		$targets = array_values( array_filter( $targets, static function( $locale ) use ( $source ) { return $locale !== $source; } ) );
		foreach ( self::fallback_pool() as $fallback ) {
			if ( count( $targets ) >= 9 ) { break; }
			if ( $fallback !== $source && ! in_array( $fallback, $targets, true ) ) { $targets[] = $fallback; }
		}
		return array_slice( array_values( array_unique( $targets ) ), 0, 9 );
	}

	public static function override_translation_route() {
		register_rest_route( HE_V2_API::NS, '/future/translations/(?P<id>\\d+)', array(
			array(
				'methods' => 'GET',
				'callback' => array( 'HE_V24_Future_API', 'rest_translations' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods' => 'POST',
				'callback' => array( __CLASS__, 'rest_translation_write' ),
				'permission_callback' => array( __CLASS__, 'translation_write_permission' ),
			),
		), true );
	}

	public static function translation_write_permission( $request ) {
		$concept = HE_V24_Future_Schema::concept_row( absint( $request['id'] ), false );
		if ( ! $concept ) {
			return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$allowed = HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT, (int) $concept['post_id'], 'file06-translation-write' );
		if ( true !== $allowed ) { return $allowed; }
		if ( ! HE_V241_Governance::editor_type_allowed( get_current_user_id(), $concept['type_slug'] ) && ! HE_V2_Auth::is_founder() ) {
			return new WP_Error( 'he_editor_type_scope_required', __( 'This editor is not assigned to the knowledge type being translated.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		return true;
	}

	private static function mutation_guard( WP_REST_Request $request, $operation ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Domain::rate_allow( 'v242:' . sanitize_key( $operation ) . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function finish( $reservation, $result, $success_code = 200 ) {
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		if ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], $reservation['code'] ); }
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			HE_V2_Domain::idempotent_finish( $reservation['id'], $code, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			return $result;
		}
		HE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $result );
		return new WP_REST_Response( $result, $success_code );
	}

	private static function clean_content( $content, $machine_assisted ) {
		$content = is_array( $content ) ? $content : array( 'body' => (string) $content );
		$out = array();
		foreach ( array( 'title','summary','body','key_points','safety_note' ) as $key ) {
			if ( isset( $content[ $key ] ) ) {
				$out[ $key ] = 'body' === $key ? wp_kses_post( (string) $content[ $key ] ) : sanitize_textarea_field( (string) $content[ $key ] );
			}
		}
		$out['_translation_meta'] = array(
			'policy_version' => self::POLICY_VERSION,
			'machine_assisted' => (bool) $machine_assisted,
			'human_review_required' => true,
		);
		return $out;
	}

	private static function legacy_equivalent_locales( $locale ) {
		return 'ur' === $locale ? array( 'ur', 'ur-PK' ) : array( $locale );
	}

	public static function rest_translation_write( WP_REST_Request $request ) {
		$concept = HE_V24_Future_Schema::concept_row( absint( $request['id'] ), false );
		if ( ! $concept ) { return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$reservation = self::mutation_guard( $request, 'future-translation-save-' . absint( $request['id'] ) );
		if ( is_wp_error( $reservation ) || ! empty( $reservation['replay'] ) ) { return self::finish( $reservation, null, 200 ); }
		$data = $request->get_json_params(); $data = is_array( $data ) ? $data : $request->get_params();
		$source_locale = self::canonical_locale( $concept['language'] );
		$locale = self::canonical_locale( $data['locale'] ?? '' );
		$targets = self::targets_for_source( $source_locale );
		if ( ! $locale || ! in_array( $locale, $targets, true ) || $locale === $source_locale ) {
			return self::finish( $reservation, new WP_Error( 'he_future_locale_invalid', __( 'The target locale is not one of the nine governed translations for this source language.', 'homeopathy-encyclopedia' ), array( 'status' => 400, 'allowed_targets' => $targets, 'policy_version' => self::POLICY_VERSION ) ) );
		}
		$source = absint( $data['source_version'] ?? 0 );
		if ( ! $source || $source !== (int) $concept['current_version'] || ! HE_V24_Future_Schema::version_belongs( $concept['id'], $source ) ) {
			return self::finish( $reservation, new WP_Error( 'he_future_translation_source_invalid', __( 'Translations must bind to the current governed source version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		$content = self::clean_content( $data['content'] ?? array(), ! empty( $data['machine_assisted'] ) );
		if ( empty( $content['title'] ) || empty( $content['body'] ) ) {
			return self::finish( $reservation, new WP_Error( 'he_future_translation_content_required', __( 'Translated title and body are required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) ) );
		}
		$hash = hash( 'sha256', wp_json_encode( $content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		global $wpdb; $table = HE_V24_Future_Schema::table( 'translations' ); $now = current_time( 'mysql', true );
		$equivalents = self::legacy_equivalent_locales( $locale );
		if ( 1 === count( $equivalents ) ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND locale=%s", $concept['id'], $locale ), ARRAY_A );
		} else {
			$existing_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND locale IN (%s,%s) ORDER BY CASE WHEN locale=%s THEN 0 ELSE 1 END,id ASC", $concept['id'], $equivalents[0], $equivalents[1], $locale ), ARRAY_A );
			if ( count( $existing_rows ) > 1 ) {
				return self::finish( $reservation, new WP_Error( 'he_translation_locale_collision', __( 'Both legacy and canonical Urdu translation rows exist. Reconcile the duplicate before editing.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) );
			}
			$existing = $existing_rows ? $existing_rows[0] : null;
		}
		if ( $existing ) {
			$expected = absint( $data['expected_translation_version'] ?? 0 );
			if ( ! $expected || $expected !== (int) $existing['translation_version'] ) {
				return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) );
			}
			$version = $expected + 1;
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET locale=%s,source_locale=%s,source_version=%d,translation_version=translation_version+1,status='draft',translator_id=%d,reviewer_id=0,content_json=%s,content_hash=%s,published_at=NULL,updated_at=%s WHERE id=%d AND translation_version=%d", $locale, $source_locale, $source, get_current_user_id(), wp_json_encode( $content ), $hash, $now, (int) $existing['id'], $expected ) );
			if ( 1 !== (int) $updated ) { return self::finish( $reservation, new WP_Error( 'he_version_conflict', __( 'The translation changed in another session. Reload and retry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) ) ); }
		} else {
			$version = 1;
			$ok = $wpdb->insert( $table, array( 'concept_id' => (int) $concept['id'], 'locale' => $locale, 'source_locale' => $source_locale, 'source_version' => $source, 'translation_version' => 1, 'status' => 'draft', 'translator_id' => get_current_user_id(), 'reviewer_id' => 0, 'content_json' => wp_json_encode( $content ), 'content_hash' => $hash, 'created_at' => $now, 'updated_at' => $now ) );
			if ( ! $ok ) { return self::finish( $reservation, new WP_Error( 'he_future_translation_write_failed', __( 'The translation could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) ) ); }
		}
		HE_V24_Future_Schema::append_provenance( 'translation', $concept['public_id'] . ':' . $locale, 'translation.saved', '', array( 'source_version' => $source, 'translation_version' => $version, 'source_hash' => $hash, 'policy_version' => self::POLICY_VERSION, 'machine_assisted' => ! empty( $data['machine_assisted'] ) ) );
		return self::finish( $reservation, array( 'saved' => true, 'status' => 'draft', 'locale' => $locale, 'source_locale' => $source_locale, 'source_version' => $source, 'translation_version' => $version, 'translation_targets' => $targets, 'policy_version' => self::POLICY_VERSION, 'human_review_required' => true ), $existing ? 200 : 201 );
	}

	public static function extend_contract( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		if ( empty( $contracts['file-06'] ) || ! is_array( $contracts['file-06'] ) ) { return $contracts; }
		$contracts['file-06']['multilingual_policy'] = array(
			'policy_version' => self::POLICY_VERSION,
			'original_language_first' => true,
			'governed_translation_count' => 9,
			'core_targets' => self::core_targets(),
			'fallback_pool' => self::fallback_pool(),
			'human_review_before_publish' => true,
			'source_revision_bound' => true,
			'localized_url_seo_owner' => 'cross-file multilingual publishing/search owners',
		);
		return $contracts;
	}
}

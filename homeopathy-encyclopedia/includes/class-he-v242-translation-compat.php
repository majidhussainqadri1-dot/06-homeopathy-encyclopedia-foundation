<?php
/** File 06 v2.4.2 compatibility for canonical Urdu reads while legacy ur-PK rows remain. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Translation_Compat {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'override_public_route' ), 515 );
	}

	public static function override_public_route() {
		register_rest_route( HE_V2_API::NS, '/future/public/translations/(?P<id>[a-fA-F0-9-]{36})', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( __CLASS__, 'read' ),
			'permission_callback' => '__return_true',
		), true );
	}

	public static function read( WP_REST_Request $request ) {
		$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
		$concept = HE_V2_Domain::concept_by_id( $public_id, false );
		if ( ! $concept || strtolower( (string) $concept['public_id'] ) !== $public_id || ! $concept['current_version'] ) {
			return new WP_Error( 'he_not_found', __( 'Public translations are not available for this canonical concept.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$locale = HE_V242_Multilingual::canonical_locale( $request->get_param( 'locale' ) );
		global $wpdb;
		$table = HE_V24_Future_Schema::table( 'translations' );
		$params = array( (int) $concept['id'], (int) $concept['current_version'] );
		$where = "concept_id=%d AND source_version=%d AND status='published'";
		if ( 'ur' === $locale ) {
			$where .= " AND locale IN ('ur','ur-PK')";
		} elseif ( $locale ) {
			$where .= ' AND locale=%s';
			$params[] = $locale;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT locale,source_locale,source_version,translation_version,content_json,content_hash,published_at,updated_at FROM {$table} WHERE {$where} ORDER BY CASE WHEN locale='ur' THEN 0 ELSE 1 END,locale ASC", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		/* If both canonical and legacy Urdu rows exist, expose only the canonical row and leave collision resolution to migration governance. */
		if ( 'ur' === $locale && count( $rows ) > 1 ) {
			$canonical = array_values( array_filter( $rows, static function( $row ) { return 'ur' === $row['locale']; } ) );
			$rows = $canonical ? array( $canonical[0] ) : array( $rows[0] );
		}
		$items = array();
		foreach ( $rows as $row ) {
			$content = json_decode( (string) $row['content_json'], true );
			$content = is_array( $content ) ? $content : array();
			$meta = isset( $content['_translation_meta'] ) && is_array( $content['_translation_meta'] ) ? $content['_translation_meta'] : array();
			unset( $content['_translation_meta'] );
			$items[] = array(
				'locale' => HE_V242_Multilingual::canonical_locale( $row['locale'] ) ?: $row['locale'],
				'source_locale' => HE_V242_Multilingual::canonical_locale( $row['source_locale'] ?: $concept['language'] ),
				'source_version' => (int) $row['source_version'],
				'translation_version' => (int) $row['translation_version'],
				'content' => $content,
				'content_hash' => $row['content_hash'],
				'published_at' => $row['published_at'],
				'updated_at' => $row['updated_at'],
				'human_reviewed' => true,
				'machine_assisted' => ! empty( $meta['machine_assisted'] ),
				'policy_version' => $meta['policy_version'] ?? HE_V242_Multilingual::POLICY_VERSION,
				'legacy_locale_normalized' => 'ur-PK' === $row['locale'],
			);
		}
		return rest_ensure_response( array(
			'concept_id' => $concept['public_id'],
			'source_locale' => HE_V242_Multilingual::canonical_locale( $concept['language'] ),
			'source_version' => (int) $concept['current_version'],
			'targets' => HE_V242_Multilingual::targets_for_source( $concept['language'] ),
			'items' => $items,
			'localized_url_owner' => 'cross-file-multilingual-publishing-search',
			'policy_version' => HE_V242_Multilingual::POLICY_VERSION,
		) );
	}
}

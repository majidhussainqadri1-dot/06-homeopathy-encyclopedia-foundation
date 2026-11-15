<?php
/** Bounded public search with exact/phrase/token/alias/transliteration and spelling recovery. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Search {
	public static function hooks() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'intercept' ), 70, 3 );
	}

	/**
	 * Do not convert a failed DB query into "no matches", a stale cached 200,
	 * or a PHP 8 count()/foreach() warning. Never expose raw SQL errors.
	 */
	private static function unavailable() {
		return new WP_Error( 'he_knowledge_search_unavailable',
			__( 'Encyclopedia search is temporarily unavailable.', 'homeopathy-encyclopedia' ),
			array( 'status' => 503 ) );
	}

	private static function checked_results( $prepared, $format = ARRAY_A ) {
		global $wpdb;
		if ( ! is_string( $prepared ) || '' === $prepared ) {
			return self::unavailable();
		}
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $prepared, $format );
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return self::unavailable();
		}
		return $rows;
	}

	public static function intercept( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || WP_REST_Server::READABLE !== $request->get_method() ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( $prefix . '/entries' === $route ) {
			$data = self::search( $request->get_params() );
			if ( is_wp_error( $data ) ) { return $data; }
			$result = rest_ensure_response( $data );
			$result->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
			$result->header( 'X-File-06-Contract', HE_CONTRACT_VERSION );
			return $result;
		}
		if ( $prefix . '/autocomplete' === $route ) {
			$data = self::autocomplete( (string) $request->get_param( 'q' ), absint( $request->get_param( 'limit' ) ?: 8 ) );
			if ( is_wp_error( $data ) ) { return $data; }
			$result = rest_ensure_response( $data );
			$result->header( 'Cache-Control', 'private, no-store, max-age=0, must-revalidate' );
			return $result;
		}
		return $response;
	}

	private static function filters( $args, &$params ) {
		$where = array();
		$filters = array(
			'type' => array( 'i.type_slug=%s', sanitize_key( $args['type'] ?? '' ) ),
			'body_system' => array( 'i.body_system=%s', sanitize_key( $args['body_system'] ?? '' ) ),
			'language' => array( 'i.language=%s', sanitize_text_field( $args['language'] ?? '' ) ),
			'review_status' => array( 'i.review_status=%s', sanitize_key( $args['review_status'] ?? '' ) ),
			'safety_status' => array( 'i.safety_status=%s', sanitize_key( $args['safety_status'] ?? '' ) ),
			'source_grade' => array( 'i.source_grade=%s', sanitize_key( $args['source_grade'] ?? '' ) ),
			'letter' => array( 'i.first_letter=%s', mb_substr( HE_V2_Domain::normalize( $args['letter'] ?? '' ), 0, 1, 'UTF-8' ) ),
		);
		foreach ( $filters as $filter ) {
			if ( '' !== $filter[1] ) {
				$where[] = $filter[0];
				$params[] = $filter[1];
			}
		}
		return $where;
	}

	public static function search( $args ) {
		global $wpdb;
		if ( 'active' !== HE_V2_Schema::runtime_status() ) { return self::unavailable(); }
		$limit = min( 50, max( 1, absint( $args['limit'] ?? 20 ) ) );
		$cursor = max( 0, absint( $args['cursor'] ?? 0 ) );
		$term = HE_V2_Domain::normalize( $args['q'] ?? '' );
		$where = array( "c.status='published'", "c.review_status='approved'", "c.safety_status='approved'", 'c.merged_into_id=0', 'c.current_version>0', 'c.id>%d' );
		$params = array( $cursor );
		$where = array_merge( $where, self::filters( $args, $params ) );

		if ( $term ) {
			$match = array();
			$match[] = 'EXISTS (SELECT 1 FROM ' . HE_V2_Schema::table( 'aliases' ) . ' ax WHERE ax.concept_id=c.id AND ax.normalized_alias=%s)';
			$params[] = $term;
			$match[] = 'i.search_text LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $term ) . '%';
			$tokens = array_values( array_filter( preg_split( '/\s+/u', $term ) ) );
			$token_match = array();
			foreach ( array_slice( $tokens, 0, 8 ) as $token ) {
				$token_match[] = 'i.search_text LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $token ) . '%';
			}
			if ( $token_match ) {
				$match[] = '(' . implode( ' AND ', $token_match ) . ')';
			}
			$where[] = '(' . implode( ' OR ', $match ) . ')';
		}

		$params[] = $limit + 1;
		$sql = 'SELECT DISTINCT c.* FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'search_index' ) . ' i ON i.concept_id=c.id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY c.id ASC LIMIT %d';
		$rows = self::checked_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( is_wp_error( $rows ) ) { return $rows; }
		$mode = $term ? 'exact-phrase-token-alias' : 'browse';
		if ( $term && ! $rows ) {
			$rows = self::spelling_rows( $term, $args, $cursor, $limit + 1 );
			if ( is_wp_error( $rows ) ) { return $rows; }
			$mode = $rows ? 'spelling-recovery' : 'no-match';
		}
		$has_more = count( $rows ) > $limit;
		$rows = array_slice( $rows, 0, $limit );
		$items = array();
		foreach ( $rows as $row ) {
			$dto = HE_V2_Domain::public_dto( $row );
			if ( ! $dto ) {
				continue;
			}
			$items[] = array(
				'id' => $dto['id'], 'title' => $dto['title'], 'summary' => $dto['summary'], 'type' => $dto['type'],
				'body_system' => $dto['body_system'], 'language' => $dto['language'], 'url' => $dto['canonical_url'],
				'version' => $dto['version'], 'safety_status' => $dto['safety_status'], 'updated_at' => $dto['freshness']['updated_at'],
			);
		}
		return array( 'items' => $items, 'next_cursor' => $has_more && $rows ? (int) end( $rows )['id'] : null, 'limit' => $limit, 'search_mode' => $mode, 'normalized_query' => $term );
	}

	private static function spelling_rows( $term, $args, $cursor, $limit ) {
		global $wpdb;
		$first = mb_substr( $term, 0, 1, 'UTF-8' );
		$length = mb_strlen( $term, 'UTF-8' );
		$candidates = self::checked_results( $wpdb->prepare(
			'SELECT a.concept_id,a.normalized_alias FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=a.concept_id WHERE c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 AND c.current_version>0 AND c.id>%d AND a.normalized_alias LIKE %s AND CHAR_LENGTH(a.normalized_alias) BETWEEN %d AND %d ORDER BY a.id ASC LIMIT 200",
			absint( $cursor ), $wpdb->esc_like( $first ) . '%', max( 1, $length - 4 ), $length + 4
		), ARRAY_A );
		if ( is_wp_error( $candidates ) ) { return $candidates; }
		$scores = array();
		foreach ( $candidates as $candidate ) {
			$percent = 0.0;
			similar_text( $term, (string) $candidate['normalized_alias'], $percent );
			if ( $percent >= 72.0 ) {
				$id = absint( $candidate['concept_id'] );
				$scores[ $id ] = max( $scores[ $id ] ?? 0, $percent );
			}
		}
		if ( ! $scores ) {
			return array();
		}
		arsort( $scores, SORT_NUMERIC );
		$ids = array_slice( array_keys( $scores ), 0, min( 100, $limit * 4 ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params = array_map( 'absint', $ids );
		$where = array( 'c.id IN (' . $placeholders . ')', "c.status='published'", "c.review_status='approved'", "c.safety_status='approved'", 'c.merged_into_id=0', 'c.current_version>0', 'c.id>%d' );
		$params[] = absint( $cursor );
		$where = array_merge( $where, self::filters( $args, $params ) );
		$params[] = min( 51, max( 1, absint( $limit ) ) );
		$sql = 'SELECT DISTINCT c.* FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'search_index' ) . ' i ON i.concept_id=c.id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY c.id ASC LIMIT %d';
		return self::checked_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function autocomplete( $q, $limit = 8 ) {
		global $wpdb;
		$q = HE_V2_Domain::normalize( $q );
		if ( 'active' !== HE_V2_Schema::runtime_status() ) { return self::unavailable(); }
		if ( mb_strlen( $q, 'UTF-8' ) < 2 ) {
			return array();
		}
		$limit = min( 10, max( 1, absint( $limit ) ) );
		$rows = self::checked_results( $wpdb->prepare(
			'SELECT DISTINCT a.alias,a.normalized_alias,c.public_id,c.post_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=a.concept_id WHERE a.normalized_alias LIKE %s AND c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 AND c.current_version>0 ORDER BY a.is_primary DESC,a.alias ASC LIMIT %d",
			$wpdb->esc_like( $q ) . '%', $limit
		), ARRAY_A );
		if ( is_wp_error( $rows ) ) { return $rows; }
		if ( ! $rows ) {
			$first = mb_substr( $q, 0, 1, 'UTF-8' );
			$candidates = self::checked_results( $wpdb->prepare(
				'SELECT DISTINCT a.alias,a.normalized_alias,c.public_id,c.post_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=a.concept_id WHERE a.normalized_alias LIKE %s AND c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 AND c.current_version>0 ORDER BY a.id ASC LIMIT 100",
				$wpdb->esc_like( $first ) . '%'
			), ARRAY_A );
			if ( is_wp_error( $candidates ) ) { return $candidates; }
			foreach ( $candidates as $candidate ) {
				$percent = 0.0;
				similar_text( $q, mb_substr( (string) $candidate['normalized_alias'], 0, max( mb_strlen( $q, 'UTF-8' ), 2 ), 'UTF-8' ), $percent );
				if ( $percent >= 70.0 ) {
					$rows[] = $candidate;
					if ( count( $rows ) >= $limit ) {
						break;
					}
				}
			}
		}
		return array_map( static function( $row ) {
			return array( 'label' => $row['alias'], 'id' => $row['public_id'], 'url' => get_permalink( (int) $row['post_id'] ) );
		}, array_slice( $rows, 0, $limit ) );
	}
}

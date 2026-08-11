<?php
/** File 06 v2.4.2 public research browse parity and bounded pagination. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Research_Browse {
	const SCAN_BATCH = 50;
	const MAX_SCAN = 500;

	public static function hooks() {
		/* Runs before the v2.4.2 general early guard, so invalid rows cannot starve later valid rows. */
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 65, 3 );
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request || 'GET' !== $request->get_method() || '/' . HE_V2_API::NS . '/research' !== $request->get_route() ) {
			return $response;
		}
		return rest_ensure_response( self::browse( $request ) );
	}

	private static function valid( $row ) {
		if ( ! is_array( $row ) || ! in_array( $row['status'], array( 'published','corrected','retracted' ), true ) || empty( $row['post_id'] ) ) {
			return false;
		}
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return false;
		}
		return HE_V22_Research_Guard::public_surface_eligible( $row );
	}

	private static function dto( $row ) {
		if ( ! self::valid( $row ) ) { return null; }
		$out = array(
			'id' => $row['public_id'],
			'record_type' => $row['record_type'],
			'status' => $row['status'],
			'title' => $row['title'],
			'question' => $row['question'],
			'case_tag' => $row['case_tag'],
			'canonical_url' => home_url( '/research/' . rawurlencode( $row['public_id'] ) . '/' ),
			'updated_at' => $row['updated_at'],
			'freshness' => array( 'contract_version' => HE_CONTRACT_VERSION, 'updated_at' => $row['updated_at'] ),
		);
		if ( 'retracted' === $row['status'] ) {
			$out['protocol'] = '';
			$out['notice'] = __( 'This research record has been retracted. Metadata remains visible for correction and citation integrity.', 'homeopathy-encyclopedia' );
		} else {
			$out['protocol'] = 'public' === $row['data_class'] ? $row['protocol'] : '';
		}
		if ( 'successful-case' === $row['record_type'] ) {
			$case = json_decode( (string) $row['case_json'], true );
			if ( 'public' === $row['data_class'] && 'retracted' !== $row['status'] ) {
				$out['case'] = is_array( $case ) ? $case : array();
			} else {
				$out['case_details_restricted'] = true;
			}
		}
		if ( 'dataset' === $row['record_type'] ) {
			$metadata = json_decode( (string) $row['metadata_json'], true );
			$metadata = is_array( $metadata ) ? $metadata : array();
			$public_metadata = array();
			foreach ( array( 'description','de_identification','lawful_basis','access_policy' ) as $field ) {
				if ( isset( $metadata[ $field ] ) && is_scalar( $metadata[ $field ] ) ) {
					$public_metadata[ $field ] = sanitize_textarea_field( (string) $metadata[ $field ] );
				}
			}
			$out['dataset_metadata'] = $public_metadata;
			$out['dataset_payload_public'] = false;
		}
		return $out;
	}

	private static function browse( WP_REST_Request $request ) {
		global $wpdb;
		$limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$cursor = HE_V2_Domain::decode_public_cursor( 'research', $request->get_param( 'cursor' ) );
		if ( null === $cursor ) { return new WP_Error( 'he_invalid_cursor', __( 'The research pagination cursor is invalid or has been altered.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) ); }
		$scan_cursor = $cursor;
		$items = array();
		$scanned = 0;
		$has_more = false;
		while ( count( $items ) < $limit && $scanned < self::MAX_SCAN ) {
			$take = min( self::SCAN_BATCH, self::MAX_SCAN - $scanned );
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT r.* FROM ' . HE_V2_Schema::table( 'research' ) . ' r INNER JOIN ' . $wpdb->posts . " p ON p.ID=r.post_id AND p.post_type=%s AND p.post_status='publish' WHERE r.status IN ('published','corrected','retracted') AND r.id>%d ORDER BY r.id ASC LIMIT %d",
				HE_V2_Domain::RESEARCH_TYPE, $scan_cursor, $take
			), ARRAY_A );
			if ( ! $rows ) { $has_more = false; break; }
			foreach ( $rows as $row ) {
				$scan_cursor = (int) $row['id'];
				++$scanned;
				$dto = self::dto( $row );
				if ( $dto ) { $items[] = $dto; }
				if ( count( $items ) >= $limit || $scanned >= self::MAX_SCAN ) { break; }
			}
			if ( count( $rows ) < $take ) { $has_more = false; break; }
			$has_more = true;
		}
		if ( count( $items ) >= $limit ) {
			$has_more = (bool) $wpdb->get_var( $wpdb->prepare(
				'SELECT 1 FROM ' . HE_V2_Schema::table( 'research' ) . ' r INNER JOIN ' . $wpdb->posts . " p ON p.ID=r.post_id AND p.post_type=%s AND p.post_status='publish' WHERE r.status IN ('published','corrected','retracted') AND r.id>%d LIMIT 1",
				HE_V2_Domain::RESEARCH_TYPE, $scan_cursor
			) );
		}
		return array(
			'items' => array_slice( $items, 0, $limit ),
			'next_cursor' => $has_more && $scan_cursor ? HE_V2_Domain::encode_public_cursor( 'research', $scan_cursor ) : null,
			'limit' => $limit,
			'scanned' => $scanned,
			'governance_filtered' => true,
			'scan_cap' => self::MAX_SCAN,
		);
	}
}

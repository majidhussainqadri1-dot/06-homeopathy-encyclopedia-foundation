<?php
/** Scheduled publication must revalidate the exact approved content at execution time. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Schedule {
	public static function hooks() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'capture_schedule_evidence' ), 85, 3 );
		/* Run before the inherited maintenance publisher so every due row is either safely published or fail-closed. */
		add_action( 'he_v2_maintenance', array( __CLASS__, 'publish_due_securely' ), 5 );
	}

	private static function fingerprint( $row ) {
		$post = get_post( (int) $row['post_id'] );
		if ( ! $post ) {
			return '';
		}
		global $wpdb;
		$payload = array(
			'title' => $post->post_title,
			'excerpt' => $post->post_excerpt,
			'body' => $post->post_content,
			'type' => $row['type_slug'],
			'language' => $row['language'],
			'body_system' => HE_V2_Domain::taxonomy_slug( (int) $row['post_id'], HE_V2_Domain::TAX_SYSTEM ),
			'structured' => get_post_meta( (int) $row['post_id'], '_he_structured', true ),
			'safety' => get_post_meta( (int) $row['post_id'], '_he_safety_status', true ),
			'references' => $wpdb->get_results( $wpdb->prepare( 'SELECT source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,quotation_word_count FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d ORDER BY id ASC', (int) $row['id'] ), ARRAY_A ),
		);
		return hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	public static function capture_schedule_evidence( $response, $handler, $request ) {
		if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( ! preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/([^/]+)/transition$#', $route, $m ) || WP_REST_Server::CREATABLE !== $request->get_method() ) {
			return $response;
		}
		$data = (array) $request->get_json_params();
		if ( 'scheduled' !== sanitize_key( $data['state'] ?? '' ) ) {
			return $response;
		}
		$row = HE_V2_Domain::concept_by_id( $m[1], true );
		if ( ! $row || 'scheduled' !== $row['status'] ) {
			return $response;
		}
		update_post_meta( (int) $row['post_id'], '_he_schedule_content_hash', self::fingerprint( $row ) );
		update_post_meta( (int) $row['post_id'], '_he_schedule_founder_direct', HE_V2_Auth::is_founder() ? 1 : 0 );
		update_post_meta( (int) $row['post_id'], '_he_schedule_actor', get_current_user_id() );
		return $response;
	}

	private static function approved_for_current_content( $row, $fingerprint ) {
		if ( ! $fingerprint ) {
			return false;
		}
		$saved = (string) get_post_meta( (int) $row['post_id'], '_he_schedule_content_hash', true );
		if ( ! $saved || ! hash_equals( $saved, $fingerprint ) ) {
			return false;
		}
		if ( (bool) get_post_meta( (int) $row['post_id'], '_he_schedule_founder_direct', true ) ) {
			return true;
		}
		global $wpdb;
		$post = get_post( (int) $row['post_id'] );
		$review = $wpdb->get_row( $wpdb->prepare( 'SELECT reviewer_id FROM ' . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0 AND content_hash=%s ORDER BY id DESC LIMIT 1", (int) $row['id'], $fingerprint ), ARRAY_A );
		return $review && ( ! $post || (int) $review['reviewer_id'] !== (int) $post->post_author );
	}

	private static function clear_schedule_meta( $post_id ) {
		foreach ( array( '_he_scheduled_at', '_he_schedule_content_hash', '_he_schedule_founder_direct', '_he_schedule_actor' ) as $key ) {
			delete_post_meta( absint( $post_id ), $key );
		}
	}

	public static function publish_due_securely() {
		global $wpdb;
		$table = HE_V2_Schema::table( 'concepts' );
		$rows = $wpdb->get_results( "SELECT c.* FROM {$table} c INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=c.post_id AND pm.meta_key='_he_scheduled_at' WHERE c.status='scheduled' AND pm.meta_value<=UTC_TIMESTAMP() ORDER BY c.id ASC LIMIT 50", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$published = 0;
		$invalidated = 0;
		foreach ( $rows as $row ) {
			$fingerprint = self::fingerprint( $row );
			if ( ! self::approved_for_current_content( $row, $fingerprint ) ) {
				$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='review',review_status='pending',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d", (int) $row['id'], (int) $row['row_version'] ) );
				if ( 1 === (int) $updated ) {
					self::clear_schedule_meta( (int) $row['post_id'] );
					HE_V22_Governance::reindex_concept_secure( (int) $row['id'] );
					HE_V2_Domain::emit_event( 'EncyclopediaEntryScheduleInvalidated.v1', 'concept', (int) $row['id'], array( 'reason' => 'content-or-review-changed-before-publication' ) );
					$invalidated++;
				}
				continue;
			}

			$validation = HE_V2_Domain::validate_for_review( (int) $row['id'] );
			if ( is_wp_error( $validation ) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='review',review_status='pending',row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d", (int) $row['id'], (int) $row['row_version'] ) );
				self::clear_schedule_meta( (int) $row['post_id'] );
				$invalidated++;
				continue;
			}
			$version_id = HE_V2_Domain::snapshot_version( (int) $row['id'], 'Scheduled publication', 'published', absint( get_post_meta( (int) $row['post_id'], '_he_schedule_actor', true ) ) );
			if ( ! $version_id ) {
				continue;
			}
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='published',review_status='approved',safety_status='approved',current_version=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled' AND row_version=%d", $version_id, (int) $row['id'], (int) $row['row_version'] ) );
			if ( 1 !== (int) $updated ) {
				/* Snapshot is immutable but not current; keeping it is safer than publishing a stale row. */
				continue;
			}
			wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
			self::clear_schedule_meta( (int) $row['post_id'] );
			HE_V22_Governance::reindex_concept_secure( (int) $row['id'] );
			HE_V2_Domain::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', (int) $row['id'], array( 'version_id' => $version_id, 'scheduled' => true, 'content_hash' => $fingerprint ) );
			$published++;
		}
		return array( 'checked' => count( $rows ), 'published' => $published, 'invalidated' => $invalidated );
	}
}

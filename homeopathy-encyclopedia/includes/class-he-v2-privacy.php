<?php
/** WordPress privacy lifecycle, bounded exporters/erasers and cache controls. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Privacy {
	const PAGE_SIZE = 50;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
		add_action( 'template_redirect', array( $this, 'cache_control' ), 1 );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['he-v2'] = array(
			'exporter_friendly_name' => __( 'Homeopathy Encyclopedia and Research', 'homeopathy-encyclopedia' ),
			'callback' => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['he-v2'] = array(
			'eraser_friendly_name' => __( 'Homeopathy Encyclopedia and Research', 'homeopathy-encyclopedia' ),
			'callback' => array( $this, 'erase' ),
		);
		return $erasers;
	}

	private function export_rows( $group_id, $group_label, $prefix, $rows, $id_key = '' ) {
		$data = array();
		foreach ( $rows as $index => $row ) {
			$item = $id_key && isset( $row[ $id_key ] ) ? (string) $row[ $id_key ] : (string) $index;
			$values = array();
			foreach ( $row as $key => $value ) {
				$values[] = array(
					'name' => (string) $key,
					'value' => is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value ),
				);
			}
			$data[] = array(
				'group_id' => $group_id,
				'group_label' => $group_label,
				'item_id' => $prefix . $item,
				'data' => $values,
			);
		}
		return $data;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$page   = max( 1, absint( $page ) );
		$limit  = self::PAGE_SIZE;
		$offset = ( $page - 1 ) * $limit;
		$data   = array();
		$more   = false;

		$posts = get_posts( array(
			'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ),
			'post_status' => array( 'draft', 'pending', 'publish', 'private', 'future' ),
			'author' => $user->ID,
			'posts_per_page' => $limit + 1,
			'offset' => $offset,
			'orderby' => 'ID',
			'order' => 'ASC',
		) );
		$more = $more || count( $posts ) > $limit;
		foreach ( array_slice( $posts, 0, $limit ) as $post ) {
			$data[] = array(
				'group_id' => 'he-v2-authored',
				'group_label' => __( 'Authored Encyclopedia and Research Records', 'homeopathy-encyclopedia' ),
				'item_id' => 'post-' . $post->ID,
				'data' => array(
					array( 'name' => __( 'Object type', 'homeopathy-encyclopedia' ), 'value' => $post->post_type ),
					array( 'name' => __( 'Title', 'homeopathy-encyclopedia' ), 'value' => $post->post_title ),
					array( 'name' => __( 'Status', 'homeopathy-encyclopedia' ), 'value' => $post->post_status ),
					array( 'name' => __( 'Created', 'homeopathy-encyclopedia' ), 'value' => $post->post_date_gmt ),
					array( 'name' => __( 'Modified', 'homeopathy-encyclopedia' ), 'value' => $post->post_modified_gmt ),
				),
			);
		}

		$queries = array(
			array( 'reviews', 'he-v2-reviews', __( 'Encyclopedia Review Records', 'homeopathy-encyclopedia' ), 'review-', 'id', 'reviewer_id=%d' ),
			array( 'integrity_actions', 'he-v2-integrity', __( 'Correction and Retraction Requests', 'homeopathy-encyclopedia' ), 'integrity-', 'public_id', 'created_by=%d' ),
			array( 'bookmarks', 'he-v2-bookmarks', __( 'Saved Encyclopedia Entries', 'homeopathy-encyclopedia' ), 'bookmark-', 'id', 'user_id=%d' ),
			array( 'dataset_access', 'he-v2-dataset-access', __( 'Research Dataset Access Requests', 'homeopathy-encyclopedia' ), 'dataset-access-', 'id', 'requester_id=%d OR approved_by=%d' ),
			array( 'events', 'he-v2-events', __( 'Encyclopedia Audit Events', 'homeopathy-encyclopedia' ), 'event-', 'event_id', 'actor_id=%d' ),
		);
		foreach ( $queries as $query ) {
			list( $table_key, $group, $label, $prefix, $id_key, $where ) = $query;
			$table = HE_V2_Schema::table( $table_key );
			$params = false !== strpos( $where, 'OR' ) ? array( $user->ID, $user->ID, $limit + 1, $offset ) : array( $user->ID, $limit + 1, $offset );
			$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$more = $more || count( $rows ) > $limit;
			$data = array_merge( $data, $this->export_rows( $group, $label, $prefix, array_slice( $rows, 0, $limit ), $id_key ) );
		}

		return array( 'data' => $data, 'done' => ! $more );
	}

	private function select_ids( $table_key, $where_sql, $params, $limit = 100 ) {
		global $wpdb;
		$table = HE_V2_Schema::table( $table_key );
		$params[] = absint( $limit );
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT %d", $params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function deidentify_ids( $table_key, $ids, $data ) {
		global $wpdb;
		if ( ! $ids ) {
			return 0;
		}
		$table = HE_V2_Schema::table( $table_key );
		$count = 0;
		foreach ( $ids as $id ) {
			$result = $wpdb->update( $table, $data, array( 'id' => absint( $id ) ) );
			if ( false !== $result ) {
				$count++;
			}
		}
		return $count;
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$uid      = absint( $user->ID );
		$hold     = (bool) apply_filters( 'he_v2_privacy_legal_hold', false, $uid );
		$removed  = false;
		$retained = false;
		$messages = array();

		if ( $hold ) {
			$retained = true;
			$messages[] = __( 'A documented legal or research-integrity hold is active. File 06 retained the governed records and exposed the hold for review rather than silently deleting them.', 'homeopathy-encyclopedia' );
			HE_V2_Domain::emit_event( 'File06PrivacyErasureHeld.v1', 'user', $uid, array( 'legal_hold' => true ) );
			return array( 'items_removed' => false, 'items_retained' => true, 'messages' => $messages, 'done' => true );
		}

		/* Account-owned convenience/security records may be deleted outright. */
		foreach ( array( 'bookmarks' => 'user_id', 'dataset_access' => 'requester_id', 'idempotency' => 'actor_id' ) as $table_key => $column ) {
			$table = HE_V2_Schema::table( $table_key );
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column}=%d LIMIT 250", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$removed = $removed || (int) $result > 0;
		}

		/* Integrity records remain interpretable but personal reviewer/submitter identity is removed. */
		$review_ids = $this->select_ids( 'reviews', 'reviewer_id=%d', array( $uid ) );
		if ( $review_ids ) {
			$this->deidentify_ids( 'reviews', $review_ids, array( 'reviewer_id' => 0, 'note' => '[review note de-identified after verified privacy request]' ) );
			$removed = true;
			$retained = true;
		}
		$integrity_ids = $this->select_ids( 'integrity_actions', 'created_by=%d OR decided_by=%d', array( $uid, $uid ) );
		if ( $integrity_ids ) {
			$this->deidentify_ids( 'integrity_actions', $integrity_ids, array( 'created_by' => 0, 'decided_by' => 0, 'evidence' => '[personal evidence removed after verified privacy request]' ) );
			$removed = true;
			$retained = true;
		}

		/* Author identity on immutable/public knowledge is detached; unpublished drafts are removed. */
		$posts = get_posts( array(
			'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ),
			'post_status' => array( 'draft', 'pending', 'publish', 'private', 'future' ),
			'author' => $uid,
			'posts_per_page' => 100,
			'orderby' => 'ID',
			'order' => 'ASC',
			'fields' => 'ids',
		) );
		foreach ( $posts as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				$result = wp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ), true );
				if ( is_wp_error( $result ) ) { $messages[] = __( 'A published record could not be de-identified and was retained for retry.', 'homeopathy-encyclopedia' ); } else { $retained = true; }
			} else {
				/* Canonical draft hard-delete is governance-blocked; de-identify ownership instead of falsely claiming deletion. */
				$result = wp_update_post( array( 'ID' => $post_id, 'post_author' => 0 ), true );
				if ( is_wp_error( $result ) ) { $messages[] = __( 'An unpublished governed draft could not be de-identified and was retained for retry.', 'homeopathy-encyclopedia' ); } else { $retained = true; $removed = true; }
			}
		}

		/* De-identify actor columns that are not account-owned data. */
		$actor_columns = array(
			'concepts' => 'created_by',
			'aliases' => 'created_by',
			'versions' => 'created_by',
			'references' => 'created_by',
			'relations' => 'created_by',
			'research' => 'created_by',
			'events' => 'actor_id',
		);
		foreach ( $actor_columns as $table_key => $column ) {
			$ids = $this->select_ids( $table_key, $column . '=%d', array( $uid ) );
			if ( $ids ) {
				$this->deidentify_ids( $table_key, $ids, array( $column => 0 ) );
				$removed = true;
				$retained = true;
			}
		}
		$approved_ids = $this->select_ids( 'dataset_access', 'approved_by=%d', array( $uid ) );
		if ( $approved_ids ) {
			$this->deidentify_ids( 'dataset_access', $approved_ids, array( 'approved_by' => 0 ) );
			$removed = true;
			$retained = true;
		}

		/* Successful erasure must not leave/recreate the erased user as an event object identifier. */
		$events_table = HE_V2_Schema::table( 'events' );
		$event_objects = $wpdb->query( $wpdb->prepare( "UPDATE {$events_table} SET object_id='0' WHERE object_type='user' AND object_id=%s", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $event_objects ) {
			HE_V2_Schema::record_runtime_failure( 'privacy_event_object_deidentification_failed', 'File 06 could not de-identify user-bound event object identifiers.' );
		} elseif ( (int) $event_objects > 0 ) {
			$removed = true; $retained = true;
		}

		$remaining = 0;
		$remaining += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'bookmarks' ) . ' WHERE user_id=%d', $uid ) );
		$remaining += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'dataset_access' ) . ' WHERE requester_id=%d OR approved_by=%d', $uid, $uid ) );
		$remaining += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'reviews' ) . ' WHERE reviewer_id=%d', $uid ) );
		$remaining += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE created_by=%d OR decided_by=%d', $uid, $uid ) );
		$remaining += count( get_posts( array( 'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), 'post_status' => array( 'draft', 'pending', 'publish', 'private', 'future' ), 'author' => $uid, 'posts_per_page' => 1, 'fields' => 'ids' ) ) );
		$remaining += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE object_type='user' AND object_id=%s", (string) $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $actor_columns as $table_key => $column ) {
			$remaining += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( $table_key ) . " WHERE {$column}=%d", $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $retained ) {
			$messages[] = __( 'Published knowledge, research and institutional integrity history were retained only in de-identified form so citations and correction history remain interpretable.', 'homeopathy-encyclopedia' );
		}
		$done = 0 === $remaining;
		if ( $done ) {
			HE_V2_Domain::emit_event( 'File06PrivacyErasureCompleted.v1', 'privacy-request', 0, array( 'published_records_retained' => $retained ) );
		}
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => array_values( array_unique( $messages ) ), 'done' => $done );
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Homeopathy Encyclopedia and Research', 'homeopathy-encyclopedia' ),
				wp_kses_post( wpautop( __( 'The encyclopedia stores public canonical knowledge, immutable version history, sources, reviews, corrections, research metadata, restricted dataset-access requests and minimized audit events. Public DTOs use explicit allowlists. Drafts, rejected records, access facts and private material are excluded from public caches and search indexes. Verified privacy requests are processed in bounded resumable batches. Published knowledge and integrity history may be retained only in de-identified form where citation, research-integrity or documented legal-hold obligations require it.', 'homeopathy-encyclopedia' ) ) )
			);
		}
	}

	public function cache_control() {
		if ( is_user_logged_in() && ( is_singular( HE_V2_Domain::ENTRY_TYPE ) || is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) ) {
			nocache_headers();
		}
		if ( is_admin() || current_user_can( HE_V2_Auth::CAP_EDIT ) || current_user_can( HE_V2_Auth::CAP_RESEARCH ) || current_user_can( HE_V2_Auth::CAP_REVIEW ) ) {
			nocache_headers();
		}
	}

	public function headers( $headers ) {
		if ( is_singular( HE_V2_Domain::ENTRY_TYPE ) || is_singular( HE_V2_Domain::RESEARCH_TYPE ) ) {
			$headers['X-Content-Type-Options'] = 'nosniff';
			$headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
			$headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';
		}
		return $headers;
	}
}

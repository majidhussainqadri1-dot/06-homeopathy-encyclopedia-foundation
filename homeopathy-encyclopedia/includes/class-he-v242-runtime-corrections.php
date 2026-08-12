<?php
/** Focused v2.4.2 corrections found while continuing the third 80-round audit. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Runtime_Corrections {
	public static function hooks() {
		/* Replace the earlier rollback callback with schema-accurate Future-18 child checks. */
		add_filter( 'sabri_composer_content_types', array( __CLASS__, 'composer_contract' ), 1002 );
		/* Verify research-create conflict normalization before returning success, then return the exact row version. */
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'research_create_response_parity' ), 370, 3 );
	}

	public static function composer_contract( $types ) {
		$types = is_array( $types ) ? $types : array();
		if ( isset( $types['file06_encyclopedia_entry'] ) && is_array( $types['file06_encyclopedia_entry'] ) ) {
			$types['file06_encyclopedia_entry']['rollback_command'] = array( __CLASS__, 'composer_rollback_safe' );
			$types['file06_encyclopedia_entry']['governed_pristine_rollback'] = true;
		}
		return $types;
	}

	private static function count_where( $table, $sql, $params ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$sql}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function has_future_children( $row ) {
		if ( ! class_exists( 'HE_V24_Future_Schema' ) ) { return false; }
		if ( ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Future_Schema::schema_complete() || ! HE_V24_Migration_Safety::ready() ) {
			HE_V2_Schema::record_runtime_failure( 'composer_rollback_future_state_unverified', 'Pristine composer rollback was denied because Future-18 child state could not be verified safely.' );
			return true;
		}
		$id = (int) $row['id'];
		$public_id = (string) $row['public_id'];
		foreach ( array( 'claims','external_records','concept_mappings','freshness','research_gaps','translations' ) as $suffix ) {
			if ( self::count_where( HE_V24_Future_Schema::table( $suffix ), 'concept_id=%d', array( $id ) ) ) {
				return true;
			}
		}
		$similarity = HE_V24_Future_Schema::table( 'similarity' );
		if ( self::count_where( $similarity, 'concept_a=%d OR concept_b=%d', array( $id, $id ) ) ) {
			return true;
		}
		$watchlists = HE_V24_Future_Schema::table( 'watchlists' );
		if ( self::count_where( $watchlists, "object_type='concept' AND (object_id=%s OR object_id=%s)", array( (string) $id, $public_id ) ) ) {
			return true;
		}
		$impact = HE_V24_Future_Schema::table( 'impact_queue' );
		if ( self::count_where( $impact, "source_type='concept' AND (source_id=%s OR source_id=%s)", array( (string) $id, $public_id ) ) ) {
			return true;
		}
		$provenance = HE_V24_Future_Schema::table( 'provenance' );
		if ( self::count_where( $provenance, "object_type='concept' AND (object_id=%s OR object_id=%s)", array( (string) $id, $public_id ) ) ) {
			return true;
		}
		return false;
	}

	public static function composer_rollback_safe( $native_id, $context = array() ) {
		$row = HE_V2_Domain::concept_by_id( $native_id, true );
		$actor_id = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : get_current_user_id();
		if ( ! $row || 'draft' !== $row['status'] || (int) $row['current_version'] || ! HE_V241_Governance::editor_type_allowed( $actor_id, $row['type_slug'] ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, (int) $row['post_id'], 'file06-composer-rollback', $actor_id ) ) {
			return false;
		}
		if ( self::has_future_children( $row ) ) {
			return false;
		}
		global $wpdb;
		$concept_id = (int) $row['id'];
		$post_id = (int) $row['post_id'];
		$delete_guard = array( 'HE_V242_Third_Audit', 'guard_hard_delete' );
		$domain_pre_delete = array( 'HE_V2_Domain', 'pre_delete_post' );
		$domain_deleted = array( 'HE_V2_Domain', 'on_deleted_post' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			HE_V2_Schema::record_runtime_failure( 'composer_rollback_start_failed', 'File 06 could not start the entry composer rollback transaction.' );
			return false;
		}
		/* Normal WordPress hard deletion is governance-blocked. A pristine composer rollback is the narrowly scoped exception, so suppress both the high-priority block and the lower-level archive/retraction lifecycle while this compensation transaction owns the physical deletion. */
		remove_filter( 'pre_delete_post', $delete_guard, 1 );
		remove_filter( 'pre_delete_post', $domain_pre_delete, 10 );
		remove_action( 'deleted_post', $domain_deleted, 10 );
		try {
			/* Delete the WordPress post inside the same DB transaction; if any later cleanup fails, rollback restores both sides. */
			$deleted_post = wp_delete_post( $post_id, true );
			if ( ! $deleted_post ) { throw new RuntimeException( 'post-delete-failed' ); }
			$relation_deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d', $concept_id, $concept_id ) );
			if ( false === $relation_deleted ) { throw new RuntimeException( 'relation-delete-failed' ); }
			foreach ( array( 'aliases','references','versions','search_index','bookmarks' ) as $suffix ) {
				if ( false === $wpdb->delete( HE_V2_Schema::table( $suffix ), array( 'concept_id' => $concept_id ), array( '%d' ) ) ) { throw new RuntimeException( 'child-delete-failed' ); }
			}
			if ( false === $wpdb->delete( HE_V2_Schema::table( 'reviews' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'review-delete-failed' ); }
			if ( false === $wpdb->delete( HE_V2_Schema::table( 'integrity_actions' ), array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s','%d' ) ) ) { throw new RuntimeException( 'integrity-delete-failed' ); }
			if ( 1 !== (int) $wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) ) ) { throw new RuntimeException( 'concept-delete-failed' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'entry-rollback-commit-failed' ); }
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			/* wp_delete_post() can invalidate object cache before SQL rollback restores rows. */
			clean_post_cache( $post_id );
			HE_V2_Schema::record_runtime_failure( 'composer_rollback_failed', sanitize_text_field( $error->getMessage() ) );
			return false;
		} finally {
			add_filter( 'pre_delete_post', $delete_guard, 1, 3 );
			add_filter( 'pre_delete_post', $domain_pre_delete, 10, 3 );
			add_action( 'deleted_post', $domain_deleted, 10, 2 );
		}
		HE_V2_Domain::emit_event( 'EncyclopediaDraftRolledBack.v1', 'concept', $concept_id, array( 'public_id' => $row['public_id'], 'reason' => 'composer-compensation' ) );
		return true;
	}

	private static function response_public_id( $response ) {
		if ( ! $response instanceof WP_REST_Response ) {
			return '';
		}
		$data = $response->get_data();
		if ( is_array( $data ) && isset( $data['data']['id'] ) ) {
			return sanitize_text_field( (string) $data['data']['id'] );
		}
		return is_array( $data ) && isset( $data['id'] ) ? sanitize_text_field( (string) $data['id'] ) : '';
	}

	public static function verify_research_create_normalization( $response, $handler, $request ) {
		/* Retained as a verification helper for diagnostics; it must never mutate state after route/idempotency success. */
		if ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response || is_wp_error( $response ) || 'POST' !== $request->get_method() || '/' . HE_V2_API::NS . '/research' !== $request->get_route() ) { return $response; }
		$public_id = self::response_public_id( $response );
		if ( ! $public_id ) { return $response; }
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT conflicts_json FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
		$input = (array) $request->get_json_params();
		$expected = HE_V2_Domain::normalize_conflicts( $input['conflicts'] ?? array() );
		$stored = $row ? json_decode( (string) $row['conflicts_json'], true ) : null;
		if ( ! $row || ! $expected || $stored !== $expected ) {
			update_option( HE_V2_Schema::OPTION_SAFE_MODE, 1, false );
			HE_V2_Schema::record_runtime_failure( 'research_conflict_postsuccess_invariant_failed', 'A completed research create response failed canonical conflict verification; no post-success mutation was attempted.' );
		}
		return $response;
	}

	public static function research_create_response_parity( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response || is_wp_error( $response ) || 'POST' !== $request->get_method() || '/' . HE_V2_API::NS . '/research' !== $request->get_route() ) {
			return $response;
		}
		$data = $response->get_data();
		$public_id = self::response_public_id( $response );
		if ( ! $public_id ) {
			return $response;
		}
		global $wpdb;
		$row_version = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT row_version FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ) );
		if ( ! $row_version ) {
			return $response;
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$data['data']['row_version'] = $row_version;
		} elseif ( is_array( $data ) ) {
			$data['row_version'] = $row_version;
		}
		$response->set_data( $data );
		return $response;
	}
}

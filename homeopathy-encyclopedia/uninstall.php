<?php
/** Guarded, auditable File 06 uninstall. Default behavior is deliberately non-destructive. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$purge = defined( 'HE_PURGE_ON_UNINSTALL' ) && true === HE_PURGE_ON_UNINSTALL && 'yes' === get_option( 'he_allow_destructive_uninstall' );
if ( ! $purge ) {
	return;
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		he_v2_purge_site();
		restore_current_blog();
	}
} else {
	he_v2_purge_site();
}

function he_v2_purge_site() {
	global $wpdb;
	wp_clear_scheduled_hook( 'he_v2_maintenance' );
	wp_clear_scheduled_hook( 'he_v23_future_maintenance' );
	wp_clear_scheduled_hook( 'he_v24_future_maintenance' );
	foreach ( array(
		'concepts','aliases','versions','references','relations','reviews','integrity_actions','research','dataset_access','events','outbox','idempotency','search_index','bookmarks','feedback','audit_log','metrics','rate_limits','migration_quarantine',
		'claims','claim_evidence','provenance','external_records','concept_mappings','researcher_ids','similarity','freshness','impact_queue','research_gaps','watchlists','translations'
	) as $suffix ) {
		$table = $wpdb->prefix . 'he_' . $suffix;
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$posts = get_posts( array(
		'post_type' => array( 'he_entry', 'he_research' ),
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
	) );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array(
		'he_schema_version','he_v2_runtime_failure','he_v2_safe_mode','he_v2_migration_lock','he_v2_legacy_migrated','he_allow_destructive_uninstall','he_page_map','he_legacy_system_migration',
		'he_v22_extension_version','he_v22_upgrade_lock','he_v22_legacy_cursor','he_v22_legacy_done','he_v22_reindex_cursor','he_v22_reindex_required',
		'he_v23_future_version','he_v24_future_version','he_v24_freshness_cursor','he_v24_gap_cursor','he_v24_retraction_cursor',
		'he_v24_provenance_migration_cursor','he_v24_impact_migration_cursor','he_v24_provenance_migration_done','he_v24_impact_migration_done','he_v24_migration_pending'
	) as $option ) {
		delete_option( $option );
	}
	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( array( 'he_edit_entries','he_review_entries','he_publish_entries','he_manage_taxonomy','he_manage_research','he_manage_datasets','he_repair_system' ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

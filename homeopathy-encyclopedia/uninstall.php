<?php
/** Guarded uninstall. Data is retained unless two explicit controls authorize purge. */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function he_uninstall_site() {
	global $wpdb;
	$purge = defined( 'HE_PURGE_ON_UNINSTALL' ) && true === HE_PURGE_ON_UNINSTALL && 'yes' === get_option( 'he_allow_destructive_uninstall', 'no' );
	wp_clear_scheduled_hook( 'he_daily_maintenance' );
	delete_transient( 'he_activation_notice' );
	if ( ! $purge ) {
		update_option( 'he_uninstalled_retained_at', gmdate( 'c' ), false );
		return;
	}

	foreach ( array( 'bookmarks', 'feedback', 'audit_log', 'metrics', 'rate_limits', 'search_index' ) as $suffix ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}he_{$suffix}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='he_entry'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	foreach ( $ids as $entry_id ) {
		wp_delete_post( $entry_id, true );
	}
	$pages = (array) get_option( 'he_page_map', array() );
	foreach ( $pages as $key => $page_id ) {
		$page_id = absint( $page_id );
		if ( $page_id && '1' === get_post_meta( $page_id, '_he_managed_page', true ) && sanitize_key( $key ) === get_post_meta( $page_id, '_he_managed_page_key', true ) ) {
			wp_delete_post( $page_id, true );
		}
	}
	register_taxonomy( 'he_type', 'he_entry' );
	register_taxonomy( 'he_body_system', 'he_entry' );
	foreach ( array( 'he_type', 'he_body_system' ) as $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}
	}
	$caps = array( 'he_manage_encyclopedia', 'he_review_entries', 'he_resolve_feedback', 'he_view_audit_log', 'edit_he_entry', 'read_he_entry', 'delete_he_entry', 'edit_he_entries', 'edit_others_he_entries', 'publish_he_entries', 'read_private_he_entries', 'delete_he_entries', 'delete_private_he_entries', 'delete_published_he_entries', 'delete_others_he_entries', 'edit_private_he_entries', 'edit_published_he_entries', 'create_he_entries', 'manage_homeopathy_encyclopedia' );
	if ( function_exists( 'wp_roles' ) ) {
		$roles = wp_roles();
		foreach ( array_keys( (array) $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $capability ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
	foreach ( array( 'he_page_map', 'he_version', 'he_schema_version', 'he_activation_state', 'he_runtime_failure', 'he_legacy_system_migration', 'he_allow_destructive_uninstall', 'he_uninstalled_retained_at' ) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		he_uninstall_site();
		restore_current_blog();
	}
} else {
	he_uninstall_site();
}

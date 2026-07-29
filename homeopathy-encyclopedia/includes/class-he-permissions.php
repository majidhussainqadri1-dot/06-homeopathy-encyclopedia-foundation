<?php
/** File 00-authoritative permissions. */

defined( 'ABSPATH' ) || exit;

final class HE_Permissions {
	const CAP_MANAGE = 'he_manage_encyclopedia';
	const CAP_REVIEW = 'he_review_entries';
	const CAP_FEEDBACK = 'he_resolve_feedback';
	const CAP_AUDIT = 'he_view_audit_log';

	/** Return the authoritative Founder user ID. */
	public static function founder_id() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='_smc_official_founder' AND meta_value NOT IN ('','0') ORDER BY user_id ASC LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/** Whether the user is the File 00 official Founder. */
	public static function is_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && function_exists( 'smc_is_founder' ) && smc_is_founder( $user_id );
	}

	/** Whether File 00 and corrected File 05 regard the user as an eligible verified doctor. */
	public static function is_verified_doctor( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && class_exists( 'SLC_Permissions' ) && is_callable( array( 'SLC_Permissions', 'is_verified_doctor' ) ) && SLC_Permissions::is_verified_doctor( $user_id );
	}

	/** Whether a user may submit through the governed front-end form. */
	public static function can_submit( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && ( self::is_founder( $user_id ) || user_can( $user_id, self::CAP_MANAGE ) || self::is_verified_doctor( $user_id ) );
	}

	/** Initial WordPress status for a new governed entry. */
	public static function initial_status( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return self::is_founder( $user_id ) || user_can( $user_id, self::CAP_MANAGE ) ? 'publish' : 'pending';
	}

	/** Public author label. */
	public static function label( $user_id ) {
		if ( self::is_founder( $user_id ) ) {
			return __( 'Verified Founder', 'homeopathy-encyclopedia' );
		}
		if ( self::is_verified_doctor( $user_id ) ) {
			return __( 'Verified Doctor Contributor', 'homeopathy-encyclopedia' );
		}
		return __( 'Former Contributor', 'homeopathy-encyclopedia' );
	}

	/** Public profile URL without role-only fallbacks. */
	public static function profile_url( $user_id ) {
		$user_id = absint( $user_id );
		if ( function_exists( 'smc_page_url' ) ) {
			return add_query_arg( 'member', $user_id, smc_page_url( 'sabri_profile', '/member-profile/' ) );
		}
		return get_author_posts_url( $user_id );
	}

	/** Custom post type capability map. */
	public static function post_type_caps() {
		return array(
			'edit_post'              => 'edit_he_entry',
			'read_post'              => 'read_he_entry',
			'delete_post'            => 'delete_he_entry',
			'edit_posts'             => 'edit_he_entries',
			'edit_others_posts'      => 'edit_others_he_entries',
			'publish_posts'          => 'publish_he_entries',
			'read_private_posts'     => 'read_private_he_entries',
			'delete_posts'           => 'delete_he_entries',
			'delete_private_posts'   => 'delete_private_he_entries',
			'delete_published_posts' => 'delete_published_he_entries',
			'delete_others_posts'    => 'delete_others_he_entries',
			'edit_private_posts'     => 'edit_private_he_entries',
			'edit_published_posts'   => 'edit_published_he_entries',
			'create_posts'           => 'create_he_entries',
		);
	}

	/** All manager capabilities. */
	public static function manager_caps() {
		return array_values(
			array_unique(
				array_merge(
					array( self::CAP_MANAGE, self::CAP_REVIEW, self::CAP_FEEDBACK, self::CAP_AUDIT ),
					array_values( self::post_type_caps() )
				)
			)
		);
	}

	/** Grant File 06 capabilities to administrators only. */
	public static function install_caps() {
		if ( function_exists( 'wp_roles' ) ) {
			$roles = wp_roles();
			foreach ( array_keys( (array) $roles->roles ) as $role_name ) {
				$role = get_role( $role_name );
				if ( $role ) {
					$role->remove_cap( 'manage_homeopathy_encyclopedia' );
				}
			}
		}

		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			throw new RuntimeException( 'The WordPress administrator role is unavailable.' );
		}
		foreach ( self::manager_caps() as $capability ) {
			$admin->add_cap( $capability );
		}
	}

	/** Whether an actor may review this specific entry. */
	public static function can_review_entry( $entry_id, $actor_id = 0 ) {
		$actor_id = $actor_id ? absint( $actor_id ) : get_current_user_id();
		$entry = get_post( absint( $entry_id ) );
		if ( ! $actor_id || ! $entry instanceof WP_Post || HE_Content::TYPE !== $entry->post_type || ! user_can( $actor_id, self::CAP_REVIEW ) ) {
			return false;
		}
		return self::is_founder( $actor_id ) || $actor_id !== (int) $entry->post_author;
	}
}

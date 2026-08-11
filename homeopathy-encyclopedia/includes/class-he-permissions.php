<?php
defined( 'ABSPATH' ) || exit;
final class HE_Permissions {
	public static function founder_id() { return absint( get_option( 'spf_founder_user_id', 0 ) ); }
	public static function is_founder( $id = 0 ) { $id = $id ? absint( $id ) : get_current_user_id(); return $id && $id === self::founder_id(); }
	public static function is_doctor( $id = 0 ) { $id = $id ? absint( $id ) : get_current_user_id(); if ( ! $id ) { return false; } if ( class_exists( 'SPD_Helpers' ) && SPD_Helpers::is_doctor( $id ) ) { return 'verified' === SPD_Helpers::verification_status( $id ); } $user = get_userdata( $id ); return $user && in_array( 'sabri_doctor_verified', (array) $user->roles, true ); }
	public static function can_submit( $id = 0 ) { $id = $id ? absint( $id ) : get_current_user_id(); return $id && ( user_can( $id, 'manage_homeopathy_encyclopedia' ) || self::is_founder( $id ) || self::is_doctor( $id ) ); }
	public static function status( $id = 0 ) { $id = $id ? absint( $id ) : get_current_user_id(); return user_can( $id, 'manage_homeopathy_encyclopedia' ) || self::is_founder( $id ) ? 'publish' : 'pending'; }
	public static function label( $id ) { return self::is_founder( $id ) ? 'Verified Founder' : ( self::is_doctor( $id ) ? 'Verified Doctor Contributor' : 'Editorial Author' ); }
	public static function profile( $id ) { return class_exists( 'SPD_Helpers' ) ? SPD_Helpers::profile_url( $id ) : get_author_posts_url( absint( $id ) ); }
}


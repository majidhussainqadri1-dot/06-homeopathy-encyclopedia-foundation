<?php
/** File 00-aware least-privilege authorization. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Auth {
	const CAP_EDIT = 'he_edit_entries';
	const CAP_REVIEW = 'he_review_entries';
	const CAP_PUBLISH = 'he_publish_entries';
	const CAP_TAXONOMY = 'he_manage_taxonomy';
	const CAP_RESEARCH = 'he_manage_research';
	const CAP_DATASET = 'he_manage_datasets';
	const CAP_REPAIR = 'he_repair_system';

	public static function capabilities() {
		return array(
			self::CAP_EDIT,
			self::CAP_REVIEW,
			self::CAP_PUBLISH,
			self::CAP_TAXONOMY,
			self::CAP_RESEARCH,
			self::CAP_DATASET,
			self::CAP_REPAIR,
		);
	}

	public static function install_caps() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::capabilities() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::CAP_EDIT );
			$editor->add_cap( self::CAP_REVIEW );
		}
	}

	public static function is_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( function_exists( 'smc_is_founder' ) ) {
			return (bool) smc_is_founder( $user_id );
		}
		return (bool) user_can( $user_id, 'manage_options' );
	}

	public static function membership_allowed( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( function_exists( 'smc_user_status' ) ) {
			$status = sanitize_key( (string) smc_user_status( $user_id ) );
			if ( in_array( $status, array( 'suspended', 'revoked', 'rejected', 'deleted', 'blocked' ), true ) ) {
				return false;
			}
		}
		return true;
	}

	public static function verified_doctor( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! self::membership_allowed( $user_id ) ) {
			return false;
		}
		if ( function_exists( 'smc_get_profile' ) ) {
			$profile = smc_get_profile( $user_id );
			if ( is_array( $profile ) ) {
				return ! empty( $profile['doctor_verified'] ) || 'verified_doctor' === ( $profile['role'] ?? '' );
			}
		}
		return user_can( $user_id, self::CAP_EDIT );
	}

	public static function can( $capability, $object_id = 0, $purpose = '' ) {
		$user_id = get_current_user_id();
		if ( ! $user_id || ! self::membership_allowed( $user_id ) ) {
			return false;
		}
		if ( self::is_founder( $user_id ) ) {
			return true;
		}
		if ( ! user_can( $user_id, $capability ) ) {
			return false;
		}
		if ( $object_id ) {
			$post = get_post( absint( $object_id ) );
			if ( ! $post || ! in_array( $post->post_type, array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), true ) ) {
				return false;
			}
			if ( self::CAP_EDIT === $capability && (int) $post->post_author !== $user_id && ! user_can( $user_id, self::CAP_REVIEW ) ) {
				return false;
			}
		}
		return true;
	}

	public static function rest_permission( $capability, $object_id = 0, $purpose = '' ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'he_auth_required', __( 'Authentication is required.', 'homeopathy-encyclopedia' ), array( 'status' => 401 ) );
		}
		if ( ! self::can( $capability, $object_id, $purpose ) ) {
			return new WP_Error( 'he_forbidden', __( 'You are not authorized for this action.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function require_nonce( WP_REST_Request $request ) {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}
}

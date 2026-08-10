<?php
/** File 06 authorization adapter. File 00 is authoritative for identity/state. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Auth {
	const CAP_EDIT     = 'he_edit_entries';
	const CAP_REVIEW   = 'he_review_entries';
	const CAP_PUBLISH  = 'he_publish_entries';
	const CAP_TAXONOMY = 'he_manage_taxonomy';
	const CAP_RESEARCH = 'he_manage_research';
	const CAP_DATASET  = 'he_manage_datasets';
	const CAP_REPAIR   = 'he_repair_system';

	public static function capabilities() {
		return array( self::CAP_EDIT, self::CAP_REVIEW, self::CAP_PUBLISH, self::CAP_TAXONOMY, self::CAP_RESEARCH, self::CAP_DATASET, self::CAP_REPAIR );
	}

	public static function install_caps() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::capabilities() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::CAP_EDIT );
			$editor->add_cap( self::CAP_REVIEW );
		}
	}

	public static function remove_caps() {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::capabilities() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/** File 00 is a hard dependency for every protected File 06 action. */
	public static function provider_ready() {
		return function_exists( 'smc_membership_state' )
			&& function_exists( 'smc_is_founder' )
			&& class_exists( 'SMC_Contracts' )
			&& is_callable( array( 'SMC_Contracts', 'assertions' ) );
	}

	/** Return current, versioned File 00 assertions without legacy role fallbacks. */
	public static function claims( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! self::provider_ready() ) {
			return array();
		}
		try {
			$claims = SMC_Contracts::assertions( $user_id );
			return is_array( $claims ) ? $claims : array();
		} catch ( Throwable $error ) {
			return array();
		}
	}

	public static function is_founder( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return self::provider_ready() && $user_id > 0 && smc_is_founder( $user_id );
	}

	public static function membership_allowed( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$claims  = self::claims( $user_id );
		if ( ! $claims ) {
			return false;
		}
		if ( ! empty( $claims['suspended'] ) || empty( $claims['eligible'] ) ) {
			return false;
		}
		$status = sanitize_key( isset( $claims['status'] ) ? $claims['status'] : '' );
		return ! in_array( $status, array( 'suspended', 'rejected', 'expired', 'appeal_review', 'erasure_pending', 'invalid_application', 'effects_reconciliation' ), true );
	}

	public static function verified_doctor( $user_id = 0 ) {
		$claims = self::claims( $user_id );
		$types  = isset( $claims['approved_membership_types'] ) ? (array) $claims['approved_membership_types'] : array();
		return self::membership_allowed( $user_id )
			&& in_array( 'doctor', array_map( 'sanitize_key', $types ), true )
			&& ! empty( $claims['professional_verified'] );
	}

	public static function approved_researcher( $user_id = 0 ) {
		$claims = self::claims( $user_id );
		$types  = isset( $claims['approved_membership_types'] ) ? (array) $claims['approved_membership_types'] : array();
		return self::membership_allowed( $user_id ) && in_array( 'researcher', array_map( 'sanitize_key', $types ), true );
	}

	private static function file00_publish_allows( $claims ) {
		if ( ! is_array( $claims ) || empty( $claims['eligible'] ) ) {
			return false;
		}
		if ( isset( $claims['publishing'] ) && is_array( $claims['publishing'] ) ) {
			return ! empty( $claims['publishing']['can_direct_publish'] );
		}
		return ! empty( $claims['can_direct_publish'] );
	}

	public static function can( $capability, $object_id = 0, $purpose = '', $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id || ! self::membership_allowed( $user_id ) ) {
			return false;
		}

		$claims = self::claims( $user_id );
		if ( self::is_founder( $user_id ) ) {
			return self::object_scope_allows( $object_id );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$allowed = user_can( $user, $capability );
		if ( self::CAP_RESEARCH === $capability && self::approved_researcher( $user_id ) ) {
			$allowed = true;
		}
		if ( self::CAP_PUBLISH === $capability ) {
			$allowed = $allowed && self::file00_publish_allows( $claims );
		}
		if ( ! $allowed ) {
			return false;
		}

		if ( ! $object_id ) {
			return true;
		}
		$post = get_post( absint( $object_id ) );
		if ( ! $post || ! in_array( $post->post_type, array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), true ) ) {
			return false;
		}

		if ( self::CAP_EDIT === $capability && (int) $post->post_author !== $user_id && ! user_can( $user, self::CAP_REVIEW ) ) {
			return false;
		}
		if ( self::CAP_RESEARCH === $capability && HE_V2_Domain::RESEARCH_TYPE === $post->post_type && (int) $post->post_author !== $user_id && ! user_can( $user, self::CAP_REVIEW ) ) {
			return false;
		}
		return true;
	}

	private static function object_scope_allows( $object_id ) {
		if ( ! $object_id ) {
			return true;
		}
		$post = get_post( absint( $object_id ) );
		return $post && in_array( $post->post_type, array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), true );
	}

	public static function require_nonce( $request ) {
		$nonce = $request instanceof WP_REST_Request ? $request->get_header( 'X-WP-Nonce' ) : '';
		return is_user_logged_in() && $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	public static function rest_permission( $capability, $object_id = 0, $purpose = '' ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'he_auth_required', __( 'Authentication is required.', 'homeopathy-encyclopedia' ), array( 'status' => 401 ) );
		}
		if ( ! self::provider_ready() ) {
			return new WP_Error( 'he_identity_provider_unavailable', __( 'The platform identity service is temporarily unavailable. Protected encyclopedia actions are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( ! self::can( $capability, $object_id, $purpose ) ) {
			if ( $object_id ) {
				return new WP_Error( 'he_not_found', __( 'The requested record is not available.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			return new WP_Error( 'he_forbidden', __( 'You are not allowed to perform this action.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		return true;
	}
}

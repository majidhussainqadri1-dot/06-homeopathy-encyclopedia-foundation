<?php
/** Runtime dependency contract. */

defined( 'ABSPATH' ) || exit;

final class HE_Dependencies {
	/** Return missing mandatory contracts. */
	public static function missing() {
		$missing = array();

		if ( ! defined( 'SMC_VERSION' ) || ! function_exists( 'smc_get_profile' ) || ! function_exists( 'smc_user_status' ) || ! function_exists( 'smc_is_founder' ) || ! function_exists( 'smc_page_url' ) || ! class_exists( 'SMC_Security' ) ) {
			$missing['file00'] = __( 'File 00 — Sabri Membership Core 1.0.1 or later', 'homeopathy-encyclopedia' );
		}
		if ( ! defined( 'SPF_VERSION' ) ) {
			$missing['file01'] = __( 'File 01 — Sabri Platform Foundation', 'homeopathy-encyclopedia' );
		}
		if ( ! defined( 'SLC_VERSION' ) || ! class_exists( 'SLC_Content' ) || ! class_exists( 'SLC_Database' ) ) {
			$missing['file05'] = __( 'File 05 — Learn Sabri Classical Homeopathy 1.0.0 or later', 'homeopathy-encyclopedia' );
		}
		if ( ! defined( 'SABRI_SHELL_VERSION' ) ) {
			$missing['file20'] = __( 'File 20 — Sabri Unified Application Shell 1.0.0 or later', 'homeopathy-encyclopedia' );
		}

		return $missing;
	}

	/** Whether all mandatory contracts are available. */
	public static function ready() {
		return array() === self::missing();
	}

	/** Validate activation preconditions and the Foundation-owned Encyclopedia page. */
	public static function activation_preflight() {
		$missing = self::missing();
		if ( $missing ) {
			return new WP_Error(
				'he_missing_dependencies',
				sprintf(
					/* translators: %s: comma-separated dependency names. */
					__( 'File 06 cannot activate because these mandatory dependencies are unavailable: %s.', 'homeopathy-encyclopedia' ),
					implode( ', ', $missing )
				)
			);
		}

		$pages = (array) get_option( 'spf_page_map', array() );
		$page_id = isset( $pages['encyclopedia'] ) ? absint( $pages['encyclopedia'] ) : 0;
		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status || ! has_shortcode( $page->post_content, 'sabri_platform_module' ) ) {
			return new WP_Error(
				'he_missing_encyclopedia_page',
				__( 'The File 01 Foundation-owned Encyclopedia page is missing, invalid, or no longer contains the platform module shortcode.', 'homeopathy-encyclopedia' )
			);
		}

		if ( ! HE_Permissions::founder_id() ) {
			return new WP_Error(
				'he_missing_founder',
				__( 'The File 00 official Founder account is unavailable. Repair membership governance before activating File 06.', 'homeopathy-encyclopedia' )
			);
		}

		return true;
	}

	/** Display a safe administrator-visible dependency failure. */
	public static function register_failure_notice() {
		add_action(
			'admin_notices',
			static function() {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				$missing = HE_Dependencies::missing();
				if ( ! $missing ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
					esc_html__( 'Homeopathy Encyclopedia Foundation is safely paused.', 'homeopathy-encyclopedia' ),
					esc_html( sprintf( __( 'Activate or repair: %s.', 'homeopathy-encyclopedia' ), implode( ', ', $missing ) ) )
				);
			}
		);
	}

	/** Display a migration/runtime failure without exposing implementation detail. */
	public static function register_runtime_failure_notice() {
		add_action(
			'admin_notices',
			static function() {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				$failure = (array) get_option( 'he_runtime_failure', array() );
				if ( empty( $failure['error'] ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'File 06 is safely paused because its migration did not complete.', 'homeopathy-encyclopedia' ) . '</strong> ' . esc_html__( 'Review the recorded failure before retrying.', 'homeopathy-encyclopedia' ) . '</p></div>';
			}
		);
	}

	/** Record a File 06 event in File 00 when supported. */
	public static function audit( $action, array $context = array() ) {
		if ( class_exists( 'SMC_Security' ) && is_callable( array( 'SMC_Security', 'audit' ) ) ) {
			$subject = isset( $context['user_id'] ) ? absint( $context['user_id'] ) : ( isset( $context['author_id'] ) ? absint( $context['author_id'] ) : 0 );
			$object = isset( $context['entry_id'] ) ? absint( $context['entry_id'] ) : 0;
			SMC_Security::audit( 'he_' . sanitize_key( $action ), $subject, $object ? 'he_entry' : 'he_system', $object, $context );
		}
	}
}

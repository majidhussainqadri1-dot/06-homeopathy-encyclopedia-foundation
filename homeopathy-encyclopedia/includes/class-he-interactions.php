<?php
/** Cache-safe public interactions and atomic metrics. */

defined( 'ABSPATH' ) || exit;

final class HE_Interactions {
	public function hooks() {
		add_action( 'wp_ajax_he_interaction', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_he_interaction', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_he_bookmark_states', array( $this, 'states' ) );
		add_action( 'wp_ajax_nopriv_he_bookmark_states', array( $this, 'states' ) );
		add_action( 'template_redirect', array( $this, 'view' ) );
	}

	public function ajax() {
		check_ajax_referer( 'he_interaction', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to use this action.', 'homeopathy-encyclopedia' ) ), 401 );
		}
		$user_id = get_current_user_id();
		$entry_id = isset( $_POST['entryId'] ) ? absint( $_POST['entryId'] ) : 0;
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( ! HE_Content::publicly_available( $entry_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'homeopathy-encyclopedia' ) ), 404 );
		}
		if ( ! HE_Database::allow( 'interaction:' . $user_id, 30, MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Please wait before trying again.', 'homeopathy-encyclopedia' ) ), 429 );
		}

		if ( 'bookmark' === $kind ) {
			$desired = ! empty( $_POST['desired'] ) && '1' === (string) wp_unslash( $_POST['desired'] );
			if ( ! HE_Database::set_bookmark( $user_id, $entry_id, $desired ) ) {
				wp_send_json_error( array( 'message' => __( 'The bookmark state could not be saved.', 'homeopathy-encyclopedia' ) ), 500 );
			}
			wp_send_json_success(
				array(
					'active' => $desired,
					'label' => $desired ? __( 'Bookmarked', 'homeopathy-encyclopedia' ) : __( 'Bookmark', 'homeopathy-encyclopedia' ),
				)
			);
		}

		if ( in_array( $kind, array( 'correction', 'report' ), true ) ) {
			$this->feedback( $user_id, $entry_id, $kind );
			wp_send_json_success( array( 'message' => __( 'Thank you. The editorial team will review your submission.', 'homeopathy-encyclopedia' ) ) );
		}
		wp_send_json_error( array( 'message' => __( 'Unknown action.', 'homeopathy-encyclopedia' ) ), 400 );
	}

	/** Return current-user bookmark states without embedding them in cached HTML. */
	public function states() {
		check_ajax_referer( 'he_interaction', 'nonce' );
		$ids = isset( $_POST['entryIds'] ) ? array_slice( array_unique( array_map( 'absint', (array) wp_unslash( $_POST['entryIds'] ) ) ), 0, 100 ) : array();
		if ( ! is_user_logged_in() ) {
			wp_send_json_success( array( 'loggedIn' => false, 'states' => array() ) );
		}
		$states = array();
		$user_id = get_current_user_id();
		foreach ( $ids as $entry_id ) {
			if ( HE_Content::publicly_available( $entry_id ) ) {
				$states[ $entry_id ] = HE_Database::bookmarked( $user_id, $entry_id );
			}
		}
		wp_send_json_success( array( 'loggedIn' => true, 'states' => $states ) );
	}

	private function feedback( $user_id, $entry_id, $kind ) {
		$reasons = 'correction' === $kind
			? array( 'typographical', 'broken-reference', 'incorrect-relationship', 'outdated-information', 'medical-safety', 'other' )
			: array( 'medical-safety', 'spam', 'harassment', 'copyright', 'inappropriate', 'other' );
		$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		if ( ! in_array( $reason, $reasons, true ) || ! trim( $details ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a valid reason and provide details.', 'homeopathy-encyclopedia' ) ), 400 );
		}
		$details = function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 1500, 'UTF-8' ) : substr( $details, 0, 1500 );
		if ( ! HE_Database::allow( 'feedback:' . $user_id . ':' . $entry_id, 3, HOUR_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Please wait before submitting another report for this entry.', 'homeopathy-encyclopedia' ) ), 429 );
		}
		$feedback_id = HE_Database::add_feedback( $user_id, $entry_id, $kind, $reason, $details );
		if ( ! $feedback_id ) {
			wp_send_json_error( array( 'message' => __( 'The submission could not be saved.', 'homeopathy-encyclopedia' ) ), 500 );
		}
		HE_Database::audit( $entry_id, $kind . '_submitted', '', '', $reason, $user_id );
	}

	public function view() {
		if ( ! is_singular( HE_Content::TYPE ) ) {
			return;
		}
		$entry_id = get_queried_object_id();
		if ( ! HE_Content::publicly_available( $entry_id ) ) {
			return;
		}
		$cookie = 'he_viewed_' . $entry_id;
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return;
		}
		HE_Database::increment_view( $entry_id );
		if ( ! headers_sent() ) {
			setcookie(
				$cookie,
				'1',
				array(
					'expires' => time() + 12 * HOUR_IN_SECONDS,
					'path' => COOKIEPATH ? COOKIEPATH : '/',
					'domain' => (string) COOKIE_DOMAIN,
					'secure' => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	/** Render identical cache-safe public controls for guests and members. */
	public static function actions( $entry_id ) {
		$entry_id = absint( $entry_id );
		ob_start();
		?>
		<div class="he-actions" data-he-actions>
			<button type="button" data-he-action="bookmark" aria-pressed="false"><?php esc_html_e( 'Bookmark', 'homeopathy-encyclopedia' ); ?></button>
			<details><summary><?php esc_html_e( 'Suggest Correction', 'homeopathy-encyclopedia' ); ?></summary><?php echo self::feedback_form( 'correction' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></details>
			<details><summary><?php esc_html_e( 'Report', 'homeopathy-encyclopedia' ); ?></summary><?php echo self::feedback_form( 'report' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></details>
			<span><?php echo esc_html( sprintf( _n( '%s view', '%s views', HE_Database::views( $entry_id ), 'homeopathy-encyclopedia' ), number_format_i18n( HE_Database::views( $entry_id ) ) ) ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function feedback_form( $kind ) {
		$items = 'correction' === $kind
			? array(
				'typographical' => __( 'Typographical correction', 'homeopathy-encyclopedia' ),
				'broken-reference' => __( 'Broken reference', 'homeopathy-encyclopedia' ),
				'incorrect-relationship' => __( 'Incorrect relationship', 'homeopathy-encyclopedia' ),
				'outdated-information' => __( 'Outdated information', 'homeopathy-encyclopedia' ),
				'medical-safety' => __( 'Medical safety concern', 'homeopathy-encyclopedia' ),
				'other' => __( 'Other', 'homeopathy-encyclopedia' ),
			)
			: array(
				'medical-safety' => __( 'Medical safety concern', 'homeopathy-encyclopedia' ),
				'spam' => __( 'Spam', 'homeopathy-encyclopedia' ),
				'harassment' => __( 'Harassment', 'homeopathy-encyclopedia' ),
				'copyright' => __( 'Copyright concern', 'homeopathy-encyclopedia' ),
				'inappropriate' => __( 'Inappropriate content', 'homeopathy-encyclopedia' ),
				'other' => __( 'Other', 'homeopathy-encyclopedia' ),
			);
		$out = '<form data-he-feedback data-kind="' . esc_attr( $kind ) . '"><label><span class="screen-reader-text">' . esc_html__( 'Reason', 'homeopathy-encyclopedia' ) . '</span><select name="reason" required><option value="">' . esc_html__( 'Choose reason', 'homeopathy-encyclopedia' ) . '</option>';
		foreach ( $items as $key => $label ) {
			$out .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		return $out . '</select></label><label><span class="screen-reader-text">' . esc_html__( 'Details', 'homeopathy-encyclopedia' ) . '</span><textarea name="details" maxlength="1500" required placeholder="' . esc_attr__( 'Describe the issue clearly', 'homeopathy-encyclopedia' ) . '"></textarea></label><button type="submit">' . esc_html__( 'Send', 'homeopathy-encyclopedia' ) . '</button></form>';
	}
}

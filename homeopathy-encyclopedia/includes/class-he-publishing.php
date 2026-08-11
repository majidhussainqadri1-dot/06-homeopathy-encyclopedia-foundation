<?php
/** Governed front-end encyclopedia publishing. */

defined( 'ABSPATH' ) || exit;

final class HE_Publishing {
	const LANGUAGE_POLICY_VERSION = 'en-US-editorial-1.0';

	public function hooks() {
		add_shortcode( 'he_submit_entry', array( $this, 'form' ) );
		add_action( 'admin_post_he_submit_entry', array( $this, 'submit' ) );
	}

	public function form() {
		if ( ! is_user_logged_in() ) {
			return '<div class="he-notice"><p>' . esc_html__( 'An account is required to submit an entry.', 'homeopathy-encyclopedia' ) . '</p><a class="he-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log In', 'homeopathy-encyclopedia' ) . '</a></div>';
		}
		if ( ! HE_Permissions::can_submit() ) {
			return '<div class="he-notice"><strong>' . esc_html__( 'Entry publishing is restricted.', 'homeopathy-encyclopedia' ) . '</strong><p>' . esc_html__( 'Only the Founder, authorized administrators, and currently verified doctors may submit encyclopedia entries.', 'homeopathy-encyclopedia' ) . '</p></div>';
		}

		$entries = get_posts( array( 'post_type' => HE_Content::TYPE, 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
		$books = post_type_exists( 'slc_book' ) ? get_posts( array( 'post_type' => 'slc_book', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) : array();
		$lessons = post_type_exists( 'slc_lesson' ) ? get_posts( array( 'post_type' => 'slc_lesson', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) : array();
		ob_start();
		?>
		<section class="he-module he-shell">
			<header class="he-page-head"><span><?php esc_html_e( 'Encyclopedia Editorial System', 'homeopathy-encyclopedia' ); ?></span><h1><?php esc_html_e( 'Submit Encyclopedia Entry', 'homeopathy-encyclopedia' ); ?></h1><p><?php esc_html_e( 'All fields are validated on the server. American English is confirmed by the author and independently checked during moderation.', 'homeopathy-encyclopedia' ); ?></p></header>
			<form class="he-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
				<input type="hidden" name="action" value="he_submit_entry">
				<?php wp_nonce_field( 'he_submit_entry', 'he_nonce' ); ?>
				<label><?php esc_html_e( 'Entry title', 'homeopathy-encyclopedia' ); ?><input name="title" maxlength="180" required></label>
				<label><?php esc_html_e( 'Knowledge type', 'homeopathy-encyclopedia' ); ?><select name="knowledge_type" required><option value=""><?php esc_html_e( 'Select type', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( HE_Content::types() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Body system', 'homeopathy-encyclopedia' ); ?><select name="body_system" required><option value=""><?php esc_html_e( 'Select body system', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( HE_Content::systems() as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
				<label class="he-full"><?php esc_html_e( 'Short definition or summary', 'homeopathy-encyclopedia' ); ?><textarea name="excerpt" rows="3" maxlength="500" required></textarea></label>
				<label class="he-full"><?php esc_html_e( 'Complete educational entry', 'homeopathy-encyclopedia' ); ?><textarea name="content" rows="15" required></textarea></label>
				<?php foreach ( HE_Content::fields() as $key => $label ) : ?>
					<label class="<?php echo in_array( $key, array( 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'references' ), true ) ? 'he-full' : ''; ?>"><?php echo esc_html( $label ); ?><?php if ( in_array( $key, array( 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'references' ), true ) ) : ?><textarea name="<?php echo esc_attr( $key ); ?>" rows="4"<?php echo 'references' === $key ? ' required' : ''; ?>></textarea><?php else : ?><input name="<?php echo esc_attr( $key ); ?>" maxlength="300"><?php endif; ?></label>
				<?php endforeach; ?>
				<label><?php esc_html_e( 'Featured image', 'homeopathy-encyclopedia' ); ?><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small><?php esc_html_e( 'JPG, PNG, or WebP; maximum 5 MB, 6,000 px per side, and 24 megapixels.', 'homeopathy-encyclopedia' ); ?></small></label>
				<label><?php esc_html_e( 'Related entries', 'homeopathy-encyclopedia' ); ?><select name="related_ids[]" multiple size="6"><?php foreach ( $entries as $entry ) : ?><option value="<?php echo absint( $entry->ID ); ?>"><?php echo esc_html( $entry->post_title ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Select up to five public entries.', 'homeopathy-encyclopedia' ); ?></small></label>
				<?php if ( $books ) : ?><label><?php esc_html_e( 'Related learning book', 'homeopathy-encyclopedia' ); ?><select name="book_id"><option value="0"><?php esc_html_e( 'None', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( $books as $book ) : if ( HE_Content::learning_item_public( $book->ID, 'slc_book' ) ) : ?><option value="<?php echo absint( $book->ID ); ?>"><?php echo esc_html( $book->post_title ); ?></option><?php endif; endforeach; ?></select></label><?php endif; ?>
				<?php if ( $lessons ) : ?><label><?php esc_html_e( 'Related lesson', 'homeopathy-encyclopedia' ); ?><select name="lesson_id"><option value="0"><?php esc_html_e( 'None', 'homeopathy-encyclopedia' ); ?></option><?php foreach ( $lessons as $lesson ) : if ( HE_Content::learning_item_public( $lesson->ID, 'slc_lesson' ) ) : ?><option value="<?php echo absint( $lesson->ID ); ?>"><?php echo esc_html( $lesson->post_title ); ?></option><?php endif; endforeach; ?></select></label><?php endif; ?>
				<label class="he-check he-full"><input type="checkbox" name="english_confirm" value="1" required> <?php esc_html_e( 'I confirm that every submitted text field is written in American English and that an editor may correct spelling and terminology.', 'homeopathy-encyclopedia' ); ?></label>
				<label class="he-check he-full"><input type="checkbox" name="medical_confirm" value="1" required> <?php esc_html_e( 'I confirm that the entry is educational, does not promise a cure, does not issue an individualized prescription, and does not delay urgent assessment.', 'homeopathy-encyclopedia' ); ?></label>
				<button class="he-button" type="submit"><?php echo esc_html( 'publish' === HE_Permissions::initial_status() ? __( 'Publish Entry', 'homeopathy-encyclopedia' ) : __( 'Submit for Review', 'homeopathy-encyclopedia' ) ); ?></button>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}

	public function submit() {
		if ( ! is_user_logged_in() || ! HE_Permissions::can_submit() ) {
			wp_die( esc_html__( 'You are not allowed to submit encyclopedia entries.', 'homeopathy-encyclopedia' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'he_submit_entry', 'he_nonce' );
		$user_id = get_current_user_id();
		if ( ! HE_Database::allow( 'entry-submit:' . $user_id, 5, DAY_IN_SECONDS ) ) {
			$this->fail( __( 'The daily submission limit has been reached. Please try again later.', 'homeopathy-encyclopedia' ), 429 );
		}

		$title = $this->limit( isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '', 180 );
		$excerpt = $this->limit( isset( $_POST['excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ) ) : '', 500 );
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$type = isset( $_POST['knowledge_type'] ) ? sanitize_title( wp_unslash( $_POST['knowledge_type'] ) ) : '';
		$system = isset( $_POST['body_system'] ) ? sanitize_title( wp_unslash( $_POST['body_system'] ) ) : '';
		$values = array();
		foreach ( HE_Content::fields() as $key => $label ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$values[ $key ] = in_array( $key, array( 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'references' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}

		if ( ! trim( $title ) || ! trim( $excerpt ) || ! trim( wp_strip_all_tags( $content ) ) || ! HE_Content::allowed( $type, HE_Content::TAX ) || ! HE_Content::allowed( $system, HE_Content::SYSTEM ) || ! trim( $values['references'] ) || empty( $_POST['english_confirm'] ) || empty( $_POST['medical_confirm'] ) ) {
			$this->fail( __( 'Complete the title, summary, content, approved classifications, references, and both confirmations.', 'homeopathy-encyclopedia' ) );
		}
		if ( in_array( $type, array( 'health-condition', 'pathology' ), true ) && ! trim( $values['red_flags'] ) ) {
			$this->fail( __( 'Health Condition and Pathology entries require medical red flags.', 'homeopathy-encyclopedia' ) );
		}
		if ( 'remedy' === $type && ! trim( $values['safety'] ) ) {
			$this->fail( __( 'Remedy entries require safety and limitations.', 'homeopathy-encyclopedia' ) );
		}

		$all_text = array_merge( array( $title, $excerpt, wp_strip_all_tags( $content ) ), array_values( $values ) );
		if ( $this->unsupported_script( implode( ' ', $all_text ) ) ) {
			$this->fail( __( 'This release accepts American English editorial text only. Unsupported writing scripts were detected.', 'homeopathy-encyclopedia' ) );
		}

		$related = isset( $_POST['related_ids'] ) ? array_slice( array_unique( array_map( 'absint', (array) wp_unslash( $_POST['related_ids'] ) ) ), 0, 5 ) : array();
		$related = array_values( array_filter( $related, array( $this, 'public_related_entry' ) ) );
		$book_id = isset( $_POST['book_id'] ) ? absint( $_POST['book_id'] ) : 0;
		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
		if ( $book_id && ! HE_Content::learning_item_public( $book_id, 'slc_book' ) ) {
			$this->fail( __( 'The selected learning book is not publicly available.', 'homeopathy-encyclopedia' ) );
		}
		if ( $lesson_id && ! HE_Content::learning_item_public( $lesson_id, 'slc_lesson' ) ) {
			$this->fail( __( 'The selected learning lesson is not publicly available.', 'homeopathy-encyclopedia' ) );
		}

		$image_id = 0;
		$entry_id = 0;
		try {
			$image = $this->upload_unattached_image();
			if ( is_wp_error( $image ) ) {
				throw new RuntimeException( $image->get_error_message() );
			}
			$image_id = absint( $image );
			$status = HE_Permissions::initial_status( $user_id );
			$entry_id = wp_insert_post(
				array(
					'post_type' => HE_Content::TYPE,
					'post_status' => $status,
					'post_author' => $user_id,
					'post_title' => $title,
					'post_excerpt' => $excerpt,
					'post_content' => $content,
					'comment_status' => 'open',
					'ping_status' => 'closed',
				),
				true
			);
			if ( is_wp_error( $entry_id ) ) {
				throw new RuntimeException( $entry_id->get_error_message() );
			}
			if ( ! HE_Content::assign( $entry_id, $type, HE_Content::TAX ) || ! HE_Content::assign( $entry_id, $system, HE_Content::SYSTEM ) ) {
				throw new RuntimeException( __( 'The approved classifications could not be assigned.', 'homeopathy-encyclopedia' ) );
			}
			foreach ( $values as $key => $value ) {
				update_post_meta( $entry_id, '_he_' . $key, $value );
			}
			update_post_meta( $entry_id, '_he_related_ids', $related );
			update_post_meta( $entry_id, '_he_book_id', $book_id );
			update_post_meta( $entry_id, '_he_lesson_id', $lesson_id );
			update_post_meta( $entry_id, '_he_language', 'en-US' );
			update_post_meta( $entry_id, '_he_language_policy_version', self::LANGUAGE_POLICY_VERSION );
			update_post_meta( $entry_id, '_he_language_declared_by', $user_id );
			update_post_meta( $entry_id, '_he_language_declared_at', current_time( 'mysql', true ) );
			update_post_meta( $entry_id, '_he_language_reviewed', 'publish' === $status ? 1 : 0 );
			update_post_meta( $entry_id, '_he_medical_notice_version', 'file-06-1.0' );
			update_post_meta( $entry_id, '_he_workflow_state', 'publish' === $status ? 'published' : 'submitted' );
			update_post_meta( $entry_id, '_he_row_version', 1 );
			if ( $image_id ) {
				wp_update_post( array( 'ID' => $image_id, 'post_parent' => $entry_id ) );
				if ( ! set_post_thumbnail( $entry_id, $image_id ) ) {
					throw new RuntimeException( __( 'The validated image could not be attached to the entry.', 'homeopathy-encyclopedia' ) );
				}
			}
			$workflow = 'publish' === $status ? 'published' : 'submitted';
			HE_Database::audit( $entry_id, $workflow, '', $workflow, '', $user_id );
			HE_Database::reindex_entry( $entry_id );
		} catch ( Throwable $error ) {
			if ( $entry_id ) {
				wp_delete_post( $entry_id, true );
			}
			if ( $image_id ) {
				wp_delete_attachment( $image_id, true );
			}
			$this->fail( $error->getMessage() );
		}

		if ( 'publish' === get_post_status( $entry_id ) ) {
			wp_safe_redirect( get_permalink( $entry_id ) );
		} else {
			$pages = (array) get_option( 'he_page_map', array() );
			$target = ! empty( $pages['submit'] ) ? get_permalink( absint( $pages['submit'] ) ) : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'submitted', '1', $target ) );
		}
		exit;
	}

	public function public_related_entry( $entry_id ) {
		return HE_Content::publicly_available( absint( $entry_id ) );
	}

	private function unsupported_script( $text ) {
		return (bool) preg_match( '/[\x{0600}-\x{08FF}\x{0900}-\x{0D7F}\x{0E00}-\x{0FFF}\x{0400}-\x{052F}\x{3040}-\x{30FF}\x{3400}-\x{9FFF}\x{AC00}-\x{D7AF}]/u', $text );
	}

	/** Validate and upload an unattached image, returning attachment ID or WP_Error. */
	private function upload_unattached_image() {
		if ( empty( $_FILES['image']['name'] ) ) {
			return 0;
		}
		$file = $_FILES['image'];
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'he_image_upload', __( 'The image upload did not complete successfully.', 'homeopathy-encyclopedia' ) );
		}
		if ( (int) $file['size'] > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'he_image_size', __( 'The image must be 5 MB or smaller.', 'homeopathy-encyclopedia' ) );
		}
		$allowed = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
		if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed, true ) ) {
			return new WP_Error( 'he_image_type', __( 'Only verified JPG, PNG, and WebP images are allowed.', 'homeopathy-encyclopedia' ) );
		}
		$dimensions = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $dimensions ) || empty( $dimensions[0] ) || empty( $dimensions[1] ) ) {
			return new WP_Error( 'he_image_dimensions', __( 'The image dimensions could not be verified.', 'homeopathy-encyclopedia' ) );
		}
		$width = absint( $dimensions[0] );
		$height = absint( $dimensions[1] );
		if ( $width > 6000 || $height > 6000 || ( $width * $height ) > 24000000 ) {
			return new WP_Error( 'he_image_pixels', __( 'The image exceeds the permitted dimensions or pixel count.', 'homeopathy-encyclopedia' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$image_id = media_handle_upload( 'image', 0, array(), array( 'test_form' => false, 'mimes' => $allowed ) );
		return is_wp_error( $image_id ) ? $image_id : absint( $image_id );
	}

	private function limit( $text, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length, 'UTF-8' ) : substr( $text, 0, $length );
	}

	private function fail( $message, $status = 400 ) {
		wp_die( esc_html( $message ), esc_html__( 'Entry not accepted', 'homeopathy-encyclopedia' ), array( 'response' => absint( $status ), 'back_link' => true ) );
	}
}

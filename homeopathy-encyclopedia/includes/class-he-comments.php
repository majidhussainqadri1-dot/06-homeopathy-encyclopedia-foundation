<?php
/** Entry-scoped comments without changing site-wide comment settings. */

defined( 'ABSPATH' ) || exit;

final class HE_Comments {
	public function hooks() {
		add_filter( 'preprocess_comment', array( $this, 'prepare' ) );
		add_filter( 'pre_comment_approved', array( $this, 'approval' ), 10, 2 );
		add_filter( 'comments_open', array( $this, 'comments_open' ), 10, 2 );
	}

	public function comments_open( $open, $post_id ) {
		if ( HE_Content::TYPE === get_post_type( $post_id ) ) {
			return HE_Content::publicly_available( $post_id );
		}
		return $open;
	}

	public function prepare( $data ) {
		$post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false;
		if ( ! $post || HE_Content::TYPE !== $post->post_type ) {
			return $data;
		}
		if ( ! HE_Content::publicly_available( $post->ID ) ) {
			wp_die( esc_html__( 'Comments are unavailable for this encyclopedia entry.', 'homeopathy-encyclopedia' ), '', array( 'response' => 403 ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in before commenting.', 'homeopathy-encyclopedia' ), '', array( 'response' => 403, 'back_link' => true ) );
		}
		if ( ! HE_Database::allow( 'comment:' . get_current_user_id(), 5, 10 * MINUTE_IN_SECONDS ) ) {
			wp_die( esc_html__( 'Please wait before commenting again.', 'homeopathy-encyclopedia' ), '', array( 'response' => 429, 'back_link' => true ) );
		}
		$data['comment_author_IP'] = '';
		$data['comment_author_url'] = '';
		return $data;
	}

	public function approval( $approved, $data ) {
		$post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false;
		if ( ! $post || HE_Content::TYPE !== $post->post_type ) {
			return $approved;
		}
		$user_id = ! empty( $data['user_ID'] ) ? absint( $data['user_ID'] ) : get_current_user_id();
		return HE_Permissions::is_founder( $user_id ) || user_can( $user_id, HE_Permissions::CAP_MANAGE ) ? 1 : 0;
	}
}

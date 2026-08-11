<?php
defined( 'ABSPATH' ) || exit;
final class HE_Comments {
	public function hooks() { add_filter( 'option_comment_registration', '__return_true' ); add_filter( 'preprocess_comment', array( $this, 'prepare' ) ); add_filter( 'pre_comment_approved', array( $this, 'approval' ), 10, 2 ); }
	public function prepare( $data ) { $post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false; if ( ! $post || HE_Content::TYPE !== $post->post_type ) { return $data; } if ( ! is_user_logged_in() ) { wp_die( esc_html__( 'Please log in before commenting.', 'homeopathy-encyclopedia' ), '', array( 'response' => 403, 'back_link' => true ) ); } $key = 'he_comments_' . get_current_user_id(); $count = absint( get_transient( $key ) ); if ( $count >= 5 ) { wp_die( esc_html__( 'Please wait before commenting again.', 'homeopathy-encyclopedia' ), '', array( 'response' => 429, 'back_link' => true ) ); } set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS ); $data['comment_author_IP'] = ''; $data['comment_author_url'] = ''; return $data; }
	public function approval( $approved, $data ) { $post = ! empty( $data['comment_post_ID'] ) ? get_post( absint( $data['comment_post_ID'] ) ) : false; if ( ! $post || HE_Content::TYPE !== $post->post_type ) { return $approved; } $user = ! empty( $data['user_ID'] ) ? absint( $data['user_ID'] ) : get_current_user_id(); return HE_Permissions::is_founder( $user ) || user_can( $user, 'manage_homeopathy_encyclopedia' ) ? 1 : 0; }
}


<?php
/** Published/corrected/retracted research metadata is immutable outside integrity workflows. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Research_Immutability {
	public static function hooks() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard' ), 2, 2 );
	}

	public static function guard( $data, $postarr ) {
		if ( ! is_admin() || HE_V2_Domain::RESEARCH_TYPE !== ( $data['post_type'] ?? '' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return $data; }
		$post_id = absint( $postarr['ID'] ?? ( $_POST['post_ID'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! $post_id || wp_is_post_revision( $post_id ) ) { return $data; }
		global $wpdb;
		$status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post_id ) );
		if ( in_array( $status, array( 'published','corrected','retracted' ), true ) ) {
			wp_die(
				esc_html__( 'Published, corrected and retracted research records are immutable in the normal editor. Use the governed correction/retraction workflow so review, reason and audit history remain intact.', 'homeopathy-encyclopedia' ),
				esc_html__( 'File 06 integrity workflow required', 'homeopathy-encyclopedia' ),
				array( 'response' => 409 )
			);
		}
		return $data;
	}
}

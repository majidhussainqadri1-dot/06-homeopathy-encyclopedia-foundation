<?php
/** Main runtime coordinator. */

defined( 'ABSPATH' ) || exit;

final class HE_Plugin {
	public function run() {
		add_action( 'init', array( 'HE_Content', 'register' ), 5 );
		add_action( 'init', array( $this, 'after_register' ), 20 );
		( new HE_Publishing() )->hooks();
		( new HE_Catalog() )->hooks();
		( new HE_Interactions() )->hooks();
		( new HE_Comments() )->hooks();
		( new HE_Admin() )->hooks();
		( new HE_Privacy() )->hooks();
		( new HE_SEO() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'he_daily_maintenance', array( $this, 'maintenance' ) );
		add_action( 'save_post_' . HE_Content::TYPE, array( $this, 'reindex_saved_entry' ), 30, 3 );
		add_action( 'set_object_terms', array( $this, 'reindex_terms' ), 30, 6 );
		add_action( 'before_delete_post', array( $this, 'remove_index' ) );
		add_filter( 'sabri_shell_create_url', array( $this, 'create_url' ) );
	}

	public function after_register() {
		HE_Content::seed_terms();
		HE_Database::migrate_legacy_systems();
	}

	public function assets() {
		global $post;
		$spf = (array) get_option( 'spf_page_map', array() );
		$pages = (array) get_option( 'he_page_map', array() );
		$ids = array_merge( array_filter( array( isset( $spf['encyclopedia'] ) ? $spf['encyclopedia'] : 0 ) ), $pages );
		$needed = is_singular( HE_Content::TYPE ) || is_post_type_archive( HE_Content::TYPE ) || ( $post instanceof WP_Post && ( in_array( $post->ID, array_map( 'absint', $ids ), true ) || has_shortcode( $post->post_content, 'he_encyclopedia_home' ) || has_shortcode( $post->post_content, 'sabri_encyclopedia' ) || has_shortcode( $post->post_content, 'he_submit_entry' ) || has_shortcode( $post->post_content, 'he_saved_entries' ) ) );
		if ( ! $needed ) {
			return;
		}
		wp_enqueue_style( 'he-encyclopedia', HE_URL . 'assets/css/encyclopedia.css', array(), HE_VERSION );
		wp_enqueue_script( 'he-encyclopedia', HE_URL . 'assets/js/encyclopedia.js', array(), HE_VERSION, true );
		wp_localize_script(
			'he-encyclopedia',
			'heEncyclopedia',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'he_interaction' ),
				'loginUrl' => wp_login_url( home_url( '/' ) ),
				'messages' => array(
					'genericError' => __( 'The action could not be completed.', 'homeopathy-encyclopedia' ),
					'imageTooLarge' => __( 'Please choose an image that is 5 MB or smaller.', 'homeopathy-encyclopedia' ),
					'imageDimensions' => __( 'The image exceeds the permitted dimensions or pixel count.', 'homeopathy-encyclopedia' ),
					'imageInvalid' => __( 'The selected image could not be verified.', 'homeopathy-encyclopedia' ),
					'bookmark' => __( 'Bookmark', 'homeopathy-encyclopedia' ),
					'bookmarked' => __( 'Bookmarked', 'homeopathy-encyclopedia' ),
				),
			)
		);
	}

	public function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'encyclopedia' ) || ( isset( $_GET['post_type'] ) && HE_Content::TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) ) ) ) {
			wp_enqueue_style( 'he-admin', HE_URL . 'assets/css/admin.css', array(), HE_VERSION );
		}
	}

	public function maintenance() {
		HE_Database::cleanup_orphans();
		HE_Database::reindex_all();
	}

	public function reindex_saved_entry( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		HE_Database::reindex_entry( $post_id );
	}

	public function reindex_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( in_array( $taxonomy, array( HE_Content::TAX, HE_Content::SYSTEM ), true ) && HE_Content::TYPE === get_post_type( $object_id ) ) {
			HE_Database::reindex_entry( $object_id );
		}
	}

	public function remove_index( $post_id ) {
		if ( HE_Content::TYPE === get_post_type( $post_id ) ) {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'he_search_index', array( 'entry_id' => absint( $post_id ) ), array( '%d' ) );
		}
	}

	public function create_url( $url ) {
		$pages = (array) get_option( 'he_page_map', array() );
		return HE_Permissions::can_submit() && ! empty( $pages['submit'] ) ? get_permalink( absint( $pages['submit'] ) ) : $url;
	}
}

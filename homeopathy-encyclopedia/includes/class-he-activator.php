<?php
/** Activation, migration, managed ownership, and rollback controls. */

defined( 'ABSPATH' ) || exit;

final class HE_Activator {
	const STATE_OPTION = 'he_activation_state';

	public static function activate() {
		$preflight = HE_Dependencies::activation_preflight();
		if ( is_wp_error( $preflight ) ) {
			deactivate_plugins( plugin_basename( HE_FILE ) );
			wp_die( esc_html( $preflight->get_error_message() ), esc_html__( 'File 06 activation stopped', 'homeopathy-encyclopedia' ), array( 'back_link' => true ) );
		}

		$created = array( 'pages' => array(), 'posts' => array() );
		update_option( self::STATE_OPTION, array( 'status' => 'running', 'started_at' => gmdate( 'c' ), 'version' => HE_VERSION ), false );

		try {
			HE_Content::register();
			HE_Content::seed_terms();
			HE_Permissions::install_caps();
			HE_Database::install();
			HE_Database::migrate_legacy_systems();
			self::pages( $created );
			self::starter_drafts( $created );
			HE_Database::reindex_all();
			if ( ! wp_next_scheduled( 'he_daily_maintenance' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'he_daily_maintenance' );
			}
			update_option( 'he_version', HE_VERSION, false );
			update_option( self::STATE_OPTION, array( 'status' => 'complete', 'completed_at' => gmdate( 'c' ), 'version' => HE_VERSION ), false );
			set_transient( 'he_activation_notice', '1', 120 );
			flush_rewrite_rules();
		} catch ( Throwable $error ) {
			self::rollback_created_content( $created );
			update_option(
				self::STATE_OPTION,
				array(
					'status' => 'failed',
					'failed_at' => gmdate( 'c' ),
					'version' => HE_VERSION,
					'error' => sanitize_text_field( $error->getMessage() ),
				),
				false
			);
			HE_Dependencies::audit( 'activation_failed', array( 'error' => $error->getMessage() ) );
			deactivate_plugins( plugin_basename( HE_FILE ) );
			wp_die( esc_html( $error->getMessage() ), esc_html__( 'File 06 activation rolled back', 'homeopathy-encyclopedia' ), array( 'back_link' => true ) );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'he_daily_maintenance' );
		flush_rewrite_rules();
	}

	private static function pages( array &$created ) {
		$map = (array) get_option( 'he_page_map', array() );
		$map['submit'] = self::managed_page( 'submit', __( 'Submit Encyclopedia Entry', 'homeopathy-encyclopedia' ), 'submit-encyclopedia-entry', '[he_submit_entry]', $created );
		$map['saved'] = self::managed_page( 'saved', __( 'Saved Knowledge', 'homeopathy-encyclopedia' ), 'saved-knowledge', '[he_saved_entries]', $created );
		update_option( 'he_page_map', $map, false );
	}

	private static function managed_page( $key, $title, $slug, $shortcode, array &$created ) {
		$map = (array) get_option( 'he_page_map', array() );
		$stored = isset( $map[ $key ] ) ? absint( $map[ $key ] ) : 0;
		$existing = $stored ? get_post( $stored ) : null;
		if ( $existing instanceof WP_Post && 'page' === $existing->post_type && '1' === get_post_meta( $stored, '_he_managed_page', true ) && sanitize_key( $key ) === get_post_meta( $stored, '_he_managed_page_key', true ) && $shortcode === trim( $existing->post_content ) ) {
			return $stored;
		}

		$conflict = get_page_by_path( $slug );
		if ( $conflict instanceof WP_Post && ( '1' !== get_post_meta( $conflict->ID, '_he_managed_page', true ) || sanitize_key( $key ) !== get_post_meta( $conflict->ID, '_he_managed_page_key', true ) || $shortcode !== trim( $conflict->post_content ) ) ) {
			$slug .= '-file-06';
		}

		$id = wp_insert_post(
			array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_name' => $slug,
				'post_content' => $shortcode,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		update_post_meta( $id, '_he_managed_page', '1' );
		update_post_meta( $id, '_he_managed_page_key', sanitize_key( $key ) );
		$created['pages'][] = $id;
		return $id;
	}

	/** Seed institutional content as review-required drafts, never as automatic public claims. */
	private static function starter_drafts( array &$created ) {
		$founder = HE_Permissions::founder_id();
		if ( ! $founder ) {
			throw new RuntimeException( __( 'The File 00 official Founder account is unavailable.', 'homeopathy-encyclopedia' ) );
		}
		$items = array(
			array(
				__( 'How to Use This Encyclopedia', 'homeopathy-encyclopedia' ),
				'clinical-terminology',
				'not-applicable',
				__( 'A guide to searching entries, following relationships, and checking references.', 'homeopathy-encyclopedia' ),
				__( 'Use the search, A–Z index, knowledge types, body systems, references, and connected-learning links to explore the encyclopedia. Every entry remains subject to editorial review and versioned correction.', 'homeopathy-encyclopedia' ),
			),
			array(
				__( 'Classical Homeopathy: Foundational Definition', 'homeopathy-encyclopedia' ),
				'homeopathy-philosophy',
				'not-applicable',
				__( 'An editorial draft introducing the foundational vocabulary and individualizing method of classical homeopathy.', 'homeopathy-encyclopedia' ),
				__( 'Classical homeopathy studies the patient as an individual totality and compares characteristic symptoms with the recorded symptom pictures of medicines. This draft requires institutional review before public release.', 'homeopathy-encyclopedia' ),
			),
			array(
				__( 'Introduction to Symptom Analysis', 'homeopathy-encyclopedia' ),
				'symptom',
				'general-whole-body',
				__( 'A structured introduction to location, sensation, modalities, timing, intensity, and accompanying features.', 'homeopathy-encyclopedia' ),
				__( 'Symptom analysis organizes reported experiences into clear descriptive elements so that the complete pattern can be studied consistently and documented responsibly.', 'homeopathy-encyclopedia' ),
			),
		);

		foreach ( $items as $item ) {
			$existing = get_page_by_title( $item[0], OBJECT, HE_Content::TYPE );
			if ( $existing instanceof WP_Post ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type' => HE_Content::TYPE,
					'post_status' => 'draft',
					'post_title' => $item[0],
					'post_excerpt' => $item[3],
					'post_content' => $item[4],
					'post_author' => $founder,
					'comment_status' => 'closed',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( $id->get_error_message() );
			}
			$created['posts'][] = $id;
			if ( ! HE_Content::assign( $id, $item[1], HE_Content::TAX ) || ! HE_Content::assign( $id, $item[2], HE_Content::SYSTEM ) ) {
				throw new RuntimeException( __( 'A starter draft classification could not be assigned.', 'homeopathy-encyclopedia' ) );
			}
			update_post_meta( $id, '_he_references', __( 'Institutional bibliography and source-specific citations must be completed during editorial review.', 'homeopathy-encyclopedia' ) );
			update_post_meta( $id, '_he_language', 'en-US' );
			update_post_meta( $id, '_he_language_reviewed', 0 );
			update_post_meta( $id, '_he_workflow_state', 'seeded_draft' );
			update_post_meta( $id, '_he_row_version', 1 );
			update_post_meta( $id, '_he_source_provenance', 'file-06-1.0.0-seed' );
			HE_Database::audit( $id, 'seeded_draft', '', 'seeded_draft', __( 'Created during activation for editorial review; not publicly released.', 'homeopathy-encyclopedia' ), $founder );
		}
	}

	private static function rollback_created_content( array $created ) {
		foreach ( array_merge( $created['posts'], $created['pages'] ) as $post_id ) {
			wp_delete_post( absint( $post_id ), true );
		}
	}
}

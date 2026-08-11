<?php
/** Canonical File 06 domain model and owner commands. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Domain {
	const ENTRY_TYPE = 'he_entry';
	const RESEARCH_TYPE = 'he_research';
	const TAX_TYPE = 'he_type';
	const TAX_SYSTEM = 'he_body_system';
	const TAX_TOPIC = 'he_topic';
	private static $idempotency_leases = array();

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_types' ), 5 );
		add_action( 'save_post_' . self::ENTRY_TYPE, array( __CLASS__, 'on_save_entry' ), 30, 3 );
		add_action( 'save_post_' . self::RESEARCH_TYPE, array( __CLASS__, 'on_save_research' ), 30, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_direct_publish' ), 20, 2 );
		add_action( 'he_v2_maintenance', array( __CLASS__, 'maintenance' ) );
	}

	public static function types() {
		return array(
			'remedy'                    => __( 'Remedy', 'homeopathy-encyclopedia' ),
			'symptom'                   => __( 'Symptom', 'homeopathy-encyclopedia' ),
			'health-condition'          => __( 'Health Condition', 'homeopathy-encyclopedia' ),
			'anatomy'                   => __( 'Anatomy', 'homeopathy-encyclopedia' ),
			'pathology'                 => __( 'Pathology', 'homeopathy-encyclopedia' ),
			'body-system'               => __( 'Body System', 'homeopathy-encyclopedia' ),
			'cause-etiology'            => __( 'Cause and Etiology', 'homeopathy-encyclopedia' ),
			'modalities'                => __( 'Modalities', 'homeopathy-encyclopedia' ),
			'clinical-terminology'      => __( 'Clinical Terminology', 'homeopathy-encyclopedia' ),
			'nutrition'                 => __( 'Nutrition', 'homeopathy-encyclopedia' ),
			'principles-hygiene'        => __( 'Principles of Hygiene', 'homeopathy-encyclopedia' ),
			'islamic-spiritual-healing' => __( 'Islamic Spiritual Healing', 'homeopathy-encyclopedia' ),
			'homeopathy-philosophy'     => __( 'Homeopathy Philosophy', 'homeopathy-encyclopedia' ),
			'historical-person'         => __( 'Historical Person', 'homeopathy-encyclopedia' ),
			'book-reference'            => __( 'Book Reference', 'homeopathy-encyclopedia' ),
			'research-reference'        => __( 'Research Reference', 'homeopathy-encyclopedia' ),
		);
	}

	public static function systems() {
		return array(
			'general-whole-body' => __( 'General / Whole Body', 'homeopathy-encyclopedia' ),
			'nervous'            => __( 'Nervous System', 'homeopathy-encyclopedia' ),
			'cardiovascular'     => __( 'Cardiovascular System', 'homeopathy-encyclopedia' ),
			'respiratory'        => __( 'Respiratory System', 'homeopathy-encyclopedia' ),
			'digestive'          => __( 'Digestive System', 'homeopathy-encyclopedia' ),
			'musculoskeletal'    => __( 'Musculoskeletal System', 'homeopathy-encyclopedia' ),
			'endocrine'          => __( 'Endocrine System', 'homeopathy-encyclopedia' ),
			'urinary-renal'      => __( 'Urinary / Renal System', 'homeopathy-encyclopedia' ),
			'reproductive'       => __( 'Reproductive System', 'homeopathy-encyclopedia' ),
			'integumentary'      => __( 'Integumentary System', 'homeopathy-encyclopedia' ),
			'immune-lymphatic'   => __( 'Immune / Lymphatic System', 'homeopathy-encyclopedia' ),
			'sensory'            => __( 'Sensory Systems', 'homeopathy-encyclopedia' ),
			'oral-dental'        => __( 'Oral and Dental System', 'homeopathy-encyclopedia' ),
			'not-applicable'     => __( 'Not Applicable', 'homeopathy-encyclopedia' ),
		);
	}

	public static function evidence_grades() {
		return array( 'classical-primary', 'classical-secondary', 'clinical-observation', 'systematic-review', 'controlled-study', 'observational-study', 'expert-consensus', 'ungraded' );
	}

	public static function relation_types() {
		return array( 'related-to', 'remedy-of-interest', 'caution', 'contraindication', 'lesson-about', 'research-supports', 'research-contradicts' );
	}

	public static function entry_states() {
		return array( 'draft', 'validation', 'review', 'approved', 'scheduled', 'published', 'corrected', 'retracted', 'archived' );
	}

	public static function research_states() {
		return array( 'proposal', 'ethics_review', 'approved', 'rejected', 'active', 'analysis', 'peer_review', 'published', 'corrected', 'retracted' );
	}

	public static function integrity_states() {
		return array( 'submitted', 'triaged', 'under_review', 'accepted', 'rejected', 'applied', 'appealed' );
	}

	public static function register_types() {
		register_post_type( self::ENTRY_TYPE, array(
			'labels' => array(
				'name' => __( 'Encyclopedia Entries', 'homeopathy-encyclopedia' ),
				'singular_name' => __( 'Encyclopedia Entry', 'homeopathy-encyclopedia' ),
				'add_new_item' => __( 'Add Encyclopedia Entry', 'homeopathy-encyclopedia' ),
				'edit_item' => __( 'Edit Encyclopedia Entry', 'homeopathy-encyclopedia' ),
			),
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_icon' => 'dashicons-book-alt',
			'show_in_rest' => false,
			'has_archive' => 'encyclopedia',
			'rewrite' => array( 'slug' => 'encyclopedia/entry', 'with_front' => false ),
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'comments', 'revisions', 'custom-fields' ),
			'capability_type' => array( 'he_entry', 'he_entries' ),
			'map_meta_cap' => true,
			'capabilities' => array(
				'edit_post' => HE_V2_Auth::CAP_EDIT,
				'read_post' => 'read',
				'delete_post' => HE_V2_Auth::CAP_PUBLISH,
				'edit_posts' => HE_V2_Auth::CAP_EDIT,
				'edit_others_posts' => HE_V2_Auth::CAP_REVIEW,
				'publish_posts' => HE_V2_Auth::CAP_PUBLISH,
				'read_private_posts' => HE_V2_Auth::CAP_REVIEW,
				'delete_posts' => HE_V2_Auth::CAP_PUBLISH,
			),
		) );

		register_post_type( self::RESEARCH_TYPE, array(
			'labels' => array(
				'name' => __( 'Research Records', 'homeopathy-encyclopedia' ),
				'singular_name' => __( 'Research Record', 'homeopathy-encyclopedia' ),
			),
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => 'edit.php?post_type=' . self::ENTRY_TYPE,
			'show_in_rest' => false,
			'has_archive' => 'research',
			'rewrite' => array( 'slug' => 'research', 'with_front' => false ),
			'supports' => array( 'title', 'editor', 'excerpt', 'author', 'revisions' ),
			'capability_type' => array( 'he_research', 'he_research_records' ),
			'map_meta_cap' => true,
			'capabilities' => array(
				'edit_post' => HE_V2_Auth::CAP_RESEARCH,
				'read_post' => 'read',
				'delete_post' => HE_V2_Auth::CAP_RESEARCH,
				'edit_posts' => HE_V2_Auth::CAP_RESEARCH,
				'edit_others_posts' => HE_V2_Auth::CAP_RESEARCH,
				'publish_posts' => HE_V2_Auth::CAP_RESEARCH,
				'read_private_posts' => HE_V2_Auth::CAP_RESEARCH,
				'delete_posts' => HE_V2_Auth::CAP_RESEARCH,
			),
		) );

		register_taxonomy( self::TAX_TYPE, array( self::ENTRY_TYPE ), array(
			'labels' => array( 'name' => __( 'Knowledge Types', 'homeopathy-encyclopedia' ) ),
			'public' => true,
			'show_ui' => false,
			'show_in_rest' => false,
			'hierarchical' => true,
			'rewrite' => array( 'slug' => 'encyclopedia/type' ),
		) );
		register_taxonomy( self::TAX_SYSTEM, array( self::ENTRY_TYPE ), array(
			'labels' => array( 'name' => __( 'Body Systems', 'homeopathy-encyclopedia' ) ),
			'public' => true,
			'show_ui' => false,
			'show_in_rest' => false,
			'hierarchical' => true,
			'rewrite' => array( 'slug' => 'encyclopedia/system' ),
		) );
		register_taxonomy( self::TAX_TOPIC, array( self::ENTRY_TYPE, self::RESEARCH_TYPE ), array(
			'labels' => array( 'name' => __( 'Knowledge Topics', 'homeopathy-encyclopedia' ) ),
			'public' => true,
			'show_ui' => true,
			'show_in_rest' => false,
			'hierarchical' => false,
		) );
		self::seed_terms();
	}

	public static function seed_terms() {
		foreach ( array( self::TAX_TYPE => self::types(), self::TAX_SYSTEM => self::systems() ) as $taxonomy => $items ) {
			foreach ( $items as $slug => $name ) {
				if ( ! term_exists( $slug, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				}
			}
		}
	}

	public static function normalize( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = remove_accents( $value );
		$value = mb_strtolower( $value, 'UTF-8' );
		$value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value );
		$value = strtr( $value, array(
			'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ی', 'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ہ', 'ۀ' => 'ہ',
		) );
		$value = preg_replace( '/[^\p{L}\p{N}\s-]+/u', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', trim( $value ) );
		return mb_substr( $value, 0, 191, 'UTF-8' );
	}

	public static function trace_id() {
		return bin2hex( random_bytes( 16 ) );
	}


	public static function guard_direct_publish( $data, $postarr ) {
		if ( 'publish' !== ( $data['post_status'] ?? '' ) ) {
			return $data;
		}
		$post_type = $data['post_type'] ?? ( $postarr['post_type'] ?? '' );
		$post_id = absint( $postarr['ID'] ?? 0 );
		global $wpdb;
		if ( self::ENTRY_TYPE === $post_type ) {
			$row = $post_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT status,review_status,safety_status,current_version FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', $post_id ), ARRAY_A ) : null;
			if ( ! $row || 'published' !== $row['status'] || 'approved' !== $row['review_status'] || 'approved' !== $row['safety_status'] || ! $row['current_version'] ) {
				$data['post_status'] = 'pending';
			}
		}
		if ( self::RESEARCH_TYPE === $post_type ) {
			$status = $post_id ? $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post_id ) ) : '';
			if ( 'published' !== $status ) {
				$data['post_status'] = 'pending';
			}
		}
		return $data;
	}

	public static function on_save_entry( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		self::ensure_concept_for_post( $post_id );
		self::reindex_concept_by_post( $post_id );
	}

	public static function on_save_research( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		global $wpdb;
		$table = HE_V2_Schema::table( 'research' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id=%d", $post_id ) );
		if ( ! $existing ) {
			$wpdb->insert( $table, array(
				'public_id' => wp_generate_uuid4(),
				'post_id' => $post_id,
				'record_type' => 'publication',
				'status' => 'publish' === $post->post_status ? 'published' : 'proposal',
				'title' => $post->post_title,
				'question' => $post->post_excerpt,
				'protocol' => $post->post_content,
				'investigators_json' => '[]',
				'ethics_json' => '{}',
				'consent_json' => '{}',
				'conflicts_json' => '[]',
				'data_class' => 'restricted',
				'case_json' => '{}',
				'metadata_json' => '{}',
				'created_by' => (int) $post->post_author,
				'created_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			), array( '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s' ) );
		}
	}

	public static function on_delete_post( $post_id ) {
		global $wpdb;
		if ( self::ENTRY_TYPE === get_post_type( $post_id ) ) {
			$concept_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', $post_id ) );
			if ( $concept_id ) {
				$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'status' => 'archived', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $concept_id ), array( '%s', '%s' ), array( '%d' ) );
				$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => $concept_id ), array( '%d' ) );
				self::emit_event( 'EncyclopediaEntryArchived.v1', 'concept', $concept_id, array( 'post_id' => $post_id ) );
			}
		}
	}

	public static function ensure_concept_for_post( $post_id ) {
		global $wpdb;
		$post = get_post( absint( $post_id ) );
		if ( ! $post || self::ENTRY_TYPE !== $post->post_type ) {
			return 0;
		}
		$table = HE_V2_Schema::table( 'concepts' );
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id=%d", $post_id ) );
		$type = self::taxonomy_slug( $post_id, self::TAX_TYPE );
		$language = get_post_meta( $post_id, '_he_language', true ) ?: 'en-US';
		if ( $existing ) {
			if ( ! $type || ! isset( self::types()[ $type ] ) ) {
				$type = 'clinical-terminology';
			}
			$wpdb->update( $table, array( 'type_slug' => $type, 'language' => $language, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $existing ), array( '%s','%s','%s' ), array( '%d' ) );
			return $existing;
		}

		if ( ! $type || ! isset( self::types()[ $type ] ) ) {
			$type = 'clinical-terminology';
			wp_set_object_terms( $post_id, array( $type ), self::TAX_TYPE, false );
		}
		$slug = $post->post_name ? $post->post_name : sanitize_title( $post->post_title );
		$slug = self::unique_slug( $slug, 0 );
		$status = 'publish' === $post->post_status ? 'published' : 'draft';
		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert( $table, array(
			'public_id' => wp_generate_uuid4(),
			'post_id' => $post_id,
			'type_slug' => $type,
			'canonical_slug' => $slug,
			'language' => $language,
			'status' => $status,
			'safety_status' => get_post_meta( $post_id, '_he_safety_status', true ) ?: 'unreviewed',
			'review_status' => get_post_meta( $post_id, '_he_review_status', true ) ?: 'unreviewed',
			'created_by' => (int) $post->post_author,
			'created_at' => $now,
			'updated_at' => $now,
		), array( '%s','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s' ) );
		if ( ! $ok ) {
			return 0;
		}
		$concept_id = (int) $wpdb->insert_id;
		self::add_alias( $concept_id, $post->post_title, 'en-US', 'canonical', true, (int) $post->post_author );
		if ( 'published' === $status ) {
			$version_id = self::snapshot_version( $concept_id, 'Imported published baseline', 'published', (int) $post->post_author );
			$wpdb->update( $table, array( 'current_version' => $version_id ), array( 'id' => $concept_id ), array( '%d' ), array( '%d' ) );
		}
		return $concept_id;
	}

	private static function unique_slug( $slug, $exclude_id ) {
		global $wpdb;
		$base = sanitize_title( $slug );
		$base = $base ?: 'concept';
		$candidate = $base;
		$counter = 2;
		$table = HE_V2_Schema::table( 'concepts' );
		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE canonical_slug=%s AND id<>%d", $candidate, absint( $exclude_id ) ) ) ) {
			$candidate = $base . '-' . $counter;
			++$counter;
		}
		return $candidate;
	}

	public static function taxonomy_slug( $post_id, $taxonomy ) {
		$terms = get_the_terms( absint( $post_id ), $taxonomy );
		return $terms && ! is_wp_error( $terms ) ? (string) $terms[0]->slug : '';
	}

	public static function concept_by_id( $identifier, $include_private = false ) {
		global $wpdb;
		$table = HE_V2_Schema::table( 'concepts' );
		if ( is_numeric( $identifier ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $identifier ) ), ARRAY_A );
		} else {
			$value = sanitize_text_field( (string) $identifier );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id=%s OR canonical_slug=%s", $value, sanitize_title( $value ) ), ARRAY_A );
			if ( ! $row ) {
				$normalized = self::normalize( $value );
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT c.* FROM {$table} c INNER JOIN " . HE_V2_Schema::table( 'aliases' ) . " a ON a.concept_id=c.id WHERE a.normalized_alias=%s LIMIT 1", $normalized ), ARRAY_A );
			}
		}
		if ( ! $row ) {
			return null;
		}
		if ( ! empty( $row['merged_into_id'] ) ) {
			return self::concept_by_id( (int) $row['merged_into_id'], $include_private );
		}
		if ( ! $include_private && ! self::is_public_concept( $row ) ) {
			return null;
		}
		return $row;
	}

	public static function is_public_concept( $row ) {
		if ( ! is_array( $row ) || ! in_array( $row['status'], array( 'published', 'retracted' ), true ) || 'approved' !== $row['review_status'] || 'approved' !== $row['safety_status'] || ! empty( $row['merged_into_id'] ) ) {
			return false;
		}
		$post = get_post( (int) $row['post_id'] );
		return $post && 'publish' === $post->post_status;
	}

	public static function public_dto( $row, $version_number = 0 ) {
		global $wpdb;
		if ( ! is_array( $row ) || ! self::is_public_concept( $row ) ) {
			return null;
		}
		$post = get_post( (int) $row['post_id'] );
		$version = $version_number ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE concept_id=%d AND version_number=%d', $row['id'], $version_number ), ARRAY_A ) : $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d', $row['current_version'] ), ARRAY_A );
		if ( ! $version ) {
			return null;
		}
		$aliases = $wpdb->get_results( $wpdb->prepare( 'SELECT alias,language,alias_type FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d ORDER BY is_primary DESC,alias ASC', $row['id'] ), ARRAY_A );
		$references = $wpdb->get_results( $wpdb->prepare( 'SELECT id,source_type,author,title,edition,volume,page_locator,publisher,year,url,doi,evidence_grade,rights_status,link_status FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d ORDER BY id ASC', $row['id'], $version['id'] ), ARRAY_A );
		$integrity = $wpdb->get_results( $wpdb->prepare( "SELECT public_id,action_type,status,reason,replacement_object_id,updated_at FROM " . HE_V2_Schema::table( 'integrity_actions' ) . " WHERE object_type='concept' AND object_id=%d AND status IN ('accepted','applied') ORDER BY id DESC", $row['id'] ), ARRAY_A );
		$structured = json_decode( (string) $version['structured_json'], true );
		$structured = is_array( $structured ) ? $structured : array();
		return array(
			'id' => $row['public_id'],
			'canonical_url' => get_permalink( (int) $row['post_id'] ),
			'slug' => $row['canonical_slug'],
			'type' => $row['type_slug'],
			'body_system' => self::taxonomy_slug( (int) $row['post_id'], self::TAX_SYSTEM ),
			'language' => $row['language'],
			'title' => $version['title'],
			'summary' => $version['summary'],
			'body' => wp_kses_post( $version['body'] ),
			'fields' => self::public_structured_fields( $structured ),
			'aliases' => $aliases,
			'references' => $references,
			'version' => (int) $version['version_number'],
			'effective_at' => $version['effective_at'],
			'change_reason' => $version['change_reason'],
			'review_status' => $row['review_status'],
			'safety_status' => $row['safety_status'],
			'record_status' => $row['status'],
			'is_historical' => (int) $version['id'] !== (int) $row['current_version'],
			'current_version' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT version_number FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d', $row['current_version'] ) ),
			'integrity_notices' => $integrity,
			'freshness' => array( 'updated_at' => $row['updated_at'], 'contract_version' => HE_CONTRACT_VERSION ),
		);
	}

	private static function public_structured_fields( $structured ) {
		$allowed = array( 'source', 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'limitations', 'emergency_boundary', 'evidence_summary' );
		$output = array();
		foreach ( $allowed as $key ) {
			if ( isset( $structured[ $key ] ) && '' !== $structured[ $key ] ) {
				$output[ $key ] = is_array( $structured[ $key ] ) ? array_map( 'sanitize_text_field', $structured[ $key ] ) : wp_kses_post( (string) $structured[ $key ] );
			}
		}
		return $output;
	}

	public static function create_entry( $data, $actor_id ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$type = sanitize_key( $data['type'] ?? '' );
		$system = sanitize_key( $data['body_system'] ?? 'not-applicable' );
		$title = sanitize_text_field( $data['title'] ?? '' );
		$summary = sanitize_textarea_field( $data['summary'] ?? '' );
		$body = wp_kses_post( $data['body'] ?? '' );
		$language = sanitize_text_field( $data['language'] ?? 'en-US' );
		if ( ! isset( self::types()[ $type ] ) || ! isset( self::systems()[ $system ] ) || ! $title ) {
			return new WP_Error( 'he_invalid_entry', __( 'A valid title, type and body system are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$post_id = wp_insert_post( array(
			'post_type' => self::ENTRY_TYPE,
			'post_status' => 'draft',
			'post_author' => $actor_id,
			'post_title' => $title,
			'post_excerpt' => $summary,
			'post_content' => $body,
			'comment_status' => 'open',
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		wp_set_object_terms( $post_id, array( $type ), self::TAX_TYPE, false );
		wp_set_object_terms( $post_id, array( $system ), self::TAX_SYSTEM, false );
		update_post_meta( $post_id, '_he_language', $language );
		update_post_meta( $post_id, '_he_structured', self::sanitize_structured( $data['fields'] ?? array() ) );
		$concept_id = self::ensure_concept_for_post( $post_id );
		if ( ! $concept_id ) {
			wp_delete_post( $post_id, true );
			return new WP_Error( 'he_concept_failed', __( 'The canonical concept could not be created.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
		}
		if ( ! empty( $data['aliases'] ) ) {
			foreach ( (array) $data['aliases'] as $alias ) {
				$alias_result = self::add_alias( $concept_id, $alias['alias'] ?? '', $alias['language'] ?? $language, $alias['type'] ?? 'synonym', false, $actor_id );
				if ( is_wp_error( $alias_result ) ) {
					self::rollback_new_entry( $concept_id, $post_id );
					return $alias_result;
				}
			}
		}
		if ( ! empty( $data['references'] ) ) {
			foreach ( array_slice( (array) $data['references'], 0, 100 ) as $reference ) {
				$reference_result = self::add_reference( $concept_id, is_array( $reference ) ? $reference : array(), $actor_id, 0 );
				if ( is_wp_error( $reference_result ) ) {
					self::rollback_new_entry( $concept_id, $post_id );
					return $reference_result;
				}
			}
		}
		self::emit_event( 'EncyclopediaEntryDraftCreated.v1', 'concept', $concept_id, array( 'public_id' => self::concept_by_id( $concept_id, true )['public_id'] ) );
		return self::concept_by_id( $concept_id, true );
	}

	public static function sanitize_structured( $fields ) {
		$output = array();
		$allowed = array( 'source', 'key_points', 'symptoms', 'causes', 'modalities', 'red_flags', 'safety', 'limitations', 'emergency_boundary', 'evidence_summary' );
		foreach ( $allowed as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				$output[ $key ] = is_array( $fields[ $key ] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $fields[ $key ] ) ) ) : sanitize_textarea_field( $fields[ $key ] );
			}
		}
		return $output;
	}

	private static function rollback_new_entry( $concept_id, $post_id ) {
		global $wpdb;
		$concept_id = absint( $concept_id );
		foreach ( array( 'aliases', 'references', 'relations', 'reviews', 'versions', 'search_index' ) as $suffix ) {
			$table = HE_V2_Schema::table( $suffix );
			if ( 'relations' === $suffix ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source_concept_id=%d OR target_concept_id=%d", $concept_id, $concept_id ) );
			} elseif ( 'reviews' === $suffix ) {
				$wpdb->delete( $table, array( 'object_type' => 'concept', 'object_id' => $concept_id ), array( '%s', '%d' ) );
			} else {
				$column = 'search_index' === $suffix ? 'concept_id' : 'concept_id';
				$wpdb->delete( $table, array( $column => $concept_id ), array( '%d' ) );
			}
		}
		$wpdb->delete( HE_V2_Schema::table( 'concepts' ), array( 'id' => $concept_id ), array( '%d' ) );
		wp_delete_post( absint( $post_id ), true );
	}

	public static function add_alias( $concept_id, $alias, $language, $type, $primary, $actor_id ) {
		global $wpdb;
		$alias = sanitize_text_field( $alias );
		$normalized = self::normalize( $alias );
		$language = sanitize_text_field( $language ?: 'en-US' );
		$type = in_array( $type, array( 'canonical', 'synonym', 'transliteration', 'former-name', 'redirect' ), true ) ? $type : 'synonym';
		if ( ! $alias || ! $normalized ) {
			return new WP_Error( 'he_empty_alias', __( 'Alias cannot be empty.', 'homeopathy-encyclopedia' ) );
		}
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT concept_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE normalized_alias=%s AND language=%s', $normalized, $language ), ARRAY_A );
		if ( $existing && (int) $existing['concept_id'] !== absint( $concept_id ) ) {
			return new WP_Error( 'he_alias_collision', __( 'This alias already belongs to another canonical concept.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		if ( $existing ) {
			return true;
		}
		return (bool) $wpdb->insert( HE_V2_Schema::table( 'aliases' ), array(
			'concept_id' => absint( $concept_id ),
			'alias' => $alias,
			'normalized_alias' => $normalized,
			'language' => $language,
			'alias_type' => $type,
			'is_primary' => $primary ? 1 : 0,
			'created_by' => absint( $actor_id ),
			'created_at' => current_time( 'mysql', true ),
		), array( '%d','%s','%s','%s','%s','%d','%d','%s' ) );
	}

	public static function add_reference( $concept_id, $data, $actor_id, $version_id = 0 ) {
		global $wpdb;
		$source_type = sanitize_key( $data['source_type'] ?? '' );
		$title = sanitize_text_field( $data['title'] ?? '' );
		$grade = sanitize_key( $data['evidence_grade'] ?? 'ungraded' );
		if ( ! $source_type || ! $title || ! in_array( $grade, self::evidence_grades(), true ) ) {
			return new WP_Error( 'he_invalid_reference', __( 'Source type, title and a valid evidence grade are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$url = esc_url_raw( $data['url'] ?? '' );
		if ( $url && ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'he_invalid_reference_url', __( 'The reference URL is invalid.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$quote_count = min( 25, absint( $data['quotation_word_count'] ?? 0 ) );
		$ok = $wpdb->insert( HE_V2_Schema::table( 'references' ), array(
			'concept_id' => absint( $concept_id ),
			'version_id' => absint( $version_id ),
			'source_type' => $source_type,
			'author' => sanitize_text_field( $data['author'] ?? '' ),
			'title' => $title,
			'edition' => sanitize_text_field( $data['edition'] ?? '' ),
			'volume' => sanitize_text_field( $data['volume'] ?? '' ),
			'page_locator' => sanitize_text_field( $data['page'] ?? '' ),
			'publisher' => sanitize_text_field( $data['publisher'] ?? '' ),
			'year' => sanitize_text_field( $data['year'] ?? '' ),
			'url' => $url,
			'doi' => sanitize_text_field( $data['doi'] ?? '' ),
			'evidence_grade' => $grade,
			'rights_status' => sanitize_key( $data['rights_status'] ?? 'citation-only' ),
			'quotation_word_count' => $quote_count,
			'link_status' => $url ? 'unchecked' : 'not-applicable',
			'created_by' => absint( $actor_id ),
			'created_at' => current_time( 'mysql', true ),
		), array( '%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%s' ) );
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'he_reference_write_failed', __( 'The reference could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
	}

	public static function validate_for_review( $concept_id ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$post = get_post( (int) $row['post_id'] );
		$fields = get_post_meta( $post->ID, '_he_structured', true );
		$fields = is_array( $fields ) ? $fields : array();
		$errors = array();
		if ( ! trim( $post->post_title ) || ! trim( $post->post_excerpt ) || ! trim( wp_strip_all_tags( $post->post_content ) ) ) {
			$errors[] = 'title-summary-body-required';
		}
		$references = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND (version_id=0 OR version_id=%d)', $row['id'], (int) $row['current_version'] ) );
		if ( $references < 1 ) {
			$errors[] = 'reference-required';
		}
		if ( in_array( $row['type_slug'], array( 'health-condition', 'pathology' ), true ) && empty( $fields['red_flags'] ) ) {
			$errors[] = 'red-flags-required';
		}
		if ( 'remedy' === $row['type_slug'] && ( empty( $fields['safety'] ) || empty( $fields['limitations'] ) ) ) {
			$errors[] = 'remedy-safety-limitations-required';
		}
		if ( in_array( $row['type_slug'], array( 'health-condition', 'pathology', 'remedy' ), true ) && empty( $fields['emergency_boundary'] ) ) {
			$errors[] = 'emergency-boundary-required';
		}
		return $errors ? new WP_Error( 'he_validation_failed', __( 'Entry validation failed.', 'homeopathy-encyclopedia' ), array( 'status' => 422, 'fields' => $errors ) ) : true;
	}

	public static function transition_entry( $concept_id, $to_state, $expected_version, $actor_id, $note = '', $effective_at = '' ) {
		global $wpdb;
		$to_state = sanitize_key( $to_state );
		if ( ! in_array( $to_state, self::entry_states(), true ) ) {
			return new WP_Error( 'he_invalid_state', __( 'Invalid entry state.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$transitions = array(
			'draft' => array( 'validation', 'archived' ),
			'validation' => array( 'draft', 'review' ),
			'review' => array( 'draft', 'approved' ),
			'approved' => array( 'scheduled', 'published', 'draft' ),
			'scheduled' => array( 'published', 'draft' ),
			'published' => array( 'corrected', 'retracted', 'archived' ),
			'corrected' => array( 'published', 'retracted', 'archived' ),
			'retracted' => array( 'archived' ),
			'archived' => array(),
		);
		if ( ! in_array( $to_state, $transitions[ $row['status'] ] ?? array(), true ) ) {
			return new WP_Error( 'he_transition_forbidden', __( 'This state transition is not allowed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		if ( in_array( $to_state, array( 'review', 'approved', 'scheduled', 'published' ), true ) ) {
			$validation = self::validate_for_review( $row['id'] );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}
		if ( in_array( $to_state, array( 'approved', 'scheduled', 'published' ), true ) ) {
			$reviews = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . HE_V2_Schema::table( 'reviews' ) . " WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0", $row['id'] ) );
			if ( $reviews < 1 && ! HE_V2_Auth::is_founder( $actor_id ) ) {
				return new WP_Error( 'he_review_required', __( 'An independent approved review is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		$scheduled_at = '';
		if ( 'scheduled' === $to_state ) {
			$timestamp = strtotime( (string) $effective_at );
			if ( ! $timestamp || $timestamp <= time() || $timestamp > time() + YEAR_IN_SECONDS ) {
				return new WP_Error( 'he_invalid_schedule', __( 'A future publication time within one year is required.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			$scheduled_at = gmdate( 'Y-m-d H:i:s', $timestamp );
		}
		$table = HE_V2_Schema::table( 'concepts' );
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $to_state, $row['id'], absint( $expected_version ) ) );
		if ( 1 !== (int) $result ) {
			return new WP_Error( 'he_version_conflict', __( 'The entry changed in another session. Reload and try again.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		if ( 'scheduled' === $to_state ) {
			update_post_meta( (int) $row['post_id'], '_he_scheduled_at', $scheduled_at );
			self::emit_event( 'EncyclopediaEntryScheduled.v1', 'concept', $row['id'], array( 'effective_at' => $scheduled_at ) );
		} elseif ( in_array( $to_state, array( 'draft', 'published', 'archived' ), true ) ) {
			delete_post_meta( (int) $row['post_id'], '_he_scheduled_at' );
		}
		if ( 'published' === $to_state ) {
			$version_id = self::snapshot_version( $row['id'], $note ?: 'Published version', 'published', $actor_id );
			$wpdb->update( $table, array( 'current_version' => $version_id, 'review_status' => 'approved', 'safety_status' => 'approved' ), array( 'id' => $row['id'] ), array( '%d','%s','%s' ), array( '%d' ) );
			wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
			self::reindex_concept( $row['id'] );
			self::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', $row['id'], array( 'version_id' => $version_id ) );
		}
		return self::concept_by_id( $row['id'], true );
	}

	public static function add_review( $concept_id, $scope, $decision, $conflict, $note, $reviewer_id, $expected_version = 0 ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$expected_version = absint( $expected_version );
		if ( ! $expected_version || $expected_version !== (int) $row['row_version'] ) {
			return new WP_Error( 'he_version_conflict', __( 'The entry changed before the review could be stored. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		$post = get_post( (int) $row['post_id'] );
		if ( (int) $post->post_author === absint( $reviewer_id ) ) {
			return new WP_Error( 'he_self_review_forbidden', __( 'An author cannot independently approve their own entry.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		$scope = in_array( $scope, array( 'scientific', 'clinical', 'source', 'language', 'shariah', 'privacy' ), true ) ? $scope : 'scientific';
		$decision = in_array( $decision, array( 'approved', 'changes_required', 'rejected' ), true ) ? $decision : 'changes_required';
		$content_hash = HE_V22_Governance::entry_content_hash( $row );
		$ok = $wpdb->insert( HE_V2_Schema::table( 'reviews' ), array(
			'object_type' => 'concept',
			'object_id' => $row['id'],
			'reviewer_id' => absint( $reviewer_id ),
			'scope' => $scope,
			'decision' => $decision,
			'conflict_declared' => $conflict ? 1 : 0,
			'note' => sanitize_textarea_field( $note ),
			'content_hash' => $content_hash,
			'reviewed_row_version' => (int) $row['row_version'],
			'review_subject_author' => $post ? (int) $post->post_author : 0,
			'created_at' => current_time( 'mysql', true ),
		), array( '%s','%d','%d','%s','%s','%d','%s','%s','%d','%d','%s' ) );
		if ( $ok ) {
			self::emit_event( 'EncyclopediaEntryReviewed.v1', 'concept', $row['id'], array( 'scope' => $scope, 'decision' => $decision ) );
		}
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'he_review_write_failed', __( 'Review could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
	}

	private static function bind_references_to_snapshot( $concept_id, $previous_version_id, $new_version_id, $actor_id ) {
		global $wpdb;
		$table = HE_V2_Schema::table( 'references' );
		$concept_id = absint( $concept_id ); $previous_version_id = absint( $previous_version_id ); $new_version_id = absint( $new_version_id );
		if ( ! $concept_id || ! $new_version_id ) { return; }
		/* Pending draft references become immutable members of the new snapshot first. */
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET version_id=%d WHERE concept_id=%d AND version_id=0", $new_version_id, $concept_id ) );
		if ( ! $previous_version_id || $previous_version_id === $new_version_id ) { return; }
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE concept_id=%d AND version_id=%d ORDER BY id ASC", $concept_id, $previous_version_id ), ARRAY_A );
		foreach ( $rows as $ref ) {
			if ( ! is_array( $ref ) ) { continue; }
			$old_reference_id = absint( $ref['id'] ?? 0 );
			$new_reference_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE concept_id=%d AND version_id=%d AND source_type=%s AND title=%s AND edition=%s AND page_locator=%s AND url=%s AND doi=%s LIMIT 1",
				$concept_id, $new_version_id, $ref['source_type'], $ref['title'], $ref['edition'], $ref['page_locator'], $ref['url'], $ref['doi']
			) );
			if ( ! $new_reference_id ) {
				unset( $ref['id'] );
				$ref['version_id'] = $new_version_id;
				$ref['created_by'] = absint( $actor_id );
				$ref['created_at'] = current_time( 'mysql', true );
				if ( ! $wpdb->insert( $table, $ref ) ) {
					continue;
				}
				$new_reference_id = (int) $wpdb->insert_id;
			}
			if ( $old_reference_id && $new_reference_id ) {
				$wpdb->query( $wpdb->prepare(
					'UPDATE ' . HE_V2_Schema::table( 'relations' ) . ' SET source_reference_id=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE source_concept_id=%d AND source_reference_id=%d',
					$new_reference_id, $concept_id, $old_reference_id
				) );
			}
		}
	}

	public static function snapshot_version( $concept_id, $reason, $status, $actor_id ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) { return 0; }
		$post = get_post( (int) $row['post_id'] );
		$structured = get_post_meta( $post->ID, '_he_structured', true );
		$structured = is_array( $structured ) ? $structured : array();
		$version_number = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(version_number) FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE concept_id=%d', $row['id'] ) );
		$body = (string) $post->post_content;
		$hash = hash( 'sha256', wp_json_encode( array( $post->post_title, $post->post_excerpt, $body, $structured ) ) );
		$ok = $wpdb->insert( HE_V2_Schema::table( 'versions' ), array(
			'concept_id' => $row['id'], 'version_number' => $version_number, 'status' => sanitize_key( $status ),
			'title' => $post->post_title, 'summary' => $post->post_excerpt, 'body' => $body,
			'structured_json' => wp_json_encode( $structured ), 'content_hash' => $hash,
			'change_reason' => sanitize_textarea_field( $reason ), 'effective_at' => current_time( 'mysql', true ),
			'created_by' => absint( $actor_id ), 'created_at' => current_time( 'mysql', true ),
		) );
		if ( ! $ok ) { return 0; }
		$new_version_id = (int) $wpdb->insert_id;
		self::bind_references_to_snapshot( $row['id'], (int) $row['current_version'], $new_version_id, $actor_id );
		return $new_version_id;
	}

	public static function create_integrity_action( $concept_id, $type, $reason, $evidence, $replacement_id, $actor_id ) {
		global $wpdb;
		$type = in_array( $type, array( 'correction', 'retraction', 'merge', 'appeal' ), true ) ? $type : 'correction';
		if ( ! trim( $reason ) ) {
			return new WP_Error( 'he_reason_required', __( 'A reason is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$ok = $wpdb->insert( HE_V2_Schema::table( 'integrity_actions' ), array(
			'public_id' => wp_generate_uuid4(),
			'object_type' => 'concept',
			'object_id' => absint( $concept_id ),
			'action_type' => $type,
			'status' => 'submitted',
			'reason' => sanitize_textarea_field( $reason ),
			'evidence' => sanitize_textarea_field( $evidence ),
			'replacement_object_id' => absint( $replacement_id ),
			'created_by' => absint( $actor_id ),
			'created_at' => current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
		), array( '%s','%s','%d','%s','%s','%s','%s','%d','%d','%s','%s' ) );
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'he_integrity_write_failed', __( 'Integrity action could not be created.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
	}

	public static function apply_integrity_action( $action_id, $expected_version, $actor_id ) {
		global $wpdb;
		$table = HE_V2_Schema::table( 'integrity_actions' );
		$action = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $action_id ) ), ARRAY_A );
		if ( ! $action ) {
			return new WP_Error( 'he_not_found', __( 'Integrity action not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='applied',decided_by=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d AND status IN ('submitted','triaged','under_review','accepted')", absint( $actor_id ), $action['id'], absint( $expected_version ) ) );
		if ( 1 !== (int) $result ) {
			return new WP_Error( 'he_version_conflict', __( 'The integrity record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		$concept = self::concept_by_id( (int) $action['object_id'], true );
		if ( ! $concept ) {
			return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		if ( 'retraction' === $action['action_type'] ) {
			$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'status' => 'retracted', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $concept['id'] ), array( '%s','%s' ), array( '%d' ) );
			self::emit_event( 'EncyclopediaEntryRetracted.v1', 'concept', $concept['id'], array( 'reason' => $action['reason'], 'replacement_id' => $action['replacement_object_id'] ) );
		} elseif ( 'correction' === $action['action_type'] ) {
			$version_id = self::snapshot_version( $concept['id'], $action['reason'], 'corrected', $actor_id );
			$wpdb->update( HE_V2_Schema::table( 'concepts' ), array( 'status' => 'published', 'current_version' => $version_id, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $concept['id'] ), array( '%s','%d','%s' ), array( '%d' ) );
			self::emit_event( 'EncyclopediaEntryCorrected.v1', 'concept', $concept['id'], array( 'version_id' => $version_id, 'reason' => $action['reason'] ) );
		}
		self::reindex_concept( $concept['id'] );
		return true;
	}

	public static function add_relation( $source_id, $target_id, $type, $reference_id, $actor_id ) {
		global $wpdb;
		$type = sanitize_key( $type );
		$source_id = absint( $source_id ); $target_id = absint( $target_id ); $reference_id = absint( $reference_id );
		if ( ! in_array( $type, self::relation_types(), true ) || $source_id === $target_id ) {
			return new WP_Error( 'he_invalid_relation', __( 'Invalid knowledge relationship.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$source = self::concept_by_id( $source_id, true ); $target = self::concept_by_id( $target_id, true );
		if ( ! $source || ! $target ) {
			return new WP_Error( 'he_relation_target_missing', __( 'Relationship concepts could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		if ( ! $reference_id ) {
			return new WP_Error( 'he_relation_provenance_required', __( 'Every knowledge relationship requires source-version provenance.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$reference = $wpdb->get_row( $wpdb->prepare( 'SELECT id,concept_id,version_id FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE id=%d', $reference_id ), ARRAY_A );
		if ( ! $reference || (int) $reference['concept_id'] !== $source_id || ( (int) $reference['version_id'] !== 0 && (int) $reference['version_id'] !== (int) $source['current_version'] ) || ( ! $source['current_version'] && (int) $reference['version_id'] !== 0 ) ) {
			return new WP_Error( 'he_relation_provenance_invalid', __( 'Relationship provenance must be pending for the next source snapshot or bound to the current source version.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
		}
		$now = current_time( 'mysql', true );
		$ok = $wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . HE_V2_Schema::table( 'relations' ) . ' (source_concept_id,target_concept_id,relation_type,owner_file,source_reference_id,status,row_version,created_by,created_at,updated_at) VALUES (%d,%d,%s,%s,%d,%s,1,%d,%s,%s) ON DUPLICATE KEY UPDATE source_reference_id=VALUES(source_reference_id),status=\'active\',row_version=row_version+1,updated_at=VALUES(updated_at)',
			$source_id, $target_id, $type, 'file-06', $reference_id, 'active', absint( $actor_id ), $now, $now
		) );
		return false !== $ok ? true : new WP_Error( 'he_relation_write_failed', __( 'The knowledge relationship could not be stored.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
	}

	public static function graph( $concept_id, $depth = 1, $limit = 50 ) {
		global $wpdb;
		$depth = min( 2, max( 1, absint( $depth ) ) );
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$visited = array();
		$queue = array( array( absint( $concept_id ), 0 ) );
		$nodes = array();
		$edges = array();
		while ( $queue && count( $edges ) < $limit ) {
			list( $current, $level ) = array_shift( $queue );
			if ( isset( $visited[ $current ] ) || $level > $depth ) {
				continue;
			}
			$visited[ $current ] = true;
			$row = self::concept_by_id( $current );
			if ( $row ) {
				$dto = self::public_dto( $row );
				if ( $dto ) {
					$nodes[] = array( 'id' => $dto['id'], 'title' => $dto['title'], 'type' => $dto['type'], 'url' => $dto['canonical_url'] );
				}
			}
			if ( $level >= $depth ) {
				continue;
			}
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT r.* FROM ' . HE_V2_Schema::table( 'relations' ) . ' r INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . ' sc ON sc.id=r.source_concept_id INNER JOIN ' . HE_V2_Schema::table( 'references' ) . " ref ON ref.id=r.source_reference_id AND ref.concept_id=r.source_concept_id AND ref.version_id=sc.current_version WHERE r.status='active' AND sc.current_version>0 AND (r.source_concept_id=%d OR r.target_concept_id=%d) LIMIT %d", $current, $current, $limit ), ARRAY_A );
			foreach ( $rows as $edge ) {
				$other = (int) $edge['source_concept_id'] === $current ? (int) $edge['target_concept_id'] : (int) $edge['source_concept_id'];
				if ( ! self::concept_by_id( $other ) ) {
					continue;
				}
				$edges[] = array( 'source' => (int) $edge['source_concept_id'], 'target' => (int) $edge['target_concept_id'], 'type' => $edge['relation_type'], 'owner' => $edge['owner_file'], 'version' => (int) $edge['row_version'] );
				$queue[] = array( $other, $level + 1 );
				if ( count( $edges ) >= $limit ) {
					break;
				}
			}
		}
		return array( 'nodes' => $nodes, 'edges' => $edges, 'bounded_depth' => $depth, 'bounded_limit' => $limit );
	}

	public static function merge_concepts( $source_id, $target_id, $expected_source_version, $expected_target_version, $actor_id, $reason ) {
		global $wpdb;
		$source = self::concept_by_id( $source_id, true );
		$target = self::concept_by_id( $target_id, true );
		if ( ! $source || ! $target || (int) $source['id'] === (int) $target['id'] ) {
			return new WP_Error( 'he_invalid_merge', __( 'A valid source and target concept are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		if ( (int) $source['row_version'] !== absint( $expected_source_version ) || (int) $target['row_version'] !== absint( $expected_target_version ) ) {
			return new WP_Error( 'he_version_conflict', __( 'One of the concepts changed before the merge.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		$wpdb->query( 'START TRANSACTION' );
		try {
			$aliases = $wpdb->get_results( $wpdb->prepare( 'SELECT alias,language,alias_type FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d', $source['id'] ), ARRAY_A );
			foreach ( $aliases as $alias ) {
				$result = self::add_alias( $target['id'], $alias['alias'], $alias['language'], 'redirect', false, $actor_id );
				if ( is_wp_error( $result ) && 'he_alias_collision' !== $result->get_error_code() ) {
					throw new RuntimeException( $result->get_error_message() );
				}
			}
			$edges = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'relations' ) . ' WHERE source_concept_id=%d OR target_concept_id=%d', $source['id'], $source['id'] ), ARRAY_A );
			foreach ( $edges as $edge ) {
				$new_source = (int) $edge['source_concept_id'] === (int) $source['id'] ? (int) $target['id'] : (int) $edge['source_concept_id'];
				$new_target = (int) $edge['target_concept_id'] === (int) $source['id'] ? (int) $target['id'] : (int) $edge['target_concept_id'];
				if ( $new_source !== $new_target ) {
					$relation_result = self::add_relation( $new_source, $new_target, $edge['relation_type'], $edge['source_reference_id'], $actor_id );
					if ( is_wp_error( $relation_result ) ) { throw new RuntimeException( $relation_result->get_error_message() ); }
				}
				$wpdb->delete( HE_V2_Schema::table( 'relations' ), array( 'id' => (int) $edge['id'] ), array( '%d' ) );
			}
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . HE_V2_Schema::table( 'concepts' ) . " SET status='archived',merged_into_id=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $target['id'], $source['id'], $expected_source_version ) );
			if ( 1 !== (int) $updated ) {
				throw new RuntimeException( 'Merge concurrency conflict.' );
			}
			$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => $source['id'] ), array( '%d' ) );
			$wpdb->query( 'COMMIT' );
			self::emit_event( 'KnowledgeConceptMerged.v1', 'concept', $target['id'], array( 'source_id' => $source['public_id'], 'target_id' => $target['public_id'], 'reason' => sanitize_textarea_field( $reason ) ) );
			self::reindex_concept( $target['id'] );
			return self::concept_by_id( $target['id'], true );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'he_merge_failed', __( 'The concepts could not be merged safely.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
	}

	public static function create_research( $data, $actor_id ) {
		global $wpdb;
		$type = sanitize_key( $data['record_type'] ?? 'proposal' );
		$allowed_types = array( 'proposal', 'protocol', 'publication', 'successful-case', 'dataset' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return new WP_Error( 'he_invalid_research_type', __( 'Invalid research record type.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$title = sanitize_text_field( $data['title'] ?? '' );
		$question = sanitize_textarea_field( $data['question'] ?? '' );
		$protocol = sanitize_textarea_field( $data['protocol'] ?? '' );
		if ( ! $title || ! $question || ! $protocol ) {
			return new WP_Error( 'he_research_required_fields', __( 'Research title, question and protocol are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$consent = ! empty( $data['consent_verified'] );
		$anonymized = ! empty( $data['anonymized'] );
		$case_tag = '';
		if ( 'successful-case' === $type ) {
			if ( ! $consent || ! $anonymized || empty( $data['baseline'] ) || empty( $data['intervention'] ) || empty( $data['follow_up'] ) || empty( $data['limitations'] ) ) {
				return new WP_Error( 'he_case_governance_failed', __( 'A successful case requires consent, anonymization, baseline, intervention, follow-up and limitations.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			$pii = self::contains_direct_identifiers( implode( ' ', array( $title, $question, $protocol, $data['baseline'], $data['intervention'], $data['follow_up'], $data['limitations'] ) ) );
			if ( $pii ) {
				return new WP_Error( 'he_case_pii_detected', __( 'Potential direct personal identifiers were detected in the successful case.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
			$case_tag = 'کامیاب کیس';
		}
		$post_id = wp_insert_post( array(
			'post_type' => self::RESEARCH_TYPE,
			'post_status' => 'draft',
			'post_author' => absint( $actor_id ),
			'post_title' => $title,
			'post_excerpt' => $question,
			'post_content' => $protocol,
		), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( $case_tag ) {
			wp_set_object_terms( $post_id, array( $case_tag ), self::TAX_TOPIC, false );
		}
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', $post_id ) );
		$payload = array(
			'public_id' => wp_generate_uuid4(),
			'post_id' => $post_id,
			'record_type' => $type,
			'status' => 'proposal',
			'title' => $title,
			'question' => $question,
			'protocol' => $protocol,
			'investigators_json' => wp_json_encode( array_values( array_map( 'sanitize_text_field', (array) ( $data['investigators'] ?? array() ) ) ) ),
			'ethics_json' => wp_json_encode( array( 'review_required' => true, 'approval_reference' => sanitize_text_field( $data['ethics_reference'] ?? '' ) ) ),
			'consent_json' => wp_json_encode( array( 'verified' => $consent, 'version' => sanitize_text_field( $data['consent_version'] ?? '' ) ) ),
			'conflicts_json' => wp_json_encode( array_values( array_map( 'sanitize_text_field', (array) ( $data['conflicts'] ?? array() ) ) ) ),
			'data_class' => sanitize_key( $data['data_class'] ?? 'restricted' ),
			'case_anonymized' => $anonymized ? 1 : 0,
			'case_consent_verified' => $consent ? 1 : 0,
			'case_tag' => $case_tag,
			'case_json' => wp_json_encode( array(
				'observation_label' => 'successful-case' === $type ? 'observational successful case' : '',
				'baseline' => sanitize_textarea_field( $data['baseline'] ?? '' ),
				'intervention' => sanitize_textarea_field( $data['intervention'] ?? '' ),
				'follow_up' => sanitize_textarea_field( $data['follow_up'] ?? '' ),
				'adverse_events' => sanitize_textarea_field( $data['adverse_events'] ?? '' ),
				'limitations' => sanitize_textarea_field( $data['limitations'] ?? '' ),
			) ),
			'metadata_json' => wp_json_encode( array(
				'description' => sanitize_textarea_field( $data['dataset_description'] ?? '' ),
				'de_identification' => sanitize_textarea_field( $data['de_identification'] ?? '' ),
				'lawful_basis' => sanitize_key( $data['lawful_basis'] ?? '' ),
				'access_policy' => sanitize_textarea_field( $data['access_policy'] ?? '' ),
			) ),
			'created_by' => absint( $actor_id ),
			'created_at' => current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( $existing_id ) {
			$wpdb->update( HE_V2_Schema::table( 'research' ), $payload, array( 'id' => $existing_id ) );
			$research_id = $existing_id;
		} else {
			$wpdb->insert( HE_V2_Schema::table( 'research' ), $payload );
			$research_id = (int) $wpdb->insert_id;
		}
		if ( ! $research_id ) {
			wp_delete_post( $post_id, true );
			return new WP_Error( 'he_research_write_failed', __( 'Research record could not be saved.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
		}
		self::emit_event( 'ResearchRecordSubmitted.v1', 'research', $research_id, array( 'record_type' => $type ) );
		return self::research_dto( $research_id, true );
	}

	private static function contains_direct_identifiers( $text ) {
		$patterns = array(
			'/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u',
			'/\b(?:\+?92|0)?3\d{9}\b/u',
			'/\b\d{5}-\d{7}-\d\b/u',
			'/\b(?:CNIC|NIC|passport|phone|mobile|address)\s*[:#-]?\s*[A-Za-z0-9-]{5,}\b/ui',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, (string) $text ) ) {
				return true;
			}
		}
		return false;
	}

	public static function research_dto( $research_id, $private = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		if ( ! $private && 'published' !== $row['status'] ) {
			return null;
		}
		$dto = array(
			'id' => $row['public_id'],
			'record_type' => $row['record_type'],
			'status' => $row['status'],
			'title' => $row['title'],
			'question' => $row['question'],
			'protocol' => $row['protocol'],
			'case_tag' => $row['case_tag'],
			'case' => 'successful-case' === $row['record_type'] ? json_decode( $row['case_json'], true ) : null,
			'dataset_metadata' => 'dataset' === $row['record_type'] ? json_decode( $row['metadata_json'], true ) : null,
			'canonical_url' => $row['post_id'] ? get_permalink( (int) $row['post_id'] ) : '',
			'updated_at' => $row['updated_at'],
		);
		if ( ! $private && 'public' !== $row['data_class'] ) {
			$dto['protocol'] = '';
		}
		if ( $private ) {
			$dto['investigators'] = json_decode( $row['investigators_json'], true );
			$dto['ethics'] = json_decode( $row['ethics_json'], true );
			$dto['consent'] = json_decode( $row['consent_json'], true );
			$dto['conflicts'] = json_decode( $row['conflicts_json'], true );
			$dto['data_class'] = $row['data_class'];
			$dto['row_version'] = (int) $row['row_version'];
		}
		return $dto;
	}

	public static function transition_research( $research_id, $to_state, $expected_version, $actor_id, $note = '' ) {
		global $wpdb;
		$to_state = sanitize_key( $to_state );
		if ( ! in_array( $to_state, self::research_states(), true ) ) {
			return new WP_Error( 'he_invalid_state', __( 'Invalid research state.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$table = HE_V2_Schema::table( 'research' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $research_id ) ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$map = array(
			'proposal' => array( 'ethics_review', 'rejected' ),
			'ethics_review' => array( 'approved', 'rejected' ),
			'approved' => array( 'active' ),
			'active' => array( 'analysis' ),
			'analysis' => array( 'peer_review' ),
			'peer_review' => array( 'published', 'active' ),
			'published' => array( 'corrected', 'retracted' ),
			'corrected' => array( 'published', 'retracted' ),
			'rejected' => array(),
			'retracted' => array(),
		);
		if ( ! in_array( $to_state, $map[ $row['status'] ] ?? array(), true ) ) {
			return new WP_Error( 'he_transition_forbidden', __( 'This research transition is not allowed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		if ( in_array( $to_state, array( 'approved', 'active', 'published' ), true ) ) {
			$ethics = json_decode( $row['ethics_json'], true );
			$consent = json_decode( $row['consent_json'], true );
			if ( empty( $ethics['approval_reference'] ) || ( 'successful-case' === $row['record_type'] && empty( $consent['verified'] ) ) ) {
				return new WP_Error( 'he_ethics_gate_failed', __( 'Ethics approval and required consent must be documented.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
			}
		}
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND row_version=%d", $to_state, $row['id'], absint( $expected_version ) ) );
		if ( 1 !== (int) $result ) {
			return new WP_Error( 'he_version_conflict', __( 'The research record changed in another session.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		if ( 'published' === $to_state && $row['post_id'] ) {
			wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
			self::emit_event( 'ResearchPublicationPublished.v1', 'research', $row['id'], array( 'record_type' => $row['record_type'] ) );
		}
		if ( 'retracted' === $to_state ) {
			self::emit_event( 'ResearchRecordRetracted.v1', 'research', $row['id'], array( 'reason' => sanitize_textarea_field( $note ) ) );
		}
		return self::research_dto( $row['id'], true );
	}

	public static function request_dataset_access( $research_id, $purpose, $lawful_basis, $requester_id ) {
		global $wpdb;
		$research = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE id=%d', absint( $research_id ) ), ARRAY_A );
		if ( ! $research || 'dataset' !== $research['record_type'] ) {
			return new WP_Error( 'he_dataset_not_found', __( 'Dataset metadata could not be found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		if ( ! trim( $purpose ) || ! trim( $lawful_basis ) ) {
			return new WP_Error( 'he_dataset_purpose_required', __( 'Purpose and lawful basis are required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$now = current_time( 'mysql', true );
		$result = $wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . HE_V2_Schema::table( 'dataset_access' ) . ' (research_id,requester_id,purpose,lawful_basis,status,created_at,updated_at) VALUES (%d,%d,%s,%s,\'requested\',%s,%s) ON DUPLICATE KEY UPDATE purpose=VALUES(purpose),lawful_basis=VALUES(lawful_basis),status=\'requested\',approved_by=0,expires_at=NULL,updated_at=VALUES(updated_at)',
			absint( $research_id ), absint( $requester_id ), sanitize_textarea_field( $purpose ), sanitize_key( $lawful_basis ), $now, $now
		) );
		return false !== $result;
	}

	public static function approve_dataset_access( $access_id, $expires_at, $actor_id ) {
		global $wpdb;
		$timestamp = strtotime( $expires_at );
		if ( ! $timestamp || $timestamp <= time() || $timestamp > time() + YEAR_IN_SECONDS ) {
			return new WP_Error( 'he_invalid_expiry', __( 'A future access expiry within one year is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$result = $wpdb->update( HE_V2_Schema::table( 'dataset_access' ), array(
			'status' => 'approved',
			'approved_by' => absint( $actor_id ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'updated_at' => current_time( 'mysql', true ),
		), array( 'id' => absint( $access_id ), 'status' => 'requested' ), array( '%s','%d','%s','%s' ), array( '%d','%s' ) );
		return 1 === (int) $result;
	}

	public static function search( $args ) {
		global $wpdb;
		$limit = min( 50, max( 1, absint( $args['limit'] ?? 20 ) ) );
		$cursor = max( 0, absint( $args['cursor'] ?? 0 ) );
		$where = array( "c.status='published'", "c.review_status='approved'", "c.safety_status='approved'", 'c.merged_into_id=0', 'c.id>%d' );
		$params = array( $cursor );
		$term = self::normalize( $args['q'] ?? '' );
		if ( $term ) {
			$words = array_values( array_filter( preg_split( '/\s+/u', $term ) ) );
			foreach ( array_slice( $words, 0, 8 ) as $word ) {
				$where[] = 'i.search_text LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $word ) . '%';
			}
		}
		$filters = array(
			'type' => array( 'i.type_slug=%s', sanitize_key( $args['type'] ?? '' ) ),
			'body_system' => array( 'i.body_system=%s', sanitize_key( $args['body_system'] ?? '' ) ),
			'language' => array( 'i.language=%s', sanitize_text_field( $args['language'] ?? '' ) ),
			'review_status' => array( 'i.review_status=%s', sanitize_key( $args['review_status'] ?? '' ) ),
			'safety_status' => array( 'i.safety_status=%s', sanitize_key( $args['safety_status'] ?? '' ) ),
			'source_grade' => array( 'i.source_grade=%s', sanitize_key( $args['source_grade'] ?? '' ) ),
			'letter' => array( 'i.first_letter=%s', mb_substr( sanitize_text_field( $args['letter'] ?? '' ), 0, 1, 'UTF-8' ) ),
		);
		foreach ( $filters as $filter ) {
			if ( '' !== $filter[1] ) {
				$where[] = $filter[0];
				$params[] = $filter[1];
			}
		}
		$params[] = $limit + 1;
		$sql = 'SELECT c.* FROM ' . HE_V2_Schema::table( 'concepts' ) . ' c INNER JOIN ' . HE_V2_Schema::table( 'search_index' ) . ' i ON i.concept_id=c.id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY c.id ASC LIMIT %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$has_more = count( $rows ) > $limit;
		$rows = array_slice( $rows, 0, $limit );
		$items = array();
		foreach ( $rows as $row ) {
			$dto = self::public_dto( $row );
			if ( $dto ) {
				$items[] = array(
					'id' => $dto['id'], 'title' => $dto['title'], 'summary' => $dto['summary'], 'type' => $dto['type'],
					'body_system' => $dto['body_system'], 'language' => $dto['language'], 'url' => $dto['canonical_url'],
					'version' => $dto['version'], 'safety_status' => $dto['safety_status'], 'updated_at' => $dto['freshness']['updated_at'],
				);
			}
		}
		return array( 'items' => $items, 'next_cursor' => $has_more && $rows ? (int) end( $rows )['id'] : null, 'limit' => $limit );
	}

	public static function autocomplete( $q, $limit = 8 ) {
		global $wpdb;
		$q = self::normalize( $q );
		if ( mb_strlen( $q, 'UTF-8' ) < 2 ) {
			return array();
		}
		$limit = min( 10, max( 1, absint( $limit ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT DISTINCT a.alias,c.public_id,c.post_id FROM ' . HE_V2_Schema::table( 'aliases' ) . ' a INNER JOIN ' . HE_V2_Schema::table( 'concepts' ) . " c ON c.id=a.concept_id WHERE a.normalized_alias LIKE %s AND c.status='published' AND c.review_status='approved' AND c.safety_status='approved' AND c.merged_into_id=0 ORDER BY a.is_primary DESC,a.alias ASC LIMIT %d",
			$q . '%', $limit
		), ARRAY_A );
		return array_map( static function( $row ) {
			return array( 'label' => $row['alias'], 'id' => $row['public_id'], 'url' => get_permalink( (int) $row['post_id'] ) );
		}, $rows );
	}


	public static function versions( $concept_id ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) {
			return array();
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id,version_number,status,title,content_hash,change_reason,effective_at,created_at FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE concept_id=%d ORDER BY version_number DESC LIMIT 100', $row['id'] ), ARRAY_A );
	}

	public static function version_diff( $concept_id, $from, $to ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Concept not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$versions = $wpdb->get_results( $wpdb->prepare( 'SELECT version_number,title,summary,body,structured_json FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE concept_id=%d AND version_number IN (%d,%d)', $row['id'], absint( $from ), absint( $to ) ), OBJECT_K );
		if ( count( $versions ) !== 2 ) {
			return new WP_Error( 'he_versions_missing', __( 'Both versions are required for a diff.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$from = absint( $from );
		$to = absint( $to );
		$a = $versions[ $from ];
		$b = $versions[ $to ];
		return array(
			'from' => absint( $from ),
			'to' => absint( $to ),
			'title_changed' => $a->title !== $b->title,
			'summary_changed' => $a->summary !== $b->summary,
			'body_diff' => function_exists( 'wp_text_diff' ) ? wp_text_diff( $a->body, $b->body, array( 'show_split_view' => true ) ) : array( 'from' => $a->body, 'to' => $b->body ),
			'fields_from' => json_decode( $a->structured_json, true ),
			'fields_to' => json_decode( $b->structured_json, true ),
		);
	}

	public static function find_duplicates( $concept_id = 0, $limit = 50 ) {
		global $wpdb;
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$table = HE_V2_Schema::table( 'aliases' );
		$concepts = HE_V2_Schema::table( 'concepts' );
		$where = $concept_id ? $wpdb->prepare( ' AND a.concept_id=%d', absint( $concept_id ) ) : '';
		$aliases = $wpdb->get_results( "SELECT a.concept_id,a.normalized_alias,c.public_id,c.canonical_slug FROM {$table} a INNER JOIN {$concepts} c ON c.id=a.concept_id WHERE c.merged_into_id=0 {$where} ORDER BY a.id ASC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pairs = array();
		$count = count( $aliases );
		for ( $i = 0; $i < $count; $i++ ) {
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( (int) $aliases[$i]['concept_id'] === (int) $aliases[$j]['concept_id'] ) { continue; }
				$a = $aliases[$i]['normalized_alias']; $b = $aliases[$j]['normalized_alias'];
				if ( ! $a || ! $b ) { continue; }
				$percent = 0.0;
				similar_text( $a, $b, $percent );
				if ( $a === $b || $percent >= 86.0 ) {
					$key = min( $aliases[$i]['concept_id'], $aliases[$j]['concept_id'] ) . ':' . max( $aliases[$i]['concept_id'], $aliases[$j]['concept_id'] );
					$pairs[$key] = array( 'source' => $aliases[$i], 'target' => $aliases[$j], 'similarity' => round( $percent, 2 ) );
					if ( count( $pairs ) >= $limit ) { break 2; }
				}
			}
		}
		return array_values( $pairs );
	}

	public static function set_bookmark( $user_id, $concept_id, $active ) {
		global $wpdb;
		$concept = self::concept_by_id( $concept_id );
		if ( ! $concept ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$table = HE_V2_Schema::table( 'bookmarks' );
		if ( $active ) {
			$result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (user_id,concept_id,created_at) VALUES (%d,%d,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE created_at=created_at", absint( $user_id ), absint( $concept_id ) ) );
			return false !== $result;
		}
		return false !== $wpdb->delete( $table, array( 'user_id' => absint( $user_id ), 'concept_id' => absint( $concept_id ) ), array( '%d','%d' ) );
	}

	public static function is_bookmarked( $user_id, $concept_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'bookmarks' ) . ' WHERE user_id=%d AND concept_id=%d', absint( $user_id ), absint( $concept_id ) ) );
	}

	public static function rate_allow( $key, $limit, $window_seconds ) {
		global $wpdb;
		$table = HE_V2_Schema::table( 'rate_limits' );
		$key = hash( 'sha256', (string) $key );
		$now = current_time( 'mysql', true );
		$expiry = gmdate( 'Y-m-d H:i:s', time() + max( 1, absint( $window_seconds ) ) );
		$write = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (rate_key,window_start,hit_count,expires_at) VALUES (%s,%s,1,%s) ON DUPLICATE KEY UPDATE hit_count=IF(expires_at<=UTC_TIMESTAMP(),1,hit_count+1),window_start=IF(expires_at<=UTC_TIMESTAMP(),VALUES(window_start),window_start),expires_at=IF(expires_at<=UTC_TIMESTAMP(),VALUES(expires_at),expires_at)", $key, $now, $expiry ) );
		if ( false === $write ) {
			HE_V2_Schema::record_runtime_failure( 'rate_limit_write_failed', 'The File 06 rate-limit counter could not be persisted; protected mutations are failing closed.' );
			return false;
		}
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT hit_count FROM {$table} WHERE rate_key=%s", $key ) );
		if ( null === $count || '' !== (string) $wpdb->last_error ) {
			HE_V2_Schema::record_runtime_failure( 'rate_limit_read_failed', 'The File 06 rate-limit counter could not be verified; protected mutations are failing closed.' );
			return false;
		}
		return (int) $count <= max( 1, absint( $limit ) );
	}

	public static function publish_due() {
		global $wpdb;
		$table = HE_V2_Schema::table( 'concepts' );
		$rows = $wpdb->get_results( "SELECT c.* FROM {$table} c INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=c.post_id AND pm.meta_key='_he_scheduled_at' WHERE c.status='scheduled' AND pm.meta_value<=UTC_TIMESTAMP() ORDER BY c.id ASC LIMIT 50", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$version_id = self::snapshot_version( $row['id'], 'Scheduled publication', 'published', 0 );
			if ( ! $version_id ) { continue; }
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='published',review_status='approved',safety_status='approved',current_version=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='scheduled'", $version_id, $row['id'] ) );
			if ( 1 === (int) $updated ) {
				wp_update_post( array( 'ID' => (int) $row['post_id'], 'post_status' => 'publish' ) );
				delete_post_meta( (int) $row['post_id'], '_he_scheduled_at' );
				self::reindex_concept( $row['id'] );
				self::emit_event( 'EncyclopediaEntryPublished.v1', 'concept', $row['id'], array( 'version_id' => $version_id, 'scheduled' => true ) );
			}
		}
		return count( $rows );
	}

	public static function reindex_concept_by_post( $post_id ) {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE post_id=%d', absint( $post_id ) ) );
		if ( $id ) {
			self::reindex_concept( $id );
		}
	}

	public static function reindex_concept( $concept_id ) {
		global $wpdb;
		$row = self::concept_by_id( $concept_id, true );
		if ( ! $row || ! self::is_public_concept( $row ) || ! $row['current_version'] ) {
			$wpdb->delete( HE_V2_Schema::table( 'search_index' ), array( 'concept_id' => absint( $concept_id ) ), array( '%d' ) );
			return false;
		}
		$post = get_post( (int) $row['post_id'] );
		$aliases = $wpdb->get_col( $wpdb->prepare( 'SELECT alias FROM ' . HE_V2_Schema::table( 'aliases' ) . ' WHERE concept_id=%d', $row['id'] ) );
		$references = $wpdb->get_results( $wpdb->prepare( 'SELECT author,title,publisher,doi,evidence_grade FROM ' . HE_V2_Schema::table( 'references' ) . ' WHERE concept_id=%d AND version_id=%d', $row['id'], (int) $row['current_version'] ), ARRAY_A );
		$reference_text = '';
		$best_grade = '';
		foreach ( $references as $reference ) {
			$reference_text .= ' ' . implode( ' ', $reference );
			$best_grade = $best_grade ?: $reference['evidence_grade'];
		}
		$structured = get_post_meta( $post->ID, '_he_structured', true );
		$text = self::normalize( implode( ' ', array( $post->post_title, $post->post_excerpt, wp_strip_all_tags( $post->post_content ), wp_json_encode( $structured ), implode( ' ', $aliases ), $reference_text ) ) );
		$first = mb_strtoupper( mb_substr( self::normalize( $post->post_title ), 0, 1, 'UTF-8' ), 'UTF-8' );
		$now = current_time( 'mysql', true );
		$sql = $wpdb->prepare(
			'INSERT INTO ' . HE_V2_Schema::table( 'search_index' ) . ' (concept_id,first_letter,type_slug,body_system,language,source_grade,review_status,safety_status,search_text,updated_at) VALUES (%d,%s,%s,%s,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE first_letter=VALUES(first_letter),type_slug=VALUES(type_slug),body_system=VALUES(body_system),language=VALUES(language),source_grade=VALUES(source_grade),review_status=VALUES(review_status),safety_status=VALUES(safety_status),search_text=VALUES(search_text),updated_at=VALUES(updated_at)',
			$row['id'], $first, $row['type_slug'], self::taxonomy_slug( $post->ID, self::TAX_SYSTEM ), $row['language'], $best_grade, $row['review_status'], $row['safety_status'], $text, $now
		);
		return false !== $wpdb->query( $sql );
	}

	public static function reindex_all() {
		global $wpdb;
		$ids = $wpdb->get_col( 'SELECT id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' ORDER BY id ASC' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( $ids as $id ) {
			self::reindex_concept( (int) $id );
		}
		return count( $ids );
	}

	public static function emit_event( $name, $object_type, $object_id, $payload ) {
		global $wpdb;
		$event_id = wp_generate_uuid4();
		$trace_id = self::trace_id();
		$payload = is_array( $payload ) ? $payload : array();
		$payload['owner'] = 'file-06';
		$payload['contract_version'] = HE_CONTRACT_VERSION;
		$payload['occurred_at'] = gmdate( 'c' );
		$json = wp_json_encode( $payload );
		$now = current_time( 'mysql', true );
		/* Internal File 06 transaction call-site audit confirms emit_event() is invoked after owning transactions commit. Own only the event/outbox pair here and avoid vendor-specific transaction-state variables. */
		$started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $started ) {
			HE_V2_Schema::record_runtime_failure( 'event_outbox_transaction_start_failed', 'File 06 could not start the domain-event/outbox atomic transaction.' );
			throw new RuntimeException( 'File 06 event/outbox transaction could not start.' );
		}
		$event_ok = $wpdb->insert( HE_V2_Schema::table( 'events' ), array(
			'event_id' => $event_id, 'event_name' => sanitize_text_field( $name ), 'object_type' => sanitize_key( $object_type ), 'object_id' => absint( $object_id ),
			'actor_id' => get_current_user_id(), 'trace_id' => $trace_id, 'payload_json' => $json, 'created_at' => $now,
		), array( '%s','%s','%s','%d','%d','%s','%s','%s' ) );
		$outbox_ok = $wpdb->insert( HE_V2_Schema::table( 'outbox' ), array(
			'event_id' => $event_id, 'event_name' => sanitize_text_field( $name ), 'payload_json' => $json, 'status' => 'pending', 'attempts' => 0,
			'next_attempt_at' => $now, 'last_error' => '', 'created_at' => $now, 'updated_at' => $now,
		), array( '%s','%s','%s','%s','%d','%s','%s','%s','%s' ) );
		if ( ! $event_ok || ! $outbox_ok ) {
			$wpdb->query( 'ROLLBACK' );
			HE_V2_Schema::record_runtime_failure( 'event_outbox_atomic_write_failed', 'A File 06 domain event and its outbox record could not be persisted as one atomic unit.' );
			throw new RuntimeException( 'File 06 event/outbox atomic persistence failed.' );
		}
		$committed = $wpdb->query( 'COMMIT' );
		if ( false === $committed ) {
			$wpdb->query( 'ROLLBACK' );
			HE_V2_Schema::record_runtime_failure( 'event_outbox_commit_failed', 'File 06 could not commit the domain-event/outbox atomic transaction.' );
			throw new RuntimeException( 'File 06 event/outbox transaction could not commit.' );
		}
		do_action( 'he_v2_event', $name, $payload, $event_id, $trace_id );
		return array( 'event_id' => $event_id, 'trace_id' => $trace_id );
	}

	private static function canonicalize_idempotency_value( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		$keys = array_keys( $value );
		$is_list = empty( $keys ) || $keys === range( 0, count( $keys ) - 1 );
		if ( ! $is_list ) { ksort( $value, SORT_STRING ); }
		foreach ( $value as $key => $item ) { $value[ $key ] = self::canonicalize_idempotency_value( $item ); }
		return $value;
	}

	public static function idempotent_begin( $actor_id, $operation, $key, $request_body ) {
		global $wpdb;
		$key = sanitize_text_field( $key );
		if ( ! $key || strlen( $key ) < 8 || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		$request_hash = hash( 'sha256', wp_json_encode( self::canonicalize_idempotency_value( $request_body ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$table = HE_V2_Schema::table( 'idempotency' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_id=%d AND operation=%s AND idempotency_key=%s", absint( $actor_id ), sanitize_key( $operation ), $key ), ARRAY_A );
		if ( $row ) {
			if ( ! hash_equals( $row['request_hash'], $request_hash ) ) {
				return new WP_Error( 'he_idempotency_conflict', __( 'This idempotency key was already used for a different request.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
			}
			if ( $row['response_code'] ) {
				return array( 'replay' => true, 'code' => (int) $row['response_code'], 'body' => json_decode( $row['response_json'], true ) );
			}
			$now = current_time( 'mysql', true );
			$expiry = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
			$reclaimed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET created_at=%s,expires_at=%s WHERE id=%d AND response_code=0 AND request_hash=%s AND created_at=%s AND created_at<=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)",
				$now, $expiry, (int) $row['id'], $request_hash, (string) $row['created_at']
			) );
			if ( 1 === (int) $reclaimed ) {
				self::$idempotency_leases[ (int) $row['id'] ] = $now;
				return array( 'replay' => false, 'id' => (int) $row['id'], 'reclaimed' => true );
			}
			return new WP_Error( 'he_request_in_progress', __( 'An identical request is still being processed.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		}
		$created_at = current_time( 'mysql', true );
		$ok = $wpdb->insert( $table, array(
			'actor_id' => absint( $actor_id ), 'operation' => sanitize_key( $operation ), 'idempotency_key' => $key, 'request_hash' => $request_hash,
			'response_code' => 0, 'response_json' => '', 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'created_at' => $created_at,
		), array( '%d','%s','%s','%s','%d','%s','%s','%s' ) );
		if ( ! $ok ) {
			return new WP_Error( 'he_idempotency_write_failed', __( 'The request could not be reserved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 500 ) );
		}
		$id = (int) $wpdb->insert_id;
		self::$idempotency_leases[ $id ] = $created_at;
		return array( 'replay' => false, 'id' => $id );
	}

	public static function idempotent_finish( $id, $code, $body ) {
		global $wpdb;
		$id = absint( $id );
		$lease = isset( self::$idempotency_leases[ $id ] ) ? (string) self::$idempotency_leases[ $id ] : '';
		if ( ! $id || ! $lease ) { return false; }
		$updated = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . HE_V2_Schema::table( 'idempotency' ) . ' SET response_code=%d,response_json=%s WHERE id=%d AND response_code=0 AND created_at=%s',
			absint( $code ), wp_json_encode( $body ), $id, $lease
		) );
		unset( self::$idempotency_leases[ $id ] );
		if ( false === $updated ) {
			HE_V2_Schema::record_runtime_failure( 'idempotency_finish_failed', 'The reserved File 06 response could not be persisted.' );
			return false;
		}
		if ( 1 !== (int) $updated ) {
			HE_V2_Schema::record_runtime_failure( 'idempotency_finish_stale_lease', 'A File 06 idempotency reservation was reclaimed or changed before its original worker could finalize the response.' );
			return false;
		}
		return true;
	}

	public static function maintenance() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'idempotency' ) . ' WHERE expires_at<%s LIMIT 1000', current_time( 'mysql', true ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . HE_V2_Schema::table( 'rate_limits' ) . ' WHERE expires_at<%s LIMIT 1000', current_time( 'mysql', true ) ) );
		self::publish_due();
		$wpdb->query( $wpdb->prepare( "UPDATE " . HE_V2_Schema::table( 'dataset_access' ) . " SET status='expired',updated_at=%s WHERE status='approved' AND expires_at IS NOT NULL AND expires_at<%s", current_time( 'mysql', true ), current_time( 'mysql', true ) ) );
		HE_V2_Integrations::process_outbox( 50 );
	}
}

<?php
/** Content types and governed classification vocabularies. */

defined( 'ABSPATH' ) || exit;

final class HE_Content {
	const TYPE = 'he_entry';
	const TAX = 'he_type';
	const SYSTEM = 'he_body_system';

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

	public static function fields() {
		return array(
			'source'     => __( 'Source or Classification', 'homeopathy-encyclopedia' ),
			'key_points' => __( 'Key Educational Points', 'homeopathy-encyclopedia' ),
			'symptoms'   => __( 'Symptoms or Characteristics', 'homeopathy-encyclopedia' ),
			'causes'     => __( 'Causes and Etiology', 'homeopathy-encyclopedia' ),
			'modalities' => __( 'Modalities', 'homeopathy-encyclopedia' ),
			'red_flags'  => __( 'Medical Red Flags', 'homeopathy-encyclopedia' ),
			'safety'     => __( 'Safety and Limitations', 'homeopathy-encyclopedia' ),
			'references' => __( 'References', 'homeopathy-encyclopedia' ),
		);
	}

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'           => array( 'name' => __( 'Encyclopedia Entries', 'homeopathy-encyclopedia' ), 'singular_name' => __( 'Encyclopedia Entry', 'homeopathy-encyclopedia' ) ),
				'public'           => true,
				'show_ui'          => true,
				'show_in_menu'     => false,
				'show_in_rest'     => false,
				'has_archive'      => 'encyclopedia-entries',
				'rewrite'          => array( 'slug' => 'encyclopedia-entry', 'with_front' => false ),
				'supports'         => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'comments', 'revisions' ),
				'taxonomies'       => array( self::TAX, self::SYSTEM ),
				'capability_type'  => array( 'he_entry', 'he_entries' ),
				'capabilities'     => HE_Permissions::post_type_caps(),
				'map_meta_cap'     => true,
				'delete_with_user' => false,
			)
		);

		register_taxonomy( self::TAX, array( self::TYPE ), array( 'labels' => array( 'name' => __( 'Knowledge Types', 'homeopathy-encyclopedia' ) ), 'public' => true, 'show_ui' => false, 'show_in_rest' => false, 'hierarchical' => true, 'rewrite' => array( 'slug' => 'encyclopedia-type' ) ) );
		register_taxonomy( self::SYSTEM, array( self::TYPE ), array( 'labels' => array( 'name' => __( 'Body Systems', 'homeopathy-encyclopedia' ) ), 'public' => true, 'show_ui' => false, 'show_in_rest' => false, 'hierarchical' => true, 'rewrite' => array( 'slug' => 'body-system' ) ) );
	}

	/** Seed controlled terms and fail explicitly when WordPress rejects one. */
	public static function seed_terms() {
		foreach ( array( self::TAX => self::types(), self::SYSTEM => self::systems() ) as $taxonomy => $items ) {
			foreach ( $items as $slug => $name ) {
				if ( ! get_term_by( 'slug', $slug, $taxonomy ) ) {
					$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
					if ( is_wp_error( $result ) ) {
						throw new RuntimeException( $result->get_error_message() );
					}
				}
			}
		}
	}

	public static function allowed( $slug, $taxonomy = self::TAX ) {
		$items = self::SYSTEM === $taxonomy ? self::systems() : self::types();
		return isset( $items[ sanitize_title( $slug ) ] );
	}

	public static function assign( $post_id, $slug, $taxonomy = self::TAX ) {
		if ( ! self::allowed( $slug, $taxonomy ) ) {
			return false;
		}
		self::seed_terms();
		$term = get_term_by( 'slug', $slug, $taxonomy );
		return $term && ! is_wp_error( wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy, false ) );
	}

	public static function term( $post_id, $taxonomy = self::TAX, $field = 'name' ) {
		$terms = get_the_terms( absint( $post_id ), $taxonomy );
		return $terms && ! is_wp_error( $terms ) && isset( $terms[0]->{$field} ) ? $terms[0]->{$field} : '';
	}

	public static function meta( $post_id, $key ) {
		return get_post_meta( absint( $post_id ), '_he_' . sanitize_key( $key ), true );
	}

	/** Whether the entry satisfies public-governance invariants. */
	public static function publicly_available( $entry_id ) {
		$entry_id = absint( $entry_id );
		if ( self::TYPE !== get_post_type( $entry_id ) || 'publish' !== get_post_status( $entry_id ) ) {
			return false;
		}
		$type = self::term( $entry_id, self::TAX, 'slug' );
		$system = self::term( $entry_id, self::SYSTEM, 'slug' );
		return self::allowed( $type, self::TAX )
			&& self::allowed( $system, self::SYSTEM )
			&& 'published' === self::meta( $entry_id, 'workflow_state' )
			&& 'en-US' === self::meta( $entry_id, 'language' )
			&& (bool) self::meta( $entry_id, 'language_reviewed' )
			&& trim( (string) self::meta( $entry_id, 'references' ) );
	}

	/** Validate public-release fields and relationships. */
	public static function publication_error( $entry_id ) {
		$entry_id = absint( $entry_id );
		$type = self::term( $entry_id, self::TAX, 'slug' );
		$system = self::term( $entry_id, self::SYSTEM, 'slug' );
		if ( ! self::allowed( $type, self::TAX ) || ! self::allowed( $system, self::SYSTEM ) ) {
			return __( 'Approved knowledge type or body system is missing.', 'homeopathy-encyclopedia' );
		}
		if ( ! trim( (string) get_post_field( 'post_excerpt', $entry_id ) ) || ! trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $entry_id ) ) ) || ! trim( (string) self::meta( $entry_id, 'references' ) ) ) {
			return __( 'A summary, complete entry, and references are required.', 'homeopathy-encyclopedia' );
		}
		if ( 'en-US' !== self::meta( $entry_id, 'language' ) ) {
			return __( 'The American English language declaration is missing.', 'homeopathy-encyclopedia' );
		}
		if ( in_array( $type, array( 'health-condition', 'pathology' ), true ) && ! trim( (string) self::meta( $entry_id, 'red_flags' ) ) ) {
			return __( 'Medical red flags are required for this knowledge type.', 'homeopathy-encyclopedia' );
		}
		if ( 'remedy' === $type && ! trim( (string) self::meta( $entry_id, 'safety' ) ) ) {
			return __( 'Safety and limitations are required for remedy entries.', 'homeopathy-encyclopedia' );
		}
		foreach ( array_map( 'absint', (array) self::meta( $entry_id, 'related_ids' ) ) as $related_id ) {
			if ( $related_id && ! self::publicly_available( $related_id ) ) {
				return __( 'A related encyclopedia entry is not publicly available.', 'homeopathy-encyclopedia' );
			}
		}
		foreach ( array( 'book_id' => 'slc_book', 'lesson_id' => 'slc_lesson' ) as $key => $post_type ) {
			$linked = absint( self::meta( $entry_id, $key ) );
			if ( $linked && ! self::learning_item_public( $linked, $post_type ) ) {
				return __( 'A connected learning item is not publicly available.', 'homeopathy-encyclopedia' );
			}
		}
		return '';
	}

	/** Verify a connected File 05 item is actually public. */
	public static function learning_item_public( $post_id, $post_type ) {
		$post_id = absint( $post_id );
		if ( $post_type !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		if ( 'slc_lesson' === $post_type && class_exists( 'SLC_Database' ) && is_callable( array( 'SLC_Database', 'lesson_publicly_available' ) ) ) {
			return SLC_Database::lesson_publicly_available( $post_id );
		}
		return true;
	}
}

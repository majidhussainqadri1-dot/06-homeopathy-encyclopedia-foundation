<?php
/** Canonical structured data for public governed entries. */

defined( 'ABSPATH' ) || exit;

final class HE_SEO {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'schema' ), 20 );
		add_action( 'wp_head', array( $this, 'canonical' ), 9 );
	}

	public function canonical() {
		if ( is_singular( HE_Content::TYPE ) && HE_Content::publicly_available( get_queried_object_id() ) ) {
			echo '<link rel="canonical" href="' . esc_url( get_permalink( get_queried_object_id() ) ) . '">';
		}
	}

	public function schema() {
		if ( ! is_singular( HE_Content::TYPE ) ) {
			return;
		}
		$entry_id = get_queried_object_id();
		if ( ! HE_Content::publicly_available( $entry_id ) ) {
			return;
		}
		$type = HE_Content::term( $entry_id, HE_Content::TAX, 'slug' );
		$author = absint( get_post_field( 'post_author', $entry_id ) );
		$reviewer = absint( HE_Content::meta( $entry_id, 'reviewer_id' ) );
		$medical = in_array( $type, array( 'health-condition', 'pathology', 'anatomy', 'body-system', 'symptom' ), true );
		$data = array(
			'@context' => 'https://schema.org',
			'@type' => $medical ? 'MedicalWebPage' : 'Article',
			'name' => get_the_title( $entry_id ),
			'headline' => get_the_title( $entry_id ),
			'description' => wp_strip_all_tags( get_the_excerpt( $entry_id ) ),
			'inLanguage' => 'en-US',
			'datePublished' => get_the_date( DATE_W3C, $entry_id ),
			'dateModified' => get_the_modified_date( DATE_W3C, $entry_id ),
			'url' => get_permalink( $entry_id ),
			'mainEntityOfPage' => get_permalink( $entry_id ),
			'about' => array( '@type' => 'DefinedTerm', 'name' => get_the_title( $entry_id ), 'description' => wp_strip_all_tags( get_the_excerpt( $entry_id ) ), 'inDefinedTermSet' => home_url( '/homeopathy-encyclopedia/' ) ),
			'author' => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $author ), 'url' => HE_Permissions::profile_url( $author ) ),
			'publisher' => array( '@type' => 'Organization', 'name' => 'Sabri Social Homeopathy Platform', 'url' => home_url( '/' ) ),
		);
		if ( $reviewer && get_userdata( $reviewer ) ) {
			$data['reviewedBy'] = array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $reviewer ) );
			$data['lastReviewed'] = HE_Content::meta( $entry_id, 'reviewed_at' ) ? mysql2date( 'Y-m-d', HE_Content::meta( $entry_id, 'reviewed_at' ), false ) : get_the_modified_date( 'Y-m-d', $entry_id );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	}
}

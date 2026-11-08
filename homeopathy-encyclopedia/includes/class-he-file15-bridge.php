<?php
/**
 * File 06 -> File 15: read-only canonical remedy DTOs and durable v1 events.
 *
 * Source candidate only; this bridge does not assert installed provider parity.
 */
defined( 'ABSPATH' ) || exit;

final class HE_File15_Bridge {
	public static function hooks() {
		add_filter( 'rsr_file06_get_remedies', array( __CLASS__, 'remedies' ), 10, 3 );
		// Only the persisted outbox path delivers to File 15. Never mint a second event ID.
		add_action( 'he_v2_outbox_event', array( __CLASS__, 'deliver' ), 20, 3 );
	}

	/**
	 * File 15 v1 consumes only current, source-backed, independently reviewed remedies.
	 * No aliases, numeric internal IDs, merged IDs, historical versions or private records.
	 */
	public static function remedies( $previous, $ids, $contract = '' ) {
		if ( 'v1' !== $contract || 'active' !== HE_V2_Schema::runtime_status() || ! is_array( $ids ) ) {
			return array();
		}
		global $wpdb;
		$out = array();
		$ids = array_slice( array_unique( array_map( 'strval', $ids ) ), 0, 50 );
		foreach ( $ids as $id ) {
			if ( ! self::valid_public_id( $id ) ) { continue; }
			$row = HE_V2_Domain::concept_by_id( $id, true );
			if ( ! is_array( $row ) || ! hash_equals( (string) $row['public_id'], $id )
				|| 'remedy' !== $row['type_slug'] || 'published' !== $row['status']
				|| 'approved' !== $row['review_status'] || 'approved' !== $row['safety_status']
				|| ! empty( $row['merged_into_id'] ) || (int) $row['current_version'] < 1
				|| ! HE_V2_Domain::is_public_concept( $row ) ) {
				continue;
			}
			$post = get_post( (int) $row['post_id'] );
			if ( ! $post || HE_V2_Domain::ENTRY_TYPE !== $post->post_type ) { continue; }
			// A review of an older snapshot does not authorize the current remedy.
			$hash = HE_V22_Governance::entry_content_hash( $row );
			if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) { continue; }
			$review = $wpdb->get_row( $wpdb->prepare(
				'SELECT created_at FROM ' . HE_V2_Schema::table( 'reviews' ) .
				" WHERE object_type='concept' AND object_id=%d AND decision='approved' AND conflict_declared=0 AND content_hash=%s AND reviewer_id<>%d ORDER BY id DESC LIMIT 1",
				(int) $row['id'], $hash, (int) $post->post_author
			), ARRAY_A );
			if ( ! is_array( $review ) || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}/', (string) ( $review['created_at'] ?? '' ) ) ) { continue; }
			$dto = HE_V2_Domain::public_dto( $row );
			if ( ! is_array( $dto ) || $dto['id'] !== $id || 'remedy' !== $dto['type']
				|| 'published' !== $dto['record_status'] || ! empty( $dto['is_historical'] )
				|| (int) $dto['version'] < 1 || (int) $dto['current_version'] !== (int) $dto['version']
				|| ! is_array( $dto['references'] ) || ! count( $dto['references'] ) ) { continue; }
			$out[ $id ] = array(
				'public_id' => $id,
				'published' => true,
				'eligible' => true,
				'title' => sanitize_text_field( (string) $dto['title'] ),
				'url' => esc_url_raw( (string) $dto['canonical_url'] ),
				'version' => (string) $dto['version'],
				'review_date' => substr( (string) $review['created_at'], 0, 10 ),
				'summary' => wp_kses_post( (string) $dto['summary'] ),
				'contract_version' => 'v1',
				'source_version' => HE_CONTRACT_VERSION,
			);
		}
		return $out;
	}

	private static function valid_public_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id );
	}

	/**
	 * Deliver only canonical File 06 remedy facts, reconstructing identity from
	 * the persisted source event; neither WordPress post IDs nor caller-supplied
	 * untrusted payload can authorize a medical mapping.
	 */
	public static function deliver( $name, $payload, $event_id ) {
		$names = array(
			'EncyclopediaEntryPublished.v1',
			'EncyclopediaEntryCorrected.v1',
			'EncyclopediaEntryRetracted.v1',
			'KnowledgeConceptMerged.v1',
		);
		if ( ! in_array( $name, $names, true ) ) { return; }
		// A missing File 15 has no local consumer to notify. New installations
		// must reconcile existing mappings in staging before enabling comparisons.
		if ( ! function_exists( 'rsr_accept_platform_event' ) ) {
			if ( class_exists( 'RSR_Plugin' ) ) { throw new RuntimeException( 'file15-contract-unavailable' ); }
			return;
		}
		if ( 'active' !== HE_V2_Schema::runtime_status() ) { throw new RuntimeException( 'file06-schema-not-ready' ); }
		if ( ! self::valid_public_id( $event_id ) ) { throw new RuntimeException( 'file06-event-id-invalid' ); }
		global $wpdb;
		$event = $wpdb->get_row( $wpdb->prepare(
			'SELECT object_type,object_id FROM ' . HE_V2_Schema::table( 'events' ) . ' WHERE event_id=%s AND event_name=%s LIMIT 1',
			$event_id, $name
		), ARRAY_A );
		if ( ! is_array( $event ) || 'concept' !== $event['object_type'] || (int) $event['object_id'] < 1 ) {
			throw new RuntimeException( 'file06-event-provenance-unavailable' );
		}
		$concepts = HE_V2_Schema::table( 'concepts' );
		$target = $wpdb->get_row( $wpdb->prepare(
			"SELECT id,public_id,type_slug FROM {$concepts} WHERE id=%d LIMIT 1",
			(int) $event['object_id']
		), ARRAY_A );
		if ( ! is_array( $target ) || 'remedy' !== $target['type_slug'] || ! self::valid_public_id( $target['public_id'] ) ) { return; }
		$out = array( 'event_id' => $event_id, 'contract_version' => 'v1', 'source_owner' => 'file-06' );
		if ( 'KnowledgeConceptMerged.v1' === $name ) {
			$source_id = is_array( $payload ) ? (string) ( $payload['source_id'] ?? '' ) : '';
			$target_id = is_array( $payload ) ? (string) ( $payload['target_id'] ?? '' ) : '';
			if ( ! self::valid_public_id( $source_id ) || $target_id !== $target['public_id'] || $source_id === $target_id ) {
				throw new RuntimeException( 'file06-merge-identity-invalid' );
			}
			$source = $wpdb->get_row( $wpdb->prepare(
				"SELECT public_id,type_slug FROM {$concepts} WHERE public_id=%s LIMIT 1", $source_id
			), ARRAY_A );
			if ( ! is_array( $source ) || 'remedy' !== $source['type_slug'] ) {
				throw new RuntimeException( 'file06-merge-source-invalid' );
			}
			$out['source_remedy_public_id'] = $source_id;
			$out['target_remedy_public_id'] = $target_id;
		} else {
			$out['remedy_public_id'] = (string) $target['public_id'];
			$out['entry_public_id'] = (string) $target['public_id'];
		}
		$result = rsr_accept_platform_event( $name, $out );
		if ( is_wp_error( $result ) || true !== $result ) {
			$code = is_wp_error( $result ) ? $result->get_error_code() : 'not-acknowledged';
			throw new RuntimeException( 'file15-event-delivery-' . sanitize_key( $code ) );
		}
	}
}

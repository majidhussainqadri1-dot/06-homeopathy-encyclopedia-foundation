<?php
/** File 06: reviewer-only revalidation of claims linked to withdrawn literature. */
defined( 'ABSPATH' ) || exit;

final class HE_V12_Evidence_Impact {
	/**
	 * Never automatically reverse a claim's truth or republish evidence.
	 * Revoke its previous independent review; force a fresh human decision.
	 */
	public static function revalidate_crossref( $external_record, $signals ) {
		if ( ! is_array( $external_record ) || 'crossref' !== ( $external_record['provider'] ?? '' ) || empty( $signals ) ) { return true; }
		if ( ! HE_V24_Migration_Safety::ready() || 'active' !== HE_V2_Schema::runtime_status() ) {
			return new WP_Error( 'he_v12_evidence_schema_not_ready', 'Canonical evidence schema is unavailable.', array( 'status' => 503 ) );
		}
		global $wpdb;
		$doi = sanitize_text_field( (string) ( $external_record['external_id'] ?? '' ) );
		if ( '' === $doi ) { return new WP_Error( 'he_v12_evidence_doi_missing', 'The external record must have a governed DOI.', array( 'status' => 422 ) ); }
		$token = 'crossref|' . rawurlencode( $doi );
		$claims = HE_V24_Future_Schema::table( 'claims' );
		$evidence = HE_V24_Future_Schema::table( 'claim_evidence' );
		$references = HE_V2_Schema::table( 'references' );
		$concepts = HE_V2_Schema::table( 'concepts' );
		// Only linked/current claims, never all claims of the same medical subject.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT c.id,c.concept_id,c.version_id,c.review_status,c.row_version,k.public_id FROM {$claims} c
			INNER JOIN {$concepts} k ON k.id=c.concept_id AND k.current_version=c.version_id
			INNER JOIN {$evidence} e ON e.claim_id=c.id
			LEFT JOIN {$references} r ON r.id=e.reference_id AND r.concept_id=c.concept_id AND r.version_id=c.version_id
			WHERE (e.external_id=%s OR (r.doi=%s AND e.reference_id>0))
			AND k.status='published' AND k.merged_into_id=0
			ORDER BY c.id ASC LIMIT 101",
			$token, $doi
		), ARRAY_A );
		if ( ! is_array( $rows ) || count( $rows ) > 100 ) {
			HE_V2_Schema::record_runtime_failure( 'v12_evidence_impact_unbounded', 'Source withdrawal impacts too many claims for a safe bounded batch; manual intervention is required.' );
			return new WP_Error( 'he_v12_evidence_impact_unbounded', 'The evidence revalidation needs staged batches and owner review.', array( 'status' => 503 ) );
		}
		$signatures = array_values( array_intersect( array( 'retraction','correction','expression-of-concern' ), array_map( 'sanitize_key', (array) $signals ) ) );
		foreach ( $rows as $claim ) {
			// Pending/rejected claims need no automatic change.
			if ( 'approved' !== $claim['review_status'] ) { continue; }
			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE {$claims} SET review_status='pending',evidence_state='revalidation-required',reviewed_by=0,row_version=row_version+1,updated_at=UTC_TIMESTAMP()
				WHERE id=%d AND row_version=%d AND review_status='approved'",
				(int) $claim['id'], (int) $claim['row_version']
			) );
			if ( 1 !== (int) $updated ) {
				HE_V2_Schema::record_runtime_failure( 'v12_claim_revalidation_write_failed', 'The canonical claim review could not be invalidated after a source retraction signal.' );
				return new WP_Error( 'he_v12_claim_revalidation_write_failed', 'Claim review revalidation could not be persisted.', array( 'status' => 503 ) );
			}
			$hash = HE_V24_Future_Schema::append_provenance(
				'claim', (string) $claim['id'], 'external-source-revalidation-required', '',
				array( 'external_record_id' => (int) $external_record['id'], 'provider' => 'crossref',
					'external_id' => $doi, 'signals' => $signatures, 'review_status' => 'pending' )
			);
			if ( ! $hash ) {
				HE_V2_Schema::record_runtime_failure( 'v12_claim_revalidation_provenance_failed', 'The claim revalidation provenance ledger could not be written.' );
				return new WP_Error( 'he_v12_claim_revalidation_provenance_failed', 'Claim revalidation provenance was not persisted.', array( 'status' => 503 ) );
			}
			$impact = HE_V24_Future_Schema::queue_impact(
				'concept', (string) $claim['public_id'], 'KnowledgeEvidenceChanged.v1',
				array( 'concept_id' => (string) $claim['public_id'], 'claim_version_id' => (int) $claim['version_id'],
					'signals' => $signatures, 'requires_human_review' => true )
			);
			if ( (int) $impact < 6 ) {
				HE_V2_Schema::record_runtime_failure( 'v12_claim_revalidation_queue_incomplete', 'Not all owner-consumer evidence revalidations were durably queued.' );
				return new WP_Error( 'he_v12_claim_revalidation_queue_incomplete', 'Consumer revalidation is incomplete.', array( 'status' => 503 ) );
			}
		}
		return true;
	}
}

<?php
/**
 * File 06 v12: independently reviewed, exactly source-linked claim invalidation.
 *
 * Preserve old reviews/provenance. A source warning never changes a claim's
 * medical truth automatically; it revokes only eligibility for publication of
 * the current approved claim pending human re-review.
 */
defined( 'ABSPATH' ) || exit;

final class HE_V12_Evidence_Impact {
	const MAX_CLAIMS_PER_SCAN = 40;

	public static function revalidate_crossref( $external_record, $signals ) {
		if ( ! is_array( $external_record ) || 'crossref' !== ( $external_record['provider'] ?? '' ) || ! $signals ) { return true; }
		if ( ! HE_V24_Migration_Safety::ready() || 'active' !== HE_V2_Schema::runtime_status() ) {
			return new WP_Error( 'he_v12_evidence_schema_not_ready', 'Canonical evidence schema is unavailable.', array( 'status' => 503 ) );
		}
		global $wpdb;
		$doi = sanitize_text_field( (string) ( $external_record['external_id'] ?? '' ) );
		if ( ! $doi || empty( $external_record['id'] ) ) {
			return new WP_Error( 'he_v12_evidence_doi_missing', 'A canonical external record and DOI are required.', array( 'status' => 422 ) );
		}
		$token = 'crossref|' . rawurlencode( $doi );
		$claims = HE_V24_Future_Schema::table( 'claims' );
		$evidence = HE_V24_Future_Schema::table( 'claim_evidence' );
		$references = HE_V2_Schema::table( 'references' );
		$concepts = HE_V2_Schema::table( 'concepts' );
		// Query only approved current claims. Committed batches disappear from this
		// queue and the next scan resumes on the remaining approved claims.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT c.id,c.concept_id,c.version_id,c.review_status,c.row_version,k.public_id FROM {$claims} c
			INNER JOIN {$concepts} k ON k.id=c.concept_id AND k.current_version=c.version_id
			INNER JOIN {$evidence} e ON e.claim_id=c.id
			LEFT JOIN {$references} r ON r.id=e.reference_id AND r.concept_id=c.concept_id AND r.version_id=c.version_id
			WHERE (e.external_id=%s OR (r.doi=%s AND e.reference_id>0))
			AND k.status='published' AND k.merged_into_id=0 AND c.review_status='approved'
			ORDER BY c.id ASC LIMIT %d",
			$token, $doi, self::MAX_CLAIMS_PER_SCAN + 1
		), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			HE_V2_Schema::record_runtime_failure( 'v12_evidence_impact_lookup_failed', 'The evidence lookup returned no reliable row collection.' );
			return new WP_Error( 'he_v12_evidence_impact_lookup_failed', 'The claim-impact lookup failed.', array( 'status' => 503 ) );
		}
		$has_more = count( $rows ) > self::MAX_CLAIMS_PER_SCAN;
		$rows = array_slice( $rows, 0, self::MAX_CLAIMS_PER_SCAN );
		$signatures = array_values( array_unique( array_intersect( array( 'retraction','correction','expression-of-concern' ), array_map( 'sanitize_key', (array) $signals ) ) ) );
		if ( ! $signatures ) { return true; }
		foreach ( $rows as $claim ) {
			// The claim update, content-review revocation, immutable provenance and
			// dependent consumer queue must COMMIT together; failure rolls back all.
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return new WP_Error( 'he_v12_evidence_transaction_failed', 'The claim-review transaction could not start.', array( 'status' => 503 ) );
			}
			try {
				$updated = $wpdb->query( $wpdb->prepare(
					"UPDATE {$claims} SET review_status='pending',evidence_state='revalidation-required',
					reviewed_by=0,row_version=row_version+1,updated_at=UTC_TIMESTAMP()
					WHERE id=%d AND row_version=%d AND review_status='approved'",
					(int) $claim['id'], (int) $claim['row_version']
				) );
				if ( 1 !== (int) $updated ) { throw new RuntimeException( 'v12_claim_revalidation_cas_failed' ); }
				$hash = HE_V24_Future_Schema::append_provenance(
					'claim', (string) $claim['id'], 'external-source-revalidation-required', '',
					array( 'external_record_id' => (int) $external_record['id'], 'provider' => 'crossref',
						'external_id' => $doi, 'signals' => $signatures, 'review_status' => 'pending' )
				);
				if ( ! $hash ) { throw new RuntimeException( 'v12_claim_revalidation_provenance_failed' ); }
				$payload = array( 'concept_id' => (string) $claim['public_id'],
					'claim_version_id' => (int) $claim['version_id'], 'signals' => $signatures,
					'requires_human_review' => true );
				$impact = HE_V24_Future_Schema::queue_impact(
					'concept', (string) $claim['public_id'], 'KnowledgeEvidenceChanged.v1', $payload, array(), false
				);
				if ( 6 !== (int) $impact ) { throw new RuntimeException( 'v12_claim_revalidation_queue_incomplete' ); }
				if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'v12_claim_revalidation_commit_failed' ); }
			} catch ( Throwable $error ) {
				$wpdb->query( 'ROLLBACK' );
				HE_V2_Schema::record_runtime_failure( 'v12_evidence_atomic_revalidation_failed',
					'Claim review, provenance and dependent consumer impact did not commit atomically.' );
				return new WP_Error( 'he_v12_evidence_atomic_revalidation_failed',
					'Claim revalidation was not durably completed. Retry after operator reconciliation.', array( 'status' => 503 ) );
			}
			// Notification after commit only. Consumer reads the durable impact queue.
			do_action( 'he_v24_knowledge_impact_queued', 'concept', (string) $claim['public_id'],
				'KnowledgeEvidenceChanged.v1', $payload );
		}
		if ( $has_more ) {
			// The outer provider cursor stays on this external record; next maintenance
			// run processes the next batch (only approved claims are selected above).
			return new WP_Error( 'he_v12_evidence_more_claims', 'More linked claims require a bounded next scan.', array( 'status' => 503 ) );
		}
		return true;
	}
}

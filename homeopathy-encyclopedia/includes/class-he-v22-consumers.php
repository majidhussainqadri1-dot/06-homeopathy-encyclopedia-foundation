<?php
/** Explicit read-only consumer contracts required by the File 06 plan. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Consumers {
	public static function hooks() {
		add_filter( 'sabri_file06_consumer_contracts', array( __CLASS__, 'contracts' ), 100 );
		add_filter( 'sabri_learning_knowledge_providers', array( __CLASS__, 'learning_provider' ), 100 );
		add_filter( 'sabri_pdf_knowledge_providers', array( __CLASS__, 'pdf_provider' ), 100 );
		add_filter( 'sabri_radar_knowledge_providers', array( __CLASS__, 'radar_provider' ), 100 );
		add_filter( 'sabri_ai_knowledge_providers', array( __CLASS__, 'ai_provider' ), 100 );
		add_filter( 'sabri_home_news_knowledge_providers', array( __CLASS__, 'home_news_provider' ), 100 );
	}

	private static function provider( $consumer, $purpose ) {
		return array(
			'owner' => 'file-06',
			'consumer' => $consumer,
			'contract_version' => HE_CONTRACT_VERSION,
			'purpose' => $purpose,
			'access' => 'public-safe-read-only',
			'query' => array( __CLASS__, 'query' ),
			'get' => array( __CLASS__, 'get' ),
			'get_related' => array( __CLASS__, 'related' ),
			'research_query' => array( __CLASS__, 'research_query' ),
			'freshness_fields' => array( 'version', 'updated_at', 'contract_version', 'record_status', 'safety_status' ),
			'write_authority' => false,
			'private_fields' => false,
		);
	}

	public static function contracts( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		$contracts['file-05'] = self::provider( 'file-05', 'lesson-and-curriculum knowledge links' );
		$contracts['file-12'] = self::provider( 'file-12', 'document citations and knowledge links' );
		$contracts['file-15'] = self::provider( 'file-15', 'Radar source/remedy validation and explanation links' );
		$contracts['file-16'] = self::provider( 'file-16', 'retrieval and education only; never autonomous diagnosis or prescription' );
		$contracts['file-21'] = self::provider( 'file-21', 'read-only knowledge cards and correction projections' );
		$contracts['file-26'] = self::provider( 'file-26', 'global search/discovery projection with visibility recheck' );
		return $contracts;
	}

	private static function attach( $providers, $key, $purpose ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers['file-06'] = self::provider( $key, $purpose );
		return $providers;
	}

	public static function learning_provider( $providers ) { return self::attach( $providers, 'file-05', 'lesson-and-curriculum knowledge links' ); }
	public static function pdf_provider( $providers ) { return self::attach( $providers, 'file-12', 'document citations and knowledge links' ); }
	public static function radar_provider( $providers ) { return self::attach( $providers, 'file-15', 'Radar source/remedy validation and explanation links' ); }
	public static function ai_provider( $providers ) {
		$providers = self::attach( $providers, 'file-16', 'retrieval and education only; never autonomous diagnosis or prescription' );
		$providers['file-06']['clinical_authority'] = false;
		$providers['file-06']['emergency_replacement'] = false;
		$providers['file-06']['citation_required'] = true;
		return $providers;
	}
	public static function home_news_provider( $providers ) { return self::attach( $providers, 'file-21', 'read-only knowledge cards and correction projections' ); }

	public static function query( $query = '', $filters = array(), $cursor = 0, $limit = 20 ) {
		$args = is_array( $filters ) ? $filters : array();
		$args['q'] = sanitize_text_field( (string) $query );
		$args['cursor'] = absint( $cursor );
		$args['limit'] = min( 50, max( 1, absint( $limit ) ) );
		return HE_V22_Search::search( $args );
	}

	public static function get( $public_id ) {
		$row = HE_V2_Domain::concept_by_id( sanitize_text_field( (string) $public_id ) );
		$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;
		if ( ! $dto ) {
			return null;
		}
		return array_intersect_key( $dto, array_flip( array( 'id', 'canonical_url', 'type', 'body_system', 'language', 'title', 'summary', 'fields', 'references', 'version', 'effective_at', 'change_reason', 'review_status', 'safety_status', 'record_status', 'integrity_notices', 'freshness' ) ) );
	}

	public static function related( $public_id, $depth = 1, $limit = 50 ) {
		return HE_V2_Domain::get_related_graph( sanitize_text_field( (string) $public_id ), min( 2, max( 1, absint( $depth ) ) ), min( 100, max( 1, absint( $limit ) ) ) );
	}

	public static function research_query( $cursor = 0, $limit = 20 ) {
		$request = new WP_REST_Request( 'GET', '/' . HE_V2_API::NS . '/research' );
		$request->set_param( 'cursor', absint( $cursor ) );
		$request->set_param( 'limit', min( 50, max( 1, absint( $limit ) ) ) );
		return HE_V22_Governance::browse_research( $request );
	}
}

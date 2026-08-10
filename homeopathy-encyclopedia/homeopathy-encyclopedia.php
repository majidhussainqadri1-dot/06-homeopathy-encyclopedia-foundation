<?php
/**
 * Plugin Name: Homeopathy Encyclopedia Foundation
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical, versioned and governed homeopathy encyclopedia, research registry and knowledge graph for the Sabri Social Homeopathy Platform.
 * Version: 2.3.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: homeopathy-encyclopedia
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'HE_VERSION', '2.3.0' );
define( 'HE_SCHEMA_VERSION', 9 );
define( 'HE_FILE', __FILE__ );
define( 'HE_DIR', plugin_dir_path( __FILE__ ) );
define( 'HE_URL', plugin_dir_url( __FILE__ ) );
define( 'HE_BASENAME', plugin_basename( __FILE__ ) );
define( 'HE_CONTRACT_VERSION', '2.3' );

require_once HE_DIR . 'includes/class-he-v2-auth.php';
require_once HE_DIR . 'includes/class-he-v2-schema.php';
require_once HE_DIR . 'includes/class-he-v2-domain.php';
require_once HE_DIR . 'includes/class-he-v2-api.php';
require_once HE_DIR . 'includes/class-he-v2-public.php';
require_once HE_DIR . 'includes/class-he-v2-admin.php';
require_once HE_DIR . 'includes/class-he-v2-integrations.php';
require_once HE_DIR . 'includes/class-he-v2-privacy.php';
require_once HE_DIR . 'includes/class-he-v22-governance.php';
require_once HE_DIR . 'includes/class-he-v22-public-guard.php';
require_once HE_DIR . 'includes/class-he-v22-integrity.php';
require_once HE_DIR . 'includes/class-he-v22-rest-guard.php';
require_once HE_DIR . 'includes/class-he-v22-schedule.php';
require_once HE_DIR . 'includes/class-he-v22-search.php';
require_once HE_DIR . 'includes/class-he-v22-type-schemas.php';
require_once HE_DIR . 'includes/class-he-v22-research-guard.php';
require_once HE_DIR . 'includes/class-he-v22-admin-first-save.php';
require_once HE_DIR . 'includes/class-he-v22-consumers.php';
require_once HE_DIR . 'includes/class-he-v22-operations.php';
require_once HE_DIR . 'includes/class-he-v23-future.php';

register_activation_hook( HE_FILE, array( 'HE_V22_Governance', 'activate' ) );
register_activation_hook( HE_FILE, array( 'HE_V23_Future', 'activate' ) );
register_deactivation_hook( HE_FILE, array( 'HE_V2_Schema', 'deactivate' ) );

/** Deactivation must clear every File 06 scheduled job, including Future-18 maintenance. */
function he_deactivate_future_runtime() {
	wp_clear_scheduled_hook( HE_V23_Future::CRON );
}
register_deactivation_hook( HE_FILE, 'he_deactivate_future_runtime' );

/** Public, stable provider descriptor for platform discovery. */
function he_contract_descriptor() {
	$events = HE_V2_Integrations::published_events();
	$events[] = 'ResearchPublicationCorrected.v1';
	$events[] = 'File06IntegrityStateChanged.v1';
	$events[] = 'EncyclopediaEntryScheduleInvalidated.v1';
	$events[] = 'KnowledgeEvidenceChanged.v1';
	$events[] = 'KnowledgeFreshnessDue.v1';
	$events[] = 'KnowledgeTranslationOutdated.v1';
	return array(
		'owner'             => 'file-06',
		'contract_version'  => HE_CONTRACT_VERSION,
		'plugin_version'    => HE_VERSION,
		'schema_version'    => HE_SCHEMA_VERSION,
		'status'            => HE_V2_Schema::runtime_status(),
		'identity_authority'=> 'file-00',
		'layout_owner'      => 'file-20',
		'visual_owner'      => 'file-25',
		'search_owner'      => 'file-26',
		'assurance_owner'   => 'file-24',
		'notification_owner'=> 'file-19',
		'canonical_routes'  => array( '/encyclopedia/', '/encyclopedia/{type}/', '/encyclopedia/entry/{canonical_slug}/', '/research/', '/research/{permanent_id}/', '/knowledge/editor/' ),
		'queries'           => array( 'search_knowledge', 'get_entry', 'get_related_graph', 'browse_research', 'health', 'get_type_schemas', 'get_claim_graph', 'get_provenance', 'get_time_machine', 'get_freshness', 'get_research_gaps', 'get_integrity_command_center' ),
		'commands'          => array( 'create_entry_draft', 'submit_entry_review', 'publish_entry_version', 'merge_concepts', 'submit_research', 'submit_research_review', 'submit_integrity_action', 'transition_integrity_action', 'bounded_reindex', 'stage_external_metadata', 'scan_duplicate_candidates', 'queue_consumer_revalidation', 'save_governed_translation', 'manage_watchlist' ),
		'events'            => array_values( array_unique( $events ) ),
		'privacy_class'     => 'mixed-public-restricted',
		'fixed_type_count'  => count( HE_V22_Type_Schemas::schemas() ),
		'future_requirement_count' => 18,
		'consumer_files'    => array( 'file-05', 'file-12', 'file-15', 'file-16', 'file-21', 'file-26' ),
		'search_semantics'  => array( 'exact', 'phrase', 'token', 'alias', 'transliteration-alias', 'spelling-recovery', 'safe-autocomplete' ),
		'migration'         => array( 'resumable' => true, 'quarantine' => true, 'batch_max' => 100 ),
		'reliability'       => array( 'idempotency_required' => true, 'bounded_retry' => true, 'dead_letter' => true, 'outbox_reconciliation' => true, 'scheduled_publication_revalidation' => true, 'future_impact_queue' => true, 'human_review_for_external_metadata' => true ),
		'release_state'     => array( 'coded_candidate' => true, 'staging_accepted' => false, 'live_deployed' => false, 'operational' => false ),
	);
}

/** Start runtime after WordPress and companion contracts are available. */
function he_start_v2() {
	load_plugin_textdomain( 'homeopathy-encyclopedia', false, dirname( HE_BASENAME ) . '/languages' );

	try {
		HE_V22_Governance::maybe_upgrade();
		HE_V23_Future::maybe_upgrade();
	} catch ( Throwable $error ) {
		HE_V2_Schema::record_runtime_failure( 'schema_upgrade_failed', $error->getMessage() );
	}

	HE_V2_Domain::register();
	( new HE_V2_API() )->hooks();
	( new HE_V2_Public() )->hooks();
	( new HE_V2_Admin() )->hooks();
	( new HE_V2_Integrations() )->hooks();
	( new HE_V2_Privacy() )->hooks();
	HE_V22_Search::hooks();
	HE_V22_REST_Guard::hooks();
	HE_V22_Governance::hooks();
	HE_V22_Public_Guard::hooks();
	HE_V22_Integrity::hooks();
	HE_V22_Schedule::hooks();
	HE_V22_Type_Schemas::hooks();
	HE_V22_Research_Guard::hooks();
	HE_V22_Admin_First_Save::hooks();
	HE_V22_Consumers::hooks();
	HE_V22_Operations::hooks();
	HE_V23_Future::hooks();

	add_filter( 'sabri_platform_contracts', static function( $contracts ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		$contracts['file-06'] = he_contract_descriptor();
		return $contracts;
	} );
}
add_action( 'plugins_loaded', 'he_start_v2', 35 );

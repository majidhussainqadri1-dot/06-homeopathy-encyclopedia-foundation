<?php
/**
 * Plugin Name: Homeopathy Encyclopedia Foundation
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical, versioned and governed homeopathy encyclopedia, research registry and knowledge graph for the Sabri Social Homeopathy Platform.
 * Version: 2.4.1
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: homeopathy-encyclopedia
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'HE_VERSION', '2.4.1' );
define( 'HE_SCHEMA_VERSION', 10 );
define( 'HE_FILE', __FILE__ );
define( 'HE_DIR', plugin_dir_path( __FILE__ ) );
define( 'HE_URL', plugin_dir_url( __FILE__ ) );
define( 'HE_BASENAME', plugin_basename( __FILE__ ) );
define( 'HE_CONTRACT_VERSION', '2.4.1' );

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
require_once HE_DIR . 'includes/class-he-v24-future-schema.php';
require_once HE_DIR . 'includes/class-he-v24-migration-safety.php';
require_once HE_DIR . 'includes/class-he-v24-future-api.php';
require_once HE_DIR . 'includes/class-he-v24-future-privacy.php';
require_once HE_DIR . 'includes/class-he-v24-future-review-guard.php';
require_once HE_DIR . 'includes/class-he-v24-public-provenance.php';
require_once HE_DIR . 'includes/class-he-v241-governance.php';
require_once HE_DIR . 'includes/class-he-v241-runtime-guard.php';
require_once HE_DIR . 'includes/class-he-v241-before-callback-normalizer.php';
require_once HE_DIR . 'includes/class-he-v241-public-dto-guard.php';

/**
 * Build the legacy Future-18 base tables, then harden/migrate them before any
 * Future-18 REST route or background job can be enabled.
 */
function he_activate_future_runtime() {
	wp_clear_scheduled_hook( HE_V23_Future::CRON );
	wp_clear_scheduled_hook( HE_V24_Future_Schema::CRON );
	HE_V23_Future::install();
	HE_V24_Migration_Safety::activate();
}

register_activation_hook( HE_FILE, array( 'HE_V22_Governance', 'activate' ) );
register_activation_hook( HE_FILE, 'he_activate_future_runtime' );
register_deactivation_hook( HE_FILE, array( 'HE_V2_Schema', 'deactivate' ) );
register_deactivation_hook( HE_FILE, array( 'HE_V24_Future_Schema', 'deactivate' ) );

/** Public, stable provider descriptor for platform discovery. */
function he_contract_descriptor() {
	$events = HE_V2_Integrations::published_events();
	$events[] = 'ResearchPublicationCorrected.v1';
	$events[] = 'File06IntegrityStateChanged.v1';
	$events[] = 'EncyclopediaEntryScheduleInvalidated.v1';
	$events[] = 'KnowledgeEvidenceChanged.v1';
	$events[] = 'KnowledgeFreshnessDue.v1';
	$events[] = 'KnowledgeTranslationOutdated.v1';
	$events[] = 'KnowledgeTranslationUpdated.v1';
	$events[] = 'File06EditorScopeChanged.v1';
	$events[] = 'File06ReviewerAssigned.v1';
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
		'commands'          => array( 'create_entry_draft', 'assign_editor_type_scope', 'assign_entry_reviewer', 'submit_entry_review', 'publish_entry_version', 'merge_concepts', 'submit_research', 'submit_research_review', 'submit_integrity_action', 'transition_integrity_action', 'bounded_reindex', 'stage_external_metadata', 'review_external_metadata', 'scan_duplicate_candidates', 'queue_consumer_revalidation', 'save_governed_translation', 'review_governed_translation', 'publish_governed_translation', 'manage_watchlist', 'map_researcher_orcid' ),
		'events'            => array_values( array_unique( $events ) ),
		'privacy_class'     => 'mixed-public-restricted',
		'fixed_type_count'  => count( HE_V22_Type_Schemas::schemas() ),
		'future_requirement_count' => 18,
		'future_hardening_version' => '2.4.1',
		'consumer_files'    => array( 'file-05', 'file-12', 'file-15', 'file-16', 'file-21', 'file-26' ),
		'search_semantics'  => array( 'exact', 'phrase', 'token', 'alias', 'transliteration-alias', 'spelling-recovery', 'safe-autocomplete' ),
		'authorization'     => array( 'file00_claims_required' => true, 'native_object_scope_required' => true, 'editor_type_assignment_required' => true, 'reviewer_assignment_required' => true, 'admin_and_composer_scope_enforced' => true, 'successful_guards_continue_to_callback' => true ),
		'migration'         => array( 'resumable' => true, 'quarantine' => true, 'batch_max' => 100, 'verified_future_schema' => true, 'preflight_existing_rows' => true, 'bounded_postflight' => true, 'future_routes_fail_closed_until_ready' => true ),
		'reliability'       => array( 'idempotency_required' => true, 'bounded_retry' => true, 'dead_letter' => true, 'consumer_acknowledgement' => true, 'outbox_reconciliation' => true, 'scheduled_publication_revalidation' => true, 'legacy_unverified_scheduler_disabled' => true, 'core_maintenance_serialized' => true, 'future_maintenance_serialized' => true, 'future_impact_queue' => true, 'human_review_for_external_metadata' => true, 'provider_response_bound' => true ),
		'public_api'        => array( 'canonical_public_ids_only' => true, 'internal_ids_exposed' => false, 'core_numeric_enumeration_blocked' => true, 'public_provenance_types' => array( 'concept', 'claim' ) ),
		'release_state'     => array( 'coded_candidate' => true, 'staging_accepted' => false, 'live_deployed' => false, 'operational' => false ),
	);
}

/** Start runtime after WordPress and companion contracts are available. */
function he_start_v2() {
	load_plugin_textdomain( 'homeopathy-encyclopedia', false, dirname( HE_BASENAME ) . '/languages' );

	try {
		HE_V22_Governance::maybe_upgrade();
		if ( (int) get_option( HE_V24_Future_Schema::OPTION_VERSION, 0 ) < HE_V24_Future_Schema::VERSION ) {
			HE_V23_Future::maybe_upgrade();
		}
		/* Always resume bounded pre/postflight work until the hardening layer reports ready. */
		HE_V24_Migration_Safety::maybe_upgrade();
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

	$future_v24_ready = HE_V24_Migration_Safety::ready();
	if ( $future_v24_ready ) {
		HE_V23_Future::hooks();
		HE_V24_Future_Schema::hooks();
		HE_V24_Future_API::hooks();
		HE_V24_Future_Privacy::hooks();
		HE_V24_Future_Review_Guard::hooks();
		HE_V24_Public_Provenance::hooks();
	} else {
		/* A failed/partial migration must never expose the older, less-hardened Future-18 routes or workers. */
		wp_clear_scheduled_hook( HE_V23_Future::CRON );
		wp_clear_scheduled_hook( HE_V24_Future_Schema::CRON );
	}

	HE_V241_Governance::hooks();
	HE_V241_Runtime_Guard::hooks();
	HE_V241_Before_Callback_Normalizer::hooks();
	HE_V241_Public_DTO_Guard::hooks();

	add_filter( 'sabri_platform_contracts', static function( $contracts ) use ( $future_v24_ready ) {
		$contracts = is_array( $contracts ) ? $contracts : array();
		$contracts['file-06'] = he_contract_descriptor();
		$contracts['file-06']['future_v24_ready'] = (bool) $future_v24_ready;
		return $contracts;
	} );
}
add_action( 'plugins_loaded', 'he_start_v2', 35 );

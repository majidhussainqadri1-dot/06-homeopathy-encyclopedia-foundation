<?php
/** Versioned cross-file adapters, provider contracts and reliable outbox. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_Integrations {
	public function hooks() {
		add_filter( 'sabri_composer_content_types', array( $this, 'composer_types' ) );
		add_filter( 'sabri_publishing_dashboard_providers', array( $this, 'dashboard_provider' ) );
		add_filter( 'sabri_search_connectors', array( $this, 'search_connector' ) );
		add_filter( 'sabri_security_assurance_providers', array( $this, 'assurance_provider' ) );
		add_filter( 'sabri_shell_routes', array( $this, 'shell_routes' ) );
		add_filter( 'sabri_public_component_registry', array( $this, 'public_components' ) );
		add_action( 'LearningLessonPublished.v1', array( $this, 'consume_learning_event' ), 10, 2 );
		add_action( 'PDFDocumentPublished.v1', array( $this, 'consume_pdf_event' ), 10, 2 );
		add_action( 'RadarSourceReviewRequested.v1', array( $this, 'consume_radar_event' ), 10, 2 );
		add_action( 'he_v2_event', array( $this, 'forward_local_event' ), 10, 4 );
	}

	public static function published_events() {
		return array(
			'EncyclopediaEntryPublished.v1',
			'EncyclopediaEntryCorrected.v1',
			'EncyclopediaEntryRetracted.v1',
			'KnowledgeConceptMerged.v1',
			'ResearchPublicationPublished.v1',
			'ResearchRecordRetracted.v1',
		);
	}

	public function composer_types( $types ) {
		$types = is_array( $types ) ? $types : array();
		$types['file06_encyclopedia_entry'] = array(
			'owner' => 'file-06',
			'label' => __( 'Encyclopedia Entry', 'homeopathy-encyclopedia' ),
			'icon' => 'book-alt',
			'contract_version' => HE_CONTRACT_VERSION,
			'available' => HE_V2_Schema::runtime_status() === 'active' && HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT ),
			'draft_command' => array( __CLASS__, 'composer_create_draft' ),
			'preview_url' => rest_url( HE_V2_API::NS . '/entries/{id}' ),
			'native_owner' => 'file-06',
			'rollback_command' => array( __CLASS__, 'composer_rollback_draft' ),
			'fields' => array( 'title', 'summary', 'body', 'type', 'body_system', 'language', 'fields', 'aliases', 'references' ),
			'states' => HE_V2_Domain::entry_states(),
		);
		$types['file06_research_record'] = array(
			'owner' => 'file-06',
			'label' => __( 'Research Record', 'homeopathy-encyclopedia' ),
			'icon' => 'analytics',
			'contract_version' => HE_CONTRACT_VERSION,
			'available' => HE_V2_Schema::runtime_status() === 'active' && HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH ),
			'draft_command' => array( __CLASS__, 'composer_create_research' ),
			'native_owner' => 'file-06',
			'fields' => array( 'record_type', 'title', 'question', 'protocol', 'investigators', 'ethics_reference', 'consent_verified', 'anonymized', 'data_class' ),
			'states' => HE_V2_Domain::research_states(),
		);
		return $types;
	}

	public static function composer_create_draft( $payload, $context = array() ) {
		$current_id = get_current_user_id();
		$actor_id = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : $current_id;
		if ( ! $current_id || ! $actor_id || $actor_id !== $current_id ) {
			return new WP_Error( 'he_composer_actor_mismatch', __( 'Composer actor attribution must match the authenticated user.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Auth::provider_ready() || ! HE_V2_Auth::membership_allowed( $actor_id ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, 0, 'file06-composer-create-entry', $actor_id ) ) {
			return new WP_Error( 'he_composer_forbidden', __( 'File 06 creation is not authorized.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		return HE_V2_Domain::create_entry( is_array( $payload ) ? $payload : array(), $actor_id );
	}

	public static function composer_create_research( $payload, $context = array() ) {
		$current_id = get_current_user_id();
		$actor_id = isset( $context['actor_id'] ) ? absint( $context['actor_id'] ) : $current_id;
		if ( ! $current_id || ! $actor_id || $actor_id !== $current_id ) {
			return new WP_Error( 'he_composer_actor_mismatch', __( 'Composer actor attribution must match the authenticated user.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		if ( ! HE_V2_Auth::provider_ready() || ! HE_V2_Auth::membership_allowed( $actor_id ) || ! HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, 0, 'file06-composer-create-research', $actor_id ) ) {
			return new WP_Error( 'he_composer_forbidden', __( 'File 06 research creation is not authorized.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		return HE_V2_Domain::create_research( is_array( $payload ) ? $payload : array(), $actor_id );
	}

	public static function composer_rollback_draft( $native_id, $context = array() ) {
		$row = HE_V2_Domain::concept_by_id( $native_id, true );
		if ( ! $row || 'draft' !== $row['status'] || ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, (int) $row['post_id'] ) ) {
			return false;
		}
		return (bool) wp_delete_post( (int) $row['post_id'], true );
	}

	public function dashboard_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers['file-06'] = array(
			'owner' => 'file-06',
			'contract_version' => HE_CONTRACT_VERSION,
			'status' => HE_V2_Schema::runtime_status(),
			'inventory_query' => array( $this, 'dashboard_inventory' ),
			'item_query' => array( $this, 'dashboard_item' ),
			'native_edit_url' => array( $this, 'native_edit_url' ),
			'capabilities' => array( HE_V2_Auth::CAP_EDIT, HE_V2_Auth::CAP_REVIEW, HE_V2_Auth::CAP_PUBLISH, HE_V2_Auth::CAP_RESEARCH ),
			'states' => array_merge( HE_V2_Domain::entry_states(), HE_V2_Domain::research_states() ),
			'write_maturity' => 'candidate',
		);
		return $providers;
	}

	private static function dashboard_post_allowed( $post, $user_id ) {
		if ( ! $post || ! $user_id ) { return false; }
		if ( HE_V2_Auth::is_founder( $user_id ) ) { return true; }
		if ( HE_V2_Domain::ENTRY_TYPE === $post->post_type ) {
			$type = HE_V2_Domain::taxonomy_slug( (int) $post->ID, HE_V2_Domain::TAX_TYPE );
			$editor = HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT, (int) $post->ID, 'file06-dashboard-entry-edit', $user_id ) && HE_V241_Governance::editor_type_allowed( $user_id, $type );
			$reviewer = HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, (int) $post->ID, 'file06-dashboard-entry-review', $user_id ) && HE_V241_Governance::reviewer_assigned( (int) $post->ID, $user_id );
			return $editor || $reviewer;
		}
		if ( HE_V2_Domain::RESEARCH_TYPE === $post->post_type ) {
			$research = HE_V2_Auth::can( HE_V2_Auth::CAP_RESEARCH, (int) $post->ID, 'file06-dashboard-research', $user_id );
			$reviewer = HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW, (int) $post->ID, 'file06-dashboard-research-review', $user_id ) && HE_V241_Governance::reviewer_assigned( (int) $post->ID, $user_id );
			return $research || $reviewer;
		}
		return false;
	}

	public function dashboard_inventory( $args = array() ) {
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_EDIT ) && ! HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW ) ) {
			return new WP_Error( 'he_dashboard_forbidden', __( 'Dashboard inventory is not authorized.', 'homeopathy-encyclopedia' ) );
		}
		$query = new WP_Query( array(
			'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ),
			'post_status' => array( 'draft', 'pending', 'publish', 'private' ),
			'posts_per_page' => min( 100, max( 1, absint( $args['limit'] ?? 25 ) ) ),
			'paged' => max( 1, absint( $args['page'] ?? 1 ) ),
			'author' => ! HE_V2_Auth::can( HE_V2_Auth::CAP_REVIEW ) ? get_current_user_id() : 0,
			'orderby' => 'modified',
			'order' => 'DESC',
		) );
		$items = array();
		$user_id = get_current_user_id();
		foreach ( $query->posts as $post ) {
			if ( ! self::dashboard_post_allowed( $post, $user_id ) ) { continue; }
			$items[] = array(
				'universal_ref' => 'file-06:' . $post->post_type . ':' . $post->ID,
				'native_id' => $post->ID,
				'object_type' => $post->post_type,
				'title' => $post->post_title,
				'native_status' => $post->post_status,
				'modified_at' => get_post_modified_time( 'c', true, $post ),
				'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
				'public_url' => 'publish' === $post->post_status ? get_permalink( $post->ID ) : '',
			);
		}
		return array( 'items' => $items, 'total' => count( $items ), 'pages' => $items ? 1 : 0, 'scope_filtered' => true );
	}

	public function dashboard_item( $native_id ) {
		$post = get_post( absint( $native_id ) );
		if ( ! $post || ! in_array( $post->post_type, array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ), true ) ) {
			return null;
		}
		if ( ! self::dashboard_post_allowed( $post, get_current_user_id() ) ) {
			return null;
		}
		return array(
			'universal_ref' => 'file-06:' . $post->post_type . ':' . $post->ID,
			'native_id' => $post->ID,
			'owner' => 'file-06',
			'title' => $post->post_title,
			'status' => $post->post_status,
			'author_id' => (int) $post->post_author,
			'modified_at' => get_post_modified_time( 'c', true, $post ),
			'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
			'public_url' => 'publish' === $post->post_status ? get_permalink( $post->ID ) : '',
		);
	}

	public function native_edit_url( $native_id ) {
		return get_edit_post_link( absint( $native_id ), 'raw' );
	}

	public function search_connector( $connectors ) {
		$connectors = is_array( $connectors ) ? $connectors : array();
		$connectors['file-06'] = array(
			'owner' => 'file-06',
			'contract_version' => HE_CONTRACT_VERSION,
			'entity_types' => array( 'knowledge-entry', 'research-record' ),
			'query' => array( $this, 'file26_query' ),
			'get' => array( $this, 'file26_get' ),
			'visibility_recheck' => array( $this, 'file26_visibility' ),
			'rebuild' => array( 'HE_V2_Domain', 'reindex_all' ),
			'events' => self::published_events(),
			'privacy_fields' => array( 'id', 'title', 'summary', 'type', 'language', 'url', 'version', 'safety_status', 'updated_at' ),
		);
		return $connectors;
	}

	public function file26_query( $query, $filters = array(), $cursor = 0, $limit = 20 ) {
		$args = is_array( $filters ) ? $filters : array();
		$args['q'] = sanitize_text_field( $query );
		$args['cursor'] = absint( $cursor );
		$args['limit'] = absint( $limit );
		return HE_V2_Domain::search( $args );
	}

	public function file26_get( $public_id ) {
		$row = HE_V2_Domain::concept_by_id( $public_id );
		$dto = $row ? HE_V2_Domain::public_dto( $row ) : null;
		if ( ! $dto ) {
			return null;
		}
		return array_intersect_key( $dto, array_flip( array( 'id', 'title', 'summary', 'type', 'language', 'canonical_url', 'version', 'safety_status', 'freshness' ) ) );
	}

	public function file26_visibility( $public_id ) {
		return (bool) HE_V2_Domain::concept_by_id( $public_id );
	}

	public function assurance_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$providers['file-06'] = array(
			'owner' => 'file-06',
			'contract_version' => HE_CONTRACT_VERSION,
			'health' => array( 'HE_V2_Schema', 'health' ),
			'evidence_query' => array( $this, 'assurance_evidence' ),
			'control_families' => array( 'identity', 'authorization', 'medical-safety', 'research-ethics', 'privacy', 'integrity', 'outbox', 'backup-readiness' ),
			'native_enforcement_preserved' => true,
		);
		return $providers;
	}

	public function assurance_evidence() {
		global $wpdb;
		$tables = array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'reviews', 'integrity_actions', 'research', 'dataset_access', 'events', 'outbox', 'idempotency', 'search_index' );
		$counts = array();
		foreach ( $tables as $suffix ) {
			$table = HE_V2_Schema::table( $suffix );
			$counts[ $suffix ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return array(
			'generated_at' => gmdate( 'c' ),
			'owner' => 'file-06',
			'contract_version' => HE_CONTRACT_VERSION,
			'health' => HE_V2_Schema::health(),
			'counts' => $counts,
			'retention' => array( 'canonical-knowledge' => 'permanent-versioned', 'research-record' => 'institutional-record', 'dataset-access' => 'policy-and-lawful-basis', 'idempotency' => '24-hours' ),
			'public_private_boundary' => 'explicit-dto-allowlists',
		);
	}

	public function shell_routes( $routes ) {
		$routes = is_array( $routes ) ? $routes : array();
		$routes['encyclopedia'] = array(
			'owner' => 'file-06',
			'path' => '/encyclopedia/',
			'label' => __( 'Encyclopedia', 'homeopathy-encyclopedia' ),
			'icon' => 'book-alt',
			'access' => 'public',
			'cache' => 'public',
			'index' => true,
			'contract_version' => HE_CONTRACT_VERSION,
		);
		$routes['research'] = array(
			'owner' => 'file-06',
			'path' => '/research/',
			'label' => __( 'Research', 'homeopathy-encyclopedia' ),
			'icon' => 'analytics',
			'access' => 'public',
			'cache' => 'public-metadata-only',
			'index' => true,
			'contract_version' => HE_CONTRACT_VERSION,
		);
		return $routes;
	}

	public function public_components( $components ) {
		$components = is_array( $components ) ? $components : array();
		$components['file06-knowledge-card-v2'] = array(
			'owner' => 'file-06',
			'presentation_owner' => 'file-25',
			'version' => '2.0',
			'fields' => array( 'id', 'title', 'summary', 'type', 'body_system', 'language', 'version', 'safety_status', 'url', 'updated_at' ),
			'tokens' => array( '--sabri-primary', '--sabri-surface', '--sabri-text', '--sabri-border', '--sabri-focus' ),
		);
		return $components;
	}

	public function consume_learning_event( $payload, $event_id = '' ) {
		$this->record_consumed_event( 'LearningLessonPublished.v1', $payload, $event_id );
	}

	public function consume_pdf_event( $payload, $event_id = '' ) {
		$this->record_consumed_event( 'PDFDocumentPublished.v1', $payload, $event_id );
	}

	public function consume_radar_event( $payload, $event_id = '' ) {
		$this->record_consumed_event( 'RadarSourceReviewRequested.v1', $payload, $event_id );
	}

	private function record_consumed_event( $name, $payload, $event_id ) {
		global $wpdb;
		$event_id = $event_id && preg_match( '/^[a-f0-9-]{16,64}$/i', $event_id ) ? $event_id : wp_generate_uuid4();
		$table = HE_V2_Schema::table( 'events' );
		$safe_payload = HE_V2_Domain::minimize_event_payload( is_array( $payload ) ? $payload : array() );
		$inserted = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (event_id,event_name,object_type,object_id,actor_id,trace_id,payload_json,created_at) VALUES (%s,%s,'external',0,0,%s,%s,%s)",
			$event_id, sanitize_text_field( $name ), HE_V2_Domain::trace_id(), wp_json_encode( $safe_payload ), current_time( 'mysql', true )
		) );
		if ( false === $inserted ) {
			HE_V2_Schema::record_runtime_failure( 'consumed_event_write_failed', 'A File 06 consumed-domain-event audit row could not be persisted.' );
		}
	}

	public function forward_local_event( $name, $payload, $event_id, $trace_id ) {
		do_action( 'sabri_domain_event', array(
			'event_id' => $event_id,
			'name' => $name,
			'owner' => 'file-06',
			'contract_version' => HE_CONTRACT_VERSION,
			'trace_id' => $trace_id,
			'payload' => $payload,
		) );
	}

	public static function process_outbox( $limit = 50 ) {
		global $wpdb;
		$limit = min( 100, max( 1, absint( $limit ) ) );
		$table = HE_V2_Schema::table( 'outbox' );
		$wpdb->query( "UPDATE {$table} SET status='retry',next_attempt_at=UTC_TIMESTAMP(),last_error='stale-processing-recovered',updated_at=UTC_TIMESTAMP() WHERE status='processing' AND updated_at<=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('pending','retry') AND next_attempt_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT %d", $limit ), ARRAY_A );
		$processed = 0;
		foreach ( $rows as $row ) {
			$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='processing',attempts=attempts+1,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status=%s AND attempts=%d AND next_attempt_at<=UTC_TIMESTAMP()", (int) $row['id'], (string) $row['status'], (int) $row['attempts'] ) );
			if ( 1 !== (int) $claimed ) { continue; }
			$processed++;
			$attempts = (int) $row['attempts'] + 1;
			$payload = json_decode( $row['payload_json'], true );
			try {
				do_action( 'he_v2_outbox_event', $row['event_name'], is_array( $payload ) ? $payload : array(), $row['event_id'] );
				$done = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='delivered',last_error='',updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='processing' AND attempts=%d", (int) $row['id'], $attempts ) );
				if ( 1 !== (int) $done ) { HE_V2_Schema::record_runtime_failure( 'outbox_delivery_finalize_failed', 'A File 06 outbox delivery lost its processing lease before finalization.' ); }
			} catch ( Throwable $error ) {
				$status = $attempts >= 5 ? 'dead-letter' : 'retry';
				$delay = min( DAY_IN_SECONDS, 60 * ( 2 ** min( 8, $attempts ) ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status=%s,next_attempt_at=%s,last_error=%s,updated_at=UTC_TIMESTAMP() WHERE id=%d AND status='processing' AND attempts=%d", $status, gmdate( 'Y-m-d H:i:s', time() + $delay ), sanitize_text_field( $error->getMessage() ), (int) $row['id'], $attempts ) );
			}
		}
		return $processed;
	}

}

<?php
/** Versioned REST command/query API. */
defined( 'ABSPATH' ) || exit;

final class HE_V2_API {
	const NS = 'sabri/v2/file-06';

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'security_headers' ), 10, 4 );
	}

	public function routes() {
		register_rest_route( self::NS, '/health', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'health' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR ); },
		) );
		register_rest_route( self::NS, '/entries', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args' => $this->search_args(),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_entry' ),
				'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_EDIT ); },
			),
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_entry' ),
			'permission_callback' => '__return_true',
			'args' => array( 'version' => array( 'sanitize_callback' => 'absint' ) ),
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/versions', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'versions' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/diff', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'version_diff' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'from' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'to' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
			),
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/bookmark', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'bookmark_state' ),
				'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); },
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'bookmark' ),
				'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); },
			),
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/aliases', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'add_alias' ),
			'permission_callback' => function( $request ) { return $this->entry_permission( $request, HE_V2_Auth::CAP_EDIT ); },
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/references', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'add_reference' ),
			'permission_callback' => function( $request ) { return $this->entry_permission( $request, HE_V2_Auth::CAP_EDIT ); },
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/review', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'review' ),
			'permission_callback' => function( $request ) { return $this->entry_permission( $request, HE_V2_Auth::CAP_REVIEW ); },
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/transition', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'transition_entry' ),
			'permission_callback' => function( $request ) {
				$state = sanitize_key( $request->get_param( 'state' ) );
				$cap = in_array( $state, array( 'approved', 'scheduled', 'published', 'corrected', 'retracted', 'archived' ), true ) ? HE_V2_Auth::CAP_PUBLISH : HE_V2_Auth::CAP_EDIT;
				return $this->entry_permission( $request, $cap );
			},
		) );
		register_rest_route( self::NS, '/entries/(?P<id>[A-Za-z0-9-]+)/integrity', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'create_integrity' ),
			'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); },
		) );
		register_rest_route( self::NS, '/integrity/(?P<id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/apply', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'apply_integrity' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH ); },
		) );
		register_rest_route( self::NS, '/graph/(?P<id>[A-Za-z0-9-]+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'graph' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'add_relation' ),
				'permission_callback' => function( $request ) { return $this->entry_permission( $request, HE_V2_Auth::CAP_EDIT ); },
			),
		) );
		register_rest_route( self::NS, '/duplicates', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'duplicates' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_TAXONOMY ); },
			'args' => array(
				'id' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
				'limit' => array( 'sanitize_callback' => 'absint', 'default' => 50 ),
			),
		) );
		register_rest_route( self::NS, '/merge', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'merge' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_TAXONOMY ); },
		) );
		register_rest_route( self::NS, '/autocomplete', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'autocomplete' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'limit' => array( 'sanitize_callback' => 'absint', 'default' => 8 ),
			),
		) );
		register_rest_route( self::NS, '/research', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'browse_research' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_research' ),
				'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH ); },
			),
		) );
		register_rest_route( self::NS, '/research/(?P<id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/transition', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'transition_research' ),
			'permission_callback' => function( $request ) {
				global $wpdb;
				$row = $wpdb->get_row( $wpdb->prepare( 'SELECT post_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', strtolower( sanitize_text_field( (string) $request['id'] ) ) ), ARRAY_A );
				if ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
				return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_RESEARCH, (int) $row['post_id'], 'file06-rest' );
			},
		) );
		register_rest_route( self::NS, '/datasets/(?P<id>[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})/access', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'request_dataset_access' ),
			'permission_callback' => function() { return is_user_logged_in() && HE_V2_Auth::membership_allowed(); },
		) );
		register_rest_route( self::NS, '/dataset-access/(?P<id>\d+)/approve', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'approve_dataset_access' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_DATASET ); },
		) );
		register_rest_route( self::NS, '/repair', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'repair' ),
			'permission_callback' => function() { return HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_REPAIR ); },
		) );
	}

	private function search_args() {
		return array(
			'q' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
			'type' => array( 'sanitize_callback' => 'sanitize_key', 'default' => '' ),
			'body_system' => array( 'sanitize_callback' => 'sanitize_key', 'default' => '' ),
			'language' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
			'review_status' => array( 'sanitize_callback' => 'sanitize_key', 'default' => '' ),
			'safety_status' => array( 'sanitize_callback' => 'sanitize_key', 'default' => '' ),
			'source_grade' => array( 'sanitize_callback' => 'sanitize_key', 'default' => '' ),
			'letter' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
			'cursor' => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
			'limit' => array( 'sanitize_callback' => 'absint', 'default' => 20 ),
		);
	}

	private function entry_permission( $request, $cap ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return HE_V2_Auth::rest_permission( $cap, (int) $row['post_id'], 'file06-rest' );
	}

	private function require_mutation_guards( WP_REST_Request $request, $operation ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {
			return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode. Public reading remains available, but mutations are paused.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		if ( ! HE_V2_Auth::require_nonce( $request ) ) {
			return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) );
		}
		$user_id = get_current_user_id();
		if ( ! HE_V2_Domain::rate_allow( 'rest:' . $operation . ':' . $user_id, 60, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) );
		}
		$key = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		if ( '' === $key || strlen( $key ) > 128 ) {
			return new WP_Error( 'he_idempotency_required', __( 'A valid Idempotency-Key header is required.', 'homeopathy-encyclopedia' ), array( 'status' => 400 ) );
		}
		return HE_V2_Domain::idempotent_begin( $user_id, $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private function mutation_response( $reservation, $result, $success_code = 200 ) {
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}
		if ( ! empty( $reservation['replay'] ) ) {
			return new WP_REST_Response( $reservation['body'], $reservation['code'] );
		}
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			$body = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $data );
			$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $code, $body );
			if ( ! $finished ) {
				return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request outcome could not be recorded safely. Do not retry with a new key until the current state is reloaded.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
			}
			return $result;
		}
		$body = array( 'data' => $result, 'trace_id' => HE_V2_Domain::trace_id() );
		$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $body );
		if ( ! $finished ) {
			return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		return new WP_REST_Response( $body, $success_code );
	}

	public function health() {
		return rest_ensure_response( HE_V2_Schema::health() );
	}

	public function search( WP_REST_Request $request ) {
		$response = rest_ensure_response( HE_V2_Domain::search( $request->get_params() ) );
		$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=120' );
		$response->header( 'X-File-06-Contract', HE_CONTRACT_VERSION );
		return $response;
	}

	public function get_entry( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$dto = HE_V2_Domain::public_dto( $row, absint( $request->get_param( 'version' ) ) );
		if ( ! $dto ) {
			return new WP_Error( 'he_version_not_found', __( 'The requested entry version is unavailable.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$response = rest_ensure_response( $dto );
		$response->set_headers( array(
			'Cache-Control' => 'public, max-age=120, stale-while-revalidate=300',
			'ETag' => '"he-' . $dto['id'] . '-v' . $dto['version'] . '"',
			'X-File-06-Contract' => HE_CONTRACT_VERSION,
		) );
		return $response;
	}

	public function versions( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( HE_V2_Domain::versions( $row['id'] ) );
	}

	public function version_diff( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( HE_V2_Domain::version_diff( $row['id'], $request->get_param( 'from' ), $request->get_param( 'to' ) ) );
	}

	public function bookmark_state( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'bookmarked' => HE_V2_Domain::is_bookmarked( get_current_user_id(), $row['id'] ) ) );
	}

	public function bookmark( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		$reservation = $this->require_mutation_guards( $request, 'bookmark-' . $row['public_id'] );
		$data = (array) $request->get_json_params();
		$active = ! array_key_exists( 'active', $data ) || rest_sanitize_boolean( $data['active'] );
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::set_bookmark( get_current_user_id(), $row['id'], $active );
		if ( ! is_wp_error( $result ) ) {
			$result = array( 'bookmarked' => (bool) $result );
		}
		return $this->mutation_response( $reservation, $result );
	}

	public function duplicates( WP_REST_Request $request ) {
		$concept_id = 0;
		if ( $request->get_param( 'id' ) ) {
			$row = HE_V2_Domain::concept_by_id( $request->get_param( 'id' ), true );
			if ( ! $row ) {
				return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			}
			$concept_id = (int) $row['id'];
		}
		return rest_ensure_response( HE_V2_Domain::find_duplicates( $concept_id, $request->get_param( 'limit' ) ) );
	}

	public function create_entry( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'create-entry' );
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::create_entry( (array) $request->get_json_params(), get_current_user_id() );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function add_alias( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'add-alias-' . $request['id'] );
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		$data = (array) $request->get_json_params();
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_alias( $row['id'], $data['alias'] ?? '', $data['language'] ?? 'en-US', $data['type'] ?? 'synonym', false, get_current_user_id() );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function add_reference( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'add-reference-' . $request['id'] );
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_reference( $row['id'], (array) $request->get_json_params(), get_current_user_id(), absint( $request->get_param( 'version_id' ) ) );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function review( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'review-entry-' . $request['id'] );
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		$data = (array) $request->get_json_params();
		if ( ! is_wp_error( $reservation ) && ( ! $row || ! absint( $data['expected_version'] ?? 0 ) || absint( $data['expected_version'] ?? 0 ) !== (int) $row['row_version'] ) ) {
			$result = new WP_Error( 'he_version_conflict', __( 'The entry changed after it was loaded for review. Reload the current version before deciding.', 'homeopathy-encyclopedia' ), array( 'status' => 409 ) );
		} else {
			$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::add_review( $row['id'], sanitize_key( $data['scope'] ?? 'scientific' ), sanitize_key( $data['decision'] ?? 'changes_required' ), ! empty( $data['conflict_declared'] ), $data['note'] ?? '', get_current_user_id(), absint( $data['expected_version'] ?? 0 ) );
		}
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function transition_entry( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'transition-entry-' . $request['id'] );
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		$data = (array) $request->get_json_params();
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::transition_entry( $row['id'], sanitize_key( $data['state'] ?? '' ), absint( $data['expected_version'] ?? 0 ), get_current_user_id(), $data['note'] ?? '', $data['effective_at'] ?? '' );
		return $this->mutation_response( $reservation, $result );
	}

	public function create_integrity( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		if ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$reservation = $this->require_mutation_guards( $request, 'integrity-' . $row['public_id'] );
		$data = (array) $request->get_json_params();
		$replacement_id = 0;
		$replacement_identifier = trim( (string) ( $data['replacement_id'] ?? '' ) );
		if ( '' !== $replacement_identifier ) {
			if ( ctype_digit( $replacement_identifier ) ) {
				$result = new WP_Error( 'he_canonical_public_id_required', __( 'Replacement entries must use a canonical public identifier or canonical slug.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
				return $this->mutation_response( $reservation, $result, 201 );
			}
			$replacement = HE_V2_Domain::concept_by_id( sanitize_text_field( $replacement_identifier ), true );
			if ( ! $replacement ) {
				$result = new WP_Error( 'he_replacement_not_found', __( 'Replacement entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
				return $this->mutation_response( $reservation, $result, 201 );
			}
			$replacement_id = (int) $replacement['id'];
		}
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::create_integrity_action( $row['id'], sanitize_key( $data['type'] ?? 'correction' ), $data['reason'] ?? '', $data['evidence'] ?? '', $replacement_id, get_current_user_id() );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function apply_integrity( WP_REST_Request $request ) {
		global $wpdb;
		$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
		$action_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'integrity_actions' ) . ' WHERE public_id=%s', $public_id ) );
		if ( ! $action_id ) { return new WP_Error( 'he_not_found', __( 'Integrity action not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$reservation = $this->require_mutation_guards( $request, 'apply-integrity-' . $public_id );
		$data = (array) $request->get_json_params();
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::apply_integrity_action( $action_id, absint( $data['expected_version'] ?? 0 ), get_current_user_id() );
		return $this->mutation_response( $reservation, $result );
	}

	public function graph( WP_REST_Request $request ) {
		$row = HE_V2_Domain::concept_by_id( $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'he_not_found', __( 'Entry not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( HE_V2_Domain::graph( $row['id'], absint( $request->get_param( 'depth' ) ?: 1 ), absint( $request->get_param( 'limit' ) ?: 50 ) ) );
	}

	public function add_relation( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'add-relation-' . $request['id'] );
		$row = HE_V2_Domain::concept_by_id( $request['id'], true );
		$data = (array) $request->get_json_params();
		$target = HE_V2_Domain::concept_by_id( $data['target_id'] ?? '', true );
		$result = is_wp_error( $reservation ) ? $reservation : ( ! $target ? new WP_Error( 'he_relation_target_missing', __( 'Relationship target not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) : HE_V2_Domain::add_relation( $row['id'], $target['id'], sanitize_key( $data['type'] ?? '' ), absint( $data['reference_id'] ?? 0 ), get_current_user_id() ) );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function merge( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'merge-concepts' );
		$data = (array) $request->get_json_params();
		$source = HE_V2_Domain::concept_by_id( $data['source_id'] ?? '', true );
		$target = HE_V2_Domain::concept_by_id( $data['target_id'] ?? '', true );
		$result = is_wp_error( $reservation ) ? $reservation : ( ( ! $source || ! $target ) ? new WP_Error( 'he_not_found', __( 'One or both concepts were not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ) : HE_V2_Domain::merge_concepts( $source['id'], $target['id'], absint( $data['source_version'] ?? 0 ), absint( $data['target_version'] ?? 0 ), get_current_user_id(), $data['reason'] ?? '' ) );
		return $this->mutation_response( $reservation, $result );
	}

	public function autocomplete( WP_REST_Request $request ) {
		return rest_ensure_response( HE_V2_Domain::autocomplete( $request->get_param( 'q' ), $request->get_param( 'limit' ) ) );
	}

	public function browse_research( WP_REST_Request $request ) {
		global $wpdb;
		$limit = min( 50, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$cursor = max( 0, absint( $request->get_param( 'cursor' ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . " WHERE status IN ('published','corrected','retracted') AND id>%d ORDER BY id ASC LIMIT %d", $cursor, $limit + 1 ), ARRAY_A );
		$has_more = count( $rows ) > $limit;
		$rows = array_slice( $rows, 0, $limit );
		$items = array_values( array_filter( array_map( static function( $row ) { return HE_V2_Domain::research_dto( (int) $row['id'], false ); }, $rows ) ) );
		return rest_ensure_response( array( 'items' => $items, 'next_cursor' => $has_more && $rows ? (int) end( $rows )['id'] : null ) );
	}

	public function create_research( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'create-research' );
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::create_research( (array) $request->get_json_params(), get_current_user_id() );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function transition_research( WP_REST_Request $request ) {
		global $wpdb;
		$public_id = strtolower( sanitize_text_field( (string) $request['id'] ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
		if ( ! $row ) { return new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) ); }
		$reservation = $this->require_mutation_guards( $request, 'transition-research-' . $public_id );
		$data = (array) $request->get_json_params();
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::transition_research( (int) $row['id'], sanitize_key( $data['state'] ?? '' ), absint( $data['expected_version'] ?? 0 ), get_current_user_id(), $data['note'] ?? '' );
		return $this->mutation_response( $reservation, $result );
	}

	public function request_dataset_access( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'dataset-access-' . $request['id'] );
		$data = (array) $request->get_json_params();
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::request_dataset_access( sanitize_text_field( $request['id'] ), $data['purpose'] ?? '', $data['lawful_basis'] ?? '', get_current_user_id() );
		return $this->mutation_response( $reservation, $result, 201 );
	}

	public function approve_dataset_access( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'approve-dataset-access-' . $request['id'] );
		$data = (array) $request->get_json_params();
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Domain::approve_dataset_access( absint( $request['id'] ), $data['expires_at'] ?? '', get_current_user_id() );
		return $this->mutation_response( $reservation, $result );
	}

	public function repair( WP_REST_Request $request ) {
		$reservation = $this->require_mutation_guards( $request, 'repair' );
		$dry_run = ! empty( $request->get_json_params()['dry_run'] );
		$result = is_wp_error( $reservation ) ? $reservation : HE_V2_Schema::repair( $dry_run );
		return $this->mutation_response( $reservation, $result );
	}

	public function security_headers( $served, $result, $request, $server ) {
		if ( 0 === strpos( $request->get_route(), '/' . self::NS ) ) {
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
		return $served;
	}
}

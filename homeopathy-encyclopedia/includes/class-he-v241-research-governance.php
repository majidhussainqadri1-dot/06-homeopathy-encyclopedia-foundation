<?php
/** File 06 v2.4.1 research review-assignment and integrity-state hardening. */
defined( 'ABSPATH' ) || exit;

final class HE_V241_Research_Governance {
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 330 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 338, 3 );
	}

	public static function register_routes() {
		$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
		register_rest_route( HE_V2_API::NS, '/governance/research-reviewer-assignment/(?P<id>' . $uuid . ')', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( __CLASS__, 'assign' ),
			'permission_callback' => static function( WP_REST_Request $request ) {
				$row = self::research( $request['id'] );
				return $row ? HE_V2_Auth::rest_permission( HE_V2_Auth::CAP_PUBLISH, (int) $row['post_id'], 'file06-research-review-assignment' ) : new WP_Error( 'he_not_found', __( 'Research record not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
			},
		) );
	}

	private static function research( $public_id ) {
		global $wpdb;
		$public_id = strtolower( sanitize_text_field( (string) $public_id ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $public_id ) ) { return null; }
		return $wpdb->get_row( $wpdb->prepare( 'SELECT id,public_id,post_id,status,row_version FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE public_id=%s', $public_id ), ARRAY_A );
	}

	private static function guard_mutation( WP_REST_Request $request, $operation ) {
		if ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) { return new WP_Error( 'he_safe_mode', __( 'File 06 is in safe mode.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ); }
		if ( ! HE_V2_Auth::require_nonce( $request ) ) { return new WP_Error( 'he_invalid_nonce', __( 'The security token is missing or expired.', 'homeopathy-encyclopedia' ), array( 'status' => 403 ) ); }
		if ( ! HE_V2_Domain::rate_allow( 'v241-research-governance:' . sanitize_key( $operation ) . ':' . get_current_user_id(), 30, MINUTE_IN_SECONDS ) ) { return new WP_Error( 'he_rate_limited', __( 'Too many requests. Please retry later.', 'homeopathy-encyclopedia' ), array( 'status' => 429 ) ); }
		$key=trim((string)$request->get_header('Idempotency-Key'));
		if(strlen($key)<8||strlen($key)>128){return new WP_Error('he_idempotency_required',__('A valid Idempotency-Key header is required.','homeopathy-encyclopedia'),array('status'=>400));}
		return HE_V2_Domain::idempotent_begin( get_current_user_id(), $operation, $key, $request->get_json_params() ?: $request->get_params() );
	}

	private static function finish( $reservation, $result, $code=200 ) {
		if ( is_wp_error( $reservation ) ) { return $reservation; }
		if ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], $reservation['code'] ); }
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
			$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $status, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
			if ( ! $finished ) {
				return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request outcome could not be recorded safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
			}
			return $result;
		}
		$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $code, $result );
		if ( ! $finished ) {
			return new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
		}
		return new WP_REST_Response( $result, $code );
	}

	public static function assign( WP_REST_Request $request ) {
		$public_id=strtolower(sanitize_text_field((string)$request['id']));
		$res=self::guard_mutation($request,'research-reviewer-assignment-'.sanitize_key($public_id));if(is_wp_error($res)||!empty($res['replay']))return self::finish($res,null);
		$row=self::research($public_id);if(!$row)return self::finish($res,new WP_Error('he_not_found',__('Research record not found.','homeopathy-encyclopedia'),array('status'=>404)));
		$reviewer=absint($request->get_param('reviewer_id'));$scope=sanitize_key($request->get_param('scope')?:'scientific');$allowed=array('scientific','clinical','source','language','shariah','privacy','ethics','methodology');
		if(!in_array($scope,$allowed,true)||!$reviewer||!HE_V2_Auth::can(HE_V2_Auth::CAP_REVIEW,(int)$row['post_id'],'file06-research-review-target',$reviewer))return self::finish($res,new WP_Error('he_research_reviewer_assignment_invalid',__('The reviewer or review scope is invalid.','homeopathy-encyclopedia'),array('status'=>422)));
		$post=get_post((int)$row['post_id']);if($post&&(int)$post->post_author===$reviewer&&!HE_V2_Auth::is_founder($reviewer))return self::finish($res,new WP_Error('he_self_review_forbidden',__('A research author cannot be assigned as the independent reviewer.','homeopathy-encyclopedia'),array('status'=>422)));
		$assignments=get_post_meta((int)$row['post_id'],HE_V241_Governance::META_REVIEW_ASSIGNMENTS,true);$assignments=is_array($assignments)?$assignments:array();$old=is_array($assignments[$scope]??null)?$assignments[$scope]:array();
		$expiry=0;if($request->get_param('expires_at')){$expiry=strtotime((string)$request->get_param('expires_at'));if(!$expiry||$expiry<=time()||$expiry>time()+YEAR_IN_SECONDS)return self::finish($res,new WP_Error('he_reviewer_assignment_expiry_invalid',__('Reviewer assignment expiry is invalid.','homeopathy-encyclopedia'),array('status'=>422)));}
		$assignments[$scope]=array('reviewer_id'=>$reviewer,'assigned_by'=>get_current_user_id(),'assigned_at'=>gmdate('c'),'expires_at'=>$expiry?gmdate('c',$expiry):'','assignment_version'=>1+absint($old['assignment_version']??0));
		update_post_meta((int)$row['post_id'],HE_V241_Governance::META_REVIEW_ASSIGNMENTS,$assignments);
		HE_V2_Domain::emit_event('File06ResearchReviewerAssigned.v1','research',(int)$row['id'],array('scope'=>$scope,'reviewer_user_id'=>$reviewer,'assignment_version'=>$assignments[$scope]['assignment_version']));
		return self::finish($res,array('research_id'=>$row['public_id'],'scope'=>$scope,'reviewer_id'=>$reviewer,'assignment_version'=>$assignments[$scope]['assignment_version']));
	}

	private static function assigned( $post_id, $user_id, $scope ) {
		if(HE_V2_Auth::is_founder($user_id))return true;
		$a=get_post_meta(absint($post_id),HE_V241_Governance::META_REVIEW_ASSIGNMENTS,true);if(!is_array($a)||empty($a[$scope])||!is_array($a[$scope]))return false;$x=$a[$scope];
		if(absint($x['reviewer_id']??0)!==absint($user_id))return false;$e=!empty($x['expires_at'])?strtotime($x['expires_at']):0;return !$e||$e>time();
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if(null!==$response||!$request instanceof WP_REST_Request||'GET'===$request->get_method())return $response;$route=$request->get_route();$prefix='/'.HE_V2_API::NS;
		$uuid='[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
		if(preg_match('#^'.preg_quote($prefix,'#').'/research/('.$uuid.')/review$#',$route,$m)){
			$row=self::research($m[1]);if(!$row)return new WP_Error('he_not_found',__('Research record not found.','homeopathy-encyclopedia'),array('status'=>404));$scope=sanitize_key($request->get_param('scope')?:'scientific');
			if(!self::assigned((int)$row['post_id'],get_current_user_id(),$scope))return new WP_Error('he_reviewer_assignment_required',__('An active reviewer assignment for this research scope is required.','homeopathy-encyclopedia'),array('status'=>403));
		}
		if(preg_match('#^'.preg_quote($prefix,'#').'/research-integrity/(\d+)/apply$#',$route,$m)){
			global $wpdb;$action=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.HE_V2_Schema::table('integrity_actions')." WHERE id=%d AND object_type='research'",absint($m[1])),ARRAY_A);
			if(!$action||'accepted'!==$action['status'])return new WP_Error('he_integrity_acceptance_required',__('A research correction or retraction must be explicitly accepted before it can be applied.','homeopathy-encyclopedia'),array('status'=>409));
			$row=self::research($action['object_id']);if(!$row)return new WP_Error('he_not_found',__('Research record not found.','homeopathy-encyclopedia'),array('status'=>404));$perm=HE_V2_Auth::rest_permission(HE_V2_Auth::CAP_PUBLISH,(int)$row['post_id'],'file06-research-integrity-apply');return true===$perm?$response:$perm;
		}
		return $response;
	}
}

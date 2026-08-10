<?php
/** Defense-in-depth authorization for v2.2 intercepted and operational REST routes. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_REST_Guard {
	public static function hooks() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'authorize' ), 80, 3 );
	}

	public static function authorize( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$prefix = '/' . HE_V2_API::NS;
		$route = $request->get_route();
		if ( 0 !== strpos( $route, $prefix ) ) {
			return $response;
		}

		$cap = '';
		if ( $prefix . '/health' === $route ) {
			$cap = HE_V2_Auth::CAP_REPAIR;
		} elseif ( $prefix . '/duplicates' === $route || $prefix . '/merge' === $route ) {
			$cap = HE_V2_Auth::CAP_TAXONOMY;
		} elseif ( $prefix . '/repair' === $route || $prefix . '/operations/reindex' === $route ) {
			$cap = HE_V2_Auth::CAP_REPAIR;
		} elseif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/dataset-access/\\d+/approve$#', $route ) ) {
			$cap = HE_V2_Auth::CAP_DATASET;
		} elseif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/(?:integrity|research-integrity)/\\d+/(?:apply|transition)$#', $route ) ) {
			$cap = false !== strpos( $route, '/transition' ) ? HE_V2_Auth::CAP_REVIEW : HE_V2_Auth::CAP_PUBLISH;
		} elseif ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/research/\\d+/review$#', $route ) ) {
			$cap = HE_V2_Auth::CAP_REVIEW;
		}

		if ( ! $cap ) {
			return $response;
		}
		$allowed = HE_V2_Auth::rest_permission( $cap );
		return is_wp_error( $allowed ) ? $allowed : $response;
	}
}

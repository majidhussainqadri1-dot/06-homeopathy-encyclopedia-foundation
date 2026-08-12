<?php
/** Normalize successful defense-in-depth permission checks back to the REST callback path. */
defined( 'ABSPATH' ) || exit;

final class HE_V241_Before_Callback_Normalizer {
	public static function hooks() {
		/* Runs after v2.4.1 object-scope guards and before callbacks execute. */
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'normalize' ), 342, 3 );
	}

	public static function normalize( $response, $handler, $request ) {
		if ( true !== $response || ! $request instanceof WP_REST_Request || 'GET' === $request->get_method() ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		$direct_permission_routes = array(
			'#^' . preg_quote( $prefix, '#' ) . '/integrity/\d+/apply$#',
			'#^' . preg_quote( $prefix, '#' ) . '/research/\d+/transition$#',
			'#^' . preg_quote( $prefix, '#' ) . '/dataset-access/\d+/approve$#',
			'#^' . preg_quote( $prefix, '#' ) . '/future/claims$#',
			'#^' . preg_quote( $prefix, '#' ) . '/future/(?:mappings|duplicates/scan)$#',
			'#^' . preg_quote( $prefix, '#' ) . '/future/impact/[0-9a-fA-F-]{36}$#',
			'#^' . preg_quote( $prefix, '#' ) . '/future/translations/[0-9a-fA-F-]{36}$#',
			'#^' . preg_quote( $prefix, '#' ) . '/future/external/lookup$#',
			'#^' . preg_quote( $prefix, '#' ) . '/future/external/\d+/review$#',
		);
		foreach ( $direct_permission_routes as $pattern ) {
			if ( preg_match( $pattern, $route ) ) {
				return null;
			}
		}
		return $response;
	}
}

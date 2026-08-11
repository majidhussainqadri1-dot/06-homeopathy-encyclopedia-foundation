<?php
/** File 06 v2.4.3 canonical public translation DTO guard. */
defined( 'ABSPATH' ) || exit;

final class HE_V242_Public_Translation_Guard {
	public static function hooks() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_callbacks' ), 650, 3 );
	}

	public static function after_callbacks( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response || is_wp_error( $response ) ) { return $response; }
		$route = $request->get_route();
		if ( ! preg_match( '#^/' . preg_quote( HE_V2_API::NS, '#' ) . '/future/public/translations/([a-fA-F0-9-]{36})$#', $route, $match ) ) { return $response; }
		$concept = HE_V2_Domain::concept_by_id( strtolower( $match[1] ), false );
		if ( ! $concept ) { return $response; }
		global $wpdb;
		$number = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT version_number FROM ' . HE_V2_Schema::table( 'versions' ) . ' WHERE id=%d AND concept_id=%d', (int) $concept['current_version'], (int) $concept['id'] ) );
		$data = $response->get_data();
		if ( ! is_array( $data ) ) { return $response; }
		unset( $data['source_version'] );
		$data['source_version_number'] = $number;
		foreach ( array( 'translations', 'items' ) as $collection ) {
			if ( ! isset( $data[ $collection ] ) || ! is_array( $data[ $collection ] ) ) { continue; }
			foreach ( $data[ $collection ] as &$item ) {
				if ( ! is_array( $item ) ) { continue; }
				unset( $item['source_version'] );
				$item['source_version_number'] = $number;
			}
			unset( $item );
		}
		$response->set_data( $data );
		return $response;
	}
}

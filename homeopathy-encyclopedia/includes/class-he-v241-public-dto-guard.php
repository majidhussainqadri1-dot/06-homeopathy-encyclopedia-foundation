<?php
/** File 06 v2.4.1 public DTO hardening for the core v2 API. */
defined( 'ABSPATH' ) || exit;

final class HE_V241_Public_DTO_Guard {
	public static function hooks() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 345, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_callbacks' ), 345, 3 );
	}

	public static function before_callbacks( $response, $handler, $request ) {
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		/* Canonical public UUID/slug identifiers are the public contract; raw DB IDs are not. */
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/\d+(?:$|/(?:versions|diff|bookmark|aliases|references|review|transition|integrity))#', $route )
			|| preg_match( '#^' . preg_quote( $prefix, '#' ) . '/graph/\d+$#', $route ) ) {
			return new WP_Error( 'he_canonical_public_id_required', __( 'Use the canonical File 06 public identifier or canonical slug; internal numeric identifiers are not an API contract.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );
		}
		return $response;
	}

	public static function after_callbacks( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request || ! $response instanceof WP_REST_Response || is_wp_error( $response ) || 'GET' !== $request->get_method() ) {
			return $response;
		}
		$route = $request->get_route();
		$prefix = '/' . HE_V2_API::NS;
		$data = $response->get_data();

		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/[^/]+$#', $route ) && is_array( $data ) ) {
			$data = self::sanitize_entry_dto( $data );
			$response->set_data( $data );
			return $response;
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/entries/[^/]+/versions$#', $route ) && is_array( $data ) ) {
			foreach ( $data as &$version ) {
				if ( is_array( $version ) ) {
					unset( $version['id'] );
					unset( $version['created_by'] );
				}
			}
			$response->set_data( $data );
			return $response;
		}
		if ( preg_match( '#^' . preg_quote( $prefix, '#' ) . '/graph/[^/]+$#', $route ) && is_array( $data ) && isset( $data['edges'] ) ) {
			$data['edges'] = self::public_graph_edges( (array) $data['edges'] );
			$response->set_data( $data );
		}
		return $response;
	}

	private static function sanitize_entry_dto( $data ) {
		if ( isset( $data['references'] ) && is_array( $data['references'] ) ) {
			foreach ( $data['references'] as &$reference ) {
				if ( is_array( $reference ) ) {
					unset( $reference['id'] );
				}
			}
		}
		if ( isset( $data['integrity_notices'] ) && is_array( $data['integrity_notices'] ) ) {
			global $wpdb;
			foreach ( $data['integrity_notices'] as &$notice ) {
				if ( ! is_array( $notice ) ) { continue; }
				$replacement = absint( $notice['replacement_object_id'] ?? 0 );
				unset( $notice['replacement_object_id'] );
				if ( $replacement ) {
					$public = $wpdb->get_var( $wpdb->prepare( 'SELECT public_id FROM ' . HE_V2_Schema::table( 'concepts' ) . ' WHERE id=%d', $replacement ) );
					if ( $public ) { $notice['replacement_id'] = $public; }
				}
			}
		}
		return $data;
	}

	private static function public_graph_edges( $edges ) {
		global $wpdb;
		$ids = array();
		foreach ( $edges as $edge ) {
			if ( is_array( $edge ) ) {
				$ids[] = absint( $edge['source'] ?? 0 );
				$ids[] = absint( $edge['target'] ?? 0 );
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		$map = array();
		if ( $ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,public_id FROM ' . HE_V2_Schema::table( 'concepts' ) . " WHERE id IN ({$placeholders})", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rows as $row ) { $map[ (int) $row['id'] ] = $row['public_id']; }
		}
		$out = array();
		foreach ( $edges as $edge ) {
			if ( ! is_array( $edge ) ) { continue; }
			$source = absint( $edge['source'] ?? 0 );
			$target = absint( $edge['target'] ?? 0 );
			if ( empty( $map[ $source ] ) || empty( $map[ $target ] ) ) { continue; }
			$edge['source'] = $map[ $source ];
			$edge['target'] = $map[ $target ];
			$out[] = $edge;
		}
		return $out;
	}
}

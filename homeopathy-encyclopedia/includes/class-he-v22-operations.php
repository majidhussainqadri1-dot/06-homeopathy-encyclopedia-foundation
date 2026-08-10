<?php
/** Correct operational status/assurance projection for v2.2. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Operations {
	public static function hooks() {
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance_provider' ), 140 );
	}

	public static function health() {
		global $wpdb;
		$health = HE_V22_Governance::health();
		$outbox = HE_V2_Schema::table( 'outbox' );
		$health['dead_letter'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$outbox} WHERE status='dead-letter'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$health['outbox_pending'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$outbox} WHERE status IN ('pending','retry')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$health['outbox_delivered'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$outbox} WHERE status='delivered'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$health['safe_mode'] = (bool) get_option( HE_V2_Schema::OPTION_SAFE_MODE );
		$health['plugin_version'] = HE_VERSION;
		$health['schema_version_expected'] = HE_SCHEMA_VERSION;
		$health['contract_version'] = HE_CONTRACT_VERSION;
		return $health;
	}

	public static function assurance_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		if ( isset( $providers['file-06'] ) ) {
			$providers['file-06']['health'] = array( __CLASS__, 'health' );
			$providers['file-06']['dead_letter_status'] = 'dead-letter';
			$providers['file-06']['native_enforcement_preserved'] = true;
		}
		return $providers;
	}
}

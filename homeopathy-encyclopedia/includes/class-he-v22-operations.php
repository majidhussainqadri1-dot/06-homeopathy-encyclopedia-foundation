<?php
/** Correct operational status, repair and assurance projection for v2.2. */
defined( 'ABSPATH' ) || exit;

final class HE_V22_Operations {
	public static function hooks() {
		add_filter( 'sabri_security_assurance_providers', array( __CLASS__, 'assurance_provider' ), 140 );
		add_action( 'admin_menu', array( __CLASS__, 'replace_operations_page' ), 100 );
		add_action( 'admin_post_he_v2_repair', array( __CLASS__, 'admin_repair' ), 1 );
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

	public static function assurance_evidence() {
		global $wpdb;
		$counts = array();
		foreach ( array( 'concepts', 'aliases', 'versions', 'references', 'relations', 'reviews', 'integrity_actions', 'research', 'dataset_access', 'events', 'outbox', 'idempotency', 'search_index', 'bookmarks', 'rate_limits', 'migration_quarantine' ) as $suffix ) {
			$table = HE_V2_Schema::table( $suffix );
			$counts[ $suffix ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return array(
			'generated_at' => gmdate( 'c' ),
			'owner' => 'file-06',
			'contract_version' => HE_CONTRACT_VERSION,
			'health' => self::health(),
			'counts' => $counts,
			'retention' => array(
				'canonical-knowledge' => 'permanent-versioned',
				'research-record' => 'institutional-record-with-governed-correction',
				'dataset-access' => 'purpose-lawful-basis-expiry',
				'idempotency' => 'bounded-short-lived',
			),
			'public_private_boundary' => 'explicit-dto-allowlists',
			'native_enforcement_preserved' => true,
		);
	}

	public static function assurance_provider( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		if ( isset( $providers['file-06'] ) ) {
			$providers['file-06']['health'] = array( __CLASS__, 'health' );
			$providers['file-06']['evidence_query'] = array( __CLASS__, 'assurance_evidence' );
			$providers['file-06']['dead_letter_status'] = 'dead-letter';
			$providers['file-06']['native_enforcement_preserved'] = true;
			$providers['file-06']['identity_authority'] = 'file-00';
			$providers['file-06']['assurance_owner'] = 'file-24';
		}
		return $providers;
	}

	public static function replace_operations_page() {
		$parent = 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE;
		remove_submenu_page( $parent, 'he-v2-operations' );
		add_submenu_page(
			$parent,
			__( 'Encyclopedia Operations', 'homeopathy-encyclopedia' ),
			__( 'Operations', 'homeopathy-encyclopedia' ),
			HE_V2_Auth::CAP_REPAIR,
			'he-v2-operations',
			array( __CLASS__, 'operations_page' )
		);
	}

	public static function operations_page() {
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR ) ) {
			wp_die( esc_html__( 'You are not authorized to operate File 06.', 'homeopathy-encyclopedia' ) );
		}
		$health = self::health();
		$status = isset( $health['status'] ) ? $health['status'] : ( ! empty( $health['file00_authority_ready'] ) ? 'ready' : 'degraded' );
		?>
		<div class="wrap he-v2">
			<h1><?php esc_html_e( 'File 06 Operations — v2.2', 'homeopathy-encyclopedia' ); ?></h1>
			<p><?php esc_html_e( 'Read-first health, resumable migration, quarantine visibility, bounded repair/reindex, outbox/dead-letter evidence and reversible safe mode.', 'homeopathy-encyclopedia' ); ?></p>
			<div class="he-v2__grid">
				<div class="he-v2__card"><h2><?php esc_html_e( 'Runtime', 'homeopathy-encyclopedia' ); ?></h2><p><strong><?php echo esc_html( $status ); ?></strong></p><p><?php echo esc_html( sprintf( 'Plugin %1$s · Schema %2$d · Contract %3$s', HE_VERSION, HE_SCHEMA_VERSION, HE_CONTRACT_VERSION ) ); ?></p></div>
				<div class="he-v2__card"><h2><?php esc_html_e( 'Identity authority', 'homeopathy-encyclopedia' ); ?></h2><p><?php echo ! empty( $health['file00_authority_ready'] ) ? esc_html__( 'File 00 ready', 'homeopathy-encyclopedia' ) : esc_html__( 'File 00 unavailable — protected actions fail closed', 'homeopathy-encyclopedia' ); ?></p></div>
				<div class="he-v2__card"><h2><?php esc_html_e( 'Outbox', 'homeopathy-encyclopedia' ); ?></h2><p><?php echo esc_html( sprintf( __( '%1$d pending/retry · %2$d dead-letter · %3$d delivered', 'homeopathy-encyclopedia' ), (int) $health['outbox_pending'], (int) $health['dead_letter'], (int) $health['outbox_delivered'] ) ); ?></p></div>
				<div class="he-v2__card"><h2><?php esc_html_e( 'Migration', 'homeopathy-encyclopedia' ); ?></h2><p><?php echo esc_html( sprintf( __( 'Done: %1$s · Cursor: %2$d · Unresolved quarantine: %3$d', 'homeopathy-encyclopedia' ), ! empty( $health['legacy_migration']['done'] ) ? __( 'yes', 'homeopathy-encyclopedia' ) : __( 'no', 'homeopathy-encyclopedia' ), (int) ( $health['legacy_migration']['cursor'] ?? 0 ), (int) $health['quarantine_unresolved'] ) ); ?></p></div>
			</div>
			<div class="he-v2__actions">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="he_v2_repair"><?php wp_nonce_field( 'he_v2_repair' ); ?><button class="button" name="dry_run" value="1"><?php esc_html_e( 'Preview v2.2 repair', 'homeopathy-encyclopedia' ); ?></button><button class="button button-primary" name="dry_run" value="0"><?php esc_html_e( 'Run bounded v2.2 repair', 'homeopathy-encyclopedia' ); ?></button></form>
			</div>
			<pre class="he-v2__code"><?php echo esc_html( wp_json_encode( $health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
		</div>
		<?php
	}

	public static function admin_repair() {
		check_admin_referer( 'he_v2_repair' );
		if ( ! HE_V2_Auth::can( HE_V2_Auth::CAP_REPAIR ) ) {
			wp_die( esc_html__( 'Not authorized.', 'homeopathy-encyclopedia' ) );
		}
		$dry_run = ! empty( $_POST['dry_run'] );
		$result = HE_V22_Governance::repair( $dry_run );
		set_transient( 'he_v2_admin_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => wp_json_encode( $result ) ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . HE_V2_Domain::ENTRY_TYPE . '&page=he-v2-operations' ) );
		exit;
	}
}

<?php
/**
 * Plugin Name: Homeopathy Encyclopedia Foundation
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Governed encyclopedia entries, relationships, search, bookmarks, corrections, moderation, and privacy controls for the Sabri Social Homeopathy Platform.
 * Version: 1.0.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: homeopathy-encyclopedia
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'HE_VERSION', '1.0.0' );
define( 'HE_SCHEMA_VERSION', 2 );
define( 'HE_FILE', __FILE__ );
define( 'HE_DIR', plugin_dir_path( __FILE__ ) );
define( 'HE_URL', plugin_dir_url( __FILE__ ) );

require_once HE_DIR . 'includes/class-he-dependencies.php';
require_once HE_DIR . 'includes/class-he-database.php';
require_once HE_DIR . 'includes/class-he-permissions.php';
require_once HE_DIR . 'includes/class-he-content.php';
require_once HE_DIR . 'includes/class-he-activator.php';
require_once HE_DIR . 'includes/class-he-publishing.php';
require_once HE_DIR . 'includes/class-he-catalog.php';
require_once HE_DIR . 'includes/class-he-interactions.php';
require_once HE_DIR . 'includes/class-he-comments.php';
require_once HE_DIR . 'includes/class-he-admin.php';
require_once HE_DIR . 'includes/class-he-privacy.php';
require_once HE_DIR . 'includes/class-he-seo.php';
require_once HE_DIR . 'includes/class-he-plugin.php';

register_activation_hook( HE_FILE, array( 'HE_Activator', 'activate' ) );
register_deactivation_hook( HE_FILE, array( 'HE_Activator', 'deactivate' ) );

/** Start only when the authoritative platform contracts are available. */
function he_start_plugin() {
	load_plugin_textdomain( 'homeopathy-encyclopedia', false, dirname( plugin_basename( HE_FILE ) ) . '/languages' );

	if ( ! HE_Dependencies::ready() ) {
		HE_Dependencies::register_failure_notice();
		return;
	}

	try {
		HE_Database::maybe_upgrade();
	} catch ( Throwable $error ) {
		update_option(
			'he_runtime_failure',
			array(
				'time'  => gmdate( 'c' ),
				'error' => sanitize_text_field( $error->getMessage() ),
			),
			false
		);
		HE_Dependencies::audit( 'runtime_migration_failed', array( 'error' => $error->getMessage() ) );
		HE_Dependencies::register_runtime_failure_notice();
		return;
	}

	delete_option( 'he_runtime_failure' );
	( new HE_Plugin() )->run();
}
add_action( 'plugins_loaded', 'he_start_plugin', 30 );

<?php
/**
 * Plugin Name: Homeopathy Encyclopedia Foundation
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Searchable American English knowledge entries, relationships, bookmarks, corrections and moderation.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: homeopathy-encyclopedia
 */

defined( 'ABSPATH' ) || exit;
define( 'HE_VERSION', '0.1.0' ); define( 'HE_FILE', __FILE__ ); define( 'HE_DIR', plugin_dir_path( __FILE__ ) ); define( 'HE_URL', plugin_dir_url( __FILE__ ) );
require_once HE_DIR . 'includes/class-he-permissions.php'; require_once HE_DIR . 'includes/class-he-content.php'; require_once HE_DIR . 'includes/class-he-activator.php'; require_once HE_DIR . 'includes/class-he-publishing.php'; require_once HE_DIR . 'includes/class-he-catalog.php'; require_once HE_DIR . 'includes/class-he-interactions.php'; require_once HE_DIR . 'includes/class-he-comments.php'; require_once HE_DIR . 'includes/class-he-admin.php'; require_once HE_DIR . 'includes/class-he-privacy.php'; require_once HE_DIR . 'includes/class-he-seo.php'; require_once HE_DIR . 'includes/class-he-plugin.php';
register_activation_hook( HE_FILE, array( 'HE_Activator', 'activate' ) ); register_deactivation_hook( HE_FILE, array( 'HE_Activator', 'deactivate' ) );
function he_start_plugin() { ( new HE_Plugin() )->run(); } add_action( 'plugins_loaded', 'he_start_plugin' );


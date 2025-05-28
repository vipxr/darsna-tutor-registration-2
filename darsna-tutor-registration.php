<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 4.0.0
 * Description: Ultra-optimized tutor checkout with LatePoint v5 API integration
 * Requires PHP: 7.4
 * Author: Darsna
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'DARSNA_TUTOR_VERSION', '4.0.0' );
define( 'DARSNA_TUTOR_FILE', __FILE__ );
define( 'DARSNA_TUTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DARSNA_TUTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'DARSNA_TUTOR_BASENAME', plugin_basename( __FILE__ ) );

// Load the main plugin class
require_once DARSNA_TUTOR_PATH . 'includes/class-darsna-tutor-main.php';

/**
 * Initialize the plugin
 */
function darsna_tutor_init() {
    Darsna_Tutor_Main::instance();
}
add_action( 'plugins_loaded', 'darsna_tutor_init' );

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, 'darsna_tutor_activate' );
function darsna_tutor_activate() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Create any necessary database tables or options here if needed
    // For now, we'll just ensure the plugin is properly initialized
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, 'darsna_tutor_deactivate' );
function darsna_tutor_deactivate() {
    // Clean up any temporary data
    flush_rewrite_rules();
}
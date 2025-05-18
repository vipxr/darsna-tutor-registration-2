<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint
 * Plugin URI: darsna.com
 * Description: Integrates user registration on WooCommerce with LatePoint plugin to create tutors and students with subject selection.
 * Version: 1.0.1
 * Author: Darsna
 * Author URI: 
 * Text Domain: darsna-tutor-reg
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'DARSNA_TUTOR_REG_VERSION', '1.0.1' );
define( 'DARSNA_TUTOR_REG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DARSNA_TUTOR_REG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DARSNA_URGENT_HELP_SERVICE_ID', 1); // Service ID for urgent help in LatePoint

// Include main plugin class
require_once DARSNA_TUTOR_REG_PLUGIN_DIR . 'includes/class-darsna-tutor-registration.php';

/**
 * Check if LatePoint plugin is properly loaded with all required classes.
 * 
 * @since    1.0.1
 * @return   boolean  True if LatePoint is fully loaded, false otherwise.
 */
function darsna_tutor_reg_is_latepoint_loaded() {
    return class_exists('LatePointAgentModel');
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks, 
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function darsna_tutor_reg_run() {
    // Only initialize if LatePoint is properly loaded
    if (darsna_tutor_reg_is_latepoint_loaded()) {
        $plugin = new Darsna_Tutor_Registration();
        $plugin->run();
    } else {
        // Log the issue but don't prevent plugin from loading basic functionality
        error_log('Darsna Tutor Reg: LatePoint plugin classes not fully loaded yet. Some functionality may be limited.');
        // We'll still initialize the plugin but with limited functionality
        $plugin = new Darsna_Tutor_Registration();
        $plugin->run();
    }
}

// Try to initialize as late as possible to ensure LatePoint is loaded
add_action('plugins_loaded', 'darsna_tutor_reg_run', PHP_INT_MAX);

// Fallback initialization if LatePoint still isn't loaded by plugins_loaded
add_action('wp_loaded', 'darsna_tutor_reg_run', PHP_INT_MAX);
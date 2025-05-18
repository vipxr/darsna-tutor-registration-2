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

// Debugging function to log potential null values
function darsna_debug_log($message) {
    error_log('[DARSNA DEBUG] ' . $message);
}

// Add an error handler to get more detailed information
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (strpos($errstr, 'strlen(): Passing null to parameter') !== false) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $traceLog = "Error: $errstr in $errfile on line $errline\n";
        $traceLog .= "Backtrace:\n";
        foreach ($trace as $i => $step) {
            $traceLog .= "#$i " . 
                        (isset($step['file']) ? $step['file'] : '[internal function]') . 
                        (isset($step['line']) ? "({$step['line']})" : '') . 
                        ": " . 
                        (isset($step['class']) ? $step['class'] . $step['type'] : '') . 
                        $step['function'] . "()\n";
        }
        error_log($traceLog);
        return false;
    }
    return false;
}, E_DEPRECATED);

// Include main plugin class
require_once DARSNA_TUTOR_REG_PLUGIN_DIR . 'includes/class-darsna-tutor-registration.php';

/**
 * Check if LatePoint plugin is properly loaded with all required classes.
 * 
 * @since    1.0.1
 * @return   boolean  True if LatePoint is fully loaded, false otherwise.
 */
function darsna_tutor_reg_is_latepoint_loaded() {
    if (!did_action('latepoint_init') && !did_action('latepoint_loaded')) {
        return false;
    }
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
    static $plugin_initialized = false;
    
    // Only initialize once
    if ($plugin_initialized) {
        return;
    }
    
    $plugin_initialized = true;
    $plugin = new Darsna_Tutor_Registration();
    $plugin->run();
}

// Remove the existing hooks
// add_action('plugins_loaded', 'darsna_tutor_reg_run', PHP_INT_MAX);
// add_action('wp_loaded', 'darsna_tutor_reg_run', PHP_INT_MAX);

// Use later hooks
add_action('wp', 'darsna_tutor_reg_run');
add_action('admin_init', 'darsna_tutor_reg_run');

// For AJAX requests
add_action('wp_ajax_nopriv_any_action', 'darsna_tutor_reg_run', 5);
add_action('wp_ajax_any_action', 'darsna_tutor_reg_run', 5);
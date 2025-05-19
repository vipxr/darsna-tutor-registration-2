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
    $latepoint_init_done = did_action('latepoint_init');
    $latepoint_loaded_done = did_action('latepoint_loaded');
    $agent_model_exists = class_exists('LatePointAgentModel');
    $service_model_exists = class_exists('LatePointServiceModel'); // Added check for another core LP class
    $settings_class_exists = class_exists('LatePointSettingsHelper'); // Added check for settings class

    if (!$latepoint_init_done && !$latepoint_loaded_done) {
        darsna_debug_log('LatePoint check: Neither latepoint_init nor latepoint_loaded action has run.');
        return false;
    }
    if (!$agent_model_exists) {
        darsna_debug_log('LatePoint check: LatePointAgentModel class does not exist.');
    }
    if (!$service_model_exists) {
        darsna_debug_log('LatePoint check: LatePointServiceModel class does not exist.');
    }
    if (!$settings_class_exists) {
        darsna_debug_log('LatePoint check: LatePointSettingsHelper class does not exist.');
    }

    // Consider LatePoint loaded if its core actions have run and key classes are available
    $is_loaded = ($latepoint_init_done || $latepoint_loaded_done) && $agent_model_exists && $service_model_exists && $settings_class_exists;
    if ($is_loaded) {
        darsna_debug_log('LatePoint check: Considered loaded. latepoint_init: ' . ($latepoint_init_done ? 'yes' : 'no') . ', latepoint_loaded: ' . ($latepoint_loaded_done ? 'yes' : 'no') . ', AgentModel: yes, ServiceModel: yes, SettingsHelper: yes');
    } else {
        darsna_debug_log('LatePoint check: Considered NOT loaded. latepoint_init: ' . ($latepoint_init_done ? 'yes' : 'no') . ', latepoint_loaded: ' . ($latepoint_loaded_done ? 'yes' : 'no') . ', AgentModel: ' . ($agent_model_exists ? 'yes' : 'no') . ', ServiceModel: ' . ($service_model_exists ? 'yes' : 'no') . ', SettingsHelper: ' . ($settings_class_exists ? 'yes' : 'no'));
    }
    return $is_loaded;
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

// Use later hooks with priority
add_action('wp_loaded', 'darsna_tutor_reg_run', 20);
add_action('admin_init', 'darsna_tutor_reg_run', 20);

// For AJAX requests
add_action('wp_ajax_nopriv_any_action', 'darsna_tutor_reg_run', 5);
add_action('wp_ajax_any_action', 'darsna_tutor_reg_run', 5);

// Add direct retry mechanism
if (!function_exists('darsna_check_pending_syncs')) {
    function darsna_check_pending_syncs() {
        // Check if LatePoint is likely loaded enough to have its classes available
        // and if the Darsna_Tutor_Registration class (and its public methods) are available
        if (class_exists('LatePointAgentModel') && class_exists('Darsna_Tutor_Registration')) {
            $pending_syncs = get_option('darsna_pending_latepoint_syncs', array());
            if (!empty($pending_syncs)) {
                darsna_debug_log("darsna_check_pending_syncs: Found " . count($pending_syncs) . " pending LatePoint syncs on init.");
                
                // Get an instance of the public class to call process_pending_latepoint_syncs
                // This assumes Darsna_Tutor_Registration has a way to get an instance of Darsna_Tutor_Reg_Public
                // or that process_pending_latepoint_syncs can be called statically or via a global instance.
                // For simplicity, if Darsna_Tutor_Registration instantiates Darsna_Tutor_Reg_Public and stores it,
                // we might need a more robust way to access it here.
                // A common pattern is to have a static getter or a global instance.

                // Simpler approach: Directly instantiate Darsna_Tutor_Reg_Public if its constructor is light
                // and it doesn't rely on the main plugin class's full initialization for this specific task.
                // This requires Darsna_Tutor_Reg_Public to be included.
                if (file_exists(DARSNA_TUTOR_REG_PLUGIN_DIR . 'public/class-darsna-tutor-reg-public.php')) {
                    require_once DARSNA_TUTOR_REG_PLUGIN_DIR . 'public/class-darsna-tutor-reg-public.php';
                    if (class_exists('Darsna_Tutor_Reg_Public')) {
                        $public_handler = new Darsna_Tutor_Reg_Public(DARSNA_TUTOR_REG_VERSION, 'darsna-tutor-reg'); // Adjust constructor params if needed
                        $public_handler->process_pending_latepoint_syncs();
                        darsna_debug_log("darsna_check_pending_syncs: Called process_pending_latepoint_syncs directly.");
                    } else {
                        darsna_debug_log("darsna_check_pending_syncs: Darsna_Tutor_Reg_Public class not found after include.");
                    }
                } else {
                     darsna_debug_log("darsna_check_pending_syncs: Darsna_Tutor_Reg_Public class file not found.");
                }
            }
        } else {
            if (!class_exists('LatePointAgentModel')) {
                darsna_debug_log("darsna_check_pending_syncs: LatePointAgentModel not available yet.");
            }
            if (!class_exists('Darsna_Tutor_Registration')) {
                 darsna_debug_log("darsna_check_pending_syncs: Darsna_Tutor_Registration class not available yet.");
            }
        }
    }
}
// add_action('init', 'darsna_check_pending_syncs', 999); // Run late on init
<?php
/**
 * Plugin Name: Darsna Tutor Registration
 * Plugin URI: https://wordpress.com/
 * Description: Tutor registration system with WooCommerce integration for payments and LatePoint for scheduling. Includes commission-based revenue model.
 * Version: 4.2.7
 * Author: Wordpress
 * Author URI: https://wordpress.com
 * Text Domain: darsna-tutor-registration
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * 
 * @package Darsna_Tutor_Registration
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('DARSNA_TUTOR_REGISTRATION_VERSION', '1.0.0');
define('DARSNA_TUTOR_REGISTRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DARSNA_TUTOR_REGISTRATION_PLUGIN_URL', plugin_dir_url(__FILE__));
// Legacy constants for backward compatibility
define('DARSNA_TUTOR_PATH', DARSNA_TUTOR_REGISTRATION_PLUGIN_DIR);
define('DARSNA_TUTOR_URL', DARSNA_TUTOR_REGISTRATION_PLUGIN_URL);
define('DARSNA_TUTOR_FILE', __FILE__);

/**
 * Main plugin class
 */
class Darsna_Tutor_Registration {
    
    /** @var Darsna_Tutor_Registration Single instance */
    private static $instance = null;
    
    /** @var array Loaded classes */
    private $classes = [];
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check requirements
        if ( ! $this->check_requirements() ) {
            return;
        }
        
        // Load plugin text domain for translations
        load_plugin_textdomain('darsna-tutor-registration', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        $errors = [];
        
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.6', '<' ) ) {
            $errors[] = __( 'WordPress 5.6 or higher is required for Darsna Tutor Registration.', 'darsna-tutor-registration' );
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $errors[] = __( 'PHP 7.4 or higher is required for Darsna Tutor Registration.', 'darsna-tutor-registration' );
        }
        
        // Check WooCommerce - REQUIRED
        if ( ! class_exists( 'WooCommerce' ) ) {
            $errors[] = __( 'WooCommerce is required for Darsna Tutor Registration to work.', 'darsna-tutor-registration' );
        } else {
            // Check WooCommerce version
            if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.0', '<' ) ) {
                $errors[] = __( 'WooCommerce 4.0 or higher is required.', 'darsna-tutor-registration' );
            }
        }
        
        // Check LatePoint - RECOMMENDED (show warning if missing)
        if ( ! class_exists( 'LatePoint' ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Darsna Tutor Registration:</strong> ' . 
                     __( 'LatePoint plugin is recommended for full scheduling functionality. Some features may be limited without it.', 'darsna-tutor-registration' ) . 
                     '</p></div>';
            } );
        }
        
        // Show critical errors and prevent plugin loading
        if ( ! empty( $errors ) ) {
            add_action( 'admin_notices', function() use ( $errors ) {
                foreach ( $errors as $error ) {
                    echo '<div class="notice notice-error"><p><strong>Darsna Tutor Registration:</strong> ' . 
                         esc_html( $error ) . '</p></div>';
                }
            } );
            
            // Deactivate plugin if critical requirements are not met
            add_action( 'admin_init', function() {
                deactivate_plugins( plugin_basename( __FILE__ ) );
            } );
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        $includes_path = DARSNA_TUTOR_PATH . 'includes/';
        
        // Core classes
        require_once $includes_path . 'class-darsna-notification-system.php';
        // LatePoint integration functions have been moved to their respective classes
        require_once $includes_path . 'class-darsna-service-sync.php';
        require_once $includes_path . 'class-darsna-commission-system.php';
        require_once $includes_path . 'class-darsna-admin-dashboard.php';
        require_once $includes_path . 'class-darsna-tutor-tutors-page.php';
        
        
    }
    
    /**
     * Initialize admin functionality
     */
    private function init_admin_functionality() {
        // Admin-specific initialization
        $admin_file = DARSNA_TUTOR_REGISTRATION_PLUGIN_DIR . 'includes/admin/class-darsna-admin-dashboard.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
            if (class_exists('Darsna_Admin_Dashboard')) {
                $this->classes['admin'] = new Darsna_Admin_Dashboard();
            }
        }
    }
    
    /**
     * Initialize plugin components
     * Note: All dependencies are already verified in check_requirements()
     */
    private function init_components() {
        // Notification System (initialize first for other components to use)
        $this->classes['notification'] = Darsna_Notification_System::get_instance();
        
        // Registration Form Handler (must be initialized first)
        require_once DARSNA_TUTOR_PATH . 'includes/class-darsna-registration-system.php';
        $this->classes['form_handler'] = Darsna_Registration_System::get_instance();
        
        // WooCommerce Integration - WooCommerce is guaranteed to be available
        $this->init_woocommerce_hooks();
        
        // Service Sync (initialize if LatePoint is available)
        if ( class_exists( 'LatePoint' ) ) {
            $this->classes['service_sync'] = new Darsna_Service_Sync();
        }
        
        // Commission System
        $this->classes['commission'] = new Darsna_Commission_System();
        
        // Initialize admin functionality if in admin area
        if ( is_admin() ) {
            $this->init_admin_functionality();
        }
        
        // Tutors Display Page
        $this->classes['tutors_page'] = new Darsna_Tutor_Tutors_Page();
        
        // Handle commission recording when orders are created
        // WooCommerce hooks are safe to use since WooCommerce is verified
        add_action( 'woocommerce_payment_complete', [ $this, 'record_commission_on_payment' ], 20 );
    }
    
    /**
     * Initialize WooCommerce hooks
     * Note: WooCommerce availability is already verified in check_requirements()
     */
    private function init_woocommerce_hooks() {
        // Order and commission handling
        $commission_system = new Darsna_Commission_System();
        add_action( 'woocommerce_order_refunded', [ $commission_system, 'handle_order_refund' ], 10, 2 );
        
        // Admin functionality - use admin dashboard
        if ( isset( $this->classes['admin'] ) ) {
            add_action( 'admin_notices', [ $this->classes['admin'], 'show_pending_tutors_notice' ] );
            add_action( 'admin_action_approve_tutor', [ $this->classes['admin'], 'handle_tutor_approval' ] );
        }
        
        
    }
    
    /**
     * Record commission when payment is complete
     */
    public function record_commission_on_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
            
            $agent_id = $order->get_meta( '_darsna_agent_id' );
            $commission_rate = $order->get_meta( '_darsna_commission_rate' );
            
            if ( $agent_id && $commission_rate && isset( $this->classes['commission'] ) ) {
                $this->classes['commission']->record_commission( 
                    $order_id, 
                    $agent_id, 
                    $order->get_total(), 
                    $commission_rate 
                );
            }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Add default options
        add_option('darsna_tutor_registration_enabled', 'yes');
        add_option('darsna_tutor_registration_auto_approve', 'no');
        add_option('darsna_tutor_registration_email_notifications', 'yes');
        
        // Create custom user roles
        $this->create_user_roles();
        
        // Create database tables
        $this->create_database_tables();
        
        // Schedule cron events
        if ( ! wp_next_scheduled( 'darsna_weekly_payout_summary' ) ) {
            wp_schedule_event( strtotime( 'next monday 9am' ), 'weekly', 'darsna_weekly_payout_summary' );
        }
        
        // Flush rewrite rules to ensure custom post types work
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        if ( class_exists( 'Darsna_Commission_System' ) ) {
            $commission_system = new Darsna_Commission_System();
            if ( method_exists( $commission_system, 'ensure_tables_exist' ) ) {
                $success = $commission_system->ensure_tables_exist();
            } else if ( method_exists( $commission_system, 'create_tables' ) ) {
                $success = $commission_system->create_tables();
            } else {
                $success = false;
            }
            
            // Table creation handled silently
        }
    }
    
    /**
     * Manual Database Table Creation
     * 
     * If you need to manually create the darsna_payouts table,
     * you can call this method programmatically
     */
    public function create_tables_manually() {
        $success = false;
        
        if ( class_exists( 'Darsna_Commission_System' ) ) {
            $commission_system = new Darsna_Commission_System();
            if ( method_exists( $commission_system, 'ensure_tables_exist' ) ) {
                $success = $commission_system->ensure_tables_exist();
            } else if ( method_exists( $commission_system, 'create_tables' ) ) {
                $success = $commission_system->create_tables();
            }
        }
        
        return $success;
    }
    
    /**
     * Verify Database Tables
     * 
     * Check if all required tables exist and are properly structured
     */
    public function verify_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'darsna_payouts';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
        
        if ( $table_exists ) {
            $columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
            $column_count = count( $columns );
            
            echo "✓ Table '{$table_name}' exists with {$column_count} columns\n";
            
            // Check for required columns
            $required_columns = ['id', 'agent_id', 'order_id', 'gross_amount', 'platform_fee', 'tutor_earning', 'payout_status'];
            $existing_columns = array_column( $columns, 'Field' );
            
            foreach ( $required_columns as $required_col ) {
                if ( in_array( $required_col, $existing_columns ) ) {
                    echo "  ✓ Column '{$required_col}' exists\n";
                } else {
                    echo "  ✗ Column '{$required_col}' missing\n";
                }
            }
        } else {
            echo "✗ Table '{$table_name}' does not exist\n";
            echo "Run create_tables_manually() to create it.\n";
        }
    }
    
    /**
     * Create custom user roles
     */
    private function create_user_roles() {
        // Add tutor role
        add_role(
            'tutor',
            __('Tutor', 'darsna-tutor-registration'),
            array(
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
            )
        );
        
        // Add tutor manager role
        add_role(
            'tutor_manager',
            __('Tutor Manager', 'darsna-tutor-registration'),
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'manage_tutors' => true,
            )
        );
        
        // Add admin role capabilities
        add_role(
            'darsna_admin',
            __('Darsna Admin', 'darsna-tutor-registration'),
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => true,
                'manage_tutors' => true,
                'manage_commissions' => true,
                'view_earnings' => true,
            )
        );
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'darsna_weekly_payout_summary' );
        wp_clear_scheduled_hook( 'darsna_daily_service_cleanup' );
        
        // Call deactivation methods from other classes
        if ( class_exists( 'Darsna_Service_Sync' ) ) {
            Darsna_Service_Sync::deactivate();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        return self::instance();
    }
}

// Registration class is now loaded from separate file

// Initialize the plugin
add_action('plugins_loaded', function() {
    Darsna_Tutor_Registration::instance();
}, 10);

// Fallback initialization if WooCommerce loads later
add_action('init', function() {
    $reflection = new ReflectionClass('Darsna_Tutor_Registration');
    $instance_property = $reflection->getProperty('instance');
    $instance_property->setAccessible(true);
    
    if ($instance_property->getValue() === null) {
        Darsna_Tutor_Registration::instance();
    }
}, 20);



// Declare HPOS compatibility
add_action( 'before_woocommerce_init', function() {
    // Declare WooCommerce HPOS compatibility
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );
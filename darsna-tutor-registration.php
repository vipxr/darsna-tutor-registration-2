<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.2.0
 * Description: Tutor-only checkout with manual order approval for LatePoint agents
 * Requires PHP: 7.4
 * Author: Darsna
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DARSNA_TUTOR_REG_VERSION', '3.2.0' );
define( 'DARSNA_TUTOR_REG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DARSNA_TUTOR_REG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class Darsna_Tutor_Checkout {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Required plugins for functionality
     */
    private $required_plugins = [
        'woocommerce/woocommerce.php' => 'WooCommerce',
        'woocommerce-subscriptions/woocommerce-subscriptions.php' => 'WooCommerce Subscriptions',
        'latepoint/latepoint.php' => 'LatePoint',
    ];

    /**
     * Valid subscription statuses that should activate agent
     */
    private $active_statuses = [ 'active' ];

    /**
     * Valid subscription statuses that should deactivate agent
     */
    private $inactive_statuses = [ 'pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash' ];

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_scripts' ] );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_checkout_fields' ], 10, 2 );
    }

    /**
     * Initialize plugin functionality
     */
    public function init() {
        // Check dependencies first
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'show_dependency_notice' ] );
            return;
        }

        $this->setup_hooks();
    }

    /**
     * Setup all WordPress hooks
     */
    private function setup_hooks() {
        // Order and payment hooks
        add_filter( 'woocommerce_cod_process_payment_order_status', [ $this, 'force_cod_orders_on_hold' ], 10, 2 );
        add_action( 'woocommerce_subscription_payment_complete', [ $this, 'handle_subscription_payment_complete' ], 5, 1 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completed' ], 10, 1 );

        // Subscription status hooks
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_subscription_status_change' ], 10, 3 );

        // User lifecycle hooks
        add_action( 'delete_user', [ $this, 'handle_user_deletion' ], 10, 1 );
        add_action( 'remove_user_role', [ $this, 'handle_user_role_removal' ], 10, 2 );
        add_action( 'set_user_role', [ $this, 'handle_user_role_change' ], 10, 3 );

        // Frontend hooks
        add_filter( 'wp_nav_menu_items', [ $this, 'add_user_menu_items' ], 99, 2 );
        
        // Admin hooks
        add_action( 'admin_init', [ $this, 'maybe_show_admin_notices' ] );
    }

    /**
     * Check if all required plugins are active
     */
    private function check_dependencies() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( $this->required_plugins as $plugin_file => $plugin_name ) {
            if ( ! is_plugin_active( $plugin_file ) ) {
                $this->log( "Missing required plugin: {$plugin_name}" );
                return false;
            }
        }

        return true;
    }

    /**
     * Show dependency notice in admin
     */
    public function show_dependency_notice() {
        $missing_plugins = [];
        
        foreach ( $this->required_plugins as $plugin_file => $plugin_name ) {
            if ( ! is_plugin_active( $plugin_file ) ) {
                $missing_plugins[] = $plugin_name;
            }
        }

        if ( ! empty( $missing_plugins ) ) {
            printf(
                '<div class="notice notice-error"><p><strong>%s</strong><br>%s: %s</p></div>',
                esc_html__( 'Tutor Registration Plugin Error', 'darsna-tutor-registration' ),
                esc_html__( 'Missing required plugins', 'darsna-tutor-registration' ),
                esc_html( implode( ', ', $missing_plugins ) )
            );
        }
    }

    /**
     * Force all COD orders to on-hold status for manual review
     */
    public function force_cod_orders_on_hold( $status, $order ) {
        $this->log( "COD order #{$order->get_id()} forced to on-hold status" );
        return 'on-hold';
    }

    /**
     * Handle subscription payment complete - ensure COD subscriptions stay on hold
     */
    public function handle_subscription_payment_complete( $subscription ) {
        try {
            $parent_order = $this->get_subscription_parent_order( $subscription );
            
            if ( $parent_order && $parent_order->get_payment_method() === 'cod' ) {
                $subscription->update_status( 'on-hold', 'COD subscription requires manual approval.' );
                $this->log( "COD subscription #{$subscription->get_id()} held for manual review" );
            }
        } catch ( Exception $e ) {
            $this->log( "Error handling subscription payment complete: " . $e->getMessage() );
        }
    }

    /**
     * Handle order completion - activate associated subscriptions and agents
     */
    public function handle_order_completed( $order_id ) {
        try {
            $order = wc_get_order( $order_id );
            
            if ( ! $order || ! $this->order_contains_subscription( $order ) ) {
                return;
            }

            $this->log( "Processing completed order #{$order_id}" );
            
            $subscriptions = $this->get_order_subscriptions( $order );
            
            foreach ( $subscriptions as $subscription ) {
                $this->activate_subscription_and_agent( $subscription );
            }
            
        } catch ( Exception $e ) {
            $this->log( "Error handling order completion: " . $e->getMessage() );
        }
    }

    /**
     * Handle subscription status changes
     */
    public function handle_subscription_status_change( $subscription, $new_status, $old_status ) {
        if ( $new_status === $old_status ) {
            return;
        }

        try {
            $user_id = $subscription->get_user_id();
            
            if ( ! $user_id ) {
                $this->log( "Subscription status change but no user ID found" );
                return;
            }

            $this->log( "Subscription #{$subscription->get_id()} status change: {$old_status} → {$new_status} (User: {$user_id})" );

            if ( in_array( $new_status, $this->active_statuses, true ) ) {
                $this->handle_subscription_activation( $subscription, $user_id );
            } elseif ( in_array( $new_status, $this->inactive_statuses, true ) ) {
                $this->handle_subscription_deactivation( $subscription, $user_id );
            }
            
        } catch ( Exception $e ) {
            $this->log( "Error handling subscription status change: " . $e->getMessage() );
        }
    }

    /**
     * Handle subscription activation
     */
    private function handle_subscription_activation( $subscription, $user_id ) {
        $parent_order = $this->get_subscription_parent_order( $subscription );
        
        // Only activate if parent order is completed (manual approval)
        if ( $parent_order && $parent_order->get_status() === 'completed' ) {
            $this->activate_user_as_agent( $user_id, $subscription );
        } else {
            // Keep subscription on hold until manual approval
            $subscription->update_status( 'on-hold', 'Pending manual approval.' );
            $this->log( "Subscription #{$subscription->get_id()} held pending manual approval" );
        }
    }

    /**
     * Handle subscription deactivation
     */
    private function handle_subscription_deactivation( $subscription, $user_id ) {
        $this->deactivate_user_agent( $user_id );
    }

    /**
     * Activate subscription and associated agent
     */
    private function activate_subscription_and_agent( $subscription ) {
        $user_id = $subscription->get_user_id();
        
        if ( ! $user_id ) {
            $this->log( "Cannot activate subscription - no user ID" );
            return;
        }

        // Activate subscription if not already active
        if ( $subscription->get_status() !== 'active' ) {
            $subscription->update_status( 'active', 'Activated after manual order completion.' );
        }

        // Activate user as agent
        $this->activate_user_as_agent( $user_id, $subscription );
    }

    /**
     * Activate user as tutor/agent
     */
    private function activate_user_as_agent( $user_id, $subscription = null ) {
        try {
            $this->log( "Activating user {$user_id} as tutor/agent" );

            // Update user meta
            update_user_meta( $user_id, '_darsna_account_type', 'tutor' );
            update_user_meta( $user_id, '_darsna_subscription_active', 'yes' );
            
            if ( $subscription ) {
                update_user_meta( $user_id, '_darsna_subscription_id', $subscription->get_id() );
            }

            // Assign LatePoint agent role
            $this->assign_latepoint_agent_role( $user_id );

            // Create/update LatePoint agent
            $this->sync_latepoint_agent( $user_id, 'active' );

        } catch ( Exception $e ) {
            $this->log( "Error activating user as agent: " . $e->getMessage() );
        }
    }

    /**
     * Deactivate user agent
     */
    private function deactivate_user_agent( $user_id ) {
        try {
            $this->log( "Deactivating user {$user_id} as agent" );

            // Update user meta
            update_user_meta( $user_id, '_darsna_subscription_active', 'no' );

            // Remove agent role and set to customer
            $user = new WP_User( $user_id );
            $user->set_role( 'customer' );

            // Disable LatePoint agent
            $this->sync_latepoint_agent( $user_id, 'disabled' );

        } catch ( Exception $e ) {
            $this->log( "Error deactivating user agent: " . $e->getMessage() );
        }
    }

    /**
     * Assign LatePoint agent role to user
     */
    private function assign_latepoint_agent_role( $user_id ) {
        $user = new WP_User( $user_id );
        $old_roles = $user->roles;
        
        $user->set_role( 'latepoint_agent' );
        
        $new_roles = ( new WP_User( $user_id ) )->roles;
        $this->log( "User {$user_id} role change: " . implode( ',', $old_roles ) . " → " . implode( ',', $new_roles ) );
    }

    /**
     * Sync user data with LatePoint agent
     */
    private function sync_latepoint_agent( $user_id, $status = 'active' ) {
        if ( ! class_exists( '\OsAgentModel' ) ) {
            $this->log( 'OsAgentModel class not found - LatePoint may not be active' );
            return false;
        }

        try {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                $this->log( "User {$user_id} not found" );
                return false;
            }

            $agent_model = new \OsAgentModel();
            $existing_agent = $agent_model->where( [ 'wp_user_id' => $user_id ] )->get_results();

            if ( $existing_agent ) {
                // Update existing agent
                $agent_model->update_where( 
                    [ 'wp_user_id' => $user_id ], 
                    [ 'status' => $status ] 
                );
                $this->log( "Updated existing LatePoint agent for user {$user_id} - status: {$status}" );
            } else {
                // Create new agent
                if ( $status === 'active' ) {
                    $agent_data = $this->prepare_agent_data( $user );
                    
                    foreach ( $agent_data as $key => $value ) {
                        $agent_model->set_data( $key, $value );
                    }
                    
                    $agent_model->save();
                    $this->log( "Created new LatePoint agent for user {$user_id}" );
                }
            }

            return true;

        } catch ( Exception $e ) {
            $this->log( "Error syncing LatePoint agent: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Prepare agent data for LatePoint
     */
    private function prepare_agent_data( $user ) {
        $user_id = $user->ID;
        
        // Get user's latest order for additional data
        $latest_order = $this->get_user_latest_order( $user_id );
        
        // Prepare names
        $first_name = $user->first_name;
        $last_name = $user->last_name;
        
        if ( $latest_order ) {
            $first_name = $first_name ?: $latest_order->get_billing_first_name();
            $last_name = $last_name ?: $latest_order->get_billing_last_name();
        }
        
        // Fallback to display name
        if ( empty( $first_name ) || empty( $last_name ) ) {
            $name_parts = array_pad( explode( ' ', $user->display_name, 2 ), 2, '' );
            $first_name = $first_name ?: $name_parts[0];
            $last_name = $last_name ?: $name_parts[1];
        }
        
        // Prepare phone
        $phone = '';
        if ( $latest_order ) {
            $phone = $this->format_phone_for_latepoint( 
                $latest_order->get_billing_phone()
            );
        }

        return [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'wp_user_id' => $user_id,
            'status' => 'active',
        ];
    }

    /**
     * Format phone number for LatePoint (simplified)
     */
    private function format_phone_for_latepoint( $phone, $country_code = null ) {
        if ( empty( $phone ) ) {
            return '';
        }

        // Remove all non-digit and non-plus characters
        $clean_phone = preg_replace( '/[^\d+]/', '', $phone );
        
        // If phone already starts with +, return as is
        if ( strpos( $clean_phone, '+' ) === 0 ) {
            return $clean_phone;
        }

        // If no + at the beginning, add it
        $digits_only = preg_replace( '/\D/', '', $phone );
        return '+' . $digits_only;
    }

    /**
     * Handle user deletion
     */
    public function handle_user_deletion( $user_id ) {
        $this->log( "User {$user_id} deleted - disabling LatePoint agent" );
        $this->sync_latepoint_agent( $user_id, 'disabled' );
    }

    /**
     * Handle user role removal
     */
    public function handle_user_role_removal( $user_id, $role ) {
        if ( $role === 'latepoint_agent' ) {
            $this->log( "LatePoint agent role removed from user {$user_id}" );
            $this->sync_latepoint_agent( $user_id, 'disabled' );
        }
    }

    /**
     * Handle user role change
     */
    public function handle_user_role_change( $user_id, $role, $old_roles ) {
        // If user is no longer a latepoint_agent, disable their agent
        if ( in_array( 'latepoint_agent', $old_roles, true ) && $role !== 'latepoint_agent' ) {
            $this->log( "User {$user_id} role changed from latepoint_agent to {$role}" );
            $this->sync_latepoint_agent( $user_id, 'disabled' );
        }
    }

    /**
     * Enqueue scripts for checkout page
     */
    public function enqueue_checkout_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        // Simple phone input setup
        wp_add_inline_script( 'jquery', $this->get_phone_input_script() );
        
        // Add simple styling for phone input
        wp_add_inline_style( 'woocommerce-general', $this->get_phone_input_styles() );
    }

    /**
     * Get phone input initialization script
     */
    private function get_phone_input_script() {
        return "
            jQuery(function($) {
                var phoneInput = $('#billing_phone');
                if (phoneInput.length) {
                    // Set placeholder with example
                    phoneInput.attr('placeholder', '+96512345678');
                    
                    // Add helper text
                    if (!phoneInput.next('.phone-helper').length) {
                        phoneInput.after('<small class=\"phone-helper\" style=\"display: block; color: #666; margin-top: 4px;\">Please include country code (e.g., +96512345678)</small>');
                    }
                    
                    // Simple formatting on input
                    phoneInput.on('input', function() {
                        var value = $(this).val().replace(/[^\d+]/g, '');
                        if (value && !value.startsWith('+')) {
                            value = '+' + value;
                        }
                        $(this).val(value);
                    });
                }
            });
        ";
    }

    /**
     * Get phone input styles
     */
    private function get_phone_input_styles() {
        return "
            #billing_phone {
                font-family: monospace;
                letter-spacing: 1px;
            }
            .phone-helper {
                font-size: 12px;
                color: #666;
                font-style: italic;
            }
        ";
    }

    /**
     * Validate checkout fields
     */
    public function validate_checkout_fields( $data, $errors ) {
        $this->validate_phone_number( $data, $errors );
        $this->validate_required_fields( $data, $errors );
    }

    /**
     * Validate phone number
     */
    private function validate_phone_number( $data, $errors ) {
        $phone = $data['billing_phone'] ?? '';
        
        if ( empty( $phone ) ) {
            $errors->add( 'validation', __( 'Phone number is required.', 'darsna-tutor-registration' ) );
            return;
        }

        // Remove all non-digit and non-plus characters for validation
        $clean_phone = preg_replace( '/[^\d+]/', '', $phone );
        
        // Must start with + and have country code
        if ( ! preg_match( '/^\+\d{1,4}\d{7,}$/', $clean_phone ) ) {
            $errors->add( 'validation', 
                __( 'Please enter a valid international phone number with country code (e.g., +96512345678).', 'darsna-tutor-registration' ) 
            );
            return;
        }

        // Check minimum total length (country code + number should be at least 8 digits)
        $digits_only = preg_replace( '/\D/', '', $phone );
        if ( strlen( $digits_only ) < 8 ) {
            $errors->add( 'validation', 
                __( 'Phone number is too short. Please include country code and full number.', 'darsna-tutor-registration' ) 
            );
        }
    }

    /**
     * Validate required fields for tutor registration
     */
    private function validate_required_fields( $data, $errors ) {
        $required_fields = [
            'billing_first_name' => __( 'First name is required.', 'darsna-tutor-registration' ),
            'billing_last_name' => __( 'Last name is required.', 'darsna-tutor-registration' ),
            'billing_email' => __( 'Email address is required.', 'darsna-tutor-registration' ),
        ];

        foreach ( $required_fields as $field => $message ) {
            if ( empty( $data[ $field ] ) ) {
                $errors->add( 'validation', $message );
            }
        }
    }

    /**
     * Add dashboard and logout links to navigation menu
     */
    public function add_user_menu_items( $items, $args ) {
        if ( ! is_user_logged_in() ) {
            return $items;
        }

        // Only add to primary menu
        if ( isset( $args->theme_location ) && $args->theme_location !== 'primary-menu' ) {
            return $items;
        }

        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;

        // Determine dashboard URL based on user role
        $dashboard_url = in_array( 'latepoint_agent', $user_roles, true )
            ? admin_url( 'admin.php?page=latepoint' )
            : wc_get_page_permalink( 'myaccount' );

        // Add dashboard link
        $items .= sprintf(
            '<li class="menu-item menu-item-dashboard"><a href="%s">%s</a></li>',
            esc_url( $dashboard_url ),
            esc_html__( 'Dashboard', 'darsna-tutor-registration' )
        );

        // Add logout link
        $items .= sprintf(
            '<li class="menu-item menu-item-logout"><a href="%s">%s</a></li>',
            esc_url( wp_logout_url( home_url() ) ),
            esc_html__( 'Logout', 'darsna-tutor-registration' )
        );

        return $items;
    }

    /**
     * Helper method to check if order contains subscription
     */
    private function order_contains_subscription( $order ) {
        return function_exists( 'wcs_order_contains_subscription' ) && 
               wcs_order_contains_subscription( $order );
    }

    /**
     * Get subscriptions for an order
     */
    private function get_order_subscriptions( $order ) {
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return [];
        }
        return wcs_get_subscriptions_for_order( $order );
    }

    /**
     * Get subscription parent order
     */
    private function get_subscription_parent_order( $subscription ) {
        $parent_id = $subscription->get_parent_id();
        return $parent_id ? wc_get_order( $parent_id ) : null;
    }

    /**
     * Get user's latest order
     */
    private function get_user_latest_order( $user_id ) {
        $orders = wc_get_orders( [
            'customer' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ] );

        return ! empty( $orders ) ? $orders[0] : null;
    }

    /**
     * Show admin notices if needed
     */
    public function maybe_show_admin_notices() {
        // Add any admin-specific notices here
    }

    /**
     * Log messages for debugging
     */
    private function log( $message ) {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( '[DarsnaTutorCheckout] ' . $message );
        }
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    Darsna_Tutor_Checkout::get_instance();
} );

// Activation hook
register_activation_hook( __FILE__, function() {
    // Add any activation tasks here
    if ( WP_DEBUG ) {
        error_log( '[DarsnaTutorCheckout] Plugin activated' );
    }
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // Add any cleanup tasks here
    if ( WP_DEBUG ) {
        error_log( '[DarsnaTutorCheckout] Plugin deactivated' );
    }
} );
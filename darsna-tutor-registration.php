<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.3.0
 * Description: Enhanced tutor checkout with service selection, hourly rates, and LatePoint integration
 * Requires PHP: 7.4
 * Author: Darsna
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DARSNA_TUTOR_REG_VERSION', '3.3.0' );
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
        
        // Add tutor registration fields to checkout
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'add_tutor_registration_fields' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_tutor_fields' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_tutor_checkout_data' ] );
    }

    /**
     * Log messages for debugging
     */
    private function log( $message ) {
        if ( WP_DEBUG && WP_DEBUG_LOG ) {
            error_log( '[DarsnaTutorCheckout] ' . $message );
        }
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
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_darsna_sync_agent', [ $this, 'ajax_sync_agent' ] );
        add_action( 'wp_ajax_darsna_debug_user', [ $this, 'ajax_debug_user' ] );
        add_action( 'wp_ajax_darsna_get_logs', [ $this, 'ajax_get_logs' ] );
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
            // Check if user is already activated to prevent double activation
            $already_active = get_user_meta( $user_id, '_darsna_subscription_active', true );
            $processing_key = '_darsna_processing_user_' . $user_id;
            
            if ( $already_active === 'yes' ) {
                $this->log( "User {$user_id} already activated (meta check), skipping duplicate activation" );
                return;
            }
            
            if ( get_transient( $processing_key ) ) {
                $this->log( "User {$user_id} currently being processed (transient check), skipping duplicate activation" );
                return;
            }
            
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

        // Check if already processing this user to prevent double activation
        $processing_key = '_darsna_processing_activation_' . $user_id;
        if ( get_transient( $processing_key ) ) {
            $this->log( "User {$user_id} activation already in progress, skipping duplicate" );
            return;
        }

        // Set processing flag for 60 seconds
        set_transient( $processing_key, true, 60 );

        try {
            $this->log( "Activating subscription and agent for user {$user_id}" );
            
            // Activate subscription if not already active
            if ( $subscription->get_status() !== 'active' ) {
                $subscription->update_status( 'active', 'Activated after manual order completion.' );
            }

            // Activate user as agent (this has its own duplicate protection)
            $this->activate_user_as_agent( $user_id, $subscription );
            
        } finally {
            // Always clear the processing flag
            delete_transient( $processing_key );
        }
    }

    /**
     * Activate user as tutor/agent
     */
    private function activate_user_as_agent( $user_id, $subscription = null ) {
        try {
            // Check if already processing to prevent double activation
            $processing_key = '_darsna_processing_user_' . $user_id;
            if ( get_transient( $processing_key ) ) {
                $this->log( "User {$user_id} already being processed, skipping duplicate activation" );
                return;
            }

            // Set processing flag for 60 seconds
            set_transient( $processing_key, true, 60 );

            $this->log( "Activating user {$user_id} as tutor/agent" );

            // Get tutor data from their order
            $tutor_data = $this->get_tutor_data_from_orders( $user_id );

            // Update user meta FIRST to prevent race conditions
            update_user_meta( $user_id, '_darsna_account_type', 'tutor' );
            update_user_meta( $user_id, '_darsna_subscription_active', 'yes' );
            
            if ( $subscription ) {
                update_user_meta( $user_id, '_darsna_subscription_id', $subscription->get_id() );
            }

            // Store tutor-specific data
            if ( $tutor_data ) {
                update_user_meta( $user_id, '_darsna_tutor_service_id', $tutor_data['service_id'] );
                update_user_meta( $user_id, '_darsna_tutor_hourly_rate', $tutor_data['hourly_rate'] );
                update_user_meta( $user_id, '_darsna_tutor_bio', $tutor_data['bio'] );
                
                $this->log( "Stored tutor data - Service: {$tutor_data['service_id']}, Rate: {$tutor_data['hourly_rate']}" );
            }

            // Assign LatePoint agent role
            $this->assign_latepoint_agent_role( $user_id );

            // Create/update LatePoint agent
            $sync_result = $this->sync_latepoint_agent( $user_id, 'active' );
            
            // Assign service to agent in LatePoint
            if ( $sync_result && $tutor_data && $tutor_data['service_id'] ) {
                $this->assign_service_to_agent( $user_id, $tutor_data['service_id'] );
            }
            
            if ( $sync_result ) {
                $this->log( "User {$user_id} successfully activated as agent" );
            } else {
                $this->log( "User {$user_id} activated but LatePoint sync failed" );
            }

        } catch ( Exception $e ) {
            $this->log( "Error activating user as agent: " . $e->getMessage() );
        } finally {
            // Always clear processing flag
            delete_transient( $processing_key );
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
     * Sync user data with LatePoint agent - FIXED VERSION
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
            $existing_agents = $agent_model->where( [ 'wp_user_id' => $user_id ] )->get_results();

            $this->log( "Syncing LatePoint agent for user {$user_id} with status: {$status}" );
            $this->log( "Found " . count( $existing_agents ) . " existing agent(s) for user {$user_id}" );

            if ( $existing_agents ) {
                // Update existing agent - FIXED: Use proper LatePoint methods
                $existing_agent = $existing_agents[0];
                $update_agent = new \OsAgentModel();
                
                // Load existing agent data
                if ( method_exists( $update_agent, 'load_by_id' ) ) {
                    $update_agent->load_by_id( $existing_agent->id );
                } else {
                    // Fallback: set the ID
                    $update_agent->id = $existing_agent->id;
                    foreach ( (array) $existing_agent as $key => $value ) {
                        $update_agent->set_data( $key, $value );
                    }
                }
                
                // Update the status
                $update_agent->set_data( 'status', $status );
                $update_result = $update_agent->save();
                
                $this->log( "Updated existing LatePoint agent for user {$user_id} - status: {$status}, result: " . ( $update_result ? 'success' : 'failed' ) );
                
                return $update_result;
            } else {
                // Create new agent only if activating
                if ( $status === 'active' ) {
                    $agent_data = $this->prepare_agent_data( $user );
                    
                    $this->log( "Creating new LatePoint agent with data: " . json_encode( $agent_data ) );
                    
                    // Check if email already exists in LatePoint
                    $existing_by_email = $agent_model->where( [ 'email' => $agent_data['email'] ] )->get_results();
                    if ( $existing_by_email ) {
                        $this->log( "WARNING: Agent with email {$agent_data['email']} already exists - updating instead" );
                        $existing_agent = $existing_by_email[0];
                        $update_agent = new \OsAgentModel();
                        
                        if ( method_exists( $update_agent, 'load_by_id' ) ) {
                            $update_agent->load_by_id( $existing_agent->id );
                        } else {
                            $update_agent->id = $existing_agent->id;
                            foreach ( (array) $existing_agent as $key => $value ) {
                                $update_agent->set_data( $key, $value );
                            }
                        }
                        
                        // Update with new data including wp_user_id
                        foreach ( $agent_data as $key => $value ) {
                            $update_agent->set_data( $key, $value );
                        }
                        
                        $update_result = $update_agent->save();
                        $this->log( "Updated existing agent by email: " . ( $update_result ? 'success' : 'failed' ) );
                        return $update_result;
                    }
                    
                    // Get debug info about LatePoint
                    global $wpdb;
                    $agents_table = $wpdb->prefix . 'latepoint_agents';
                    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $agents_table ) );
                    $this->log( "LatePoint agents table ({$agents_table}) exists: " . ( $table_exists ? 'yes' : 'no' ) );
                    
                    if ( $table_exists ) {
                        $columns = $wpdb->get_results( "DESCRIBE {$agents_table}" );
                        $column_names = array_column( $columns, 'Field' );
                        $this->log( "Table columns: " . implode( ', ', $column_names ) );
                    }
                    
                    // Create new agent
                    $new_agent = new \OsAgentModel();
                    
                    // Set data one by one
                    foreach ( $agent_data as $key => $value ) {
                        try {
                            $new_agent->set_data( $key, $value );
                            $this->log( "Set {$key}: {$value}" );
                        } catch ( Exception $e ) {
                            $this->log( "ERROR setting {$key}: " . $e->getMessage() );
                        }
                    }
                    
                    // Attempt to save
                    $save_result = $new_agent->save();
                    
                    if ( $save_result ) {
                        $agent_id = $new_agent->id ?? 'unknown';
                        $this->log( "Successfully created LatePoint agent ID: {$agent_id} for user {$user_id}" );
                        return true;
                    } else {
                        $this->log( "ERROR: Failed to save new LatePoint agent for user {$user_id}" );
                        
                        // Try direct database insertion as fallback
                        $this->log( "Attempting direct database insertion as fallback..." );
                        $insert_result = $this->direct_agent_insert( $agent_data );
                        if ( $insert_result ) {
                            $this->log( "Direct database insertion successful" );
                            return true;
                        } else {
                            $this->log( "Direct database insertion also failed" );
                        }
                        
                        return false;
                    }
                } else {
                    $this->log( "No existing agent found for user {$user_id} and status is {$status} - nothing to do" );
                }
            }

            return true;

        } catch ( Exception $e ) {
            $this->log( "ERROR syncing LatePoint agent: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Direct database insertion fallback for LatePoint agents
     */
    private function direct_agent_insert( $agent_data ) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'latepoint_agents';
            
            // Prepare data for insertion with correct column names
            $insert_data = [
                'first_name' => $agent_data['first_name'] ?? '',
                'last_name' => $agent_data['last_name'] ?? '',
                'display_name' => $agent_data['display_name'] ?? '',
                'email' => $agent_data['email'] ?? '',
                'phone' => $agent_data['phone'] ?? '',
                'wp_user_id' => $agent_data['wp_user_id'] ?? 0,
                'status' => $agent_data['status'] ?? 'active',
                'created_at' => current_time( 'mysql' ),  // Fixed column name
                'updated_at' => current_time( 'mysql' ),  // Fixed column name
            ];
            
            $this->log( "Direct insert data: " . json_encode( $insert_data ) );
            
            $result = $wpdb->insert( $table_name, $insert_data );
            
            if ( $result === false ) {
                $this->log( "Direct insert failed. MySQL error: " . $wpdb->last_error );
                return false;
            } else {
                $insert_id = $wpdb->insert_id;
                $this->log( "Direct insert successful. Agent ID: {$insert_id}" );
                return true;
            }
            
        } catch ( Exception $e ) {
            $this->log( "Exception in direct_agent_insert: " . $e->getMessage() );
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
        
        // Get tutor-specific data
        $tutor_bio = get_user_meta( $user_id, '_darsna_tutor_bio', true );
        
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
            'bio' => $tutor_bio ? sanitize_textarea_field( $tutor_bio ) : '',
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
        
        // Add simple styling for phone input and new fields
        wp_add_inline_style( 'woocommerce-general', $this->get_checkout_styles() );
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

                // Add searchable functionality to service dropdown
                var serviceSelect = $('#tutor_service');
                if (serviceSelect.length) {
                    // Add search input above select
                    serviceSelect.before('<input type=\"text\" id=\"service-search\" placeholder=\"Search subjects...\" style=\"width: 100%; margin-bottom: 10px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;\">');
                    
                    var searchInput = $('#service-search');
                    var originalOptions = serviceSelect.find('option');
                    
                    searchInput.on('input', function() {
                        var searchTerm = $(this).val().toLowerCase();
                        
                        serviceSelect.find('option').remove();
                        serviceSelect.append('<option value=\"\">Select a subject...</option>');
                        
                        originalOptions.each(function() {
                            var option = $(this);
                            if (option.val() === '' || option.text().toLowerCase().includes(searchTerm)) {
                                serviceSelect.append(option.clone());
                            }
                        });
                    });
                }

                // Character counter for bio
                var bioTextarea = $('#tutor_bio');
                if (bioTextarea.length) {
                    var maxLength = 500;
                    bioTextarea.after('<div id=\"bio-counter\" style=\"font-size: 12px; color: #666; margin-top: 5px;\">0/' + maxLength + ' characters</div>');
                    
                    bioTextarea.on('input', function() {
                        var currentLength = $(this).val().length;
                        $('#bio-counter').text(currentLength + '/' + maxLength + ' characters');
                        
                        if (currentLength > maxLength) {
                            $('#bio-counter').css('color', '#d63638');
                        } else {
                            $('#bio-counter').css('color', '#666');
                        }
                    });
                }
            });
        ";
    }

    /**
     * Get checkout page styles
     */
    private function get_checkout_styles() {
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
            .tutor-field-group {
                background: #f9f9f9;
                border: 1px solid #e1e1e1;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .tutor-field-group h3 {
                margin-top: 0;
                color: #0073aa;
                border-bottom: 2px solid #0073aa;
                padding-bottom: 10px;
            }
            .tutor-field-row {
                display: flex;
                gap: 20px;
                margin-bottom: 15px;
            }
            .tutor-field-row .form-row {
                flex: 1;
            }
            #tutor_service, #tutor_hourly_rate {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            #tutor_bio {
                width: 100%;
                min-height: 80px;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                resize: vertical;
            }
            .required-field::after {
                content: ' *';
                color: #d63638;
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
     * Add tutor registration fields to checkout
     */
    public function add_tutor_registration_fields( $checkout ) {
        echo '<div class="tutor-field-group">';
        echo '<h3>' . __( 'Tutor Information', 'darsna-tutor-registration' ) . '</h3>';
        
        echo '<div class="tutor-field-row">';
        
        // Service/Subject Selection
        echo '<div class="form-row form-row-first">';
        $services = $this->get_latepoint_services();
        $service_options = [ '' => __( 'Select a subject...', 'darsna-tutor-registration' ) ];
        
        foreach ( $services as $service ) {
            $service_options[ $service->id ] = $service->name;
        }
        
        woocommerce_form_field( 'tutor_service', [
            'type'        => 'select',
            'class'       => [ 'form-row-wide' ],
            'label'       => __( 'Subject to teach', 'darsna-tutor-registration' ) . ' <span class="required-field"></span>',
            'required'    => true,
            'options'     => $service_options,
        ], $checkout->get_value( 'tutor_service' ) );
        echo '</div>';
        
        // Hourly Rate Selection
        echo '<div class="form-row form-row-last">';
        $currency_symbol = get_woocommerce_currency_symbol();
        $rate_options = [ '' => __( 'Select hourly rate...', 'darsna-tutor-registration' ) ];
        
        for ( $rate = 5; $rate <= 50; $rate += 5 ) {
            $rate_options[ $rate ] = $currency_symbol . $rate . __( '/hour', 'darsna-tutor-registration' );
        }
        
        woocommerce_form_field( 'tutor_hourly_rate', [
            'type'        => 'select',
            'class'       => [ 'form-row-wide' ],
            'label'       => __( 'Hourly Rate', 'darsna-tutor-registration' ) . ' <span class="required-field"></span>',
            'required'    => true,
            'options'     => $rate_options,
        ], $checkout->get_value( 'tutor_hourly_rate' ) );
        echo '</div>';
        
        echo '</div>'; // End tutor-field-row
        
        // Bio/Specialties (Optional)
        woocommerce_form_field( 'tutor_bio', [
            'type'        => 'textarea',
            'class'       => [ 'form-row-wide' ],
            'label'       => __( 'Brief Bio / Specialties (Optional)', 'darsna-tutor-registration' ),
            'placeholder' => __( 'Tell students about your background, experience, and teaching specialties...', 'darsna-tutor-registration' ),
            'custom_attributes' => [
                'maxlength' => '500',
                'rows'      => '4',
            ],
        ], $checkout->get_value( 'tutor_bio' ) );
        
        echo '</div>'; // End tutor-field-group
    }

    /**
     * Validate tutor-specific fields
     */
    public function validate_tutor_fields() {
        // Validate service selection
        if ( empty( $_POST['tutor_service'] ) ) {
            wc_add_notice( __( 'Please select a subject to teach.', 'darsna-tutor-registration' ), 'error' );
        } else {
            $selected_service = intval( $_POST['tutor_service'] );
            $services = $this->get_latepoint_services();
            $valid_service = false;
            
            foreach ( $services as $service ) {
                if ( $service->id === $selected_service ) {
                    $valid_service = true;
                    break;
                }
            }
            
            if ( ! $valid_service ) {
                wc_add_notice( __( 'Please select a valid subject.', 'darsna-tutor-registration' ), 'error' );
            }
        }
        
        // Validate hourly rate
        if ( empty( $_POST['tutor_hourly_rate'] ) ) {
            wc_add_notice( __( 'Please select your hourly rate.', 'darsna-tutor-registration' ), 'error' );
        } else {
            $selected_rate = intval( $_POST['tutor_hourly_rate'] );
            if ( $selected_rate < 5 || $selected_rate > 50 || $selected_rate % 5 !== 0 ) {
                wc_add_notice( __( 'Please select a valid hourly rate.', 'darsna-tutor-registration' ), 'error' );
            }
        }
        
        // Validate bio length (if provided)
        if ( ! empty( $_POST['tutor_bio'] ) && strlen( $_POST['tutor_bio'] ) > 500 ) {
            wc_add_notice( __( 'Bio must be 500 characters or less.', 'darsna-tutor-registration' ), 'error' );
        }
    }

    /**
     * Save tutor checkout data to order meta
     */
    public function save_tutor_checkout_data( $order_id ) {
        if ( ! empty( $_POST['tutor_service'] ) ) {
            update_post_meta( $order_id, '_tutor_service_id', sanitize_text_field( $_POST['tutor_service'] ) );
        }
        
        if ( ! empty( $_POST['tutor_hourly_rate'] ) ) {
            update_post_meta( $order_id, '_tutor_hourly_rate', sanitize_text_field( $_POST['tutor_hourly_rate'] ) );
        }
        
        if ( ! empty( $_POST['tutor_bio'] ) ) {
            update_post_meta( $order_id, '_tutor_bio', sanitize_textarea_field( $_POST['tutor_bio'] ) );
        }
    }

    /**
     * Get LatePoint services for dropdown
     */
    private function get_latepoint_services() {
        if ( ! class_exists( '\OsServiceModel' ) ) {
            $this->log( 'OsServiceModel class not found - LatePoint may not be active' );
            return [];
        }

        try {
            $service_model = new \OsServiceModel();
            $services = $service_model->where( [ 'status' => 'active' ] )->get_results();
            
            if ( ! $services ) {
                $this->log( 'No active services found in LatePoint' );
                return [];
            }
            
            $this->log( 'Found ' . count( $services ) . ' active LatePoint services' );
            return $services;
            
        } catch ( Exception $e ) {
            $this->log( 'Error getting LatePoint services: ' . $e->getMessage() );
            return [];
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
     * Get tutor data from user's orders
     */
    private function get_tutor_data_from_orders( $user_id ) {
        $orders = wc_get_orders( [
            'customer' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ] );

        if ( empty( $orders ) ) {
            $this->log( "No orders found for user {$user_id}" );
            return null;
        }

        $order = $orders[0];
        $service_id = $order->get_meta( '_tutor_service_id' );
        $hourly_rate = $order->get_meta( '_tutor_hourly_rate' );
        $bio = $order->get_meta( '_tutor_bio' );

        if ( empty( $service_id ) || empty( $hourly_rate ) ) {
            $this->log( "Missing tutor data in order #{$order->get_id()} for user {$user_id}" );
            return null;
        }

        return [
            'service_id' => intval( $service_id ),
            'hourly_rate' => intval( $hourly_rate ),
            'bio' => sanitize_textarea_field( $bio ),
        ];
    }

    /**
     * Assign service to agent in LatePoint
     */
    private function assign_service_to_agent( $user_id, $service_id ) {
        if ( ! class_exists( '\OsAgentModel' ) || ! class_exists( '\OsServiceModel' ) ) {
            $this->log( 'LatePoint models not found for service assignment' );
            return false;
        }

        try {
            // Get the agent
            $agent_model = new \OsAgentModel();
            $agents = $agent_model->where( [ 'wp_user_id' => $user_id ] )->get_results();

            if ( empty( $agents ) ) {
                $this->log( "No LatePoint agent found for user {$user_id}" );
                return false;
            }

            $agent = $agents[0];
            $agent_id = $agent->id;

            // Verify service exists
            $service_model = new \OsServiceModel();
            $service = $service_model->where( [ 'id' => $service_id ] )->get_results();

            if ( empty( $service ) ) {
                $this->log( "Service {$service_id} not found in LatePoint" );
                return false;
            }

            // Use LatePoint's connection table to assign service to agent
            global $wpdb;
            $connections_table = $wpdb->prefix . 'latepoint_agent_services';
            
            // Check if connection already exists
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$connections_table} WHERE agent_id = %d AND service_id = %d",
                $agent_id,
                $service_id
            ) );

            if ( ! $existing ) {
                $result = $wpdb->insert(
                    $connections_table,
                    [
                        'agent_id' => $agent_id,
                        'service_id' => $service_id,
                    ],
                    [ '%d', '%d' ]
                );

                if ( $result ) {
                    $this->log( "Successfully assigned service {$service_id} to agent {$agent_id} for user {$user_id}" );
                    return true;
                } else {
                    $this->log( "Failed to assign service to agent. MySQL error: " . $wpdb->last_error );
                    return false;
                }
            } else {
                $this->log( "Service {$service_id} already assigned to agent {$agent_id}" );
                return true;
            }

        } catch ( Exception $e ) {
            $this->log( "Error assigning service to agent: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Show admin notices if needed
     */
    public function maybe_show_admin_notices() {
        // Add any admin-specific notices here
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Tutor Registration', 'darsna-tutor-registration' ),
            __( 'Tutor Registration', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-tutor-registration',
            [ $this, 'admin_dashboard_page' ],
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'darsna-tutor-registration',
            __( 'Dashboard', 'darsna-tutor-registration' ),
            __( 'Dashboard', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-tutor-registration',
            [ $this, 'admin_dashboard_page' ]
        );

        add_submenu_page(
            'darsna-tutor-registration',
            __( 'Tutors & Agents', 'darsna-tutor-registration' ),
            __( 'Tutors & Agents', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-tutor-agents',
            [ $this, 'admin_agents_page' ]
        );

        add_submenu_page(
            'darsna-tutor-registration',
            __( 'Debug Tools', 'darsna-tutor-registration' ),
            __( 'Debug Tools', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-tutor-debug',
            [ $this, 'admin_debug_page' ]
        );

        add_submenu_page(
            'darsna-tutor-registration',
            __( 'Logs', 'darsna-tutor-registration' ),
            __( 'Logs', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-tutor-logs',
            [ $this, 'admin_logs_page' ]
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'darsna-tutor' ) === false ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_add_inline_script( 'jquery', $this->get_admin_script() );
        wp_add_inline_style( 'common', $this->get_admin_styles() );
    }

    /**
     * Get admin JavaScript
     */
    private function get_admin_script() {
        return "
            jQuery(document).ready(function($) {
                // Sync agent button
                $('.sync-agent-btn').on('click', function(e) {
                    e.preventDefault();
                    var userId = $(this).data('user-id');
                    var button = $(this);
                    
                    button.prop('disabled', true).text('Syncing...');
                    
                    $.post(ajaxurl, {
                        action: 'darsna_sync_agent',
                        user_id: userId,
                        nonce: '" . wp_create_nonce( 'darsna_admin_nonce' ) . "'
                    }, function(response) {
                        if (response.success) {
                            button.text('Success!').removeClass('button-primary').addClass('button-secondary');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            button.text('Error!').removeClass('button-primary').addClass('button-danger');
                            alert('Error: ' + response.data);
                        }
                    }).fail(function() {
                        button.text('Failed!').removeClass('button-primary').addClass('button-danger');
                    });
                });

                // Debug user button
                $('.debug-user-btn').on('click', function(e) {
                    e.preventDefault();
                    var userId = $(this).data('user-id');
                    var button = $(this);
                    
                    button.prop('disabled', true).text('Debugging...');
                    
                    $.post(ajaxurl, {
                        action: 'darsna_debug_user',
                        user_id: userId,
                        nonce: '" . wp_create_nonce( 'darsna_admin_nonce' ) . "'
                    }, function(response) {
                        button.prop('disabled', false).text('Debug User');
                        if (response.success) {
                            $('#debug-output').html('<pre>' + response.data + '</pre>');
                        } else {
                            $('#debug-output').html('<p style=\"color: red;\">Error: ' + response.data + '</p>');
                        }
                    });
                });

                // Auto-refresh logs
                function refreshLogs() {
                    if ($('#logs-container').length) {
                        $.post(ajaxurl, {
                            action: 'darsna_get_logs',
                            nonce: '" . wp_create_nonce( 'darsna_admin_nonce' ) . "'
                        }, function(response) {
                            if (response.success) {
                                $('#logs-container').html(response.data);
                            }
                        });
                    }
                }

                // Refresh logs every 10 seconds
                if ($('#logs-container').length) {
                    setInterval(refreshLogs, 10000);
                }
            });
        ";
    }

    /**
     * Get admin CSS
     */
    private function get_admin_styles() {
        return "
            .darsna-admin-wrap {
                margin: 20px 0;
            }
            .darsna-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin: 20px 0;
                padding: 20px;
            }
            .darsna-stats {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }
            .darsna-stat-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
                flex: 1;
            }
            .darsna-stat-number {
                font-size: 32px;
                font-weight: bold;
                color: #0073aa;
                display: block;
            }
            .darsna-stat-label {
                color: #666;
                font-size: 14px;
            }
            .darsna-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .darsna-table th,
            .darsna-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            .darsna-table th {
                background: #f9f9f9;
                font-weight: 600;
            }
            .status-active { color: #00a32a; font-weight: 600; }
            .status-inactive { color: #d63638; font-weight: 600; }
            .status-pending { color: #dba617; font-weight: 600; }
            #debug-output {
                background: #f1f1f1;
                border: 1px solid #ddd;
                padding: 15px;
                margin: 15px 0;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
                max-height: 400px;
                overflow-y: auto;
            }
            #logs-container {
                background: #f1f1f1;
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
                max-height: 600px;
                overflow-y: auto;
            }
            .button-danger {
                background: #d63638 !important;
                border-color: #d63638 !important;
                color: #fff !important;
            }
        ";
    }

    /**
     * Admin dashboard page
     */
    public function admin_dashboard_page() {
        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Tutor Registration Dashboard', 'darsna-tutor-registration' ); ?></h1>
            
            <div class="darsna-admin-wrap">
                <div class="darsna-stats">
                    <div class="darsna-stat-box">
                        <span class="darsna-stat-number"><?php echo esc_html( $stats['total_tutors'] ); ?></span>
                        <span class="darsna-stat-label"><?php _e( 'Total Tutors', 'darsna-tutor-registration' ); ?></span>
                    </div>
                    <div class="darsna-stat-box">
                        <span class="darsna-stat-number"><?php echo esc_html( $stats['active_subscriptions'] ); ?></span>
                        <span class="darsna-stat-label"><?php _e( 'Active Subscriptions', 'darsna-tutor-registration' ); ?></span>
                    </div>
                    <div class="darsna-stat-box">
                        <span class="darsna-stat-number"><?php echo esc_html( $stats['latepoint_agents'] ); ?></span>
                        <span class="darsna-stat-label"><?php _e( 'LatePoint Agents', 'darsna-tutor-registration' ); ?></span>
                    </div>
                    <div class="darsna-stat-box">
                        <span class="darsna-stat-number"><?php echo esc_html( $stats['pending_orders'] ); ?></span>
                        <span class="darsna-stat-label"><?php _e( 'Pending Orders', 'darsna-tutor-registration' ); ?></span>
                    </div>
                </div>

                <div class="darsna-card">
                    <h2><?php _e( 'System Status', 'darsna-tutor-registration' ); ?></h2>
                    <table class="darsna-table">
                        <tr>
                            <td><strong><?php _e( 'Plugin Version', 'darsna-tutor-registration' ); ?></strong></td>
                            <td><?php echo esc_html( DARSNA_TUTOR_REG_VERSION ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'WooCommerce', 'darsna-tutor-registration' ); ?></strong></td>
                            <td><?php echo is_plugin_active( 'woocommerce/woocommerce.php' ) ? '<span class="status-active">✓ Active</span>' : '<span class="status-inactive">✗ Inactive</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'WooCommerce Subscriptions', 'darsna-tutor-registration' ); ?></strong></td>
                            <td><?php echo is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ? '<span class="status-active">✓ Active</span>' : '<span class="status-inactive">✗ Inactive</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'LatePoint', 'darsna-tutor-registration' ); ?></strong></td>
                            <td><?php echo is_plugin_active( 'latepoint/latepoint.php' ) ? '<span class="status-active">✓ Active</span>' : '<span class="status-inactive">✗ Not Available</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'LatePoint Agent Model', 'darsna-tutor-registration' ); ?></strong></td>
                            <td><?php echo class_exists( '\OsAgentModel' ) ? '<span class="status-active">✓ Available</span>' : '<span class="status-inactive">✗ Not Available</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e( 'Debug Logging', 'darsna-tutor-registration' ); ?></strong></td>
                            <td><?php echo ( WP_DEBUG && WP_DEBUG_LOG ) ? '<span class="status-active">✓ Enabled</span>' : '<span class="status-inactive">✗ Disabled</span>'; ?></td>
                        </tr>
                    </table>
                </div>

                <div class="darsna-card">
                    <h2><?php _e( 'Recent Activity', 'darsna-tutor-registration' ); ?></h2>
                    <p><?php _e( 'Last 5 tutor registrations and status changes:', 'darsna-tutor-registration' ); ?></p>
                    <?php $this->display_recent_activity(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Admin agents page
     */
    public function admin_agents_page() {
        $tutors = $this->get_tutors_data();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Tutors & Agents Management', 'darsna-tutor-registration' ); ?></h1>
            
            <div class="darsna-admin-wrap">
                <div class="darsna-card">
                    <h2><?php _e( 'All Tutors', 'darsna-tutor-registration' ); ?></h2>
                    <table class="darsna-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'User ID', 'darsna-tutor-registration' ); ?></th>
                                <th><?php _e( 'Name', 'darsna-tutor-registration' ); ?></th>
                                <th><?php _e( 'Email', 'darsna-tutor-registration' ); ?></th>
                                <th><?php _e( 'Role', 'darsna-tutor-registration' ); ?></th>
                                <th><?php _e( 'Subscription', 'darsna-tutor-registration' ); ?></th>
                                <th><?php _e( 'LatePoint Agent', 'darsna-tutor-registration' ); ?></th>
                                <th><?php _e( 'Actions', 'darsna-tutor-registration' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $tutors as $tutor ) : ?>
                            <tr>
                                <td><?php echo esc_html( $tutor['user_id'] ); ?></td>
                                <td><?php echo esc_html( $tutor['name'] ); ?></td>
                                <td><?php echo esc_html( $tutor['email'] ); ?></td>
                                <td><?php echo esc_html( implode( ', ', $tutor['roles'] ) ); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr( $tutor['subscription_status'] ); ?>">
                                        <?php echo esc_html( ucfirst( $tutor['subscription_status'] ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-<?php echo esc_attr( $tutor['latepoint_status'] ); ?>">
                                        <?php echo esc_html( ucfirst( $tutor['latepoint_status'] ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="button button-small sync-agent-btn" data-user-id="<?php echo esc_attr( $tutor['user_id'] ); ?>">
                                        <?php _e( 'Sync Agent', 'darsna-tutor-registration' ); ?>
                                    </button>
                                    <button class="button button-small debug-user-btn" data-user-id="<?php echo esc_attr( $tutor['user_id'] ); ?>">
                                        <?php _e( 'Debug', 'darsna-tutor-registration' ); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Admin debug page
     */
    public function admin_debug_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Debug Tools', 'darsna-tutor-registration' ); ?></h1>
            
            <div class="darsna-admin-wrap">
                <div class="darsna-card">
                    <h2><?php _e( 'Debug User', 'darsna-tutor-registration' ); ?></h2>
                    <p><?php _e( 'Enter a user ID to debug their tutor/agent status:', 'darsna-tutor-registration' ); ?></p>
                    <input type="number" id="debug-user-id" placeholder="<?php _e( 'User ID', 'darsna-tutor-registration' ); ?>" style="width: 100px;">
                    <button class="button button-primary debug-user-btn" data-user-id="" onclick="this.setAttribute('data-user-id', document.getElementById('debug-user-id').value)">
                        <?php _e( 'Debug User', 'darsna-tutor-registration' ); ?>
                    </button>
                    
                    <div id="debug-output" style="display: none;"></div>
                </div>

                <div class="darsna-card">
                    <h2><?php _e( 'Manual Agent Sync', 'darsna-tutor-registration' ); ?></h2>
                    <p><?php _e( 'Force sync a user with LatePoint:', 'darsna-tutor-registration' ); ?></p>
                    <input type="number" id="sync-user-id" placeholder="<?php _e( 'User ID', 'darsna-tutor-registration' ); ?>" style="width: 100px;">
                    <button class="button button-primary sync-agent-btn" data-user-id="" onclick="this.setAttribute('data-user-id', document.getElementById('sync-user-id').value)">
                        <?php _e( 'Sync Agent', 'darsna-tutor-registration' ); ?>
                    </button>
                </div>

                <div class="darsna-card">
                    <h2><?php _e( 'System Information', 'darsna-tutor-registration' ); ?></h2>
                    <pre style="background: #f1f1f1; padding: 15px; border-radius: 4px; font-size: 12px;">
                        <?php $this->display_system_info(); ?>
                    </pre>
                </div>
            </div>
        </div>
        <script>
            // Show debug output when user enters a user ID and clicks debug
            jQuery(document).ready(function($) {
                $('.debug-user-btn').on('click', function() {
                    $('#debug-output').show();
                });
            });
        </script>
        <?php
    }

    /**
     * Admin logs page
     */
    public function admin_logs_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Plugin Logs', 'darsna-tutor-registration' ); ?></h1>
            
            <div class="darsna-admin-wrap">
                <div class="darsna-card">
                    <h2><?php _e( 'Recent Logs', 'darsna-tutor-registration' ); ?></h2>
                    <p><?php _e( 'Auto-refreshes every 10 seconds. Showing last 50 log entries:', 'darsna-tutor-registration' ); ?></p>
                    <div id="logs-container">
                        <?php $this->display_logs(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for syncing agent
     */
    public function ajax_sync_agent() {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        
        if ( ! $user_id ) {
            wp_send_json_error( 'Invalid user ID' );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( 'User not found' );
        }

        // Determine status based on subscription
        $subscription_active = get_user_meta( $user_id, '_darsna_subscription_active', true );
        $status = ( $subscription_active === 'yes' ) ? 'active' : 'disabled';

        $result = $this->sync_latepoint_agent( $user_id, $status );
        
        if ( $result ) {
            wp_send_json_success( 'Agent synced successfully' );
        } else {
            wp_send_json_error( 'Failed to sync agent' );
        }
    }

    /**
     * AJAX handler for debugging user
     */
    public function ajax_debug_user() {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $user_id = intval( $_POST['user_id'] ?? 0 );
        
        if ( ! $user_id ) {
            wp_send_json_error( 'Invalid user ID' );
        }

        // Get the debug info
        $debug_info = $this->get_debug_info( $user_id );
        
        wp_send_json_success( $debug_info );
    }

    /**
     * AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        ob_start();
        $this->display_logs();
        $logs_html = ob_get_clean();
        
        wp_send_json_success( $logs_html );
    }

    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        $stats = [
            'total_tutors' => 0,
            'active_subscriptions' => 0,
            'latepoint_agents' => 0,
            'pending_orders' => 0,
        ];

        // Get total tutors (users with tutor account type)
        $tutors = get_users([
            'meta_key' => '_darsna_account_type',
            'meta_value' => 'tutor',
            'count_total' => true,
        ]);
        $stats['total_tutors'] = $tutors->total_users ?? 0;

        // Get active subscriptions
        if ( function_exists( 'wcs_get_subscriptions' ) ) {
            $active_subs = wcs_get_subscriptions([
                'subscription_status' => 'active',
                'limit' => -1,
            ]);
            $stats['active_subscriptions'] = count( $active_subs );
        }

        // Get LatePoint agents
        if ( class_exists( '\OsAgentModel' ) ) {
            $agent_model = new \OsAgentModel();
            $agents = $agent_model->where([ 'status' => 'active' ])->get_results();
            $stats['latepoint_agents'] = count( $agents );
        }

        // Get pending orders
        $pending_orders = wc_get_orders([
            'status' => 'on-hold',
            'payment_method' => 'cod',
            'limit' => -1,
            'return' => 'ids',
        ]);
        $stats['pending_orders'] = count( $pending_orders );

        return $stats;
    }

    /**
     * Get tutors data for admin table
     */
    private function get_tutors_data() {
        $tutors = [];
        
        $users = get_users([
            'meta_key' => '_darsna_account_type',
            'meta_value' => 'tutor',
        ]);

        foreach ( $users as $user ) {
            $subscription_active = get_user_meta( $user->ID, '_darsna_subscription_active', true );
            
            // Check LatePoint agent status
            $latepoint_status = 'none';
            if ( class_exists( '\OsAgentModel' ) ) {
                $agent_model = new \OsAgentModel();
                $agent = $agent_model->where([ 'wp_user_id' => $user->ID ])->get_results();
                if ( $agent ) {
                    $latepoint_status = $agent[0]->status ?? 'unknown';
                }
            }

            $tutors[] = [
                'user_id' => $user->ID,
                'name' => $user->display_name ?: ( $user->first_name . ' ' . $user->last_name ),
                'email' => $user->user_email,
                'roles' => $user->roles,
                'subscription_status' => $subscription_active === 'yes' ? 'active' : 'inactive',
                'latepoint_status' => $latepoint_status,
            ];
        }

        return $tutors;
    }

    /**
     * Display recent activity
     */
    private function display_recent_activity() {
        // This would ideally come from a custom table or meta queries
        // For now, we'll show recent users with tutor account type
        $recent_users = get_users([
            'meta_key' => '_darsna_account_type',
            'meta_value' => 'tutor',
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => 5,
        ]);

        if ( empty( $recent_users ) ) {
            echo '<p>' . __( 'No recent activity found.', 'darsna-tutor-registration' ) . '</p>';
            return;
        }

        echo '<table class="darsna-table">';
        echo '<thead><tr>';
        echo '<th>' . __( 'User', 'darsna-tutor-registration' ) . '</th>';
        echo '<th>' . __( 'Email', 'darsna-tutor-registration' ) . '</th>';
        echo '<th>' . __( 'Registered', 'darsna-tutor-registration' ) . '</th>';
        echo '<th>' . __( 'Status', 'darsna-tutor-registration' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $recent_users as $user ) {
            $subscription_active = get_user_meta( $user->ID, '_darsna_subscription_active', true );
            $status_class = $subscription_active === 'yes' ? 'active' : 'inactive';
            
            echo '<tr>';
            echo '<td>' . esc_html( $user->display_name ?: ( $user->first_name . ' ' . $user->last_name ) ) . '</td>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
            echo '<td>' . esc_html( date( 'Y-m-d H:i', strtotime( $user->user_registered ) ) ) . '</td>';
            echo '<td><span class="status-' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $status_class ) ) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Display system information
     */
    private function display_system_info() {
        echo "WordPress Version: " . get_bloginfo( 'version' ) . "\n";
        echo "PHP Version: " . PHP_VERSION . "\n";
        echo "Plugin Version: " . DARSNA_TUTOR_REG_VERSION . "\n";
        echo "WooCommerce Version: " . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed' ) . "\n";
        echo "WP Debug: " . ( WP_DEBUG ? 'Enabled' : 'Disabled' ) . "\n";
        echo "WP Debug Log: " . ( WP_DEBUG_LOG ? 'Enabled' : 'Disabled' ) . "\n";
        echo "Memory Limit: " . ini_get( 'memory_limit' ) . "\n";
        echo "Max Execution Time: " . ini_get( 'max_execution_time' ) . "s\n";
        
        if ( class_exists( '\OsAgentModel' ) ) {
            try {
                $agent_model = new \OsAgentModel();
                $all_agents = $agent_model->get_results();
                echo "Total LatePoint Agents: " . count( $all_agents ) . "\n";
            } catch ( Exception $e ) {
                echo "LatePoint Agent Error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "LatePoint Agents: Model not available\n";
        }
    }

    /**
     * Get debug information for a user
     */
    private function get_debug_info( $user_id ) {
        $debug_info = [];
        
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return "User {$user_id} not found";
        }

        $debug_info[] = "=== USER DEBUG INFO ===";
        $debug_info[] = "User ID: {$user_id}";
        $debug_info[] = "Email: {$user->user_email}";
        $debug_info[] = "Display Name: {$user->display_name}";
        $debug_info[] = "Roles: " . implode( ', ', $user->roles );
        
        // User meta
        $account_type = get_user_meta( $user_id, '_darsna_account_type', true );
        $subscription_active = get_user_meta( $user_id, '_darsna_subscription_active', true );
        $subscription_id = get_user_meta( $user_id, '_darsna_subscription_id', true );
        
        // Tutor-specific meta
        $tutor_service_id = get_user_meta( $user_id, '_darsna_tutor_service_id', true );
        $tutor_hourly_rate = get_user_meta( $user_id, '_darsna_tutor_hourly_rate', true );
        $tutor_bio = get_user_meta( $user_id, '_darsna_tutor_bio', true );
        
        $debug_info[] = "";
        $debug_info[] = "=== USER META ===";
        $debug_info[] = "Account Type: " . ( $account_type ?: 'Not set' );
        $debug_info[] = "Subscription Active: " . ( $subscription_active ?: 'Not set' );
        $debug_info[] = "Subscription ID: " . ( $subscription_id ?: 'Not set' );
        
        $debug_info[] = "";
        $debug_info[] = "=== TUTOR DATA ===";
        $debug_info[] = "Service ID: " . ( $tutor_service_id ?: 'Not set' );
        $debug_info[] = "Hourly Rate: " . ( $tutor_hourly_rate ? get_woocommerce_currency_symbol() . $tutor_hourly_rate . '/hour' : 'Not set' );
        $debug_info[] = "Bio Length: " . ( $tutor_bio ? strlen( $tutor_bio ) . ' characters' : 'Not set' );
        
        // Get service name if set
        if ( $tutor_service_id ) {
            $services = $this->get_latepoint_services();
            foreach ( $services as $service ) {
                if ( $service->id == $tutor_service_id ) {
                    $debug_info[] = "Service Name: " . $service->name;
                    break;
                }
            }
        }

        // Check subscriptions
        if ( function_exists( 'wcs_get_users_subscriptions' ) ) {
            $subscriptions = wcs_get_users_subscriptions( $user_id );
            $debug_info[] = "";
            $debug_info[] = "=== SUBSCRIPTIONS ===";
            $debug_info[] = "Total Subscriptions: " . count( $subscriptions );
            
            foreach ( $subscriptions as $subscription ) {
                $debug_info[] = "Sub #{$subscription->get_id()}: {$subscription->get_status()}";
            }
        }

        // Check LatePoint agent
        if ( class_exists( '\OsAgentModel' ) ) {
            $agent_model = new \OsAgentModel();
            $agents = $agent_model->where([ 'wp_user_id' => $user_id ])->get_results();
            
            $debug_info[] = "";
            $debug_info[] = "=== LATEPOINT AGENTS ===";
            $debug_info[] = "Total Agents Found: " . count( $agents );
            
            if ( $agents ) {
                foreach ( $agents as $agent ) {
                    $agent_data = (array) $agent;
                    $debug_info[] = "Agent ID: {$agent_data['id']}";
                    $debug_info[] = "Status: {$agent_data['status']}";
                    $debug_info[] = "Email: {$agent_data['email']}";
                    $debug_info[] = "Name: {$agent_data['first_name']} {$agent_data['last_name']}";
                    $debug_info[] = "Phone: {$agent_data['phone']}";
                }
            } else {
                $debug_info[] = "No LatePoint agents found for this user";
            }
        } else {
            $debug_info[] = "LatePoint Agent Model not available";
        }

        return implode( "\n", $debug_info );
    }

    /**
     * Display logs
     */
    private function display_logs() {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if ( ! file_exists( $log_file ) ) {
            echo '<p>No log file found. Make sure WP_DEBUG_LOG is enabled.</p>';
            return;
        }

        $logs = file_get_contents( $log_file );
        $log_lines = explode( "\n", $logs );
        
        // Filter for our plugin logs and get last 50
        $plugin_logs = [];
        foreach ( array_reverse( $log_lines ) as $line ) {
            if ( strpos( $line, '[DarsnaTutorCheckout]' ) !== false ) {
                $plugin_logs[] = $line;
                if ( count( $plugin_logs ) >= 50 ) {
                    break;
                }
            }
        }

        if ( empty( $plugin_logs ) ) {
            echo '<p>No plugin logs found.</p>';
            return;
        }

        foreach ( $plugin_logs as $log ) {
            echo esc_html( $log ) . "\n";
        }
    }

    /**
     * Debug method to check LatePoint agent status
     * Can be called manually for troubleshooting
     */
    public function debug_latepoint_agent( $user_id ) {
        if ( ! class_exists( '\OsAgentModel' ) ) {
            $this->log( 'DEBUG: OsAgentModel class not found' );
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            $this->log( "DEBUG: User {$user_id} not found" );
            return;
        }

        $this->log( "DEBUG: Checking LatePoint agent for user {$user_id} ({$user->user_email})" );
        
        // Check user roles
        $this->log( "DEBUG: User roles: " . implode( ', ', $user->roles ) );
        
        // Check user meta
        $account_type = get_user_meta( $user_id, '_darsna_account_type', true );
        $subscription_active = get_user_meta( $user_id, '_darsna_subscription_active', true );
        $this->log( "DEBUG: Account type: {$account_type}, Subscription active: {$subscription_active}" );

        // Check LatePoint agent
        $agent_model = new \OsAgentModel();
        $agents = $agent_model->where( [ 'wp_user_id' => $user_id ] )->get_results();
        
        $this->log( "DEBUG: Found " . count( $agents ) . " LatePoint agent(s)" );
        
        if ( $agents ) {
            foreach ( $agents as $agent ) {
                $agent_data = (array) $agent;
                $this->log( "DEBUG: Agent ID: {$agent_data['id']}, Status: {$agent_data['status']}, Email: {$agent_data['email']}, Name: {$agent_data['first_name']} {$agent_data['last_name']}" );
            }
        }

        // Check all agents in LatePoint (for comparison)
        $all_agents = $agent_model->get_results();
        $this->log( "DEBUG: Total agents in LatePoint: " . count( $all_agents ) );
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    Darsna_Tutor_Checkout::get_instance();
} );

// Debug function - call this in functions.php if needed: darsna_debug_agent(71);
function darsna_debug_agent( $user_id ) {
    if ( class_exists( 'Darsna_Tutor_Checkout' ) ) {
        $instance = Darsna_Tutor_Checkout::get_instance();
        $instance->debug_latepoint_agent( $user_id );
    }
}

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
<?php
/**
 * Backend functionality for tutor registration
 * 
 * @package Darsna_Tutor_Registration
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// LatePoint's OsWorkPeriodModel will be loaded by LatePoint plugin

/**
 * Backend class - Handles order processing and agent management
 */
class Darsna_Tutor_Backend {
    
    private static $instance;
    private static $cache = [];
    
    private const ACTIVE_STATUSES = ['active'];
    private const INACTIVE_STATUSES = ['pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash'];
    
    /**
     * Singleton instance
     */
    public static function instance() {
        return self::$instance ??= new self();
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->setup_hooks();
    }
    
    /**
     * Setup backend hooks
     */
    private function setup_hooks(): void {
        // Order status hooks
        add_filter( 'woocommerce_cod_process_payment_order_status', [ $this, 'set_cod_hold_status' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completion' ] );
        
        // Subscription hooks
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_subscription_status_change' ], 10, 3 );
        
        // CRITICAL: Register the custom action hook
        add_action( 'darsna_activate_agent', [ $this, 'activate_tutor_agent' ], 10, 2 );
        
        // LatePoint integration hooks
        $this->setup_latepoint_hooks();
    }
    
    /**
     * Setup LatePoint integration hooks
     */
    private function setup_latepoint_hooks(): void {
        // Schedule management removed - users will handle scheduling in LatePoint dashboard
        
        // Admin hooks removed - users will handle scheduling in LatePoint dashboard
    }
    
    // Schedule visibility and work period filtering methods removed - users will handle scheduling in LatePoint dashboard
    
    // ensure_darsna_agent_schedules method removed - users will handle scheduling in LatePoint dashboard
    
    // fix_agent_form_schedule_variables method removed - users will handle scheduling in LatePoint dashboard
    
    // fix_custom_schedule_checkbox method removed - users will handle scheduling in LatePoint dashboard
    
    // sync_darsna_agent_schedule method removed - users will handle scheduling in LatePoint dashboard
    
    // verify_agent_schedule_exists method removed - users will handle scheduling in LatePoint dashboard
    
    /**
     * Get user ID by agent ID
     */
    private function get_user_id_by_agent_id($agent_id) {
        global $wpdb;
        
        // Try to find user by agent wp_user_id first
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}latepoint_agents WHERE id = %d",
            $agent_id
        ));
        
        if ($user_id && get_userdata($user_id)) {
            return $user_id;
        }
        
        // Fallback: search by agent name/email matching
        $agent_data = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name, email FROM {$wpdb->prefix}latepoint_agents WHERE id = %d",
            $agent_id
        ));
        
        if ($agent_data) {
            $user = get_user_by('email', $agent_data->email);
            return $user ? $user->ID : null;
        }
        
        return null;
    }
    
    // get_default_schedule method removed - users will handle scheduling in LatePoint dashboard
    
    // debug_agent_schedule method removed - debugging code cleaned up
    
    /**
     * Set COD orders to on-hold status
     */
    public function set_cod_hold_status( $status ) {
        return 'on-hold';
    }
    
    /**
     * Handle order completion
     */
    public function handle_order_completion( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return;
        }
        
        // Check if this is a subscription order
        if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {
            $this->activate_tutor_agent( $user_id, $order_id );
        } else {
            // For non-subscription orders, activate immediately
            $this->activate_tutor_agent( $user_id, $order_id );
        }
    }
    
    // schedule_agent_activation method removed - direct activation used instead
    
    /**
     * Handle subscription status changes
     */
    public function handle_subscription_status_change( $subscription, string $new_status, string $old_status ): void {
        $user_id = $subscription->get_user_id();
        
        if ( in_array( $new_status, self::ACTIVE_STATUSES, true ) ) {
            $this->handle_subscription_activation( $user_id, $subscription->get_id() );
        } elseif ( in_array( $new_status, self::INACTIVE_STATUSES, true ) ) {
            $this->deactivate_tutor_agent( $user_id );
        }
    }
    
    /**
     * Handle subscription activation
     */
    private function handle_subscription_activation( int $user_id, int $subscription_id ): void {
        // Get the parent order from the subscription
        $subscription = wcs_get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return;
        }
        
        $parent_order_id = $subscription->get_parent_id();
        if ( $parent_order_id ) {
            $this->activate_tutor_agent( $user_id, $parent_order_id );
        }
    }
    
    /**
     * Activate tutor agent in LatePoint
     */
    public function activate_tutor_agent( int $user_id, int $order_id ): void {
        try {
            $tutor_data = $this->get_tutor_data( $user_id );
            if ( ! $tutor_data ) {
                return;
            }
            
            $user = get_user_by( 'ID', $user_id );
            if ( ! $user ) {
                return;
            }
            
            // Check if agent already exists
            $existing_agent = $this->get_existing_agent( $user_id );
            
            if ( $existing_agent ) {
                // Update existing agent
                $this->update_agent_status( $existing_agent->id, 'active' );
                $agent_id = $existing_agent->id;
            } else {
                // Create new agent
                $agent_id = $this->create_new_agent( $user, $tutor_data );
                if ( ! $agent_id ) {
                    return;
                }
            }
            
            // Assign services
            $this->assign_agent_services( $agent_id, $tutor_data );
            
            // Update user role to LatePoint agent
            $user->add_role( 'latepoint_agent' );
            
        } catch ( Exception $e ) {
            // Error handling - agent activation failed
        }
    }
    
    /**
     * Deactivate tutor agent
     */
    public function deactivate_tutor_agent( int $user_id ): void {
        try {
            $agent = $this->get_existing_agent( $user_id );
            if ( $agent ) {
                $this->update_agent_status( $agent->id, 'inactive' );
                
                // Remove tutor role
                $user = get_user_by( 'ID', $user_id );
                if ( $user ) {
                    $user->remove_role( 'latepoint_agent' );
                }
                
            }
        } catch ( Exception $e ) {
            // Error handling - agent deactivation failed
        }
    }
    
    /**
     * Completely remove tutor agent from LatePoint
     */
    public function remove_tutor_agent( int $user_id ): void {
        global $wpdb;
        
        try {
            $agent = $this->get_existing_agent( $user_id );
            if ( $agent ) {
                $agent_id = $agent->id;
                
                // Remove from agents_services table
                $agents_services_table = $wpdb->prefix . 'latepoint_agents_services';
                $wpdb->delete( $agents_services_table, [ 'agent_id' => $agent_id ], [ '%d' ] );
                
                // Remove from agents table
                $agents_table = $wpdb->prefix . 'latepoint_agents';
                $wpdb->delete( $agents_table, [ 'id' => $agent_id ], [ '%d' ] );
                
                // Remove WordPress user role
                $user = get_user_by( 'ID', $user_id );
                if ( $user ) {
                    $user->remove_role( 'latepoint_agent' );
                }
                
            }
        } catch ( Exception $e ) {
            // Error handling - agent removal failed
        }
    }
    
    /**
     * Create new agent in LatePoint
     */
    private function create_new_agent( WP_User $user, array $tutor_data ): ?int {
        global $wpdb;
        
        $agent_data = [
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name' => $user->last_name ?: '',
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $this->format_phone( get_user_meta( $user->ID, 'billing_phone', true ) ),
            'bio' => $tutor_data['bio'] ?? '',
            'wp_user_id' => $user->ID,
            'status' => 'active',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        ];
        
        try {
            // Try v5 Repository first
            if ( class_exists( '\LatePoint\App\Repositories\AgentRepository' ) ) {
                $agent = new \LatePoint\App\Models\Agent();
                foreach ( $agent_data as $key => $value ) {
                    $agent->$key = $value;
                }
                $agent->save();
                return $agent->id;
            } else {
                // Fallback to direct database insertion
                $table = $wpdb->prefix . 'latepoint_agents';
                $result = $wpdb->insert( $table, $agent_data );
                return $result ? $wpdb->insert_id : null;
            }
        } catch ( Exception $e ) {
            return null;
        }
    }
    
    /**
     * Update agent status
     */
    private function update_agent_status( int $agent_id, string $status ): bool {
        global $wpdb;
        
        try {
            // Try v5 Repository first
            if ( class_exists( '\LatePoint\App\Repositories\AgentRepository' ) ) {
                $agent = \LatePoint\App\Repositories\AgentRepository::find_by_id( $agent_id );
                if ( $agent ) {
                    $agent->status = $status;
                    $agent->updated_at = current_time( 'mysql' );
                    $agent->save();
                    return true;
                }
            } else {
                // Fallback to direct database update
                $table = $wpdb->prefix . 'latepoint_agents';
                $result = $wpdb->update(
                    $table,
                    [ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ],
                    [ 'id' => $agent_id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                return $result !== false;
            }
        } catch ( Exception $e ) {
            // Error handling - status update failed
        }
        
        return false;
    }
    
    /**
     * Assign services to agent using LatePoint API
     */
    private function assign_agent_services( int $agent_id, array $tutor_data ): bool {
        try {
            // Check if LatePoint AgentsServicesRepository is available
            if ( ! class_exists( '\OsRepositories\AgentsServicesRepository' ) ) {
                return $this->assign_agent_services_fallback( $agent_id, $tutor_data );
            }
            
            // Build service assignment rows for all services
            $rows = [];
            foreach ( $tutor_data['services'] as $service ) {
                $rows[] = [
                    'service_id'         => $service['service_id'],
                    'location_id'        => $tutor_data['location_id'] ?? 1,
                    'is_custom_hours'    => 0,
                'custom_hours'       => null,
                    'is_custom_price'    => 1, // Always custom price for tutors
                    'custom_price'       => $service['rate'],
                    'is_custom_duration' => isset( $tutor_data['custom_duration'] ) ? 1 : 0,
                    'custom_duration'    => $tutor_data['custom_duration'] ?? null,
                ];
            }
            
            // Use LatePoint repository to sync agent services
            $repo = \OsRepositories\AgentsServicesRepository::instance();
            $success = $repo->sync_agent_services( $agent_id, $rows );
            
            return (bool) $success;
        } catch ( Exception $e ) {
            return $this->assign_agent_services_fallback( $agent_id, $tutor_data );
        }
    }
    
    /**
     * Fallback method for direct database assignment
     */
    private function assign_agent_services_fallback( int $agent_id, array $tutor_data ): bool {
        global $wpdb;
        
        try {
            // Clear existing assignments
            $table = $wpdb->prefix . 'latepoint_agents_services';
            $wpdb->delete( $table, [ 'agent_id' => $agent_id ], [ '%d' ] );
            
            // Add new assignments for all services
            $success = true;
            foreach ( $tutor_data['services'] as $service ) {
                $result = $wpdb->insert( $table, [
                    'agent_id' => $agent_id,
                    'service_id' => $service['service_id'],
                    'location_id' => $tutor_data['location_id'] ?? 1,
                    'is_custom_hours' => 0,
                    'is_custom_price' => 1, // Always custom price for tutors
                    'is_custom_duration' => isset( $tutor_data['custom_duration'] ) ? 1 : 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ], [ '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ] );
                
                if ( $result === false ) {
                    $success = false;
                }
                
                // Also insert custom pricing if needed
                if ( $result !== false && isset( $service['rate'] ) ) {
                    $this->set_custom_price( $agent_id, $service['service_id'], $service['rate'] );
                }
            }
            
            return $success;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    // set_agent_schedule method removed - users will handle scheduling in LatePoint dashboard
    
    // set_agent_schedule_fallback method removed - users will handle scheduling in LatePoint dashboard
    
    // insert_work_period_direct method removed - users will handle scheduling in LatePoint dashboard
    
    /**
     * Get existing agent by user ID
     */
    private function get_existing_agent( int $user_id ) {
        $cache_key = "agent_{$user_id}";
        
        if ( ! isset( self::$cache[ $cache_key ] ) ) {
            try {
                // Try v5 Repository first
                if ( class_exists( '\LatePoint\App\Repositories\AgentRepository' ) ) {
                    $agents = \LatePoint\App\Repositories\AgentRepository::where( [ 'wp_user_id' => $user_id ] );
                    self::$cache[ $cache_key ] = $agents[0] ?? null;
                } else {
                    // Fallback to direct database query
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'latepoint_agents';
                    
                    $agent = $wpdb->get_row(
                        $wpdb->prepare( "SELECT * FROM {$table_name} WHERE wp_user_id = %d", $user_id )
                    );
                    
                    self::$cache[ $cache_key ] = $agent;
                }
                
            } catch ( Exception $e ) {
                self::$cache[ $cache_key ] = null;
            }
        }
        
        return self::$cache[ $cache_key ];
    }
    
    /**
     * Get user's most recent order
     */
    private function get_user_order( int $user_id ) {
        $cache_key = "order_{$user_id}";
        
        if ( ! isset( self::$cache[ $cache_key ] ) ) {
            $orders = wc_get_orders( [
                'customer' => $user_id,
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC'
            ] );
            
            self::$cache[ $cache_key ] = $orders[0] ?? null;
        }
        
        return self::$cache[ $cache_key ];
    }
    
    /**
     * Get tutor data from order
     */
    private function get_tutor_data( int $user_id ): ?array {
        $order = $this->get_user_order( $user_id );
        if ( ! $order ) {
            return null;
        }
        
        // Try to get new multi-service data first
        $services_data = $order->get_meta( '_tutor_services' );
        
        if ( ! empty( $services_data ) && is_array( $services_data ) ) {
            // New multi-service format
            return [
                'services' => $services_data,
                'bio' => sanitize_textarea_field( $order->get_meta( '_tutor_bio' ) )
                // Schedule data removed - handled by LatePoint
            ];
        }
        
        // Fallback to legacy single service format
        $service_id = $order->get_meta( '_tutor_service_id' );
        $hourly_rate = $order->get_meta( '_tutor_hourly_rate' );
        
        if ( ! $service_id || ! $hourly_rate ) {
            return null;
        }
        
        // Convert legacy format to new format
        return [
            'services' => [[
                'service_id' => (int) $service_id,
                'rate' => (float) $hourly_rate
            ]],
            'bio' => sanitize_textarea_field( $order->get_meta( '_tutor_bio' ) )
            // Schedule data removed - handled by LatePoint
        ];
    }
    
    /**
     * Format phone number
     */
    private function format_phone( string $phone ): string {
        if ( empty( $phone ) ) {
            return '';
        }
        
        $clean = preg_replace( '/[^\d+]/', '', $phone );
        
        // Add Jordan country code if missing
        if ( ! empty( $clean ) && strpos( $clean, '+' ) !== 0 ) {
            $clean = preg_replace( '/\D/', '', $clean );
            $clean = '+962' . ltrim( $clean, '962' );
        }
        
        return $clean;
    }
    
    /**
     * Convert time string to minutes
     */
    private function time_to_minutes( string $time ): int {
        $parts = explode( ':', $time );
        return ( (int) $parts[0] * 60 ) + (int) ( $parts[1] ?? 0 );
    }
    
    /**
     * Get all agents from LatePoint using Repository API
     */
    public function get_all_agents(): array {
        try {
            // Use LatePoint Repository API if available
            if ( class_exists( '\OsRepositories\AgentsRepository' ) ) {
                $models = \OsRepositories\AgentsRepository::instance()->get_all();
                
                $out = [];
                foreach ( $models as $m ) {
                    // Convert model to array
                    $data = $m->toArray();
                    
                    // Attach the WordPress user
                    $wp = get_userdata( $m->wp_user_id );
                    if ( $wp ) {
                        $data['wp_user_id'] = $wp->ID;
                        $data['wp_user'] = $wp;
                    }
                    
                    $out[] = $data;
                }
                
                return $out;
            }
        } catch ( Exception $e ) {
            // Fall through to database fallback
        }
        
        // Fallback to direct database query
        global $wpdb;
        
        $agents_table = $wpdb->prefix . 'latepoint_agents';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$agents_table'");
        if (!$table_exists) {
            return [];
        }
        
        $agents = $wpdb->get_results(
            "SELECT * FROM {$agents_table} ORDER BY created_at DESC"
        );
        
        if ($wpdb->last_error) {
            return [];
        }
        
        // Enhance with WordPress user data
        foreach ( $agents as &$agent ) {
            $wp_user = get_user_by( 'email', $agent->email );
            if ( $wp_user ) {
                $agent->wp_user_id = $wp_user->ID;
                $agent->wp_user = $wp_user;
            }
        }
        
        return $agents ?: [];
    }
    
    /**
     * Get agent by ID using Repository API
     */
    public function get_agent_by_id( int $agent_id ) {
        try {
            // Use LatePoint Repository API if available
            if ( class_exists( '\OsRepositories\AgentsRepository' ) ) {
                $agent = \OsRepositories\AgentsRepository::instance()->get_by_id( $agent_id );
                return $agent ? $agent->toArray() : null;
            }
        } catch ( Exception $e ) {
            // Error handling - repository API failed
        }
        
        // Fallback to direct database query
        global $wpdb;
        
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}latepoint_agents WHERE id = %d",
            $agent_id
        ));
    }
    
    /**
     * Get agent services using Repository API
     */
    public function get_agent_services( int $agent_id ): array {
        try {
            // Use LatePoint Repository API if available
            if ( class_exists( '\OsRepositories\AgentsServicesRepository' ) ) {
                $agent_services = \OsRepositories\AgentsServicesRepository::instance()->get_agent_services( $agent_id );
                
                $services = [];
                foreach ( $agent_services as $agent_service ) {
                    $service_data = $agent_service->toArray();
                    
                    // Get service details
                    if ( class_exists( '\OsRepositories\ServicesRepository' ) ) {
                        $service = \OsRepositories\ServicesRepository::instance()->get_by_id( $agent_service->service_id );
                        if ( $service ) {
                            $service_data = array_merge( $service_data, $service->toArray() );
                        }
                    }
                    
                    // Get custom rate from custom_prices table if available
                    
                    // Check if custom pricing is enabled (handle both string and integer values)
                    $has_custom_price = ($agent_service->is_custom_price === 'yes' || $agent_service->is_custom_price === '1' || $agent_service->is_custom_price === 1);
                    
                    if ( $has_custom_price ) {
                        global $wpdb;
                        $custom_price = $wpdb->get_var( $wpdb->prepare(
                            "SELECT charge_amount FROM {$wpdb->prefix}latepoint_custom_prices 
                             WHERE agent_id = %d AND service_id = %d",
                            $agent_id,
                            $agent_service->service_id
                        ));
                        if ( $custom_price !== null ) {
                            $service_data['custom_rate'] = floatval( $custom_price );
                        }
                    } else {
                        // Always try to get custom price as fallback
                        global $wpdb;
                        $custom_price = $wpdb->get_var( $wpdb->prepare(
                            "SELECT charge_amount FROM {$wpdb->prefix}latepoint_custom_prices 
                             WHERE agent_id = %d AND service_id = %d",
                            $agent_id,
                            $agent_service->service_id
                        ));
                        if ( $custom_price !== null ) {
                            $service_data['custom_rate'] = floatval( $custom_price );

                        }
                    }
                    
                    $services[] = (object) $service_data;
                }
                
                return $services;
            }
        } catch ( Exception $e ) {
            // Error handling - repository API failed
        }
        
        // Fallback to direct database query
        global $wpdb;
        
        $agents_services_table = $wpdb->prefix . 'latepoint_agents_services';
        $services_table = $wpdb->prefix . 'latepoint_services';
        
        $services = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.name, s.id, s.charge_amount, ags.is_custom_hours, ags.is_custom_duration, ags.is_custom_price
             FROM {$agents_services_table} ags
             JOIN {$services_table} s ON ags.service_id = s.id
             WHERE ags.agent_id = %d",
            $agent_id
        )) ?: [];
        
        // Get custom rates from custom_prices table
        foreach ($services as $service) {
            // Check if custom pricing is enabled (handle both string and integer values)
            $has_custom_price = ($service->is_custom_price === 'yes' || $service->is_custom_price === '1' || $service->is_custom_price === 1);
            
            if ($has_custom_price) {
                $custom_price = $wpdb->get_var($wpdb->prepare(
                    "SELECT charge_amount FROM {$wpdb->prefix}latepoint_custom_prices 
                     WHERE agent_id = %d AND service_id = %d",
                    $agent_id,
                    $service->id
                ));
                
                if ($custom_price !== null) {
                    $service->custom_rate = floatval($custom_price);
                }
            } else {
                // Always try to get custom price as fallback
                $custom_price = $wpdb->get_var($wpdb->prepare(
                    "SELECT charge_amount FROM {$wpdb->prefix}latepoint_custom_prices 
                     WHERE agent_id = %d AND service_id = %d",
                    $agent_id,
                    $service->id
                ));
                
                if ($custom_price !== null) {
                    $service->custom_rate = floatval($custom_price);
                }
            }
        }
        return $services;
    }
    
    // get_agent_schedule method removed - users will handle scheduling in LatePoint dashboard
    
    /**
     * Convert minutes to HH:MM format
     */
    private function minutes_to_time( int $minutes ): string {
        $hours = floor( $minutes / 60 );
        $mins = $minutes % 60;
        return sprintf( '%02d:%02d', $hours, $mins );
    }
    
    /**
     * Update agent services and rates
     */
    public function update_agent_services( int $agent_id, array $services, array $service_rates = [] ): bool {
        global $wpdb;
        

        
        try {
            // Remove existing services
            $wpdb->delete(
                $wpdb->prefix . 'latepoint_agents_services',
                ['agent_id' => $agent_id],
                ['%d']
            );
            
            // Add new services with rates
            foreach ($services as $service_id) {
                $service_id = intval($service_id);
                $custom_rate = isset($service_rates[$service_id]) ? floatval($service_rates[$service_id]) : null;
                
                $wpdb->insert(
                    $wpdb->prefix . 'latepoint_agents_services',
                    [
                        'agent_id' => $agent_id,
                        'service_id' => $service_id,
                        'location_id' => 1, // Default location
                        'is_custom_hours' => 'no',
                        'is_custom_price' => $custom_rate ? 'yes' : 'no',
                        'is_custom_duration' => 'no',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
                );
                
                // Store custom rate in custom_prices table if provided
                if ($custom_rate) {
                    // Remove existing custom price
                    $wpdb->delete(
                        $wpdb->prefix . 'latepoint_custom_prices',
                        [
                            'agent_id' => $agent_id,
                            'service_id' => $service_id,
                            'location_id' => 1
                        ],
                        ['%d', '%d', '%d']
                    );
                    
                    // Insert new custom price
                    $wpdb->insert(
                        $wpdb->prefix . 'latepoint_custom_prices',
                        [
                            'agent_id' => $agent_id,
                            'service_id' => $service_id,
                            'location_id' => 1,
                            'is_price_variable' => false,
                            'price_min' => null,
                            'price_max' => null,
                            'charge_amount' => $custom_rate,
                            'is_deposit_required' => false,
                            'deposit_amount' => null,
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%d', '%d', '%f', '%f', '%f', '%d', '%f', '%s', '%s']
                    );
                    

                }
            }
            
            return true;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Get agent meta data
     */
    public function get_agent_meta( int $agent_id, string $meta_key ) {
        try {
            // Use LatePoint Repository API if available
            if ( class_exists( '\OsRepositories\AgentsRepository' ) ) {
                $agent = \OsRepositories\AgentsRepository::instance()->get_by_id( $agent_id );
                if ( $agent ) {
                    return $agent->get_meta( $meta_key );
                }
            }
        } catch ( Exception $e ) {
            // Error handling - repository API failed
        }
        
        // Fallback to direct database query
        global $wpdb;
        
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}latepoint_agent_meta WHERE object_id = %d AND meta_key = %s",
            $agent_id,
            $meta_key
        ));
    }
    
    /**
     * Set custom price for agent service
     */
    private function set_custom_price( int $agent_id, int $service_id, float $custom_rate ): bool {
        global $wpdb;
        
        try {
            // Remove existing custom price
            $wpdb->delete(
                $wpdb->prefix . 'latepoint_custom_prices',
                [
                    'agent_id' => $agent_id,
                    'service_id' => $service_id,
                    'location_id' => 1
                ],
                ['%d', '%d', '%d']
            );
            
            // Insert new custom price
            $result = $wpdb->insert(
                $wpdb->prefix . 'latepoint_custom_prices',
                [
                    'agent_id' => $agent_id,
                    'service_id' => $service_id,
                    'location_id' => 1,
                    'is_price_variable' => false,
                    'price_min' => null,
                    'price_max' => null,
                    'charge_amount' => $custom_rate,
                    'is_deposit_required' => false,
                    'deposit_amount' => null,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%d', '%f', '%f', '%f', '%d', '%f', '%s', '%s']
            );
            
            if ( $result !== false ) {
                return true;
            }
            
            return false;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Update agent meta data using Repository API
     */
    public function update_agent_meta( int $agent_id, string $meta_key, $meta_value ): bool {
        try {
            // Use LatePoint Repository API if available
            if ( class_exists( '\OsRepositories\AgentsRepository' ) ) {
                $agent = \OsRepositories\AgentsRepository::instance()->get_by_id( $agent_id );
                if ( $agent ) {
                    $agent->save_meta( $meta_key, $meta_value );
                    return true;
                }
            }
        } catch ( Exception $e ) {
            // Error handling - repository API failed
        }
        
        // Fallback to direct database query
        global $wpdb;
        
        try {
            // Check if meta exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_id FROM {$wpdb->prefix}latepoint_agent_meta WHERE object_id = %d AND meta_key = %s",
                $agent_id,
                $meta_key
            ));
            
            if ($existing) {
                // Update existing meta
                $result = $wpdb->update(
                    $wpdb->prefix . 'latepoint_agent_meta',
                    ['meta_value' => $meta_value],
                    ['object_id' => $agent_id, 'meta_key' => $meta_key],
                    ['%s'],
                    ['%d', '%s']
                );
            } else {
                // Insert new meta
                $result = $wpdb->insert(
                    $wpdb->prefix . 'latepoint_agent_meta',
                    [
                        'object_id' => $agent_id,
                        'meta_key' => $meta_key,
                        'meta_value' => $meta_value
                    ],
                    ['%d', '%s', '%s']
                );
            }
            
            return $result !== false;
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Update agent basic information using Repository API
     */
    public function update_agent_basic_info( int $agent_id, array $form_data ): bool {
        try {
            // Use LatePoint Repository API if available
            if ( class_exists( '\OsRepositories\AgentsRepository' ) ) {
                $agent = \OsRepositories\AgentsRepository::instance()->get_by_id( $agent_id );
                if ( $agent ) {
                    $agent->first_name = sanitize_text_field($form_data['first_name'] ?? '');
                    $agent->last_name = sanitize_text_field($form_data['last_name'] ?? '');
                    $agent->email = sanitize_email($form_data['email'] ?? '');
                    $agent->phone = sanitize_text_field($form_data['phone'] ?? '');
                    $agent->status = sanitize_text_field($form_data['status'] ?? 'active');
                    $agent->bio = sanitize_textarea_field($form_data['bio'] ?? '');
                    $agent->features = sanitize_textarea_field($form_data['features'] ?? '');
                    
                    return $agent->save();
                }
            }
        } catch ( Exception $e ) {
            // Error handling - repository API failed
        }
        
        // Fallback to direct database query
        global $wpdb;
        
        try {
            $agent_data = [
                'first_name' => sanitize_text_field($form_data['first_name'] ?? ''),
                'last_name' => sanitize_text_field($form_data['last_name'] ?? ''),
                'email' => sanitize_email($form_data['email'] ?? ''),
                'phone' => sanitize_text_field($form_data['phone'] ?? ''),
                'status' => sanitize_text_field($form_data['status'] ?? 'active'),
                'bio' => sanitize_textarea_field($form_data['bio'] ?? ''),
                'features' => sanitize_textarea_field($form_data['features'] ?? ''),
                'updated_at' => current_time('mysql')
            ];
            
            $result = $wpdb->update(
                $wpdb->prefix . 'latepoint_agents',
                $agent_data,
                ['id' => $agent_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            return $result !== false;
        } catch ( Exception $e ) {
            return false;
        }
    }
}
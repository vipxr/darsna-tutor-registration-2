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
    }
    
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
            error_log( "Darsna: No user ID found for order {$order_id}" );
            return;
        }
        
        // Check if this is a subscription order
        if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {
            $this->schedule_agent_activation( $user_id, $order_id );
        } else {
            // For non-subscription orders, activate immediately
            $this->activate_tutor_agent( $user_id, $order_id );
        }
    }
    
    /**
     * Schedule agent activation for subscription orders
     */
    private function schedule_agent_activation( int $user_id, int $order_id ): void {
        // For subscription orders, we wait for the subscription to become active
        $subscriptions = wcs_get_subscriptions_for_order( $order_id );
        
        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->has_status( 'active' ) ) {
                $this->activate_tutor_agent( $user_id, $order_id );
                break;
            }
        }
    }
    
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
                error_log( "Darsna: No tutor data found for user {$user_id}" );
                return;
            }
            
            $user = get_user_by( 'ID', $user_id );
            if ( ! $user ) {
                error_log( "Darsna: User {$user_id} not found" );
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
                    error_log( "Darsna: Failed to create agent for user {$user_id}" );
                    return;
                }
            }
            
            // Assign services and set schedule
            $this->assign_agent_services( $agent_id, $tutor_data['service_id'], $tutor_data );
            $this->set_agent_schedule( $user_id, $tutor_data['schedule'] );
            
            // Update user role to LatePoint agent
            $user->add_role( 'latepoint_agent' );
            
            error_log( "Darsna: Successfully activated agent for user {$user_id}" );
            
        } catch ( Exception $e ) {
            error_log( "Darsna: Error activating agent for user {$user_id}: " . $e->getMessage() );
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
                
                error_log( "Darsna: Deactivated agent for user {$user_id}" );
            }
        } catch ( Exception $e ) {
            error_log( "Darsna: Error deactivating agent for user {$user_id}: " . $e->getMessage() );
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
                
                error_log( "Darsna: Completely removed agent for user {$user_id}" );
            }
        } catch ( Exception $e ) {
            error_log( "Darsna: Error removing agent for user {$user_id}: " . $e->getMessage() );
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
            error_log( "Darsna: Error creating agent: " . $e->getMessage() );
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
            error_log( "Darsna: Error updating agent status: " . $e->getMessage() );
        }
        
        return false;
    }
    
    /**
     * Assign services to agent using LatePoint API
     */
    private function assign_agent_services( int $agent_id, int $service_id, array $tutor_data ): bool {
        try {
            // Check if LatePoint AgentsServicesRepository is available
            if ( ! class_exists( '\OsRepositories\AgentsServicesRepository' ) ) {
                error_log( "Darsna: LatePoint AgentsServicesRepository not found, falling back to direct database" );
                return $this->assign_agent_services_fallback( $agent_id, $service_id, $tutor_data );
            }
            
            // Build the service assignment row with custom hours and pricing
            $row = [
                'service_id'         => $service_id,
                'location_id'        => $tutor_data['location_id'] ?? 1,
                'is_custom_hours'    => isset( $tutor_data['schedule'] ) ? 1 : 0,
                'custom_hours'       => $tutor_data['schedule'] ?? null,
                'is_custom_price'    => isset( $tutor_data['hourly_rate'] ) ? 1 : 0,
                'custom_price'       => $tutor_data['hourly_rate'] ?? null,
                'is_custom_duration' => isset( $tutor_data['custom_duration'] ) ? 1 : 0,
                'custom_duration'    => $tutor_data['custom_duration'] ?? null,
            ];
            
            // Use LatePoint repository to sync agent services
            $repo = \OsRepositories\AgentsServicesRepository::instance();
            $success = $repo->sync_agent_services( $agent_id, [ $row ] );
            
            return (bool) $success;
        } catch ( Exception $e ) {
            error_log( "Darsna: Error assigning services via API: " . $e->getMessage() );
            return $this->assign_agent_services_fallback( $agent_id, $service_id, $tutor_data );
        }
    }
    
    /**
     * Fallback method for direct database assignment
     */
    private function assign_agent_services_fallback( int $agent_id, int $service_id, array $tutor_data ): bool {
        global $wpdb;
        
        try {
            // Clear existing assignments
            $table = $wpdb->prefix . 'latepoint_agents_services';
            $wpdb->delete( $table, [ 'agent_id' => $agent_id ], [ '%d' ] );
            
            // Add new assignment with custom pricing and hours from form data
            $result = $wpdb->insert( $table, [
                'agent_id' => $agent_id,
                'service_id' => $service_id,
                'location_id' => $tutor_data['location_id'] ?? 1,
                'is_custom_hours' => isset( $tutor_data['schedule'] ) ? 1 : 0,
                'is_custom_price' => isset( $tutor_data['hourly_rate'] ) ? 1 : 0,
                'is_custom_duration' => isset( $tutor_data['custom_duration'] ) ? 1 : 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ], [ '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ] );
            
            return $result !== false;
        } catch ( Exception $e ) {
            error_log( "Darsna: Error in fallback assignment: " . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Set agent schedule using LatePoint OsWorkPeriodModel
     */
    public function set_agent_schedule( int $user_id, array $schedule ): bool {

        error_log( 'set_agent_schedule called for user_id='.$user_id.'; schedule='. print_r($schedule, true) );

        // Must have at least one day selected
        if ( empty( $schedule['days'] ) ) {
            return false;
        }
        
        // Check if OsWorkPeriodModel is available
        if ( ! class_exists( 'OsModels\OsWorkPeriodModel' ) ) {
            error_log( "Darsna: OsWorkPeriodModel not found, falling back to direct database" );
            return $this->set_agent_schedule_fallback( $user_id, $schedule );
        }
        
        try {
            // Fetch LatePoint agent record
            $agent = $this->get_existing_agent( $user_id );
            if ( ! $agent ) {
                return false;
            }
            
            // Convert hh:mm to minutes since midnight
            $start = $this->time_to_minutes( $schedule['start'] ?? '09:00' );
            $end = $this->time_to_minutes( $schedule['end'] ?? '17:00' );
            $location_id = $schedule['location_id'] ?? 1;
            
            // Delete any old periods for this agent
            \OsModels\OsWorkPeriodModel::where( 'agent_id', $agent->id )->delete();
            
            // Map keys to LatePoint week_day numbers
            $day_map = [
                'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4,
                'fri' => 5, 'sat' => 6, 'sun' => 7
            ];
            
            // Insert a new row for each selected day
            foreach ( $schedule['days'] as $day ) {
                if ( ! isset( $day_map[ $day ] ) ) {
                    continue;
                }
                \OsModels\OsWorkPeriodModel::create([
                    'agent_id' => $agent->id,
                    'service_id' => 0, // 0 = all services
                    'location_id' => $location_id,
                    'week_day' => $day_map[ $day ],
                    'start_time' => $start,
                    'end_time' => $end,
                    'chain_id' => wp_generate_uuid4(),
                ]);
            }
            
            return true;
            
        } catch ( Exception $e ) {
            error_log( "Darsna: Error setting schedule via OsWorkPeriodModel: " . $e->getMessage() );
            return $this->set_agent_schedule_fallback( $user_id, $schedule );
        }
    }
    
    /**
     * Fallback method for direct database schedule setting
     */
    private function set_agent_schedule_fallback( int $user_id, array $schedule ): bool {
        if ( empty( $schedule ) ) {
            return false;
        }
        
        $agent = $this->get_existing_agent( $user_id );
        if ( ! $agent ) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'latepoint_work_periods';
        
        // Clear existing schedule
        $wpdb->delete( $table, [ 'agent_id' => $agent->id ], [ '%d' ] );
        
        // Day mapping
        $day_map = [
            'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4,
            'fri' => 5, 'sat' => 6, 'sun' => 7
        ];
        
        $start_time = $this->time_to_minutes( $schedule['start'] ?? '09:00' );
        $end_time = $this->time_to_minutes( $schedule['end'] ?? '17:00' );
        
        // Insert new schedule
        foreach ( $day_map as $day => $day_number ) {
            if ( ! empty( $schedule['days'] ) && in_array( $day, $schedule['days'] ) ) {
                $wpdb->insert( $table, [
                    'agent_id' => $agent->id,
                    'service_id' => 0, // 0 means all services
                    'location_id' => $schedule['location_id'] ?? 1,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'week_day' => $day_number,
                    'chain_id' => wp_generate_uuid4(),
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' )
                ], [ '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ] );
            }
        }
        
        return true;
    }
    
    /**
     * Get existing agent by user ID
     */
    private function get_existing_agent( int $user_id ) {
        $cache_key = "agent_{$user_id}";
        
        if ( ! isset( self::$cache[ $cache_key ] ) ) {
            try {
                // Try v5 Repository first
                if ( class_exists( '\LatePoint\App\Repositories\AgentRepository' ) ) {
                    self::$cache[ $cache_key ] = \LatePoint\App\Repositories\AgentRepository::where( [ 'wp_user_id' => $user_id ] )[0] ?? null;
                } else {
                    // Fallback to direct database query
                    global $wpdb;
                    self::$cache[ $cache_key ] = $wpdb->get_row(
                        $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}latepoint_agents WHERE wp_user_id = %d", $user_id )
                    );
                }
            } catch ( Exception $e ) {
                error_log( "Darsna: Error fetching agent: " . $e->getMessage() );
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
            error_log( "Darsna: No order found for user ID: {$user_id}" );
            return null;
        }
        
        $service_id = $order->get_meta( '_tutor_service_id' );
        $hourly_rate = $order->get_meta( '_tutor_hourly_rate' );
        
        if ( ! $service_id || ! $hourly_rate ) {
            error_log( "Darsna: Missing tutor meta data for user {$user_id} - service_id: {$service_id}, rate: {$hourly_rate}" );
            return null;
        }
        
        return [
            'service_id' => (int) $service_id,
            'hourly_rate' => (int) $hourly_rate,
            'bio' => sanitize_textarea_field( $order->get_meta( '_tutor_bio' ) ),
            'schedule' => $order->get_meta( '_tutor_schedule' ) ?: []
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
            error_log( "Darsna: Error using LatePoint Repository API: " . $e->getMessage() );
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
                    
                    // Get custom rate from agent meta if available
                    if ( $agent_service->is_custom_price === 'yes' ) {
                        $custom_rate = $this->get_agent_meta( $agent_id, "service_{$agent_service->service_id}_rate" );
                        if ( $custom_rate !== null ) {
                            $service_data['custom_rate'] = floatval( $custom_rate );
                        }
                    }
                    
                    $services[] = (object) $service_data;
                }
                
                return $services;
            }
        } catch ( Exception $e ) {
            error_log( "Darsna: Error using LatePoint Repository API: " . $e->getMessage() );
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
        
        // Get custom rates from agent meta
        foreach ($services as $service) {
            $custom_rate = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}latepoint_agent_meta 
                 WHERE object_id = %d AND meta_key = %s",
                $agent_id,
                "service_{$service->id}_rate"
            ));
            
            if ($custom_rate !== null) {
                $service->custom_rate = floatval($custom_rate);
            }
        }
        
        return $services;
    }
    
    /**
     * Get agent schedule from meta data
     */
    public function get_agent_schedule( int $agent_id ): array {
        global $wpdb;
        
        $schedule_data = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}latepoint_agent_meta WHERE object_id = %d AND meta_key LIKE 'schedule_%'",
            $agent_id
        ));
        
        $schedule = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        // Initialize default schedule
        foreach ( $days as $day ) {
            $schedule[$day] = [
                'enabled' => false,
                'start_time' => '09:00',
                'end_time' => '17:00'
            ];
        }
        
        // Parse existing schedule data
        foreach ( $schedule_data as $meta ) {
            if ( strpos( $meta->meta_key, 'schedule_' ) === 0 ) {
                $parts = explode( '_', $meta->meta_key );
                if ( count( $parts ) >= 3 ) {
                    $day = $parts[1];
                    $field = $parts[2];
                    
                    if ( isset( $schedule[$day] ) ) {
                        if ( $field === 'enabled' ) {
                            $schedule[$day]['enabled'] = ( $meta->meta_value === '1' || $meta->meta_value === 'true' );
                        } else {
                            $schedule[$day][$field] = $meta->meta_value;
                        }
                    }
                }
            }
        }
        
        return $schedule;
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
                
                // Store custom rate in agent meta if provided
                if ($custom_rate) {
                    $this->update_agent_meta($agent_id, "service_{$service_id}_rate", $custom_rate);
                }
            }
            
            return true;
        } catch ( Exception $e ) {
            error_log( "Darsna: Error updating agent services: " . $e->getMessage() );
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
            error_log( "Darsna: Error using LatePoint Repository API: " . $e->getMessage() );
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
            error_log( "Darsna: Error using LatePoint Repository API: " . $e->getMessage() );
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
            error_log( "Darsna: Error updating agent meta: " . $e->getMessage() );
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
            error_log( "Darsna: Error using LatePoint Repository API: " . $e->getMessage() );
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
            error_log( "Darsna: Error updating agent basic info: " . $e->getMessage() );
            return false;
        }
    }
}
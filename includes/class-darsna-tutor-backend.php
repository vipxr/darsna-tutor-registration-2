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
            $this->assign_agent_services( $agent_id, $tutor_data['service_id'] );
            $this->set_agent_schedule( $user_id, $tutor_data['schedule'] );
            
            // Update user role
            $user->add_role( 'tutor' );
            
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
                    $user->remove_role( 'tutor' );
                }
                
                error_log( "Darsna: Deactivated agent for user {$user_id}" );
            }
        } catch ( Exception $e ) {
            error_log( "Darsna: Error deactivating agent for user {$user_id}: " . $e->getMessage() );
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
     * Assign services to agent
     */
    private function assign_agent_services( int $agent_id, int $service_id ): bool {
        global $wpdb;
        
        try {
            // Clear existing assignments
            $table = $wpdb->prefix . 'latepoint_agent_services';
            $wpdb->delete( $table, [ 'agent_id' => $agent_id ], [ '%d' ] );
            
            // Add new assignment
            $result = $wpdb->insert( $table, [
                'agent_id' => $agent_id,
                'service_id' => $service_id
            ], [ '%d', '%d' ] );
            
            return $result !== false;
        } catch ( Exception $e ) {
            error_log( "Darsna: Error assigning services: " . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Set agent schedule
     */
    private function set_agent_schedule( int $user_id, array $schedule ): bool {
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
                    'location_id' => 1, // Default location
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
}
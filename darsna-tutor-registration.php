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

/**
 * Main plugin class - Optimized for LatePoint v5
 */
final class Darsna_Tutor_Checkout {
    
    private static $instance;
    private static $cache = [];
    
    // Constants for better maintainability
    private const REQUIRED_PLUGINS = [
        'woocommerce/woocommerce.php',
        'woocommerce-subscriptions/woocommerce-subscriptions.php',
        'latepoint/latepoint.php'
    ];
    
    private const ACTIVE_STATUSES = ['active'];
    private const INACTIVE_STATUSES = ['pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash'];
    
    private const DEFAULT_SCHEDULE_DAYS = ['mon', 'tue', 'wed', 'thu', 'sun'];
    private const DEFAULT_WORK_HOURS = ['start' => '09:00', 'end' => '17:00'];
    
    // Rate configuration
    private const MIN_RATE = 5;
    private const MAX_RATE = 50;
    private const RATE_STEP = 5;
    
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
        add_action( 'init', [ $this, 'init' ] );
    }
    
    /**
     * Initialize plugin
     */
    public function init(): void {
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'show_dependency_notice' ] );
            return;
        }
        
        $this->setup_hooks();
    }
    
    /**
     * Setup all WordPress hooks
     */
    private function setup_hooks(): void {
        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'render_tutor_fields' ] );
        
        // Checkout processing hooks
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_checkout' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_order_meta' ] );
        
        // Order status hooks
        add_filter( 'woocommerce_cod_process_payment_order_status', [ $this, 'set_cod_hold_status' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completion' ] );
        
        // Subscription hooks
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_subscription_status_change' ], 10, 3 );
        
        // User management hooks
        add_action( 'delete_user', [ $this, 'handle_user_deletion' ] );
        
        // Menu customization
        add_filter( 'wp_nav_menu_items', [ $this, 'customize_menu' ], 99, 2 );
        
        // LatePoint pricing hook
        add_filter( 'latepoint_full_amount_for_service', [ $this, 'apply_dynamic_pricing' ], 10, 3 );
        
        // CRITICAL: Register the custom action hook
        add_action( 'darsna_activate_agent', [ $this, 'activate_tutor_agent' ], 10, 2 );
    }
    
    /**
     * Check plugin dependencies
     */
    private function check_dependencies(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach ( self::REQUIRED_PLUGINS as $plugin ) {
            if ( ! is_plugin_active( $plugin ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Show dependency notice
     */
    public function show_dependency_notice(): void {
        printf(
            '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
            esc_html__( 'Tutor Registration', 'darsna' ),
            esc_html__( 'Missing required plugins: WooCommerce, WooCommerce Subscriptions, or LatePoint', 'darsna' )
        );
    }
    
    /**
     * Set COD orders to hold status
     */
    public function set_cod_hold_status(): string {
        return 'on-hold';
    }
    
    /**
     * Handle user deletion
     */
    public function handle_user_deletion( int $user_id ): void {
        $this->sync_agent_status( $user_id, 'disabled' );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets(): void {
        if ( ! is_checkout() ) {
            return;
        }
        
        // Inline JavaScript for enhanced UX
        $js = $this->get_checkout_javascript();
        wp_add_inline_script( 'jquery', $js );
        
        // Inline CSS for modern styling
        $css = $this->get_checkout_styles();
        wp_add_inline_style( 'woocommerce-general', $css );
    }
    
    /**
     * Get optimized checkout JavaScript
     */
    private function get_checkout_javascript(): string {
        return <<<'JS'
jQuery(function($) {
    const $phoneField = $('#billing_phone');
    const $bioField = $('#tutor_bio');
    const $dayCheckboxes = $('.day');
    const $timesContainer = $('.times');
    
    // Enhanced phone number formatting with Jordan country code support
    $phoneField.attr('placeholder', '+962xxxxxxxxx').on('input', function() {
        let value = $(this).val().replace(/[^\d+]/g, '');
        
        // Auto-add Jordan country code if no country code present
        if (value && !value.startsWith('+')) {
            value = value.startsWith('962') ? '+' + value : '+962' + value;
        }
        
        $(this).val(value);
    });
    
    // Bio character counter with better UX
    if ($bioField.length) {
        const $counter = $('<div id="bio-counter" class="char-counter">0/500</div>');
        $bioField.after($counter);
        
        $bioField.on('input', function() {
            const length = $(this).val().length;
            const isOverLimit = length > 500;
            
            $counter.text(`${length}/500`)
                   .toggleClass('over-limit', isOverLimit)
                   .css('color', isOverLimit ? '#dc3232' : '#666');
        });
    }
    
    // Dynamic schedule visibility with smooth animations
    function toggleScheduleTimes() {
        const hasSelectedDays = $dayCheckboxes.filter(':checked').length > 0;
        $timesContainer.toggle(hasSelectedDays);
    }
    
    $dayCheckboxes.on('change', toggleScheduleTimes);
    
    // Initialize on page load
    toggleScheduleTimes();
});
JS;
    }
    
    /**
     * Get modern checkout styles
     */
    private function get_checkout_styles(): string {
        return <<<'CSS'
.tutor {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    padding: 30px;
    margin: 30px 0;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.tutor:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}

.tutor h3 {
    margin-top: 0;
    color: #2c3e50;
    border-bottom: 3px solid #3498db;
    padding-bottom: 12px;
    font-size: 1.4em;
    font-weight: 600;
}

.row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.row .form-row {
    flex: 1;
    min-width: 250px;
}

#tutor_service, #tutor_hourly_rate, #tutor_bio {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s ease;
}

#tutor_service:focus, #tutor_hourly_rate:focus, #tutor_bio:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    outline: none;
}

#tutor_bio {
    min-height: 100px;
    resize: vertical;
    font-family: inherit;
}

.char-counter {
    font-size: 12px;
    color: #666;
    margin-top: 8px;
    text-align: right;
    transition: color 0.3s ease;
}

.char-counter.over-limit {
    color: #dc3232;
    font-weight: 600;
}

.sched {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 2px solid #ecf0f1;
}

.sched h4 {
    color: #2c3e50;
    margin-bottom: 10px;
    font-size: 1.2em;
}

.days {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 12px;
    margin: 15px 0;
}

.day-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    background: #ffffff;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.day-item:hover {
    border-color: #3498db;
    background: #f8f9ff;
}

.day-item input[type="checkbox"]:checked + label {
    font-weight: 600;
    color: #3498db;
}

.times {
    margin-top: 20px;
    padding: 20px;
    background: #ffffff;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
}

.time-row {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.time-row label {
    font-weight: 600;
    color: #2c3e50;
    margin-right: 10px;
}

.time-row input[type="time"] {
    padding: 10px 12px;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 16px;
    min-width: 140px;
    transition: all 0.3s ease;
}

.time-row input[type="time"]:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    
    .days {
        grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
    }
    
    .time-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .time-row input[type="time"] {
        min-width: auto;
    }
}
CSS;
    }
    
    /**
     * Render tutor fields on checkout
     */
    public function render_tutor_fields( $checkout ): void {
        $services = $this->get_services();
        $service_options = $this->build_service_options( $services );
        $rate_options = $this->build_rate_options();
        
        echo '<div class="tutor">';
        echo '<h3>' . esc_html__( 'Tutor Information', 'darsna' ) . '</h3>';
        
        // Service and Rate row
        echo '<div class="row">';
        $this->render_service_field( $checkout, $service_options );
        $this->render_rate_field( $checkout, $rate_options );
        echo '</div>';
        
        // Bio field
        $this->render_bio_field( $checkout );
        
        // Schedule section
        $this->render_schedule_section( $checkout );
        
        echo '</div>';
    }
    
    /**
     * Build service options array
     */
    private function build_service_options( array $services ): array {
        if ( empty( $services ) ) {
            return [ '' => __( 'No subjects available', 'darsna' ) ];
        }
        
        $options = [ '' => __( 'Select subject...', 'darsna' ) ];
        foreach ( $services as $service ) {
            $options[ $service->id ] = esc_html( $service->name );
        }
        
        return $options;
    }
    
    /**
     * Build rate options array
     */
    private function build_rate_options(): array {
        $options = [ '' => __( 'Select rate...', 'darsna' ) ];
        $currency_symbol = get_woocommerce_currency_symbol();
        
        for ( $rate = self::MIN_RATE; $rate <= self::MAX_RATE; $rate += self::RATE_STEP ) {
            $options[ $rate ] = sprintf( '%s%d/hr', $currency_symbol, $rate );
        }
        
        return $options;
    }
    
    /**
     * Render service field
     */
    private function render_service_field( $checkout, array $options ): void {
        woocommerce_form_field( 'tutor_service', [
            'type' => 'select',
            'class' => [ 'form-row-first' ],
            'label' => __( 'Subject', 'darsna' ),
            'required' => true,
            'options' => $options
        ], $checkout->get_value( 'tutor_service' ) );
    }
    
    /**
     * Render rate field
     */
    private function render_rate_field( $checkout, array $options ): void {
        woocommerce_form_field( 'tutor_hourly_rate', [
            'type' => 'select',
            'class' => [ 'form-row-last' ],
            'label' => __( 'Hourly Rate', 'darsna' ),
            'required' => true,
            'options' => $options
        ], $checkout->get_value( 'tutor_hourly_rate' ) );
    }
    
    /**
     * Render bio field
     */
    private function render_bio_field( $checkout ): void {
        woocommerce_form_field( 'tutor_bio', [
            'type' => 'textarea',
            'class' => [ 'form-row-wide' ],
            'label' => __( 'Teaching Background & Bio', 'darsna' ),
            'placeholder' => __( 'Tell students about your teaching experience, qualifications, and specialties...', 'darsna' ),
            'custom_attributes' => [
                'maxlength' => '500',
                'rows' => '5'
            ]
        ], $checkout->get_value( 'tutor_bio' ) );
    }
    
    /**
     * Render schedule section
     */
    private function render_schedule_section( $checkout ): void {
        echo '<div class="sched">';
        echo '<h4>' . esc_html__( 'Weekly Availability', 'darsna' ) . '</h4>';
        echo '<p style="font-size:14px;color:#666;margin-bottom:15px;">' . 
             esc_html__( 'Select the days you want to teach and set your preferred hours', 'darsna' ) . '</p>';
        
        $this->render_day_checkboxes( $checkout );
        $this->render_time_inputs( $checkout );
        
        echo '</div>';
    }
    
    /**
     * Render day checkboxes
     */
    private function render_day_checkboxes( $checkout ): void {
        $days = [
            'mon' => __( 'Mon', 'darsna' ),
            'tue' => __( 'Tue', 'darsna' ),
            'wed' => __( 'Wed', 'darsna' ),
            'thu' => __( 'Thu', 'darsna' ),
            'fri' => __( 'Fri', 'darsna' ),
            'sat' => __( 'Sat', 'darsna' ),
            'sun' => __( 'Sun', 'darsna' )
        ];
        
        echo '<div class="days">';
        
        foreach ( $days as $key => $label ) {
            $checked_value = $checkout->get_value( "schedule_{$key}" );
            $is_checked = $checked_value !== null ? $checked_value : in_array( $key, self::DEFAULT_SCHEDULE_DAYS );
            $checked = $is_checked ? 'checked' : '';
            
            printf(
                '<div class="day-item">
                    <input type="checkbox" id="schedule_%1$s" name="schedule_%1$s" value="1" class="day" %3$s>
                    <label for="schedule_%1$s">%2$s</label>
                </div>',
                esc_attr( $key ),
                esc_html( $label ),
                $checked
            );
        }
        
        echo '</div>';
    }
    
    /**
     * Render time inputs
     */
    private function render_time_inputs( $checkout ): void {
        $start_time = $checkout->get_value( 'schedule_start' ) ?: self::DEFAULT_WORK_HOURS['start'];
        $end_time = $checkout->get_value( 'schedule_end' ) ?: self::DEFAULT_WORK_HOURS['end'];
        
        printf(
            '<div class="times">
                <div class="time-row">
                    <label>%s</label>
                    <input type="time" name="schedule_start" value="%s" required>
                    <label>%s</label>
                    <input type="time" name="schedule_end" value="%s" required>
                </div>
            </div>',
            esc_html__( 'Available from:', 'darsna' ),
            esc_attr( $start_time ),
            esc_html__( 'Available until:', 'darsna' ),
            esc_attr( $end_time )
        );
    }
    
    /**
     * Validate checkout form
     */
    public function validate_checkout(): void {
        $errors = [];
        
        // Validate service selection
        if ( empty( $_POST['tutor_service'] ) ) {
            $errors[] = __( 'Please select a subject to teach', 'darsna' );
        }
        
        // Validate hourly rate
        $rate = (int) ( $_POST['tutor_hourly_rate'] ?? 0 );
        if ( empty( $_POST['tutor_hourly_rate'] ) ) {
            $errors[] = __( 'Please select your hourly rate', 'darsna' );
        } elseif ( $rate < self::MIN_RATE || $rate > self::MAX_RATE || $rate % self::RATE_STEP !== 0 ) {
            $errors[] = sprintf( 
                __( 'Invalid hourly rate. Must be between %d and %d in increments of %d', 'darsna' ),
                self::MIN_RATE,
                self::MAX_RATE,
                self::RATE_STEP
            );
        }
        
        // Validate bio length
        $bio = $_POST['tutor_bio'] ?? '';
        if ( ! empty( $bio ) && strlen( $bio ) > 500 ) {
            $errors[] = __( 'Bio must be 500 characters or less', 'darsna' );
        }
        
        // Validate schedule times
        $start_time = $_POST['schedule_start'] ?? '';
        $end_time = $_POST['schedule_end'] ?? '';
        
        if ( ! empty( $start_time ) && ! empty( $end_time ) && $start_time >= $end_time ) {
            $errors[] = __( 'End time must be after start time', 'darsna' );
        }
        
        // Add all errors to WooCommerce
        foreach ( $errors as $error ) {
            wc_add_notice( $error, 'error' );
        }
    }
    
    /**
     * Save order meta data
     */
    public function save_order_meta( int $order_id ): void {
        $meta_data = [
            '_tutor_service_id' => sanitize_text_field( $_POST['tutor_service'] ?? '' ),
            '_tutor_hourly_rate' => (int) ( $_POST['tutor_hourly_rate'] ?? 0 ),
            '_tutor_bio' => sanitize_textarea_field( $_POST['tutor_bio'] ?? '' )
        ];
        
        // Save basic meta
        foreach ( $meta_data as $key => $value ) {
            update_post_meta( $order_id, $key, $value );
        }
        
        // Save schedule data
        $schedule = $this->process_schedule_data();
        update_post_meta( $order_id, '_tutor_schedule', $schedule );
    }
    
    /**
     * Process and validate schedule data
     */
    private function process_schedule_data(): array {
        $schedule = [];
        $has_selected_days = false;
        
        // Process day selections
        $days = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
        foreach ( $days as $day ) {
            if ( ! empty( $_POST["schedule_{$day}"] ) ) {
                $schedule[ $day ] = 1;
                $has_selected_days = true;
            }
        }
        
        // Use default days if none selected
        if ( ! $has_selected_days ) {
            foreach ( self::DEFAULT_SCHEDULE_DAYS as $day ) {
                $schedule[ $day ] = 1;
            }
        }
        
        // Add time information
        $schedule['start'] = sanitize_text_field( $_POST['schedule_start'] ?? self::DEFAULT_WORK_HOURS['start'] );
        $schedule['end'] = sanitize_text_field( $_POST['schedule_end'] ?? self::DEFAULT_WORK_HOURS['end'] );
        
        return $schedule;
    }
    
    /**
     * Handle order completion
     */
    public function handle_order_completion( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return;
        }
        
        foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
            $user_id = $subscription->get_user_id();
            $subscription_id = $subscription->get_id();
            
            if ( ! $user_id ) {
                continue;
            }
            
            // Schedule agent activation using Action Scheduler (preferred) or wp_cron
            $this->schedule_agent_activation( $user_id, $subscription_id );
        }
    }
    
    /**
     * Schedule agent activation
     */
    private function schedule_agent_activation( int $user_id, int $subscription_id ): void {
        $delay = 5; // seconds
        
        if ( function_exists( 'as_schedule_single_action' ) ) {
            // Use Action Scheduler if available (WooCommerce default)
            as_schedule_single_action( time() + $delay, 'darsna_activate_agent', [ $user_id, $subscription_id ] );
        } else {
            // Fallback to WordPress cron
            wp_schedule_single_event( time() + $delay, 'darsna_activate_agent', [ $user_id, $subscription_id ] );
        }
    }
    
    /**
     * Handle subscription status changes
     */
    public function handle_subscription_status_change( $subscription, string $new_status, string $old_status ): void {
        if ( $new_status === $old_status ) {
            return;
        }
        
        $user_id = $subscription->get_user_id();
        if ( ! $user_id ) {
            return;
        }
        
        if ( in_array( $new_status, self::ACTIVE_STATUSES, true ) ) {
            $this->handle_subscription_activation( $subscription, $user_id );
        } elseif ( in_array( $new_status, self::INACTIVE_STATUSES, true ) ) {
            $this->deactivate_tutor_agent( $user_id );
        }
    }
    
    /**
     * Handle subscription activation
     */
    private function handle_subscription_activation( $subscription, int $user_id ): void {
        $parent_order = $subscription->get_parent_id() ? wc_get_order( $subscription->get_parent_id() ) : null;
        
        if ( $parent_order && $parent_order->get_status() === 'completed' ) {
            $this->schedule_agent_activation( $user_id, $subscription->get_id() );
        } else {
            $subscription->update_status( 'on-hold', __( 'Pending payment verification', 'darsna' ) );
        }
    }
    
    /**
     * Activate tutor agent - Main activation method
     */
    public function activate_tutor_agent( int $user_id, int $subscription_id ): void {
        // Verify user exists first
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            error_log( "Darsna: User not found for ID: {$user_id}" );
            return;
        }
        
        $tutor_data = $this->get_tutor_data( $user_id );
        if ( ! $tutor_data ) {
            error_log( "Darsna: No tutor data found for user ID: {$user_id}" );
            return;
        }
        
        try {
            // Update user meta
            $this->update_user_meta( $user_id, $subscription_id, $tutor_data );
            
            // Update user role
            wp_update_user( [ 'ID' => $user_id, 'role' => 'latepoint_agent' ] );
            
            // Sync with LatePoint
            if ( $this->sync_agent_status( $user_id, 'active' ) ) {
                $this->assign_service_to_agent( $user_id, $tutor_data['service_id'] );
                
                if ( ! empty( $tutor_data['schedule'] ) ) {
                    $this->set_agent_schedule( $user_id, $tutor_data['schedule'] );
                }
                
                do_action( 'darsna_tutor_activated', $user_id, $subscription_id, $tutor_data );
                error_log( "Darsna: Successfully activated tutor agent for user ID: {$user_id}" );
            } else {
                error_log( "Darsna: Failed to sync agent status for user ID: {$user_id}" );
            }
            
        } catch ( Exception $e ) {
            error_log( "Darsna: Error activating tutor agent for user {$user_id}: " . $e->getMessage() );
        }
    }
    
    /**
     * Update user meta with tutor information
     */
    private function update_user_meta( int $user_id, int $subscription_id, array $tutor_data ): void {
        $meta_updates = [
            '_darsna_account_type' => 'tutor',
            '_darsna_subscription_active' => 'yes',
            '_darsna_subscription_id' => $subscription_id,
            '_darsna_tutor_service_id' => $tutor_data['service_id'],
            '_darsna_tutor_hourly_rate' => $tutor_data['hourly_rate'],
            '_darsna_tutor_bio' => $tutor_data['bio']
        ];
        
        foreach ( $meta_updates as $key => $value ) {
            update_user_meta( $user_id, $key, $value );
        }
    }
    
    /**
     * Deactivate tutor agent
     */
    private function deactivate_tutor_agent( int $user_id ): void {
        update_user_meta( $user_id, '_darsna_subscription_active', 'no' );
        wp_update_user( [ 'ID' => $user_id, 'role' => 'customer' ] );
        $this->sync_agent_status( $user_id, 'disabled' );
        
        do_action( 'darsna_tutor_deactivated', $user_id );
    }
    
    /**
     * Sync agent status with LatePoint - Optimized for v5
     */
    private function sync_agent_status( int $user_id, string $status = 'active' ): bool {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return false;
        }
        
        try {
            $existing_agent = $this->get_existing_agent( $user_id );
            
            if ( $existing_agent ) {
                return $this->update_existing_agent( $existing_agent->id, $status );
            } elseif ( $status === 'active' ) {
                return $this->create_new_agent( $user );
            }
            
            return true;
            
        } catch ( Exception $e ) {
            error_log( "Darsna: Error syncing agent status: " . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Get existing agent by user ID
     */
    private function get_existing_agent( int $user_id ) {
        global $wpdb;
        
        try {
            $agent = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}latepoint_agents WHERE wp_user_id = %d",
                $user_id
            ) );
            
            if ( ! $agent ) {
                error_log( "Darsna: No agent found for user ID: {$user_id}" );
            }
            
            return $agent;
        } catch ( Exception $e ) {
            error_log( "Darsna: Database error getting agent for user {$user_id}: " . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Update existing agent status
     */
    private function update_existing_agent( int $agent_id, string $status ): bool {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'latepoint_agents',
            [ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $agent_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        ) !== false;
    }
    
    /**
     * Create new agent - Optimized with better error handling
     */
    private function create_new_agent( WP_User $user ): bool {
        $order = $this->get_user_order( $user->ID );
        $bio = get_user_meta( $user->ID, '_darsna_tutor_bio', true );
        
        // Get user names with fallbacks
        $names = $this->extract_user_names( $user, $order );
        
        $agent_data = [
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $order ? $this->format_phone( $order->get_billing_phone() ) : '',
            'wp_user_id' => $user->ID,
            'status' => 'active',
            'bio' => $bio ? sanitize_textarea_field( $bio ) : '',
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        ];
        
        // Try LatePoint v5 Repository first, then fallback
        return $this->create_agent_with_fallback( $agent_data );
    }
    
    /**
     * Extract user names with proper fallbacks
     */
    private function extract_user_names( WP_User $user, $order ): array {
        $first_name = $user->first_name ?: ( $order ? $order->get_billing_first_name() : '' );
        $last_name = $user->last_name ?: ( $order ? $order->get_billing_last_name() : '' );
        
        // Fallback to display name parsing
        if ( ! $first_name || ! $last_name ) {
            $name_parts = explode( ' ', $user->display_name, 2 );
            $first_name = $first_name ?: ( $name_parts[0] ?? 'User' );
            $last_name = $last_name ?: ( $name_parts[1] ?? '' );
        }
        
        return [
            'first' => sanitize_text_field( $first_name ),
            'last' => sanitize_text_field( $last_name )
        ];
    }
    
    /**
     * Create agent with v5 Repository fallback
     */
    private function create_agent_with_fallback( array $agent_data ): bool {
        // Try LatePoint v5 Repository API first
        if ( class_exists( '\LatePoint\App\Repositories\AgentRepository' ) ) {
            try {
                $agent = \LatePoint\App\Repositories\AgentRepository::create( $agent_data );
                return $agent && isset( $agent->id );
            } catch ( Exception $e ) {
                error_log( "Darsna: LatePoint v5 Repository failed: " . $e->getMessage() );
            }
        }
        
        // Fallback to direct database insert
        return $this->insert_agent_directly( $agent_data );
    }
    
    /**
     * Insert agent directly into database
     */
    private function insert_agent_directly( array $agent_data ): bool {
        global $wpdb;
        
        // Check for existing agent with same email
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}latepoint_agents WHERE email = %s",
            $agent_data['email']
        ) );
        
        if ( $existing ) {
            // Update existing agent
            return $wpdb->update(
                $wpdb->prefix . 'latepoint_agents',
                $agent_data,
                [ 'id' => $existing ],
                null,
                [ '%d' ]
            ) !== false;
        }
        
        // Insert new agent
        return $wpdb->insert(
            $wpdb->prefix . 'latepoint_agents',
            $agent_data
        ) !== false;
    }
    
    /**
     * Assign service to agent - FIXED table name
     */
    private function assign_service_to_agent( int $user_id, $service_id ): bool {
        if ( ! $service_id ) {
            return false;
        }
        
        $agent = $this->get_existing_agent( $user_id );
        if ( ! $agent ) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'latepoint_agents_services'; // FIXED: was latepoint_agent_services
        
        // Check if assignment already exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE agent_id = %d AND service_id = %d",
            $agent->id,
            $service_id
        ) );
        
        if ( $exists ) {
            return true; // Already assigned
        }
        
        // Try v5 Repository first
        if ( class_exists( '\LatePoint\App\Repositories\AgentServiceRepository' ) ) {
            try {
                $assignment = \LatePoint\App\Repositories\AgentServiceRepository::create( [
                    'agent_id' => (int) $agent->id,
                    'service_id' => (int) $service_id,
                    'location_id' => 1, // Default location
                    'is_custom_hours' => 0,
                    'is_custom_price' => 0,
                    'is_custom_duration' => 0,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' )
                ] );
                
                return $assignment && isset( $assignment->id );
            } catch ( Exception $e ) {
                error_log( "Darsna: AgentService Repository failed: " . $e->getMessage() );
            }
        }
        
        // Fallback to direct database insert
        return $wpdb->insert( $table, [
            'agent_id' => (int) $agent->id,
            'service_id' => (int) $service_id,
            'location_id' => 1,
            'is_custom_hours' => 0,
            'is_custom_price' => 0,
            'is_custom_duration' => 0,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' )
        ], [ '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ] ) !== false;
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
            if ( ! empty( $schedule[ $day ] ) ) {
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
     * Convert time string to minutes
     */
    private function time_to_minutes( string $time ): int {
        $parts = explode( ':', $time );
        return ( (int) $parts[0] * 60 ) + (int) ( $parts[1] ?? 0 );
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
     * Get services from LatePoint
     */
    private function get_services(): array {
        if ( ! isset( self::$cache['services'] ) ) {
            try {
                // Try v5 Repository first
                if ( class_exists( '\LatePoint\App\Repositories\ServiceRepository' ) ) {
                    self::$cache['services'] = \LatePoint\App\Repositories\ServiceRepository::where( [ 'status' => 'active' ] );
                } else {
                    // Fallback to direct database query
                    global $wpdb;
                    self::$cache['services'] = $wpdb->get_results(
                        "SELECT * FROM {$wpdb->prefix}latepoint_services WHERE status = 'active' ORDER BY name"
                    ) ?: [];
                }
            } catch ( Exception $e ) {
                error_log( "Darsna: Error fetching services: " . $e->getMessage() );
                self::$cache['services'] = [];
            }
        }
        
        return self::$cache['services'];
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
     * Apply dynamic pricing based on tutor rates
     */
    public function apply_dynamic_pricing( $amount_for_service, $booking, $apply_coupons ) {
        if ( empty( $booking->agent_id ) ) {
            return $amount_for_service;
        }
        
        $cache_key = "rate_{$booking->agent_id}";
        if ( isset( self::$cache[ $cache_key ] ) ) {
            return self::$cache[ $cache_key ] ?: $amount_for_service;
        }
        
        try {
            $agent = $this->get_existing_agent_by_id( $booking->agent_id );
            if ( ! $agent || ! isset( $agent->wp_user_id ) || ! $agent->wp_user_id ) {
                error_log( "Darsna: No valid agent or wp_user_id for agent ID: {$booking->agent_id}" );
                self::$cache[ $cache_key ] = false;
                return $amount_for_service;
            }
            
            $rate = (int) get_user_meta( $agent->wp_user_id, '_darsna_tutor_hourly_rate', true );
            self::$cache[ $cache_key ] = $rate ?: false;
            
            return $rate ?: $amount_for_service;
            
        } catch ( Exception $e ) {
            error_log( "Darsna: Error applying dynamic pricing: " . $e->getMessage() );
            return $amount_for_service;
        }
    }
    
    /**
     * Get existing agent by agent ID
     */
    private function get_existing_agent_by_id( int $agent_id ) {
        global $wpdb;
        
        try {
            $agent = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}latepoint_agents WHERE id = %d",
                $agent_id
            ) );
            
            if ( ! $agent ) {
                error_log( "Darsna: No agent found for agent ID: {$agent_id}" );
            }
            
            return $agent;
        } catch ( Exception $e ) {
            error_log( "Darsna: Database error getting agent by ID {$agent_id}: " . $e->getMessage() );
            return null;
        }
    }
    
    /**
     * Customize navigation menu for logged-in users
     */
    public function customize_menu( string $items, $args ): string {
        if ( ! is_user_logged_in() || $args->theme_location !== 'primary-menu' ) {
            return $items;
        }
        
        $user = wp_get_current_user();
        $dashboard_url = in_array( 'latepoint_agent', $user->roles ) 
            ? admin_url( 'admin.php?page=latepoint' )
            : wc_get_page_permalink( 'myaccount' );
        
        $menu_items = sprintf(
            '<li class="menu-item"><a href="%s">%s</a></li>
             <li class="menu-item"><a href="%s">%s</a></li>',
            esc_url( $dashboard_url ),
            esc_html__( 'Dashboard', 'darsna' ),
            esc_url( wp_logout_url( home_url() ) ),
            esc_html__( 'Logout', 'darsna' )
        );
        
        return $items . $menu_items;
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    Darsna_Tutor_Checkout::instance();
}, 20 ); // Load after other plugins
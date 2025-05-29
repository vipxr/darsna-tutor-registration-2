<?php
/**
 * Frontend functionality for tutor registration
 * 
 * @package Darsna_Tutor_Registration
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend class - Handles checkout forms and validation
 */
class Darsna_Tutor_Frontend {
    
    private static $instance;
    private static $cache = [];
    
    // Rate configuration
    private const MIN_RATE = 5;
    private const MAX_RATE = 50;
    private const RATE_STEP = 5;
    
    private const DEFAULT_SCHEDULE_DAYS = ['mon', 'tue', 'wed', 'thu', 'sun'];
    private const DEFAULT_WORK_HOURS = ['start' => '09:00', 'end' => '17:00'];
    
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
     * Setup frontend hooks
     */
    private function setup_hooks(): void {
        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_after_checkout_registration_form', [ $this, 'render_tutor_fields' ] );
        
        // Checkout processing hooks
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_checkout' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_order_meta' ] );
        
        // LatePoint pricing hook
        add_filter( 'latepoint_full_amount_for_service', [ $this, 'apply_dynamic_pricing' ], 10, 3 );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets(): void {
        if ( ! is_checkout() ) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'darsna-tutor-frontend',
            DARSNA_TUTOR_URL . 'assets/css/frontend.css',
            [],
            DARSNA_TUTOR_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'darsna-tutor-frontend',
            DARSNA_TUTOR_URL . 'assets/js/frontend.js',
            ['jquery'],
            DARSNA_TUTOR_VERSION,
            true
        );
    }
    
    /**
     * Render tutor fields on checkout
     */
    public function render_tutor_fields( $checkout ): void {
        echo '<div id="tutor-registration-fields">';
        echo '<h3>' . __( 'Tutor Registration', 'darsna-tutor' ) . '</h3>';
        
        // Service selection
        $this->render_service_field();
        
        // Rate selection
        $this->render_rate_field();
        
        // Bio field
        $this->render_bio_field();
        
        // Schedule section
        $this->render_schedule_section();
        
        echo '</div>';
    }
    
    /**
     * Render service selection field
     */
    private function render_service_field(): void {
        $services = $this->get_services();
        
        if ( empty( $services ) ) {
            echo '<p class="error">' . __( 'No services available. Please contact support.', 'darsna-tutor' ) . '</p>';
            return;
        }
        
        echo '<p class="form-row form-row-wide">';
        echo '<label for="tutor_service">' . __( 'Teaching Subject', 'darsna-tutor' ) . ' <span class="required">*</span></label>';
        echo '<select name="tutor_service" id="tutor_service" class="select" required>';
        echo '<option value="">' . __( 'Select a subject...', 'darsna-tutor' ) . '</option>';
        
        foreach ( $services as $service ) {
            $selected = selected( $_POST['tutor_service'] ?? '', $service->id, false );
            echo "<option value='{$service->id}'{$selected}>{$service->name}</option>";
        }
        
        echo '</select></p>';
    }
    
    /**
     * Render rate selection field
     */
    private function render_rate_field(): void {
        echo '<p class="form-row form-row-wide">';
        echo '<label for="tutor_rate">' . __( 'Hourly Rate  (USD)', 'darsna-tutor' ) . ' <span class="required">*</span></label>';
        echo '<select name="tutor_rate" id="tutor_rate" class="select" required>';
        echo '<option value="">' . __( 'Select your rate...', 'darsna-tutor' ) . '</option>';
        
        for ( $rate = self::MIN_RATE; $rate <= self::MAX_RATE; $rate += self::RATE_STEP ) {
            $selected = selected( $_POST['tutor_rate'] ?? '', $rate, false );
            echo "<option value='{$rate}'{$selected}>{$rate}</option>";
        }
        
        echo '</select></p>';
    }
    
    /**
     * Render bio field
     */
    private function render_bio_field(): void {
        $bio = sanitize_textarea_field( $_POST['tutor_bio'] ?? '' );
        
        echo '<p class="form-row form-row-wide">';
        echo '<label for="tutor_bio">' . __( 'Bio/Experience', 'darsna-tutor' ) . '</label>';
        echo '<textarea name="tutor_bio" id="tutor_bio" class="input-text" rows="4" placeholder="' . __( 'Tell us about your teaching experience...', 'darsna-tutor' ) . '">' . esc_textarea( $bio ) . '</textarea>';
        echo '</p>';
    }
    
    /**
     * Render schedule section
     */
    private function render_schedule_section(): void {
        echo '<div class="tutor-schedule-section">';
        echo '<h4>' . __( 'Availability Schedule', 'darsna-tutor' ) . '</h4>';
        
        // Days selection
        echo '<div class="schedule-days">';
        echo '<label>' . __( 'Available Days:', 'darsna-tutor' ) . '</label>';
        
        $days = [
            'mon' => __( 'Mon', 'darsna-tutor' ),
            'tue' => __( 'Tue', 'darsna-tutor' ),
            'wed' => __( 'Wed', 'darsna-tutor' ),
            'thu' => __( 'Thu', 'darsna-tutor' ),
            'fri' => __( 'Fri', 'darsna-tutor' ),
            'sat' => __( 'Sat', 'darsna-tutor' ),
            'sun' => __( 'Sun', 'darsna-tutor' )
        ];
        
        echo '<div class="days-container">';
        foreach ( $days as $key => $label ) {
            $checked = in_array( $key, $_POST['schedule_days'] ?? self::DEFAULT_SCHEDULE_DAYS ) ? 'checked' : '';
            echo "<label class='day-checkbox'><input type='checkbox' name='schedule_days[]' value='{$key}' {$checked}><span>{$label}</span></label>";
        }
        echo '</div>';
        
        echo '</div>';
        
        // Time selection
        echo '<div class="schedule-times">';
        echo '<div class="time-group">';
        echo '<label for="schedule_start">' . __( 'Start Time:', 'darsna-tutor' ) . '</label>';
        echo '<input type="time" name="schedule_start" id="schedule_start" value="' . esc_attr( $_POST['schedule_start'] ?? self::DEFAULT_WORK_HOURS['start'] ) . '">';
        echo '</div>';
        
        echo '<div class="time-group">';
        echo '<label for="schedule_end">' . __( 'End Time:', 'darsna-tutor' ) . '</label>';
        echo '<input type="time" name="schedule_end" id="schedule_end" value="' . esc_attr( $_POST['schedule_end'] ?? self::DEFAULT_WORK_HOURS['end'] ) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Validate checkout form
     */
    public function validate_checkout(): void {
        $errors = [];
        
        // Validate service
        if ( empty( $_POST['tutor_service'] ) ) {
            $errors[] = __( 'Please select a teaching subject.', 'darsna-tutor' );
        }
        
        // Validate rate
        $rate = (int) ( $_POST['tutor_rate'] ?? 0 );
        if ( $rate < self::MIN_RATE || $rate > self::MAX_RATE ) {
            $errors[] = sprintf( __( 'Please select a valid hourly rate between %d and %d.', 'darsna-tutor' ), self::MIN_RATE, self::MAX_RATE );
        }
        
        // Validate schedule
        if ( empty( $_POST['schedule_days'] ) ) {
            $errors[] = __( 'Please select at least one available day.', 'darsna-tutor' );
        }
        
        // Validate time range
        $start_time = $_POST['schedule_start'] ?? '';
        $end_time = $_POST['schedule_end'] ?? '';
        
        if ( empty( $start_time ) || empty( $end_time ) ) {
            $errors[] = __( 'Please set your availability hours.', 'darsna-tutor' );
        } elseif ( strtotime( $start_time ) >= strtotime( $end_time ) ) {
            $errors[] = __( 'End time must be after start time.', 'darsna-tutor' );
        }
        
        // Display errors
        foreach ( $errors as $error ) {
            wc_add_notice( $error, 'error' );
        }
    }
    
    /**
     * Save order meta data
     */
    public function save_order_meta( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        // Save tutor data
        $order->update_meta_data( '_tutor_service_id', sanitize_text_field( $_POST['tutor_service'] ?? '' ) );
        $order->update_meta_data( '_tutor_hourly_rate', (int) ( $_POST['tutor_rate'] ?? 0 ) );
        $order->update_meta_data( '_tutor_bio', sanitize_textarea_field( $_POST['tutor_bio'] ?? '' ) );
        
        // Save schedule data
        $schedule = [
            'days' => array_map( 'sanitize_text_field', $_POST['schedule_days'] ?? [] ),
            'start' => sanitize_text_field( $_POST['schedule_start'] ?? '' ),
            'end' => sanitize_text_field( $_POST['schedule_end'] ?? '' )
        ];
        
        $order->update_meta_data( '_tutor_schedule', $schedule );
        $order->save();
    }
    
    /**
     * Apply dynamic pricing based on tutor hourly rate
     * Filter: latepoint_full_amount_for_service
     * @param string $amount The original amount
     * @param OsBookingModel $booking The booking object
     * @param mixed $additional_param Additional parameter (if any)
     * @return string Modified amount
     */
    public function apply_dynamic_pricing( $amount, $booking, $additional_param = null ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return $amount;
        }
        
        // Check if we have a booking object and it has an agent_id
        if ( ! $booking || ! isset( $booking->agent_id ) || ! isset( $booking->service_id ) ) {
            return $amount;
        }
        
        try {
            global $wpdb;
            
            // Get custom price from custom_prices table
            $custom_price = $wpdb->get_var( $wpdb->prepare(
                "SELECT charge_amount FROM {$wpdb->prefix}latepoint_custom_prices 
                 WHERE agent_id = %d AND service_id = %d AND location_id = 1",
                $booking->agent_id,
                $booking->service_id
            ));
            
            if ( $custom_price !== null && $custom_price > 0 ) {
                error_log( "Darsna: Applied custom pricing {$custom_price} for agent {$booking->agent_id}, service {$booking->service_id}" );
                return $custom_price;
            }
            
        } catch ( Exception $e ) {
            error_log( "Darsna: Error applying dynamic pricing: " . $e->getMessage() );
        }
        
        return $amount;
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
}
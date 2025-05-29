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
        
        // Service selection with rates
        $this->render_service_field();
        
        // Bio field
        $this->render_bio_field();
        
        // Schedule section
        $this->render_schedule_section();
        
        echo '</div>';
    }
    
    /**
     * Render multiple services selection with custom pricing
     */
    private function render_service_field(): void {
        $services = $this->get_services();
        
        if ( empty( $services ) ) {
            echo '<p class="error">' . __( 'No services available. Please contact support.', 'darsna-tutor' ) . '</p>';
            return;
        }
        
        echo '<div class="tutor-services-section">';
        echo '<h4>' . __( 'Teaching Subjects & Rates', 'darsna-tutor' ) . ' <span class="required">*</span></h4>';
        echo '<p class="description">' . __( 'Select the subjects you can teach and set your hourly rate for each.', 'darsna-tutor' ) . '</p>';
        
        echo '<div id="tutor-services-container">';
        
        // Get existing services from POST data
        $existing_services = $_POST['tutor_services'] ?? [];
        
        if ( empty( $existing_services ) ) {
            // Add one empty service row by default
            $this->render_service_row( $services, 0 );
        } else {
            // Render existing services
            foreach ( $existing_services as $index => $service_data ) {
                $this->render_service_row( $services, $index, $service_data );
            }
        }
        
        echo '</div>';
        
        echo '<button type="button" id="add-service-btn" class="button">' . __( 'Add Another Subject', 'darsna-tutor' ) . '</button>';
        echo '</div>';
    }
    
    /**
     * Render a single service row
     */
    private function render_service_row( $services, $index, $service_data = [] ): void {
        $service_id = $service_data['service_id'] ?? '';
        $rate = $service_data['rate'] ?? '';
        
        echo '<div class="service-row" data-index="' . $index . '">';
        
        // Service selection
        echo '<div class="service-select">';
        echo '<label>' . __( 'Subject:', 'darsna-tutor' ) . '</label>';
        echo '<select name="tutor_services[' . $index . '][service_id]" class="service-dropdown" required>';
        echo '<option value="">' . __( 'Select a subject...', 'darsna-tutor' ) . '</option>';
        
        foreach ( $services as $service ) {
            $selected = selected( $service_id, $service->id, false );
            echo "<option value='{$service->id}' data-default-rate='{$service->charge_amount}'{$selected}>{$service->name}</option>";
        }
        
        echo '</select>';
        echo '</div>';
        
        // Rate input
        echo '<div class="service-rate">';
        echo '<label>' . __( 'Your Rate (USD):', 'darsna-tutor' ) . '</label>';
        echo '<input type="number" name="tutor_services[' . $index . '][rate]" class="rate-input" value="' . esc_attr( $rate ) . '" step="0.01" min="' . self::MIN_RATE . '" max="' . self::MAX_RATE . '" placeholder="' . self::MIN_RATE . '.00" required>';
        echo '</div>';
        
        // Remove button (only show if not the first row)
        if ( $index > 0 ) {
            echo '<button type="button" class="remove-service-btn button-link-delete">' . __( 'Remove', 'darsna-tutor' ) . '</button>';
        }
        
        echo '</div>';
    }
    
    /**
     * Legacy method - kept for backward compatibility
     */
    private function render_rate_field(): void {
        // This method is now handled within render_service_field()
        // Kept for backward compatibility
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
        
        // Validate services
        $services = $_POST['tutor_services'] ?? [];
        
        if ( empty( $services ) ) {
            $errors[] = __( 'Please select at least one teaching subject.', 'darsna-tutor' );
        } else {
            $selected_services = [];
            
            foreach ( $services as $index => $service_data ) {
                $service_id = $service_data['service_id'] ?? '';
                $rate = floatval( $service_data['rate'] ?? 0 );
                
                // Validate service selection
                if ( empty( $service_id ) ) {
                    $errors[] = sprintf( __( 'Please select a subject for row %d.', 'darsna-tutor' ), $index + 1 );
                    continue;
                }
                
                // Check for duplicate services
                if ( in_array( $service_id, $selected_services ) ) {
                    $errors[] = __( 'You cannot select the same subject multiple times.', 'darsna-tutor' );
                    continue;
                }
                
                $selected_services[] = $service_id;
                
                // Validate rate
                if ( $rate < self::MIN_RATE || $rate > self::MAX_RATE ) {
                    $errors[] = sprintf( __( 'Please enter a valid hourly rate between $%d and $%d for row %d.', 'darsna-tutor' ), self::MIN_RATE, self::MAX_RATE, $index + 1 );
                }
            }
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
        
        // Save tutor services data
        $services_data = [];
        $tutor_services = $_POST['tutor_services'] ?? [];
        
        foreach ( $tutor_services as $service_data ) {
            $service_id = sanitize_text_field( $service_data['service_id'] ?? '' );
            $rate = floatval( $service_data['rate'] ?? 0 );
            
            if ( ! empty( $service_id ) && $rate > 0 ) {
                $services_data[] = [
                    'service_id' => $service_id,
                    'rate' => $rate
                ];
            }
        }
        
        $order->update_meta_data( '_tutor_services', $services_data );
        $order->update_meta_data( '_tutor_bio', sanitize_textarea_field( $_POST['tutor_bio'] ?? '' ) );
        
        // Save schedule data
        $schedule = [
            'days' => array_map( 'sanitize_text_field', $_POST['schedule_days'] ?? [] ),
            'start' => sanitize_text_field( $_POST['schedule_start'] ?? '' ),
            'end' => sanitize_text_field( $_POST['schedule_end'] ?? '' )
        ];
        
        $order->update_meta_data( '_tutor_schedule', $schedule );
        $order->save();
        
        // Also save legacy format for backward compatibility
        if ( ! empty( $services_data ) ) {
            $first_service = $services_data[0];
            $order->update_meta_data( '_tutor_service_id', $first_service['service_id'] );
            $order->update_meta_data( '_tutor_hourly_rate', $first_service['rate'] );
        }
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
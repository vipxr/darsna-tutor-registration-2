<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.4.0
 * Description: Streamlined tutor checkout with service selection, hourly rates, and LatePoint integration
 * Requires PHP: 7.4
 * Author: Darsna
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DARSNA_TUTOR_REG_VERSION', '3.4.0' );

class Darsna_Tutor_Checkout {
    private static $instance = null;
    private $required_plugins = [
        'woocommerce/woocommerce.php' => 'WooCommerce',
        'woocommerce-subscriptions/woocommerce-subscriptions.php' => 'WooCommerce Subscriptions',
        'latepoint/latepoint.php' => 'LatePoint',
    ];
    private $active_statuses = [ 'active' ];
    private $inactive_statuses = [ 'pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash' ];

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }

    private function log_error( $message ) {
        error_log( '[DarsnaTutorCheckout] ' . $message );
    }

    public function init() {
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'show_dependency_notice' ] );
            return;
        }

        $this->setup_hooks();
    }

    private function setup_hooks() {
        // Checkout hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_scripts' ] );
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_checkout_fields' ], 10, 2 );
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'add_tutor_registration_fields' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_tutor_fields' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_tutor_checkout_data' ] );

        // Order and subscription hooks
        add_filter( 'woocommerce_cod_process_payment_order_status', [ $this, 'force_cod_orders_on_hold' ], 10, 2 );
        add_action( 'woocommerce_subscription_payment_complete', [ $this, 'handle_subscription_payment_complete' ], 5, 1 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completed' ], 10, 1 );
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_subscription_status_change' ], 10, 3 );

        // User management hooks
        add_action( 'delete_user', [ $this, 'handle_user_deletion' ], 10, 1 );
        add_action( 'remove_user_role', [ $this, 'handle_user_role_removal' ], 10, 2 );
        add_action( 'set_user_role', [ $this, 'handle_user_role_change' ], 10, 3 );

        // Menu hook
        add_filter( 'wp_nav_menu_items', [ $this, 'add_user_menu_items' ], 99, 2 );
    }

    private function check_dependencies() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( $this->required_plugins as $plugin_file => $plugin_name ) {
            if ( ! is_plugin_active( $plugin_file ) ) {
                return false;
            }
        }
        return true;
    }

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

    public function force_cod_orders_on_hold( $status, $order ) {
        return 'on-hold';
    }

    public function handle_subscription_payment_complete( $subscription ) {
        try {
            $parent_order = $this->get_subscription_parent_order( $subscription );
            if ( $parent_order && $parent_order->get_payment_method() === 'cod' ) {
                $subscription->update_status( 'on-hold', 'COD subscription requires manual approval.' );
            }
        } catch ( Exception $e ) {
            $this->log_error( "Error handling subscription payment complete: " . $e->getMessage() );
        }
    }

    public function handle_order_completed( $order_id ) {
        try {
            $order = wc_get_order( $order_id );
            if ( ! $order || ! $this->order_contains_subscription( $order ) ) {
                return;
            }

            $subscriptions = $this->get_order_subscriptions( $order );
            foreach ( $subscriptions as $subscription ) {
                $this->activate_subscription_and_agent( $subscription );
            }
        } catch ( Exception $e ) {
            $this->log_error( "Error handling order completion: " . $e->getMessage() );
        }
    }

    public function handle_subscription_status_change( $subscription, $new_status, $old_status ) {
        if ( $new_status === $old_status ) {
            return;
        }

        try {
            $user_id = $subscription->get_user_id();
            if ( ! $user_id ) {
                return;
            }

            if ( in_array( $new_status, $this->active_statuses, true ) ) {
                $this->handle_subscription_activation( $subscription, $user_id );
            } elseif ( in_array( $new_status, $this->inactive_statuses, true ) ) {
                $this->handle_subscription_deactivation( $subscription, $user_id );
            }
        } catch ( Exception $e ) {
            $this->log_error( "Error handling subscription status change: " . $e->getMessage() );
        }
    }

    private function handle_subscription_activation( $subscription, $user_id ) {
        $parent_order = $this->get_subscription_parent_order( $subscription );
        
        if ( $parent_order && $parent_order->get_status() === 'completed' ) {
            $already_active = get_user_meta( $user_id, '_darsna_subscription_active', true );
            if ( $already_active === 'yes' ) {
                return;
            }
            $this->activate_user_as_agent( $user_id, $subscription );
        } else {
            $subscription->update_status( 'on-hold', 'Pending manual approval.' );
        }
    }

    private function handle_subscription_deactivation( $subscription, $user_id ) {
        $this->deactivate_user_agent( $user_id );
    }

    private function activate_subscription_and_agent( $subscription ) {
        $user_id = $subscription->get_user_id();
        if ( ! $user_id ) {
            return;
        }

        // Prevent duplicate processing
        $processing_key = '_darsna_processing_activation_' . $user_id;
        if ( get_transient( $processing_key ) ) {
            return;
        }
        set_transient( $processing_key, true, 60 );

        try {
            if ( $subscription->get_status() !== 'active' ) {
                $subscription->update_status( 'active', 'Activated after manual order completion.' );
            }
            $this->activate_user_as_agent( $user_id, $subscription );
        } finally {
            delete_transient( $processing_key );
        }
    }

    private function activate_user_as_agent( $user_id, $subscription = null ) {
        try {
            // Prevent duplicate processing
            $processing_key = '_darsna_processing_user_' . $user_id;
            if ( get_transient( $processing_key ) ) {
                return;
            }
            set_transient( $processing_key, true, 60 );

            $tutor_data = $this->get_tutor_data_from_orders( $user_id );

            // Update user meta
            update_user_meta( $user_id, '_darsna_account_type', 'tutor' );
            update_user_meta( $user_id, '_darsna_subscription_active', 'yes' );

            if ( $subscription ) {
                update_user_meta( $user_id, '_darsna_subscription_id', $subscription->get_id() );
            }

            if ( $tutor_data ) {
                update_user_meta( $user_id, '_darsna_tutor_service_id', $tutor_data['service_id'] );
                update_user_meta( $user_id, '_darsna_tutor_hourly_rate', $tutor_data['hourly_rate'] );
                update_user_meta( $user_id, '_darsna_tutor_bio', $tutor_data['bio'] );
            }

            // Set user role and sync with LatePoint
            $this->assign_latepoint_agent_role( $user_id );
            $sync_result = $this->sync_latepoint_agent( $user_id, 'active' );

            if ( $sync_result && $tutor_data && $tutor_data['service_id'] ) {
                $this->assign_service_to_agent( $user_id, $tutor_data['service_id'] );
            }
        } catch ( Exception $e ) {
            $this->log_error( "Error activating user as agent: " . $e->getMessage() );
        } finally {
            delete_transient( $processing_key );
        }
    }

    private function deactivate_user_agent( $user_id ) {
        try {
            update_user_meta( $user_id, '_darsna_subscription_active', 'no' );
            $user = new WP_User( $user_id );
            $user->set_role( 'customer' );
            $this->sync_latepoint_agent( $user_id, 'disabled' );
        } catch ( Exception $e ) {
            $this->log_error( "Error deactivating user agent: " . $e->getMessage() );
        }
    }

    private function assign_latepoint_agent_role( $user_id ) {
        $user = new WP_User( $user_id );
        $user->set_role( 'latepoint_agent' );
    }

    private function sync_latepoint_agent( $user_id, $status = 'active' ) {
        if ( ! class_exists( '\OsAgentModel' ) ) {
            return false;
        }

        try {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                return false;
            }

            $agent_model = new \OsAgentModel();
            $existing_agents = $agent_model->where( [ 'wp_user_id' => $user_id ] )->get_results();

            if ( $existing_agents ) {
                // Update existing agent
                $existing_agent = $existing_agents[0];
                $update_agent = new \OsAgentModel();
                
                if ( method_exists( $update_agent, 'load_by_id' ) ) {
                    $update_agent->load_by_id( $existing_agent->id );
                } else {
                    $update_agent->id = $existing_agent->id;
                    foreach ( (array) $existing_agent as $key => $value ) {
                        $update_agent->set_data( $key, $value );
                    }
                }

                $update_agent->set_data( 'status', $status );
                return $update_agent->save();
            } else {
                // Create new agent if status is active
                if ( $status === 'active' ) {
                    return $this->create_new_agent( $user );
                }
            }
            return true;
        } catch ( Exception $e ) {
            $this->log_error( "Error syncing LatePoint agent: " . $e->getMessage() );
            return false;
        }
    }

    private function create_new_agent( $user ) {
        $agent_data = $this->prepare_agent_data( $user );

        // Check for existing agent by email
        $agent_model = new \OsAgentModel();
        $existing_by_email = $agent_model->where( [ 'email' => $agent_data['email'] ] )->get_results();
        
        if ( $existing_by_email ) {
            // Update existing agent found by email
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

            foreach ( $agent_data as $key => $value ) {
                $update_agent->set_data( $key, $value );
            }
            return $update_agent->save();
        }

        // Create new agent
        $new_agent = new \OsAgentModel();
        foreach ( $agent_data as $key => $value ) {
            $new_agent->set_data( $key, $value );
        }

        $save_result = $new_agent->save();
        
        // Fallback to direct database insertion if ORM fails
        if ( ! $save_result ) {
            return $this->direct_agent_insert( $agent_data );
        }
        
        return $save_result;
    }

    private function direct_agent_insert( $agent_data ) {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'latepoint_agents';
            $insert_data = [
                'first_name' => $agent_data['first_name'] ?? '',
                'last_name' => $agent_data['last_name'] ?? '',
                'display_name' => $agent_data['display_name'] ?? '',
                'email' => $agent_data['email'] ?? '',
                'phone' => $agent_data['phone'] ?? '',
                'wp_user_id' => $agent_data['wp_user_id'] ?? 0,
                'status' => $agent_data['status'] ?? 'active',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ];

            $result = $wpdb->insert( $table_name, $insert_data );
            return $result !== false;
        } catch ( Exception $e ) {
            $this->log_error( "Exception in direct_agent_insert: " . $e->getMessage() );
            return false;
        }
    }

    private function prepare_agent_data( $user ) {
        $user_id = $user->ID;
        $latest_order = $this->get_user_latest_order( $user_id );
        $tutor_bio = get_user_meta( $user_id, '_darsna_tutor_bio', true );

        $first_name = $user->first_name;
        $last_name = $user->last_name;

        if ( $latest_order ) {
            $first_name = $first_name ?: $latest_order->get_billing_first_name();
            $last_name = $last_name ?: $latest_order->get_billing_last_name();
        }

        if ( empty( $first_name ) || empty( $last_name ) ) {
            $name_parts = array_pad( explode( ' ', $user->display_name, 2 ), 2, '' );
            $first_name = $first_name ?: $name_parts[0];
            $last_name = $last_name ?: $name_parts[1];
        }

        $phone = '';
        if ( $latest_order ) {
            $phone = $this->format_phone_for_latepoint( $latest_order->get_billing_phone() );
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

    private function format_phone_for_latepoint( $phone ) {
        if ( empty( $phone ) ) {
            return '';
        }

        $clean_phone = preg_replace( '/[^\d+]/', '', $phone );
        if ( strpos( $clean_phone, '+' ) === 0 ) {
            return $clean_phone;
        }

        $digits_only = preg_replace( '/\D/', '', $phone );
        return '+' . $digits_only;
    }

    public function handle_user_deletion( $user_id ) {
        $this->sync_latepoint_agent( $user_id, 'disabled' );
    }

    public function handle_user_role_removal( $user_id, $role ) {
        if ( $role === 'latepoint_agent' ) {
            $this->sync_latepoint_agent( $user_id, 'disabled' );
        }
    }

    public function handle_user_role_change( $user_id, $role, $old_roles ) {
        if ( in_array( 'latepoint_agent', $old_roles, true ) && $role !== 'latepoint_agent' ) {
            $this->sync_latepoint_agent( $user_id, 'disabled' );
        }
    }

    public function enqueue_checkout_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_add_inline_script( 'jquery', $this->get_phone_input_script() );
        wp_add_inline_style( 'woocommerce-general', $this->get_checkout_styles() );
    }

    private function get_phone_input_script() {
        return "
            jQuery(function($) {
                var phoneInput = $('#billing_phone');
                if (phoneInput.length) {
                    phoneInput.attr('placeholder', '+96512345678');
                    if (!phoneInput.next('.phone-helper').length) {
                        phoneInput.after('<small class=\"phone-helper\" style=\"display: block; color: #666; margin-top: 4px;\">Please include country code (e.g., +96512345678)</small>');
                    }
                    phoneInput.on('input', function() {
                        var value = $(this).val().replace(/[^\d+]/g, '');
                        if (value && !value.startsWith('+')) {
                            value = '+' + value;
                        }
                        $(this).val(value);
                    });
                }

                var bioTextarea = $('#tutor_bio');
                if (bioTextarea.length) {
                    var maxLength = 500;
                    bioTextarea.after('<div id=\"bio-counter\" style=\"font-size: 12px; color: #666; margin-top: 5px;\">0/' + maxLength + ' characters</div>');
                    bioTextarea.on('input', function() {
                        var currentLength = $(this).val().length;
                        $('#bio-counter').text(currentLength + '/' + maxLength + ' characters');
                        $('#bio-counter').css('color', currentLength > maxLength ? '#d63638' : '#666');
                    });
                }
            });
        ";
    }

    private function get_checkout_styles() {
        return "
            #billing_phone { font-family: monospace; letter-spacing: 1px; }
            .phone-helper { font-size: 12px; color: #666; font-style: italic; }
            .tutor-field-group { background: #f9f9f9; border: 1px solid #e1e1e1; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .tutor-field-group h3 { margin-top: 0; color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
            .tutor-field-row { display: flex; gap: 20px; margin-bottom: 15px; }
            .tutor-field-row .form-row { flex: 1; }
            #tutor_service, #tutor_hourly_rate { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
            #tutor_bio { width: 100%; min-height: 80px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; resize: vertical; }
            .required-field::after { content: ' *'; color: #d63638; }
        ";
    }

    public function validate_checkout_fields( $data, $errors ) {
        $this->validate_phone_number( $data, $errors );
        $this->validate_required_fields( $data, $errors );
    }

    private function validate_phone_number( $data, $errors ) {
        $phone = $data['billing_phone'] ?? '';

        if ( empty( $phone ) ) {
            $errors->add( 'validation', __( 'Phone number is required.', 'darsna-tutor-registration' ) );
            return;
        }

        $clean_phone = preg_replace( '/[^\d+]/', '', $phone );
        if ( ! preg_match( '/^\+\d{1,4}\d{7,}$/', $clean_phone ) ) {
            $errors->add( 'validation', 
                __( 'Please enter a valid international phone number with country code (e.g., +96512345678).', 'darsna-tutor-registration' ) 
            );
            return;
        }

        $digits_only = preg_replace( '/\D/', '', $phone );
        if ( strlen( $digits_only ) < 8 ) {
            $errors->add( 'validation', 
                __( 'Phone number is too short. Please include country code and full number.', 'darsna-tutor-registration' ) 
            );
        }
    }

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

    public function add_tutor_registration_fields( $checkout ) {
        echo '<div class="tutor-field-group">';
        echo '<h3>' . __( 'Tutor Information', 'darsna-tutor-registration' ) . '</h3>';
        echo '<div class="tutor-field-row">';

        // Service selection
        echo '<div class="form-row form-row-first">';
        $services = $this->get_latepoint_services();
        $service_options = [ '' => __( 'Select a subject...', 'darsna-tutor-registration' ) ];

        if ( ! empty( $services ) ) {
            foreach ( $services as $service ) {
                $service_options[ $service->id ] = $service->name;
            }
        } else {
            $service_options[''] = __( 'No subjects available - please contact support', 'darsna-tutor-registration' );
        }

        woocommerce_form_field( 'tutor_service', [
            'type'        => 'select',
            'class'       => [ 'form-row-wide' ],
            'label'       => __( 'Subject to teach', 'darsna-tutor-registration' ) . ' <span class="required-field"></span>',
            'required'    => true,
            'options'     => $service_options,
        ], $checkout->get_value( 'tutor_service' ) );
        echo '</div>';

        // Hourly rate selection
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
        echo '</div>';

        // Bio field
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

        echo '</div>';
    }

    public function validate_tutor_fields() {
        if ( empty( $_POST['tutor_service'] ) ) {
            wc_add_notice( __( 'Please select a subject to teach.', 'darsna-tutor-registration' ), 'error' );
        } else {
            $selected_service = sanitize_text_field( $_POST['tutor_service'] );
            $services = $this->get_latepoint_services();
            $valid_service = false;

            if ( ! empty( $services ) ) {
                foreach ( $services as $service ) {
                    if ( $service->id == $selected_service ) {
                        $valid_service = true;
                        break;
                    }
                }
            } else {
                wc_add_notice( __( 'No subjects available. Please contact support.', 'darsna-tutor-registration' ), 'error' );
                return;
            }

            if ( ! $valid_service ) {
                wc_add_notice( __( 'Please select a valid subject.', 'darsna-tutor-registration' ), 'error' );
            }
        }

        if ( empty( $_POST['tutor_hourly_rate'] ) ) {
            wc_add_notice( __( 'Please select your hourly rate.', 'darsna-tutor-registration' ), 'error' );
        } else {
            $selected_rate = intval( $_POST['tutor_hourly_rate'] );
            if ( $selected_rate < 5 || $selected_rate > 50 || $selected_rate % 5 !== 0 ) {
                wc_add_notice( __( 'Please select a valid hourly rate.', 'darsna-tutor-registration' ), 'error' );
            }
        }

        if ( ! empty( $_POST['tutor_bio'] ) && strlen( $_POST['tutor_bio'] ) > 500 ) {
            wc_add_notice( __( 'Bio must be 500 characters or less.', 'darsna-tutor-registration' ), 'error' );
        }
    }

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

    private function get_latepoint_services() {
        if ( ! class_exists( '\OsServiceModel' ) ) {
            return [];
        }

        try {
            $service_model = new \OsServiceModel();
            $services = $service_model->where( [ 'status' => 'active' ] )->get_results();
            return $services ?: [];
        } catch ( Exception $e ) {
            $this->log_error( 'Error getting LatePoint services: ' . $e->getMessage() );
            return [];
        }
    }

    public function add_user_menu_items( $items, $args ) {
        if ( ! is_user_logged_in() || ! isset( $args->theme_location ) || $args->theme_location !== 'primary-menu' ) {
            return $items;
        }

        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;

        $dashboard_url = in_array( 'latepoint_agent', $user_roles, true )
            ? admin_url( 'admin.php?page=latepoint' )
            : wc_get_page_permalink( 'myaccount' );

        $items .= sprintf(
            '<li class="menu-item menu-item-dashboard"><a href="%s">%s</a></li>',
            esc_url( $dashboard_url ),
            esc_html__( 'Dashboard', 'darsna-tutor-registration' )
        );

        $items .= sprintf(
            '<li class="menu-item menu-item-logout"><a href="%s">%s</a></li>',
            esc_url( wp_logout_url( home_url() ) ),
            esc_html__( 'Logout', 'darsna-tutor-registration' )
        );

        return $items;
    }

    // Helper methods
    private function order_contains_subscription( $order ) {
        return function_exists( 'wcs_order_contains_subscription' ) && 
               wcs_order_contains_subscription( $order );
    }

    private function get_order_subscriptions( $order ) {
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return [];
        }
        return wcs_get_subscriptions_for_order( $order );
    }

    private function get_subscription_parent_order( $subscription ) {
        $parent_id = $subscription->get_parent_id();
        return $parent_id ? wc_get_order( $parent_id ) : null;
    }

    private function get_user_latest_order( $user_id ) {
        $orders = wc_get_orders( [
            'customer' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ] );
        return ! empty( $orders ) ? $orders[0] : null;
    }

    private function get_tutor_data_from_orders( $user_id ) {
        $orders = wc_get_orders( [
            'customer' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ] );

        if ( empty( $orders ) ) {
            return null;
        }

        $order = $orders[0];
        $service_id = $order->get_meta( '_tutor_service_id' );
        $hourly_rate = $order->get_meta( '_tutor_hourly_rate' );
        $bio = $order->get_meta( '_tutor_bio' );

        if ( empty( $service_id ) || empty( $hourly_rate ) ) {
            return null;
        }

        return [
            'service_id' => intval( $service_id ),
            'hourly_rate' => intval( $hourly_rate ),
            'bio' => sanitize_textarea_field( $bio ),
        ];
    }

    private function assign_service_to_agent( $user_id, $service_id ) {
        if ( ! class_exists( '\OsAgentModel' ) || ! class_exists( '\OsServiceModel' ) ) {
            return false;
        }

        try {
            $agent_model = new \OsAgentModel();
            $agents = $agent_model->where( [ 'wp_user_id' => $user_id ] )->get_results();

            if ( empty( $agents ) ) {
                return false;
            }

            $agent_id = $agents[0]->id;
            
            // Verify service exists
            $service_model = new \OsServiceModel();
            $service = $service_model->where( [ 'id' => $service_id ] )->get_results();
            if ( empty( $service ) ) {
                return false;
            }

            // Check if connection already exists
            global $wpdb;
            $connections_table = $wpdb->prefix . 'latepoint_agent_services';
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$connections_table} WHERE agent_id = %d AND service_id = %d",
                $agent_id,
                $service_id
            ) );

            if ( ! $existing ) {
                $result = $wpdb->insert(
                    $connections_table,
                    [ 'agent_id' => $agent_id, 'service_id' => $service_id ],
                    [ '%d', '%d' ]
                );
                return $result !== false;
            }

            return true;
        } catch ( Exception $e ) {
            $this->log_error( "Error assigning service to agent: " . $e->getMessage() );
            return false;
        }
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', function() {
    Darsna_Tutor_Checkout::get_instance();
} );

// Activation/Deactivation hooks
register_activation_hook( __FILE__, function() {
    error_log( '[DarsnaTutorCheckout] Plugin activated' );
} );

register_deactivation_hook( __FILE__, function() {
    error_log( '[DarsnaTutorCheckout] Plugin deactivated' );
} );
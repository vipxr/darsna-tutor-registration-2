<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.6.0
 * Description: Minimal tutor checkout with service selection and LatePoint integration
 * Requires PHP: 7.4
 * Author: Darsna
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Darsna_Tutor_Checkout {
    private static $instance;
    private static $services_cache;
    
    private const REQUIRED_PLUGINS = [
        'woocommerce/woocommerce.php',
        'woocommerce-subscriptions/woocommerce-subscriptions.php',
        'latepoint/latepoint.php'
    ];
    
    private const ACTIVE_STATUSES = ['active'];
    private const INACTIVE_STATUSES = ['pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash'];

    public static function instance() {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }

    public function init() {
        if ( ! $this->dependencies_met() ) {
            add_action( 'admin_notices', [ $this, 'dependency_notice' ] );
            return;
        }
        $this->setup_hooks();
    }

    private function dependencies_met() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach ( self::REQUIRED_PLUGINS as $plugin ) {
            if ( ! is_plugin_active( $plugin ) ) return false;
        }
        return true;
    }

    public function dependency_notice() {
        echo '<div class="notice notice-error"><p><strong>Tutor Registration:</strong> Missing required plugins (WooCommerce, WooCommerce Subscriptions, LatePoint)</p></div>';
    }

    private function setup_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'checkout_assets' ] );
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'checkout_fields' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_fields' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_order_data' ] );
        
        add_filter( 'woocommerce_cod_process_payment_order_status', fn() => 'on-hold' );
        add_action( 'woocommerce_subscription_payment_complete', [ $this, 'handle_payment' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_completion' ] );
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_status_change' ], 10, 3 );
        
        add_action( 'delete_user', [ $this, 'handle_user_deletion' ] );
        add_action( 'remove_user_role', [ $this, 'handle_role_removal' ], 10, 2 );
        add_action( 'set_user_role', [ $this, 'handle_role_change' ], 10, 3 );
        
        add_filter( 'wp_nav_menu_items', [ $this, 'user_menu' ], 99, 2 );
        add_action( 'darsna_assign_service', [ $this, 'delayed_service_assignment' ], 10, 2 );
        add_action( 'darsna_apply_schedule', [ $this, 'apply_agent_schedule' ], 10, 2 );
        
        add_filter( 'latepoint_booking_data_for_pricing_calculation', [ $this, 'dynamic_agent_pricing' ], 10, 2 );
        add_filter( 'latepoint_service_charge_amount', [ $this, 'agent_service_pricing' ], 10, 3 );
    }

    public function checkout_assets() {
        if ( ! is_checkout() ) return;
        
        wp_add_inline_script( 'jquery', "
            jQuery(function($) {
                $('#billing_phone').attr('placeholder', '+96512345678')
                    .after('<small style=\"color:#666;font-size:12px;display:block;margin-top:4px\">Include country code</small>')
                    .on('input', function() {
                        var v = $(this).val().replace(/[^\d+]/g, '');
                        $(this).val(v && !v.startsWith('+') ? '+' + v : v);
                    });
                
                $('#tutor_bio').after('<div id=\"bio-counter\" style=\"font-size:12px;color:#666;margin-top:5px\">0/500</div>')
                    .on('input', function() {
                        var len = $(this).val().length;
                        $('#bio-counter').text(len + '/500').css('color', len > 500 ? '#d63638' : '#666');
                    });

                $('.schedule-day').change(function() {
                    var checked = $('.schedule-day:checked').length;
                    $('.schedule-times')[checked > 0 ? 'show' : 'hide']();
                });
            });
        " );
        
        wp_add_inline_style( 'woocommerce-general', "
            .tutor-fields{background:#f9f9f9;border:1px solid #e1e1e1;border-radius:8px;padding:20px;margin:20px 0}
            .tutor-fields h3{margin-top:0;color:#0073aa;border-bottom:2px solid #0073aa;padding-bottom:10px}
            .tutor-row{display:flex;gap:20px;margin-bottom:15px}
            .tutor-row .form-row{flex:1}
            #tutor_service,#tutor_hourly_rate,#tutor_bio{width:100%;padding:10px;border:1px solid #ddd;border-radius:4px}
            #tutor_bio{min-height:80px;resize:vertical}
            .schedule-section{margin-top:20px;padding-top:15px;border-top:1px solid #ddd}
            .schedule-days{display:grid;grid-template-columns:repeat(auto-fit,minmax(80px,1fr));gap:10px;margin:10px 0}
            .schedule-day-item{display:flex;align-items:center;gap:5px;font-size:13px}
            .schedule-times{display:none;margin-top:15px}
            .schedule-time-row{display:flex;gap:15px;align-items:center}
            .schedule-time-row input{padding:8px;border:1px solid #ddd;border-radius:4px;width:120px}
        " );
    }

    public function checkout_fields( $checkout ) {
        $services = $this->get_services();
        $service_options = [ '' => __( 'Select subject...', 'darsna' ) ];
        
        if ( $services ) {
            foreach ( $services as $service ) {
                $service_options[ $service->id ] = $service->name;
            }
        } else {
            $service_options[''] = __( 'No subjects available', 'darsna' );
        }

        $rate_options = [ '' => __( 'Select rate...', 'darsna' ) ];
        $symbol = get_woocommerce_currency_symbol();
        for ( $i = 5; $i <= 50; $i += 5 ) {
            $rate_options[ $i ] = $symbol . $i . '/hr';
        }

        echo '<div class="tutor-fields">
            <h3>' . __( 'Tutor Information', 'darsna' ) . '</h3>
            <div class="tutor-row">';

        woocommerce_form_field( 'tutor_service', [
            'type' => 'select',
            'class' => ['form-row-first'],
            'label' => __( 'Subject', 'darsna' ),
            'required' => true,
            'options' => $service_options,
        ], $checkout->get_value( 'tutor_service' ) );

        woocommerce_form_field( 'tutor_hourly_rate', [
            'type' => 'select',
            'class' => ['form-row-last'],
            'label' => __( 'Rate', 'darsna' ),
            'required' => true,
            'options' => $rate_options,
        ], $checkout->get_value( 'tutor_hourly_rate' ) );

        echo '</div>';

        woocommerce_form_field( 'tutor_bio', [
            'type' => 'textarea',
            'class' => ['form-row-wide'],
            'label' => __( 'Bio', 'darsna' ),
            'placeholder' => __( 'Teaching background and specialties...', 'darsna' ),
            'custom_attributes' => ['maxlength' => '500', 'rows' => '4'],
        ], $checkout->get_value( 'tutor_bio' ) );

        echo '<div class="schedule-section">
            <h4>' . __( 'Availability (Optional)', 'darsna' ) . '</h4>
            <p style="font-size:13px;color:#666;margin:5px 0 15px">' . __( 'Set your default teaching hours. You can adjust this later in your dashboard.', 'darsna' ) . '</p>
            
            <div class="schedule-days">';
            
        $days = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
        foreach ( $days as $key => $label ) {
            $checked = $checkout->get_value( 'schedule_' . $key ) ? 'checked' : '';
            echo '<div class="schedule-day-item">
                <input type="checkbox" id="schedule_' . $key . '" name="schedule_' . $key . '" value="1" class="schedule-day" ' . $checked . '>
                <label for="schedule_' . $key . '">' . $label . '</label>
            </div>';
        }
        
        echo '</div>
            <div class="schedule-times">
                <div class="schedule-time-row">
                    <label>' . __( 'From:', 'darsna' ) . '</label>
                    <input type="time" name="schedule_start" value="' . esc_attr( $checkout->get_value( 'schedule_start' ) ?: '09:00' ) . '">
                    <label>' . __( 'To:', 'darsna' ) . '</label>
                    <input type="time" name="schedule_end" value="' . esc_attr( $checkout->get_value( 'schedule_end' ) ?: '17:00' ) . '">
                </div>
            </div>
        </div>';

        echo '</div>';
    }

    public function validate_fields() {
        $phone = $_POST['billing_phone'] ?? '';
        if ( ! $phone ) {
            wc_add_notice( __( 'Phone required', 'darsna' ), 'error' );
        } elseif ( ! preg_match( '/^\+\d{8,15}$/', preg_replace( '/[^\d+]/', '', $phone ) ) ) {
            wc_add_notice( __( 'Invalid phone format', 'darsna' ), 'error' );
        }

        $service = $_POST['tutor_service'] ?? '';
        if ( ! $service ) {
            wc_add_notice( __( 'Subject required', 'darsna' ), 'error' );
        } else {
            $services = $this->get_services();
            if ( ! $services || ! wp_list_filter( $services, ['id' => $service] ) ) {
                wc_add_notice( __( 'Invalid subject', 'darsna' ), 'error' );
            }
        }

        $rate = (int) ( $_POST['tutor_hourly_rate'] ?? 0 );
        if ( ! $rate || $rate < 5 || $rate > 50 || $rate % 5 !== 0 ) {
            wc_add_notice( __( 'Invalid rate', 'darsna' ), 'error' );
        }

        if ( ! empty( $_POST['tutor_bio'] ) && strlen( $_POST['tutor_bio'] ) > 500 ) {
            wc_add_notice( __( 'Bio too long', 'darsna' ), 'error' );
        }

        if ( ! empty( $_POST['schedule_start'] ) && ! empty( $_POST['schedule_end'] ) ) {
            if ( $_POST['schedule_start'] >= $_POST['schedule_end'] ) {
                wc_add_notice( __( 'End time must be after start time', 'darsna' ), 'error' );
            }
        }
    }

    public function save_order_data( $order_id ) {
        $fields = ['tutor_service' => '_tutor_service_id', 'tutor_hourly_rate' => '_tutor_hourly_rate', 'tutor_bio' => '_tutor_bio'];
        foreach ( $fields as $field => $meta ) {
            if ( ! empty( $_POST[ $field ] ) ) {
                update_post_meta( $order_id, $meta, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        $schedule = [];
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        foreach ( $days as $day ) {
            if ( ! empty( $_POST[ 'schedule_' . $day ] ) ) {
                $schedule[ $day ] = 1;
            }
        }
        
        if ( ! empty( $schedule ) && ! empty( $_POST['schedule_start'] ) && ! empty( $_POST['schedule_end'] ) ) {
            $schedule['start'] = sanitize_text_field( $_POST['schedule_start'] );
            $schedule['end'] = sanitize_text_field( $_POST['schedule_end'] );
            update_post_meta( $order_id, '_tutor_schedule', $schedule );
        }
    }

    public function handle_payment( $subscription ) {
        $parent = $subscription->get_parent_id() ? wc_get_order( $subscription->get_parent_id() ) : null;
        if ( $parent && $parent->get_payment_method() === 'cod' ) {
            $subscription->update_status( 'on-hold', 'COD requires approval' );
        }
    }

    public function handle_completion( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) return;
        
        $subscriptions = wcs_get_subscriptions_for_order( $order );
        foreach ( $subscriptions as $subscription ) {
            $this->activate_subscription( $subscription );
        }
    }

    public function handle_status_change( $subscription, $new_status, $old_status ) {
        if ( $new_status === $old_status ) return;
        
        $user_id = $subscription->get_user_id();
        if ( ! $user_id ) return;

        if ( in_array( $new_status, self::ACTIVE_STATUSES ) ) {
            $this->handle_activation( $subscription, $user_id );
        } elseif ( in_array( $new_status, self::INACTIVE_STATUSES ) ) {
            $this->deactivate_user( $user_id );
        }
    }

    private function handle_activation( $subscription, $user_id ) {
        $parent = $subscription->get_parent_id() ? wc_get_order( $subscription->get_parent_id() ) : null;
        
        if ( $parent && $parent->get_status() === 'completed' ) {
            if ( get_user_meta( $user_id, '_darsna_subscription_active', true ) !== 'yes' ) {
                $this->activate_user( $user_id, $subscription );
            }
        } else {
            $subscription->update_status( 'on-hold', 'Pending approval' );
        }
    }

    private function activate_subscription( $subscription ) {
        $user_id = $subscription->get_user_id();
        if ( ! $user_id ) return;

        $key = '_processing_' . $user_id;
        if ( get_transient( $key ) ) return;
        set_transient( $key, 1, 60 );

        if ( $subscription->get_status() !== 'active' ) {
            $subscription->update_status( 'active', 'Activated' );
        }
        $this->activate_user( $user_id, $subscription );
        delete_transient( $key );
    }

    private function activate_user( $user_id, $subscription = null ) {
        $key = '_activating_' . $user_id;
        if ( get_transient( $key ) ) return;
        set_transient( $key, 1, 60 );

        $tutor_data = $this->get_tutor_data( $user_id );
        
        $meta = [
            '_darsna_account_type' => 'tutor',
            '_darsna_subscription_active' => 'yes'
        ];
        
        if ( $subscription ) {
            $meta['_darsna_subscription_id'] = $subscription->get_id();
        }
        
        if ( $tutor_data ) {
            $meta['_darsna_tutor_service_id'] = $tutor_data['service_id'];
            $meta['_darsna_tutor_hourly_rate'] = $tutor_data['hourly_rate'];
            $meta['_darsna_tutor_bio'] = $tutor_data['bio'];
        }
        
        foreach ( $meta as $k => $v ) {
            update_user_meta( $user_id, $k, $v );
        }

        wp_update_user( ['ID' => $user_id, 'role' => 'latepoint_agent'] );
        
        if ( $this->sync_agent( $user_id, 'active' ) && $tutor_data ) {
            wp_schedule_single_event( time() + 5, 'darsna_assign_service', [ $user_id, $tutor_data['service_id'] ] );
        }
        
        delete_transient( $key );
    }

    private function deactivate_user( $user_id ) {
        update_user_meta( $user_id, '_darsna_subscription_active', 'no' );
        wp_update_user( ['ID' => $user_id, 'role' => 'customer'] );
        $this->sync_agent( $user_id, 'disabled' );
    }

    private function sync_agent( $user_id, $status = 'active' ) {
        if ( ! class_exists( '\OsAgentModel' ) ) return false;

        $user = get_userdata( $user_id );
        if ( ! $user ) return false;

        $model = new \OsAgentModel();
        $existing = $model->where( ['wp_user_id' => $user_id] )->get_results();

        if ( $existing ) {
            return $this->update_agent( $existing[0], $status );
        } elseif ( $status === 'active' ) {
            return $this->create_agent( $user );
        }
        
        return true;
    }

    private function update_agent( $agent, $status ) {
        $update = new \OsAgentModel();
        
        if ( method_exists( $update, 'load_by_id' ) ) {
            $update->load_by_id( $agent->id );
        } else {
            $update->id = $agent->id;
            foreach ( (array) $agent as $k => $v ) {
                $update->set_data( $k, $v );
            }
        }
        
        $update->set_data( 'status', $status );
        return $update->save();
    }

    private function create_agent( $user ) {
        $data = $this->prepare_agent_data( $user );
        
        $model = new \OsAgentModel();
        $existing = $model->where( ['email' => $data['email']] )->get_results();
        
        if ( $existing ) {
            return $this->update_agent( $existing[0], 'active' );
        }

        $agent = new \OsAgentModel();
        foreach ( $data as $k => $v ) {
            $agent->set_data( $k, $v );
        }

        return $agent->save() ?: $this->direct_insert( $data );
    }

    private function prepare_agent_data( $user ) {
        $order = $this->get_latest_order( $user->ID );
        $bio = get_user_meta( $user->ID, '_darsna_tutor_bio', true );
        
        $first = $user->first_name ?: ( $order ? $order->get_billing_first_name() : '' );
        $last = $user->last_name ?: ( $order ? $order->get_billing_last_name() : '' );
        
        if ( ! $first || ! $last ) {
            $parts = explode( ' ', $user->display_name, 2 );
            $first = $first ?: ( $parts[0] ?? '' );
            $last = $last ?: ( $parts[1] ?? '' );
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $order ? $this->format_phone( $order->get_billing_phone() ) : '',
            'bio' => $bio ? sanitize_textarea_field( $bio ) : '',
            'wp_user_id' => $user->ID,
            'status' => 'active',
        ];
    }

    private function format_phone( $phone ) {
        if ( ! $phone ) return '';
        $clean = preg_replace( '/[^\d+]/', '', $phone );
        return strpos( $clean, '+' ) === 0 ? $clean : '+' . preg_replace( '/\D/', '', $phone );
    }

    private function direct_insert( $data ) {
        global $wpdb;
        return $wpdb->insert( 
            $wpdb->prefix . 'latepoint_agents',
            array_merge( $data, [
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' )
            ] )
        ) !== false;
    }

    private function get_services() {
        if ( isset( self::$services_cache ) ) {
            return self::$services_cache;
        }

        if ( ! class_exists( '\OsServiceModel' ) ) {
            return self::$services_cache = [];
        }

        $model = new \OsServiceModel();
        return self::$services_cache = $model->where( ['status' => 'active'] )->get_results() ?: [];
    }

    private function get_latest_order( $user_id ) {
        static $cache = [];
        if ( isset( $cache[ $user_id ] ) ) return $cache[ $user_id ];
        
        $orders = wc_get_orders( ['customer' => $user_id, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC'] );
        return $cache[ $user_id ] = $orders[0] ?? null;
    }

    private function get_tutor_data( $user_id ) {
        $order = $this->get_latest_order( $user_id );
        if ( ! $order ) return null;

        $service_id = $order->get_meta( '_tutor_service_id' );
        $rate = $order->get_meta( '_tutor_hourly_rate' );
        
        if ( ! $service_id || ! $rate ) return null;

        return [
            'service_id' => (int) $service_id,
            'hourly_rate' => (int) $rate,
            'bio' => sanitize_textarea_field( $order->get_meta( '_tutor_bio' ) ),
            'schedule' => $order->get_meta( '_tutor_schedule' ) ?: []
        ];
    }

    public function delayed_service_assignment( $user_id, $service_id ) {
        $this->assign_service( $user_id, $service_id );
    }

    public function apply_agent_schedule( $user_id, $schedule ) {
        if ( ! class_exists( '\OsAgentModel' ) || empty( $schedule ) ) return;

        $agent_model = new \OsAgentModel();
        $agents = $agent_model->where( ['wp_user_id' => $user_id, 'status' => 'active'] )->get_results();
        if ( ! $agents ) return;

        global $wpdb;
        $agent_id = $agents[0]->id;
        $table = $wpdb->prefix . 'latepoint_work_periods';

        $wpdb->delete( $table, ['agent_id' => $agent_id], ['%d'] );

        $day_map = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        $start_minutes = $this->time_to_minutes( $schedule['start'] ?? '09:00' );
        $end_minutes = $this->time_to_minutes( $schedule['end'] ?? '17:00' );

        foreach ( $day_map as $day => $week_day ) {
            if ( ! empty( $schedule[ $day ] ) ) {
                $wpdb->insert( $table, [
                    'agent_id' => $agent_id,
                    'week_day' => $week_day,
                    'start_time' => $start_minutes,
                    'end_time' => $end_minutes,
                    'chain_id' => wp_generate_uuid4()
                ], ['%d', '%d', '%d', '%d', '%s'] );
            }
        }
    }

    private function time_to_minutes( $time ) {
        $parts = explode( ':', $time );
        return ( (int) $parts[0] * 60 ) + ( (int) ( $parts[1] ?? 0 ) );
    }

    private function assign_service( $user_id, $service_id ) {
        if ( ! class_exists( '\OsAgentModel' ) || ! $service_id ) return false;

        $model = new \OsAgentModel();
        $agents = $model->where( ['wp_user_id' => $user_id, 'status' => 'active'] )->get_results();
        if ( ! $agents ) return false;

        global $wpdb;
        $table = $wpdb->prefix . 'latepoint_agent_services';
        
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE agent_id = %d AND service_id = %d",
            $agents[0]->id, $service_id
        ) );

        if ( $existing ) return true;

        $result = $wpdb->insert( $table, [
            'agent_id' => (int) $agents[0]->id,
            'service_id' => (int) $service_id
        ], ['%d', '%d'] );

        return $result !== false;
    }

    public function handle_user_deletion( $user_id ) {
        $this->sync_agent( $user_id, 'disabled' );
    }

    public function handle_role_removal( $user_id, $role ) {
        if ( $role === 'latepoint_agent' ) {
            $this->sync_agent( $user_id, 'disabled' );
        }
    }

    public function handle_role_change( $user_id, $role, $old_roles ) {
        if ( in_array( 'latepoint_agent', $old_roles ) && $role !== 'latepoint_agent' ) {
            $this->sync_agent( $user_id, 'disabled' );
        }
    }

    public function dynamic_agent_pricing( $booking_data, $booking ) {
        if ( empty( $booking_data['agent_id'] ) ) return $booking_data;
        
        $rate = $this->get_agent_rate( $booking_data['agent_id'] );
        if ( $rate ) {
            $booking_data['custom_agent_rate'] = $rate;
        }
        
        return $booking_data;
    }

    public function agent_service_pricing( $charge_amount, $service_id, $agent_id = null ) {
        if ( ! $agent_id ) return $charge_amount;
        
        $rate = $this->get_agent_rate( $agent_id );
        return $rate ?: $charge_amount;
    }

    private function get_agent_rate( $agent_id ) {
        static $cache = [];
        
        if ( isset( $cache[ $agent_id ] ) ) {
            return $cache[ $agent_id ];
        }
        
        if ( ! class_exists( '\OsAgentModel' ) ) {
            return $cache[ $agent_id ] = false;
        }
        
        $agent_model = new \OsAgentModel();
        $agent = $agent_model->where( ['id' => $agent_id] )->get_results();
        
        if ( ! $agent || ! $agent[0]->wp_user_id ) {
            return $cache[ $agent_id ] = false;
        }
        
        $rate = get_user_meta( $agent[0]->wp_user_id, '_darsna_tutor_hourly_rate', true );
        return $cache[ $agent_id ] = $rate ? (int) $rate : false;
    }

    public function user_menu( $items, $args ) {
        if ( ! is_user_logged_in() || $args->theme_location !== 'primary-menu' ) {
            return $items;
        }

        $user = wp_get_current_user();
        $is_agent = in_array( 'latepoint_agent', $user->roles );
        $dashboard = $is_agent ? admin_url( 'admin.php?page=latepoint' ) : wc_get_page_permalink( 'myaccount' );

        return $items . sprintf(
            '<li class="menu-item"><a href="%s">Dashboard</a></li><li class="menu-item"><a href="%s">Logout</a></li>',
            esc_url( $dashboard ),
            esc_url( wp_logout_url( home_url() ) )
        );
    }
}

add_action( 'plugins_loaded', fn() => Darsna_Tutor_Checkout::instance() );
register_activation_hook( __FILE__, fn() => error_log( '[DarsnaTutorCheckout] Activated' ) );
register_deactivation_hook( __FILE__, fn() => error_log( '[DarsnaTutorCheckout] Deactivated' ) );
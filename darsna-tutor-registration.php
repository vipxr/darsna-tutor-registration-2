<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.7.0
 * Description: Ultra-optimized tutor checkout with dynamic pricing and schedules
 * Requires PHP: 7.4
 * Author: Darsna
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Darsna_Tutor_Checkout {
    private static $instance;
    private static $cache = [];
    
    private const REQUIRED = ['woocommerce/woocommerce.php', 'woocommerce-subscriptions/woocommerce-subscriptions.php', 'latepoint/latepoint.php'];
    private const ACTIVE = ['active'];
    private const INACTIVE = ['pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash'];

    public static function instance() {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }

    public function init() {
        if ( ! $this->check_deps() ) {
            add_action( 'admin_notices', fn() => printf( '<div class="notice notice-error"><p><strong>Tutor Registration:</strong> Missing WooCommerce, Subscriptions, or LatePoint</p></div>' ) );
            return;
        }
        $this->setup();
    }

    private function check_deps() {
        if ( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        return !array_filter( self::REQUIRED, fn($p) => !is_plugin_active($p) );
    }

    private function setup() {
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'fields' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate' ] );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save' ] );
        
        add_filter( 'woocommerce_cod_process_payment_order_status', fn() => 'on-hold' );
        add_action( 'woocommerce_order_status_completed', [ $this, 'complete' ] );
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'status' ], 10, 3 );
        
        add_action( 'delete_user', fn($id) => $this->sync($id, 'disabled') );
        add_filter( 'wp_nav_menu_items', [ $this, 'menu' ], 99, 2 );
        
        add_filter( 'latepoint_service_charge_amount', [ $this, 'pricing' ], 10, 3 );
        add_action( 'darsna_activate_agent', [ $this, 'activate' ], 10, 2 );
    }

    public function assets() {
        if ( ! is_checkout() ) return;
        
        wp_add_inline_script( 'jquery', "jQuery(function($){
            $('#billing_phone').attr('placeholder','+96512345678').on('input',function(){
                var v=$(this).val().replace(/[^\\d+]/g,'');
                $(this).val(v&&!v.startsWith('+')?'+'+v:v);
            });
            $('#tutor_bio').after('<div id=\"c\">0/500</div>').on('input',function(){
                var l=$(this).val().length;$('#c').text(l+'/500').css('color',l>500?'red':'#666');
            });
            $('.day').change(function(){$('.times')[$('.day:checked').length?'show':'hide']();});
        });" );
        
        wp_add_inline_style( 'woocommerce-general', "
            .tutor{background:#f9f9f9;border:1px solid #e1e1e1;border-radius:8px;padding:20px;margin:20px 0}
            .tutor h3{margin-top:0;color:#0073aa;border-bottom:2px solid #0073aa;padding-bottom:10px}
            .row{display:flex;gap:20px;margin-bottom:15px}.row .form-row{flex:1}
            #tutor_service,#tutor_hourly_rate,#tutor_bio{width:100%;padding:10px;border:1px solid #ddd;border-radius:4px}
            #tutor_bio{min-height:80px;resize:vertical}#c{font-size:12px;color:#666;margin-top:5px}
            .sched{margin-top:15px;padding-top:15px;border-top:1px solid #ddd}
            .days{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin:10px 0}
            .day-item{display:flex;align-items:center;gap:5px;font-size:13px}
            .times{display:none;margin-top:15px}.time-row{display:flex;gap:15px;align-items:center}
            .time-row input{padding:8px;border:1px solid #ddd;border-radius:4px;width:120px}
        " );
    }

    public function fields( $checkout ) {
        $services = $this->services();
        $service_opts = [ '' => 'Select subject...' ];
        foreach ( $services as $s ) $service_opts[ $s->id ] = $s->name;
        if ( !$services ) $service_opts[''] = 'No subjects available';

        $rate_opts = [ '' => 'Select rate...' ];
        $sym = get_woocommerce_currency_symbol();
        for ( $i = 5; $i <= 50; $i += 5 ) $rate_opts[ $i ] = $sym . $i . '/hr';

        echo '<div class="tutor"><h3>Tutor Information</h3><div class="row">';

        woocommerce_form_field( 'tutor_service', [
            'type' => 'select', 'class' => ['form-row-first'], 'label' => 'Subject', 'required' => true, 'options' => $service_opts
        ], $checkout->get_value( 'tutor_service' ) );

        woocommerce_form_field( 'tutor_hourly_rate', [
            'type' => 'select', 'class' => ['form-row-last'], 'label' => 'Rate', 'required' => true, 'options' => $rate_opts
        ], $checkout->get_value( 'tutor_hourly_rate' ) );

        echo '</div>';

        woocommerce_form_field( 'tutor_bio', [
            'type' => 'textarea', 'class' => ['form-row-wide'], 'label' => 'Bio', 
            'placeholder' => 'Teaching background and specialties...', 'custom_attributes' => ['maxlength' => '500', 'rows' => '4']
        ], $checkout->get_value( 'tutor_bio' ) );

        echo '<div class="sched"><h4>Availability</h4><p style="font-size:13px;color:#666">Set default hours (optional)</p><div class="days">';
        
        $days = ['mon'=>'M', 'tue'=>'T', 'wed'=>'W', 'thu'=>'T', 'fri'=>'F', 'sat'=>'S', 'sun'=>'S'];
        foreach ( $days as $k => $l ) {
            $c = $checkout->get_value( 'schedule_' . $k ) ? 'checked' : '';
            echo "<div class=\"day-item\"><input type=\"checkbox\" id=\"s{$k}\" name=\"schedule_{$k}\" value=\"1\" class=\"day\" {$c}><label for=\"s{$k}\">{$l}</label></div>";
        }
        
        echo '</div><div class="times"><div class="time-row">
            <label>From:</label><input type="time" name="schedule_start" value="' . esc_attr( $checkout->get_value( 'schedule_start' ) ?: '09:00' ) . '">
            <label>To:</label><input type="time" name="schedule_end" value="' . esc_attr( $checkout->get_value( 'schedule_end' ) ?: '17:00' ) . '">
        </div></div></div></div>';
    }

    public function validate() {
        if ( !$_POST['tutor_service'] ) wc_add_notice( 'Subject required', 'error' );
        if ( !$_POST['tutor_hourly_rate'] ) wc_add_notice( 'Rate required', 'error' );
        
        $rate = (int)$_POST['tutor_hourly_rate'];
        if ( $rate < 5 || $rate > 50 || $rate % 5 ) wc_add_notice( 'Invalid rate', 'error' );
        
        if ( $_POST['tutor_bio'] && strlen($_POST['tutor_bio']) > 500 ) wc_add_notice( 'Bio too long', 'error' );
        
        if ( $_POST['schedule_start'] && $_POST['schedule_end'] && $_POST['schedule_start'] >= $_POST['schedule_end'] ) {
            wc_add_notice( 'End time must be after start time', 'error' );
        }
    }

    public function save( $order_id ) {
        update_post_meta( $order_id, '_tutor_service_id', sanitize_text_field( $_POST['tutor_service'] ?? '' ) );
        update_post_meta( $order_id, '_tutor_hourly_rate', sanitize_text_field( $_POST['tutor_hourly_rate'] ?? '' ) );
        update_post_meta( $order_id, '_tutor_bio', sanitize_textarea_field( $_POST['tutor_bio'] ?? '' ) );

        $sched = [];
        foreach ( ['mon','tue','wed','thu','fri','sat','sun'] as $d ) {
            if ( $_POST["schedule_{$d}"] ?? false ) $sched[$d] = 1;
        }
        if ( $sched && $_POST['schedule_start'] && $_POST['schedule_end'] ) {
            $sched['start'] = sanitize_text_field( $_POST['schedule_start'] );
            $sched['end'] = sanitize_text_field( $_POST['schedule_end'] );
            update_post_meta( $order_id, '_tutor_schedule', $sched );
        }
    }

    public function complete( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( !$order || !function_exists('wcs_get_subscriptions_for_order') ) return;
        
        foreach ( wcs_get_subscriptions_for_order( $order ) as $sub ) {
            wp_schedule_single_event( time() + 5, 'darsna_activate_agent', [ $sub->get_user_id(), $sub->get_id() ] );
        }
    }

    public function status( $sub, $new, $old ) {
        if ( $new === $old ) return;
        
        $uid = $sub->get_user_id();
        if ( !$uid ) return;

        if ( in_array( $new, self::ACTIVE ) ) {
            $parent = $sub->get_parent_id() ? wc_get_order( $sub->get_parent_id() ) : null;
            if ( $parent && $parent->get_status() === 'completed' ) {
                wp_schedule_single_event( time() + 5, 'darsna_activate_agent', [ $uid, $sub->get_id() ] );
            } else {
                $sub->update_status( 'on-hold', 'Pending approval' );
            }
        } elseif ( in_array( $new, self::INACTIVE ) ) {
            $this->deactivate( $uid );
        }
    }

    public function activate( $user_id, $sub_id ) {
        $data = $this->get_data( $user_id );
        if ( !$data ) return;

        $meta = [
            '_darsna_account_type' => 'tutor',
            '_darsna_subscription_active' => 'yes',
            '_darsna_subscription_id' => $sub_id,
            '_darsna_tutor_service_id' => $data['service_id'],
            '_darsna_tutor_hourly_rate' => $data['hourly_rate'],
            '_darsna_tutor_bio' => $data['bio']
        ];
        
        foreach ( $meta as $k => $v ) update_user_meta( $user_id, $k, $v );
        wp_update_user( ['ID' => $user_id, 'role' => 'latepoint_agent'] );
        
        if ( $this->sync( $user_id, 'active' ) ) {
            $this->assign_service( $user_id, $data['service_id'] );
            if ( $data['schedule'] ) $this->set_schedule( $user_id, $data['schedule'] );
        }
    }

    private function deactivate( $user_id ) {
        update_user_meta( $user_id, '_darsna_subscription_active', 'no' );
        wp_update_user( ['ID' => $user_id, 'role' => 'customer'] );
        $this->sync( $user_id, 'disabled' );
    }

    private function sync( $user_id, $status = 'active' ) {
        if ( !class_exists('\OsAgentModel') ) return false;

        $user = get_userdata( $user_id );
        if ( !$user ) return false;

        $model = new \OsAgentModel();
        $agents = $model->where(['wp_user_id' => $user_id])->get_results();

        if ( $agents ) {
            $agent = $agents[0];
            $update = new \OsAgentModel();
            if ( method_exists($update, 'load_by_id') ) {
                $update->load_by_id( $agent->id );
            } else {
                $update->id = $agent->id;
                foreach ( (array)$agent as $k => $v ) $update->set_data( $k, $v );
            }
            $update->set_data( 'status', $status );
            return $update->save();
        } elseif ( $status === 'active' ) {
            return $this->create_agent( $user );
        }
        
        return true;
    }

    private function create_agent( $user ) {
        $order = $this->get_order( $user->ID );
        $bio = get_user_meta( $user->ID, '_darsna_tutor_bio', true );
        
        $first = $user->first_name ?: ($order ? $order->get_billing_first_name() : '');
        $last = $user->last_name ?: ($order ? $order->get_billing_last_name() : '');
        
        if ( !$first || !$last ) {
            $parts = explode( ' ', $user->display_name, 2 );
            $first = $first ?: ($parts[0] ?? '');
            $last = $last ?: ($parts[1] ?? '');
        }

        $data = [
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $order ? $this->format_phone($order->get_billing_phone()) : '',
            'bio' => $bio ? sanitize_textarea_field($bio) : '',
            'wp_user_id' => $user->ID,
            'status' => 'active'
        ];

        $model = new \OsAgentModel();
        $existing = $model->where(['email' => $data['email']])->get_results();
        
        if ( $existing ) {
            $update = new \OsAgentModel();
            $update->load_by_id( $existing[0]->id );
            foreach ( $data as $k => $v ) $update->set_data( $k, $v );
            return $update->save();
        }

        $agent = new \OsAgentModel();
        foreach ( $data as $k => $v ) $agent->set_data( $k, $v );
        
        if ( $agent->save() ) return true;

        global $wpdb;
        return $wpdb->insert( $wpdb->prefix . 'latepoint_agents', array_merge($data, [
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]) ) !== false;
    }

    private function assign_service( $user_id, $service_id ) {
        if ( !class_exists('\OsAgentModel') || !$service_id ) return false;

        $model = new \OsAgentModel();
        $agents = $model->where(['wp_user_id' => $user_id, 'status' => 'active'])->get_results();
        if ( !$agents ) return false;

        global $wpdb;
        $table = $wpdb->prefix . 'latepoint_agent_services';
        
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE agent_id = %d AND service_id = %d",
            $agents[0]->id, $service_id
        ));

        return $exists ?: $wpdb->insert( $table, [
            'agent_id' => (int)$agents[0]->id,
            'service_id' => (int)$service_id
        ], ['%d', '%d'] ) !== false;
    }

    private function set_schedule( $user_id, $schedule ) {
        if ( !class_exists('\OsAgentModel') || !$schedule ) return;

        $model = new \OsAgentModel();
        $agents = $model->where(['wp_user_id' => $user_id])->get_results();
        if ( !$agents ) return;

        global $wpdb;
        $agent_id = $agents[0]->id;
        $table = $wpdb->prefix . 'latepoint_work_periods';

        $wpdb->delete( $table, ['agent_id' => $agent_id], ['%d'] );

        $days = ['mon'=>1, 'tue'=>2, 'wed'=>3, 'thu'=>4, 'fri'=>5, 'sat'=>6, 'sun'=>7];
        $start = $this->time_mins( $schedule['start'] ?? '09:00' );
        $end = $this->time_mins( $schedule['end'] ?? '17:00' );

        foreach ( $days as $day => $num ) {
            if ( !empty($schedule[$day]) ) {
                $wpdb->insert( $table, [
                    'agent_id' => $agent_id,
                    'week_day' => $num,
                    'start_time' => $start,
                    'end_time' => $end,
                    'chain_id' => wp_generate_uuid4()
                ], ['%d', '%d', '%d', '%d', '%s'] );
            }
        }
    }

    private function format_phone( $phone ) {
        if ( !$phone ) return '';
        $clean = preg_replace( '/[^\d+]/', '', $phone );
        return strpos($clean, '+') === 0 ? $clean : '+' . preg_replace('/\D/', '', $phone);
    }

    private function time_mins( $time ) {
        $parts = explode( ':', $time );
        return ((int)$parts[0] * 60) + (int)($parts[1] ?? 0);
    }

    private function services() {
        return self::$cache['services'] ??= (
            class_exists('\OsServiceModel') ? 
            (new \OsServiceModel())->where(['status' => 'active'])->get_results() ?: [] : 
            []
        );
    }

    private function get_order( $user_id ) {
        return self::$cache["order_{$user_id}"] ??= (
            wc_get_orders(['customer' => $user_id, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC'])[0] ?? null
        );
    }

    private function get_data( $user_id ) {
        $order = $this->get_order( $user_id );
        if ( !$order ) return null;

        $service_id = $order->get_meta( '_tutor_service_id' );
        $rate = $order->get_meta( '_tutor_hourly_rate' );
        
        return ($service_id && $rate) ? [
            'service_id' => (int)$service_id,
            'hourly_rate' => (int)$rate,
            'bio' => sanitize_textarea_field( $order->get_meta('_tutor_bio') ),
            'schedule' => $order->get_meta( '_tutor_schedule' ) ?: []
        ] : null;
    }

    public function pricing( $amount, $service_id, $agent_id = null ) {
        if ( !$agent_id ) return $amount;
        
        $key = "rate_{$agent_id}";
        if ( isset( self::$cache[$key] ) ) return self::$cache[$key] ?: $amount;
        
        if ( !class_exists('\OsAgentModel') ) return self::$cache[$key] = false;
        
        $model = new \OsAgentModel();
        $agent = $model->where(['id' => $agent_id])->get_results();
        
        if ( !$agent || !$agent[0]->wp_user_id ) return self::$cache[$key] = false;
        
        $rate = get_user_meta( $agent[0]->wp_user_id, '_darsna_tutor_hourly_rate', true );
        return self::$cache[$key] = $rate ? (int)$rate : false;
    }

    public function menu( $items, $args ) {
        if ( !is_user_logged_in() || $args->theme_location !== 'primary-menu' ) return $items;

        $user = wp_get_current_user();
        $dash = in_array('latepoint_agent', $user->roles) ? admin_url('admin.php?page=latepoint') : wc_get_page_permalink('myaccount');

        return $items . sprintf(
            '<li><a href="%s">Dashboard</a></li><li><a href="%s">Logout</a></li>',
            esc_url($dash), esc_url(wp_logout_url(home_url()))
        );
    }
}

add_action( 'plugins_loaded', fn() => Darsna_Tutor_Checkout::instance() );
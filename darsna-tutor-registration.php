<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.1.1
 * Description: Tutor-only checkout with manual order approval for LatePoint agents
 * Requires PHP: 7.2
 * Author: Darsna
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DARSNA_TUTOR_REG_VERSION', '3.1.1' );
define( 'DARSNA_TUTOR_REG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

class Darsna_Tutor_Checkout {

    public function __construct() {
        add_action( 'init', [ $this, 'init' ] );

        // enqueue intl-tel-input on checkout
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_phone_scripts' ] );

        // simple phone validation at checkout
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_phone_number' ], 10, 2 );
    }

    public function init() {
        // if missing dependencies, warning + bail
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'dependency_notice' ] );
            return;
        }

        // 1) Force COD orders to start on-hold
        add_filter( 'woocommerce_cod_process_payment_order_status', [ $this, 'force_cod_orders_on_hold' ], 10, 2 );
        // 2) Also trap subscription payment_complete to re-hold COD subs
        add_action( 'woocommerce_subscription_payment_complete', [ $this, 'check_cod_subscription_activation' ], 5, 1 );

        // 3) Manual “Completed” → activate subscription + agent
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completed' ], 10, 1 );

        // 4) Catch _any_ subscription status change
        add_action( 'woocommerce_subscription_status_updated', [ $this, 'handle_subscription_status' ], 10, 3 );

        // 5) If a WP user is deleted or their agent role removed → disable agent
        add_action( 'delete_user',               [ $this, 'remove_latepoint_agent' ],      10, 1 );
        add_action( 'remove_user_role',          [ $this, 'maybe_remove_agent_on_role_change' ], 10, 2 );

        // 6) Dashboard / Logout links for agents
        add_filter( 'wp_nav_menu_items', [ $this, 'add_dashboard_logout_menu_links' ], 99, 2 );
    }

    private function check_dependencies() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $required = [
            'woocommerce/woocommerce.php'                   => 'WooCommerce',
            'woocommerce-subscriptions/woocommerce-subscriptions.php' => 'WooCommerce Subscriptions',
            'latepoint/latepoint.php'                       => 'LatePoint',
        ];
        foreach ( $required as $file => $name ) {
            if ( ! is_plugin_active( $file ) ) {
                if ( WP_DEBUG ) {
                    error_log( "[DarsnaTutorCheckout] Missing plugin: {$name}" );
                }
                return false;
            }
        }
        return true;
    }

    public function dependency_notice() {
        printf(
            '<div class="error"><p>%s</p></div>',
            esc_html__( 'Tutor Registration requires WooCommerce, WooCommerce Subscriptions & LatePoint. The plugin is partially disabled until they’re active.', 'darsna-tutor-registration' )
        );
    }

    public function force_cod_orders_on_hold( $status, $order ) {
        if ( WP_DEBUG ) {
            error_log( "[DarsnaTutorCheckout] COD order #{$order->get_id()} forced to on-hold" );
        }
        return 'on-hold';
    }

    public function check_cod_subscription_activation( $subscription ) {
        $parent = wc_get_order( $subscription->get_parent_id() );
        if ( $parent && $parent->get_payment_method() === 'cod' ) {
            if ( WP_DEBUG ) {
                error_log( "[DarsnaTutorCheckout] COD subscription #{$subscription->get_id()} re-held" );
            }
            $subscription->update_status( 'on-hold', 'Awaiting manual review for COD order.' );
        }
    }

    public function handle_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! function_exists( 'wcs_order_contains_subscription' ) ) {
            return;
        }
        if ( wcs_order_contains_subscription( $order ) ) {
            foreach ( wcs_get_subscriptions_for_order( $order ) as $subscription ) {
                // activate if not active yet
                if ( $subscription->get_status() !== 'active' ) {
                    $subscription->update_status( 'active', 'Activated after manual order completion.' );
                }
                $this->activate_subscription( $subscription, $subscription->get_user_id() );
            }
        }
    }

    public function handle_subscription_status( $subscription, $new_status, $old_status ) {
        if ( $new_status === $old_status ) {
            return;
        }
        $user_id = $subscription->get_user_id();
        if ( ! $user_id ) {
            if ( WP_DEBUG ) {
                error_log( '[DarsnaTutorCheckout] Subscription status change but no user ID.' );
            }
            return;
        }
        if ( WP_DEBUG ) {
            error_log( "[DarsnaTutorCheckout] Sub #{$subscription->get_id()} for user {$user_id}: {$old_status} → {$new_status}" );
        }
        // When it goes active and parent order completed → activate agent
        if ( $new_status === 'active' ) {
            $parent = wc_get_order( $subscription->get_parent_id() );
            if ( $parent && $parent->get_status() === 'completed' ) {
                $this->activate_subscription( $subscription, $user_id );
            } else {
                // keep it held until manual
                $subscription->update_status( 'on-hold', 'Pending manual approval.' );
            }
        }
        // Any other non-active state → deactivate agent
        elseif ( in_array( $new_status, [ 'pending', 'on-hold', 'cancelled', 'expired', 'suspended', 'trash' ], true ) ) {
            $this->deactivate_subscription( $user_id );
        }
    }

    private function activate_subscription( $subscription, $user_id ) {
        if ( WP_DEBUG ) {
            error_log( "[DarsnaTutorCheckout] Activating user {$user_id} as agent." );
        }
        update_user_meta( $user_id, '_darsna_account_type',       'tutor' );
        update_user_meta( $user_id, '_darsna_subscription_active', 'yes' );
        $this->assign_latepoint_agent_role( $user_id );
        $this->create_latepoint_agent( $user_id );
    }

    private function deactivate_subscription( $user_id ) {
        if ( WP_DEBUG ) {
            error_log( "[DarsnaTutorCheckout] Deactivating user {$user_id} as agent." );
        }
        $this->remove_latepoint_agent( $user_id );
        $user = new WP_User( $user_id );
        $user->set_role( 'customer' );
        update_user_meta( $user_id, '_darsna_subscription_active', 'no' );
    }

    private function assign_latepoint_agent_role( $user_id ) {
        $user = new WP_User( $user_id );
        $old = $user->roles;
        $user->set_role( 'latepoint_agent' );
        if ( WP_DEBUG ) {
            $new = ( new WP_User( $user_id ) )->roles;
            error_log( "[DarsnaTutorCheckout] User {$user_id} roles: " . implode( ',', $old ) . " → " . implode( ',', $new ) );
        }
    }

    private function format_phone_for_latepoint( $phone, $country ) {
        $has_plus = strpos( trim( $phone ), '+' ) === 0;
        $digits   = preg_replace( '/\D+/', '', $phone );
        $code     = WC()->countries->get_country_calling_code( $country );
        if ( $has_plus ) {
            return '+' . $digits;
        }
        // strip leading code if duplicated
        $digits = ltrim( $digits, ltrim( $code, '+' ) );
        return '+' . $code . $digits;
    }

    private function create_latepoint_agent( $user_id ) {
        if ( ! class_exists( '\OsAgentModel' ) ) {
            if ( WP_DEBUG ) error_log( '[DarsnaTutorCheckout] OsAgentModel not found.' );
            return;
        }
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $model   = new \OsAgentModel();
        $exists  = $model->where( [ 'wp_user_id' => $user_id ] )->get_results();
        $status  = 'active';
        $order_id = current( wc_get_orders( [
            'customer' => $user_id,
            'limit'    => 1,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'return'   => 'ids',
        ] ) );

        $phone   = '';
        $first   = $user->first_name;
        $last    = $user->last_name;

        if ( $order_id ) {
            $o = wc_get_order( $order_id );
            if ( $o ) {
                $phone  = $this->format_phone_for_latepoint( $o->get_billing_phone(), $o->get_billing_country() );
                $first  = $first ?: $o->get_billing_first_name();
                $last   = $last  ?: $o->get_billing_last_name();
            }
        }

        if ( empty( $first ) || empty( $last ) ) {
            list( $first, $last ) = array_pad( explode( ' ', $user->display_name, 2 ), 2, '' );
        }

        $data = [
            'first_name'   => $first,
            'last_name'    => $last,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'phone'        => $phone,
            'wp_user_id'   => $user_id,
            'status'       => $status,
        ];

        // update or create
        if ( $exists ) {
            $model->update_where( [ 'wp_user_id' => $user_id ], [ 'status' => $status ] );
        } else {
            foreach ( $data as $k => $v ) {
                $model->set_data( $k, $v );
            }
            $model->save();
        }

        if ( WP_DEBUG ) {
            error_log( "[DarsnaTutorCheckout] LatePoint agent synced for user {$user_id}." );
        }
    }

    public function remove_latepoint_agent( $user_id ) {
        if ( ! class_exists( '\OsAgentModel' ) ) {
            if ( WP_DEBUG ) error_log( '[DarsnaTutorCheckout] OsAgentModel not found.' );
            return;
        }
        $model  = new \OsAgentModel();
        $exists = $model->where( [ 'wp_user_id' => $user_id ] )->get_results();
        if ( $exists ) {
            $model->update_where( [ 'wp_user_id' => $user_id ], [ 'status' => 'disabled' ] );
            if ( WP_DEBUG ) error_log( "[DarsnaTutorCheckout] Disabled LatePoint agent for user {$user_id}." );
        }
    }

    public function maybe_remove_agent_on_role_change( $user_id, $role ) {
        if ( $role === 'latepoint_agent' ) {
            $this->remove_latepoint_agent( $user_id );
        }
    }

    public function enqueue_phone_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_script( 'iti-js',
                'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js',
                [ 'jquery' ], null, true
            );
            wp_enqueue_style( 'iti-css',
                'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css'
            );
            wp_add_inline_script( 'iti-js', "
                jQuery(function($){
                    var input = document.querySelector('#billing_phone');
                    window.intlTelInput(input, {
                        utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js',
                        initialCountry: 'auto',
                        geoIpLookup: function(cb){
                            $.get('https://ipinfo.io','jsonp').always(function(r){
                                cb((r&&r.country)||'us');
                            });
                        },
                    });
                });
            " );
        }
    }

    public function validate_phone_number( $data, $errors ) {
        // very loose: only digits, +, spaces, dashes, parentheses allowed
        if ( ! preg_match( '/^[0-9+\-\s\(\)]+$/', $data['billing_phone'] ?? '' ) ) {
            $errors->add( 'validation',
                __( 'Please enter a valid phone number (digits, +, spaces, dashes, parentheses only).' )
            );
        }
    }

    public function add_dashboard_logout_menu_links( $items, $args ) {
        if ( ! is_user_logged_in() || ( isset( $args->theme_location ) && $args->theme_location !== 'primary-menu' ) ) {
            return $items;
        }
        $user_roles = (array) wp_get_current_user()->roles;
        $url        = in_array( 'latepoint_agent', $user_roles )
            ? admin_url( 'admin.php?page=latepoint' )
            : wc_get_page_permalink( 'myaccount' );

        $items .= sprintf(
            '<li class="menu-item"><a href="%1$s">%2$s</a></li>',
            esc_url( $url ),
            esc_html__( 'Dashboard', 'darsna-tutor-registration' )
        );
        $items .= sprintf(
            '<li class="menu-item"><a href="%1$s">%2$s</a></li>',
            esc_url( wp_logout_url( home_url() ) ),
            esc_html__( 'Logout', 'darsna-tutor-registration' )
        );
        return $items;
    }
}

new Darsna_Tutor_Checkout();

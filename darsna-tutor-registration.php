<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.1.0
 * Description: Tutor-only checkout with manual order approval for LatePoint agents
 * Requires PHP: 7.2
 * Author: Darsna
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DarsnaTutorRegistration
 */

if (!defined('ABSPATH')) exit;

define('DARSNA_TUTOR_REG_VERSION', '3.1.0');
define('DARSNA_TUTOR_REG_PLUGIN_DIR', plugin_dir_path(__FILE__));

class Darsna_Tutor_Checkout {
    public function __construct() {
        // Core initialization
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']);
        add_action('woocommerce_subscription_status_updated', [$this, 'handle_subscription_status'], 10, 3);
        
        // Phone number handling
        add_action('woocommerce_after_checkout_validation', [$this, 'validate_phone_format'], 10, 2);
        add_filter('woocommerce_billing_fields', [$this, 'modify_phone_field']);
    }

    private function format_phone_for_latepoint($phone, $country) {
        // Strip non-digit characters except leading +
        $has_plus = strpos(trim($phone), '+') === 0;
        $digits = preg_replace('/[^\d]/', '', $phone);

        // Get country code from WooCommerce
        if ($country && function_exists('WC')) {
            $calling_code = ltrim(WC()->countries->get_country_calling_code($country), '+');
            
            // Remove country code if already present
            if (strpos($digits, $calling_code) === 0) {
                $digits = substr($digits, strlen($calling_code));
            }
            
            return '+' . $calling_code . $digits;
        }
        
        return $has_plus ? '+' . $digits : '+' . $digits;
    }

    public function validate_phone_format($data, $errors) {
        $phone = $data['billing_phone'] ?? '';
        
        if (!preg_match('/^[\d+\-\(\)\s]{6,20}$/', $phone)) {
            $errors->add('validation', 'Please enter a valid phone number with digits, spaces, +, -, or parentheses');
        }
    }

    public function modify_phone_field($fields) {
        $fields['billing_phone']['placeholder'] = 'Example: (962) 79 123 4567 or +44 20 7123 4567';
        return $fields;
    }

    private function create_latepoint_agent($user_id) {
        if (!class_exists('\OsAgentModel')) {
            if (WP_DEBUG) {
                error_log('[DarsnaTutorCheckout] OsAgentModel class not found');
            }
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            if (WP_DEBUG) {
                error_log(sprintf('[DarsnaTutorCheckout] User not found for ID: %d', $user_id));
            }
            return;
        }
        
        $agent_model = new \OsAgentModel();
        $existing = $agent_model->where(array('wp_user_id' => $user_id))->get_results();
        
        if (!empty($existing)) {
            error_log('Agent already exists for user ' . $user_id . ', updating status');
            $agent_model->update_where(
                array('wp_user_id' => $user_id),
                array('status' => 'active') // Using 'active' instead of LATEPOINT_AGENT_STATUS_APPROVED constant
            );
            return;
        }
        
        $customer_orders = wc_get_orders(array(
            'customer' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        ));
        
        $phone = '';
        $first_name = $user->first_name;
        $last_name = $user->last_name;
        
        if (!empty($customer_orders)) {
            $order = wc_get_order($customer_orders[0]);
            $formatted_phone = $this->format_phone_for_latepoint(
                $order->get_billing_phone(),
                $order->get_billing_country()
            );
            if ($order) {
                $raw_phone = $order->get_billing_phone();
                $billing_country = $order->get_billing_country();
                $phone = $this->format_phone_for_latepoint($raw_phone, $billing_country);
                if (empty($first_name)) $first_name = $order->get_billing_first_name();
                if (empty($last_name)) $last_name = $order->get_billing_last_name();
            }
        }
        
        if (empty($first_name) || empty($last_name)) {
            $name_parts = explode(' ', $user->display_name, 2);
            if (empty($first_name)) $first_name = $name_parts[0];
            if (empty($last_name)) $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
        }
        
        $agent_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'phone' => $phone,
            'wp_user_id' => $user_id,
            'status' => 'active', 
        );
        
        error_log('Creating LatePoint agent with data: ' . print_r($agent_data, true));
        
        foreach ($agent_data as $key => $value) {
            $agent_model->set_data($key, $value);
        }
        
        $result = $agent_model->save();
        
        if ($result) {
            error_log('Successfully created LatePoint agent for user ' . $user_id);
        } else {
            error_log('Failed to create LatePoint agent for user ' . $user_id);
            error_log('LatePoint errors: ' . print_r($agent_model->get_error_messages(), true));
        }
    }

    private function remove_latepoint_agent($user_id) {
        if (!class_exists('\OsAgentModel')) {
            if (WP_DEBUG) {
                error_log('[DarsnaTutorCheckout] OsAgentModel class not found');
            }
            return;
        }
        
        $agent_model = new \OsAgentModel();
        $existing = $agent_model->where(array('wp_user_id' => $user_id))->get_results();
        
        if (!empty($existing)) {
            if (WP_DEBUG) {
                error_log(sprintf('[DarsnaTutorCheckout] Disabling LatePoint agent for user %d', $user_id));
            }
            
            $agent_model->update_where(
                array('wp_user_id' => $user_id),
                array('status' => 'disabled')
            );
        } else {
            if (WP_DEBUG) {
                error_log(sprintf('[DarsnaTutorCheckout] No LatePoint agent found for user %d', $user_id));
            }
        }
    }
    

    public function add_dashboard_logout_menu_links($items, $args) {
        if (!is_user_logged_in()) return $items;
        
        if (isset($args->theme_location) && $args->theme_location !== 'primary-menu') {
            return $items;
        }
        
        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;
        
        $new_items = '';
        
        $is_agent = in_array('latepoint_agent', $user_roles);
        
        if ($is_agent) {
            $dashboard_url = admin_url('admin.php?page=latepoint');
        } else {
            $dashboard_url = wc_get_page_permalink('myaccount');
        }
        
        $new_items .= '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="' . esc_url($dashboard_url) . '">Dashboard</a></li>';
        $new_items .= '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="' . esc_url(wp_logout_url(home_url())) . '">Logout</a></li>';
        
        return $items . $new_items;
    }
}

new Darsna_Tutor_Checkout();
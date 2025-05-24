<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 2.1.0
 * Description: Checkout-based tutor registration with subscription activation
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

define('DARSNA_TUTOR_REG_VERSION', '2.1.0');
define('DARSNA_TUTOR_REG_PLUGIN_DIR', plugin_dir_path(__FILE__));

class Darsna_Tutor_Checkout {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        if (!$this->check_dependencies()) return;
        
        add_filter('woocommerce_checkout_fields', array($this, 'add_checkout_fields'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_fields'));
        add_action('woocommerce_subscription_status_updated', array($this, 'handle_subscription_status'), 10, 3);
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completed'), 10, 1);
        add_filter('wp_nav_menu_items', array($this, 'add_dashboard_logout_menu_links'), 99, 2);
        add_action('admin_notices', array($this, 'dependency_notice'));
    }
    
    private function check_dependencies() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('woocommerce/woocommerce.php') && 
               is_plugin_active('latepoint/latepoint.php') &&
               is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php');
    }
    
    public function dependency_notice() {
        if (!$this->check_dependencies()) {
            echo '<div class="error"><p>Tutor Registration requires WooCommerce, WooCommerce Subscriptions, and LatePoint plugins to be active.</p></div>';
        }
    }
    
    public function add_checkout_fields($fields) {
        if (!$this->has_subscription_product()) return $fields;
        
        $fields['billing']['account_type'] = array(
            'type' => 'select',
            'label' => 'Account Type',
            'required' => true,
            'options' => array(
                'student' => 'Student',
                'tutor' => 'Tutor'
            ),
            'priority' => 25
        );
        
        return $fields;
    }
    
    public function validate_checkout_fields() {
        if (!$this->has_subscription_product()) return;
        
        if (empty($_POST['account_type']) || !in_array($_POST['account_type'], array('student', 'tutor'))) {
            wc_add_notice('Please select a valid account type.', 'error');
        }
    }
    
    public function save_checkout_fields($order_id) {
        if (!$this->has_subscription_product()) return;
        
        if (!empty($_POST['account_type'])) {
            update_post_meta($order_id, '_account_type', sanitize_text_field($_POST['account_type']));
        }
    }
    
    public function handle_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Check if order contains subscriptions
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            $subscriptions = wcs_get_subscriptions_for_order($order);
            foreach ($subscriptions as $subscription) {
                if ($subscription->get_status() === 'active') {
                    $this->activate_subscription($subscription, $subscription->get_user_id());
                }
            }
        }
    }
    
    public function handle_subscription_status($subscription, $new_status, $old_status) {
        $user_id = $subscription->get_user_id();
        if (!$user_id) return;
        
        // Only activate if the parent order is completed
        if ($new_status === 'active') {
            $parent_order = wc_get_order($subscription->get_parent_id());
            if ($parent_order && $parent_order->get_status() === 'completed') {
                $this->activate_subscription($subscription, $user_id);
            }
        } elseif (in_array($new_status, array('expired', 'cancelled', 'pending-cancel', 'on-hold'))) {
            $this->deactivate_subscription($user_id);
        }
    }
    
    private function activate_subscription($subscription, $user_id) {
        $parent_order_id = $subscription->get_parent_id();
        $account_type = get_post_meta($parent_order_id, '_account_type', true);
        
        if (!$account_type) {
            $account_type = get_user_meta($user_id, '_darsna_account_type', true);
        }
        
        if (!$account_type) return;
        
        update_user_meta($user_id, '_darsna_account_type', $account_type);
        update_user_meta($user_id, '_darsna_subscription_active', 'yes');
        
        if ($account_type === 'student') {
            $this->create_student_role($user_id);
        } elseif ($account_type === 'tutor') {
            $this->assign_latepoint_agent_role($user_id);
            $this->create_latepoint_agent($user_id);
        }
    }
    
    private function deactivate_subscription($user_id) {
        $account_type = get_user_meta($user_id, '_darsna_account_type', true);
        
        if ($account_type === 'tutor') {
            $this->remove_latepoint_agent($user_id);
        }
        
        $user = new WP_User($user_id);
        $user->set_role('customer');
        update_user_meta($user_id, '_darsna_subscription_active', 'no');
    }
    
    private function create_student_role($user_id) {
        if (!get_role('student')) {
            add_role('student', 'Student', array('read' => true));
        }
        $user = new WP_User($user_id);
        $user->set_role('student');
    }
    
    private function assign_latepoint_agent_role($user_id) {
        // Use LatePoint's built-in agent role
        $user = new WP_User($user_id);
        $user->set_role('LatePoint Agent');
    }
    
    private function format_phone_for_latepoint($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If phone starts with country code, keep it
        // Otherwise, you might want to add a default country code
        // This example assumes Jordan (+962) as default if no country code
        if (strlen($phone) === 9 && substr($phone, 0, 1) !== '0') {
            $phone = '962' . $phone;
        } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            $phone = '962' . substr($phone, 1);
        }
        
        return $phone;
    }
    
    private function create_latepoint_agent($user_id) {
        if (!class_exists('LatePointAgentModel')) return;
        
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $agent_model = new LatePointAgentModel();
        $existing = $agent_model->where(array('wp_user_id' => $user_id))->get_results();
        
        if (!empty($existing)) {
            $agent_model->update_where(
                array('wp_user_id' => $user_id),
                array('status' => LATEPOINT_AGENT_STATUS_APPROVED)
            );
            return;
        }
        
        // Get parent order to fetch billing details
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
            if ($order) {
                $phone = $this->format_phone_for_latepoint($order->get_billing_phone());
                if (empty($first_name)) $first_name = $order->get_billing_first_name();
                if (empty($last_name)) $last_name = $order->get_billing_last_name();
            }
        }
        
        // Fallback for names
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
            'status' => LATEPOINT_AGENT_STATUS_APPROVED,
        );
        
        $agent_model->create($agent_data);
    }
    
    private function remove_latepoint_agent($user_id) {
        if (!class_exists('LatePointAgentModel')) return;
        
        $agent_model = new LatePointAgentModel();
        $existing = $agent_model->where(array('wp_user_id' => $user_id))->get_results();
        
        if (!empty($existing)) {
            $agent_model->update_where(
                array('wp_user_id' => $user_id),
                array('status' => 'disabled')
            );
        }
    }
    
    private function has_subscription_product() {
        if (!WC()->cart) return false;
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product && ($product->get_type() === 'subscription' || $product->get_type() === 'variable-subscription')) {
                return true;
            }
        }
        return false;
    }
    
    public function add_dashboard_logout_menu_links($items, $args) {
        if (!is_user_logged_in()) return $items;
        
        if (isset($args->theme_location) && $args->theme_location !== 'primary-menu') {
            return $items;
        }
        
        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;
        
        $new_items = '';
        
        if (in_array('LatePoint Agent', $user_roles)) {
            $dashboard_url = admin_url('admin.php?page=latepoint');
        } else {
            $dashboard_url = site_url('/student-dashboard/');
        }
        
        $new_items .= '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="' . esc_url($dashboard_url) . '">Dashboard</a></li>';
        $new_items .= '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="' . esc_url(wp_logout_url(home_url())) . '">Logout</a></li>';
        
        return $items . $new_items;
    }
}

new Darsna_Tutor_Checkout();
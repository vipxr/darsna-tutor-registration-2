<?php
/**
 * Plugin Name: Tutor Registration for WooCommerce & LatePoint - Checkout
 * Version: 3.1.0
 * Description: Tutor-only checkout with manual order approval for LatePoint agents
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) exit;

define('DARSNA_TUTOR_REG_VERSION', '3.1.0');
define('DARSNA_TUTOR_REG_PLUGIN_DIR', plugin_dir_path(__FILE__));

class Darsna_Tutor_Checkout {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('darsna_hold_subscription', array($this, 'handle_scheduled_hold'), 10, 1);
    }
    
    public function init() {
        if (!$this->check_dependencies()) return;
        
        // Handle new subscriptions - put them on hold immediately after creation
        add_action('woocommerce_subscription_payment_complete', array($this, 'hold_new_subscription'), 10, 1);
        add_action('woocommerce_thankyou', array($this, 'hold_subscription_after_checkout'), 10, 1);
        
        // Handle subscription status changes
        add_action('woocommerce_subscription_status_updated', array($this, 'handle_subscription_status'), 10, 3);
        
        // Activate subscription only when order is manually completed
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
    
    public function hold_new_subscription($subscription) {
        // Immediately put subscription on hold after payment
        if ($subscription && is_a($subscription, 'WC_Subscription')) {
            $parent_order = wc_get_order($subscription->get_parent_id());
            
            // Only hold if parent order is not completed
            if ($parent_order && $parent_order->get_status() !== 'completed') {
                // Add a small delay to ensure the subscription is fully created
                wp_schedule_single_event(time() + 2, 'darsna_hold_subscription', array($subscription->get_id()));
            }
        }
    }
    
    public function hold_subscription_after_checkout($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Check if order contains subscriptions
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            $subscriptions = wcs_get_subscriptions_for_order($order);
            foreach ($subscriptions as $subscription) {
                if ($subscription->get_status() === 'active') {
                    $subscription->update_status('on-hold', 'Subscription on hold pending manual approval.');
                    
                    // Ensure user doesn't have agent role
                    $user_id = $subscription->get_user_id();
                    if ($user_id) {
                        $user = new WP_User($user_id);
                        if (in_array('latepoint_agent', $user->roles)) {
                            $user->set_role('customer');
                        }
                    }
                }
            }
            
            // Add order note
            $order->add_order_note('Tutor subscription created - pending manual approval. Complete this order to activate subscription and create LatePoint agent.');
        }
    }
    
    public function handle_scheduled_hold($subscription_id) {
        $subscription = wcs_get_subscription($subscription_id);
        if ($subscription && $subscription->get_status() === 'active') {
            $parent_order = wc_get_order($subscription->get_parent_id());
            if ($parent_order && $parent_order->get_status() !== 'completed') {
                $subscription->update_status('on-hold', 'Subscription on hold pending manual approval.');
            }
        }
    }
    
    public function handle_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Check if order contains subscriptions
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            $subscriptions = wcs_get_subscriptions_for_order($order);
            foreach ($subscriptions as $subscription) {
                $user_id = $subscription->get_user_id();
                
                // Activate the subscription when order is completed
                if ($subscription->get_status() !== 'active') {
                    $subscription->update_status('active', 'Subscription activated after manual order completion.');
                }
                
                // Directly activate the user as LatePoint Agent
                if ($user_id) {
                    $this->activate_subscription($subscription, $user_id);
                }
            }
        }
    }
    
    public function handle_subscription_status($subscription, $new_status, $old_status) {
        $user_id = $subscription->get_user_id();
        if (!$user_id) return;
        
        // Only process status changes, not initial status
        if ($old_status === $new_status) return;
        
        // Activate when subscription becomes active (only if parent order is completed)
        if ($new_status === 'active') {
            $parent_order = wc_get_order($subscription->get_parent_id());
            if ($parent_order && $parent_order->get_status() === 'completed') {
                $this->activate_subscription($subscription, $user_id);
            } else {
                // If order not completed, put subscription back on hold
                wp_schedule_single_event(time() + 1, 'darsna_hold_subscription', array($subscription->get_id()));
            }
        } 
        // Deactivate for any non-active status
        elseif (in_array($new_status, array('expired', 'cancelled', 'pending-cancel', 'on-hold', 'suspended', 'pending', 'trash'))) {
            $this->deactivate_subscription($user_id);
        }
    }
    
    private function activate_subscription($subscription, $user_id) {
        error_log('Activating subscription for user ' . $user_id);
        
        // All subscriptions are for tutors
        update_user_meta($user_id, '_darsna_account_type', 'tutor');
        update_user_meta($user_id, '_darsna_subscription_active', 'yes');
        
        $this->assign_latepoint_agent_role($user_id);
        $this->create_latepoint_agent($user_id);
        
        error_log('Subscription activation completed for user ' . $user_id);
    }
    
    private function deactivate_subscription($user_id) {
        error_log('Deactivating subscription for user ' . $user_id);
        
        $this->remove_latepoint_agent($user_id);
        
        $user = new WP_User($user_id);
        $user->set_role('customer');
        update_user_meta($user_id, '_darsna_subscription_active', 'no');
    }
    
    private function assign_latepoint_agent_role($user_id) {
        error_log('Assigning LatePoint Agent role to user ' . $user_id);
        
        $user = new WP_User($user_id);
        $user->set_role('latepoint_agent');
        
        error_log('Assigned latepoint_agent role to user ' . $user_id);
        
        // Verify role was assigned
        $user = new WP_User($user_id);
        $roles = $user->roles;
        error_log('User ' . $user_id . ' roles after assignment: ' . print_r($roles, true));
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
        if (!class_exists('\OsAgentModel')) {
            error_log('[DarsnaTutorCheckout] OsAgentModel class not found');
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            error_log('User not found for ID: ' . $user_id);
            return;
        }
        
        $agent_model = new \OsAgentModel();
        $existing = $agent_model->where(array('wp_user_id' => $user_id))->get_results();
        
        if (!empty($existing)) {
            error_log('Agent already exists for user ' . $user_id . ', updating status');
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
        
        error_log('Creating LatePoint agent with data: ' . print_r($agent_data, true));
        
        // Set the data on the model
        foreach ($agent_data as $key => $value) {
            $agent_model->set_data($key, $value);
        }
        
        // Save the agent
        $result = $agent_model->save();
        
        if ($result) {
            error_log('Successfully created LatePoint agent for user ' . $user_id);
        } else {
            error_log('Failed to create LatePoint agent for user ' . $user_id);
            error_log('LatePoint errors: ' . print_r($agent_model->get_error_messages(), true));
        }
    }
    
    private function remove_latepoint_agent($user_id) {
        if (!class_exists('\OsAgentModel')) return;
        
        $agent_model = new \OsAgentModel();
        $existing = $agent_model->where(array('wp_user_id' => $user_id))->get_results();
        
        if (!empty($existing)) {
            $agent_model->update_where(
                array('wp_user_id' => $user_id),
                array('status' => 'disabled')
            );
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
        
        // Check for LatePoint agent role
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

// Initialize the plugin
new Darsna_Tutor_Checkout();
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

/**
 * Main plugin class handling tutor registration and LatePoint agent management.
 *
 * @since 3.1.0
 */
class Darsna_Tutor_Checkout {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize plugin hooks and filters.
     *
     * @since 3.1.0
     * @return void
     */
    public function init() {
        if (!$this->check_dependencies()) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            return;
        }
        
        // Handle subscription status changes - this is our main entry point
        add_action('woocommerce_subscription_status_updated', array($this, 'handle_subscription_status'), 10, 3);
        
        // Activate subscription only when order is manually completed
        add_action('woocommerce_order_status_completed', array($this, 'handle_order_completed'), 10, 1);
        
        // Force COD orders to start as on-hold
        add_filter('woocommerce_cod_process_payment_order_status', array($this, 'force_cod_orders_on_hold'), 10, 2);
        
        // Extra safeguard for COD subscriptions
        add_action('woocommerce_subscription_payment_complete', array($this, 'check_cod_subscription_activation'), 5, 1);
        
        add_filter('wp_nav_menu_items', array($this, 'add_dashboard_logout_menu_links'), 99, 2);
    }
    
    /**
     * Check if all required plugins are active.
     *
     * @since 3.1.0
     * @return bool True if all dependencies are met, false otherwise.
     */
    private function check_dependencies() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $required_plugins = array(
            'woocommerce/woocommerce.php' => 'WooCommerce',
            'latepoint/latepoint.php' => 'LatePoint',
            'woocommerce-subscriptions/woocommerce-subscriptions.php' => 'WooCommerce Subscriptions'
        );
        
        foreach ($required_plugins as $plugin => $name) {
            if (!is_plugin_active($plugin)) {
                if (WP_DEBUG) {
                    error_log(sprintf('[DarsnaTutorCheckout] Required plugin %s is not active', $name));
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Display admin notice for missing dependencies.
     *
     * @since 3.1.0
     * @return void
     */
    public function dependency_notice() {
        if (!$this->check_dependencies()) {
            $message = __('Tutor Registration requires WooCommerce, WooCommerce Subscriptions, and LatePoint plugins to be active. Some features may not work correctly.', 'darsna-tutor-registration');
            printf('<div class="error"><p>%s</p></div>', esc_html($message));
        }
    }
    
    /**
     * Force all COD orders to start as on-hold instead of processing or completed.
     *
     * @since 3.1.0
     * @param string $status The default order status.
     * @param WC_Order $order The order object.
     * @return string Modified order status.
     */
    public function force_cod_orders_on_hold($status, $order) {
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Setting COD order %d status to on-hold', $order->get_id()));
        }
        return 'on-hold';
    }

    /**
     * Extra safeguard to ensure COD subscriptions stay on-hold until manual review.
     *
     * @since 3.1.0
     * @param WC_Subscription $subscription The subscription object.
     * @return void
     */
    public function check_cod_subscription_activation($subscription) {
        $parent = wc_get_order($subscription->get_parent_id());
        if ($parent && $parent->get_payment_method() === 'cod') {
            if (WP_DEBUG) {
                error_log(sprintf('[DarsnaTutorCheckout] Enforcing on-hold status for COD subscription %d', $subscription->get_id()));
            }
            $subscription->update_status('on-hold', 'Awaiting manual review for COD order.');
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
    
    /**
     * Handle subscription status changes.
     * This is our main entry point for managing tutor status.
     *
     * @since 3.1.0
     * @param WC_Subscription $subscription The subscription object.
     * @param string         $new_status   The new subscription status.
     * @param string         $old_status   The old subscription status.
     * @return void
     */
    public function handle_subscription_status($subscription, $new_status, $old_status) {
        $user_id = $subscription->get_user_id();
        if (!$user_id) {
            if (WP_DEBUG) {
                error_log('[DarsnaTutorCheckout] No user ID found for subscription');
            }
            return;
        }
        
        // Only process status changes, not initial status
        if ($old_status === $new_status) return;
        
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Processing subscription status change for user %d: %s -> %s',
                $user_id, $old_status, $new_status));
        }
        
        // Activate when subscription becomes active (only if parent order is completed)
        if ($new_status === 'active') {
            $parent_order = wc_get_order($subscription->get_parent_id());
            if ($parent_order && $parent_order->get_status() === 'completed') {
                $this->activate_subscription($subscription, $user_id);
            } else {
                $subscription->update_status('on-hold', 'Subscription on hold pending manual approval.');
            }
        } 
        // Deactivate for any non-active status
        elseif (in_array($new_status, array('expired', 'cancelled', 'pending-cancel', 'on-hold', 'suspended', 'pending', 'trash'))) {
            $this->deactivate_subscription($user_id);
        }
    }
    
    /**
     * Activate a subscription and set up the user as a tutor.
     *
     * @since 3.1.0
     * @param WC_Subscription $subscription The subscription object.
     * @param int            $user_id      The user ID.
     * @return void
     */
    private function activate_subscription($subscription, $user_id) {
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Activating subscription for user %d', $user_id));
        }
        
        // All subscriptions are for tutors
        update_user_meta($user_id, '_darsna_account_type', 'tutor');
        update_user_meta($user_id, '_darsna_subscription_active', 'yes');
        
        $this->assign_latepoint_agent_role($user_id);
        $this->create_latepoint_agent($user_id);
        
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Subscription activation completed for user %d', $user_id));
        }
    }
    
    /**
     * Deactivate a subscription and remove tutor privileges.
     *
     * @since 3.1.0
     * @param int $user_id The user ID.
     * @return void
     */
    private function deactivate_subscription($user_id) {
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Deactivating subscription for user %d', $user_id));
        }
        
        $this->remove_latepoint_agent($user_id);
        
        $user = new WP_User($user_id);
        $user->set_role('customer');
        update_user_meta($user_id, '_darsna_subscription_active', 'no');
        
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Subscription deactivation completed for user %d', $user_id));
        }
    }
    
    /**
     * Assign the LatePoint agent role to a user.
     *
     * @since 3.1.0
     * @param int $user_id The user ID.
     * @return void
     */
    private function assign_latepoint_agent_role($user_id) {
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] Assigning LatePoint Agent role to user %d', $user_id));
        }
        
        $user = new WP_User($user_id);
        $old_roles = $user->roles;
        $user->set_role('latepoint_agent');
        
        // Verify role was assigned
        $user = new WP_User($user_id);
        $new_roles = $user->roles;
        
        if (WP_DEBUG) {
            error_log(sprintf('[DarsnaTutorCheckout] User %d roles changed from %s to %s',
                $user_id,
                implode(', ', $old_roles),
                implode(', ', $new_roles)
            ));
        }
    }
    
    /**
     * Format phone number for LatePoint compatibility.
     * Assumes Jordanian phone numbers (+962) as default.
     *
     * @since 3.1.0
     * @param string $phone Raw phone number from order.
     * @return string Formatted phone number.
     */
    private function format_phone_for_latepoint($phone) {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Validate Jordanian phone number format
        $jordan_mobile_pattern = '/^(?:(?:7|9)\d{8}|0(?:7|9)\d{8}|962(?:7|9)\d{8})$/'; 
        
        if (preg_match($jordan_mobile_pattern, $phone)) {
            // If starts with 0, remove it and add 962
            if (substr($phone, 0, 1) === '0') {
                $phone = '962' . substr($phone, 1);
            }
            // If doesn't start with 962, add it
            elseif (substr($phone, 0, 3) !== '962') {
                $phone = '962' . $phone;
            }
        } else {
            if (WP_DEBUG) {
                error_log(sprintf('[DarsnaTutorCheckout] Invalid phone number format: %s', $phone));
            }
        }
        
        return $phone;
    }
    
    /**
     * Create or update a LatePoint agent for a user.
     *
     * @since 3.1.0
     * @param int $user_id The user ID.
     * @return void
     */
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
    
    /**
     * Remove LatePoint agent status from a user.
     *
     * @since 3.1.0
     * @param int $user_id The user ID.
     * @return void
     */
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
    
    /**
     * Add dashboard and logout links to the primary menu for logged-in users.
     *
     * @since 3.1.0
     * @param string    $items The menu items.
     * @param stdClass $args  The menu arguments.
     * @return string Modified menu items.
     */
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
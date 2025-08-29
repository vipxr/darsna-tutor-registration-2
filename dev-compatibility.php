<?php
/**
 * Development Compatibility Stubs
 * 
 * This file provides compatibility stubs for WordPress and LatePoint functions
 * that may not be available during development or testing.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// WordPress function stubs
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Development stub
    }
}

if (!function_exists('wp_update_user')) {
    function wp_update_user($userdata) {
        return true; // Mock success
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return (object) [
            'ID' => $user_id,
            'display_name' => 'Mock User',
            'user_email' => 'mock@example.com'
        ];
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        return $single ? '' : array();
    }
}

// LatePoint Agent Helper stub
if (!class_exists('OsAgentHelper')) {
    class OsAgentHelper {
        public static function get_agents($conditions = array()) {
            return array(); // Mock empty results
        }
        
        public static function get_agent_by_user_id($user_id) {
            return null; // Mock no agent found
        }
        
        public static function get_logged_in_agent_id() {
            return 1; // Mock agent ID
        }
        
        public static function create_agent($agent_data = null) {
            return new OsAgentModel();
        }
        
        public static function assign_service_to_agent($agent_id, $service_id) {
            return true;
        }
        
        public static function get_agent_services($agent_id) {
            return array(); // Mock empty services
        }
        
        public static function get_agent_service_price($agent_id, $service_id) {
            return 50.00; // Mock price
        }
    }
}

// LatePoint Service Helper stub
if (!class_exists('OsServiceHelper')) {
    class OsServiceHelper {
        public static function get_services($conditions = array()) {
            return array(); // Mock empty services
        }
        
        public static function get_service_by_name($name) {
            return (object) [
                'id' => 1,
                'name' => $name,
                'description' => 'Mock service description'
            ];
        }
        
        public static function assign_service_to_agent($agent_id, $service_id) {
            return true;
        }
    }
}

// LatePoint Pricing Helper stub
if (!class_exists('OsPricingHelper')) {
    class OsPricingHelper {
        public static function get_agent_service_price($agent_id, $service_id) {
            return 50.00; // Mock price
        }
        
        public static function set_agent_service_price($agent_id, $service_id, $price) {
            return true; // Mock success
        }
    }
}

// LatePoint Auth Helper stub
if (!class_exists('OsAuthHelper')) {
    class OsAuthHelper {
        public static function get_logged_in_agent_id() {
            return 1; // Mock agent ID
        }
    }
}

// LatePoint Agent Model stub
if (!class_exists('OsAgentModel')) {
    class OsAgentModel {
        public $id;
        
        public function __construct() {
            $this->id = 1;
        }
        
        public function get_logged_in_agent_id() {
            return 1; // Mock agent ID
        }
        
        public function get_service_by_name($name) {
            return (object) [
                'id' => 1,
                'name' => $name,
                'description' => 'Mock service description'
            ];
        }
        
        public function assign_service_to_agent($agent_id, $service_id) {
            return true;
        }
        
        public function set_data($data) {
            return $this;
        }
        
        public function save() {
            return true;
        }
        
        public function order_by($clause) {
            return $this;
        }
        
        public function get_results() {
            return array(); // Mock empty results
        }
        
        public function get_agent_service_price($agent_id, $service_id) {
            return 50.00; // Mock price
        }
        
        public function where($conditions) {
            return $this;
        }
    }
}

// LatePoint Service Model stub
if (!class_exists('OsServiceModel')) {
    class OsServiceModel {
        public function where($conditions) {
            return $this;
        }
        
        public function get_results() {
            return array(); // Mock empty results
        }
        
        public function order_by($clause) {
            return $this;
        }
    }
}
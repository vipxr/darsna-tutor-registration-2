<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Darsna_Service_Sync {
    public function __construct() {
        $this->init_hooks();
    }
    private function init_hooks() {

        add_action('init', array($this, 'setup_service_sync_hooks'));
        add_action('admin_notices', array($this, 'check_service_integrity'));
        add_action('wp_ajax_cleanup_orphaned_services', array($this, 'ajax_cleanup_orphaned_services'));
        if (!wp_next_scheduled('darsna_daily_service_cleanup')) {
            wp_schedule_event(time(), 'daily', 'darsna_daily_service_cleanup');
        }
        add_action('darsna_daily_service_cleanup', array($this, 'cleanup_orphaned_services'));
    }
    public function setup_service_sync_hooks() {
        if (class_exists('OsServiceModel')) {
            add_action('latepoint_service_deleted', array($this, 'handle_service_deleted'), 10, 1);
            add_action('latepoint_service_updated', array($this, 'handle_service_updated'), 10, 1);
            add_action('latepoint_service_created', array($this, 'handle_service_created'), 10, 1);
        }
    }
    public function handle_service_deleted($service_id) {

        $this->remove_service_from_tutors($service_id);
        $this->remove_service_from_agents($service_id);
        $this->notify_admin_service_cleanup($service_id, 'deleted');
    }
    public function handle_service_updated($service_id) {
        $this->notify_tutors_service_updated($service_id);
    }
    public function handle_service_created($service_id) {
        // Service created - no additional processing needed
    }

    private function remove_service_from_tutors($service_id) {
        $tutors = get_users(array(
            'role__in' => array('pending_tutor', 'latepoint_agent'),
            'meta_key' => 'tutor_subjects',
            'meta_compare' => 'EXISTS'
        ));

        $updated_count = 0;
        foreach ($tutors as $tutor) {
            $subjects = get_user_meta($tutor->ID, 'tutor_subjects', true);
            if (is_array($subjects) && in_array($service_id, $subjects)) {
                $subjects = array_diff($subjects, array($service_id));
                update_user_meta($tutor->ID, 'tutor_subjects', array_values($subjects));
                $updated_count++;
            }
        }

        // Service removed from tutors successfully
    }
    private function remove_service_from_agents($service_id) {
        if (!class_exists('OsAgentModel')) {
            return;
        }

        try {

            if (class_exists('OsAgentHelper') && method_exists('OsAgentHelper', 'get_allowed_active_agents')) {
                $agents = OsAgentHelper::get_allowed_active_agents();
                $detached = 0;
                foreach ($agents as $agent) {
                    $agent_id = isset($agent->id) ? $agent->id : null;
                    if (!$agent_id) { continue; }

                    if (method_exists('OsAgentHelper', 'remove_service_from_agent')) {
                        $ok = OsAgentHelper::remove_service_from_agent($agent_id, $service_id);
                        if ($ok) { $detached++; }
                    } else {

                        if (class_exists('OsAgentServiceModel')) {

                            $agent_service = new OsAgentServiceModel();
                            if (method_exists($agent_service, 'where') && method_exists($agent_service, 'delete')) {
                                $agent_service->where(['agent_id' => $agent_id, 'service_id' => $service_id]);
                                if ($agent_service->delete()) { $detached++; }
                            }
                        }
                    }
                }

            }
        } catch (Exception $e) {

        }
    }
    public function cleanup_orphaned_services() {
        if (!class_exists('Darsna_Registration_System')) {
            return;
        }

        $integration = Darsna_Registration_System::get_instance();
        $valid_services = $integration->get_subjects();
        $valid_service_ids = array_keys($valid_services);

        $tutors = get_users(array(
            'role__in' => array('pending_tutor', 'latepoint_agent'),
            'meta_key' => 'tutor_subjects',
            'meta_compare' => 'EXISTS'
        ));

        $cleanup_count = 0;
        foreach ($tutors as $tutor) {
            $subjects = get_user_meta($tutor->ID, 'tutor_subjects', true);
            if (is_array($subjects)) {
                $original_count = count($subjects);
                $subjects = array_intersect($subjects, $valid_service_ids);
                
                if (count($subjects) !== $original_count) {
                    update_user_meta($tutor->ID, 'tutor_subjects', array_values($subjects));
                    $cleanup_count++;
                }
            }
        }

        if ($cleanup_count > 0) {
            $this->notify_admin_orphaned_cleanup($cleanup_count);
        }
    }
    public function check_service_integrity() {
        if (!current_user_can('manage_options') || !class_exists('Darsna_Registration_System')) {
            return;
        }

        if (!current_user_can('manage_options') || !class_exists('Darsna_Registration_System')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('dashboard', 'users', 'toplevel_page_latepoint'))) {
            return;
        }

        $integration = Darsna_Registration_System::get_instance();
        $valid_services = $integration->get_subjects();
        $valid_service_ids = array_keys($valid_services);

        $tutors = get_users(array(
            'role__in' => array('pending_tutor', 'latepoint_agent'),
            'meta_key' => 'tutor_subjects',
            'meta_compare' => 'EXISTS',
            'number' => 50 // Limit for performance
        ));

        $orphaned_count = 0;
        foreach ($tutors as $tutor) {
            $subjects = get_user_meta($tutor->ID, 'tutor_subjects', true);
            if (is_array($subjects)) {
                $orphaned_services = array_diff($subjects, $valid_service_ids);
                if (!empty($orphaned_services)) {
                    $orphaned_count++;
                }
            }
        }

        if ($orphaned_count > 0) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Darsna Service Sync:</strong> Found ' . $orphaned_count . ' tutors with orphaned service references. ';
            echo '<a href="#" id="cleanup-orphaned-services" class="button button-secondary">Clean Up Now</a></p>';
            echo '</div>';
            echo '<script>
            jQuery(document).ready(function($) {
                $("#cleanup-orphaned-services").click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    button.prop("disabled", true).text("Cleaning...");
                    
                    $.post(ajaxurl, {
                        action: "cleanup_orphaned_services",
                        nonce: "' . wp_create_nonce('cleanup_orphaned_services') . '"
                    }, function(response) {
                        if (response.success) {
                            button.closest(".notice").fadeOut();
                            $("<div class=\"notice notice-success is-dismissible\"><p>" + response.data + "</p></div>").insertAfter(".wrap h1:first");
                        } else {
                            alert("Error: " + response.data);
                            button.prop("disabled", false).text("Clean Up Now");
                        }
                    });
                });
            });
            </script>';
        }
    }
    public function ajax_cleanup_orphaned_services() {
        if (!wp_verify_nonce($_POST['nonce'], 'cleanup_orphaned_services') || !current_user_can('manage_options')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $this->cleanup_orphaned_services();
        wp_send_json_success('Orphaned services cleaned up successfully.');
    }
    private function notify_admin_service_cleanup($service_id, $action) {
        if (class_exists('Darsna_Notification_System')) {
            $notification_system = new Darsna_Notification_System();
            $notification_system->notify_admin_service_cleanup($service_id, $action);
        }
    }
    private function notify_admin_orphaned_cleanup($cleanup_count) {
        if (class_exists('Darsna_Notification_System')) {
            $notification_system = new Darsna_Notification_System();
            $notification_system->notify_admin_orphaned_cleanup($cleanup_count);
        }
    }
    private function notify_tutors_service_updated($service_id) {
        if (class_exists('Darsna_Notification_System')) {
            $notification_system = new Darsna_Notification_System();
            $notification_system->notify_tutors_service_updated($service_id);
        }
    }
    public static function deactivate() {

        wp_clear_scheduled_hook('darsna_daily_service_cleanup');
    }
}
if (class_exists('OsServiceModel')) {
    new Darsna_Service_Sync();
}

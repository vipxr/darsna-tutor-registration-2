<?php
/**
 * Darsna Notification System
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Darsna_Notification_System {
    private static $instance = null;
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function __construct() {
        // Private constructor for singleton
    }
    public function notify_admin_new_tutor($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !is_object($user)) {
            return false;
        }
        
        // Get tutor subjects
        $subject_ids = get_user_meta($user_id, 'tutor_subjects', true);
        $subject_names = [];
        
        if (is_array($subject_ids) && !empty($subject_ids)) {
            $subject_names = $this->get_subject_names_by_ids($subject_ids);
        }
        
        // Get tutor details
        $hourly_rate = get_user_meta($user_id, 'tutor_hourly_rate', true);
        $admin_url = admin_url('users.php?role=pending_tutor');
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email)) {
            return false;
        }
        
        // Prepare email content
        $subject = sprintf(__('[%s] New Tutor Registration', 'darsna-tutor-registration'), $site_name);
        $message = sprintf(
            __(
                "A new tutor has registered and requires approval:\n\n" .
                "Name: %s\n" .
                "Email: %s\n" .
                "Subjects: %s\n" .
                "Hourly Rate: $%s\n\n" .
                "Approve this tutor: %s",
                'darsna-tutor-registration'
            ),
            isset($user->display_name) ? $user->display_name : '',
            isset($user->user_email) ? $user->user_email : '',
            implode(', ', $subject_names),
            $hourly_rate,
            $admin_url
        );
        
        return $this->send_email($admin_email, $subject, $message);
    }
    
    /**
     * Notify tutor about approval
     * 
     * @param int $user_id The user ID of the approved tutor
     * @return bool True if email was sent successfully, false otherwise
     */
    public function notify_tutor_approved($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !is_object($user) || empty($user->user_email)) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        
        // Prepare email content
        $subject = sprintf(__('[%s] Your Tutor Application Approved!', 'darsna-tutor-registration'), $site_name);
        $message = sprintf(
            __(
                "Congratulations %s!\n\n" .
                "Your tutor application has been approved. You can now start accepting bookings.\n\n" .
                "Login to your account: %s",
                'darsna-tutor-registration'
            ),
            isset($user->display_name) ? $user->display_name : 'Tutor',
            $login_url
        );
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    public function notify_tutor_rejected($user_id, $reason = '') {
        $user = get_userdata($user_id);
        if (!$user || !is_object($user) || empty($user->user_email)) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        
        // Prepare email content
        $subject = sprintf(__('[%s] Tutor Application Update', 'darsna-tutor-registration'), $site_name);
        $message = sprintf(
            __(
                "Dear %s,\n\n" .
                "Thank you for your interest in becoming a tutor on our platform.\n\n" .
                "Unfortunately, we are unable to approve your application at this time.\n\n" .
                "%s" .
                "\n\nIf you have any questions, please contact our support team.",
                'darsna-tutor-registration'
            ),
            isset($user->display_name) ? $user->display_name : 'Applicant',
            !empty($reason) ? "Reason: {$reason}\n\n" : ''
        );
        
        return $this->send_email($user->user_email, $subject, $message);
    }
    public function notify_admin_service_cleanup($service_id, $action) {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return false;
        }
        
        $site_url = get_site_url();
        
        // Prepare email content
        $subject = 'Darsna: Service ' . ucfirst($action);
        $message = "Service ID {$service_id} has been {$action} from LatePoint.\n\n";
        $message .= "All tutor assignments and agent connections have been automatically updated.\n\n";
        if (!empty($site_url)) {
            $message .= "Site: " . $site_url;
        }
        
        return $this->send_email($admin_email, $subject, $message);
    }
    public function notify_admin_orphaned_cleanup($cleanup_count) {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return false;
        }
        
        $site_url = get_site_url();
        
        // Prepare email content
        $subject = 'Darsna: Orphaned Services Cleaned Up';
        $message = "Automatic cleanup removed orphaned service references from {$cleanup_count} tutors.\n\n";
        $message .= "This helps maintain data integrity between LatePoint and tutor assignments.\n\n";
        if (!empty($site_url)) {
            $message .= "Site: " . $site_url;
        }
        
        return $this->send_email($admin_email, $subject, $message);
    }

    public function notify_tutors_service_updated($service_id, $service_name = '', $update_type = 'updated') {
        // Get tutors who have this service
        $tutors = get_users(array(
            'role__in' => array('pending_tutor', 'latepoint_agent'),
            'meta_key' => 'tutor_subjects',
            'meta_compare' => 'EXISTS'
        ));
        
        $affected_tutors = array();
        foreach ($tutors as $tutor) {
            $subjects = get_user_meta($tutor->ID, 'tutor_subjects', true);
            if (is_array($subjects) && in_array($service_id, $subjects)) {
                $affected_tutors[] = $tutor;
            }
        }
        
        if (empty($affected_tutors)) {
            return true; // No tutors affected, consider it successful
        }
        
        $site_name = get_bloginfo('name');
        $service_display_name = !empty($service_name) ? $service_name : "Service #{$service_id}";
        
        // Prepare email content
        $subject = sprintf(__('[%s] Service Update Notification', 'darsna-tutor-registration'), $site_name);
        $message = sprintf(
            __(
                "Dear Tutor,\n\n" .
                "We wanted to inform you that a service you're associated with has been %s:\n\n" .
                "Service: %s\n\n" .
                "Please review your service offerings and update your profile if necessary.\n\n" .
                "If you have any questions, please contact our support team.",
                'darsna-tutor-registration'
            ),
            $update_type,
            $service_display_name
        );
        
        $success_count = 0;
        foreach ($affected_tutors as $tutor) {
            if (!empty($tutor->user_email)) {
                if ($this->send_email($tutor->user_email, $subject, $message)) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    }
    public function notify_tutor_booking_paid($order_id, $booking_details = array()) {
        $order = wc_get_order($order_id);
        if (!$order || !is_object($order)) {
            return false;
        }
        
        $agent_id = $order->get_meta('_darsna_agent_id');
        if (!$agent_id) {
            return false;
        }
        
        // Get tutor email from agent
        $agent_email = $this->get_agent_email($agent_id);
        if (empty($agent_email)) {
            return false;
        }
        
        // Get financial details
        $tutor_earning = $order->get_meta('_darsna_tutor_earning');
        $platform_fee = $order->get_meta('_darsna_platform_fee');
        
        // Prepare email content
        $subject = sprintf(__('New Paid Booking - Order #%s', 'darsna-tutor-registration'), $order_id);
        $message = sprintf(
            __(
                "You have a new paid booking!\n\n" .
                "Order Total: $%s\n" .
                "Platform Fee (20%%): $%s\n" .
                "Your Earning: $%s\n\n" .
                "View booking details in your dashboard.",
                'darsna-tutor-registration'
            ),
            number_format($order->get_total(), 2),
            number_format($platform_fee, 2),
            number_format($tutor_earning, 2)
        );
        
        return $this->send_email($agent_email, $subject, $message);
    }
    private function send_email($to, $subject, $message, $headers = array()) {
        if (empty($to) || empty($subject) || empty($message)) {
            return false;
        }
        
        // Try LatePoint's email system first if available
        if (class_exists('OsEmailHelper') && method_exists('OsEmailHelper', 'send_email')) {
            try {
                /** @var \OsEmailHelper $emailHelper */
                return OsEmailHelper::send_email($to, $subject, $message);
            } catch (Exception $e) {
                // Fall through to wp_mail
            }
        }
        
        // Fallback to WordPress mail function
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get agent email by agent ID
     * 
     * @param int $agent_id Agent ID
     * @return string|null Agent email or null if not found
     */
    private function get_agent_email($agent_id) {
        if (!$agent_id || !is_numeric($agent_id)) {
            return null;
        }
        
        // Use LatePoint Agent Helper only
        if (class_exists('OsAgentHelper') && method_exists('OsAgentHelper', 'get_agent_by_id')) {
            $agent = OsAgentHelper::get_agent_by_id($agent_id);
            if ($agent && is_object($agent) && isset($agent->email)) {
                return $agent->email;
            }
        }
        
        return null;
    }
    private function get_subject_names_by_ids($subject_ids) {
        if (!is_array($subject_ids) || empty($subject_ids)) {
            return array();
        }
        
        // Use registration system only
        if (class_exists('Darsna_Registration_System')) {
            $registration_system = Darsna_Registration_System::get_instance();
            if (method_exists($registration_system, 'get_subjects')) {
                return $registration_system->get_subjects($subject_ids, true);
            }
        }
        
        return array();
    }
    private function get_available_subjects() {
        // Try to get subjects from registration system
        if (class_exists('Darsna_Registration_System')) {
            $registration_system = Darsna_Registration_System::get_instance();
            if (method_exists($registration_system, 'get_subjects')) {
                try {
                    return $registration_system->get_subjects();
                } catch (Exception $e) {
                    // Silent fallback
                }
            }
        }
        
        return array();
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

class Darsna_Registration_System {
    private $form_fields = [];
    
    public function __construct() {
        $this->init_hooks();
        $this->init_form_fields();
    }
    private function init_hooks(): void {
        add_action('woocommerce_register_form_start', [$this, 'render_fields'], 5);
        add_action('woocommerce_register_form', [$this, 'render_fields'], 5);
        add_action('woocommerce_register_form_end', [$this, 'render_fields'], 5);
        add_action('woocommerce_before_checkout_registration_form', [$this, 'render_fields'], 5);
        add_action('woocommerce_register_post', [$this, 'validate_registration'], 10, 3);
        add_action('woocommerce_created_customer', [$this, 'process_registration'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_action('woocommerce_register_form', [$this, 'add_nonce_field'], 5);
    }
    
    public function enqueue_assets(): void {
        if (!defined('DARSNA_TUTOR_URL') || !defined('DARSNA_TUTOR_REGISTRATION_VERSION')) {
            return;
        }
        
        if (is_account_page() || is_checkout() || is_woocommerce() || is_cart()) {
            wp_enqueue_style(
                'darsna-woocommerce-registration',
                DARSNA_TUTOR_URL . 'assets/css/woocommerce-registration.css',
                [],
                DARSNA_TUTOR_REGISTRATION_VERSION
            );
            
            wp_enqueue_script(
                'darsna-woocommerce-registration',
                DARSNA_TUTOR_URL . 'assets/js/woocommerce-registration.js',
                ['jquery', 'select2'],
                DARSNA_TUTOR_REGISTRATION_VERSION,
                true
            );
            
            wp_enqueue_script('selectWoo');
            wp_enqueue_script('wc-enhanced-select');
            wp_enqueue_style('select2');
        }
    }
    private function init_form_fields(): void {
        $this->form_fields = [
            'billing_first_name' => [
                'type' => 'text',
                'label' => __('First Name', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-first'],
                'custom_attributes' => ['minlength' => '2']
            ],
            'billing_last_name' => [
                'type' => 'text',
                'label' => __('Last Name', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-last'],
                'custom_attributes' => ['minlength' => '2']
            ],
            'billing_country' => [
                'type' => 'country',
                'label' => __('Country', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-wide']
            ],
            'billing_phone' => [
                'type' => 'tel',
                'label' => __('Phone Number', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-wide'],
                'placeholder' => __('Please include country code (e.g., +9661234567)', 'darsna-tutor-registration'),
                'custom_attributes' => ['pattern' => '^\+.{6,}$'],
                'validation' => ['phone_format']
            ],
            'user_type' => [
                'type' => 'select',
                'label' => __('I want to register as:', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-wide'],
                'options' => [
                    '' => __('Select user type', 'darsna-tutor-registration'),
                    'student' => __('Student - I want to book tutoring sessions', 'darsna-tutor-registration'),
                    'tutor' => __('Tutor - I want to offer tutoring services', 'darsna-tutor-registration')
                ]
            ],
            'tutor_subjects' => [
                'type' => 'select',
                'label' => __('Subjects I can teach:', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-wide'],
                'input_class' => ['wc-enhanced-select'],
                'description' => __('Select multiple subjects you can teach.', 'darsna-tutor-registration'),
                'custom_attributes' => [
                    'data-placeholder' => __('Choose subjects...', 'darsna-tutor-registration'),
                    'multiple' => 'multiple',
                    'name' => 'tutor_subjects[]'
                ],
                'options' => [],
                'conditional' => 'tutor'
            ],
            'tutor_bio' => [
                'type' => 'textarea',
                'label' => __('Brief Bio:', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-wide'],
                'placeholder' => __('Tell students about yourself and your teaching approach...', 'darsna-tutor-registration'),
                'custom_attributes' => ['rows' => '4', 'minlength' => '25'],
                'conditional' => 'tutor'
            ],
            'tutor_hourly_rate' => [
                'type' => 'number',
                'label' => __('Hourly Rate ($):', 'darsna-tutor-registration'),
                'required' => true,
                'class' => ['form-row-wide'],
                'description' => __('Your rate before platform fees (you receive 80%)', 'darsna-tutor-registration'),
                'custom_attributes' => ['min' => '10', 'max' => '100', 'step' => '5'],
                'conditional' => 'tutor'
            ]
        ];
    }
    
    public function render_fields(): void {
        echo '<div class="darsna-billing-fields">';
        echo '<h3>' . esc_html__('Personal Information', 'darsna-tutor-registration') . '</h3>';
        foreach (['billing_first_name', 'billing_last_name', 'billing_country', 'billing_phone'] as $field) {
            $this->render_form_field($field);
        }
        echo '</div>';
        
        echo '<div class="darsna-user-type-section">';
        echo '<h3>' . esc_html__('Account Type', 'darsna-tutor-registration') . '</h3>';
        $this->render_form_field('user_type');
        echo '</div>';
        
        echo '<div id="tutor-fields" class="darsna-tutor-fields">';
        echo '<h3>' . esc_html__('Tutor Information', 'darsna-tutor-registration') . '</h3>';
        
        $subjects = $this->get_subjects(null, true);
        $this->form_fields['tutor_subjects']['options'] = !empty($subjects) ? $subjects : [
            '' => __('No subjects available - Please contact administrator', 'darsna-tutor-registration')
        ];
        
        foreach (['tutor_subjects', 'tutor_bio', 'tutor_hourly_rate'] as $field) {
            $this->render_form_field($field);
        }
        echo '</div>';
    }
    
    private function render_form_field(string $field_key): void {
        if (!isset($this->form_fields[$field_key])) {
            return;
        }
        
        $field_config = $this->form_fields[$field_key];
        $posted = $_POST[$field_key] ?? '';
        $current_value = is_array($posted) 
            ? array_map('sanitize_text_field', $posted)
            : sanitize_text_field($posted);
        
        if (isset($field_config['conditional'])) {
            $field_config['class'][] = 'darsna-field-hidden';
        }
        
        woocommerce_form_field($field_key, $field_config, $current_value);
    }
    
    public function get_subjects($subject_ids = null, $names_only = false): array {
        try {
            $services = OsServiceHelper::get_allowed_active_services();
            if (!$services || !is_array($services)) {
                return array();
            }

            $results = array();
            foreach ($services as $service) {
                if (!isset($service->id, $service->name)) {
                    continue;
                }

                if ($subject_ids && !in_array($service->id, $subject_ids)) {
                    continue;
                }

                if ($names_only) {
                    $results[$service->id] = sanitize_text_field($service->name);
                } else {
                    $results[] = array(
                        'id' => intval($service->id),
                        'name' => sanitize_text_field($service->name),
                        'description' => isset($service->short_description) ? sanitize_text_field($service->short_description) : '',
                        'duration' => isset($service->duration) ? intval($service->duration) : 60,
                        'price' => isset($service->charge_amount) ? floatval($service->charge_amount) : 0
                    );
                }
            }

            return $results;
        } catch (Exception $e) {
            return array();
        }
    }
    
    public function validate_registration($username, $email, $validation_errors): void {
        $is_my_account = isset($_POST['register']) && !empty($_POST['register']);
        if ($is_my_account && !wp_verify_nonce($_POST['darsna_registration_nonce'] ?? '', 'darsna_registration')) {
            $validation_errors->add('nonce_error', __('Security check failed.', 'darsna-tutor-registration'));
            return;
        }
        
        $user_type = sanitize_text_field($_POST['user_type'] ?? '');
        
        foreach ($this->form_fields as $field_key => $field_config) {
            if (isset($field_config['conditional']) && $user_type !== $field_config['conditional']) {
                continue;
            }
            $value = is_array($_POST[$field_key] ?? '') ? array_map('sanitize_text_field', $_POST[$field_key]) : sanitize_text_field($_POST[$field_key] ?? '');
            
            if (!empty($field_config['required']) && empty($value)) {
                $validation_errors->add(
                    $field_key . '_error',
                    sprintf(__('Please enter %s.', 'darsna-tutor-registration'), strtolower($field_config['label']))
                );
                continue;
            }
            
            if (!empty($field_config['validation']) && !empty($value)) {
                foreach ($field_config['validation'] as $rule) {
                    switch ($rule) {
                        case 'phone_format':
                            if (!preg_match('/^\+.{6,}$/', trim($value))) {
                                $validation_errors->add(
                                    $field_key . '_error',
                                    __('Please enter a valid phone number with country code (e.g., +9661234567).', 'darsna-tutor-registration')
                                );
                            }
                            break;
                    }
                }
            }
        }
    }
    
    public function process_registration($user_id, $new_customer_data = [], $password_generated = false): void {
        $user_type = sanitize_text_field($_POST['user_type'] ?? '');
        $user = new WP_User($user_id);
        
        update_user_meta($user_id, 'first_name', sanitize_text_field($_POST['billing_first_name'] ?? ''));
        update_user_meta($user_id, 'last_name', sanitize_text_field($_POST['billing_last_name'] ?? ''));
        update_user_meta($user_id, 'billing_first_name', sanitize_text_field($_POST['billing_first_name'] ?? ''));
        update_user_meta($user_id, 'billing_last_name', sanitize_text_field($_POST['billing_last_name'] ?? ''));
        update_user_meta($user_id, 'billing_country', sanitize_text_field($_POST['billing_country'] ?? ''));
        update_user_meta($user_id, 'phone', sanitize_text_field($_POST['billing_phone'] ?? ''));
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone'] ?? ''));
        update_user_meta($user_id, 'user_type', $user_type);
        
        if ($user_type === 'tutor') {
            $user->set_role('pending_tutor');
            update_user_meta($user_id, 'tutor_subjects', is_array($_POST['tutor_subjects'] ?? '') ? array_map('sanitize_text_field', $_POST['tutor_subjects']) : sanitize_text_field($_POST['tutor_subjects'] ?? ''));
            update_user_meta($user_id, 'tutor_bio', sanitize_textarea_field($_POST['tutor_bio'] ?? ''));
            update_user_meta($user_id, 'tutor_hourly_rate', floatval(sanitize_text_field($_POST['tutor_hourly_rate'] ?? '')));
            update_user_meta($user_id, 'tutor_status', 'pending_approval');
            update_user_meta($user_id, 'tutor_registration_date', current_time('mysql'));
            $this->notify_admin_new_tutor($user_id);
        } else {
            $user->set_role('student');
        }
    }
    
    private function notify_admin_new_tutor(int $user_id): void {
        $notification_system = new Darsna_Notification_System();
        $notification_system->notify_admin_new_tutor($user_id);
    }
    
    public function add_nonce_field(): void {
        wp_nonce_field('darsna_registration', 'darsna_registration_nonce');
    }
    
    public static function get_instance(): self {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new self();
        }
        
        return $instance;
    }
}
<?php

class Darsna_Tutor_Tutors_Page {
    
    public function __construct() {
        add_shortcode('darsna_tutors', array($this, 'render_enhanced_tutors'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_filter_tutors', array($this, 'ajax_filter_tutors'));
        add_action('wp_ajax_nopriv_filter_tutors', array($this, 'ajax_filter_tutors'));
        add_action('wp_ajax_get_tutor_details', array($this, 'ajax_get_tutor_details'));
        add_action('wp_ajax_nopriv_get_tutor_details', array($this, 'ajax_get_tutor_details'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('darsna-tutors-page', plugin_dir_url(__FILE__) . '../assets/js/tutors-page.js', array('jquery'), '1.0.0', true);
        wp_localize_script('darsna-tutors-page', 'darsna_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('darsna_tutors_nonce')
        ));
        
        wp_enqueue_style('darsna-tutors-page', plugin_dir_url(__FILE__) . '../assets/css/tutors-page.css', array(), '1.0.0');
    }
    
    // Removed outdated render_tutors_page method - only using enhanced version now
    
    private function get_all_tutors() {
        global $wpdb;
        
        $query = "
            SELECT DISTINCT
                a.id,
                a.first_name,
                a.last_name,
                a.email,
                a.phone,
                a.bio,
                a.features,
                a.wp_user_id,
                GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as services,
                GROUP_CONCAT(DISTINCT COALESCE(cp.charge_amount, s.charge_amount) SEPARATOR ', ') as prices,
                MIN(COALESCE(cp.charge_amount, s.charge_amount)) as min_price,
                MAX(COALESCE(cp.charge_amount, s.charge_amount)) as max_price,
                am.meta_value as urgent_help_enabled
            FROM {$wpdb->prefix}latepoint_agents a
            LEFT JOIN {$wpdb->prefix}latepoint_agents_services ags ON a.id = ags.agent_id
            LEFT JOIN {$wpdb->prefix}latepoint_services s ON ags.service_id = s.id
            LEFT JOIN {$wpdb->prefix}latepoint_custom_prices cp ON (
                cp.agent_id = a.id AND 
                cp.service_id = s.id
            )
            LEFT JOIN {$wpdb->prefix}latepoint_agent_meta am ON (
                am.object_id = a.id AND 
                am.meta_key = 'urgent_help_enabled'
            )
            WHERE a.status = 'active'
            GROUP BY a.id
            ORDER BY a.first_name, a.last_name
        ";
        
        $results = $wpdb->get_results($query);
        
        // Process tutor data and get additional info from user meta
        foreach ($results as &$tutor) {
            // Get additional data from WordPress user meta if wp_user_id exists
            if (isset($tutor->wp_user_id)) {
                $code = get_user_meta( $tutor->wp_user_id, 'billing_country', true );
                $tutor->country = WC()->countries->countries[ $code ] ?? '';
                $tutor->experience = get_user_meta($tutor->wp_user_id, 'tutor_experience', true) ?: '';
                $tutor->education = get_user_meta($tutor->wp_user_id, 'tutor_education', true) ?: '';
                $tutor->languages = get_user_meta($tutor->wp_user_id, 'tutor_languages', true) ?: '';
            } else {
                $tutor->country = '';
                $tutor->experience = '';
                $tutor->education = '';
                $tutor->languages = '';
            }
        }
        
        return $results;
    }
    
    private function get_unique_countries($tutors) {
        $countries = array();
        foreach ($tutors as $tutor) {
            if (!empty($tutor->country) && !in_array($tutor->country, $countries)) {
                $countries[] = $tutor->country;
            }
        }
        sort($countries);
        return $countries;
    }
    
    private function get_unique_subjects($tutors) {
        $subjects = array();
        foreach ($tutors as $tutor) {
            if (!empty($tutor->services)) {
                $tutor_services = explode(', ', $tutor->services);
                foreach ($tutor_services as $service) {
                    $service = trim($service);
                    if (!empty($service) && !in_array($service, $subjects)) {
                        $subjects[] = $service;
                    }
                }
            }
        }
        sort($subjects);
        return $subjects;
    }
    
    private function render_tutors_grid($tutors) {
        if (empty($tutors)) {
            return '<div class="no-tutors"><p>No tutors available at the moment.</p></div>';
        }
        
        $output = '';
        foreach ($tutors as $tutor) {
            $output .= $this->render_tutor_card($tutor);
        }
        
        return $output;
    }
    
    private function render_tutor_card($tutor) {
        $avatar_url = $this->get_avatar_url($tutor->id);
        $full_name = trim($tutor->first_name . ' ' . $tutor->last_name);
        $price_range = $this->format_price_range($tutor->min_price, $tutor->max_price);
        
        ob_start();
        ?> 
        <div class="tutor-card" 
             data-country="<?php echo esc_attr($tutor->country); ?>"
             data-services="<?php echo esc_attr(strtolower($tutor->services)); ?>"
             data-min-price="<?php echo esc_attr($tutor->min_price); ?>"
             data-max-price="<?php echo esc_attr($tutor->max_price); ?>"
             data-name="<?php echo esc_attr(strtolower($full_name)); ?>"
             data-urgent-help="<?php echo !empty($tutor->urgent_help_enabled) && $tutor->urgent_help_enabled == 1 ? 'yes' : 'no'; ?>">
            
            <div class="tutor-avatar">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($full_name); ?>" class="avatar-img">
                <div class="online-status"></div>
                <?php if (!empty($tutor->urgent_help_enabled) && $tutor->urgent_help_enabled == 1): ?>
                    <div class="urgent-help-badge" title="⚡ Urgent Help Available - This tutor responds within 6 hours for immediate assistance">
                        <span class="urgent-icon">⚡</span>
                        <span class="urgent-text">Urgent Help</span>
                        <div class="urgent-tooltip">
                            <div class="tooltip-content">
                                <strong>⚡ Urgent Help Available</strong>
                                <p>This tutor provides immediate assistance and responds within 6 hours</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tutor-info">
                <h3 class="tutor-name"><?php echo esc_html($full_name); ?></h3>
                
                <?php if (!empty($tutor->country)): ?>
                    <div class="tutor-location">
                        <?php echo esc_html($tutor->country); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->bio)): ?>
                    <p class="tutor-bio"><?php echo esc_html(wp_trim_words($tutor->bio, 20)); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($tutor->services)): ?>
                    <div class="tutor-subjects">
                        <span class="subjects-label">Subjects:</span>
                        <div class="subjects-tags">
                            <?php 
                            $services = explode(', ', $tutor->services);
                            foreach (array_slice($services, 0, 3) as $service): 
                            ?>
                                <span class="subject-tag"><?php echo esc_html($service); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($services) > 3): ?>
                                <span class="more-subjects">+<?php echo count($services) - 3; ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->experience)): ?>
                    <div class="tutor-experience">
                        <span class="experience-icon">🎓</span>
                        <?php echo esc_html($tutor->experience); ?> years experience
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->languages)): ?>
                    <div class="tutor-languages">
                        <span class="languages-icon">🗣️</span>
                        <?php echo esc_html($tutor->languages); ?>
                    </div>
                <?php endif; ?>
                
                <div class="tutor-pricing">
                    <span class="price-label">Starting from:</span>
                    <span class="price-amount"><?php echo $price_range; ?></span>
                </div>
                
                <div class="tutor-actions">
                    <button class="btn btn-secondary view-profile" data-tutor-id="<?php echo esc_attr($tutor->id); ?>">
                        View Profile
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_avatar_url($tutor_id) {
        global $wpdb;
        
        // Get agent data from LatePoint agents table
        $agent_data = $wpdb->get_row($wpdb->prepare(
            "SELECT avatar_image_id, wp_user_id FROM {$wpdb->prefix}latepoint_agents WHERE id = %d",
            $tutor_id
        ));
        
        if ($agent_data && !empty($agent_data->avatar_image_id)) {
            // Get the image metadata from wp_postmeta
            $image_meta = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_wp_attachment_metadata'",
                $agent_data->avatar_image_id
            ));
            
            if ($image_meta) {
                $meta_data = maybe_unserialize($image_meta);
                
                if (is_array($meta_data) && isset($meta_data['file'])) {
                    // Get the upload directory info
                    $upload_dir = wp_upload_dir();
                    
                    // Check if thumbnail size exists, otherwise use original
                    if (isset($meta_data['sizes']['thumbnail']['file'])) {
                        $file_path = dirname($meta_data['file']) . '/' . $meta_data['sizes']['thumbnail']['file'];
                    } else {
                        $file_path = $meta_data['file'];
                    }
                    
                    // Construct the full URL
                    $avatar_url = $upload_dir['baseurl'] . '/' . $file_path;
                    
                    // Verify the file exists
                    $file_full_path = $upload_dir['basedir'] . '/' . $file_path;
                    if (file_exists($file_full_path)) {
                        return $avatar_url;
                    }
                }
            }
        }
        
        // Fallback to gravatar if wp_user_id exists
        if ($agent_data && !empty($agent_data->wp_user_id)) {
            $user_info = get_userdata($agent_data->wp_user_id);
            if ($user_info) {
                return get_avatar_url($user_info->user_email, array(
                    'size' => 120,
                    'default' => 'identicon'
                ));
            }
        }
        
        // Final fallback to default gravatar
        return get_avatar_url('default@example.com', array(
            'size' => 120,
            'default' => 'identicon'
        ));
    }
    
    private function format_price_range($min_price, $max_price) {
        if (empty($min_price) && empty($max_price)) {
            return 'Contact for pricing';
        }
        
        $min_price = floatval($min_price);
        $max_price = floatval($max_price);
        
        if ($min_price == $max_price) {
            return '$' . number_format($min_price, 0) . '/hr';
        }
        
        return '$' . number_format($min_price, 0) . ' - $' . number_format($max_price, 0) . '/hr';
    }
    
    public function ajax_filter_tutors() {
        check_ajax_referer('darsna_tutors_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $country = sanitize_text_field($_POST['country'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $price_range = sanitize_text_field($_POST['price_range'] ?? '');
        $urgent_help = sanitize_text_field($_POST['urgent_help'] ?? '');
        $sort = sanitize_text_field($_POST['sort'] ?? 'name');
        
        $tutors = $this->get_filtered_tutors($search, $country, $subject, $price_range, $urgent_help, $sort);
        
        wp_send_json_success(array(
            'html' => $this->render_tutors_grid($tutors),
            'count' => count($tutors)
        ));
    }
    
    private function get_filtered_tutors($search, $country, $subject, $price_range, $urgent_help, $sort) {
        $tutors = $this->get_all_tutors();
        
        // Apply filters
        $filtered_tutors = array_filter($tutors, function($tutor) use ($search, $country, $subject, $price_range, $urgent_help) {
            // Search filter
            if (!empty($search)) {
                $search_text = strtolower($search);
                $tutor_text = strtolower($tutor->first_name . ' ' . $tutor->last_name . ' ' . $tutor->services . ' ' . $tutor->bio);
                if (strpos($tutor_text, $search_text) === false) {
                    return false;
                }
            }
            
            // Country filter
            if (!empty($country) && $tutor->country !== $country) {
                return false;
            }
            
            // Subject filter
            if (!empty($subject)) {
                $tutor_services = strtolower($tutor->services);
                if (strpos($tutor_services, strtolower($subject)) === false) {
                    return false;
                }
            }
            
            // Price filter
            if (!empty($price_range)) {
                $min_price = floatval($tutor->min_price);
                switch ($price_range) {
                    case '0-20':
                        if ($min_price > 20) return false;
                        break;
                    case '20-40':
                        if ($min_price < 20 || $min_price > 40) return false;
                        break;
                    case '40-60':
                        if ($min_price < 40 || $min_price > 60) return false;
                        break;
                    case '60-100':
                        if ($min_price < 60 || $min_price > 100) return false;
                        break;
                    case '100+':
                        if ($min_price < 100) return false;
                        break;
                }
            }
            
            // Urgent help filter
            if (!empty($urgent_help)) {
                $has_urgent_help = !empty($tutor->urgent_help_enabled) && $tutor->urgent_help_enabled == 1;
                if ($urgent_help === 'yes' && !$has_urgent_help) {
                    return false;
                }
                if ($urgent_help === 'no' && $has_urgent_help) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Apply sorting
        usort($filtered_tutors, function($a, $b) use ($sort) {
            switch ($sort) {
                case 'price-low':
                    return floatval($a->min_price) - floatval($b->min_price);
                case 'price-high':
                    return floatval($b->min_price) - floatval($a->min_price);
                case 'rating':
                    // Placeholder for rating sorting
                    return 0;
                case 'name':
                default:
                    return strcmp($a->first_name . ' ' . $a->last_name, $b->first_name . ' ' . $b->last_name);
            }
        });
        
        return $filtered_tutors;
    }
    
    /**
     * Enhanced tutors shortcode that mimics LatePoint's latepoint_resources
     * but with additional search, filtering, and enhanced popup functionality
     */
    public function render_enhanced_tutors($atts) {
        $atts = shortcode_atts(array(
            'columns' => '3',
            'show_search' => 'yes',
            'show_filters' => 'yes',
            'items_per_page' => '12',
            'show_bio' => 'yes',
            'show_pricing' => 'yes'
        ), $atts);
        
        // Get all tutors
        $tutors = $this->get_all_tutors();
        $countries = $this->get_unique_countries($tutors);
        $subjects = $this->get_unique_subjects($tutors);
        
        ob_start();
        ?>
        <div class="darsna-enhanced-tutors latepoint-resources-w" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php if ($atts['show_search'] === 'yes' || $atts['show_filters'] === 'yes'): ?>
            <div class="tutors-enhanced-filters">
                <?php if ($atts['show_search'] === 'yes'): ?>
                <div class="enhanced-search-container">
                    <input type="text" id="enhanced-tutor-search" placeholder="Search tutors by name, subject, or expertise..." class="enhanced-search-input" style="padding: 10px;">
                </div>
                <?php endif; ?>
                
                <?php if ($atts['show_filters'] === 'yes'): ?>
                <div class="enhanced-filters-row">
                    <select id="enhanced-subject-filter" class="enhanced-filter-select">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo esc_attr($subject); ?>"><?php echo esc_html($subject); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="enhanced-country-filter" class="enhanced-filter-select">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="enhanced-price-filter" class="enhanced-filter-select">
                        <option value="">All Prices</option>
                        <option value="0-20">$0 - $20/hr</option>
                        <option value="20-40">$20 - $40/hr</option>
                        <option value="40-60">$40 - $60/hr</option>
                        <option value="60-100">$60 - $100/hr</option>
                        <option value="100+">$100+/hr</option>
                    </select>
                    
                    <select id="enhanced-sort-filter" class="enhanced-filter-select">
                        <option value="name">Sort by Name</option>
                        <option value="price-low">Price: Low to High</option>
                        <option value="price-high">Price: High to Low</option>
                    </select>
                    
                    <select id="enhanced-urgent-help-filter" class="enhanced-filter-select">
                        <option value="">All Tutors</option>
                        <option value="yes">⚡ Urgent Help Only</option>
                    </select>
                    
                    <button id="enhanced-clear-filters" class="enhanced-clear-btn">Clear</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="latepoint-resources enhanced-tutors-grid" id="enhanced-tutors-grid">
                <?php echo $this->render_enhanced_tutors_grid($tutors, $atts); ?>
            </div>
            
            <div class="enhanced-loading-spinner" id="enhanced-loading-spinner" style="display: none;">
                <div class="spinner"></div>
                <p>Loading tutors...</p>
            </div>
            
            <div class="enhanced-no-results" id="enhanced-no-results" style="display: none;">
                <div class="enhanced-no-results-content">
                    <h3>No tutors found</h3>
                    <p>Try adjusting your search or filter criteria.</p>
                </div>
            </div>
        </div>
        
        <!-- Enhanced Tutor Details Popup -->
        <div class="os-item-details-popup enhanced-tutor-popup" id="enhanced-tutor-popup" style="display: none;">
            <div class="os-item-details-popup-content">
                <div class="os-item-details-popup-close enhanced-popup-close">×</div>
                <div class="enhanced-popup-body" id="enhanced-popup-body">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    private function render_enhanced_tutors_grid($tutors, $atts) {
        if (empty($tutors)) {
            return '<div class="enhanced-no-tutors"><p>No tutors available at the moment.</p></div>';
        }
        
        $output = '';
        foreach ($tutors as $tutor) {
            $output .= $this->render_enhanced_tutor_card($tutor, $atts);
        }
        
        return $output;
    }
    
    private function render_enhanced_tutor_card($tutor, $atts) {
        $avatar_url = $this->get_avatar_url($tutor->id);
        $full_name = trim($tutor->first_name . ' ' . $tutor->last_name);
        $price_range = $this->format_price_range($tutor->min_price, $tutor->max_price);
        
        ob_start();
        ?>
        <div class="latepoint-resource-item enhanced-tutor-item" 
             data-country="<?php echo esc_attr($tutor->country); ?>"
             data-services="<?php echo esc_attr(strtolower($tutor->services)); ?>"
             data-min-price="<?php echo esc_attr($tutor->min_price); ?>"
             data-max-price="<?php echo esc_attr($tutor->max_price); ?>"
             data-name="<?php echo esc_attr(strtolower($full_name)); ?>"
             data-tutor-id="<?php echo esc_attr($tutor->id); ?>"
             data-urgent-help="<?php echo !empty($tutor->urgent_help_enabled) && $tutor->urgent_help_enabled == 1 ? 'yes' : 'no'; ?>">
            
            <div class="latepoint-resource-item-avatar">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($full_name); ?>" class="latepoint-resource-avatar">
                <?php if (!empty($tutor->urgent_help_enabled) && $tutor->urgent_help_enabled == 1): ?>
                    <div class="urgent-help-badge" title="⚡ Urgent Help Available - This tutor responds within 6 hours for immediate assistance">
                        <span class="urgent-icon">⚡</span>
                        <span class="urgent-text">Urgent Help</span>
                        <div class="urgent-tooltip">
                            <strong>⚡ Urgent Help Available</strong>
                            <p>This tutor provides quick responses within 6 hours for immediate assistance with your learning needs.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="latepoint-resource-item-info">
                <div class="latepoint-resource-item-name"><?php echo esc_html($full_name); ?></div>
                
                <?php if (!empty($tutor->services) && $atts['show_bio'] === 'yes'): ?>
                    <div class="enhanced-tutor-subjects">
                        <?php 
                        $services = explode(', ', $tutor->services);
                        foreach (array_slice($services, 0, 2) as $service): 
                        ?>
                            <span class="enhanced-subject-badge"><?php echo esc_html($service); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($services) > 2): ?>
                            <span class="enhanced-more-subjects">+<?php echo count($services) - 2; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->bio) && $atts['show_bio'] === 'yes'): ?>
                    <div class="latepoint-resource-item-description">
                        <?php echo esc_html(wp_trim_words($tutor->bio, 15)); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_pricing'] === 'yes'): ?>
                    <div class="enhanced-pricing-info">
                        <span class="enhanced-price"><?php echo $price_range; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->experience)): ?>
                    <div class="enhanced-experience">
                        <span class="enhanced-exp-icon">🎓</span>
                        <?php echo esc_html($tutor->experience); ?> years
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="latepoint-resource-item-actions">
                <button class="latepoint-btn latepoint-btn-primary os-trigger-item-details-popup enhanced-view-details" 
                        data-item-details-popup-id="enhanced-tutor-popup"
                        data-tutor-id="<?php echo esc_attr($tutor->id); ?>">
                    Learn More
                </button>
                <?php $current_user = wp_get_current_user(); ?>
                <button class="latepoint-btn latepoint-btn-secondary enhanced-book-now<?php echo in_array('latepoint_agent', $current_user->roles) ? ' disabled' : ''; ?>" 
                        data-selected-agent="<?php echo esc_attr($tutor->id); ?>"
                        <?php echo in_array('latepoint_agent', $current_user->roles) ? 'onclick="alert(\"Booking is for students only. You are a tutor.\"); return false;" style="cursor: default;"' : ''; ?>>
                    Book Now
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_get_tutor_details() {
        check_ajax_referer('darsna_tutors_nonce', 'nonce');
        
        $tutor_id = intval($_POST['tutor_id'] ?? 0);
        if (!$tutor_id) {
            wp_send_json_error('Invalid tutor ID');
        }
        
        $tutor = $this->get_tutor_details($tutor_id);
        if (!$tutor) {
            wp_send_json_error('Tutor not found');
        }
        
        wp_send_json_success(array(
            'html' => $this->render_enhanced_tutor_popup($tutor)
        ));
    }
    
    private function get_tutor_details($tutor_id) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT DISTINCT
                a.id,
                a.first_name,
                a.last_name,
                a.email,
                a.phone,
                a.bio,
                a.features,
                a.wp_user_id,
                GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as services,
                GROUP_CONCAT(DISTINCT s.id SEPARATOR ', ') as service_ids,
                GROUP_CONCAT(DISTINCT COALESCE(cp.charge_amount, s.charge_amount) SEPARATOR ', ') as prices
            FROM {$wpdb->prefix}latepoint_agents a
            LEFT JOIN {$wpdb->prefix}latepoint_agents_services ags ON a.id = ags.agent_id
            LEFT JOIN {$wpdb->prefix}latepoint_services s ON ags.service_id = s.id
            LEFT JOIN {$wpdb->prefix}latepoint_custom_prices cp ON (
                cp.agent_id = a.id AND 
                cp.service_id = s.id
            )
            WHERE a.id = %d AND a.status = 'active'
            GROUP BY a.id
        ", $tutor_id);
        
        $tutor = $wpdb->get_row($query);
        
        if ($tutor && isset($tutor->wp_user_id)) {
            $code = get_user_meta( $tutor->wp_user_id, 'billing_country', true );
            $tutor->country = WC()->countries->countries[ $code ] ?? '';            
            $tutor->experience = get_user_meta($tutor->wp_user_id, 'tutor_experience', true) ?: '';
            $tutor->education = get_user_meta($tutor->wp_user_id, 'tutor_education', true) ?: '';
            $tutor->languages = get_user_meta($tutor->wp_user_id, 'tutor_languages', true) ?: '';
            $tutor->qualifications = get_user_meta($tutor->wp_user_id, 'tutor_qualifications', true) ?: '';
            $tutor->teaching_style = get_user_meta($tutor->wp_user_id, 'tutor_teaching_style', true) ?: '';
        }
        
        return $tutor;
    }
    
    private function render_enhanced_tutor_popup($tutor) {
        $avatar_url = $this->get_avatar_url($tutor->id);
        $full_name = trim($tutor->first_name . ' ' . $tutor->last_name);
        
        // Parse services and prices
        $services = !empty($tutor->services) ? explode(', ', $tutor->services) : array();
        $service_ids = !empty($tutor->service_ids) ? explode(', ', $tutor->service_ids) : array();
        $prices = !empty($tutor->prices) ? explode(', ', $tutor->prices) : array();
        
        ob_start();
        ?>
        <div class="enhanced-popup-header">
            <div class="enhanced-popup-avatar">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($full_name); ?>">
            </div>
            <div class="enhanced-popup-title">
                <h2><?php echo esc_html($full_name); ?></h2>
                <?php if (!empty($tutor->country)): ?>
                    <div class="enhanced-popup-location">
                        <?php echo esc_html($tutor->country); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="enhanced-popup-content">
            <?php if (!empty($tutor->bio)): ?>
                <div class="enhanced-popup-section">
                    <h3>About</h3>
                    <p><?php echo esc_html($tutor->bio); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($services)): ?>
                <div class="enhanced-popup-section">
                    <h3>Subjects & Pricing</h3>
                    <div class="enhanced-services-list">
                        <?php for ($i = 0; $i < count($services); $i++): ?>
                            <div class="enhanced-service-item">
                                <span class="service-name"><?php echo esc_html($services[$i]); ?></span>
                                <span class="service-price">
                                    <?php if (isset($prices[$i]) && !empty($prices[$i])): ?>
                                        $<?php echo esc_html(number_format(floatval($prices[$i]), 0)); ?>/hr
                                    <?php else: ?>
                                        Contact for pricing
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="enhanced-popup-details">
                <?php if (!empty($tutor->experience)): ?>
                    <div class="enhanced-detail-item">
                        <span class="detail-label">Experience:</span>
                        <span class="detail-value"><?php echo esc_html($tutor->experience); ?> years</span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->education)): ?>
                    <div class="enhanced-detail-item">
                        <span class="detail-label">Education:</span>
                        <span class="detail-value"><?php echo esc_html($tutor->education); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->languages)): ?>
                    <div class="enhanced-detail-item">
                        <span class="detail-label">Languages:</span>
                        <span class="detail-value"><?php echo esc_html($tutor->languages); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->qualifications)): ?>
                    <div class="enhanced-detail-item">
                        <span class="detail-label">Qualifications:</span>
                        <span class="detail-value"><?php echo esc_html($tutor->qualifications); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->teaching_style)): ?>
                    <div class="enhanced-detail-item">
                        <span class="detail-label">Teaching Style:</span>
                        <span class="detail-value"><?php echo esc_html($tutor->teaching_style); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="enhanced-popup-actions">
                <?php $current_user = wp_get_current_user(); ?>
                <button class="latepoint-btn latepoint-btn-primary enhanced-book-tutor<?php echo in_array('latepoint_agent', $current_user->roles) ? ' disabled' : ''; ?>" 
                        data-selected-agent="<?php echo esc_attr($tutor->id); ?>"
                        <?php echo in_array('latepoint_agent', $current_user->roles) ? 'onclick="alert(\"Booking is for students only. You are a tutor.\"); return false;" style="cursor: default;"' : ''; ?>>
                    Book a Session
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the class
new Darsna_Tutor_Tutors_Page();
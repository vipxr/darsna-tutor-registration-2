<?php

class Darsna_Tutor_Tutors_Page {
    
    public function __construct() {
        add_shortcode('darsna_tutors_page', array($this, 'render_tutors_page'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_filter_tutors', array($this, 'ajax_filter_tutors'));
        add_action('wp_ajax_nopriv_filter_tutors', array($this, 'ajax_filter_tutors'));
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
    
    public function render_tutors_page($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'show_filters' => 'yes'
        ), $atts);
        
        ob_start();
        
        // Get all tutors
        $tutors = $this->get_all_tutors();
        $countries = $this->get_unique_countries($tutors);
        $subjects = $this->get_unique_subjects($tutors);
        
        ?>
        <div class="darsna-tutors-page">
            <?php if ($atts['show_filters'] === 'yes'): ?>
            <div class="tutors-filters">
                <div class="filters-container">
                    <div class="search-box">
                        <input type="text" id="tutor-search" placeholder="Search tutors by name or expertise..." class="search-input">
                        <span class="search-icon">🔍</span>
                    </div>
                    
                    <div class="filter-group">
                        <select id="country-filter" class="filter-select">
                            <option value="">All Countries</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select id="subject-filter" class="filter-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo esc_attr($subject); ?>"><?php echo esc_html($subject); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select id="price-filter" class="filter-select">
                            <option value="">All Prices</option>
                            <option value="0-20">$0 - $20/hr</option>
                            <option value="20-40">$20 - $40/hr</option>
                            <option value="40-60">$40 - $60/hr</option>
                            <option value="60-100">$60 - $100/hr</option>
                            <option value="100+">$100+/hr</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select id="sort-filter" class="filter-select">
                            <option value="name">Sort by Name</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="rating">Highest Rated</option>
                        </select>
                    </div>
                    
                    <button id="clear-filters" class="clear-btn">Clear All</button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="tutors-grid" id="tutors-grid">
                <?php echo $this->render_tutors_grid($tutors); ?>
            </div>
            
            <div class="loading-spinner" id="loading-spinner" style="display: none;">
                <div class="spinner"></div>
                <p>Loading tutors...</p>
            </div>
            
            <div class="no-results" id="no-results" style="display: none;">
                <div class="no-results-content">
                    <h3>No tutors found</h3>
                    <p>Try adjusting your filters or search terms.</p>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    private function get_all_tutors() {
        global $wpdb;
        
        $query = "
            SELECT DISTINCT
                a.id,
                a.first_name,
                a.last_name,
                a.email,
                a.phone,
                a.avatar_image_id,
                a.bio_short,
                a.extra_data,
                GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as services,
                GROUP_CONCAT(DISTINCT COALESCE(cp.charge_amount, s.charge_amount) SEPARATOR ', ') as prices,
                MIN(COALESCE(cp.charge_amount, s.charge_amount)) as min_price,
                MAX(COALESCE(cp.charge_amount, s.charge_amount)) as max_price
            FROM {$wpdb->prefix}latepoint_agents a
            LEFT JOIN {$wpdb->prefix}latepoint_agents_services ags ON a.id = ags.agent_id
            LEFT JOIN {$wpdb->prefix}latepoint_services s ON ags.service_id = s.id
            LEFT JOIN {$wpdb->prefix}latepoint_custom_prices cp ON (
                cp.agent_id = a.id AND 
                cp.service_id = s.id
            )
            WHERE a.status = 'active'
            GROUP BY a.id
            ORDER BY a.first_name, a.last_name
        ";
        
        $results = $wpdb->get_results($query);
        
        // Process extra data
        foreach ($results as &$tutor) {
            if ($tutor->extra_data) {
                $extra_data = json_decode($tutor->extra_data, true);
                $tutor->country = isset($extra_data['country']) ? $extra_data['country'] : '';
                $tutor->city = isset($extra_data['city']) ? $extra_data['city'] : '';
                $tutor->experience = isset($extra_data['experience']) ? $extra_data['experience'] : '';
                $tutor->education = isset($extra_data['education']) ? $extra_data['education'] : '';
                $tutor->languages = isset($extra_data['languages']) ? $extra_data['languages'] : '';
            } else {
                $tutor->country = '';
                $tutor->city = '';
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
        $avatar_url = $this->get_avatar_url($tutor->avatar_image_id);
        $full_name = trim($tutor->first_name . ' ' . $tutor->last_name);
        $price_range = $this->format_price_range($tutor->min_price, $tutor->max_price);
        
        ob_start();
        ?>
        <div class="tutor-card" 
             data-country="<?php echo esc_attr($tutor->country); ?>"
             data-services="<?php echo esc_attr(strtolower($tutor->services)); ?>"
             data-min-price="<?php echo esc_attr($tutor->min_price); ?>"
             data-max-price="<?php echo esc_attr($tutor->max_price); ?>"
             data-name="<?php echo esc_attr(strtolower($full_name)); ?>">
            
            <div class="tutor-avatar">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($full_name); ?>" class="avatar-img">
                <div class="online-status"></div>
            </div>
            
            <div class="tutor-info">
                <h3 class="tutor-name"><?php echo esc_html($full_name); ?></h3>
                
                <?php if (!empty($tutor->country)): ?>
                    <div class="tutor-location">
                        <span class="location-icon">📍</span>
                        <?php echo esc_html($tutor->country); ?>
                        <?php if (!empty($tutor->city)): ?>
                            , <?php echo esc_html($tutor->city); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($tutor->bio_short)): ?>
                    <p class="tutor-bio"><?php echo esc_html(wp_trim_words($tutor->bio_short, 20)); ?></p>
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
                    <button class="btn btn-primary contact-tutor" data-tutor-id="<?php echo esc_attr($tutor->id); ?>">
                        Contact Tutor
                    </button>
                    <button class="btn btn-secondary view-profile" data-tutor-id="<?php echo esc_attr($tutor->id); ?>">
                        View Profile
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_avatar_url($avatar_image_id) {
        if ($avatar_image_id) {
            $avatar_url = wp_get_attachment_image_url($avatar_image_id, 'thumbnail');
            if ($avatar_url) {
                return $avatar_url;
            }
        }
        
        // Default avatar
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="#e1e5e9"/><circle cx="50" cy="35" r="15" fill="#9ca3af"/><path d="M20 80c0-16.569 13.431-30 30-30s30 13.431 30 30" fill="#9ca3af"/></svg>');
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
        $sort = sanitize_text_field($_POST['sort'] ?? 'name');
        
        $tutors = $this->get_filtered_tutors($search, $country, $subject, $price_range, $sort);
        
        wp_send_json_success(array(
            'html' => $this->render_tutors_grid($tutors),
            'count' => count($tutors)
        ));
    }
    
    private function get_filtered_tutors($search, $country, $subject, $price_range, $sort) {
        $tutors = $this->get_all_tutors();
        
        // Apply filters
        $filtered_tutors = array_filter($tutors, function($tutor) use ($search, $country, $subject, $price_range) {
            // Search filter
            if (!empty($search)) {
                $search_text = strtolower($search);
                $tutor_text = strtolower($tutor->first_name . ' ' . $tutor->last_name . ' ' . $tutor->services . ' ' . $tutor->bio_short);
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
}

// Initialize the class
new Darsna_Tutor_Tutors_Page();
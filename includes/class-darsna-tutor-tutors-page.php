<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Darsna_Tutor_Tutors_Page {
  
  public function __construct() {
    // Register shortcode
    add_shortcode( 'darsna_tutors', [ $this, 'render_tutors_page' ] );
    
    // Enqueue scripts and styles
    add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    
    // AJAX handlers
    add_action( 'wp_ajax_darsna_load_more_tutors', [ $this, 'ajax_load_more_tutors' ] );
    add_action( 'wp_ajax_nopriv_darsna_load_more_tutors', [ $this, 'ajax_load_more_tutors' ] );
  }

  public function enqueue_scripts() {
    if ( ! wp_script_is( 'jquery', 'enqueued' ) ) {
      wp_enqueue_script( 'jquery' );
    }

    wp_enqueue_style(
      'darsna-tutors-page-style',
      plugin_dir_url( __FILE__ ) . '../assets/css/tutors-page.css',
      [],
      '1.0.0'
    );

    wp_enqueue_script(
      'darsna-tutors-page-script',
      plugin_dir_url( __FILE__ ) . '../assets/js/tutors-page.js',
      [ 'jquery' ],
      '1.0.0',
      true
    );

    if ( class_exists( 'OsAssetsHelper' ) ) {
      if ( method_exists( 'OsAssetsHelper', 'load_latepoint_lightbox_assets' ) ) {
        OsAssetsHelper::load_latepoint_lightbox_assets();
      }
      if ( method_exists( 'OsAssetsHelper', 'load_latepoint_css_bundle' ) ) {
        OsAssetsHelper::load_latepoint_css_bundle();
      }
      if ( method_exists( 'OsAssetsHelper', 'load_latepoint_js_bundle' ) ) {
        OsAssetsHelper::load_latepoint_js_bundle();
      }
    }

    wp_localize_script( 'darsna-tutors-page-script', 'darsna_tutors_ajax', [
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'nonce'    => wp_create_nonce( 'darsna_tutors_nonce' ),
    ] );
  }

  public function render_tutors_page( $attributes = [] ) {
    $processed_attributes = $this->process_shortcode_attributes( $attributes );
    $tutors_result = $this->get_active_tutors( 1, $processed_attributes['per_page'] );
    $all_subjects = $this->get_unique_subjects( $tutors_result['tutors'] );
    $all_countries = $this->get_unique_countries( $tutors_result['tutors'] );
    ob_start();
    ?>
    <div class="darsna-tutors-page fiverr-layout" data-per-page="<?php echo esc_attr( $processed_attributes['per_page'] ); ?>">
      <!-- Mobile Filter Toggle -->
      <div class="mobile-filter-toggle">
        <button id="mobile-filter-btn" class="mobile-filter-btn">
          <span class="filter-icon">⚙</span>
          <span class="filter-text">Filters</span>
          <span class="results-count"><?php echo count($tutors_result['tutors']); ?> results</span>
        </button>
      </div>

      <div class="tutors-layout-container">
        <!-- Left Sidebar with Filters -->
        <?php if ( $processed_attributes['show_filters'] ) : ?>
          <aside class="tutors-sidebar" id="tutors-sidebar">
            <div class="sidebar-header">
              <h3 class="sidebar-title">Find Tutors</h3>
              <button class="sidebar-close" id="sidebar-close">×</button>
            </div>
            <div class="sidebar-content">
              <?php echo $this->render_sidebar_filters( $all_subjects, $all_countries ); ?>
            </div>
          </aside>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main class="tutors-main-content">
          <!-- Results Header -->
          <div class="results-header">
            <div class="results-info">
              <h2 class="results-title">Find Your Perfect Tutor</h2>
              <p class="results-count-text">
                <span id="results-count"><?php echo count($tutors_result['tutors']); ?></span> qualified tutors available
              </p>
            </div>
            <div class="results-sort">
              <?php echo $this->render_sort_dropdown(); ?>
            </div>
          </div>

          <!-- Tutors Grid -->
          <div class="darsna-tutors-container">
            <?php echo $this->render_tutors_grid( $tutors_result['tutors'] ); ?>
            
            <div id="no-results" class="darsna-no-results hidden">
              <div class="no-results-content">
                <div class="no-results-icon">🔍</div>
                <h3>No tutors found</h3>
                <p><?php esc_html_e( 'Try adjusting your filters or search terms.', 'darsna-tutor-registration' ); ?></p>
                <button class="clear-all-filters">Clear All Filters</button>
              </div>
            </div>
            
            <?php if ( $tutors_result['has_more'] ) : ?>
              <div class="darsna-load-more-container">
                <button id="darsna-load-more-btn" class="darsna-load-more-btn" data-page="2">
                  <span class="load-more-text"><?php esc_html_e( 'Load More Tutors', 'darsna-tutor-registration' ); ?></span>
                  <span class="load-more-icon">↓</span>
                </button>
              </div>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>
    
    <script type="text/javascript">
    var darsna_tutors = {
        ajax_url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
        nonce: '<?php echo wp_create_nonce( 'darsna_tutors_nonce' ); ?>',
        per_page: <?php echo intval( $processed_attributes['per_page'] ); ?>
    };
    </script>
    <?php
    return ob_get_clean();
  }

  private function process_shortcode_attributes( array $attributes ): array {
    return shortcode_atts( [
      'per_page'     => 50,
      'show_filters' => true,
    ], $attributes, 'darsna_tutors' );
  }

  private function render_filter_section( array $all_subjects, array $all_countries ): string {
    $html = '<div class="darsna-filters">';
    $html .= '<div class="filter-row">';
    $html .= $this->render_search_input();
    $html .= $this->render_dropdown_filters( $all_subjects, $all_countries );
    $html .= '</div>';
    $html .= '<div class="filter-row">';
    $html .= $this->render_clear_button();
    $html .= '</div>';
    $html .= '</div>';
    return $html;
  }

  private function render_sidebar_filters( array $all_subjects, array $all_countries ): string {
    $html = '<div class="sidebar-filters">';
    $html .= '<div class="filter-group">';
    $html .= '<label class="filter-label">Search Tutors</label>';
    $html .= '<div class="search-wrapper">';
    $html .= '<input type="text" id="tutor-search" class="filter-input search-input" placeholder="Search by tutor name or subject..." />';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="filter-group">';
    $html .= '<label class="filter-label">Subject</label>';
    $html .= '<div class="select-wrapper">';
    $html .= '<select id="subject-filter" class="filter-select">';
    $html .= '<option value="">All Subjects</option>';
    foreach ( $all_subjects as $subject_name ) {
      $html .= '<option value="' . esc_attr( $subject_name ) . '">' . esc_html( $subject_name ) . '</option>';
    }
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="filter-group">';
    $html .= '<label class="filter-label">Location</label>';
    $html .= '<div class="select-wrapper">';
    $html .= '<select id="location-filter" class="filter-select">';
    $html .= '<option value="">All Locations</option>';
    foreach ( $all_countries as $country_name ) {
      $html .= '<option value="' . esc_attr( $country_name ) . '">' . esc_html( $country_name ) . '</option>';
    }
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="filter-group">';
    $html .= '<label class="filter-label">Hourly Rate</label>';
    $html .= '<div class="price-range-options">';
    $html .= '<label class="price-option"><input type="radio" name="price-range" value="" checked> Any rate</label>';
    $html .= '<label class="price-option"><input type="radio" name="price-range" value="0-30"> Under $30/hr</label>';
    $html .= '<label class="price-option"><input type="radio" name="price-range" value="30-50"> $30 - $50/hr</label>';
    $html .= '<label class="price-option"><input type="radio" name="price-range" value="50-100"> $50 - $100/hr</label>';
    $html .= '<label class="price-option"><input type="radio" name="price-range" value="100+"> $100+/hr</label>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="filter-group">';
    $html .= '<button id="clear-filters" class="clear-filters-btn sidebar-clear-btn">Clear All Filters</button>';
    $html .= '</div>';
    
    $html .= '</div>';
    return $html;
  }

  private function render_sort_dropdown(): string {
    return '<div class="sort-wrapper">' .
           '<label for="sort-filter" class="sort-label">Sort by:</label>' .
           '<select id="sort-filter" class="sort-select">' .
           '<option value="relevance">Relevance</option>' .
           '<option value="price-low">Price: Low to High</option>' .
           '<option value="price-high">Price: High to Low</option>' .
           '<option value="rating">Best Rating</option>' .
           '<option value="name">Name A-Z</option>' .
           '</select>' .
           '</div>';
  }

  private function render_search_input(): string {
    return '<input type="text" id="tutor-search" class="search-input" placeholder="Search tutors by name..." />';
  }

  private function render_dropdown_filters( array $all_subjects, array $all_countries ): string {
    $html = '';
    $html .= $this->render_subject_filter( $all_subjects );
    $html .= $this->render_location_filter( $all_countries );
    $html .= $this->render_price_filter();
    $html .= $this->render_sort_filter();
    return $html;
  }

  private function render_subject_filter( array $all_subjects ): string {
    $html = '<select id="subject-filter" class="filter-select">' .
            '<option value="">All Subjects</option>';
    
    foreach ( $all_subjects as $subject_name ) {
      $html .= '<option value="' . esc_attr( $subject_name ) . '">' . esc_html( $subject_name ) . '</option>';
    }
    
    $html .= '</select>';
    return $html;
  }

  private function render_location_filter( array $all_countries ): string {
    $html = '<select id="location-filter" class="filter-select">' .
            '<option value="">All Locations</option>';
    
    foreach ( $all_countries as $country_name ) {
      $html .= '<option value="' . esc_attr( $country_name ) . '">' . esc_html( $country_name ) . '</option>';
    }
    
    $html .= '</select>';
    return $html;
  }

  private function render_price_filter(): string {
    return '<select id="price-filter" class="filter-select">' .
           '<option value="">All Prices</option>' .
           '<option value="0-30">$0 - $30</option>' .
           '<option value="30-50">$30 - $50</option>' .
           '<option value="50-100">$50 - $100</option>' .
           '<option value="100+">$100+</option>' .
           '</select>';
  }

  private function render_sort_filter(): string {
    return '<select id="sort-filter" class="filter-select">' .
           '<option value="name">Sort by Name</option>' .
           '<option value="price-low">Price: Low to High</option>' .
           '<option value="price-high">Price: High to Low</option>' .
           '</select>';
  }

  private function render_clear_button(): string {
    return '<button id="clear-filters" class="clear-filters-btn">Clear Filters</button>';
  }

  private function get_active_tutors( $page = 1, $per_page = 50 ) {
    try {
      if ( ! ( class_exists( 'OsAgentHelper' ) && method_exists( 'OsAgentHelper', 'get_allowed_active_agents' ) ) ) {

        throw new Exception( 'LatePoint not available (OsAgentHelper::get_allowed_active_agents).' );
      }
      $agents = [];
      if ( class_exists( 'OsAgentHelper' ) && method_exists( 'OsAgentHelper', 'get_allowed_active_agents' ) ) {
        $agents = OsAgentHelper::get_allowed_active_agents();
      }
      if ( empty( $agents ) && class_exists( 'OsAgentHelper' ) && method_exists( 'OsAgentHelper', 'get_agents' ) ) {
        $agents = OsAgentHelper::get_agents();
      }
      if ( empty( $agents ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'latepoint_agents';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
          $agents = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'active'" );
        } else {

        }
      }
      $tutors = [];
      foreach ( (array) $agents as $agent ) {
        try {
          $tutor = $this->map_agent_to_tutor( $agent );
          $tutors[] = $tutor;

        } catch ( Exception $e ) {

        }
      }
      $offset          = max( 0, ( $page - 1 ) * $per_page );
      $paginated       = array_slice( $tutors, $offset, $per_page );
      $total           = count( $tutors );
      
      return [
        'tutors'   => $paginated,
        'total'    => $total,
        'page'     => (int) $page,
        'per_page' => (int) $per_page,
        'has_more' => ( $offset + $per_page ) < $total,
      ];
    } catch ( Exception $e ) {

      return [ 'tutors' => [], 'total' => 0, 'page' => (int) $page, 'per_page' => (int) $per_page, 'has_more' => false ];
    }
  }
  private function map_agent_to_tutor( $agent ) {

    $tutor_data = (object) [
      'id'              => $agent->id,
      'first_name'      => $agent->first_name,
      'last_name'       => $agent->last_name,
      'email'           => $agent->email,
      'bio'             => $agent->bio ?? '',
      'wp_user_id'      => $agent->wp_user_id,
      'avatar_image_id' => $agent->avatar_image_id ?? null,
      'services'        => '',
      'subjects'        => [],
      'min_price'       => 0,
      'max_price'       => 0,
      'country'         => '',
      'hourly_rate'     => '',
    ];
    
    // Retrieve services and pricing from LatePoint connections
    $service_names = [];
    $service_prices = [];
    if ( class_exists( 'OsConnectorHelper' ) && method_exists( 'OsConnectorHelper', 'get_connected_object_ids' ) ) {
      // Get services connected to this agent
      $connected_service_ids = [];
      if ( class_exists( 'OsConnectorHelper' ) && method_exists( 'OsConnectorHelper', 'get_connected_object_ids' ) ) {
        $connected_service_ids = (array) OsConnectorHelper::get_connected_object_ids( 'service_id', [ 'agent_id' => $agent->id ] );
      }
      $available_services = [];
      
      // Load service models for connected services
      if ( $connected_service_ids && class_exists( 'OsServiceModel' ) ) {
        foreach ( $connected_service_ids as $service_id ) {
          $service_model = new OsServiceModel( $service_id );
          if ( ! empty( $service_model->id ) ) {
            $available_services[] = $service_model;

          }
        }
      }
      
      // Extract service names and prices
        foreach ( $available_services as $service_model ) {
          // Safely access service name property
          if (is_object($service_model) && isset($service_model->name)) {
            $service_names[] = $service_model->name;
          } else {
            $service_names[] = '';
          }
          
          // Try to get agent-specific pricing, fallback to service default
          if ( class_exists( 'OsPricingHelper' ) && method_exists( 'OsPricingHelper', 'get_agent_service_price' ) ) {
            $agent_price = OsPricingHelper::get_agent_service_price( $agent->id, $service_model->id );
            if (is_object($service_model) && isset($service_model->charge_amount)) {
              $service_prices[] = ( $agent_price !== null && $agent_price !== '' ) ? $agent_price : $service_model->charge_amount;
            } else {
              $service_prices[] = ( $agent_price !== null && $agent_price !== '' ) ? $agent_price : 0;
            }
          } else {
            if (is_object($service_model) && isset($service_model->charge_amount)) {
              $service_prices[] = $service_model->charge_amount;
            } else {
              $service_prices[] = 0;
            }
          }
        }
    } else {

    }
    
    // Set subject names and calculate price range
        $tutor_data->subjects_string = implode( ', ', $service_names );
    if ( $service_prices ) {
      $tutor_data->min_price = min( $service_prices );
      $tutor_data->max_price = max( $service_prices );

    }

    // Retrieve additional tutor information from WordPress user meta
    if ( $tutor_data->wp_user_id ) {
      $tutor_data->hourly_rate = get_user_meta( $tutor_data->wp_user_id, 'tutor_hourly_rate', true );
      $tutor_data->country = $this->get_user_country( $tutor_data->wp_user_id );
      
      // Get subjects from user meta and resolve to names
      $stored_subject_ids = get_user_meta( $tutor_data->wp_user_id, 'tutor_subjects', true );
      $tutor_data->subjects = is_array( $stored_subject_ids ) ? $this->get_service_names_by_ids( $stored_subject_ids ) : [];

      // Use hourly rate as fallback pricing if no LatePoint prices available
      if ( ! $tutor_data->min_price && ! $tutor_data->max_price && $tutor_data->hourly_rate !== '' ) {
        $tutor_data->min_price = $tutor_data->max_price = (float) $tutor_data->hourly_rate;

      }
    } else {

    }
    
    return $tutor_data;
  }

  /** Resolve service IDs to names using our custom Darsna integration */
  private function get_service_names_by_ids( array $service_ids ): array {
    if ( empty( $service_ids ) ) return [];

    $service_names = [];

    // Use Darsna integration for service names
    if ( class_exists( 'Darsna_Registration_System' ) ) {
      try {
        $registration_handler = Darsna_Registration_System::get_instance();
        if ( method_exists( $registration_handler, 'get_subjects' ) ) {
                $available_subjects = (array) $registration_handler->get_subjects();
          foreach ( $service_ids as $service_id ) {
            foreach ( $available_subjects as $subject_data ) {
              if ( isset( $subject_data['id'] ) && (int) $subject_data['id'] === (int) $service_id ) {
                $service_names[] = $subject_data['name'];
                break;
              }
            }
          }
        }
      } catch ( Exception $e ) {}
    }

    return $service_names;
  }

  /** Country from user meta, friendly name if WooCommerce is present */
  private function get_user_country( $user_id ) {
    if ( ! $user_id ) return '';
    
    // Check primary country meta keys in order of preference
    $code = get_user_meta( $user_id, 'billing_country', true );
    if ( ! $code ) {
      $code = get_user_meta( $user_id, 'country', true );
    }
    
    if ( ! $code ) return '';

    // Convert country code to friendly name if WooCommerce is available
    if ( WC() && isset( WC()->countries ) ) {
      $countries = WC()->countries->get_countries();
      return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
    }
    return $code;
  }

  // ========================================
  // FILTER DATA EXTRACTION
  // ========================================

  /** Extract unique subjects from all tutors for filter dropdown */
  private function get_unique_subjects( array $tutors_data ): array {
    $all_subjects = [];
    
    // Collect all subjects from all tutors
    foreach ( $tutors_data as $tutor_data ) {
      if ( ! empty( $tutor_data->subjects ) && is_array( $tutor_data->subjects ) ) {
        $all_subjects = array_merge( $all_subjects, $tutor_data->subjects );
      }
    }
    
    // Remove duplicates and return sorted list
    return array_unique( $all_subjects );
  }

  /** Extract unique countries from all tutors for filter dropdown */
  private function get_unique_countries( array $tutors_data ): array {
    $all_countries = [];
    
    // Collect all countries from all tutors
    foreach ( $tutors_data as $tutor_data ) {
      if ( ! empty( $tutor_data->country ) ) {
        $all_countries[] = $tutor_data->country;
      }
    }
    
    // Remove duplicates and return sorted list
    return array_unique( $all_countries );
  }

  // ========================================
  // TUTOR DISPLAY RENDERING
  // ========================================

  /** Render the main tutors grid container and individual tutor cards */
  private function render_tutors_grid( array $tutors_data ): string {
    $html = '<div class="tutors-grid" id="tutors-grid">';
    
    // Generate individual tutor cards
    foreach ( $tutors_data as $tutor_data ) {
      $html .= $this->render_tutor_card( $tutor_data );
    }
    
    $html .= '</div>';
    return $html;
  }

  /** Render individual tutor card with avatar, details, and pricing */
  private function render_tutor_card( $tutor_data ): string {
    // Get tutor avatar image
    $avatar_url = $this->get_tutor_avatar( $tutor_data );
    
    // Format pricing display
    $price_display = $this->format_price_range( $tutor_data->min_price, $tutor_data->max_price );
    
    // Build subjects display string
    $subjects_display = is_array( $tutor_data->subjects ) ? implode( ', ', $tutor_data->subjects ) : '';
    $subjects_string = is_array( $tutor_data->subjects ) ? implode( ',', array_map( 'strtolower', $tutor_data->subjects ) ) : '';
    
    // Safely get bio content with fallback
    $bio_content = wp_strip_all_tags( $tutor_data->bio );
    
    // Full name for data attributes
    $full_name = $tutor_data->first_name . ' ' . $tutor_data->last_name;
    
    // Generate educational-focused tutor card
    $experience_years = rand( 2, 10 ); // Placeholder for experience
    $qualification = $this->get_tutor_qualification( $tutor_data );
    
    // Generate the complete educational tutor card HTML
    return '<div class="tutor-card educational-card" data-tutor-id="' . esc_attr( $tutor_data->id ) . '" data-name="' . esc_attr( strtolower( $full_name ) ) . '" data-location="' . esc_attr( strtolower( $tutor_data->country ) ) . '" data-subjects="' . esc_attr( $subjects_string ) . '" data-price="' . esc_attr( $tutor_data->min_price ) . '">' .
           '<div class="tutor-card-header">' .
           '<div class="tutor-avatar">' .
           '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $full_name ) . '" />' .
           '</div>' .
           '<div class="tutor-basic-info">' .
           '<h3 class="tutor-name">' . esc_html( $full_name ) . '</h3>' .
           '<p class="tutor-qualification">' . esc_html( $qualification ) . '</p>' .
           '<div class="tutor-location">' .
           '<span class="location-icon">📍</span>' .
           '<span class="location-text">' . esc_html( $tutor_data->country ) . '</span>' .
           '</div>' .
           '</div>' .
           '</div>' .
           '<div class="tutor-card-body">' .
           '<div class="tutor-subjects">' .
           '<h4 class="subjects-title">Subjects</h4>' .
           '<div class="subjects-list">' . esc_html( $subjects_display ) . '</div>' .
           '</div>' .
           ( $bio_content ? '<div class="tutor-bio">' . esc_html( wp_trim_words( $bio_content, 20 ) ) . '</div>' : '' ) .
           '</div>' .
           '<div class="tutor-card-footer">' .
           '<div class="tutor-experience">' .
           '<span class="experience-icon">🎓</span>' .
           '<span class="experience-text">' . $experience_years . '+ years experience</span>' .
           '</div>' .
           '<div class="tutor-pricing">' .
           '<span class="price-label">From</span>' .
           '<span class="price-amount">' . $price_display . '/hr</span>' .
           '</div>' .
           '</div>' .
           '<button class="book-tutor-btn book-now-btn" data-agent-id="' . esc_attr( $tutor_data->id ) . '">Book Session</button>' .
           '</div>';
  }

  /** Get tutor qualification based on available data */
  private function get_tutor_qualification( $tutor_data ): string {
    // Try to get qualification from user meta or bio
    if ( !empty( $tutor_data->bio ) ) {
      // Look for common qualification keywords in bio
      $bio_lower = strtolower( $tutor_data->bio );
      if ( strpos( $bio_lower, 'phd' ) !== false || strpos( $bio_lower, 'doctorate' ) !== false ) {
        return 'PhD Qualified';
      } elseif ( strpos( $bio_lower, 'master' ) !== false || strpos( $bio_lower, 'msc' ) !== false || strpos( $bio_lower, 'ma ' ) !== false ) {
        return 'Masters Degree';
      } elseif ( strpos( $bio_lower, 'bachelor' ) !== false || strpos( $bio_lower, 'bsc' ) !== false || strpos( $bio_lower, 'ba ' ) !== false ) {
        return 'Bachelor\'s Degree';
      } elseif ( strpos( $bio_lower, 'certified' ) !== false || strpos( $bio_lower, 'certificate' ) !== false ) {
        return 'Certified Tutor';
      }
    }
    
    // Default qualification
    return 'Qualified Tutor';
  }

  /** Get tutor avatar URL, with fallback to default avatar */
  private function get_tutor_avatar( $tutor_data ): string {
    // Try to get custom avatar from LatePoint
    if ( $tutor_data->avatar_image_id ) {
      $custom_avatar = wp_get_attachment_image_url( $tutor_data->avatar_image_id, 'thumbnail' );
      if ( $custom_avatar ) return $custom_avatar;
    }
    
    // Fallback to WordPress Gravatar if user has WP account
    if ( $tutor_data->wp_user_id ) {
      return get_avatar_url( $tutor_data->wp_user_id, [ 'size' => 150 ] );
    }
    
    // Final fallback to email-based Gravatar
    return get_avatar_url( $tutor_data->email, [ 'size' => 150 ] );
  }

  /** Format price range for display, handling single prices and ranges */
  private function format_price_range( $min_price, $max_price ): string {
    // No pricing information available
    if ( ! $min_price && ! $max_price ) {
      return esc_html__( 'Contact for pricing', 'darsna-tutor-registration' );
    }
    
    // Single price point (min equals max)
    if ( $min_price == $max_price ) {
      return wc_price( $min_price );
    }
    
    // Price range (different min and max)
    $min_formatted = wc_price( $min_price );
    $max_formatted = wc_price( $max_price );
    return $min_formatted . ' - ' . $max_formatted;
  }

  // ========================================
  // AJAX HANDLERS
  // ========================================

  /** AJAX handler for infinite scroll pagination */
  public function ajax_load_more_tutors() {
    // Verify nonce for security
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'darsna_tutors_nonce' ) ) {
      wp_send_json_error( 'Security check failed' );
      return;
    }

    // Get pagination parameters from AJAX request
    $page = max( 1, intval( $_POST['page'] ?? 1 ) );
    $per_page = max( 1, min( 100, intval( $_POST['per_page'] ?? 50 ) ) );

    // Retrieve tutors for the requested page
    $tutors_result = $this->get_active_tutors( $page, $per_page );
    
    // Generate HTML for the new tutors
    $tutors_html = '';
    foreach ( $tutors_result['tutors'] as $tutor_data ) {
      $tutors_html .= $this->render_tutor_card( $tutor_data );
    }

    // Return JSON response with tutor HTML and pagination info
    wp_send_json_success( [
      'html'     => $tutors_html,
      'has_more' => $tutors_result['has_more'],
      'page'     => $tutors_result['page'],
      'total'    => $tutors_result['total'],
    ] );
  }

  /** Format a single price value for display */
  private function format_single_price( $price ): string {
    return wc_price( $price );
  }
}

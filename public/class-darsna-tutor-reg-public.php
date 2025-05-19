<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       
 * @since      1.0.1
 *
 * @package    Darsna_Tutor_Reg
 * @subpackage Darsna_Tutor_Reg/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for 
 * enqueueing the public-specific stylesheet and JavaScript,
 * and all other public-facing logic.
 *
 * @package    Darsna_Tutor_Reg
 * @subpackage Darsna_Tutor_Reg/public
 * @author     Your Name <you@example.com>
 */
class Darsna_Tutor_Reg_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.1
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.1
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.1
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.1
     */
    public function enqueue_styles() {
        if ( function_exists('is_account_page') && is_account_page() ) {
            wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css', array(), '4.1.0', 'all' );
            // You can enqueue a custom stylesheet here if needed for your fields
            // wp_enqueue_style( $this->plugin_name, DARSNA_TUTOR_REG_PLUGIN_URL . 'public/css/darsna-tutor-reg-public.css', array(), $this->version, 'all' );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.1
     */
    public function enqueue_scripts() {
        if ( function_exists('is_account_page') && is_account_page() ) {
            wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
            // You can enqueue a custom script here for your fields' interactivity
            // wp_enqueue_script( $this->plugin_name, DARSNA_TUTOR_REG_PLUGIN_URL . 'public/js/darsna-tutor-reg-public.js', array( 'jquery', 'select2' ), $this->version, true );
            /*wp_localize_script( $this->plugin_name, 'darsna_tutor_reg_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' )
            ) );*/
        }
    }

    /**
     * Render custom fields on WooCommerce registration and edit account forms.
     *
     * @since 1.0.1
     */
    public function render_tutor_fields() {
        // Prevent duplicate rendering of fields
        static $already_rendered = false;
        if ($already_rendered) {
            return;
        }
        $already_rendered = true;
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $is_tutor = in_array( 'tutor', (array) $user->roles, true ) || in_array( 'latepoint_agent', (array) $user->roles, true );
            // On edit account, only show if user is tutor or if it's registration page
            // This logic might need adjustment based on exact requirements for role switching
            if ( is_account_page() && ! is_wc_endpoint_url( 'edit-account' ) && ! $is_tutor && !is_page( 'my-account/edit-account' ) && !is_wc_endpoint_url( 'lost-password' ) && !is_wc_endpoint_url( 'customer-logout' ) && !is_wc_endpoint_url( 'add-payment-method' ) ) {
                 // If it's some other account page and user is not tutor, don't show.
                 // The above condition is a bit complex, might need simplification based on where exactly these fields should appear.
                 // The original logic was: if ( !is_page('my-account/edit-account') && !$current_user_is_tutor )
                 // Let's try to stick to edit-account or registration
                 if ( !is_wc_endpoint_url( 'edit-account' ) && !$is_tutor && !(isset($_GET['action']) && $_GET['action'] === 'register')){
                    // return; // This was too restrictive, let's refine
                 }
            }
        }

        $data = array(
            'full_name'    => isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '',
            'account_type' => isset($_POST['account_type']) ? sanitize_text_field(wp_unslash($_POST['account_type'])) : 'student',
            'hourly_rate'  => isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 0,
            'urgent_help'  => isset($_POST['urgent_help']) ? 'yes' : 'no',
            'urgent_rate'  => isset($_POST['urgent_hourly_rate']) ? floatval($_POST['urgent_hourly_rate']) : 0,
            'subject'      => '',
        );

        if (isset($_POST['subject']) && !empty($_POST['subject'])) {
            $data['subject'] = intval($_POST['subject']);
        }

        if ( is_user_logged_in() && is_wc_endpoint_url( 'edit-account' ) ) {
            $user_id = get_current_user_id();
            $user_meta = get_user_meta( $user_id );
            $current_user = wp_get_current_user();

            $first_name = isset($user_meta['first_name'][0]) ? $user_meta['first_name'][0] : '';
            $last_name = isset($user_meta['last_name'][0]) ? $user_meta['last_name'][0] : '';
            $data['full_name'] = trim($first_name . ' ' . $last_name);
            if(empty(trim($data['full_name']))) $data['full_name'] = $current_user->display_name;

            $data['account_type'] = ( in_array( 'tutor', (array) $current_user->roles, true ) || in_array( 'latepoint_agent', (array) $current_user->roles, true ) ) ? 'latepoint_agent' : 'student';
            $data['hourly_rate']  = isset( $user_meta['hourly_rate'][0] ) ? floatval( $user_meta['hourly_rate'][0] ) : 0;
            $data['urgent_help']  = (isset( $user_meta['urgent_help'][0] ) && $user_meta['urgent_help'][0] === 'yes') ? 'yes' : 'no';
            $data['urgent_rate']  = isset( $user_meta['urgent_hourly_rate'][0] ) ? floatval( $user_meta['urgent_hourly_rate'][0] ) : 0;
            $subject_meta = get_user_meta( $user_id, 'darsna_subject', true ); // Changed to 'darsna_subject'
            $data['subject'] = !empty($subject_meta) ? intval( $subject_meta ) : ''; // Store single subject ID
        }

        global $wpdb;
        $services = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}latepoint_services ORDER BY name" );

        wp_nonce_field( 'darsna_tutor_fields_action', 'darsna_tutor_nonce' );
        ?>
        <p class="form-row form-row-first">
            <label for="reg_full_name">Full Name <span class="required">*</span></label>
            <input type="text" class="input-text" name="full_name" id="reg_full_name"
                   value="<?php echo esc_attr( $data['full_name'] ); ?>" required />
        </p>
        <p class="form-row form-row-last">
            <label for="reg_account_type">Account Type <span class="required">*</span></label>
            <select name="account_type" id="reg_account_type" required>
                <option value="student" <?php selected( $data['account_type'], 'student' ); ?>>Student</option>
                <option value="latepoint_agent" <?php selected( $data['account_type'], 'latepoint_agent' ); ?>>Tutor</option>
            </select>
        </p>
        <div class="clear"></div>

        <div id="tutor_extra_fields" style="<?php echo ( $data['account_type'] === 'latepoint_agent' ) ? '' : 'display:none;'; ?>">
            <p class="form-row form-row-wide">
                <label for="reg_subject">Subject You Teach <span class="required">*</span></label> <!-- Changed label and for attribute -->
                <select name="subject" id="reg_subject" style="width:100%"> <!-- Removed multiple, changed name and id -->
                    <option value="">Select a Subject</option> <!-- Added default option -->
                    <?php if ( ! empty( $services ) ) : ?>
                        <?php foreach ( $services as $service ) : ?>
                            <option value="<?php echo intval( $service->id ); ?>"
                                <?php selected( intval( $service->id ), $data['subject'] ); ?>> <!-- Adjusted selected logic -->
                                <?php echo esc_html( $service->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <option value="" disabled>No services found in LatePoint</option>
                    <?php endif; ?>
                </select>
            </p>
            <p class="form-row form-row-wide" id="hourly_rate_field_wrapper" style="display:none;">
                <label for="reg_hourly_rate">Hourly Rate (USD) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0" class="input-text"
                       name="hourly_rate" id="reg_hourly_rate" value="<?php echo esc_attr( $data['hourly_rate'] ); ?>" />
            </p>
            <p class="form-row" id="urgent_help_wrapper" style="display:none;">
                <label>
                    <input type="checkbox" name="urgent_help" id="reg_urgent_help"
                           value="yes" <?php checked( $data['urgent_help'], 'yes' ); ?> />
                    Offer Urgent (Within the Hour) Help
                </label>
            </p>
            <p class="form-row form-row-wide" id="urgent_rate_wrap"
               style="<?php echo ( $data['urgent_help'] === 'yes' ) ? '' : 'display:none;'; ?>">
                <label for="reg_urgent_hourly_rate">Urgent Hourly Rate (USD) <span class="required">*</span></label>
                <input type="number" step="0.01" min="0" class="input-text"
                       name="urgent_hourly_rate" id="reg_urgent_hourly_rate"
                       value="<?php echo esc_attr( $data['urgent_rate'] ); ?>" />
            </p>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function toggleUrgentHelpCheckboxVisibility() {
                    var hourlyRateValue = parseFloat($('#reg_hourly_rate').val());
                    var hourlyRateFieldVisible = $('#hourly_rate_field_wrapper').is(':visible');

                    if (hourlyRateFieldVisible && hourlyRateValue > 0) {
                        $('#urgent_help_wrapper').slideDown();
                    } else {
                        $('#urgent_help_wrapper').slideUp();
                        $('#reg_urgent_help').prop('checked', false); // Uncheck if its wrapper is hidden
                    }
                    toggleUrgentRateField(); // Always update urgent rate field based on checkbox visibility and state
                }

                function toggleUrgentRateField() {
                    var urgentHelpCheckboxVisible = $('#urgent_help_wrapper').is(':visible');
                    var urgentHelpChecked = $('#reg_urgent_help').is(':checked');
                    var isTutor = $('#reg_account_type').val() === 'latepoint_agent';

                    if (isTutor && urgentHelpCheckboxVisible && urgentHelpChecked) {
                        $('#urgent_rate_wrap').slideDown();
                        $('#reg_urgent_hourly_rate').prop('required', true);
                    } else {
                        $('#urgent_rate_wrap').slideUp();
                        $('#reg_urgent_hourly_rate').prop('required', false);
                    }
                }

                function toggleHourlyRateVisibility() {
                    var subjectSelected = $('#reg_subject').val() !== '' && $('#reg_subject').val() !== null;
                    var isTutor = $('#reg_account_type').val() === 'latepoint_agent';

                    if (isTutor && subjectSelected) {
                        $('#hourly_rate_field_wrapper').slideDown();
                        $('#reg_hourly_rate').prop('required', true);
                    } else {
                        $('#hourly_rate_field_wrapper').slideUp();
                        $('#reg_hourly_rate').prop('required', false);
                    }
                    toggleUrgentHelpCheckboxVisibility(); // Update urgent help visibility when hourly rate visibility changes
                }

                function toggleTutorExtraFields() {
                    var accountType = $('#reg_account_type').val();
                    if (accountType === 'latepoint_agent') {
                        $('#tutor_extra_fields').slideDown();
                        $('#reg_subject').prop('required', true);
                    } else {
                        $('#tutor_extra_fields').slideUp();
                        $('#reg_subject, #reg_urgent_hourly_rate').prop('required', false);
                    }
                    toggleHourlyRateVisibility(); // This will cascade to toggleUrgentHelpCheckboxVisibility
                }

                function initializeSelect2(){
                    if (typeof $.fn.select2 !== 'undefined' && $('#reg_subject').length) {
                        $('#reg_subject').select2({
                            placeholder: 'Select a Subject',
                            width: '100%'
                        });
                    }
                }

                // Event Listeners
                $('#reg_account_type').on('change', function(){
                    toggleTutorExtraFields();
                    // toggleUrgentRateField(); // Not needed here, handled by cascades
                });
                $('#reg_subject').on('change', toggleHourlyRateVisibility);
                $('#reg_hourly_rate').on('input change', toggleUrgentHelpCheckboxVisibility); // For hourly rate input
                $('#reg_urgent_help').on('change', toggleUrgentRateField); // For urgent help checkbox

                // Initial state setup
                initializeSelect2();
                toggleTutorExtraFields(); // This will set up the initial state for all dependent fields through cascading calls
                
                // WooCommerce AJAX updates re-initialization
                $(document.body).on('updated_checkout updated_wc_div', function() {
                    initializeSelect2();
                    toggleTutorExtraFields(); 
                });
                // For edit account page, which might not use checkout events
                if ($('form.woocommerce-EditAccountForm').length) {
                     // initializeSelect2(); // Already called
                     // toggleTutorExtraFields(); // Already called
                }
            });
        </script>
        <div class="clear"></div>
        <?php
    }

    /**
     * Validate custom fields on WooCommerce registration.
     *
     * @since 1.0.1
     * @param WP_Error $errors      Existing registration errors.
     * @param string   $username    User's username.
     * @param string   $email       User's email.
     * @return WP_Error             Modified errors object.
     */
    // In validate_tutor_fields()
    public function validate_tutor_fields($errors, $username, $email) {
        if ( ! isset( $_POST['darsna_tutor_nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['darsna_tutor_nonce'])), 'darsna_tutor_fields_action' ) ) {
            $errors->add( 'nonce_error', __( 'Security check failed. Please refresh and try again.', 'darsna-tutor-reg' ) );
            return $errors;
        }

        if (!isset($_POST['full_name']) || empty($_POST['full_name'])) {
            $errors->add('full_name_error', __('Full Name is required.', 'darsna-tutor-reg'));
        }

        // Add debug before full_name check
        if (!isset($_POST['full_name'])) {
            darsna_debug_log("full_name is not set in _POST");
        } else {
            darsna_debug_log("full_name before check: " . gettype($_POST['full_name']) . " - " . (is_null($_POST['full_name']) ? "NULL" : $_POST['full_name']));
        }

        // Add debug before account_type sanitization
        if (isset($_POST['account_type'])) {
            darsna_debug_log("account_type before sanitize: " . gettype($_POST['account_type']) . " - " . (is_null($_POST['account_type']) ? "NULL" : $_POST['account_type']));
        } else {
            darsna_debug_log("account_type is not set in _POST");
        }
        $account_type = isset($_POST['account_type']) ? sanitize_text_field(wp_unslash($_POST['account_type'])) : '';
        if ( ! in_array( $account_type, array( 'student', 'latepoint_agent' ), true ) ) {
            $errors->add( 'account_type_error', __( 'Please select a valid Account Type (Student or Tutor).', 'darsna-tutor-reg' ) );
        }

        if ( $account_type === 'latepoint_agent' ) {
            if ( ! isset( $_POST['hourly_rate'] ) || floatval( $_POST['hourly_rate'] ) <= 0 ) {
                $errors->add( 'hourly_rate_error', __( 'A valid Hourly Rate is required for tutors.', 'darsna-tutor-reg' ) );
            }
            if (!isset($_POST['subject']) || empty($_POST['subject']) || !is_numeric($_POST['subject'])) {
                $errors->add('subject_error', __('Please select a Subject you teach.', 'darsna-tutor-reg'));
            }
            if ( isset( $_POST['urgent_help'] ) && $_POST['urgent_help'] === 'yes' ) {
                if ( ! isset( $_POST['urgent_hourly_rate'] ) || floatval( $_POST['urgent_hourly_rate'] ) <= 0 ) {
                    $errors->add( 'urgent_rate_error', __( 'A valid Urgent Hourly Rate is required if offering urgent help.', 'darsna-tutor-reg' ) );
                }
            }
        }
        return $errors;
    }

    /**
     * Validate custom fields on WooCommerce account details update.
     *
     * @since 1.0.1
     * @param WP_Error $errors  Existing errors.
     * @param WP_User  $user    User object.
     */
    public function validate_tutor_fields_update( $errors, $user ) {
        // Re-use the same validation logic, nonce is checked inside render_tutor_fields implicitly by save_tutor_profile
        // The $errors object is passed by reference, so modifications here will be reflected.
        // We need to ensure the nonce check is appropriate for this context as well.
        if ( ! isset( $_POST['darsna_tutor_nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['darsna_tutor_nonce'])), 'darsna_tutor_fields_action' ) ) {
             $errors->add( 'nonce_error_update', __( 'Security check failed on update. Please refresh and try again.', 'darsna-tutor-reg' ) );
             return; // Return early if nonce fails
        }

        if ( empty( $_POST['full_name'] ) ) {
            $errors->add( 'full_name_error', __( 'Full Name is required.', 'darsna-tutor-reg' ) );
        }

        $account_type = isset( $_POST['account_type'] ) ? sanitize_text_field( $_POST['account_type'] ) : '';
        // Note: On edit, 'tutor' role might be represented as 'latepoint_agent' in the form for consistency with LatePoint terminology.
        // The actual role stored in WP might be 'tutor'. This needs careful handling during save.
        if ( ! in_array( $account_type, array( 'student', 'latepoint_agent' ), true ) ) {
            $errors->add( 'account_type_error', __( 'Please select a valid Account Type (Student or Tutor).', 'darsna-tutor-reg' ) );
        }

        if ( $account_type === 'latepoint_agent' ) {
            if ( ! isset( $_POST['hourly_rate'] ) || floatval( $_POST['hourly_rate'] ) <= 0 ) {
                $errors->add( 'hourly_rate_error', __( 'A valid Hourly Rate is required for tutors.', 'darsna-tutor-reg' ) );
            }
            if ( empty( $_POST['subject'] ) || (isset($_POST['subject']) && !ctype_digit( (string)($_POST['subject']) )) ) {
                $errors->add( 'subject_error', __( 'Please select a Subject you teach.', 'darsna-tutor-reg' ) );
            }
            if ( isset( $_POST['urgent_help'] ) && $_POST['urgent_help'] === 'yes' ) {
                if ( ! isset( $_POST['urgent_hourly_rate'] ) || floatval( $_POST['urgent_hourly_rate'] ) <= 0 ) {
                    $errors->add( 'urgent_rate_error', __( 'A valid Urgent Hourly Rate is required if offering urgent help.', 'darsna-tutor-reg' ) );
                }
            }
        }
    }

    /**
     * Save custom fields on new customer creation and account details update.
     * Also syncs data with LatePoint if the user is a tutor.
     *
     * @since 1.0.1
     * @param int $user_id User ID.
     */
    public function save_tutor_profile( $user_id ) {
        // Nonce verification
        if ( ! isset( $_POST['darsna_tutor_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['darsna_tutor_nonce'] ) ), 'darsna_tutor_fields_action' ) ) {
            // For registration, errors are handled by 'validate_tutor_fields'
            // For account update, we might want to add an error if nonce fails, though WC handles some of this.
            // For now, let's assume validation hooks catch issues. If not, an error could be added here for updates.
            // Example: wc_add_notice( __( 'Security check failed. Please try again.', 'darsna-tutor-reg' ), 'error' );
            // return; // Or handle error appropriately
        }

        $user = new WP_User( $user_id );
        $account_type = isset( $_POST['account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['account_type'] ) ) : null;

        // If account_type is not submitted (e.g. admin editing user, or other forms), try to get current role
        if ( empty($account_type) && is_user_logged_in() && $user_id === get_current_user_id() ) {
             $current_user_roles = $user->roles;
             if (in_array('tutor', $current_user_roles, true) || in_array('latepoint_agent', $current_user_roles, true)) {
                 $account_type = 'latepoint_agent';
             } else {
                 $account_type = 'student'; // Default or based on other roles
             }
        } elseif (empty($account_type)) {
            // Fallback if not set and not current user edit, could default to student or do nothing
            // This scenario needs careful consideration based on where save_tutor_profile is called from
            // For 'woocommerce_created_customer', $_POST['account_type'] should be set from the form.
        }


        // Update basic user info (first name, last name from full_name)
        if ( isset( $_POST['full_name'] ) ) {
            $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ) );
            $name_parts = explode( ' ', $full_name, 2 );
            $first_name = $name_parts[0];
            $last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

            wp_update_user( array(
                'ID'         => $user_id,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => $full_name // Also update display name
            ) );
            update_user_meta($user_id, 'nickname', $full_name);
        }


        if ( $account_type === 'latepoint_agent' ) {
            if ( ! get_role( 'tutor' ) ) {
                add_role( 'tutor', 'Tutor', array( 'read' => true ) ); // Ensure 'tutor' role exists
            }
            $user->set_role( 'tutor' ); // Set WordPress role to 'tutor'

            update_user_meta( $user_id, 'hourly_rate', isset( $_POST['hourly_rate'] ) ? floatval( $_POST['hourly_rate'] ) : 0 );
            $subject = ( ! empty( $_POST['subject'] ) && isset($_POST['subject']) && ctype_digit( (string)($_POST['subject']) ) ) ? intval( $_POST['subject'] ) : ''; // Changed from 'subjects' to 'subject'
            update_user_meta( $user_id, 'darsna_subject', $subject ); // Changed meta key to 'darsna_subject'
            update_user_meta( $user_id, 'urgent_help', ( isset( $_POST['urgent_help'] ) && $_POST['urgent_help'] === 'yes' ) ? 'yes' : 'no' );
            update_user_meta( $user_id, 'urgent_hourly_rate', ( isset( $_POST['urgent_hourly_rate'] ) ) ? floatval( $_POST['urgent_hourly_rate'] ) : 0 );

            // Always attempt synchronization // This line and the next are the change
            // $this->sync_latepoint_data($user_id); // REMOVED this line

            // Only sync with LatePoint if it's properly loaded
            if (function_exists('darsna_tutor_reg_is_latepoint_loaded') && darsna_tutor_reg_is_latepoint_loaded()) {
                $this->sync_latepoint_data( $user_id );
            } else {
                error_log('Darsna Tutor Reg: Cannot sync user ' . $user_id . ' with LatePoint because LatePointAgentModel class is not available.');
            }

        } else if ( $account_type === 'student' ) {
            // If user switches from Tutor to Student, or registers as Student
            $user->set_role( 'customer' ); // Or your default student role
            // Optionally, remove tutor-specific meta and LatePoint agent data
            delete_user_meta( $user_id, 'hourly_rate' );
            delete_user_meta( $user_id, 'darsna_subject' );
            delete_user_meta( $user_id, 'urgent_help' );
            delete_user_meta( $user_id, 'urgent_hourly_rate' );
            // Remove from LatePoint if they were an agent
            if (function_exists('darsna_tutor_reg_is_latepoint_loaded') && darsna_tutor_reg_is_latepoint_loaded()) {
                $this->remove_latepoint_agent_data( $user_id );
            }
        }
    }

    /**
     * Sync user data with LatePoint as an agent.
     *
     * @since 1.0.1
     * @access private
     * @param int $user_id WordPress User ID.
     */
    private function sync_latepoint_data($user_id) {
        if (!class_exists('LatePointAgentModel')) {
            error_log('Darsna Debug: LatePointAgentModel class not available - cannot create agent for user ' . $user_id);
            return;
        }

        global $wpdb;
        $latepoint_db_prefix = $wpdb->prefix;

        $wp_user = get_userdata($user_id);
        if (!$wp_user) {
            error_log('Darsna Debug: WP User not found for ID ' . $user_id);
            return;
        }

        try {
            $agent_model = new LatePointAgentModel();
            $agent = $agent_model->where( array( 'wp_user_id' => $user_id ) )->get_results();
        } catch (Exception $e) {
            error_log("Darsna Tutor Reg: Failed to create or find LatePoint agent for WP User ID " . $user_id . " - " . $e->getMessage());
            return;
        }

        $agent_data = array(
            'first_name'   => $wp_user->first_name,
            'last_name'    => $wp_user->last_name,
            'display_name' => $wp_user->display_name,
            'email'        => $wp_user->user_email,
            'wp_user_id'   => $user_id,
            'status'       => 'approved', // Or manage status as needed
        );

        if ( $agent ) {
            $agent_id = $agent[0]->id;
            $agent_model->update_where( array('id' => $agent_id), $agent_data );
        } else {
            $agent_model->create( $agent_data );
            $agent_id = $agent_model->get_id();
        }

        if ( ! $agent_id ) {
            error_log('Darsna Tutor Reg: Failed to create or find LatePoint agent for WP User ID ' . $user_id);
            return;
        }

        // Sync service (subject)
        $wpdb->delete( $latepoint_db_prefix . 'latepoint_agents_services', array( 'agent_id' => $agent_id ) );
        $subject_id = get_user_meta( $user_id, 'darsna_subject', true ); // Changed to 'darsna_subject'
        if ( ! empty( $subject_id ) && ctype_digit( strval( $subject_id ) ) ) {
            $service_id = intval( $subject_id );
            if ( $service_id > 0 ) {
                $wpdb->insert( $latepoint_db_prefix . 'latepoint_agents_services', array(
                    'agent_id'   => $agent_id,
                    'service_id' => $service_id,
                ) );
            }
        }

        // Sync urgent help service if URGENT_HELP_SERVICE_ID is defined and user opted in
        if ( defined('DARSNA_URGENT_HELP_SERVICE_ID') && get_user_meta( $user_id, 'urgent_help', true ) === 'yes' ) {
            // Check if this specific connection already exists to prevent duplicates if run multiple times
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$latepoint_db_prefix}latepoint_agents_services WHERE agent_id = %d AND service_id = %d",
                $agent_id, DARSNA_URGENT_HELP_SERVICE_ID
            ) );
            if ( ! $exists ) {
                 $wpdb->insert( $latepoint_db_prefix . 'latepoint_agents_services', array(
                    'agent_id'   => $agent_id,
                    'service_id' => DARSNA_URGENT_HELP_SERVICE_ID,
                ) );
            }
        }

        // Sync meta (hourly rate, urgent rate)
        // LatePoint stores these in latepoint_agent_meta
        $hourly_rate = get_user_meta( $user_id, 'hourly_rate', true );
        $urgent_rate = get_user_meta( $user_id, 'urgent_hourly_rate', true );
        $urgent_help_enabled = get_user_meta( $user_id, 'urgent_help', true ) === 'yes';

        // Remove old meta first to avoid duplicates if keys change or values are removed
        $wpdb->delete( $latepoint_db_prefix . 'latepoint_agent_meta', array( 'object_id' => $agent_id, 'meta_key' => 'cost_per_appointment' ) );
        $wpdb->delete( $latepoint_db_prefix . 'latepoint_agent_meta', array( 'object_id' => $agent_id, 'meta_key' => 'urgent_cost_per_appointment' ) );

        if ( $hourly_rate > 0 ) {
            $wpdb->insert( $latepoint_db_prefix . 'latepoint_agent_meta', array(
                'object_id'  => $agent_id,
                'meta_key'   => 'cost_per_appointment',
                'meta_value' => $hourly_rate,
            ) );
        }
        if ( $urgent_help_enabled && $urgent_rate > 0 ) {
            $wpdb->insert( $latepoint_db_prefix . 'latepoint_agent_meta', array(
                'object_id'  => $agent_id,
                'meta_key'   => 'urgent_cost_per_appointment', // Ensure this meta_key is what LatePoint uses
                'meta_value' => $urgent_rate,
            ) );
        }
    }

    /**
     * Remove LatePoint agent data when a WordPress user is deleted.
     *
     * @since 1.0.1
     * @param int $user_id WordPress User ID.
     */
    public function remove_agent_on_user_delete( $user_id ) {
        // Only attempt to remove LatePoint agent if LatePoint is properly loaded
        if (function_exists('darsna_tutor_reg_is_latepoint_loaded') && darsna_tutor_reg_is_latepoint_loaded()) {
            $this->remove_latepoint_agent_by_wp_user_id( $user_id );
        }
    }
    
    /**
     * Helper function to remove LatePoint agent by WP User ID.
     *
     * @since 1.0.1
     * @access private
     * @param int $user_id WordPress User ID.
     */
    private function remove_latepoint_agent_by_wp_user_id( $user_id ){
        // Check if LatePoint is properly loaded
        if ( ! function_exists('darsna_tutor_reg_is_latepoint_loaded') || ! darsna_tutor_reg_is_latepoint_loaded() ) return;
        global $wpdb;
        $latepoint_db_prefix = $wpdb->prefix;

        $agent_model = new LatePointAgentModel();
        $agent = $agent_model->where( array( 'wp_user_id' => $user_id ) )->get_results();

        if ( $agent && isset($agent[0]->id) ) {
            $agent_id = $agent[0]->id;
            // Delete from latepoint_agents_services
            $wpdb->delete( $latepoint_db_prefix . 'latepoint_agents_services', array( 'agent_id' => $agent_id ) );
            // Delete from latepoint_agent_meta
            $wpdb->delete( $latepoint_db_prefix . 'latepoint_agent_meta', array( 'object_id' => $agent_id ) );
            // Delete from latepoint_agents
            $agent_model->delete( $agent_id );
        }
    }

    /**
     * Tweak WooCommerce login form text (Username or email address -> Email address).
     *
     * @since 1.0.1
     * @param string $translated_text Translated text.
     * @param string $original_text   Original text.
     * @param string $domain          Text domain.
     * @return string Modified translated text.
     */
    public function tweak_wc_login_text( $translated_text, $original_text, $domain ) {
        if ( $domain === 'woocommerce' && trim( $original_text ) === 'Username or email address' ) {
            return __( 'Email address', 'darsna-tutor-reg' );
        }
        return $translated_text;
    }

    /**
     * Add JavaScript to tweak WooCommerce login form (placeholder and disable strength check).
     *
     * @since 1.0.1
     */
    public function tweak_wc_login_form_js() {
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
        // Only run on login/register pages, not all account pages
        if (is_wc_endpoint_url( 'lost-password' ) || is_wc_endpoint_url( 'edit-account' ) || is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'downloads' ) || is_wc_endpoint_url( 'edit-address' ) || is_wc_endpoint_url( 'payment-methods' ) || is_wc_endpoint_url( 'add-payment-method' ) || is_wc_endpoint_url( 'customer-logout' )) return;
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // For login form
                $('form.woocommerce-form-login .woocommerce-form-row input#username').attr('placeholder', '<?php _e( "Email address", "darsna-tutor-reg" ); ?>');
                // For registration form (email field is usually #reg_email)
                // The label change is handled by gettext filter. This is for placeholder if needed.
                // $('form.woocommerce-form-register #reg_email').attr('placeholder', '<?php _e( "Email address", "darsna-tutor-reg" ); ?>');

                // Disable password strength check on registration form
                $('form.woocommerce-form-register').off('submit'); 
            });
        </script>
        <?php
    }

    /**
     * Disable WooCommerce password strength meter.
     *
     * @since 1.0.1
     * @return int 0 to disable.
     */
    public function disable_password_strength_meter() {
        return 0; // Return 0 to disable the meter, 1 for weak, 2 for medium, 3 for strong.
    }

    /**
     * Dequeue WooCommerce password strength meter script on account pages.
     *
     * @since 1.0.1
     */
    public function dequeue_password_strength_meter() {
        if ( function_exists( 'is_account_page' ) && is_account_page() ) {
             // Only run on login/register pages
            if (is_wc_endpoint_url( 'lost-password' ) || is_wc_endpoint_url( 'edit-account' ) || is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'downloads' ) || is_wc_endpoint_url( 'edit-address' ) || is_wc_endpoint_url( 'payment-methods' ) || is_wc_endpoint_url( 'add-payment-method' ) || is_wc_endpoint_url( 'customer-logout' )) return;
            wp_dequeue_script( 'wc-password-strength-meter' );
        }
    }

    /**
     * Add Dashboard and Logout links to the navigation menu.
     *
     * Targets 'primary-menu' theme location, common in Divi.
     * Adjust theme_location if your theme uses a different one for the main menu.
     *
     * @since 1.0.1
     * @param string $items HTML list items for the menu.
     * @param object $args  An object containing wp_nav_menu() arguments.
     * @return string Modified HTML list items.
     */
    public function add_dashboard_logout_menu_links( $items, $args ) {
        if ( ! is_user_logged_in() ) {
            return $items;
        }

        // Check for a specific theme location. Adjust 'primary-menu' as needed.
        if ( ! isset( $args->theme_location ) || $args->theme_location !== 'primary-menu' ) {
            // Fallback: if no theme location is set, or it's not primary, maybe it's a default menu.
            // This part is tricky without knowing the exact theme. For now, let's assume it's for primary-menu.
            // If you want it on ALL menus, remove this condition, but that's usually not desired.
            // return $items;
        }

        $new_items = '';
        $current_user = wp_get_current_user();
        $user_roles = (array) $current_user->roles;

        $dashboard_label = __( 'Dashboard', 'darsna-tutor-reg' );
        if ( in_array( 'tutor', $user_roles, true ) || in_array( 'latepoint_agent', $user_roles, true ) ) {
            $dashboard_url = admin_url( 'admin.php?page=latepoint' ); // LatePoint dashboard for tutors
        } else {
            $dashboard_url = site_url( '/student-dashboard/' ); // Custom student dashboard page
        }
        $new_items .= '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="' . esc_url( $dashboard_url ) . '">' . esc_html( $dashboard_label ) . '</a></li>';

        $logout_url = wp_logout_url( home_url() );
        $new_items .= '<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="' . esc_url( $logout_url ) . '">' . __( 'Logout', 'darsna-tutor-reg' ) . '</a></li>';

        return $items . $new_items;
    }

    /**
     * Register retry hook for LatePoint sync
     */
    public function register_retry_hook() {
        add_action('darsna_retry_latepoint_sync', array($this, 'retry_sync_latepoint_data'), 10, 1);
    }

    /**
     * Retry syncing data with LatePoint
     */
    public function retry_sync_latepoint_data($user_id) {
        if (function_exists('darsna_tutor_reg_is_latepoint_loaded') && darsna_tutor_reg_is_latepoint_loaded()) {
            $this->sync_latepoint_data($user_id);
        } else {
            $retry_count = get_user_meta($user_id, '_darsna_latepoint_sync_retries', true);
            $retry_count = !empty($retry_count) ? intval($retry_count) + 1 : 1;
            
            if ($retry_count < 5) {
                update_user_meta($user_id, '_darsna_latepoint_sync_retries', $retry_count);
                wp_schedule_single_event(time() + 60, 'darsna_retry_latepoint_sync', array($user_id));
                error_log("Darsna Tutor Reg: Retry {$retry_count} for user {$user_id}");
            } else {
                error_log("Darsna Tutor Reg: Sync failed after {$retry_count} retries");
                delete_user_meta($user_id, '_darsna_latepoint_sync_retries');
            }
        }
    }

    /**
     * Check for pending LatePoint synchronizations
     */
    public function process_pending_latepoint_syncs() {
        // Only run if LatePoint is available now
        if (!class_exists('LatePointAgentModel')) {
            return;
        }
        
        // Query users with pending sync flag
        $args = array(
            'meta_key' => '_darsna_pending_latepoint_sync',
            'meta_value' => 'yes',
            'fields' => 'ID',
        );
        
        $users = get_users($args);
        
        foreach ($users as $user_id) {
            // Process the sync
            $this->sync_latepoint_data($user_id);
            // Remove the pending flag
            delete_user_meta($user_id, '_darsna_pending_latepoint_sync');
        }
    }
}
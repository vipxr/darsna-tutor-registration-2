<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       
 * @since      1.0.1
 *
 * @package    Darsna_Tutor_Reg
 * @subpackage Darsna_Tutor_Reg/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.1
 * @package    Darsna_Tutor_Reg
 * @subpackage Darsna_Tutor_Reg/includes
 * @author     Your Name <you@example.com>
 */
class Darsna_Tutor_Registration {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.1
     * @access   protected
     * @var      Darsna_Tutor_Reg_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.1
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.1
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.1
     */
    public function __construct() {
        if ( defined( 'DARSNA_TUTOR_REG_VERSION' ) ) {
            $this->version = DARSNA_TUTOR_REG_VERSION;
        } else {
            $this->version = '1.0.1';
        }
        $this->plugin_name = 'darsna-tutor-reg';

        $this->load_dependencies();

        // Check if LatePoint plugin is active and its classes are loaded
        if (!class_exists('\LatePoint\Loader')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Darsna Tutor Reg: LatePoint plugin classes not fully loaded yet. Some functionality may be limited.</p></div>';
            });
            // Still define basic hooks but skip LatePoint-dependent ones
            $this->define_basic_hooks();
        } else {
            // Define all hooks including LatePoint-dependent ones
            $this->define_hooks();
        }
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Darsna_Tutor_Reg_Loader. Orchestrates the hooks of the plugin.
     * - Darsna_Tutor_Reg_Public. Defines all hooks for the public side.
     * - Darsna_Tutor_Reg_Admin. Defines all hooks for the admin side.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.1
     * @access   private
     */
    private function load_dependencies() {
        require_once DARSNA_TUTOR_REG_PLUGIN_DIR . 'includes/class-darsna-tutor-reg-loader.php';
        require_once DARSNA_TUTOR_REG_PLUGIN_DIR . 'public/class-darsna-tutor-reg-public.php';
        require_once DARSNA_TUTOR_REG_PLUGIN_DIR . 'admin/class-darsna-tutor-reg-admin.php';
        $this->loader = new Darsna_Tutor_Reg_Loader();
    }

    /**
     * Define all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.1
     * @access   private
     */
    /**
     * Define basic hooks that don't depend on LatePoint plugin.
     *
     * @since    1.0.1
     * @access   private
     */
    private function define_basic_hooks() {
        $plugin_public = new Darsna_Tutor_Reg_Public( $this->get_plugin_name(), $this->get_version() );
        $plugin_admin = new Darsna_Tutor_Reg_Admin( $this->get_plugin_name(), $this->get_version() );

        // Check required plugins
        $this->loader->add_action( 'admin_init', $plugin_admin, 'check_required_plugins' );

        // Enqueue scripts and styles
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        // WooCommerce login and password tweaks
        $this->loader->add_filter( 'gettext', $plugin_public, 'tweak_wc_login_text', 20, 3 );
        $this->loader->add_action( 'wp_footer', $plugin_public, 'tweak_wc_login_form_js' );
        $this->loader->add_filter( 'woocommerce_min_password_strength', $plugin_public, 'disable_password_strength_meter' );
        $this->loader->add_action( 'wp_print_scripts', $plugin_public, 'dequeue_password_strength_meter', 100 );

        // Menu links
        $this->loader->add_filter( 'wp_nav_menu_items', $plugin_public, 'add_dashboard_logout_menu_links', 99, 2 );

        // Always register form fields regardless of LatePoint status
        $this->loader->add_action( 'woocommerce_register_form_start', $plugin_public, 'render_tutor_fields', 20 );
        $this->loader->add_action( 'woocommerce_edit_account_form', $plugin_public, 'render_tutor_fields', 20 );
        $this->loader->add_filter( 'woocommerce_registration_errors', $plugin_public, 'validate_tutor_fields', 10, 3 );
        $this->loader->add_action( 'woocommerce_save_account_details_errors', $plugin_public, 'validate_tutor_fields_update', 10, 2 );
        $this->loader->add_action( 'woocommerce_created_customer', $plugin_public, 'save_tutor_profile', 20 );
        $this->loader->add_action( 'woocommerce_save_account_details', $plugin_public, 'save_tutor_profile', 20 );
    }

    /**
     * Define all hooks including those that depend on LatePoint plugin.
     *
     * @since    1.0.1
     * @access   private
     */
    private function define_hooks() {
        $plugin_public = new Darsna_Tutor_Reg_Public($this->get_plugin_name(), $this->get_version());
        $plugin_admin = new Darsna_Tutor_Reg_Admin( $this->get_plugin_name(), $this->get_version() );

        // First add all basic hooks
        $this->define_basic_hooks();

        // Then add LatePoint-dependent hooks
        // WooCommerce registration and account fields
        $this->loader->add_action( 'woocommerce_register_form_start', $plugin_public, 'render_tutor_fields', 20 );
        $this->loader->add_action( 'woocommerce_edit_account_form', $plugin_public, 'render_tutor_fields', 20 );

        // Validate fields
        $this->loader->add_filter( 'woocommerce_registration_errors', $plugin_public, 'validate_tutor_fields', 10, 3 );
        $this->loader->add_action( 'woocommerce_save_account_details_errors', $plugin_public, 'validate_tutor_fields_update', 10, 2 );

        // Save fields and sync with LatePoint
        $this->loader->add_action( 'woocommerce_created_customer', $plugin_public, 'save_tutor_profile', 20 );
        $this->loader->add_action( 'woocommerce_save_account_details', $plugin_public, 'save_tutor_profile', 20 );

        // User deletion cleanup
        $this->loader->add_action( 'delete_user', $plugin_public, 'remove_agent_on_user_delete', 10, 1 );
    
        // Add retry hook registration
        $this->loader->add_action('init', $plugin_public, 'register_retry_hook');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.1
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.1
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.1
     * @return    Darsna_Tutor_Reg_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.1
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}
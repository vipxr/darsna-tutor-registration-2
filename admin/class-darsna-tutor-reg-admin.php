<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       
 * @since      1.0.1
 *
 * @package    Darsna_Tutor_Reg
 * @subpackage Darsna_Tutor_Reg/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Darsna_Tutor_Reg
 * @subpackage Darsna_Tutor_Reg/admin
 * @author     Your Name <you@example.com>
 */
class Darsna_Tutor_Reg_Admin {

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
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Check if required plugins (WooCommerce and LatePoint) are active.
     *
     * If not, it displays an admin notice and deactivates this plugin.
     *
     * @since 1.0.1
     */
    public function check_required_plugins() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        $woocommerce_active = is_plugin_active( 'woocommerce/woocommerce.php' );
        $latepoint_active = is_plugin_active( 'latepoint/latepoint.php' );

        if ( ! $woocommerce_active || ! $latepoint_active ) {
            add_action( 'admin_notices', array( $this, 'required_plugins_notice' ) );
            deactivate_plugins( plugin_basename( DARSNA_TUTOR_REG_PLUGIN_DIR . 'darsna-tutor-registration.php' ) );
        }
    }

    /**
     * Display an admin notice if required plugins are not active.
     *
     * @since 1.0.1
     */
    public function required_plugins_notice() {
        $message = '<div class="error"><p>';
        $message .= __( 'Tutor Registration for WooCommerce & LatePoint requires both WooCommerce and LatePoint plugins to be active. The plugin has been deactivated.', 'darsna-tutor-reg' );
        $message .= '</p></div>';
        echo $message;
    }

    // Potentially add other admin-specific hooks and functions here in the future
    // For example, plugin settings pages, admin-specific scripts/styles, etc.

}
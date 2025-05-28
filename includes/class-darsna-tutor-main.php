<?php
/**
 * Main plugin class - Entry point and initialization
 * 
 * @package Darsna_Tutor_Registration
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class - Optimized for LatePoint v5
 */
final class Darsna_Tutor_Main {
    
    private static $instance;
    
    // Constants for better maintainability
    private const REQUIRED_PLUGINS = [
        'woocommerce/woocommerce.php',
        'woocommerce-subscriptions/woocommerce-subscriptions.php',
        'latepoint/latepoint.php'
    ];
    
    /**
     * Singleton instance
     */
    public static function instance() {
        return self::$instance ??= new self();
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
    }
    
    /**
     * Initialize plugin
     */
    public function init(): void {
        if ( ! $this->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'show_dependency_notice' ] );
            return;
        }
        
        $this->load_dependencies();
        $this->setup_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies(): void {
        // Load frontend class
        require_once DARSNA_TUTOR_PATH . 'includes/class-darsna-tutor-frontend.php';
        
        // Load backend class
        require_once DARSNA_TUTOR_PATH . 'includes/class-darsna-tutor-backend.php';
        
        // Initialize classes
        Darsna_Tutor_Frontend::instance();
        Darsna_Tutor_Backend::instance();
    }
    
    /**
     * Setup main hooks
     */
    private function setup_hooks(): void {
        // User management hooks
        add_action( 'delete_user', [ $this, 'handle_user_deletion' ] );
        
        // Menu customization
        add_filter( 'wp_nav_menu_items', [ $this, 'customize_nav_menu' ], 99, 2 );
    }
    
    /**
     * Check plugin dependencies
     */
    private function check_dependencies(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        foreach ( self::REQUIRED_PLUGINS as $plugin ) {
            if ( ! is_plugin_active( $plugin ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Show dependency notice
     */
    public function show_dependency_notice(): void {
        $missing = [];
        
        foreach ( self::REQUIRED_PLUGINS as $plugin ) {
            if ( ! is_plugin_active( $plugin ) ) {
                $missing[] = basename( dirname( $plugin ) );
            }
        }
        
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Darsna Tutor Registration:</strong> Requires the following plugins: ';
        echo implode( ', ', $missing );
        echo '</p></div>';
    }
    
    /**
     * Handle user deletion
     */
    public function handle_user_deletion( int $user_id ): void {
        // Deactivate associated agent
        $backend = Darsna_Tutor_Backend::instance();
        $backend->deactivate_tutor_agent( $user_id );
    }
    
    /**
     * Customize navigation menu for logged-in users
     */
    public function darsna_simplify_menu_links( $items, $args ) {
       // only for logged‐in users on the Primary Menu
       if ( ! is_user_logged_in() || 'primary-menu' !== $args->theme_location ) {
           return $items;
       }
   
       // Dashboard for tutors, Account for everyone else
       if ( current_user_can( 'latepoint_agent' ) ) {
           $items .= '<li class="menu-item"><a href="' . admin_url( 'admin.php?page=latepoint' ) . '">Dashboard</a></li>';
       } else {
           $items .= '<li class="menu-item"><a href="' . get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . '">Account</a></li>';
       }
   
       // Logout link
       $items .= '<li class="menu-item">' . wp_loginout( home_url(), false ) . '</li>';
   
       return $items;
   }
   add_filter( 'wp_nav_menu_items', 'darsna_simplify_menu_links', 10, 2 );
}
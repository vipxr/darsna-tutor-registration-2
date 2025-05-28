<?php
/**
 * Main plugin class - Entry point and initialization
 * 
 * @package Darsna_Tutor_Registration
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Darsna_Tutor_Main' ) ) {

    final class Darsna_Tutor_Main {

        /** @var Darsna_Tutor_Main */
        private static $instance;

        /** @var string[] List of required plugins */
        private const REQUIRED_PLUGINS = [
            'woocommerce/woocommerce.php',
            'woocommerce-subscriptions/woocommerce-subscriptions.php',
            'latepoint/latepoint.php',
        ];

        /**
         * Singleton instance
         *
         * @return Darsna_Tutor_Main
         */
        public static function instance(): Darsna_Tutor_Main {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor
         */
        private function __construct() {
            add_action( 'init', [ $this, 'init' ] );
        }

        /**
         * Run on init: check dependencies, load files, register hooks.
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
         * Verify required plugins are active.
         *
         * @return bool
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
         * Show admin notice if dependencies are missing.
         */
        public function show_dependency_notice(): void {
            $missing = [];

            foreach ( self::REQUIRED_PLUGINS as $plugin ) {
                if ( ! is_plugin_active( $plugin ) ) {
                    $missing[] = basename( dirname( $plugin ) );
                }
            }

            printf(
                '<div class="notice notice-error"><p><strong>%s</strong> requires: %s</p></div>',
                esc_html__( 'Darsna Tutor Registration', 'darsna' ),
                esc_html( implode( ', ', $missing ) )
            );
        }

        /**
         * Include other class files and bootstrap them.
         */
        private function load_dependencies(): void {
            if ( ! defined( 'DARSNA_TUTOR_PATH' ) ) {
                define( 'DARSNA_TUTOR_PATH', plugin_dir_path( __DIR__ ) );
            }

            require_once DARSNA_TUTOR_PATH . 'includes/class-darsna-tutor-frontend.php';
            require_once DARSNA_TUTOR_PATH . 'includes/class-darsna-tutor-backend.php';

            Darsna_Tutor_Frontend::instance();
            Darsna_Tutor_Backend::instance();
        }

        /**
         * Register all action & filter hooks.
         */
        private function setup_hooks(): void {
            // Clean up when a WP user is deleted
            add_action( 'delete_user', [ $this, 'handle_user_deletion' ] );

            // Add Dashboard/Account + Logout to Divi menus
            add_filter( 'wp_nav_menu_items',        [ $this, 'darsna_simplify_menu_links' ], 10, 2 );
            add_filter( 'et_mobile_nav_menu_items', [ $this, 'darsna_simplify_menu_links' ], 10, 2 );
        }

        /**
         * Completely remove the LatePoint agent when the WP user is deleted.
         *
         * @param int $user_id
         */
        public function handle_user_deletion( int $user_id ): void {
            $backend = Darsna_Tutor_Backend::instance();
            $backend->remove_tutor_agent( $user_id );
        }

        /**
         * Append Dashboard/Account + Logout to Divi’s primary menu for logged-in users.
         *
         * @param string   $items The HTML list items.
         * @param stdClass $args  The wp_nav_menu() arguments.
         * @return string
         */
        public function darsna_simplify_menu_links( $items, $args ): string {
            // Only for logged-in users on the primary menu location
            if ( ! is_user_logged_in() || 'primary-menu' !== $args->theme_location ) {
                return $items;
            }

            // Dashboard for tutors, Account for everyone else
            if ( current_user_can( 'latepoint_agent' ) ) {
                $items .= '<li class="menu-item"><a href="' . esc_url( admin_url( 'admin.php?page=latepoint' ) ) . '">'
                       . esc_html__( 'Dashboard', 'darsna' ) . '</a></li>';
            } else {
                $account_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
                $items      .= '<li class="menu-item"><a href="' . esc_url( $account_url ) . '">'
                              . esc_html__( 'Account', 'darsna' ) . '</a></li>';
            }

            // Logout link
            $items .= '<li class="menu-item">' . wp_loginout( home_url(), false ) . '</li>';

            return $items;
        }
    }
}

// Bootstrap the plugin
Darsna_Tutor_Main::instance();

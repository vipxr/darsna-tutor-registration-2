<?php
/**
 * Admin dashboard functionality for tutor registration
 * 
 * @package Darsna_Tutor_Registration
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class - Handles admin dashboard and agent management
 */
class Darsna_Tutor_Admin {
    
    private static $instance;
    
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
        $this->setup_hooks();
    }
    
    /**
     * Setup admin hooks
     */
    private function setup_hooks(): void {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_darsna_update_agent_status', [ $this, 'ajax_update_agent_status' ] );
        add_action( 'wp_ajax_darsna_delete_agent', [ $this, 'ajax_delete_agent' ] );
        add_action( 'wp_ajax_darsna_get_agent_details', [ $this, 'ajax_get_agent_details' ] );
        add_action( 'wp_ajax_darsna_update_agent_services', [ $this, 'ajax_update_agent_services' ] );
        add_action( 'wp_ajax_darsna_update_agent_schedule', [ $this, 'ajax_update_agent_schedule' ] );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __( 'Tutor Agents', 'darsna-tutor' ),
            __( 'Tutor Agents', 'darsna-tutor' ),
            'manage_options',
            'darsna-tutor-agents',
            [ $this, 'admin_page' ],
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'darsna-tutor-agents',
            __( 'All Agents', 'darsna-tutor' ),
            __( 'All Agents', 'darsna-tutor' ),
            'manage_options',
            'darsna-tutor-agents',
            [ $this, 'admin_page' ]
        );
        
        add_submenu_page(
            'darsna-tutor-agents',
            __( 'Settings', 'darsna-tutor' ),
            __( 'Settings', 'darsna-tutor' ),
            'manage_options',
            'darsna-tutor-settings',
            [ $this, 'settings_page' ]
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ): void {
        if ( strpos( $hook, 'darsna-tutor' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            'darsna-tutor-admin',
            DARSNA_TUTOR_ASSETS_URL . 'js/admin.js',
            [ 'jquery', 'wp-util' ],
            DARSNA_TUTOR_VERSION,
            true
        );
        
        wp_enqueue_style(
            'darsna-tutor-admin',
            DARSNA_TUTOR_ASSETS_URL . 'css/admin.css',
            [],
            DARSNA_TUTOR_VERSION
        );
        
        wp_localize_script( 'darsna-tutor-admin', 'darsna_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'darsna_admin_nonce' ),
            'strings' => [
                'confirm_delete' => __( 'Are you sure you want to delete this agent?', 'darsna-tutor' ),
                'confirm_activate' => __( 'Are you sure you want to activate this agent?', 'darsna-tutor' ),
                'confirm_deactivate' => __( 'Are you sure you want to deactivate this agent?', 'darsna-tutor' ),
                'loading' => __( 'Loading...', 'darsna-tutor' ),
                'error' => __( 'An error occurred. Please try again.', 'darsna-tutor' ),
                'success' => __( 'Operation completed successfully.', 'darsna-tutor' ),
            ]
        ]);
    }
    
    /**
     * Main admin page
     */
    public function admin_page(): void {
        $agents = $this->get_all_agents();
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'list';
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tutor Agents Management', 'darsna-tutor' ); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=darsna-tutor-agents&tab=list" class="nav-tab <?php echo $current_tab === 'list' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'All Agents', 'darsna-tutor' ); ?>
                </a>
                <a href="?page=darsna-tutor-agents&tab=pending" class="nav-tab <?php echo $current_tab === 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Pending Approval', 'darsna-tutor' ); ?>
                </a>
                <a href="?page=darsna-tutor-agents&tab=active" class="nav-tab <?php echo $current_tab === 'active' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Active Agents', 'darsna-tutor' ); ?>
                </a>
                <a href="?page=darsna-tutor-agents&tab=inactive" class="nav-tab <?php echo $current_tab === 'inactive' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Inactive Agents', 'darsna-tutor' ); ?>
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'pending':
                        $this->render_agents_table( $agents, 'pending' );
                        break;
                    case 'active':
                        $this->render_agents_table( $agents, 'active' );
                        break;
                    case 'inactive':
                        $this->render_agents_table( $agents, 'inactive' );
                        break;
                    default:
                        $this->render_agents_table( $agents );
                        break;
                }
                ?>
            </div>
        </div>
        
        <!-- Agent Details Modal -->
        <div id="agent-details-modal" class="darsna-modal" style="display: none;">
            <div class="darsna-modal-content">
                <div class="darsna-modal-header">
                    <h2><?php esc_html_e( 'Agent Details', 'darsna-tutor' ); ?></h2>
                    <span class="darsna-modal-close">&times;</span>
                </div>
                <div class="darsna-modal-body">
                    <div id="agent-details-content"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page(): void {
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'darsna_settings' ) ) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'darsna-tutor' ) . '</p></div>';
        }
        
        $settings = get_option( 'darsna_tutor_settings', [] );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tutor Agent Settings', 'darsna-tutor' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'darsna_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-approve new agents', 'darsna-tutor' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_approve" value="1" <?php checked( $settings['auto_approve'] ?? false ); ?> />
                                <?php esc_html_e( 'Automatically approve new agent registrations', 'darsna-tutor' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Default agent status', 'darsna-tutor' ); ?></th>
                        <td>
                            <select name="default_status">
                                <option value="pending" <?php selected( $settings['default_status'] ?? 'pending', 'pending' ); ?>>
                                    <?php esc_html_e( 'Pending', 'darsna-tutor' ); ?>
                                </option>
                                <option value="active" <?php selected( $settings['default_status'] ?? 'pending', 'active' ); ?>>
                                    <?php esc_html_e( 'Active', 'darsna-tutor' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Email notifications', 'darsna-tutor' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked( $settings['email_notifications'] ?? true ); ?> />
                                <?php esc_html_e( 'Send email notifications for agent status changes', 'darsna-tutor' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Admin email', 'darsna-tutor' ); ?></th>
                        <td>
                            <input type="email" name="admin_email" value="<?php echo esc_attr( $settings['admin_email'] ?? get_option( 'admin_email' ) ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Email address to receive notifications about new agent registrations.', 'darsna-tutor' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render agents table
     */
    private function render_agents_table( array $agents, string $filter = '' ): void {
        if ( $filter ) {
            $agents = array_filter( $agents, function( $agent ) use ( $filter ) {
                return $agent->status === $filter;
            });
        }
        
        ?>
        <div class="agents-table-container">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="bulk-action-selector-top">
                        <option value="-1"><?php esc_html_e( 'Bulk Actions', 'darsna-tutor' ); ?></option>
                        <option value="activate"><?php esc_html_e( 'Activate', 'darsna-tutor' ); ?></option>
                        <option value="deactivate"><?php esc_html_e( 'Deactivate', 'darsna-tutor' ); ?></option>
                        <option value="delete"><?php esc_html_e( 'Delete', 'darsna-tutor' ); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'darsna-tutor' ); ?>">
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf( esc_html__( '%d agents', 'darsna-tutor' ), count( $agents ) ); ?></span>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped agents">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th class="manage-column column-name"><?php esc_html_e( 'Name', 'darsna-tutor' ); ?></th>
                        <th class="manage-column column-email"><?php esc_html_e( 'Email', 'darsna-tutor' ); ?></th>
                        <th class="manage-column column-status"><?php esc_html_e( 'Status', 'darsna-tutor' ); ?></th>
                        <th class="manage-column column-services"><?php esc_html_e( 'Services', 'darsna-tutor' ); ?></th>
                        <th class="manage-column column-rate"><?php esc_html_e( 'Rate', 'darsna-tutor' ); ?></th>
                        <th class="manage-column column-created"><?php esc_html_e( 'Created', 'darsna-tutor' ); ?></th>
                        <th class="manage-column column-actions"><?php esc_html_e( 'Actions', 'darsna-tutor' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $agents ) ) : ?>
                        <tr>
                            <td colspan="8" class="no-items"><?php esc_html_e( 'No agents found.', 'darsna-tutor' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $agents as $agent ) : ?>
                            <tr data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
                                <th class="check-column">
                                    <input type="checkbox" name="agent[]" value="<?php echo esc_attr( $agent->id ); ?>">
                                </th>
                                <td class="column-name">
                                    <strong><?php echo esc_html( $agent->first_name . ' ' . $agent->last_name ); ?></strong>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="#" class="view-agent" data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
                                                <?php esc_html_e( 'View', 'darsna-tutor' ); ?>
                                            </a> |
                                        </span>
                                        <span class="edit">
                                            <a href="#" class="edit-agent" data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
                                                <?php esc_html_e( 'Edit', 'darsna-tutor' ); ?>
                                            </a> |
                                        </span>
                                        <span class="delete">
                                            <a href="#" class="delete-agent" data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
                                                <?php esc_html_e( 'Delete', 'darsna-tutor' ); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                                <td class="column-email"><?php echo esc_html( $agent->email ); ?></td>
                                <td class="column-status">
                                    <span class="status-badge status-<?php echo esc_attr( $agent->status ); ?>">
                                        <?php echo esc_html( ucfirst( $agent->status ) ); ?>
                                    </span>
                                </td>
                                <td class="column-services">
                                    <?php 
                                    $services = $this->get_agent_services( $agent->id );
                                    echo esc_html( implode( ', ', array_column( $services, 'name' ) ) );
                                    ?>
                                </td>
                                <td class="column-rate">$<?php echo esc_html( number_format( $agent->hourly_rate ?? 0, 2 ) ); ?></td>
                                <td class="column-created"><?php echo esc_html( date( 'Y-m-d', strtotime( $agent->created_at ) ) ); ?></td>
                                <td class="column-actions">
                                    <?php if ( $agent->status === 'active' ) : ?>
                                        <button class="button button-small deactivate-agent" data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
                                            <?php esc_html_e( 'Deactivate', 'darsna-tutor' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button class="button button-primary button-small activate-agent" data-agent-id="<?php echo esc_attr( $agent->id ); ?>">
                                            <?php esc_html_e( 'Activate', 'darsna-tutor' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get all agents from LatePoint
     */
    private function get_all_agents(): array {
        global $wpdb;
        
        $agents_table = $wpdb->prefix . 'latepoint_agents';
        
        $agents = $wpdb->get_results(
            "SELECT * FROM {$agents_table} ORDER BY created_at DESC"
        );
        
        // Enhance with WordPress user data
        foreach ( $agents as &$agent ) {
            $wp_user = get_user_by( 'email', $agent->email );
            if ( $wp_user ) {
                $agent->wp_user_id = $wp_user->ID;
                $agent->wp_user = $wp_user;
            }
        }
        
        return $agents ?: [];
    }
    
    /**
     * Get agent services
     */
    private function get_agent_services( int $agent_id ): array {
        global $wpdb;
        
        $agents_services_table = $wpdb->prefix . 'latepoint_agents_services';
        $services_table = $wpdb->prefix . 'latepoint_services';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.name, s.id, s.charge_amount, ags.is_custom_hours, ags.is_custom_duration, ags.is_custom_price
             FROM {$agents_services_table} ags
             JOIN {$services_table} s ON ags.service_id = s.id
             WHERE ags.agent_id = %d",
            $agent_id
        )) ?: [];
    }
    
    /**
     * Save settings
     */
    private function save_settings(): void {
        $settings = [
            'auto_approve' => isset( $_POST['auto_approve'] ),
            'default_status' => sanitize_text_field( $_POST['default_status'] ?? 'pending' ),
            'email_notifications' => isset( $_POST['email_notifications'] ),
            'admin_email' => sanitize_email( $_POST['admin_email'] ?? get_option( 'admin_email' ) ),
        ];
        
        update_option( 'darsna_tutor_settings', $settings );
    }
    
    /**
     * AJAX: Update agent status
     */
    public function ajax_update_agent_status(): void {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'darsna-tutor' ) );
        }
        
        $agent_id = intval( $_POST['agent_id'] ?? 0 );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        
        if ( ! $agent_id || ! in_array( $status, [ 'active', 'inactive' ] ) ) {
            wp_send_json_error( __( 'Invalid parameters.', 'darsna-tutor' ) );
        }
        
        global $wpdb;
        $agents_table = $wpdb->prefix . 'latepoint_agents';
        
        $result = $wpdb->update(
            $agents_table,
            [ 'status' => $status ],
            [ 'id' => $agent_id ],
            [ '%s' ],
            [ '%d' ]
        );
        
        if ( $result !== false ) {
            wp_send_json_success( __( 'Agent status updated successfully.', 'darsna-tutor' ) );
        } else {
            wp_send_json_error( __( 'Failed to update agent status.', 'darsna-tutor' ) );
        }
    }
    
    /**
     * AJAX: Delete agent
     */
    public function ajax_delete_agent(): void {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'darsna-tutor' ) );
        }
        
        $agent_id = intval( $_POST['agent_id'] ?? 0 );
        
        if ( ! $agent_id ) {
            wp_send_json_error( __( 'Invalid agent ID.', 'darsna-tutor' ) );
        }
        
        // Get agent email to find WordPress user
        global $wpdb;
        $agents_table = $wpdb->prefix . 'latepoint_agents';
        
        $agent = $wpdb->get_row( $wpdb->prepare(
            "SELECT email FROM {$agents_table} WHERE id = %d",
            $agent_id
        ));
        
        if ( ! $agent ) {
            wp_send_json_error( __( 'Agent not found.', 'darsna-tutor' ) );
        }
        
        // Use backend method to remove agent
        $backend = Darsna_Tutor_Backend::instance();
        $wp_user = get_user_by( 'email', $agent->email );
        
        if ( $wp_user ) {
            $backend->remove_tutor_agent( $wp_user->ID );
            wp_send_json_success( __( 'Agent deleted successfully.', 'darsna-tutor' ) );
        } else {
            wp_send_json_error( __( 'WordPress user not found for this agent.', 'darsna-tutor' ) );
        }
    }
    
    /**
     * AJAX: Get agent details
     */
    public function ajax_get_agent_details(): void {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'darsna-tutor' ) );
        }
        
        $agent_id = intval( $_POST['agent_id'] ?? 0 );
        
        if ( ! $agent_id ) {
            wp_send_json_error( __( 'Invalid agent ID.', 'darsna-tutor' ) );
        }
        
        global $wpdb;
        $agents_table = $wpdb->prefix . 'latepoint_agents';
        
        $agent = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$agents_table} WHERE id = %d",
            $agent_id
        ));
        
        if ( ! $agent ) {
            wp_send_json_error( __( 'Agent not found.', 'darsna-tutor' ) );
        }
        
        // Get services
        $services = $this->get_agent_services( $agent_id );
        
        // Get WordPress user data
        $wp_user = get_user_by( 'email', $agent->email );
        
        $agent_data = [
            'agent' => $agent,
            'services' => $services,
            'wp_user' => $wp_user ? $wp_user->data : null,
        ];
        
        wp_send_json_success( $agent_data );
    }
    
    /**
     * AJAX: Update agent services
     */
    public function ajax_update_agent_services(): void {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'darsna-tutor' ) );
        }
        
        $agent_id = intval( $_POST['agent_id'] ?? 0 );
        $services = $_POST['services'] ?? [];
        
        if ( ! $agent_id ) {
            wp_send_json_error( __( 'Invalid agent ID.', 'darsna-tutor' ) );
        }
        
        $backend = Darsna_Tutor_Backend::instance();
        $result = $backend->assign_agent_services( $agent_id, $services );
        
        if ( $result ) {
            wp_send_json_success( __( 'Agent services updated successfully.', 'darsna-tutor' ) );
        } else {
            wp_send_json_error( __( 'Failed to update agent services.', 'darsna-tutor' ) );
        }
    }
    
    /**
     * AJAX: Update agent schedule
     */
    public function ajax_update_agent_schedule(): void {
        check_ajax_referer( 'darsna_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions.', 'darsna-tutor' ) );
        }
        
        $agent_id = intval( $_POST['agent_id'] ?? 0 );
        $schedule = $_POST['schedule'] ?? [];
        
        if ( ! $agent_id ) {
            wp_send_json_error( __( 'Invalid agent ID.', 'darsna-tutor' ) );
        }
        
        $backend = Darsna_Tutor_Backend::instance();
        $result = $backend->set_agent_schedule( $agent_id, $schedule );
        
        if ( $result ) {
            wp_send_json_success( __( 'Agent schedule updated successfully.', 'darsna-tutor' ) );
        } else {
            wp_send_json_error( __( 'Failed to update agent schedule.', 'darsna-tutor' ) );
        }
    }
}
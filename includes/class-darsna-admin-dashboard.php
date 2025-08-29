<?php
/**
 * Admin Dashboard for Darsna Tutor Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load development compatibility stubs if in development environment
if (!function_exists('add_action') && file_exists(dirname(__DIR__) . '/dev-compatibility.php')) {
    require_once dirname(__DIR__) . '/dev-compatibility.php';
}

class Darsna_Admin_Dashboard {
    
    public function __construct() {
        
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'admin_notices', [ $this, 'show_pending_tutors_notice' ] );
        add_action( 'admin_action_approve_tutor', [ $this, 'handle_tutor_approval' ] );
    }
    public function get_dashboard_stats(): array {

        
        $stats = [];
        
        // Get total tutors
        $tutors = get_users( [ 'role' => 'tutor' ] );
        $stats['total_tutors'] = count( $tutors );
        
        // Get booking stats from LatePoint if available
        if ( class_exists( 'OsBookingHelper' ) && method_exists( 'OsBookingHelper', 'get_bookings' ) ) {
            try {
                $bookings = class_exists('OsBookingHelper') && method_exists('OsBookingHelper', 'get_bookings') ? OsBookingHelper::get_bookings( [] ) : [];
                $stats['total_bookings'] = count( $bookings );
                
                // Calculate total revenue
                $total_revenue = 0;
                foreach ( $bookings as $booking ) {
                    if ( isset($booking->status) && $booking->status == 'approved' ) {
                        if ( class_exists( 'OsServiceModel' ) ) {
                            $service = new OsServiceModel( $booking->service_id );
                            if ( $service && $service->id && isset($service->charge_amount) ) {
                                $total_revenue += $service->charge_amount;
                            }
                        }
                    }
                }
                $stats['total_revenue'] = $total_revenue;
            } catch ( Exception $e ) {
                $stats['total_bookings'] = 0;
                $stats['total_revenue'] = 0;
            }
        } else {
            $stats['total_bookings'] = 0;
            $stats['total_revenue'] = 0;
        }
        
        // Get order stats from WooCommerce if available
        if ( class_exists( 'WooCommerce' ) ) {
            $orders = wc_get_orders([
                'status' => 'completed',
                'limit' => -1
            ]);
            $stats['wc_orders'] = count( $orders );
        } else {
            $stats['wc_orders'] = 0;
        }
        
        // Get recent registrations (last 30 days)
        $recent_users = get_users( [
            'date_query' => [
                [
                    'after' => '30 days ago'
                ]
            ],
            'fields' => 'ID'
        ] );
        $stats['recent_registrations'] = count( $recent_users );
        

        
        return $stats;
    }
    public function add_admin_menu(): void {
        add_menu_page(
            __( 'Darsna Tutors', 'darsna-tutor-registration' ),
            __( 'Darsna Tutors', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-tutors',
            [ $this, 'render_dashboard' ],
            'dashicons-welcome-learn-more',
            30
        );
        
        add_submenu_page(
            'darsna-tutors',
            __( 'Payouts', 'darsna-tutor-registration' ),
            __( 'Payouts', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-payouts',
            [ $this, 'render_payouts_page' ]
        );
        
        add_submenu_page(
            'darsna-tutors',
            __( 'Pending Tutors', 'darsna-tutor-registration' ),
            __( 'Pending Tutors', 'darsna-tutor-registration' ),
            'manage_options',
            'darsna-pending-tutors',
            [ $this, 'render_pending_tutors' ]
        );
        
        add_submenu_page(
                'darsna-dashboard',
                __( 'Settings', 'darsna-tutor-registration' ),
                __( 'Settings', 'darsna-tutor-registration' ),
                'manage_options',
                'darsna-settings',
                function() {
                    $commission_system = new Darsna_Commission_System();
                    $commission_system->render_settings_page();
                }
            );
    }
    public function enqueue_admin_scripts( $hook ): void {
        if ( strpos( $hook, 'darsna' ) === false ) {
            return;
        }
        
        wp_enqueue_style( 
            'darsna-admin', 
            DARSNA_TUTOR_URL . 'assets/css/admin-dashboard.css', 
            [], 
            DARSNA_TUTOR_VERSION 
        );
        
        wp_enqueue_script( 
            'darsna-admin', 
            DARSNA_TUTOR_URL . 'assets/js/admin-dashboard.js', 
            [ 'jquery' ], 
            DARSNA_TUTOR_VERSION, 
            true 
        );
        
        wp_localize_script( 'darsna-admin', 'darsna_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'darsna_admin' ),
            'export_payouts_nonce' => wp_create_nonce( 'export_payouts' ),
            'bulk_mark_paid_nonce' => wp_create_nonce( 'bulk_mark_paid' ),
            'mark_payout_paid_nonce' => wp_create_nonce( 'mark_payout_paid' )
        ] );
    }
    public function render_dashboard(): void {
        // Use LatePoint's native commission system
        if ( class_exists( 'OsCommissionHelper' ) ) {
            $totals = OsCommissionHelper::get_platform_earnings_summary( 'earned' );
            $unpaid_totals = OsCommissionHelper::get_platform_earnings_summary( 'pending' );
        } else {
            // Fallback if LatePoint commission not available
            $totals = (object) [ 'total_revenue' => 0, 'total_platform_earnings' => 0, 'total_tutor_earnings' => 0 ];
            $unpaid_totals = (object) [ 'total_tutor_earnings' => 0, 'total_bookings' => 0 ];
        }
        
        ?>
        <div class="wrap darsna-dashboard">
            <h1><?php esc_html_e( 'Darsna Tutors Dashboard', 'darsna-tutor-registration' ); ?></h1>
            
            <div class="darsna-stats-grid">
                <div class="stat-card">
                    <h3><?php esc_html_e( 'Total Revenue', 'darsna-tutor-registration' ); ?></h3>
                    <p class="stat-value">$<?php echo number_format( $totals->total_revenue ?? 0, 2 ); ?></p>
                </div>
                
                <div class="stat-card">
                    <h3><?php esc_html_e( 'Platform Earnings', 'darsna-tutor-registration' ); ?></h3>
                    <p class="stat-value">$<?php echo number_format( $totals->total_platform_earnings ?? 0, 2 ); ?></p>
                </div>
                
                <div class="stat-card">
                    <h3><?php esc_html_e( 'Tutor Earnings', 'darsna-tutor-registration' ); ?></h3>
                    <p class="stat-value">$<?php echo number_format( $totals->total_tutor_earnings ?? 0, 2 ); ?></p>
                </div>
                
                <div class="stat-card highlight">
                    <h3><?php esc_html_e( 'Pending Payouts', 'darsna-tutor-registration' ); ?></h3>
                    <p class="stat-value">$<?php echo number_format( $unpaid_totals->total_tutor_earnings ?? 0, 2 ); ?></p>
                    <p class="stat-meta"><?php echo sprintf( __( '%d unpaid bookings', 'darsna-tutor-registration' ), $unpaid_totals->total_bookings ?? 0 ); ?></p>
                </div>
            </div>
            
            <div class="darsna-quick-links">
                <h2><?php esc_html_e( 'Quick Actions', 'darsna-tutor-registration' ); ?></h2>
                <a href="<?php echo admin_url( 'admin.php?page=darsna-payouts' ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Manage Payouts', 'darsna-tutor-registration' ); ?>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=darsna-pending-tutors' ); ?>" class="button">
                    <?php esc_html_e( 'Review Pending Tutors', 'darsna-tutor-registration' ); ?>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=darsna-settings' ); ?>" class="button">
                    <?php esc_html_e( 'Settings', 'darsna-tutor-registration' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
    public function render_payouts_page(): void {
        // Get filters
        $agent_id = intval( $_GET['agent_id'] ?? 0 );
        $status = sanitize_text_field( $_GET['status'] ?? '' );
        $date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to = sanitize_text_field( $_GET['date_to'] ?? '' );
        $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $per_page = 50;
        
        // Get payouts using LatePoint's native commission system
        if ( class_exists( 'OsCommissionHelper' ) ) {
            // Get all tutors with earnings for admin dashboard
            $payouts = OsCommissionHelper::get_all_tutors_earnings( $status ?: 'earned' );
            
            // Filter by agent if specified
            if ( $agent_id ) {
                $payouts = array_filter( $payouts, function( $payout ) use ( $agent_id ) {
                    return $payout['tutor_id'] == $agent_id;
                } );
            }
            
            // Apply pagination
            $payouts = array_slice( $payouts, ( $paged - 1 ) * $per_page, $per_page );
            
            // Get totals for current filter
            if ( $agent_id ) {
                $filtered_totals = OsCommissionHelper::get_tutor_earnings_summary( $agent_id, $status ?: 'earned' );
            } else {
                $filtered_totals = OsCommissionHelper::get_platform_earnings_summary( $status ?: 'earned' );
            }
        } else {
            $payouts = [];
            $filtered_totals = [ 'total_earnings' => 0, 'total_bookings' => 0 ];
        }
        
        // Get all agents for filter dropdown
        $agents = $this->get_active_agents();
        
        ?>
        <div class="wrap darsna-payouts">
            <h1>
                <?php esc_html_e( 'Manage Payouts', 'darsna-tutor-registration' ); ?>
                <a href="#" class="page-title-action" id="export-payouts">
                    <?php esc_html_e( 'Export CSV', 'darsna-tutor-registration' ); ?>
                </a>
            </h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="darsna-payouts">
                    
                    <select name="agent_id" class="filter-select">
                        <option value=""><?php esc_html_e( 'All Tutors', 'darsna-tutor-registration' ); ?></option>
                        <?php foreach ( $agents as $agent ) : ?>
                            <option value="<?php echo esc_attr( $agent->id ); ?>" <?php selected( $agent_id, $agent->id ); ?>>
                                <?php echo esc_html( $agent->first_name . ' ' . $agent->last_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="filter-select">
                        <option value=""><?php esc_html_e( 'All Status', 'darsna-tutor-registration' ); ?></option>
                        <option value="unpaid" <?php selected( $status, 'unpaid' ); ?>><?php esc_html_e( 'Unpaid', 'darsna-tutor-registration' ); ?></option>
                        <option value="paid" <?php selected( $status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'darsna-tutor-registration' ); ?></option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'darsna-tutor-registration' ); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'darsna-tutor-registration' ); ?>">
                    
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'darsna-tutor-registration' ); ?>">
                    
                    <?php if ( $agent_id || $status || $date_from || $date_to ) : ?>
                        <a href="<?php echo admin_url( 'admin.php?page=darsna-payouts' ); ?>" class="button">
                            <?php esc_html_e( 'Clear Filters', 'darsna-tutor-registration' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Summary for filtered results -->
            <div class="payout-summary">
                <h3><?php esc_html_e( 'Summary', 'darsna-tutor-registration' ); ?></h3>
                <p>
                    <?php 
                    printf( 
                        __( 'Total: $%s | Platform Fees: $%s | Tutor Earnings: $%s', 'darsna-tutor-registration' ),
                        number_format( $filtered_totals->total_gross ?? 0, 2 ),
                        number_format( $filtered_totals->total_fees ?? 0, 2 ),
                        number_format( $filtered_totals->total_earnings ?? 0, 2 )
                    );
                    ?>
                </p>
            </div>
            
            <!-- Payouts Table -->
            <form id="payouts-form" method="post">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="select-all-payouts">
                            </td>
                            <th><?php esc_html_e( 'Order', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Tutor', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Customer', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Gross', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Fee (20%)', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Net', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'darsna-tutor-registration' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'darsna-tutor-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $payouts ) ) : ?>
                            <tr>
                                <td colspan="10"><?php esc_html_e( 'No payouts found.', 'darsna-tutor-registration' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $payouts as $payout ) : ?>
                                <tr>
                                    <td class="check-column">
                                        <?php if ( $payout->payout_status === 'unpaid' ) : ?>
                                            <input type="checkbox" name="payout_ids[]" value="<?php echo esc_attr( $payout->id ); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url( 'post.php?post=' . $payout->order_id . '&action=edit' ); ?>">
                                            #<?php echo esc_html( $payout->order_id ); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payout->order_date ) ) ); ?></td>
                                    <td><?php echo esc_html( $payout->first_name . ' ' . $payout->last_name ); ?></td>
                                    <td><?php echo esc_html( $payout->customer_name ); ?></td>
                                    <td>$<?php echo number_format( $payout->gross_amount, 2 ); ?></td>
                                    <td>$<?php echo number_format( $payout->platform_fee, 2 ); ?></td>
                                    <td><strong>$<?php echo number_format( $payout->tutor_earning, 2 ); ?></strong></td>
                                    <td>
                                        <?php if ( $payout->payout_status === 'paid' ) : ?>
                                            <span class="status-badge paid">
                                                <?php esc_html_e( 'Paid', 'darsna-tutor-registration' ); ?>
                                            </span>
                                            <?php if ( $payout->payout_date ) : ?>
                                                <br><small><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $payout->payout_date ) ) ); ?></small>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="status-badge unpaid">
                                                <?php esc_html_e( 'Unpaid', 'darsna-tutor-registration' ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( $payout->payout_status === 'unpaid' ) : ?>
                                            <button type="button" class="button button-small mark-paid" data-payout-id="<?php echo esc_attr( $payout->id ); ?>">
                                                <?php esc_html_e( 'Mark Paid', 'darsna-tutor-registration' ); ?>
                                            </button>
                                        <?php elseif ( $payout->payout_reference ) : ?>
                                            <small><?php echo esc_html( $payout->payout_reference ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ( ! empty( $payouts ) ) : ?>
                    <div class="tablenav bottom">
                        <div class="alignleft actions bulkactions">
                            <button type="button" class="button" id="bulk-mark-paid">
                                <?php esc_html_e( 'Mark Selected as Paid', 'darsna-tutor-registration' ); ?>
                            </button>
                        </div>
                        
                        <div class="tablenav-pages">
                            <?php
                            $total_items = $filtered_totals->total_count ?? 0;
                            $total_pages = ceil( $total_items / $per_page );
                            
                            if ( $total_pages > 1 ) {
                                echo paginate_links( [
                                    'base' => add_query_arg( 'paged', '%#%' ),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $paged
                                ] );
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Mark Paid Modal -->
        <div id="mark-paid-modal" class="darsna-modal hidden">
            <div class="modal-content">
                <h3><?php esc_html_e( 'Mark Payout as Paid', 'darsna-tutor-registration' ); ?></h3>
                <p>
                    <label><?php esc_html_e( 'Payment Reference (optional):', 'darsna-tutor-registration' ); ?></label>
                    <input type="text" id="payout-reference" class="widefat" placeholder="<?php esc_attr_e( 'e.g., Bank transfer #12345', 'darsna-tutor-registration' ); ?>">
                </p>
                <p class="submit">
                    <button type="button" class="button button-primary" id="confirm-mark-paid">
                        <?php esc_html_e( 'Confirm', 'darsna-tutor-registration' ); ?>
                    </button>
                    <button type="button" class="button" id="cancel-mark-paid">
                        <?php esc_html_e( 'Cancel', 'darsna-tutor-registration' ); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pending tutors page with enhanced WordPress API integration
     */
    public function render_pending_tutors(): void {
        // Use WordPress get_users API with performance optimizations
        $pending_tutors = get_users( [
            'meta_key' => 'tutor_status',
            'meta_value' => 'pending_approval',
            'orderby' => 'registered',
            'order' => 'DESC',
            'number' => 50, // Limit for performance
            'fields' => 'all_with_meta' // Get user data with meta in one query
        ] );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Pending Tutor Approvals', 'darsna-tutor-registration' ); ?></h1>
            
            <?php if ( isset( $_GET['approved'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Tutor has been approved successfully!', 'darsna-tutor-registration' ); ?></p>
                </div>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'darsna-tutor-registration' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'darsna-tutor-registration' ); ?></th>
                        <th><?php esc_html_e( 'Subjects', 'darsna-tutor-registration' ); ?></th>
                        <th><?php esc_html_e( 'Hourly Rate', 'darsna-tutor-registration' ); ?></th>
                        <th><?php esc_html_e( 'Registered', 'darsna-tutor-registration' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'darsna-tutor-registration' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $pending_tutors ) ) : ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e( 'No pending tutors.', 'darsna-tutor-registration' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $pending_tutors as $tutor ) : ?>
                            <?php
                            $subjects = get_user_meta( $tutor->ID, 'tutor_subjects', true );
            $hourly_rate = get_user_meta( $tutor->ID, 'tutor_hourly_rate', true );
            $registration_date = get_user_meta( $tutor->ID, 'tutor_registration_date', true );
                            
                            // Convert subject IDs to readable names using registration system
            $subject_names = array();
            if (is_array($subjects) && !empty($subjects)) {
                if (class_exists('Darsna_Registration_System')) {
                    $registration_system = Darsna_Registration_System::get_instance();
                    $subject_names = $registration_system->get_subjects($subjects, true);
                }
            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $tutor->display_name ); ?></strong>
                                    <br>
                                    <a href="<?php echo get_edit_user_link( $tutor->ID ); ?>">
                                        <?php esc_html_e( 'View Profile', 'darsna-tutor-registration' ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $tutor->user_email ); ?></td>
                                <td><?php echo esc_html( implode( ', ', $subject_names ) ); ?></td>
                                <td>$<?php echo esc_html( $hourly_rate ); ?>/hr</td>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $registration_date ) ) ); ?></td>
                                <td>
                                    <?php
                                    $approve_url = wp_nonce_url(
                                        admin_url( 'admin.php?action=approve_tutor&user_id=' . $tutor->ID ),
                                        'approve_tutor_' . $tutor->ID
                                    );
                                    ?>
                                    <a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary">
                                        <?php esc_html_e( 'Approve', 'darsna-tutor-registration' ); ?>
                                    </a>
                                    <a href="<?php echo get_edit_user_link( $tutor->ID ); ?>" class="button">
                                        <?php esc_html_e( 'Edit', 'darsna-tutor-registration' ); ?>
                                    </a>
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
     * Render settings page
     */
    // Commission settings moved to Darsna_Commission_System class
    
    /**
     * Get active agents using LatePoint 5 API with WordPress user integration
     * 
     * @return array Array of agent objects with id, first_name, last_name, wp_user_data
     */
    private function get_active_agents(): array {
        // Use LatePoint 5 Agent Helper only
        if ( ! class_exists( 'OsAgentHelper' ) || ! method_exists( 'OsAgentHelper', 'get_allowed_active_agents' ) ) {
            return [];
        }
        $lp_agents = OsAgentHelper::get_allowed_active_agents();
        
        if ( ! $lp_agents ) {
            return [];
        }
        
        $agents = [];
        foreach ( $lp_agents as $agent ) {
            $agent_data = (object) [
                'id' => $agent->id,
                'first_name' => $agent->first_name,
                'last_name' => $agent->last_name,
                'email' => $agent->email ?? '',
                'wp_user_id' => $agent->wp_user_id ?? null
            ];
            
            // Enhance with WordPress user data if available
            if ( $agent_data->wp_user_id ) {
                $wp_user = get_userdata( $agent_data->wp_user_id );
                if ( $wp_user ) {
                    $agent_data->wp_user_email = $wp_user->user_email;
                    $agent_data->wp_display_name = $wp_user->display_name;
                    $agent_data->tutor_status = get_user_meta( $agent_data->wp_user_id, 'tutor_status', true );
                    $agent_data->hourly_rate = get_user_meta( $agent_data->wp_user_id, 'tutor_hourly_rate', true );
                }
            }
            
            $agents[] = $agent_data;
        }
        
        return $agents;
    }
    
    /**
     * Get subject names optimized with WordPress and WooCommerce API integration
     * 
     * @param array $subject_ids Array of subject IDs
     * @param int $user_id WordPress user ID for caching context
     * @return array Array of subject names
     */
    private function get_subject_names_optimized( $subject_ids, $user_id = 0 ): array {
        if ( empty( $subject_ids ) || ! is_array( $subject_ids ) ) {
            return [];
        }
        

        
        $subject_names = [];
        
        // Use LatePoint subjects only
        foreach ( $subject_ids as $subject_id ) {
            if ( class_exists( 'OsServiceModel' ) ) {
                $subject = new OsServiceModel( $subject_id );
                if ( $subject && $subject->id && ! empty( $subject->name ) ) {
                    $subject_names[] = $subject->name;
                }
            }
        }
        

        
        return $subject_names;
    }
    public function show_pending_tutors_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $pending_tutors = get_users( [
            'meta_key' => 'tutor_status',
            'meta_value' => 'pending_approval',
            'number' => -1
        ] );
        
        if ( ! empty( $pending_tutors ) ) {
            $count = count( $pending_tutors );
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php 
                    printf( 
                        _n( 
                            'There is %d tutor pending approval.', 
                            'There are %d tutors pending approval.', 
                            $count, 
                            'darsna-tutor-registration' 
                        ), 
                        $count 
                    ); 
                    ?>
                    <a href="<?php echo admin_url( 'admin.php?page=darsna-pending-tutors' ); ?>"><?php esc_html_e( 'View pending tutors', 'darsna-tutor-registration' ); ?></a>
                </p>
            </div>
            <?php
        }
    }
    public function handle_tutor_approval(): void {
        if ( ! current_user_can( 'manage_options' ) || empty( $_GET['user_id'] ) ) {
            return;
        }
        
        check_admin_referer( 'approve_tutor_' . $_GET['user_id'] );
        
        $user_id = intval( $_GET['user_id'] );
        update_user_meta( $user_id, 'tutor_status', 'active' );
        
        $user = get_userdata( $user_id );
        if ( $user && is_object( $user ) ) {
            $user->remove_role( 'pending_tutor' );
            $user->remove_cap( 'pending_tutor' );
            $user->add_role( 'latepoint_agent' );
        }
        
        $this->create_latepoint_agent( $user_id );
        $this->notify_tutor_approved( $user_id );
        
        wp_redirect( add_query_arg( 'tutor_approved', '1', wp_get_referer() ) );
        exit;
    }
    
    private function create_latepoint_agent( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }
        
        if ( get_user_meta( $user_id, 'latepoint_agent_id', true ) ) {
            return;
        }
        
        $agent = new OsAgentModel();
        $agent->first_name = $user->first_name ?: $user->display_name ?: '';
        $agent->last_name = $user->last_name ?: '';
        $agent->email = $user->user_email ?: '';
        $agent->phone = get_user_meta( $user_id, 'billing_phone', true );
        $agent->bio = get_user_meta( $user_id, 'tutor_bio', true );
        $agent->status = LATEPOINT_AGENT_STATUS_ACTIVE ?? 'active';
        $agent->wp_user_id = $user_id;
        
        if ( ! $agent->save() || ! $agent->id ) {
            return;
        }
        
        update_user_meta( $user_id, 'latepoint_agent_id', $agent->id );
        $this->assign_services_to_agent( $agent->id, $user_id );
        $this->set_agent_pricing( $agent->id, $user_id );
    }
    
    private function assign_services_to_agent( $agent_id, $user_id ) {
        $tutor_subjects = get_user_meta( $user_id, 'tutor_subjects', true );
        if ( empty( $tutor_subjects ) || ! is_array( $tutor_subjects ) ) {
            return;
        }
        
        $registration_system = Darsna_Registration_System::get_instance();
        $available_services = $registration_system->get_allowed_active_services();
        if ( empty( $available_services ) ) {
            return;
        }
        
        $assigned_count = 0;
        $failed_assignments = array();
        
        foreach ( $tutor_subjects as $subject_id ) {
            $service_to_assign = null;
            foreach ( $available_services as $service ) {
                if ( isset( $service['id'] ) && $service['id'] == $subject_id ) {
                    $service_to_assign = $service;
                    break;
                }
            }
            
            if ( ! $service_to_assign ) {
                continue;
            }
            
            $assignment_result = $this->assign_single_service_to_agent( $agent_id, $service_to_assign['id'], $service_to_assign['name'] );
            
            if ( $assignment_result ) {
                $assigned_count++;
            } else {
                $failed_assignments[] = "Failed to assign service: {$service_to_assign['name']}";
            }
        }
    }
    
    private function assign_single_service_to_agent( $agent_id, $service_id, $service_name ) {
        if ( empty( $agent_id ) || empty( $service_id ) ) {
            return false;
        }
        
        $connection_data = [
            'agent_id' => $agent_id,
            'service_id' => $service_id,
            'location_id' => 1,
            'is_custom_price' => true,
            'is_custom_hours' => false,
            'is_custom_duration' => false
        ];
        
        return OsConnectorHelper::save_connection( $connection_data );
    }
    
    private function set_agent_pricing( $agent_id, $user_id ) {
        $hourly_rate = get_user_meta( $user_id, 'tutor_hourly_rate', true );
        if ( ! $hourly_rate || ! is_numeric( $hourly_rate ) || $hourly_rate <= 0 ) {
            return;
        }
        
        $hourly_rate = floatval( $hourly_rate );
        $services = $this->get_agent_services_for_pricing( $agent_id );
        if ( empty( $services ) ) {
            return;
        }
        
        $pricing_set_count = 0;
        $pricing_failures = array();
        
        foreach ( $services as $service ) {
            $service_id = is_object( $service ) ? ( isset( $service->id ) ? $service->id : 0 ) : ( isset( $service['id'] ) ? $service['id'] : 0 );
            $service_name = is_object( $service ) ? ( isset( $service->name ) ? $service->name : "Service {$service_id}" ) : ( isset( $service['name'] ) ? $service['name'] : "Service {$service_id}" );
            
            $pricing_result = $this->set_single_service_pricing( $agent_id, $service_id, $hourly_rate, $service_name );
            
            if ( $pricing_result ) {
                $pricing_set_count++;
            } else {
                $pricing_failures[] = $service_name;
            }
        }
    }
    
    private function get_agent_services_for_pricing( $agent_id ) {
        if ( empty( $agent_id ) ) {
            return array();
        }
        
        $service_ids = OsConnectorHelper::get_connected_object_ids( 'service_id', [ 'agent_id' => $agent_id ] );
        $services = [];
        if ( !empty( $service_ids ) ) {
            foreach ( $service_ids as $service_id ) {
                $service = new OsServiceModel( $service_id );
                if ( isset( $service->id ) && $service->id ) {
                    $services[] = $service;
                }
            }
        }
        if ( ! empty( $services ) ) {
            return $services;
        }
        
        return array();
    }
    
    private function set_single_service_pricing( $agent_id, $service_id, $hourly_rate, $service_name ) {
        if ( empty( $agent_id ) || empty( $service_id ) || empty( $hourly_rate ) ) {
            return false;
        }
        
        $existing_connector = OsConnectorModel::where( [
            'agent_id' => $agent_id,
            'service_id' => $service_id
        ] )->set_limit( 1 )->get_results();
        
        $connector = ! empty( $existing_connector ) ? $existing_connector[0] : new OsConnectorModel();
        
        if ( empty( $existing_connector ) ) {
            $connector->agent_id = $agent_id;
            $connector->service_id = $service_id;
            $connector->location_id = 1;
        }
        
        $connector->charge_amount = $hourly_rate;
        $connector->is_price_variable = false;
        $connector->price_min = $hourly_rate;
        $connector->price_max = $hourly_rate;
        $connector->is_custom_price = true;
        $connector->is_custom_hours = false;
        $connector->is_custom_duration = false;
        
        return $connector->save();
    }
    
    private function notify_tutor_approved( int $user_id ): void {
        $notification_system = new Darsna_Notification_System();
        $notification_system->notify_tutor_approved( $user_id );
    }
    
    public function sync_agent_to_wordpress( $agent ) {
        if ( ! isset( $agent->wp_user_id ) || ! $agent->wp_user_id ) {
            return;
        }
        
        $update_data = array( 'ID' => $agent->wp_user_id );
        if ( isset( $agent->first_name ) ) $update_data['first_name'] = $agent->first_name;
        if ( isset( $agent->last_name ) ) $update_data['last_name'] = $agent->last_name;
        if ( isset( $agent->email ) ) $update_data['user_email'] = $agent->email;
        
        wp_update_user( $update_data );
        if ( isset( $agent->phone ) ) {
            update_user_meta( $agent->wp_user_id, 'billing_phone', $agent->phone );
        }
        if ( isset( $agent->bio ) ) {
            update_user_meta( $agent->wp_user_id, 'tutor_bio', $agent->bio );
        }
    }
}

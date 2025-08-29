<?php
/**
 * Commission and Payout System for Darsna Tutor Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load development compatibility stubs if in development environment
if (file_exists(dirname(__DIR__) . '/dev-compatibility.php')) {
    require_once dirname(__DIR__) . '/dev-compatibility.php';
}

class Darsna_Commission_System {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'darsna_payouts';
        
        $this->init_hooks();
    }
    
    private function init_hooks(): void {
        // Database installation
        register_activation_hook( DARSNA_TUTOR_FILE, [ $this, 'create_tables' ] );
        
        // Commission recording hooks
        add_action( 'woocommerce_payment_complete', [ $this, 'record_commission_on_payment' ], 20 );
        
        // AJAX handlers for admin
        add_action( 'wp_ajax_darsna_export_payouts', [ $this, 'export_payouts_csv' ] );
        add_action( 'wp_ajax_darsna_mark_payout_paid', [ $this, 'ajax_mark_payout_paid' ] );
        add_action( 'wp_ajax_darsna_bulk_mark_paid', [ $this, 'bulk_mark_paid' ] );
        
        // AJAX handlers for tutors
        add_action( 'wp_ajax_darsna_export_tutor_earnings', [ $this, 'export_tutor_earnings_csv' ] );
        
        // Weekly payout summary email
        add_action( 'darsna_weekly_payout_summary', [ $this, 'send_weekly_payout_summary' ] );
        
        // Schedule weekly summary if not scheduled
        if ( ! wp_next_scheduled( 'darsna_weekly_payout_summary' ) ) {
            wp_schedule_event( strtotime( 'next monday 9am' ), 'weekly', 'darsna_weekly_payout_summary' );
        }
        
        // LatePoint integration hooks
        add_filter( 'latepoint_agent_dashboard_tabs', [ $this, 'add_earnings_tab' ], 10, 1 );
        add_action( 'latepoint_agent_dashboard_earnings', [ $this, 'render_earnings_dashboard' ], 10, 0 );
        add_action( 'wp_ajax_darsna_export_earnings', [ $this, 'ajax_export_earnings' ] );
        
        // LatePoint booking hooks
        add_action( 'latepoint_booking_created', [ $this, 'handle_booking_commission' ], 10, 1 );
        add_action( 'latepoint_booking_completed', [ $this, 'handle_booking_commission' ], 10, 1 );
        add_action( 'latepoint_booking_status_changed', [ $this, 'handle_booking_status_change' ], 10, 3 );
        add_action( 'latepoint_booking_updated_admin', [ $this, 'sync_booking_to_order' ], 10, 1 );
    }
    public function record_commission_on_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        // Check if this order has an associated agent/tutor
        $agent_id = $order->get_meta( '_darsna_agent_id' );
        if ( $agent_id ) {
            $this->record_commission( $order_id );
        }
    }
    public function init_commission_settings() {
        add_option( 'darsna_commission_system_enabled', 'yes' );
        add_option( 'darsna_commission_rate', '0.15' );
    }
    private function is_commission_enabled() {
        return get_option( 'darsna_commission_system_enabled', 'yes' ) === 'yes';
    }
    
    public function handle_booking_commission( $booking ) {
        if ( ! class_exists( 'OsCommissionHelper' ) || ! $booking ) {
            return;
        }
        
        // Define LatePoint constants if not defined
        if ( ! defined( 'LATEPOINT_BOOKING_STATUS_APPROVED' ) ) {
            define( 'LATEPOINT_BOOKING_STATUS_APPROVED', 'approved' );
        }
        if ( ! defined( 'LATEPOINT_TRANSACTION_STATUS_SUCCEEDED' ) ) {
            define( 'LATEPOINT_TRANSACTION_STATUS_SUCCEEDED', 'completed' );
        }
        
        // Only process if booking has an agent and is completed
        if ( empty( $booking->agent_id ) || $booking->status !== LATEPOINT_BOOKING_STATUS_APPROVED ) {
            return;
        }
        
        // Get the transaction for this booking
        $transaction = method_exists( $booking, 'get_transaction' ) ? $booking->get_transaction() : null;
        if ( $transaction && $transaction->status == LATEPOINT_TRANSACTION_STATUS_SUCCEEDED ) {
            // Use LatePoint's native commission processing
            if ( class_exists( 'OsCommissionHelper' ) && method_exists( 'OsCommissionHelper', 'process_transaction_commission' ) ) {
                OsCommissionHelper::process_transaction_commission( $transaction->id );
            }
            
            // Commission processed silently
        }
    }
    
    /**
     * Handle booking status changes for commission updates
     * Uses LatePoint's native commission system
     * Moved from LatePoint integration for centralized commission handling
     */
    public function handle_booking_status_change( $booking, $old_status, $new_status ) {
        if ( ! class_exists( 'OsCommissionHelper' ) || ! $booking ) {
            return;
        }
        
        // Define LatePoint constants if not defined
        if ( ! defined( 'LATEPOINT_BOOKING_STATUS_APPROVED' ) ) {
            define( 'LATEPOINT_BOOKING_STATUS_APPROVED', 'approved' );
        }
        
        // Let LatePoint handle commission status changes through its native hooks
        // The OsCommissionHelper will automatically update commission status based on booking/transaction status
        if ( $new_status === LATEPOINT_BOOKING_STATUS_APPROVED ) {
            $this->handle_booking_commission( $booking );
        }
        
        // Booking status updated silently
    }
    
    /**
     * Handle order refund and adjust commission accordingly
     * Moved from WooCommerce integration for centralized commission handling
     */
    public function handle_order_refund( $order_id, $refund_id ) {
        $order = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );
        
        if ( ! $order || ! $refund ) {
            return;
        }
        
        $agent_id = $order->get_meta( '_darsna_agent_id' );
        if ( $agent_id ) {
            $refund_amount = $refund->get_amount();
            $original_earning = $order->get_meta( '_darsna_tutor_earning' );
            
            // Calculate adjusted earnings
            $refund_percentage = $refund_amount / $order->get_total();
            $earning_adjustment = $original_earning * $refund_percentage;
            
            // Store refund data
            $refund->update_meta_data( '_darsna_earning_adjustment', -$earning_adjustment );
            $refund->save();
            
            // Update payout status if fully refunded
            if ( $refund_percentage >= 1 ) {
                $order->update_meta_data( '_darsna_payout_status', 'cancelled' );
                $order->save();
            }
            
            // Update commission record in database
            global $wpdb;
            $commission_table = $wpdb->prefix . 'darsna_payouts';
            
            $wpdb->update(
                $commission_table,
                [
                    'payout_status' => $refund_percentage >= 1 ? 'cancelled' : 'adjusted',
                    'tutor_earning' => $original_earning - $earning_adjustment
                ],
                [ 'order_id' => $order_id ],
                [ '%s', '%f' ],
                [ '%d' ]
            );
        }
    }
    
    /**
     * Legacy method for adding commission meta to order
     * @deprecated Use LatePoint's native OsWooCommerceHelper and OsCommissionHelper instead
     * Moved from WooCommerce integration for centralized commission handling
     */
    public function add_commission_meta_to_order( $order, $data ) {
        // This method is deprecated - LatePoint's native WooCommerce integration handles commission processing
        // Method is deprecated
        return;
    }
    public function handle_payment_complete( $order_id ) {
        // This method is deprecated - LatePoint's native WooCommerce integration handles payment completion
        // Method is deprecated
        return;
    }
    public function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            agent_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            gross_amount decimal(10,2) NOT NULL,
            platform_fee decimal(10,2) NOT NULL,
            tutor_earning decimal(10,2) NOT NULL,
            payout_status varchar(20) DEFAULT 'unpaid',
            payout_date datetime DEFAULT NULL,
            payout_reference varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY agent_id (agent_id),
            KEY order_id (order_id),
            KEY payout_status (payout_status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Table creation completed
    }
    public function record_commission( $order_id, $agent_id = null, $gross_amount = null, $commission_rate = null ) {
        global $wpdb;
        
        // Validate and enhance data using WooCommerce order API
        $order = wc_get_order( $order_id );
        if ( $order ) {
            // Use actual order total if gross_amount not provided or differs significantly
            $order_total = $order->get_total();
            if ( ! $gross_amount || abs( $gross_amount - $order_total ) > 0.01 ) {
                $gross_amount = $order_total;
            }
            
            // Get agent ID from order meta if not provided
            if ( ! $agent_id ) {
                $agent_id = $order->get_meta( '_darsna_agent_id' );
            }
            
            // Use order's commission rate if available
            if ( ! $commission_rate ) {
                $commission_rate = $order->get_meta( '_darsna_commission_rate' ) ?: 0.15;
            }
        }
        
        if ( ! $agent_id || ! $gross_amount ) {
            return false;
        }
        
        // Check for existing commission record to prevent duplicates
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE order_id = %d AND agent_id = %d",
            $order_id,
            $agent_id
        ) );
        
        if ( $existing ) {
            return $existing;
        }
        
        $platform_fee = $gross_amount * $commission_rate;
        $tutor_earning = $gross_amount - $platform_fee;
        
        $result = $wpdb->insert(
            $this->table_name,
            [
                'agent_id' => $agent_id,
                'order_id' => $order_id,
                'gross_amount' => $gross_amount,
                'platform_fee' => $platform_fee,
                'tutor_earning' => $tutor_earning,
                'payout_status' => 'unpaid'
            ],
            [ '%d', '%d', '%f', '%f', '%f', '%s' ]
        );
        
        if ( $result === false ) {
            return false;
        }
        
        $commission_id = $wpdb->insert_id;
        
        // Update WooCommerce order meta with commission tracking
        if ( $commission_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->update_meta_data( '_darsna_commission_id', $commission_id );
                $order->update_meta_data( '_darsna_payout_status', 'unpaid' );
                $order->save_meta_data();
            }
        }
        
        return $commission_id;
    }
    
    /**
     * Get payouts with filters
     */
    public function get_payouts( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'agent_id' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = [ '1=1' ];
        $values = [];
        
        if ( $args['agent_id'] ) {
            $where[] = 'p.agent_id = %d';
            $values[] = $args['agent_id'];
        }
        
        if ( $args['status'] ) {
            $where[] = 'p.payout_status = %s';
            $values[] = $args['status'];
        }
        
        if ( $args['date_from'] ) {
            $where[] = 'p.created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if ( $args['date_to'] ) {
            $where[] = 'p.created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where_clause = implode( ' AND ', $where );
        
        $query = "
            SELECT 
                p.*,
                COALESCE(a.first_name, wu.first_name, SUBSTRING_INDEX(wu.display_name, ' ', 1)) as first_name,
                COALESCE(a.last_name, wu.last_name, SUBSTRING_INDEX(wu.display_name, ' ', -1)) as last_name,
                COALESCE(a.email, wu.user_email) as agent_email,
                o.post_date as order_date,
                COALESCE(c.display_name, CONCAT(cm1.meta_value, ' ', cm2.meta_value)) as customer_name
            FROM {$this->table_name} p
            LEFT JOIN {$wpdb->prefix}latepoint_agents a ON p.agent_id = a.id
            LEFT JOIN {$wpdb->users} wu ON p.agent_id = wu.ID
            LEFT JOIN {$wpdb->posts} o ON p.order_id = o.ID
            LEFT JOIN {$wpdb->postmeta} om ON o.ID = om.post_id AND om.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->users} c ON om.meta_value = c.ID
            LEFT JOIN {$wpdb->postmeta} cm1 ON o.ID = cm1.post_id AND cm1.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} cm2 ON o.ID = cm2.post_id AND cm2.meta_key = '_billing_last_name'
            WHERE {$where_clause}
            ORDER BY p.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ";
        
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        if ( ! empty( $values ) ) {
            $query = $wpdb->prepare( $query, $values );
        }
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Render commission settings page
     * 
     * @return void
     */
    public function render_settings_page(): void {
        if ( isset( $_POST['submit'] ) ) {
            check_admin_referer( 'darsna_settings' );
            
            update_option( 'darsna_commission_system_enabled', isset( $_POST['commission_system_enabled'] ) );
            update_option( 'darsna_commission_rate', floatval( $_POST['commission_rate'] ) );
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Settings saved.', 'darsna-tutor-registration' ) . '</p></div>';
        }
        
        $commission_enabled = get_option( 'darsna_commission_system_enabled', true );
        $commission_rate = get_option( 'darsna_commission_rate', 0.20 );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Darsna Settings', 'darsna-tutor-registration' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'darsna_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Commission System', 'darsna-tutor-registration' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="commission_system_enabled" value="1" <?php checked( $commission_enabled ); ?>>
                                <?php esc_html_e( 'Enable commission system', 'darsna-tutor-registration' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Feature flag for safe rollout of the new payment flow.', 'darsna-tutor-registration' ); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="commission_rate"><?php esc_html_e( 'Commission Rate', 'darsna-tutor-registration' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="commission_rate" id="commission_rate" value="<?php echo esc_attr( $commission_rate ); ?>" min="0" max="1" step="0.01" disabled="disabled">
                            <p class="description">
                                <?php esc_html_e( 'Platform commission rate (0.20 = 20%). Default is 20%.', 'darsna-tutor-registration' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    // ========================================
    // LATEPOINT BOOKING INTEGRATION
    // ========================================
    
    /**
     * Sync booking status to WooCommerce order
     */
    public function sync_booking_to_order( $booking ) {
        if ( ! $booking || ! isset( $booking->id ) ) {
            return;
        }
        
        // Get order ID from booking meta
        $order_id = null;
        
        if ( method_exists( $booking, 'get_meta_by_key' ) ) {
            $order_id = $booking->get_meta_by_key( 'wc_order_id' );
        }
        
        if ( ! $order_id ) {
            return;
        }
        
        // Get WooCommerce order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        
        // Update order status based on booking status
        $booking_status = $booking->status ?? 'pending';
        
        switch ( $booking_status ) {
            case 'approved':
            case 'confirmed':
                if ( $order->get_status() !== 'completed' ) {
                    $order->update_status( 'processing', __( 'Booking confirmed in LatePoint', 'darsna-tutor-registration' ) );
                }
                break;
                
            case 'cancelled':
                if ( ! in_array( $order->get_status(), [ 'cancelled', 'refunded' ] ) ) {
                    $order->update_status( 'cancelled', __( 'Booking cancelled in LatePoint', 'darsna-tutor-registration' ) );
                }
                break;
                
            case 'completed':
                if ( $order->get_status() !== 'completed' ) {
                    $order->update_status( 'completed', __( 'Booking completed in LatePoint', 'darsna-tutor-registration' ) );
                    
                    // Handle commission when booking is completed
                    $this->handle_booking_commission( $booking, $order );
                }
                break;
        }
    }
    
    /**
     * Calculate platform fee based on order total
     */
    public function calculate_platform_fee( $order_total ) {
        $commission_rate = get_option( 'darsna_commission_rate', 0.20 );
        return $order_total * $commission_rate;
    }
    
    /**
      * Get commission record by order ID
      */
     private function get_commission_by_order( $order_id ) {
         global $wpdb;
         
         $query = $wpdb->prepare(
             "SELECT * FROM {$this->table_name} WHERE order_id = %d LIMIT 1",
             $order_id
         );
         
         return $wpdb->get_row( $query );
     }
     
     // ========================================
     // LATEPOINT EARNINGS DASHBOARD
     // ========================================
     
     /**
      * Add earnings tab to LatePoint dashboard
      */
     public function add_earnings_tab( $tabs ) {
         $tabs['earnings'] = __( 'Earnings', 'darsna-tutor-registration' );
         return $tabs;
     }
     
     /**
      * Render earnings dashboard for agents
      */
     public function render_earnings_dashboard( $agent_id ) {
         $earnings_data = $this->get_agent_earnings( $agent_id );
         
         ?>
         <div class="earnings-dashboard">
             <!-- Earnings Summary Cards -->
             <div class="earnings-summary">
                 <div class="earnings-card">
                     <h4><?php esc_html_e( 'Total Earnings', 'darsna-tutor-registration' ); ?></h4>
                     <p class="earnings-amount">$<?php echo number_format( $earnings_data['total_earnings'], 2 ); ?></p>
                 </div>
                 <div class="earnings-card">
                     <h4><?php esc_html_e( 'Pending Payout', 'darsna-tutor-registration' ); ?></h4>
                     <p class="earnings-amount">$<?php echo number_format( $earnings_data['pending_payout'], 2 ); ?></p>
                 </div>
                 <div class="earnings-card">
                     <h4><?php esc_html_e( 'This Month', 'darsna-tutor-registration' ); ?></h4>
                     <p class="earnings-amount">$<?php echo number_format( $earnings_data['month_earnings'], 2 ); ?></p>
                 </div>
             </div>
             
             <!-- Earnings Table -->
             <div class="earnings-table-wrapper">
                 <h4><?php esc_html_e( 'Recent Bookings', 'darsna-tutor-registration' ); ?></h4>
                 <table class="earnings-table">
                     <thead>
                         <tr>
                             <th><?php esc_html_e( 'Date', 'darsna-tutor-registration' ); ?></th>
                             <th><?php esc_html_e( 'Student', 'darsna-tutor-registration' ); ?></th>
                             <th><?php esc_html_e( 'Service', 'darsna-tutor-registration' ); ?></th>
                             <th><?php esc_html_e( 'Gross', 'darsna-tutor-registration' ); ?></th>
                             <th><?php esc_html_e( 'Fee', 'darsna-tutor-registration' ); ?></th>
                             <th><?php esc_html_e( 'Net', 'darsna-tutor-registration' ); ?></th>
                             <th><?php esc_html_e( 'Status', 'darsna-tutor-registration' ); ?></th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ( $earnings_data['bookings'] as $booking ) : ?>
                         <tr>
                             <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['date'] ) ) ); ?></td>
                             <td><?php echo esc_html( $booking['student_name'] ); ?></td>
                             <td><?php echo esc_html( $booking['service_name'] ); ?></td>
                             <td>$<?php echo number_format( $booking['gross_amount'], 2 ); ?></td>
                             <td>$<?php echo number_format( $booking['platform_fee'], 2 ); ?></td>
                             <td>$<?php echo number_format( $booking['net_amount'], 2 ); ?></td>
                             <td>
                                 <span class="payout-status <?php echo esc_attr( $booking['payout_status'] ); ?>">
                                     <?php echo esc_html( ucfirst( $booking['payout_status'] ) ); ?>
                                 </span>
                             </td>
                         </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
             
             <!-- Export Button -->
             <div class="earnings-actions">
                 <button class="latepoint-btn" onclick="window.darsnaExportEarnings()">
                     <?php esc_html_e( 'Export to CSV', 'darsna-tutor-registration' ); ?>
                 </button>
             </div>
         </div>
         
         <?php
         // Enqueue LatePoint integration styles
          $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
          
          if ( $plugin_url ) {
              wp_enqueue_style(
                  'darsna-latepoint-integration',
                  $plugin_url . 'assets/css/latepoint-integration.css',
                  array(),
                  '4.0.0'
              );
          }
         ?>
         
         <script>
         window.darsnaExportEarnings = function() {
             window.location.href = '<?php echo admin_url( 'admin-ajax.php?action=darsna_export_tutor_earnings&agent_id=' . $agent_id . '&nonce=' . wp_create_nonce( 'export_earnings' ) ); ?>';
         };
         </script>
         <?php
     }
     
     /**
      * Get agent earnings data using direct database queries
      */
     public function get_agent_earnings( $agent_id ) {
         global $wpdb;
         
         // Check if we have our custom commission table
         $commission_table = $wpdb->prefix . 'latepoint_commissions';
         $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $commission_table ) );
         
         if ( ! $table_exists ) {
             // Try LatePoint's native commission table if it exists
             $latepoint_commission_table = $wpdb->prefix . 'latepoint_agent_commissions';
             $latepoint_table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $latepoint_commission_table ) );
             
             if ( $latepoint_table_exists ) {
                 return $this->get_earnings_from_latepoint_table( $agent_id, $latepoint_commission_table );
             }
             
             // No commission tables available
             return [
                 'total_earnings' => 0,
                 'pending_payout' => 0,
                 'month_earnings' => 0,
                 'bookings' => []
             ];
         }
         
         // Get total earnings (all statuses)
         $total_earnings = $wpdb->get_var( $wpdb->prepare(
             "SELECT SUM(tutor_earning) FROM {$commission_table} WHERE agent_id = %d",
             $agent_id
         ) ) ?: 0;
         
         // Get pending earnings (unpaid status)
         $pending_earnings = $wpdb->get_var( $wpdb->prepare(
             "SELECT SUM(tutor_earning) FROM {$commission_table} WHERE agent_id = %d AND payout_status = 'unpaid'",
             $agent_id
         ) ) ?: 0;
         
         // Calculate current month earnings
         $current_month_start = date( 'Y-m-01' );
         $current_month_end = date( 'Y-m-t' );
         
         $month_earnings = $wpdb->get_var( $wpdb->prepare(
             "SELECT SUM(tutor_earning) FROM {$commission_table} 
             WHERE agent_id = %d 
             AND created_at >= %s AND created_at <= %s",
             $agent_id,
             $current_month_start . ' 00:00:00',
             $current_month_end . ' 23:59:59'
         ) ) ?: 0;
         
         // Get recent bookings with commission data
         $bookings_data = $wpdb->get_results( $wpdb->prepare(
             "SELECT c.*, o.post_date as order_date
             FROM {$commission_table} c
             LEFT JOIN {$wpdb->posts} o ON c.order_id = o.ID
             WHERE c.agent_id = %d
             ORDER BY c.created_at DESC
             LIMIT 20",
             $agent_id
         ) );
         
         $bookings = [];
         foreach ( $bookings_data as $commission ) {
             // Get customer name from order
             $customer_name = 'Unknown Customer';
             if ( $commission->order_id ) {
                 $order = wc_get_order( $commission->order_id );
                 if ( $order ) {
                     $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                 }
             }
             
             $bookings[] = [
                 'date' => $commission->order_date ?: $commission->created_at,
                 'student_name' => trim( $customer_name ),
                 'service_name' => 'Tutoring Session',
                 'gross_amount' => (float) $commission->gross_amount,
                 'platform_fee' => (float) $commission->platform_fee,
                 'net_amount' => (float) $commission->tutor_earning,
                 'payout_status' => $commission->payout_status
             ];
         }
         
         return [
             'total_earnings' => (float) $total_earnings,
             'pending_payout' => (float) $pending_earnings,
             'month_earnings' => (float) $month_earnings,
             'bookings' => $bookings
         ];
     }
     
     /**
      * Get earnings from LatePoint's native commission table
      */
     private function get_earnings_from_latepoint_table( $agent_id, $table_name ) {
         global $wpdb;
         
         // Get total earnings
         $total_earnings = $wpdb->get_var( $wpdb->prepare(
             "SELECT SUM(agent_amount) FROM {$table_name} WHERE agent_id = %d",
             $agent_id
         ) ) ?: 0;
         
         // Get pending earnings
         $pending_earnings = $wpdb->get_var( $wpdb->prepare(
             "SELECT SUM(agent_amount) FROM {$table_name} WHERE agent_id = %d AND status = 'pending'",
             $agent_id
         ) ) ?: 0;
         
         // Calculate current month earnings
         $current_month_start = date( 'Y-m-01' );
         $current_month_end = date( 'Y-m-t' );
         
         $month_earnings = $wpdb->get_var( $wpdb->prepare(
             "SELECT SUM(agent_amount) FROM {$table_name} 
             WHERE agent_id = %d 
             AND created_at >= %s AND created_at <= %s",
             $agent_id,
             $current_month_start . ' 00:00:00',
             $current_month_end . ' 23:59:59'
         ) ) ?: 0;
         
         return [
             'total_earnings' => (float) $total_earnings,
             'pending_payout' => (float) $pending_earnings,
             'month_earnings' => (float) $month_earnings,
             'bookings' => [] // LatePoint table structure may be different
         ];
     }
     
     /**
      * Export earnings to CSV
      */
     public function ajax_export_earnings() {
         check_ajax_referer( 'darsna_nonce', 'nonce' );
         
         // Get current user ID from WordPress
       $user_id = 0;
       $current_user = wp_get_current_user();
       $user_id = $current_user->ID;
         
         if ( ! $user_id ) {
             return;
         }
         
         $agent = null;
       if ( class_exists( 'OsAgentHelper' ) && method_exists( 'OsAgentHelper', 'get_agent_by_user_id' ) ) {
           $agent = OsAgentHelper::get_agent_by_user_id( $user_id );
       }
       
       if ( ! $agent ) {
           return;
       }
       
       $earnings_data = $this->get_agent_earnings( $agent->id );
       
       // Set headers for CSV download
       header( 'Content-Type: text/csv' );
       header( 'Content-Disposition: attachment; filename="earnings_export.csv"' );
       
       $output = fopen( 'php://output', 'w' );
       
       // CSV headers
       fputcsv( $output, [ 'Date', 'Student', 'Service', 'Gross', 'Fee', 'Net', 'Status' ] );
       
       // CSV data
       foreach ( $earnings_data['bookings'] as $booking ) {
           fputcsv( $output, [
               $booking['date'],
               $booking['student_name'],
               $booking['service_name'],
               '$' . number_format( $booking['gross_amount'], 2 ),
               '$' . number_format( $booking['platform_fee'], 2 ),
               '$' . number_format( $booking['net_amount'], 2 ),
               ucfirst( $booking['payout_status'] )
           ] );
       }
       
       fclose( $output );
       exit;
     }
 }

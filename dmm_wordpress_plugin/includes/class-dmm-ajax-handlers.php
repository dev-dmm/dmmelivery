<?php
/**
 * AJAX Handlers class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 * 
 * NOTE: This is a placeholder class. The actual AJAX handler methods
 * need to be extracted from the original dm-delivery-bridge.php file.
 * This class structure is provided as a starting point.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_AJAX_Handlers
 */
class DMM_AJAX_Handlers {
    
    /**
     * Static flag to track if handlers have been registered
     * Prevents duplicate registrations
     *
     * @var bool
     */
    private static $handlers_registered = false;
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Main plugin instance
     *
     * @var DMM_Delivery_Bridge
     */
    private $plugin;
    
    /**
     * Constructor
     *
     * @param array                $options Plugin options
     * @param DMM_Delivery_Bridge $plugin Main plugin instance
     */
    public function __construct($options = [], $plugin = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->plugin = $plugin;
        
        $this->register_ajax_handlers();
    }
    
    /**
     * Register all AJAX handlers
     * Uses static flag to prevent duplicate registrations
     */
    private function register_ajax_handlers() {
        // Prevent duplicate registrations using static flag
        if (self::$handlers_registered) {
            return;
        }
        
        // Additional safety check: verify the three problematic handlers aren't already registered
        $critical_handlers = [
            'wp_ajax_dmm_acs_track_shipment',
            'wp_ajax_dmm_acs_create_voucher',
            'wp_ajax_dmm_acs_test_connection'
        ];
        
        foreach ($critical_handlers as $handler) {
            if (has_action($handler)) {
                // Handler already registered, skip registration to prevent duplicates
                return;
            }
        }
        
        self::$handlers_registered = true;
        
        // Core AJAX handlers
        add_action('wp_ajax_dmm_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_dmm_resend_order', [$this, 'ajax_resend_order']);
        add_action('wp_ajax_dmm_sync_order', [$this, 'ajax_sync_order']);
        add_action('wp_ajax_dmm_refresh_meta_fields', [$this, 'ajax_refresh_meta_fields']);
        add_action('wp_ajax_dmm_check_logs', [$this, 'ajax_check_logs']);
        add_action('wp_ajax_dmm_create_log_table', [$this, 'ajax_create_log_table']);
        add_action('wp_ajax_dmm_bulk_send_orders', [$this, 'ajax_bulk_send_orders']);
        add_action('wp_ajax_dmm_bulk_sync_orders', [$this, 'ajax_bulk_sync_orders']);
        add_action('wp_ajax_dmm_get_bulk_progress', [$this, 'ajax_get_bulk_progress']);
        add_action('wp_ajax_dmm_cancel_bulk_send', [$this, 'ajax_cancel_bulk_send']);
        add_action('wp_ajax_dmm_diagnose_orders', [$this, 'ajax_diagnose_orders']);
        add_action('wp_ajax_dmm_force_resend_all', [$this, 'ajax_force_resend_all']);
        add_action('wp_ajax_dmm_test_force_resend', [$this, 'ajax_test_force_resend']);
        add_action('wp_ajax_dmm_get_monitoring_data', [$this, 'ajax_get_monitoring_data']);
        add_action('wp_ajax_dmm_clear_failed_orders', [$this, 'ajax_clear_failed_orders']);
        
        // ACS Courier AJAX handlers
        add_action('wp_ajax_dmm_acs_track_shipment', [$this, 'ajax_acs_track_shipment']);
        add_action('wp_ajax_dmm_acs_create_voucher', [$this, 'ajax_acs_create_voucher']);
        add_action('wp_ajax_dmm_acs_calculate_price', [$this, 'ajax_acs_calculate_price']);
        add_action('wp_ajax_dmm_acs_validate_address', [$this, 'ajax_acs_validate_address']);
        add_action('wp_ajax_dmm_acs_find_stations', [$this, 'ajax_acs_find_stations']);
        add_action('wp_ajax_dmm_acs_test_connection', [$this, 'ajax_acs_test_connection']);
        add_action('wp_ajax_dmm_acs_sync_shipment', [$this, 'ajax_acs_sync_shipment']);
        
        // Geniki Taxidromiki AJAX handlers
        add_action('wp_ajax_dmm_geniki_test_connection', [$this, 'ajax_geniki_test_connection']);
        add_action('wp_ajax_dmm_geniki_track_shipment', [$this, 'ajax_geniki_track_shipment']);
        add_action('wp_ajax_dmm_geniki_get_shops', [$this, 'ajax_geniki_get_shops']);
        add_action('wp_ajax_dmm_geniki_create_voucher', [$this, 'ajax_geniki_create_voucher']);
        add_action('wp_ajax_dmm_geniki_get_pdf', [$this, 'ajax_geniki_get_pdf']);
        
        // ELTA Hellenic Post AJAX handlers
        add_action('wp_ajax_dmm_elta_test_connection', [$this, 'ajax_elta_test_connection']);
        add_action('wp_ajax_dmm_elta_track_shipment', [$this, 'ajax_elta_track_shipment']);
        add_action('wp_ajax_dmm_elta_create_tracking', [$this, 'ajax_elta_create_tracking']);
        add_action('wp_ajax_dmm_elta_update_tracking', [$this, 'ajax_elta_update_tracking']);
        add_action('wp_ajax_dmm_elta_delete_tracking', [$this, 'ajax_elta_delete_tracking']);
        
        // Log management AJAX handlers
        add_action('wp_ajax_dmm_get_logs_table', [$this, 'ajax_get_logs_table_simple']);
        add_action('wp_ajax_dmm_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_dmm_export_logs', [$this, 'ajax_export_logs']);
        add_action('wp_ajax_dmm_resend_log', [$this, 'ajax_resend_log']);
        add_action('wp_ajax_dmm_get_order_stats', [$this, 'ajax_get_order_stats']);
    }
    
    /**
     * Verify AJAX request with nonce and capabilities
     * Standardized nonce verification for all AJAX handlers
     *
     * @param string $action The nonce action name
     * @param string $capability The required capability: 'manage_options', 'manage_woocommerce', or 'both' (default: 'both')
     */
    private function verify_ajax_request($action, $capability = 'both') {
        // Standardize on check_ajax_referer() for all AJAX handlers
        // Use a generic admin nonce for all admin operations
        check_ajax_referer('dmm_admin_nonce', 'nonce');
        
        // Check capabilities based on parameter
        $has_cap = false;
        
        if ($capability === 'both') {
            // Default: allow either manage_options OR manage_woocommerce
            $has_cap = current_user_can('manage_options') || current_user_can('manage_woocommerce');
        } elseif ($capability === 'manage_woocommerce') {
            $has_cap = current_user_can('manage_woocommerce');
        } else {
            // Default to manage_options
            $has_cap = current_user_can('manage_options');
        }
        
        if (!$has_cap) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dmm-delivery-bridge')], 403);
        }
    }
    
    // Placeholder methods - these need to be implemented by extracting from original file
    // For now, they return a simple error message
    
    public function ajax_test_connection() {
        $this->verify_ajax_request('dmm_test_connection');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_resend_order() {
        $this->verify_ajax_request('dmm_resend_order');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_sync_order() {
        $this->verify_ajax_request('dmm_sync_order');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_refresh_meta_fields() {
        $this->verify_ajax_request('dmm_refresh_meta_fields');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_check_logs() {
        $this->verify_ajax_request('dmm_check_logs');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_create_log_table() {
        $this->verify_ajax_request('dmm_create_log_table');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    /**
     * AJAX handler for bulk send orders
     * Returns job ID for progress tracking
     */
    public function ajax_bulk_send_orders() {
        $this->verify_ajax_request('dmm_bulk_send_orders');
        
        try {
            // Get parameters
            $params = isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : [];
            
            // Get pending orders
            $args = [
                'status' => ['processing', 'completed'],
                'limit' => isset($params['limit']) ? absint($params['limit']) : -1,
                'meta_query' => [
                    [
                        'key' => '_dmm_sent_to_api',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];
            
            $orders = wc_get_orders($args);
            
            if (empty($orders)) {
                wp_send_json_error([
                    'message' => __('No pending orders found to send.', 'dmm-delivery-bridge')
                ]);
            }
            
            // Create job ID for tracking
            $job_id = 'bulk_send_' . time() . '_' . wp_generate_password(8, false);
            
            // Store job data in transient
            set_transient('dmm_bulk_job_' . $job_id, [
                'type' => 'send',
                'total' => count($orders),
                'current' => 0,
                'status' => 'running',
                'started_at' => current_time('mysql'),
                'order_ids' => array_map(function($order) { return $order->get_id(); }, $orders)
            ], HOUR_IN_SECONDS);
            
            // Schedule background processing
            if (function_exists('as_schedule_single_action')) {
                $scheduled = as_schedule_single_action(
                    time(),
                    'dmm_bulk_process_orders',
                    [$job_id, 'send'],
                    'dmm_bulk_operations'
                );
                
                if (!$scheduled) {
                    // Action Scheduler failed, use immediate processing
                    if ($this->plugin && $this->plugin->logger) {
                        $this->plugin->logger->log("Bulk send: Action Scheduler failed to schedule, using immediate processing for job {$job_id}", 'warning');
                    }
                    $this->process_bulk_orders_immediate($job_id, 'send', $orders);
                } else {
                    // Trigger Action Scheduler to run immediately if possible
                    if (function_exists('as_run_queue')) {
                        // Try to process queued actions immediately
                        add_action('shutdown', function() {
                            if (function_exists('as_run_queue')) {
                                as_run_queue();
                            }
                        }, 999);
                    }
                }
            } else {
                // No Action Scheduler, process immediately
                if ($this->plugin && $this->plugin->logger) {
                    $this->plugin->logger->log("Bulk send: Action Scheduler not available, using immediate processing for job {$job_id}", 'info');
                }
                $this->process_bulk_orders_immediate($job_id, 'send', $orders);
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Started processing %d orders.', 'dmm-delivery-bridge'), count($orders)),
                'job_id' => $job_id,
                'total' => count($orders)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to start bulk operation.', 'dmm-delivery-bridge'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * AJAX handler for bulk sync orders
     */
    public function ajax_bulk_sync_orders() {
        $this->verify_ajax_request('dmm_bulk_sync_orders');
        
        try {
            $params = isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : [];
            
            // Get orders that have been sent
            $args = [
                'status' => ['processing', 'completed'],
                'limit' => isset($params['limit']) ? absint($params['limit']) : -1,
                'meta_query' => [
                    [
                        'key' => '_dmm_sent_to_api',
                        'value' => 'yes',
                        'compare' => '='
                    ]
                ]
            ];
            
            $orders = wc_get_orders($args);
            
            if (empty($orders)) {
                wp_send_json_error([
                    'message' => __('No orders found to sync.', 'dmm-delivery-bridge')
                ]);
            }
            
            $job_id = 'bulk_sync_' . time() . '_' . wp_generate_password(8, false);
            
            set_transient('dmm_bulk_job_' . $job_id, [
                'type' => 'sync',
                'total' => count($orders),
                'current' => 0,
                'status' => 'running',
                'started_at' => current_time('mysql'),
                'order_ids' => array_map(function($order) { return $order->get_id(); }, $orders)
            ], HOUR_IN_SECONDS);
            
            if (function_exists('as_schedule_single_action')) {
                $scheduled = as_schedule_single_action(
                    time(),
                    'dmm_bulk_process_orders',
                    [$job_id, 'sync'],
                    'dmm_bulk_operations'
                );
                
                if (!$scheduled) {
                    if ($this->plugin && $this->plugin->logger) {
                        $this->plugin->logger->log("Bulk sync: Action Scheduler failed to schedule, using immediate processing for job {$job_id}", 'warning');
                    }
                    $this->process_bulk_orders_immediate($job_id, 'sync', $orders);
                } else {
                    if (function_exists('as_run_queue')) {
                        add_action('shutdown', function() {
                            if (function_exists('as_run_queue')) {
                                as_run_queue();
                            }
                        }, 999);
                    }
                }
            } else {
                if ($this->plugin && $this->plugin->logger) {
                    $this->plugin->logger->log("Bulk sync: Action Scheduler not available, using immediate processing for job {$job_id}", 'info');
                }
                $this->process_bulk_orders_immediate($job_id, 'sync', $orders);
            }
            
            wp_send_json_success([
                'message' => sprintf(__('Started syncing %d orders.', 'dmm-delivery-bridge'), count($orders)),
                'job_id' => $job_id,
                'total' => count($orders)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to start bulk sync.', 'dmm-delivery-bridge'),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * AJAX handler to get bulk operation progress
     */
    public function ajax_get_bulk_progress() {
        $this->verify_ajax_request('dmm_get_bulk_progress');
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (empty($job_id)) {
            wp_send_json_error([
                'message' => __('Job ID is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        
        if (!$job_data) {
            wp_send_json_error([
                'message' => __('Job not found or expired.', 'dmm-delivery-bridge'),
                'status' => 'not_found'
            ]);
        }
        
        $label = '';
        if ($job_data['type'] === 'send') {
            $label = __('Sending orders...', 'dmm-delivery-bridge');
        } elseif ($job_data['type'] === 'sync') {
            $label = __('Syncing orders...', 'dmm-delivery-bridge');
        } elseif ($job_data['type'] === 'resend') {
            $label = __('Resending orders...', 'dmm-delivery-bridge');
        }
        
        wp_send_json_success([
            'current' => $job_data['current'],
            'total' => $job_data['total'],
            'status' => $job_data['status'],
            'label' => $label,
            'error' => isset($job_data['error']) ? $job_data['error'] : null
        ]);
    }
    
    /**
     * AJAX handler to cancel bulk operation
     */
    public function ajax_cancel_bulk_send() {
        $this->verify_ajax_request('dmm_cancel_bulk_send');
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (!empty($job_id)) {
            $job_data = get_transient('dmm_bulk_job_' . $job_id);
            if ($job_data) {
                $job_data['status'] = 'cancelled';
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            }
        }
        
        wp_send_json_success([
            'message' => __('Bulk operation cancelled.', 'dmm-delivery-bridge')
        ]);
    }
    
    /**
     * Process bulk orders immediately (fallback when Action Scheduler not available)
     * 
     * @param string $job_id Job ID
     * @param string $type Operation type (send, sync, resend)
     * @param array $orders Array of WC_Order objects
     */
    private function process_bulk_orders_immediate($job_id, $type, $orders) {
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        if (!$job_data) {
            return;
        }
        
        $processed = 0;
        $batch_size = 5; // Process 5 at a time to avoid timeouts
        
        foreach (array_chunk($orders, $batch_size) as $batch) {
            foreach ($batch as $order) {
                try {
                    if ($type === 'send' || $type === 'resend') {
                        if ($this->plugin && $this->plugin->order_processor) {
                            // Process order directly using robust method
                            $this->plugin->order_processor->process_order_robust($order);
                        }
                    } elseif ($type === 'sync') {
                        // Sync order status
                        if ($this->plugin && $order->get_meta('_dmm_delivery_sent') === 'yes') {
                            $this->plugin->process_order_update(['order_id' => $order->get_id()]);
                        }
                    }
                    
                    $processed++;
                    $job_data['current'] = $processed;
                    set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                } catch (Exception $e) {
                    // Log error but continue
                    if ($this->plugin && $this->plugin->logger) {
                        $this->plugin->logger->log('Bulk operation error for order ' . $order->get_id() . ': ' . $e->getMessage(), 'error');
                    }
                }
            }
            
            // Small delay to prevent timeout
            usleep(200000); // 0.2 second delay between batches
        }
        
        $job_data['status'] = 'completed';
        set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
    }
    
    public function ajax_diagnose_orders() {
        $this->verify_ajax_request('dmm_diagnose_orders');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_force_resend_all() {
        $this->verify_ajax_request('dmm_force_resend_all');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_test_force_resend() {
        $this->verify_ajax_request('dmm_test_force_resend');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_get_monitoring_data() {
        $this->verify_ajax_request('dmm_monitoring');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_clear_failed_orders() {
        $this->verify_ajax_request('dmm_monitoring');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    // ACS Courier methods
    public function ajax_acs_track_shipment() {
        $this->verify_ajax_request('dmm_acs_track');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_create_voucher() {
        $this->verify_ajax_request('dmm_acs_create');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_calculate_price() {
        $this->verify_ajax_request('dmm_acs_calculate');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_validate_address() {
        $this->verify_ajax_request('dmm_acs_validate');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_find_stations() {
        $this->verify_ajax_request('dmm_acs_find_stations');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_test_connection() {
        $this->verify_ajax_request('dmm_acs_test');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_sync_shipment() {
        $this->verify_ajax_request('dmm_acs_sync');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    // Geniki methods
    public function ajax_geniki_test_connection() {
        $this->verify_ajax_request('dmm_geniki_test');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_track_shipment() {
        $this->verify_ajax_request('dmm_geniki_track');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_get_shops() {
        $this->verify_ajax_request('dmm_geniki_shops');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_create_voucher() {
        $this->verify_ajax_request('dmm_geniki_create');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_get_pdf() {
        $this->verify_ajax_request('dmm_geniki_pdf');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    // ELTA methods
    public function ajax_elta_test_connection() {
        $this->verify_ajax_request('dmm_elta_test');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_track_shipment() {
        $this->verify_ajax_request('dmm_elta_track');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_create_tracking() {
        $this->verify_ajax_request('dmm_elta_create');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_update_tracking() {
        $this->verify_ajax_request('dmm_elta_update');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_delete_tracking() {
        $this->verify_ajax_request('dmm_elta_delete');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    // Log management methods
    public function ajax_get_logs_table_simple() {
        // Use standardized verification (allows both manage_options and manage_woocommerce)
        $this->verify_ajax_request('dmm_get_logs_table', 'both');
        
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_clear_logs() {
        $this->verify_ajax_request('dmm_clear_logs');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_export_logs() {
        $this->verify_ajax_request('dmm_export_logs');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_resend_log() {
        $this->verify_ajax_request('dmm_resend_log');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_get_order_stats() {
        $this->verify_ajax_request('dmm_get_order_stats');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
}


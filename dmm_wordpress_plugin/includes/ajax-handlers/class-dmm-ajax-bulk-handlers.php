<?php
/**
 * Bulk Operations AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Bulk_Handlers
 * Handles bulk operations: bulk send, bulk sync, progress tracking
 */
class DMM_AJAX_Bulk_Handlers {
    use DMM_AJAX_Verification;
    
    /**
     * Plugin instance
     *
     * @var DMM_Delivery_Bridge
     */
    private $plugin;
    
    /**
     * Constructor
     *
     * @param DMM_Delivery_Bridge $plugin Main plugin instance
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }
    
    /**
     * Register bulk operation AJAX handlers
     */
    public function register_handlers() {
        add_action('wp_ajax_dmm_bulk_send_orders', [$this, 'ajax_bulk_send_orders']);
        add_action('wp_ajax_dmm_bulk_sync_orders', [$this, 'ajax_bulk_sync_orders']);
        add_action('wp_ajax_dmm_get_bulk_progress', [$this, 'ajax_get_bulk_progress']);
        add_action('wp_ajax_dmm_cancel_bulk_send', [$this, 'ajax_cancel_bulk_send']);
        add_action('wp_ajax_dmm_process_bulk_batch', [$this, 'ajax_process_bulk_batch']);
    }
    
    /**
     * AJAX handler for bulk send orders
     * Returns job ID for progress tracking
     */
    public function ajax_bulk_send_orders() {
        $this->verify_ajax_request('dmm_bulk_send_orders');
        
        try {
            // Check circuit breaker status before starting bulk send
            $api_client = $this->plugin ? $this->plugin->api_client : null;
            if ($api_client && method_exists($api_client, 'get_circuit_breaker_status')) {
                $circuit_breaker_status = $api_client->get_circuit_breaker_status();
                if ($circuit_breaker_status['is_open']) {
                    $time_remaining = $circuit_breaker_status['time_remaining'];
                    $minutes = floor($time_remaining / 60);
                    $seconds = $time_remaining % 60;
                    $time_str = $minutes > 0 
                        ? sprintf(__('%d minute(s) and %d second(s)', 'dmm-delivery-bridge'), $minutes, $seconds)
                        : sprintf(__('%d second(s)', 'dmm-delivery-bridge'), $seconds);
                    
                    $monitoring_url = admin_url('admin.php?page=dmm-delivery-bridge-monitoring');
                    $reset_url = add_query_arg(['action' => 'reset_circuit_breaker'], $monitoring_url);
                    
                    wp_send_json_error([
                        'message' => sprintf(
                            __('⚠️ Cannot start bulk send: API calls are temporarily disabled due to high error rate. The circuit breaker will auto-reset in %s. Please check the <a href="%s" target="_blank">Monitoring page</a> to analyze errors and resolve the issue before retrying.', 'dmm-delivery-bridge'),
                            $time_str,
                            esc_url($monitoring_url)
                        ),
                        'circuit_breaker_open' => true,
                        'time_remaining' => $time_remaining,
                        'monitoring_url' => $monitoring_url
                    ]);
                }
            }
            
            // Get parameters
            $params = isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : [];
            
            // Get pending orders - exclude refunds
            $args = [
                'status' => ['processing', 'completed'],
                'limit' => isset($params['limit']) ? absint($params['limit']) : -1,
                'type' => 'shop_order', // Exclude refunds and other order types
                'meta_query' => [
                    [
                        'key' => '_dmm_sent_to_api',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ];
            
            $orders = wc_get_orders($args);
            
            // Filter out any refunds that might slip through
            $orders = array_filter($orders, function($order) {
                return !($order instanceof \WC_Order_Refund);
            });
            
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
            
            // Always process first batch immediately, then schedule rest
            // This ensures progress starts immediately
            $first_batch = array_slice($orders, 0, 5);
            if (!empty($first_batch)) {
                $this->process_bulk_orders_batch($job_id, 'send', $first_batch, 0);
            }
            
            // Schedule remaining orders
            if (function_exists('as_schedule_single_action')) {
                $scheduled = as_schedule_single_action(
                    time() + 2, // Start 2 seconds after first batch
                    'dmm_bulk_process_orders',
                    [$job_id, 'send'],
                    'dmm_bulk_operations'
                );
                
                if (!$scheduled) {
                    // Action Scheduler failed, continue with immediate processing in background
                    if ($this->plugin && $this->plugin->logger) {
                        $this->plugin->logger->log("Bulk send: Action Scheduler failed to schedule, will process remaining orders via AJAX polling for job {$job_id}", 'warning');
                    }
                    // Mark job to use immediate processing
                    $job_data = get_transient('dmm_bulk_job_' . $job_id);
                    $job_data['use_immediate'] = true;
                    set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                } else {
                    // Trigger Action Scheduler to run immediately if possible
                    if (function_exists('as_run_queue')) {
                        add_action('shutdown', function() {
                            if (function_exists('as_run_queue')) {
                                as_run_queue();
                            }
                        }, 999);
                    }
                }
            } else {
                // No Action Scheduler, mark for immediate processing
                if ($this->plugin && $this->plugin->logger) {
                    $this->plugin->logger->log("Bulk send: Action Scheduler not available, will process via AJAX polling for job {$job_id}", 'info');
                }
                $job_data = get_transient('dmm_bulk_job_' . $job_id);
                $job_data['use_immediate'] = true;
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
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
            // Check circuit breaker status before starting bulk sync
            $api_client = $this->plugin ? $this->plugin->api_client : null;
            if ($api_client && method_exists($api_client, 'get_circuit_breaker_status')) {
                $circuit_breaker_status = $api_client->get_circuit_breaker_status();
                if ($circuit_breaker_status['is_open']) {
                    $time_remaining = $circuit_breaker_status['time_remaining'];
                    $minutes = floor($time_remaining / 60);
                    $seconds = $time_remaining % 60;
                    $time_str = $minutes > 0 
                        ? sprintf(__('%d minute(s) and %d second(s)', 'dmm-delivery-bridge'), $minutes, $seconds)
                        : sprintf(__('%d second(s)', 'dmm-delivery-bridge'), $seconds);
                    
                    $monitoring_url = admin_url('admin.php?page=dmm-delivery-bridge-monitoring');
                    
                    wp_send_json_error([
                        'message' => sprintf(
                            __('⚠️ Cannot start bulk sync: API calls are temporarily disabled due to high error rate. The circuit breaker will auto-reset in %s. Please check the <a href="%s" target="_blank">Monitoring page</a> to analyze errors and resolve the issue before retrying.', 'dmm-delivery-bridge'),
                            $time_str,
                            esc_url($monitoring_url)
                        ),
                        'circuit_breaker_open' => true,
                        'time_remaining' => $time_remaining,
                        'monitoring_url' => $monitoring_url
                    ]);
                }
            }
            
            $params = isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : [];
            
            // Get orders that have been sent - exclude refunds
            $args = [
                'status' => ['processing', 'completed'],
                'limit' => isset($params['limit']) ? absint($params['limit']) : -1,
                'type' => 'shop_order', // Exclude refunds
                'meta_query' => [
                    [
                        'key' => '_dmm_sent_to_api',
                        'value' => 'yes',
                        'compare' => '='
                    ]
                ]
            ];
            
            $orders = wc_get_orders($args);
            
            // Filter out any refunds that might slip through
            $orders = array_filter($orders, function($order) {
                return !($order instanceof \WC_Order_Refund);
            });
            
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
            
            // Always process first batch immediately, then schedule rest
            $first_batch = array_slice($orders, 0, 5);
            if (!empty($first_batch)) {
                $this->process_bulk_orders_batch($job_id, 'sync', $first_batch, 0);
            }
            
            // Schedule remaining orders
            if (function_exists('as_schedule_single_action')) {
                $scheduled = as_schedule_single_action(
                    time() + 2,
                    'dmm_bulk_process_orders',
                    [$job_id, 'sync'],
                    'dmm_bulk_operations'
                );
                
                if (!$scheduled) {
                    if ($this->plugin && $this->plugin->logger) {
                        $this->plugin->logger->log("Bulk sync: Action Scheduler failed to schedule, will process via AJAX polling for job {$job_id}", 'warning');
                    }
                    $job_data = get_transient('dmm_bulk_job_' . $job_id);
                    $job_data['use_immediate'] = true;
                    set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
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
                    $this->plugin->logger->log("Bulk sync: Action Scheduler not available, will process via AJAX polling for job {$job_id}", 'info');
                }
                $job_data = get_transient('dmm_bulk_job_' . $job_id);
                $job_data['use_immediate'] = true;
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
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
        
        $use_immediate = isset($job_data['use_immediate']) && $job_data['use_immediate'];
        
        wp_send_json_success([
            'current' => $job_data['current'],
            'total' => $job_data['total'],
            'status' => $job_data['status'],
            'label' => $label,
            'error' => isset($job_data['error']) ? $job_data['error'] : null,
            'use_immediate' => $use_immediate
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
     * AJAX handler to process next batch (for immediate processing mode)
     */
    public function ajax_process_bulk_batch() {
        $this->verify_ajax_request('dmm_process_bulk_batch');
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'send';
        
        if (empty($job_id)) {
            wp_send_json_error(['message' => __('Job ID is required.', 'dmm-delivery-bridge')]);
        }
        
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        if (!$job_data || $job_data['status'] !== 'running') {
            wp_send_json_error(['message' => __('Job not found or not running.', 'dmm-delivery-bridge')]);
        }
        
        $order_ids = isset($job_data['order_ids']) ? $job_data['order_ids'] : [];
        $current = isset($job_data['current']) ? intval($job_data['current']) : 0;
        $total = isset($job_data['total']) ? intval($job_data['total']) : 0;
        $batch_size = 5;
        
        // Get next batch
        $remaining_ids = array_slice($order_ids, $current, $batch_size);
        
        if (empty($remaining_ids)) {
            // All done
            $job_data['status'] = 'completed';
            $job_data['current'] = $total;
            set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            wp_send_json_success([
                'current' => $total,
                'total' => $total,
                'status' => 'completed'
            ]);
        }
        
        // Get orders - filter out refunds
        $orders = [];
        foreach ($remaining_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && !($order instanceof \WC_Order_Refund)) {
                $orders[] = $order;
            }
        }
        
        // Process batch
        $this->process_bulk_orders_batch($job_id, $type, $orders, $current);
        
        // Update job data
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        $new_current = isset($job_data['current']) ? intval($job_data['current']) : $current + count($orders);
        
        wp_send_json_success([
            'current' => $new_current,
            'total' => $total,
            'status' => $new_current >= $total ? 'completed' : 'running',
            'has_more' => $new_current < $total
        ]);
    }
    
    /**
     * Process a batch of orders and update progress
     * 
     * @param string $job_id Job ID
     * @param string $type Operation type (send, sync, resend)
     * @param array $orders Array of WC_Order objects
     * @param int $start_index Starting index for progress tracking
     */
    private function process_bulk_orders_batch($job_id, $type, $orders, $start_index) {
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        if (!$job_data) {
            return;
        }
        
        $current = $start_index;
        
        foreach ($orders as $order) {
            try {
                if ($type === 'send' || $type === 'resend') {
                    if ($this->plugin && $this->plugin->order_processor) {
                        // Skip refunds
                        if ($order instanceof \WC_Order_Refund) {
                            $current++;
                            $job_data['current'] = $current;
                            set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                            continue;
                        }
                        // Process order directly using robust method
                        $result = $this->plugin->order_processor->process_order_robust($order);
                        
                        // Check if rate limited and queue for later retry
                        if ($result && isset($result['rate_limited']) && $result['rate_limited'] === true) {
                            $wait_seconds = isset($result['wait_seconds']) ? (int) $result['wait_seconds'] : 60;
                            // Use rate-limited handler if available
                            if ($this->plugin && $this->plugin->ajax_handlers && isset($this->plugin->ajax_handlers->rate_limited_handler)) {
                                $this->plugin->ajax_handlers->rate_limited_handler->queue_rate_limited_order($order->get_id(), $wait_seconds);
                            }
                            
                            if ($this->plugin && $this->plugin->logger) {
                                $this->plugin->logger->log(sprintf(
                                    'Order %d rate limited during bulk send. Queued for retry after %d seconds.',
                                    $order->get_id(),
                                    $wait_seconds
                                ), 'warning');
                            }
                        }
                    }
                } elseif ($type === 'sync') {
                    // Sync order status
                    if ($this->plugin && $order->get_meta('_dmm_delivery_sent') === 'yes') {
                        $this->plugin->process_order_update(['order_id' => $order->get_id()]);
                    }
                }
                
                $current++;
                $job_data['current'] = $current;
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            } catch (Exception $e) {
                // Log error but continue
                if ($this->plugin && $this->plugin->logger) {
                    $this->plugin->logger->log('Bulk operation error for order ' . $order->get_id() . ': ' . $e->getMessage(), 'error');
                }
                $current++;
                $job_data['current'] = $current;
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            }
        }
    }
}


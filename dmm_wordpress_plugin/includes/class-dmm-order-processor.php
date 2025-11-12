<?php
/**
 * Order Processor class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 * 
 * This class acts as a facade/composer that delegates to specialized handler classes.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load handler classes
require_once plugin_dir_path(__FILE__) . 'order-processor/class-dmm-order-queue-handler.php';
require_once plugin_dir_path(__FILE__) . 'order-processor/class-dmm-order-data-preparer.php';
require_once plugin_dir_path(__FILE__) . 'order-processor/class-dmm-order-result-handler.php';
require_once plugin_dir_path(__FILE__) . 'order-processor/class-dmm-voucher-status-checker.php';
require_once plugin_dir_path(__FILE__) . 'order-processor/class-dmm-shipment-status-updater.php';

/**
 * Class DMM_Order_Processor
 * Main order processor class that composes and delegates to specialized handlers
 */
class DMM_Order_Processor {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * API Client instance
     *
     * @var DMM_API_Client
     */
    private $api_client;
    
    /**
     * Logger instance
     *
     * @var DMM_Logger
     */
    private $logger;
    
    /**
     * Scheduler instance
     *
     * @var DMM_Scheduler
     */
    private $scheduler;
    
    /**
     * Order queue handler instance
     *
     * @var DMM_Order_Queue_Handler
     */
    private $queue_handler;
    
    /**
     * Order data preparer instance
     *
     * @var DMM_Order_Data_Preparer
     */
    private $data_preparer;
    
    /**
     * Order result handler instance
     *
     * @var DMM_Order_Result_Handler
     */
    private $result_handler;
    
    /**
     * Voucher status checker instance
     *
     * @var DMM_Voucher_Status_Checker
     */
    private $voucher_status_checker;
    
    /**
     * Shipment status updater instance
     *
     * @var DMM_Shipment_Status_Updater
     */
    private $shipment_status_updater;
    
    /**
     * Constructor
     *
     * @param array         $options Plugin options
     * @param DMM_API_Client $api_client API client instance
     * @param DMM_Logger   $logger Logger instance
     * @param DMM_Scheduler $scheduler Scheduler instance
     */
    public function __construct($options = [], $api_client = null, $logger = null, $scheduler = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->logger = $logger ?: new DMM_Logger($this->options);
        $this->api_client = $api_client ?: new DMM_API_Client($this->options, $this->logger);
        $this->scheduler = $scheduler ?: new DMM_Scheduler($this->options, $this->logger);
        
        // Initialize handler instances
        $this->queue_handler = new DMM_Order_Queue_Handler($this->options, $this->logger, $this->scheduler);
        $this->data_preparer = new DMM_Order_Data_Preparer($this->options, $this->logger);
        $this->shipment_status_updater = new DMM_Shipment_Status_Updater($this->options, $this->logger);
        $this->voucher_status_checker = new DMM_Voucher_Status_Checker(
            $this->options,
            $this->logger,
            $this->data_preparer,
            $this->shipment_status_updater
        );
        $this->result_handler = new DMM_Order_Result_Handler(
            $this->logger,
            $this->api_client,
            $this->scheduler,
            $this->voucher_status_checker
        );
    }
    
    /**
     * Queue order for asynchronous sending to API
     * Delegates to queue handler
     *
     * @param int $order_id WooCommerce order ID
     * @return void
     * @since 1.0.0
     */
    public function queue_send_to_api($order_id) {
        $this->queue_handler->queue_send_to_api($order_id);
    }
    
    /**
     * Handle WooCommerce order status change events
     * Delegates to queue handler
     *
     * @param int    $order_id WooCommerce order ID
     * @param string $old_status Previous order status
     * @param string $new_status New order status
     * @param \WC_Order $order Order object (provided by WooCommerce hook)
     * @return void
     * @since 1.0.0
     */
    public function maybe_queue_send_on_status($order_id, $old_status, $new_status, $order) {
        $this->queue_handler->maybe_queue_send_on_status($order_id, $old_status, $new_status, $order);
    }
    
    /**
     * Process order asynchronously via Action Scheduler
     * Delegates to queue handler
     *
     * @param array $args Action arguments containing 'order_id' key
     * @return void
     * @since 1.0.0
     */
    public function process_order_async($args) {
        $this->queue_handler->process_order_async($args, [$this, 'process_order_robust']);
    }
    
    /**
     * Robust order processing with retry logic and idempotency
     * 
     * This is the core order processing method. It implements:
     * - Idempotency: Uses deterministic idempotency keys to prevent duplicate sends
     * - Retry logic: Automatically retries failed requests with exponential backoff
     * - Error handling: Comprehensive error logging and structured logging
     * - State management: Updates order meta to track processing state
     * 
     * Processing flow:
     * 1. Check if order already sent (idempotency check)
     * 2. Generate deterministic idempotency key (stable across retries)
     * 3. Check retry count (max 5 retries by default)
     * 4. Prepare order data for API
     * 5. Send to API with retry logic
     * 6. Handle response (success or failure)
     * 7. Update order meta and add order notes
     * 8. Schedule retry if needed (for retryable errors)
     * 
     * The idempotency key ensures that if the same order is processed multiple times
     * (e.g., due to retries or duplicate hooks), the API will recognize it as a duplicate
     * and return the existing order data instead of creating a duplicate.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return array|null Response array with 'success', 'rate_limited', 'wait_seconds' (if rate limited), or null if already sent/max retries
     * @since 1.0.0
     */
    public function process_order_robust($order) {
        $order_id = $order->get_id();
        
        // Check if already sent successfully
        if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
            $this->logger->debug_log('Order ' . $order_id . ' already sent, skipping');
            return null;
        }
        
        // Generate idempotency key
        $idempotency_key = $this->generate_idempotency_key($order);
        
        // Check retry count
        $retry_count = (int) $order->get_meta('_dmm_delivery_retry_count');
        $max_retries = 5;
        
        if ($retry_count >= $max_retries) {
            $this->result_handler->handle_max_retries_reached($order);
            return null;
        }
        
        $order_data = null;
        $response = null;
        
        try {
            // Prepare order data with idempotency key
            $order_data = $this->data_preparer->prepare_order_data($order);
            $order_data['idempotency_key'] = $idempotency_key;
            
            // Add structured logging
            $this->logger->log_structured('order_processing_start', [
                'order_id' => $order_id,
                'retry_count' => $retry_count,
                'idempotency_key' => $idempotency_key
            ]);
            
            // Send to API with retry logic
            $response = $this->api_client->send_to_api_with_retry($order_data, $retry_count);
            
            // Debug API response using logger's structured debug method
            $this->logger->debug_data('API Response', $response);
            
        } catch (Exception $e) {
            // Handle any exceptions during processing
            $this->logger->log_structured('order_processing_exception', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
                'retry_count' => $retry_count
            ]);
            
            $response = [
                'success' => false,
                'message' => 'Exception during processing: ' . $e->getMessage(),
                'data' => null
            ];
            
            // If order_data wasn't prepared due to exception, create minimal data for logging
            if (!$order_data) {
                $order_data = [
                    'source' => 'woocommerce',
                    'order' => [
                        'external_order_id' => (string) $order_id,
                        'order_number' => $order->get_order_number(),
                        'status' => $order->get_status()
                    ]
                ];
            }
        }
        
        // Always log the result, even if there was an exception
        if ($response) {
            // Use order_data if available, otherwise create minimal data
            $data_to_log = $order_data ?: [
                'source' => 'woocommerce',
                'order' => [
                    'external_order_id' => (string) $order_id,
                    'order_number' => $order->get_order_number(),
                    'status' => $order->get_status()
                ]
            ];
            $this->logger->log_request($order_id, $data_to_log, $response);
        }
        
        // Handle response
        if ($response && $response['success']) {
            $this->result_handler->handle_successful_send($order, $response);
            
            // Invalidate cache for this order
                $this->invalidate_order_cache($order_id);
            
            // Return success response
            return [
                'success' => true,
                'rate_limited' => false
            ];
        } else {
            // Check if rate limited
            $is_rate_limited = isset($response['rate_limited']) && $response['rate_limited'] === true;
            $wait_seconds = isset($response['wait_seconds']) ? (int) $response['wait_seconds'] : (isset($response['retry_after']) ? (int) $response['retry_after'] : 0);
            
            $this->result_handler->handle_failed_send($order, $response, $retry_count);
            
            // Return failure response with rate limit info
            return [
                'success' => false,
                'rate_limited' => $is_rate_limited,
                'wait_seconds' => $wait_seconds,
                'message' => $response['message'] ?? __('Unknown error', 'dmm-delivery-bridge')
            ];
        }
    }
    
    /**
     * Prepare WooCommerce order data for DMM Delivery API
     * Delegates to data preparer
     *
     * @param \WC_Order $order WooCommerce order object
     * @return array Order data array ready for API submission
     * @since 1.0.0
     */
    public function prepare_order_data($order) {
        return $this->data_preparer->prepare_order_data($order);
    }
    
    /**
     * Invalidate order cache
     *
     * @param int $order_id Order ID
     * @return void
     */
    private function invalidate_order_cache($order_id) {
        $cache_service = new DMM_Cache_Service($this->options, $this->logger);
        $cache_service->invalidate_order_cache($order_id);
    }
    
    /**
     * Generate deterministic idempotency key (stable across retries)
     * 
     * Creates a stable idempotency key that doesn't change across retry attempts.
     * This ensures the API can properly detect duplicate requests.
     * 
     * The key is generated using:
     * - Site URL (ensures uniqueness across WordPress installations)
     * - Order ID (unique per order)
     * - Order creation timestamp (ensures different orders have different keys)
     * - API secret (HMAC secret for security)
     * 
     * Algorithm: HMAC-SHA256 of "site_url|order_id|order_timestamp"
     * 
     * This key is stable because it only depends on order properties that don't change,
     * not on processing time or retry count.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return string 64-character hexadecimal idempotency key
     * @since 1.0.0
     */
    private function generate_idempotency_key($order) {
        $secret = isset($this->options['api_secret']) ? $this->options['api_secret'] : 'default_secret';
        $site_url = get_site_url();
        $order_id = $order->get_id();
        $order_timestamp = $order->get_date_created()->getTimestamp();
        
        // Stable key that doesn't change across retries
        $key_data = $site_url . '|' . $order_id . '|' . $order_timestamp;
        return hash_hmac('sha256', $key_data, $secret);
    }
}

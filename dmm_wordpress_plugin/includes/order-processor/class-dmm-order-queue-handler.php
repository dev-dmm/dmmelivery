<?php
/**
 * Order Queue Handler for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Order_Queue_Handler
 * Handles order queueing for asynchronous processing
 */
class DMM_Order_Queue_Handler {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
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
     * Constructor
     *
     * @param array         $options Plugin options
     * @param DMM_Logger    $logger Logger instance
     * @param DMM_Scheduler $scheduler Scheduler instance
     */
    public function __construct($options, $logger, $scheduler) {
        $this->options = $options;
        $this->logger = $logger;
        $this->scheduler = $scheduler;
    }
    
    /**
     * Queue order for asynchronous sending to API
     * 
     * This method is called by WooCommerce hooks when an order status changes.
     * It performs validation checks and queues the order for processing via Action Scheduler.
     * 
     * Validation checks:
     * - Auto-send must be enabled in settings
     * - Order status must be in the configured trigger statuses
     * - Order must not already be sent successfully
     * - No duplicate processing (transient lock check)
     * 
     * Uses a transient lock to prevent race conditions when multiple hooks fire
     * for the same order simultaneously.
     * 
     * CRITICAL: This method always returns early to prevent blocking WooCommerce order processing.
     * All errors are caught and logged, never thrown.
     *
     * @param int $order_id WooCommerce order ID
     * @return void
     * @since 1.0.0
     */
    public function queue_send_to_api($order_id) {
        // CRITICAL: Always return early to prevent blocking WooCommerce
        try {
            // Check if auto send is enabled
            if (isset($this->options['auto_send']) && $this->options['auto_send'] !== 'yes') {
                return;
            }
            
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Check if this status should trigger sending
            $order_statuses = isset($this->options['order_statuses']) ? $this->options['order_statuses'] : ['processing', 'completed'];
            if (!in_array($order->get_status(), $order_statuses)) {
                return;
            }
            
            // Check if already sent successfully
            if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
                return;
            }
            
            // Check for race condition with transient lock
            $lock_key = 'dmm_sending_' . $order_id;
            if (get_transient($lock_key)) {
                return; // Already being processed
            }
            
            // Set lock with longer TTL for queue backup scenarios (10 minutes)
            set_transient($lock_key, true, 600);
            
            // Queue immediate job using scheduler
            $this->scheduler->queue_immediate(
                'dmm_send_order',
                ['order_id' => $order_id],
                DMM_Scheduler::GROUP_IMMEDIATE,
                DMM_Scheduler::PRIORITY_NORMAL
            );
            
            $this->logger->debug_log("Queued order {$order_id} for sending to API");
            
        } catch (Exception $e) {
            // CRITICAL: Log error but NEVER let it bubble up to WooCommerce
            $this->logger->debug_log('Error in queue_send_to_api: ' . $e->getMessage());
            // Always return to prevent blocking order completion
            return;
        }
    }
    
    /**
     * Handle WooCommerce order status change events
     * 
     * Called when an order status changes. Performs the same validation as queue_send_to_api()
     * but with additional context about the status transition. This allows for more granular
     * control over when orders are sent (e.g., only on specific status transitions).
     * 
     * This method is safer than queue_send_to_api() because it receives the order object
     * directly, avoiding an extra database query.
     * 
     * CRITICAL: This method always returns early to prevent blocking WooCommerce order processing.
     * All errors are caught and logged, never thrown.
     *
     * @param int    $order_id WooCommerce order ID
     * @param string $old_status Previous order status
     * @param string $new_status New order status
     * @param \WC_Order $order Order object (provided by WooCommerce hook)
     * @return void
     * @since 1.0.0
     */
    public function maybe_queue_send_on_status($order_id, $old_status, $new_status, $order) {
        // CRITICAL: Always return early to prevent blocking WooCommerce
        try {
            // Check if auto send is enabled
            if (isset($this->options['auto_send']) && $this->options['auto_send'] !== 'yes') {
                return;
            }
            
            // Check if this status should trigger sending
            $order_statuses = isset($this->options['order_statuses']) ? $this->options['order_statuses'] : ['processing', 'completed'];
            if (!in_array($new_status, $order_statuses)) {
                return;
            }
            
            // Check if already sent successfully
            if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
                return;
            }
            
            // Check for race condition with transient lock
            $lock_key = 'dmm_sending_' . $order_id;
            if (get_transient($lock_key)) {
                return; // Already being processed
            }
            
            // Set lock with longer TTL for queue backup scenarios (10 minutes)
            set_transient($lock_key, true, 600);
            
            // Queue immediate job using scheduler
            $this->scheduler->queue_immediate(
                'dmm_send_order',
                ['order_id' => $order_id],
                DMM_Scheduler::GROUP_IMMEDIATE,
                DMM_Scheduler::PRIORITY_NORMAL
            );
            
            $this->logger->debug_log("Queued order {$order_id} for sending to API (status change: {$old_status} -> {$new_status})");
            
        } catch (Exception $e) {
            // CRITICAL: Log error but NEVER let it bubble up to WooCommerce
            $this->logger->debug_log('Error in maybe_queue_send_on_status: ' . $e->getMessage());
            // Always return to prevent blocking order completion
            return;
        }
    }
    
    /**
     * Process order asynchronously via Action Scheduler
     * 
     * This is the main processing method called by Action Scheduler. It:
     * - Validates the order exists
     * - Clears the processing lock
     * - Delegates to process_order_robust() for actual processing
     * 
     * This method is designed to be called by Action Scheduler, not directly.
     *
     * @param array $args Action arguments containing 'order_id' key
     * @param callable $process_callback Callback to process the order (process_order_robust)
     * @return void
     * @since 1.0.0
     */
    public function process_order_async($args, $process_callback) {
        $order_id = isset($args['order_id']) ? $args['order_id'] : 0;
        
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Clear the lock
        delete_transient('dmm_sending_' . $order_id);
        
        // Process the order via callback
        if (is_callable($process_callback)) {
            call_user_func($process_callback, $order);
        }
    }
}


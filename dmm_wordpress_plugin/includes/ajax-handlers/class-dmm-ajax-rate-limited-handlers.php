<?php
/**
 * Rate-Limited Orders AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Rate_Limited_Handlers
 * Handles rate-limited orders queue management
 */
class DMM_AJAX_Rate_Limited_Handlers {
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
     * Register rate-limited orders AJAX handlers
     */
    public function register_handlers() {
        add_action('wp_ajax_dmm_get_rate_limited_orders', [$this, 'ajax_get_rate_limited_orders']);
        add_action('wp_ajax_dmm_retry_rate_limited_order', [$this, 'ajax_retry_rate_limited_order']);
        add_action('wp_ajax_dmm_retry_all_rate_limited_orders', [$this, 'ajax_retry_all_rate_limited_orders']);
    }
    
    /**
     * Queue a rate-limited order for later retry
     * 
     * @param int $order_id Order ID
     * @param int $wait_seconds Seconds to wait before retry
     */
    public function queue_rate_limited_order($order_id, $wait_seconds) {
        // Get existing queue
        $queue = get_transient('dmm_rate_limited_orders');
        if (!is_array($queue)) {
            $queue = [];
        }
        
        // Calculate retry timestamp (add buffer of 5 seconds)
        $retry_at = time() + $wait_seconds + 5;
        
        // Add or update order in queue
        $queue[$order_id] = [
            'order_id' => $order_id,
            'retry_at' => $retry_at,
            'wait_seconds' => $wait_seconds,
            'queued_at' => time(),
            'attempts' => isset($queue[$order_id]['attempts']) ? $queue[$order_id]['attempts'] + 1 : 1
        ];
        
        // Save queue (expires in 24 hours, but we'll clean it up when processing)
        set_transient('dmm_rate_limited_orders', $queue, DAY_IN_SECONDS);
        
        // Schedule retry if Action Scheduler is available
        if (function_exists('as_schedule_single_action')) {
            // Schedule retry after wait time (with buffer)
            as_schedule_single_action(
                $retry_at,
                'dmm_retry_rate_limited_order',
                ['order_id' => $order_id],
                'dmm_rate_limited_retries'
            );
        }
    }
    
    /**
     * Retry a rate-limited order
     * Called by Action Scheduler or manually
     * 
     * @param int $order_id Order ID
     */
    public function retry_rate_limited_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if order was already sent
        if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
            // Remove from queue
            $this->remove_from_rate_limited_queue($order_id);
            return;
        }
        
        // Process the order
        if ($this->plugin && $this->plugin->order_processor) {
            $result = $this->plugin->order_processor->process_order_robust($order);
            
            // If still rate limited, re-queue with longer wait
            if ($result && isset($result['rate_limited']) && $result['rate_limited'] === true) {
                $wait_seconds = isset($result['wait_seconds']) ? (int) $result['wait_seconds'] : 60;
                $this->queue_rate_limited_order($order_id, $wait_seconds);
            } else {
                // Success or non-rate-limit error - remove from queue
                $this->remove_from_rate_limited_queue($order_id);
            }
        }
    }
    
    /**
     * Remove order from rate-limited queue
     * 
     * @param int $order_id Order ID
     */
    private function remove_from_rate_limited_queue($order_id) {
        $queue = get_transient('dmm_rate_limited_orders');
        if (is_array($queue) && isset($queue[$order_id])) {
            unset($queue[$order_id]);
            set_transient('dmm_rate_limited_orders', $queue, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Get rate-limited orders queue
     * 
     * @return array Array of rate-limited orders with retry info
     */
    public function get_rate_limited_orders() {
        $queue = get_transient('dmm_rate_limited_orders');
        if (!is_array($queue)) {
            return [];
        }
        
        // Filter out orders that should have been retried by now
        $now = time();
        $active_queue = [];
        foreach ($queue as $order_id => $order_data) {
            if ($order_data['retry_at'] > $now) {
                $active_queue[$order_id] = $order_data;
            } else {
                // Retry time has passed, try to process it
                $this->retry_rate_limited_order($order_id);
            }
        }
        
        return $active_queue;
    }
    
    /**
     * AJAX handler to get rate-limited orders
     */
    public function ajax_get_rate_limited_orders() {
        $this->verify_ajax_request('dmm_get_rate_limited_orders');
        
        $queue = $this->get_rate_limited_orders();
        
        // Format queue for display
        $formatted_queue = [];
        foreach ($queue as $order_id => $order_data) {
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;
            }
            
            $now = time();
            $retry_at = $order_data['retry_at'];
            $seconds_until_retry = max(0, $retry_at - $now);
            
            $formatted_queue[] = [
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'order_total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'retry_at' => $retry_at,
                'retry_at_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $retry_at),
                'seconds_until_retry' => $seconds_until_retry,
                'wait_seconds' => $order_data['wait_seconds'],
                'queued_at' => $order_data['queued_at'],
                'queued_at_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $order_data['queued_at']),
                'attempts' => $order_data['attempts'],
                'can_retry_now' => $seconds_until_retry <= 0
            ];
        }
        
        // Sort by retry_at (earliest first)
        usort($formatted_queue, function($a, $b) {
            return $a['retry_at'] - $b['retry_at'];
        });
        
        wp_send_json_success([
            'orders' => $formatted_queue,
            'total' => count($formatted_queue)
        ]);
    }
    
    /**
     * AJAX handler to retry a single rate-limited order
     */
    public function ajax_retry_rate_limited_order() {
        $this->verify_ajax_request('dmm_retry_rate_limited_order');
        
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID', 'dmm-delivery-bridge')]);
        }
        
        // Retry the order
        $this->retry_rate_limited_order($order_id);
        
        // Check if it was successful
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'dmm-delivery-bridge')]);
        }
        
        if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
            wp_send_json_success([
                'message' => __('Order sent successfully', 'dmm-delivery-bridge')
            ]);
        } else {
            // Check if still rate limited
            $queue = get_transient('dmm_rate_limited_orders');
            $is_still_queued = is_array($queue) && isset($queue[$order_id]);
            
            if ($is_still_queued) {
                $wait_seconds = $queue[$order_id]['wait_seconds'];
                wp_send_json_error([
                    'message' => sprintf(__('Order is still rate limited. Please wait %d seconds.', 'dmm-delivery-bridge'), $wait_seconds),
                    'still_rate_limited' => true,
                    'wait_seconds' => $wait_seconds
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Order retry failed. Please check the order manually.', 'dmm-delivery-bridge')
                ]);
            }
        }
    }
    
    /**
     * AJAX handler to retry all rate-limited orders that are ready
     */
    public function ajax_retry_all_rate_limited_orders() {
        $this->verify_ajax_request('dmm_retry_all_rate_limited_orders');
        
        $queue = $this->get_rate_limited_orders();
        $now = time();
        $retried = 0;
        $still_rate_limited = 0;
        $errors = [];
        
        foreach ($queue as $order_id => $order_data) {
            // Only retry orders that are ready (retry_at <= now)
            if ($order_data['retry_at'] <= $now) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    continue;
                }
                
                // Check if already sent
                if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
                    $this->remove_from_rate_limited_queue($order_id);
                    $retried++;
                    continue;
                }
                
                // Try to retry
                try {
                    $this->retry_rate_limited_order($order_id);
                    
                    // Check result
                    $order = wc_get_order($order_id);
                    if ($order && $order->get_meta('_dmm_delivery_sent') === 'yes') {
                        $retried++;
                    } else {
                        // Check if still in queue (still rate limited)
                        $updated_queue = get_transient('dmm_rate_limited_orders');
                        if (is_array($updated_queue) && isset($updated_queue[$order_id])) {
                            $still_rate_limited++;
                        } else {
                            $errors[] = sprintf(__('Order %d: Retry failed', 'dmm-delivery-bridge'), $order_id);
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = sprintf(__('Order %d: %s', 'dmm-delivery-bridge'), $order_id, $e->getMessage());
                }
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('Retried %d orders. %d still rate limited. %d errors.', 'dmm-delivery-bridge'),
                $retried,
                $still_rate_limited,
                count($errors)
            ),
            'retried' => $retried,
            'still_rate_limited' => $still_rate_limited,
            'errors' => $errors
        ]);
    }
}


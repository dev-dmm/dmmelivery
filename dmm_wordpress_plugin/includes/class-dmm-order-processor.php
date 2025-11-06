<?php
/**
 * Order Processor class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Order_Processor
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
     * @return void
     * @since 1.0.0
     */
    public function process_order_async($args) {
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
        
        // Process the order
        $this->process_order_robust($order);
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
     * @return void
     * @since 1.0.0
     */
    public function process_order_robust($order) {
        $order_id = $order->get_id();
        
        // Check if already sent successfully
        if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
            $this->logger->debug_log('Order ' . $order_id . ' already sent, skipping');
            return;
        }
        
        // Generate idempotency key
        $idempotency_key = $this->generate_idempotency_key($order);
        
        // Check retry count
        $retry_count = (int) $order->get_meta('_dmm_delivery_retry_count');
        $max_retries = 5;
        
        if ($retry_count >= $max_retries) {
            $this->handle_max_retries_reached($order);
            return;
        }
        
        $order_data = null;
        $response = null;
        
        try {
            // Prepare order data with idempotency key
            $order_data = $this->prepare_order_data($order);
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
        }
        
        // Always log the result, even if there was an exception
        if ($order_data && $response) {
            $this->logger->log_request($order_id, $order_data, $response);
        }
        
        // Handle response
        if ($response && $response['success']) {
            $this->handle_successful_send($order, $response);
            
            // Invalidate cache for this order
            if (method_exists($this->api_client, 'get_cache_service')) {
                // Cache service is private, so we'll invalidate via a helper method
                $this->invalidate_order_cache($order_id);
            } else {
                // Fallback: use cache service directly if available
                $cache_service = new DMM_Cache_Service($this->options, $this->logger);
                $cache_service->invalidate_order_cache($order_id);
            }
        } else {
            $this->handle_failed_send($order, $response, $retry_count);
        }
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
    
    /**
     * Handle successful send
     *
     * @param WC_Order $order Order object
     * @param array    $response API response
     */
    private function handle_successful_send($order, $response) {
        $order_id = $order->get_id();
        
        // Mark as sent
        $order->update_meta_data('_dmm_delivery_sent', 'yes');
        $order->update_meta_data('_dmm_delivery_order_id', $response['data']['order_id'] ?? '');
        $order->update_meta_data('_dmm_delivery_shipment_id', $response['data']['shipment_id'] ?? '');
        $order->update_meta_data('_dmm_delivery_sent_at', gmdate('c'));
        
        // Clear retry count
        $order->delete_meta_data('_dmm_delivery_retry_count');
        
        // Save once after all meta changes
        $order->save();
        
        // Add order note
        $order->add_order_note(__('Order sent to DMM Delivery system successfully.', 'dmm-delivery-bridge'));
        
        $this->logger->log_structured('order_sent_success', [
            'order_id' => $order_id,
            'dmm_order_id' => $response['data']['order_id'] ?? '',
            'dmm_shipment_id' => $response['data']['shipment_id'] ?? ''
        ]);
    }
    
    /**
     * Handle failed send
     *
     * @param WC_Order $order Order object
     * @param array    $response API response
     * @param int      $retry_count Current retry count
     */
    private function handle_failed_send($order, $response, $retry_count) {
        $order_id = $order->get_id();
        $new_retry_count = $retry_count + 1;
        
        // Update retry count
        $order->update_meta_data('_dmm_delivery_retry_count', $new_retry_count);
        $order->save();
        
        // Add order note
        $error_message = $response ? $response['message'] : __('Unknown error occurred', 'dmm-delivery-bridge');
        $order->add_order_note(
            sprintf(__('Failed to send order to DMM Delivery system (attempt %d): %s', 'dmm-delivery-bridge'), $new_retry_count, $error_message)
        );
        
        $this->logger->log_structured('order_send_failed', [
            'order_id' => $order_id,
            'retry_count' => $new_retry_count,
            'error' => $error_message
        ]);
        
        // Schedule retry if retryable using centralized scheduler
        if ($this->api_client->is_retryable_error($response)) {
            $this->scheduler->schedule_retry(
                'dmm_send_order',
                ['order_id' => $order_id],
                $new_retry_count
            );
        }
    }
    
    /**
     * Handle max retries reached
     *
     * @param WC_Order $order Order object
     */
    private function handle_max_retries_reached($order) {
        $order_id = $order->get_id();
        
        // Clear the lock
        delete_transient('dmm_sending_' . $order_id);
        
        // Mark as failed
        $order->update_meta_data('_dmm_delivery_sent', 'failed');
        $order->update_meta_data('_dmm_delivery_failed_at', gmdate('c'));
        $order->save();
        
        // Add order note
        $order->add_order_note(__('Order failed to send to DMM Delivery system after maximum retries. Please check manually.', 'dmm-delivery-bridge'));
        
        $this->logger->log_structured('order_max_retries_reached', [
            'order_id' => $order_id
        ]);
    }
    
    /**
     * Schedule retry (deprecated - use scheduler->schedule_retry instead)
     * Kept for backward compatibility but now delegates to scheduler
     *
     * @param int $order_id Order ID
     * @param int $retry_count Retry count
     * @deprecated Use $this->scheduler->schedule_retry() instead
     */
    private function schedule_retry($order_id, $retry_count) {
        // Delegate to centralized scheduler
        $this->scheduler->schedule_retry(
            'dmm_send_order',
            ['order_id' => $order_id],
            $retry_count
        );
    }
    
    /**
     * Prepare WooCommerce order data for DMM Delivery API
     * 
     * Transforms a WooCommerce order object into the format expected by the DMM Delivery API.
     * Handles edge cases gracefully (missing shipping address, product images, etc.).
     * 
     * Data structure:
     * - 'source': Always 'woocommerce'
     * - 'order': Order details (ID, status, totals, items, etc.)
     * - 'customer': Customer billing information
     * - 'shipping': Shipping address and weight
     * - 'create_shipment': Whether to create shipment (from settings)
     * - 'preferred_courier': Courier preference from order meta
     * 
     * Special handling:
     * - Falls back to billing address if shipping address is missing
     * - Attempts to get product images (main image, gallery, or placeholder)
     * - Calculates order weight from product weights
     * - Strips HTML tags from text fields for security
     * - Handles exceptions gracefully (returns minimal data on error)
     * 
     * This method is designed to never throw exceptions. If an error occurs, it returns
     * minimal valid data to prevent complete failure.
     *
     * @param \WC_Order $order WooCommerce order object
     * @return array Order data array ready for API submission
     * @since 1.0.0
     */
    public function prepare_order_data($order) {
        try {
            $shipping_address = $order->get_address('shipping');
            if (empty($shipping_address['address_1'])) {
                $shipping_address = $order->get_address('billing');
            }
            
            $billing_email = $order->get_billing_email();
            $billing_phone = $order->get_billing_phone();
            $billing_first_name = $order->get_billing_first_name();
            $billing_last_name = $order->get_billing_last_name();
            
            // Prepare order items
            $order_items = [];
            foreach ($order->get_items() as $item) {
                try {
                    $product = $item->get_product();
                    
                    // Get product image URL
                    $image_url = '';
                    if ($product) {
                        try {
                            $image_id = $product->get_image_id();
                            if ($image_id) {
                                $image_url = wp_get_attachment_image_url($image_id, 'full');
                            }
                            
                            // If no main image, try to get the first gallery image
                            if (!$image_url) {
                                $gallery_ids = $product->get_gallery_image_ids();
                                if (!empty($gallery_ids)) {
                                    $image_url = wp_get_attachment_image_url($gallery_ids[0], 'full');
                                }
                            }
                            
                            // If still no image, try to get placeholder image
                            if (!$image_url) {
                                $image_url = wc_placeholder_img_src('full');
                            }
                        } catch (Exception $e) {
                            if ($this->logger->is_debug_mode()) {
                                $this->logger->debug_log('Error getting product image: ' . $e->getMessage());
                            }
                            $image_url = '';
                        }
                    }
                    
                    $order_items[] = [
                        'sku' => $product ? $product->get_sku() : '',
                        'name' => strip_tags($item->get_name()),
                        'quantity' => $item->get_quantity(),
                        'price' => (float) $item->get_total() / $item->get_quantity(),
                        'total' => (float) $item->get_total(),
                        'weight' => $product && $product->has_weight() ? (float) $product->get_weight() : 0,
                        'product_id' => $product ? $product->get_id() : 0,
                        'variation_id' => $item->get_variation_id(),
                        'image_url' => $image_url,
                    ];
                } catch (Exception $e) {
                    if ($this->logger->is_debug_mode()) {
                        $this->logger->debug_log('Error processing product: ' . $e->getMessage());
                    }
                    continue;
                }
            }
            
            return [
                'source' => 'woocommerce',
                'order' => [
                    'external_order_id' => (string) $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'total_amount' => (float) $order->get_total(),
                    'subtotal' => (float) $order->get_subtotal(),
                    'tax_amount' => (float) $order->get_total_tax(),
                    'shipping_cost' => (float) $order->get_shipping_total(),
                    'discount_amount' => (float) $order->get_discount_total(),
                    'currency' => $order->get_currency(),
                    'payment_status' => $order->is_paid() ? 'paid' : 'pending',
                    'payment_method' => $order->get_payment_method(),
                    'items' => $order_items,
                ],
                'customer' => [
                    'first_name' => strip_tags($billing_first_name),
                    'last_name' => strip_tags($billing_last_name),
                    'email' => $billing_email,
                    'phone' => $billing_phone,
                ],
                'shipping' => [
                    'address' => [
                        'first_name' => strip_tags($shipping_address['first_name'] ?? $billing_first_name),
                        'last_name' => strip_tags($shipping_address['last_name'] ?? $billing_last_name),
                        'company' => strip_tags($shipping_address['company'] ?? ''),
                        'address_1' => strip_tags($shipping_address['address_1'] ?? ''),
                        'address_2' => strip_tags($shipping_address['address_2'] ?? ''),
                        'city' => strip_tags($shipping_address['city'] ?? ''),
                        'postcode' => $shipping_address['postcode'] ?? '',
                        'country' => $shipping_address['country'] ?? 'GR',
                        'phone' => $shipping_address['phone'] ?? $billing_phone,
                        'email' => $billing_email,
                    ],
                    'weight' => $this->calculate_order_weight($order),
                ],
                'create_shipment' => isset($this->options['create_shipment']) && $this->options['create_shipment'] === 'yes',
                'preferred_courier' => $this->get_courier_from_order($order),
            ];
            
        } catch (Exception $e) {
            $this->logger->debug_log('Fatal error in prepare_order_data: ' . $e->getMessage());
            
            // Return minimal data to prevent complete failure
            return [
                'source' => 'woocommerce',
                'order' => [
                    'external_order_id' => (string) $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'total_amount' => (float) $order->get_total(),
                    'subtotal' => (float) $order->get_subtotal(),
                    'tax_amount' => (float) $order->get_total_tax(),
                    'shipping_cost' => (float) $order->get_shipping_total(),
                    'discount_amount' => (float) $order->get_discount_total(),
                    'currency' => $order->get_currency(),
                    'payment_status' => $order->is_paid() ? 'paid' : 'pending',
                    'payment_method' => $order->get_payment_method(),
                    'items' => [],
                ],
                'customer' => [
                    'first_name' => strip_tags($order->get_billing_first_name()),
                    'last_name' => strip_tags($order->get_billing_last_name()),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                ],
                'shipping' => [
                    'address' => [
                        'first_name' => strip_tags($order->get_billing_first_name()),
                        'last_name' => strip_tags($order->get_billing_last_name()),
                        'company' => '',
                        'address_1' => strip_tags($order->get_billing_address_1()),
                        'address_2' => strip_tags($order->get_billing_address_2()),
                        'city' => strip_tags($order->get_billing_city()),
                        'postcode' => $order->get_billing_postcode(),
                        'country' => $order->get_billing_country(),
                        'phone' => $order->get_billing_phone(),
                        'email' => $order->get_billing_email(),
                    ],
                    'weight' => 0,
                ],
                'create_shipment' => false,
            ];
        }
    }
    
    /**
     * Calculate order weight
     *
     * @param WC_Order $order Order object
     * @return float Weight in kg
     */
    private function calculate_order_weight($order) {
        $weight = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->has_weight()) {
                $weight += (float) $product->get_weight() * $item->get_quantity();
            }
        }
        return $weight;
    }
    
    /**
     * Get courier from order meta
     *
     * @param WC_Order $order Order object
     * @return string Courier name or empty
     */
    private function get_courier_from_order($order) {
        // Check for various courier meta fields
        $courier_meta_keys = [
            '_acs_voucher',
            'acs_voucher',
            'courier',
            'shipping_courier',
        ];
        
        foreach ($courier_meta_keys as $key) {
            $value = $order->get_meta($key);
            if ($value) {
                // Try to determine courier from meta key or value
                if (strpos($key, 'acs') !== false) {
                    return 'acs';
                }
                return strtolower($value);
            }
        }
        
        return '';
    }
}


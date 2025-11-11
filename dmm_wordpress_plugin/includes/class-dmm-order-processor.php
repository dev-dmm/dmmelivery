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
        
        // Check voucher status and update delivery score if applicable
        $this->check_voucher_status_and_update_score($order, $response);
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
     * - 'voucher_number': Voucher/tracking number if found in order meta
     * - 'courier_company': Courier company name if voucher found
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
            // Skip refunds - they don't have address methods
            if ($order instanceof \WC_Order_Refund) {
                throw new \Exception('Cannot process refund orders');
            }
            
            // Ensure it's a valid order object with address methods
            if (!method_exists($order, 'get_address')) {
                throw new \Exception('Order object does not support get_address() method');
            }
            
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
                    
                    // Calculate price per unit (avoid division by zero)
                    $quantity = max(1, $item->get_quantity());
                    $item_total = (float) $item->get_total();
                    $price_per_unit = $quantity > 0 ? $item_total / $quantity : 0;
                    
                    // Get item name - use product name if item name is empty
                    $item_name = strip_tags($item->get_name());
                    if (empty($item_name) && $product) {
                        $item_name = strip_tags($product->get_name());
                    }
                    if (empty($item_name)) {
                        $item_name = __('Product', 'dmm-delivery-bridge') . ' #' . ($product ? $product->get_id() : $item->get_id());
                    }
                    
                    $order_items[] = [
                        'sku' => $product ? $product->get_sku() : '',
                        'name' => $item_name,
                        'quantity' => $quantity,
                        'price' => $price_per_unit,
                        'total' => $item_total,
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
            ];
            
            // Get comprehensive courier and voucher information
            $courier_voucher_info = $this->get_courier_voucher_from_order($order);
            if (!empty($courier_voucher_info['courier'])) {
                $order_data['preferred_courier'] = $courier_voucher_info['courier'];
            }
            if (!empty($courier_voucher_info['voucher_number'])) {
                $order_data['voucher_number'] = $courier_voucher_info['voucher_number'];
                $order_data['courier_company'] = $courier_voucher_info['courier']; // Also include company name
            }
            
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
     * Get courier and voucher information from order meta
     * 
     * Determines courier based on user-configured meta fields only.
     * If a configured meta field has a value, that courier is selected.
     *
     * @param WC_Order $order Order object
     * @return array Array with 'courier' (lowercase name) and 'voucher_number' (or empty strings)
     */
    private function get_courier_voucher_from_order($order) {
        $result = [
            'courier' => '',
            'voucher_number' => ''
        ];
        
        if (!$order) {
            return $result;
        }
        
        $options = get_option('dmm_delivery_bridge_options', []);
        
        // Check each courier's configured meta field (in priority order)
        // Only check the user-configured field - if it has a value, that's the courier
        $courier_checks = [
            'elta' => isset($options['elta_voucher_meta_field']) && !empty($options['elta_voucher_meta_field']) 
                ? $options['elta_voucher_meta_field'] 
                : null,
            'geniki' => isset($options['geniki_voucher_meta_field']) && !empty($options['geniki_voucher_meta_field']) 
                ? $options['geniki_voucher_meta_field'] 
                : null,
            'acs' => isset($options['acs_voucher_meta_field']) && !empty($options['acs_voucher_meta_field']) 
                ? $options['acs_voucher_meta_field'] 
                : null,
            'speedex' => isset($options['speedex_voucher_meta_field']) && !empty($options['speedex_voucher_meta_field']) 
                ? $options['speedex_voucher_meta_field'] 
                : null,
        ];
        
        // Check each courier's configured meta field
        foreach ($courier_checks as $courier_name => $meta_field) {
            // Skip if no meta field configured for this courier
            if (empty($meta_field)) {
                continue;
            }
            
            // Check if the configured meta field has a value
            $value = $order->get_meta($meta_field);
            if (!empty($value) && trim($value) !== '') {
                // Found a voucher for this courier
                $result['courier'] = strtolower($courier_name);
                $result['voucher_number'] = trim($value);
                return $result; // Return first match (priority order)
            }
        }
        
        // No configured courier fields matched - return empty result
        // Order will be sent without courier information
        return $result;
    }
    
    /**
     * Check voucher status and update delivery score if applicable
     * 
     * After successfully sending an order, if it has a voucher, check the courier API
     * for the delivery status. If the status is final (delivered, returned, cancelled),
     * update the shipment status in Laravel backend, which will trigger delivery score update.
     *
     * @param WC_Order $order Order object
     * @param array    $response API response from order send
     * @return void
     */
    private function check_voucher_status_and_update_score($order, $response) {
        try {
            // Get voucher information from order
            $courier_voucher_info = $this->get_courier_voucher_from_order($order);
            
            // Skip if no voucher found
            if (empty($courier_voucher_info['voucher_number']) || empty($courier_voucher_info['courier'])) {
                return;
            }
            
            $voucher_number = $courier_voucher_info['voucher_number'];
            $courier_name = $courier_voucher_info['courier'];
            $shipment_id = $response['data']['shipment_id'] ?? null;
            
            // Skip if no shipment ID (order wasn't converted to shipment)
            if (empty($shipment_id)) {
                $this->logger->debug_log("Skipping voucher status check: No shipment ID for order {$order->get_id()}");
                return;
            }
            
            $this->logger->log_structured('voucher_status_check_start', [
                'order_id' => $order->get_id(),
                'shipment_id' => $shipment_id,
                'voucher_number' => $voucher_number,
                'courier' => $courier_name
            ]);
            
            // Check voucher status from courier API
            $status_result = $this->check_courier_voucher_status($courier_name, $voucher_number, $shipment_id);
            
            if (!$status_result['success']) {
                $this->logger->log_structured('voucher_status_check_failed', [
                    'order_id' => $order->get_id(),
                    'shipment_id' => $shipment_id,
                    'voucher_number' => $voucher_number,
                    'courier' => $courier_name,
                    'error' => $status_result['message'] ?? 'Unknown error'
                ]);
                return;
            }
            
            $status = $status_result['status'] ?? null;
            
            // Only update if status is final (delivered, returned, cancelled)
            $final_statuses = ['delivered', 'returned', 'cancelled'];
            if (!in_array(strtolower($status), $final_statuses)) {
                $this->logger->debug_log("Voucher status is not final: {$status} for order {$order->get_id()}");
                return;
            }
            
            // Update shipment status via Laravel API (this will trigger delivery score update)
            $this->update_shipment_status($shipment_id, strtolower($status));
            
        } catch (Exception $e) {
            // Log error but don't fail the order processing
            $this->logger->log_structured('voucher_status_check_exception', [
                'order_id' => $order->get_id(),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check voucher status from courier API
     * 
     * Makes direct API calls to courier services using credentials stored in WordPress.
     * The plugin has courier API credentials because Laravel backend is not whitelisted.
     *
     * @param string $courier_name Courier name (elta, geniki, acs, speedex)
     * @param string $voucher_number Voucher/tracking number
     * @param string $shipment_id Shipment ID from Laravel (for logging only)
     * @return array Result array with 'success', 'status', and optional 'message'
     */
    private function check_courier_voucher_status($courier_name, $voucher_number, $shipment_id) {
        try {
            $courier_name_lower = strtolower($courier_name);
            
            // Route to appropriate courier API call based on courier name
            switch ($courier_name_lower) {
                case 'acs':
                    return $this->check_acs_voucher_status($voucher_number);
                    
                case 'elta':
                    return $this->check_elta_voucher_status($voucher_number);
                    
                case 'geniki':
                    return $this->check_geniki_voucher_status($voucher_number);
                    
                case 'speedex':
                    return $this->check_speedex_voucher_status($voucher_number);
                    
                default:
                    return [
                        'success' => false,
                        'message' => "Unsupported courier: {$courier_name}"
                    ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check ACS voucher status using ACS API
     *
     * @param string $voucher_number ACS voucher number
     * @return array Result array with 'success', 'status', and optional 'message'
     */
    private function check_acs_voucher_status($voucher_number) {
        try {
            // Get ACS credentials from options
            $company_id = $this->options['acs_company_id'] ?? '';
            $company_password = $this->options['acs_company_password'] ?? '';
            $user_id = $this->options['acs_user_id'] ?? '';
            $user_password = $this->options['acs_user_password'] ?? '';
            $api_endpoint = $this->options['acs_api_endpoint'] ?? 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest';
            
            if (empty($company_id) || empty($company_password) || empty($user_id) || empty($user_password)) {
                return [
                    'success' => false,
                    'message' => 'ACS credentials not configured'
                ];
            }
            
            // Build ACS API request for tracking
            $payload = [
                'ACSAlias' => 'ACS_TrackingDetails',
                'ACSInputParameters' => [
                    'Company_ID' => $company_id,
                    'Company_Password' => $company_password,
                    'User_ID' => $user_id,
                    'User_Password' => $user_password,
                    'Language' => 'GR',
                    'Voucher_No' => $voucher_number
                ]
            ];
            
            $response = wp_remote_post($api_endpoint, [
                'method' => 'POST',
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode($payload),
                'sslverify' => true
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => $response->get_error_message()
                ];
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            // Parse ACS response and extract status
            // ACS returns status in various formats - need to map to our standard statuses
            if (isset($response_data['ACSOutputParameters'])) {
                $output = $response_data['ACSOutputParameters'];
                $status = $this->map_acs_status_to_standard($output);
                
                if ($status) {
                    return [
                        'success' => true,
                        'status' => $status,
                        'data' => $output
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Could not parse ACS response'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'ACS API error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check ELTA voucher status using ELTA API
     *
     * @param string $voucher_number ELTA voucher number
     * @return array Result array with 'success', 'status', and optional 'message'
     */
    private function check_elta_voucher_status($voucher_number) {
        try {
            // Get ELTA credentials from options
            $user_code = $this->options['elta_user_code'] ?? '';
            $user_pass = $this->options['elta_user_pass'] ?? '';
            $apost_code = $this->options['elta_apost_code'] ?? '';
            $api_endpoint = $this->options['elta_api_endpoint'] ?? 'https://customers.elta-courier.gr';
            
            if (empty($user_code) || empty($user_pass) || empty($apost_code)) {
                return [
                    'success' => false,
                    'message' => 'ELTA credentials not configured'
                ];
            }
            
            // ELTA API implementation would go here
            // This is a placeholder - implement based on ELTA API documentation
            $this->logger->debug_log("ELTA tracking not fully implemented yet for voucher: {$voucher_number}");
            
            return [
                'success' => false,
                'message' => 'ELTA tracking not fully implemented'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'ELTA API error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check Geniki voucher status using Geniki SOAP API
     *
     * @param string $voucher_number Geniki voucher number
     * @return array Result array with 'success', 'status', and optional 'message'
     */
    private function check_geniki_voucher_status($voucher_number) {
        try {
            // Get Geniki credentials from options
            $username = $this->options['geniki_username'] ?? '';
            $password = $this->options['geniki_password'] ?? '';
            $application_key = $this->options['geniki_application_key'] ?? '';
            $soap_endpoint = $this->options['geniki_soap_endpoint'] ?? 'https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL';
            
            if (empty($username) || empty($password) || empty($application_key)) {
                return [
                    'success' => false,
                    'message' => 'Geniki credentials not configured'
                ];
            }
            
            // Geniki SOAP API implementation would go here
            // This is a placeholder - implement based on Geniki SOAP API documentation
            $this->logger->debug_log("Geniki tracking not fully implemented yet for voucher: {$voucher_number}");
            
            return [
                'success' => false,
                'message' => 'Geniki tracking not fully implemented'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Geniki API error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check Speedex voucher status
     *
     * @param string $voucher_number Speedex voucher number
     * @return array Result array with 'success', 'status', and optional 'message'
     */
    private function check_speedex_voucher_status($voucher_number) {
        try {
            // Speedex API implementation would go here
            $this->logger->debug_log("Speedex tracking not fully implemented yet for voucher: {$voucher_number}");
            
            return [
                'success' => false,
                'message' => 'Speedex tracking not fully implemented'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Speedex API error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Map ACS courier status to standard status (delivered, returned, cancelled)
     *
     * @param array $acs_output ACS API output parameters
     * @return string|null Standard status or null if cannot determine
     */
    private function map_acs_status_to_standard($acs_output) {
        // ACS status mapping logic
        // This needs to be implemented based on actual ACS API response structure
        // Common ACS statuses might be: "Delivered", "Returned", "Cancelled", "In Transit", etc.
        
        if (isset($acs_output['Status'])) {
            $status = strtolower($acs_output['Status']);
            
            // Map ACS statuses to our standard statuses
            if (strpos($status, 'delivered') !== false || strpos($status, 'delivery') !== false) {
                return 'delivered';
            }
            if (strpos($status, 'returned') !== false || strpos($status, 'return') !== false) {
                return 'returned';
            }
            if (strpos($status, 'cancelled') !== false || strpos($status, 'cancel') !== false) {
                return 'cancelled';
            }
        }
        
        // If status is not final, return null (don't update score)
        return null;
    }
    
    /**
     * Update shipment status in Laravel backend
     * 
     * Updates the shipment status via Laravel API. When status is final (delivered, returned, cancelled),
     * Laravel will automatically update the customer's delivery score.
     *
     * @param string $shipment_id Shipment ID from Laravel
     * @param string $status Final status (delivered, returned, cancelled)
     * @return void
     */
    private function update_shipment_status($shipment_id, $status) {
        try {
            $api_endpoint = isset($this->options['api_endpoint']) ? $this->options['api_endpoint'] : '';
            $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
            $tenant_id = isset($this->options['tenant_id']) ? $this->options['tenant_id'] : '';
            
            if (empty($api_endpoint) || empty($api_key) || empty($tenant_id)) {
                $this->logger->debug_log('Cannot update shipment status: API configuration incomplete');
                return;
            }
            
            // Build API endpoint for updating shipment status
            $update_endpoint = rtrim($api_endpoint, '/') . '/api/v1/shipments/' . urlencode($shipment_id) . '/update-status';
            
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Api-Key' => $api_key,
                'X-Tenant-Id' => $tenant_id,
            ];
            
            $payload = [
                'status' => $status
            ];
            
            $args = [
                'method' => 'POST',
                'timeout' => 15,
                'headers' => $headers,
                'body' => json_encode($payload),
                'sslverify' => true,
            ];
            
            $response = wp_remote_request($update_endpoint, $args);
            
            if (is_wp_error($response)) {
                $this->logger->log_structured('shipment_status_update_failed', [
                    'shipment_id' => $shipment_id,
                    'status' => $status,
                    'error' => $response->get_error_message()
                ]);
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            if ($response_code >= 200 && $response_code < 300) {
                $this->logger->log_structured('shipment_status_updated', [
                    'shipment_id' => $shipment_id,
                    'status' => $status,
                    'delivery_score_updated' => true
                ]);
            } else {
                $this->logger->log_structured('shipment_status_update_error', [
                    'shipment_id' => $shipment_id,
                    'status' => $status,
                    'http_code' => $response_code,
                    'error' => $response_data['message'] ?? 'Unknown error'
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->log_structured('shipment_status_update_exception', [
                'shipment_id' => $shipment_id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
    
}


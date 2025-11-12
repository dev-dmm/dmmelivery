<?php
/**
 * Order Data Preparer for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Order_Data_Preparer
 * Handles preparation of WooCommerce order data for API submission
 */
class DMM_Order_Data_Preparer {
    
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
     * Constructor
     *
     * @param array      $options Plugin options
     * @param DMM_Logger $logger Logger instance
     */
    public function __construct($options, $logger) {
        $this->options = $options;
        $this->logger = $logger;
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
            
            $order_data = [
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
            
            return $order_data;
            
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
    public function get_courier_voucher_from_order($order) {
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
}


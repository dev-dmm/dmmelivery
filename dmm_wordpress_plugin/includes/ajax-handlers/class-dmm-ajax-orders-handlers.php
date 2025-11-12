<?php
/**
 * Orders List and Meta Fields AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Orders_Handlers
 * Handles orders list retrieval and meta fields
 */
class DMM_AJAX_Orders_Handlers {
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
     * Register orders AJAX handlers
     */
    public function register_handlers() {
        add_action('wp_ajax_dmm_get_orders_list', [$this, 'ajax_get_orders_list']);
        add_action('wp_ajax_dmm_get_order_meta_fields', [$this, 'ajax_get_order_meta_fields']);
        add_action('wp_ajax_dmm_get_order_stats', [$this, 'ajax_get_order_stats']);
    }
    
    public function ajax_get_orders_list() {
        $this->verify_ajax_request('dmm_get_orders_list', 'both');
        
        // Get filter parameters
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $sent_filter = isset($_POST['sent']) ? sanitize_text_field($_POST['sent']) : '';
        $order_id_filter = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        // Build query arguments
        $args = [
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'type' => 'shop_order', // Exclude refunds
        ];
        
        // Filter by WooCommerce order status
        if (!empty($status_filter)) {
            $args['status'] = $status_filter;
        }
        
        // Filter by order ID
        if ($order_id_filter > 0) {
            $args['include'] = [$order_id_filter];
        }
        
        // Get orders
        $orders = wc_get_orders($args);
        
        // Filter out refunds
        $orders = array_filter($orders, function($order) {
            return !($order instanceof \WC_Order_Refund);
        });
        
        // Filter by DMM sent status
        if ($sent_filter !== '') {
            $orders = array_filter($orders, function($order) use ($sent_filter) {
                $is_sent = $order->get_meta('_dmm_delivery_sent') === 'yes';
                if ($sent_filter === 'sent') {
                    return $is_sent;
                } elseif ($sent_filter === 'not_sent') {
                    return !$is_sent;
                }
                return true;
            });
        }
        
        // Get total count (approximate, for pagination)
        $total_args = $args;
        $total_args['limit'] = -1;
        $total_orders = wc_get_orders($total_args);
        $total_orders = array_filter($total_orders, function($order) {
            return !($order instanceof \WC_Order_Refund);
        });
        
        if ($sent_filter !== '') {
            $total_orders = array_filter($total_orders, function($order) use ($sent_filter) {
                $is_sent = $order->get_meta('_dmm_delivery_sent') === 'yes';
                if ($sent_filter === 'sent') {
                    return $is_sent;
                } elseif ($sent_filter === 'not_sent') {
                    return !$is_sent;
                }
                return true;
            });
        }
        
        $total = count($total_orders);
        
        // Format orders for display
        $formatted_orders = [];
        foreach ($orders as $order) {
            $is_sent = $order->get_meta('_dmm_delivery_sent') === 'yes';
            $dmm_order_id = $order->get_meta('_dmm_delivery_order_id');
            $dmm_shipment_id = $order->get_meta('_dmm_delivery_shipment_id');
            $sent_at = $order->get_meta('_dmm_delivery_sent_at');
            
            $formatted_orders[] = [
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'customer_email' => $order->get_billing_email(),
                'dmm_sent' => $is_sent,
                'dmm_order_id' => $dmm_order_id ?: null,
                'dmm_shipment_id' => $dmm_shipment_id ?: null,
                'dmm_sent_at' => $sent_at ?: null,
                'edit_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            ];
        }
        
        wp_send_json_success([
            'orders' => $formatted_orders,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get all unique meta field keys from WooCommerce orders
     * Used to populate dropdown for voucher field selection
     */
    public function ajax_get_order_meta_fields() {
        $this->verify_ajax_request('dmm_get_order_meta_fields');
        
        global $wpdb;
        
        // Get all unique meta keys from WooCommerce orders
        $meta_keys = [];
        
        // Check if HPOS is enabled using the same method as the main plugin
        $is_hpos_enabled = false;
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            $is_hpos_enabled = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('custom_order_tables');
        } elseif (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            $is_hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        
        if ($is_hpos_enabled) {
            // HPOS: Query from wc_orders_meta table (WooCommerce orders only)
            $table_name = $wpdb->prefix . 'wc_orders_meta';
            $query = $wpdb->prepare(
                "SELECT DISTINCT meta_key 
                FROM {$table_name} 
                WHERE meta_key NOT LIKE %s 
                AND meta_key NOT LIKE %s
                ORDER BY meta_key ASC
                LIMIT 500",
                $wpdb->esc_like('_wp_') . '%',
                $wpdb->esc_like('_edit_') . '%'
            );
        } else {
            // Legacy: Query from postmeta table for WooCommerce shop_order post type
            $query = $wpdb->prepare(
                "SELECT DISTINCT pm.meta_key 
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_type = %s
                AND pm.meta_key NOT LIKE %s 
                AND pm.meta_key NOT LIKE %s
                ORDER BY pm.meta_key ASC
                LIMIT 500",
                'shop_order',
                $wpdb->esc_like('_wp_') . '%',
                $wpdb->esc_like('_edit_') . '%'
            );
        }
        
        $results = $wpdb->get_col($query);
        
        if ($results) {
            // Filter out empty values and ensure we only have WooCommerce order meta fields
            $meta_keys = array_values(array_filter($results, function($key) {
                return !empty($key) && 
                       strpos($key, '_wp_') !== 0 && 
                       strpos($key, '_edit_') !== 0;
            }));
        }
        
        wp_send_json_success([
            'meta_fields' => $meta_keys
        ]);
    }
    
    public function ajax_get_order_stats() {
        $this->verify_ajax_request('dmm_get_order_stats');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
}


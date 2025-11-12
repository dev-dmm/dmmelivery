<?php
/**
 * Core AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Core_Handlers
 * Handles core AJAX operations: test connection, resend order, sync order, etc.
 */
class DMM_AJAX_Core_Handlers {
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
     * Register core AJAX handlers
     */
    public function register_handlers() {
        add_action('wp_ajax_dmm_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_dmm_resend_order', [$this, 'ajax_resend_order']);
        add_action('wp_ajax_dmm_sync_order', [$this, 'ajax_sync_order']);
        add_action('wp_ajax_dmm_refresh_meta_fields', [$this, 'ajax_refresh_meta_fields']);
        add_action('wp_ajax_dmm_check_logs', [$this, 'ajax_check_logs']);
        add_action('wp_ajax_dmm_create_log_table', [$this, 'ajax_create_log_table']);
        add_action('wp_ajax_dmm_diagnose_orders', [$this, 'ajax_diagnose_orders']);
        add_action('wp_ajax_dmm_force_resend_all', [$this, 'ajax_force_resend_all']);
        add_action('wp_ajax_dmm_test_force_resend', [$this, 'ajax_test_force_resend']);
    }
    
    public function ajax_test_connection() {
        $this->verify_ajax_request('dmm_test_connection');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_resend_order() {
        $this->verify_ajax_request('dmm_resend_order');
        
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID', 'dmm-delivery-bridge')]);
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'dmm-delivery-bridge')]);
        }
        
        // Skip refunds
        if ($order instanceof \WC_Order_Refund) {
            wp_send_json_error(['message' => __('Cannot process refund orders', 'dmm-delivery-bridge')]);
        }
        
        // Process order
        if ($this->plugin && $this->plugin->order_processor) {
            try {
                $this->plugin->order_processor->process_order_robust($order);
                
                wp_send_json_success([
                    'message' => __('Order sent to DMM Delivery successfully', 'dmm-delivery-bridge')
                ]);
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => __('Failed to send order: ', 'dmm-delivery-bridge') . $e->getMessage()
                ]);
            }
        } else {
            wp_send_json_error(['message' => __('Order processor not available', 'dmm-delivery-bridge')]);
        }
    }
    
    public function ajax_sync_order() {
        $this->verify_ajax_request('dmm_sync_order');
        
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID', 'dmm-delivery-bridge')]);
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found', 'dmm-delivery-bridge')]);
        }
        
        // Check if order was sent
        if ($order->get_meta('_dmm_delivery_sent') !== 'yes') {
            wp_send_json_error(['message' => __('Order has not been sent to DMM Delivery yet', 'dmm-delivery-bridge')]);
        }
        
        // Sync order status
        if ($this->plugin) {
            try {
                $this->plugin->process_order_update(['order_id' => $order_id]);
                
                wp_send_json_success([
                    'message' => __('Order status synced successfully', 'dmm-delivery-bridge')
                ]);
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => __('Failed to sync order: ', 'dmm-delivery-bridge') . $e->getMessage()
                ]);
            }
        } else {
            wp_send_json_error(['message' => __('Plugin instance not available', 'dmm-delivery-bridge')]);
        }
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
}


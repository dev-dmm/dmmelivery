<?php
/**
 * Log Management AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Logs_Handlers
 * Handles log management operations
 */
class DMM_AJAX_Logs_Handlers {
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
     * Register log management AJAX handlers
     */
    public function register_handlers() {
        add_action('wp_ajax_dmm_get_logs_table', [$this, 'ajax_get_logs_table_simple']);
        add_action('wp_ajax_dmm_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_dmm_export_logs', [$this, 'ajax_export_logs']);
        add_action('wp_ajax_dmm_resend_log', [$this, 'ajax_resend_log']);
    }
    
    public function ajax_get_logs_table_simple() {
        // Use standardized verification (allows both manage_options and manage_woocommerce)
        $this->verify_ajax_request('dmm_get_logs_table', 'both');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name;
        
        if (!$table_exists) {
            wp_send_json_success([
                'logs' => [],
                'message' => __('Log table does not exist yet. Logs will appear here after operations are performed.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Get filter parameters
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $order_id_filter = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        
        // Build query
        $where = ['1=1'];
        $where_values = [];
        
        if (!empty($status_filter)) {
            $where[] = 'status = %s';
            $where_values[] = $status_filter;
        }
        
        if ($order_id_filter > 0) {
            $where[] = 'order_id = %d';
            $where_values[] = $order_id_filter;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total = $wpdb->get_var($count_query);
        
        // Get logs
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$limit, $offset]);
        $query = $wpdb->prepare($query, $query_values);
        
        $logs = $wpdb->get_results($query, ARRAY_A);
        
        // Format logs for display
        $formatted_logs = [];
        foreach ($logs as $log) {
            $formatted_logs[] = [
                'id' => $log['id'],
                'order_id' => $log['order_id'],
                'status' => $log['status'],
                'error_message' => $log['error_message'],
                'context' => $log['context'] ?? 'api',
                'created_at' => $log['created_at'],
                'request_data' => !empty($log['request_data']) ? json_decode($log['request_data'], true) : null,
                'response_data' => !empty($log['response_data']) ? json_decode($log['response_data'], true) : null,
            ];
        }
        
        wp_send_json_success([
            'logs' => $formatted_logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
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
}


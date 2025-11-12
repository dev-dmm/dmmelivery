<?php
/**
 * Monitoring and Circuit Breaker AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Monitoring_Handlers
 * Handles monitoring, circuit breaker, and error analysis
 */
class DMM_AJAX_Monitoring_Handlers {
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
     * Register monitoring AJAX handlers
     */
    public function register_handlers() {
        add_action('wp_ajax_dmm_get_monitoring_data', [$this, 'ajax_get_monitoring_data']);
        add_action('wp_ajax_dmm_clear_failed_orders', [$this, 'ajax_clear_failed_orders']);
        add_action('wp_ajax_dmm_reset_circuit_breaker', [$this, 'ajax_reset_circuit_breaker']);
        add_action('wp_ajax_dmm_get_circuit_breaker_status', [$this, 'ajax_get_circuit_breaker_status']);
        add_action('wp_ajax_dmm_get_recent_errors', [$this, 'ajax_get_recent_errors']);
        add_action('wp_ajax_dmm_analyze_error_patterns', [$this, 'ajax_analyze_error_patterns']);
    }
    
    public function ajax_get_monitoring_data() {
        $this->verify_ajax_request('dmm_monitoring');
        
        // Get circuit breaker status
        $api_client = $this->plugin ? $this->plugin->api_client : null;
        $circuit_breaker_status = null;
        
        if ($api_client && method_exists($api_client, 'get_circuit_breaker_status')) {
            $circuit_breaker_status = $api_client->get_circuit_breaker_status();
        }
        
        wp_send_json_success([
            'circuit_breaker' => $circuit_breaker_status
        ]);
    }
    
    public function ajax_clear_failed_orders() {
        $this->verify_ajax_request('dmm_monitoring');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_get_circuit_breaker_status() {
        $this->verify_ajax_request('dmm_monitoring');
        
        $api_client = $this->plugin ? $this->plugin->api_client : null;
        
        if (!$api_client || !method_exists($api_client, 'get_circuit_breaker_status')) {
            wp_send_json_error(['message' => __('API client not available', 'dmm-delivery-bridge')]);
        }
        
        $status = $api_client->get_circuit_breaker_status();
        wp_send_json_success($status);
    }
    
    public function ajax_reset_circuit_breaker() {
        $this->verify_ajax_request('dmm_monitoring');
        
        $api_client = $this->plugin ? $this->plugin->api_client : null;
        
        if (!$api_client || !method_exists($api_client, 'reset_circuit_breaker')) {
            wp_send_json_error(['message' => __('API client not available', 'dmm-delivery-bridge')]);
        }
        
        $reset = $api_client->reset_circuit_breaker();
        
        if ($reset) {
            wp_send_json_success([
                'message' => __('Circuit breaker has been reset. API calls are now enabled.', 'dmm-delivery-bridge')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Circuit breaker was not open, so no reset was needed.', 'dmm-delivery-bridge')
            ]);
        }
    }
    
    public function ajax_get_recent_errors() {
        $this->verify_ajax_request('dmm_monitoring');
        
        $api_client = $this->plugin ? $this->plugin->api_client : null;
        
        if (!$api_client || !method_exists($api_client, 'get_recent_errors')) {
            wp_send_json_error(['message' => __('API client not available', 'dmm-delivery-bridge')]);
        }
        
        $minutes = isset($_POST['minutes']) ? absint($_POST['minutes']) : 5;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
        
        // Sanitize inputs
        $minutes = min(max($minutes, 1), 60); // Between 1 and 60 minutes
        $limit = min(max($limit, 1), 200); // Between 1 and 200
        
        $errors = $api_client->get_recent_errors($minutes, $limit);
        wp_send_json_success($errors);
    }
    
    public function ajax_analyze_error_patterns() {
        $this->verify_ajax_request('dmm_monitoring');
        
        $api_client = $this->plugin ? $this->plugin->api_client : null;
        
        if (!$api_client || !method_exists($api_client, 'analyze_error_patterns')) {
            wp_send_json_error(['message' => __('API client not available', 'dmm-delivery-bridge')]);
        }
        
        $minutes = isset($_POST['minutes']) ? absint($_POST['minutes']) : 5;
        
        // Sanitize input
        $minutes = min(max($minutes, 1), 60); // Between 1 and 60 minutes
        
        $analysis = $api_client->analyze_error_patterns($minutes);
        wp_send_json_success($analysis);
    }
}


<?php
/**
 * AJAX Handlers class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 * 
 * This class acts as a facade/composer that delegates to specialized handler classes.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load handler classes
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-core-handlers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-bulk-handlers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-monitoring-handlers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-orders-handlers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-rate-limited-handlers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-logs-handlers.php';
require_once plugin_dir_path(__FILE__) . 'ajax-handlers/class-dmm-ajax-courier-handlers.php';

/**
 * Class DMM_AJAX_Handlers
 * Main AJAX handlers class that composes and delegates to specialized handlers
 */
class DMM_AJAX_Handlers {
    
    /**
     * Static flag to track if handlers have been registered
     * Prevents duplicate registrations
     *
     * @var bool
     */
    private static $handlers_registered = false;
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Main plugin instance
     *
     * @var DMM_Delivery_Bridge
     */
    private $plugin;
    
    /**
     * Core handlers instance
     *
     * @var DMM_AJAX_Core_Handlers
     */
    public $core_handler;
    
    /**
     * Bulk handlers instance
     *
     * @var DMM_AJAX_Bulk_Handlers
     */
    public $bulk_handler;
    
    /**
     * Monitoring handlers instance
     *
     * @var DMM_AJAX_Monitoring_Handlers
     */
    public $monitoring_handler;
    
    /**
     * Orders handlers instance
     *
     * @var DMM_AJAX_Orders_Handlers
     */
    public $orders_handler;
    
    /**
     * Rate-limited handlers instance
     *
     * @var DMM_AJAX_Rate_Limited_Handlers
     */
    public $rate_limited_handler;
    
    /**
     * Logs handlers instance
     *
     * @var DMM_AJAX_Logs_Handlers
     */
    public $logs_handler;
    
    /**
     * Courier handlers instance
     *
     * @var DMM_AJAX_Courier_Handlers
     */
    public $courier_handler;
    
    /**
     * Constructor
     *
     * @param array                $options Plugin options
     * @param DMM_Delivery_Bridge $plugin Main plugin instance
     */
    public function __construct($options = [], $plugin = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->plugin = $plugin;
        
        // Initialize handler instances
        $this->core_handler = new DMM_AJAX_Core_Handlers($plugin);
        $this->bulk_handler = new DMM_AJAX_Bulk_Handlers($plugin);
        $this->monitoring_handler = new DMM_AJAX_Monitoring_Handlers($plugin);
        $this->orders_handler = new DMM_AJAX_Orders_Handlers($plugin);
        $this->rate_limited_handler = new DMM_AJAX_Rate_Limited_Handlers($plugin);
        $this->logs_handler = new DMM_AJAX_Logs_Handlers($plugin);
        $this->courier_handler = new DMM_AJAX_Courier_Handlers($plugin);
        
        $this->register_ajax_handlers();
    }
    
    /**
     * Register all AJAX handlers
     * Uses static flag to prevent duplicate registrations
     */
    private function register_ajax_handlers() {
        // Prevent duplicate registrations using static flag
        if (self::$handlers_registered) {
            return;
        }
        
        // Additional safety check: verify the three problematic handlers aren't already registered
        $critical_handlers = [
            'wp_ajax_dmm_acs_track_shipment',
            'wp_ajax_dmm_acs_create_voucher',
            'wp_ajax_dmm_acs_test_connection'
        ];
        
        foreach ($critical_handlers as $handler) {
            if (has_action($handler)) {
                // Handler already registered, skip registration to prevent duplicates
                return;
            }
        }
        
        self::$handlers_registered = true;
        
        // Register all handlers through their respective classes
        $this->core_handler->register_handlers();
        $this->bulk_handler->register_handlers();
        $this->monitoring_handler->register_handlers();
        $this->orders_handler->register_handlers();
        $this->rate_limited_handler->register_handlers();
        $this->logs_handler->register_handlers();
        $this->courier_handler->register_handlers();
    }
    
    /**
     * Get rate-limited orders handler
     * Public method for accessing rate-limited handler functionality
     * 
     * @return DMM_AJAX_Rate_Limited_Handlers
     */
    public function get_rate_limited_handler() {
        return $this->rate_limited_handler;
    }
}

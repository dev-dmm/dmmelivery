<?php
/**
 * Courier-Specific AJAX Handlers for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '../traits/trait-dmm-ajax-verification.php';

/**
 * Class DMM_AJAX_Courier_Handlers
 * Handles ACS, Geniki, and ELTA courier-specific AJAX operations
 */
class DMM_AJAX_Courier_Handlers {
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
     * Register courier AJAX handlers
     */
    public function register_handlers() {
        // ACS Courier AJAX handlers
        add_action('wp_ajax_dmm_acs_track_shipment', [$this, 'ajax_acs_track_shipment']);
        add_action('wp_ajax_dmm_acs_create_voucher', [$this, 'ajax_acs_create_voucher']);
        add_action('wp_ajax_dmm_acs_calculate_price', [$this, 'ajax_acs_calculate_price']);
        add_action('wp_ajax_dmm_acs_validate_address', [$this, 'ajax_acs_validate_address']);
        add_action('wp_ajax_dmm_acs_find_stations', [$this, 'ajax_acs_find_stations']);
        add_action('wp_ajax_dmm_acs_test_connection', [$this, 'ajax_acs_test_connection']);
        add_action('wp_ajax_dmm_acs_sync_shipment', [$this, 'ajax_acs_sync_shipment']);
        
        // Geniki Taxidromiki AJAX handlers
        add_action('wp_ajax_dmm_geniki_test_connection', [$this, 'ajax_geniki_test_connection']);
        add_action('wp_ajax_dmm_geniki_track_shipment', [$this, 'ajax_geniki_track_shipment']);
        add_action('wp_ajax_dmm_geniki_get_shops', [$this, 'ajax_geniki_get_shops']);
        add_action('wp_ajax_dmm_geniki_create_voucher', [$this, 'ajax_geniki_create_voucher']);
        add_action('wp_ajax_dmm_geniki_get_pdf', [$this, 'ajax_geniki_get_pdf']);
        
        // ELTA Hellenic Post AJAX handlers
        add_action('wp_ajax_dmm_elta_test_connection', [$this, 'ajax_elta_test_connection']);
        add_action('wp_ajax_dmm_elta_track_shipment', [$this, 'ajax_elta_track_shipment']);
        add_action('wp_ajax_dmm_elta_create_tracking', [$this, 'ajax_elta_create_tracking']);
        add_action('wp_ajax_dmm_elta_update_tracking', [$this, 'ajax_elta_update_tracking']);
        add_action('wp_ajax_dmm_elta_delete_tracking', [$this, 'ajax_elta_delete_tracking']);
    }
    
    // ACS Courier methods
    public function ajax_acs_track_shipment() {
        $this->verify_ajax_request('dmm_acs_track');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_create_voucher() {
        $this->verify_ajax_request('dmm_acs_create');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_calculate_price() {
        $this->verify_ajax_request('dmm_acs_calculate');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_validate_address() {
        $this->verify_ajax_request('dmm_acs_validate');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_find_stations() {
        $this->verify_ajax_request('dmm_acs_find_stations');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_test_connection() {
        $this->verify_ajax_request('dmm_acs_test');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_acs_sync_shipment() {
        $this->verify_ajax_request('dmm_acs_sync');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    // Geniki methods
    public function ajax_geniki_test_connection() {
        $this->verify_ajax_request('dmm_geniki_test');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_track_shipment() {
        $this->verify_ajax_request('dmm_geniki_track');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_get_shops() {
        $this->verify_ajax_request('dmm_geniki_shops');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_create_voucher() {
        $this->verify_ajax_request('dmm_geniki_create');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_geniki_get_pdf() {
        $this->verify_ajax_request('dmm_geniki_pdf');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    // ELTA methods
    public function ajax_elta_test_connection() {
        $this->verify_ajax_request('dmm_elta_test');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_track_shipment() {
        $this->verify_ajax_request('dmm_elta_track');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_create_tracking() {
        $this->verify_ajax_request('dmm_elta_create');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_update_tracking() {
        $this->verify_ajax_request('dmm_elta_update');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
    
    public function ajax_elta_delete_tracking() {
        $this->verify_ajax_request('dmm_elta_delete');
        // TODO: Extract implementation from original file
        wp_send_json_error(['message' => 'Method not yet extracted from original file']);
    }
}


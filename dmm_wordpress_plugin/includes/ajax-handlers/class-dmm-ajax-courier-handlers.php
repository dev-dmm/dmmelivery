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
        add_action('wp_ajax_dmm_elta_create_test_voucher', [$this, 'ajax_elta_create_test_voucher']);
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
    
    /**
     * Create a test voucher for ELTA
     */
    public function ajax_elta_create_test_voucher() {
        $this->verify_ajax_request('dmm_elta_test');
        
        $options = get_option('dmm_delivery_bridge_options', []);
        
        // Check if ELTA is enabled and credentials are set
        if (empty($options['elta_enabled']) || $options['elta_enabled'] !== 'yes') {
            wp_send_json_error([
                'message' => __('ELTA integration is not enabled. Please enable it in the settings above.', 'dmm-delivery-bridge')
            ]);
        }
        
        $user_code = $options['elta_user_code'] ?? '';
        $user_pass = $options['elta_user_pass'] ?? '';
        $apost_code = $options['elta_apost_code'] ?? '';
        $api_endpoint = $options['elta_api_endpoint'] ?? 'https://customers.elta-courier.gr';
        
        if (empty($user_code) || empty($user_pass) || empty($apost_code)) {
            wp_send_json_error([
                'message' => __('ELTA credentials are not configured. Please fill in User Code, User Password, and Apost Code.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Get test data from request
        $test_data = isset($_POST['test_data']) ? $_POST['test_data'] : [];
        
        if (empty($test_data)) {
            wp_send_json_error([
                'message' => __('Test data is missing.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Sanitize test data
        $sender_name = sanitize_text_field($test_data['sender_name'] ?? '');
        $sender_address = sanitize_text_field($test_data['sender_address'] ?? '');
        $sender_city = sanitize_text_field($test_data['sender_city'] ?? '');
        $sender_postcode = sanitize_text_field($test_data['sender_postcode'] ?? '');
        $recipient_name = sanitize_text_field($test_data['recipient_name'] ?? '');
        $recipient_address = sanitize_text_field($test_data['recipient_address'] ?? '');
        $recipient_city = sanitize_text_field($test_data['recipient_city'] ?? '');
        $recipient_postcode = sanitize_text_field($test_data['recipient_postcode'] ?? '');
        $recipient_phone = sanitize_text_field($test_data['recipient_phone'] ?? '');
        
        // Validate required fields
        if (empty($sender_name) || empty($sender_address) || empty($sender_city) || 
            empty($recipient_name) || empty($recipient_address) || empty($recipient_city)) {
            wp_send_json_error([
                'message' => __('Please fill in all required fields (Sender and Recipient name, address, and city).', 'dmm-delivery-bridge')
            ]);
        }
        
        try {
            // Create ELTA API service instance
            $elta_service = new DMM_ELTA_Courier_Service($options);
            
            // Prepare voucher data
            $voucher_data = [
                'sender' => [
                    'name' => $sender_name,
                    'address' => $sender_address,
                    'city' => $sender_city,
                    'postcode' => $sender_postcode,
                ],
                'recipient' => [
                    'name' => $recipient_name,
                    'address' => $recipient_address,
                    'city' => $recipient_city,
                    'postcode' => $recipient_postcode,
                    'phone' => $recipient_phone,
                ],
            ];
            
            // Create test voucher
            $result = $elta_service->create_test_voucher($voucher_data);
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => __('Test voucher created successfully!', 'dmm-delivery-bridge'),
                    'voucher_number' => $result['voucher_number'] ?? '',
                    'details' => $result['details'] ?? []
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message'] ?? __('Failed to create test voucher.', 'dmm-delivery-bridge'),
                    'details' => $result['details'] ?? []
                ]);
            }
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(__('Error creating test voucher: %s', 'dmm-delivery-bridge'), $e->getMessage())
            ]);
        }
    }
}


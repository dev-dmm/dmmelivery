<?php
/**
 * ELTA Hellenic Post Courier Service
 * 
 * Handles API communication with ELTA Courier Web Services
 * 
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_ELTA_Courier_Service
 */
class DMM_ELTA_Courier_Service {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * API endpoint
     *
     * @var string
     */
    private $api_endpoint;
    
    /**
     * User code
     *
     * @var string
     */
    private $user_code;
    
    /**
     * User password
     *
     * @var string
     */
    private $user_pass;
    
    /**
     * Apost code
     *
     * @var string
     */
    private $apost_code;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options = []) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->api_endpoint = $this->options['elta_api_endpoint'] ?? 'https://customers.elta-courier.gr';
        $this->user_code = $this->options['elta_user_code'] ?? '';
        $this->user_pass = $this->options['elta_user_pass'] ?? '';
        $this->apost_code = $this->options['elta_apost_code'] ?? '';
    }
    
    /**
     * Create a test voucher
     * 
     * Based on ELTA Courier Web Services Integration Manual v1.2
     * 
     * @param array $voucher_data Voucher data with sender and recipient information
     * @return array Result array with 'success', 'voucher_number', 'message', and optional 'details'
     */
    public function create_test_voucher($voucher_data) {
        try {
            // Validate credentials
            if (empty($this->user_code) || empty($this->user_pass) || empty($this->apost_code)) {
                return [
                    'success' => false,
                    'message' => __('ELTA credentials are not configured.', 'dmm-delivery-bridge')
                ];
            }
            
            // Validate voucher data
            if (empty($voucher_data['sender']) || empty($voucher_data['recipient'])) {
                return [
                    'success' => false,
                    'message' => __('Sender and recipient information are required.', 'dmm-delivery-bridge')
                ];
            }
            
            $sender = $voucher_data['sender'];
            $recipient = $voucher_data['recipient'];
            
            // Prepare SOAP request payload
            // Note: This structure is based on common ELTA API patterns
            // Adjust according to the actual ELTA Web Services Integration Manual
            $soap_request = [
                'UserCode' => $this->user_code,
                'UserPass' => $this->user_pass,
                'ApostCode' => $this->apost_code,
                'Sender' => [
                    'Name' => $sender['name'] ?? '',
                    'Address' => $sender['address'] ?? '',
                    'City' => $sender['city'] ?? '',
                    'Postcode' => $sender['postcode'] ?? '',
                ],
                'Recipient' => [
                    'Name' => $recipient['name'] ?? '',
                    'Address' => $recipient['address'] ?? '',
                    'City' => $recipient['city'] ?? '',
                    'Postcode' => $recipient['postcode'] ?? '',
                    'Phone' => $recipient['phone'] ?? '',
                ],
                'TestMode' => true, // Indicate this is a test voucher
            ];
            
            // Make SOAP request to ELTA API
            $response = $this->make_soap_request('CreateVoucher', $soap_request);
            
            if ($response['success']) {
                // Extract voucher number from response
                // Adjust field names based on actual API response structure
                $voucher_number = $response['data']['VoucherNumber'] 
                    ?? $response['data']['TrackingNumber'] 
                    ?? $response['data']['ReferenceNumber'] 
                    ?? '';
                
                return [
                    'success' => true,
                    'voucher_number' => $voucher_number,
                    'message' => __('Test voucher created successfully.', 'dmm-delivery-bridge'),
                    'details' => $response['data'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message'] ?? __('Failed to create test voucher.', 'dmm-delivery-bridge'),
                    'details' => $response['data'] ?? []
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Error creating test voucher: %s', 'dmm-delivery-bridge'), $e->getMessage()),
                'details' => []
            ];
        }
    }
    
    /**
     * Get tracking details for a voucher
     * 
     * @param string $voucher_number Voucher/tracking number
     * @return array Tracking details
     */
    public function get_tracking_details($voucher_number) {
        try {
            $soap_request = [
                'UserCode' => $this->user_code,
                'UserPass' => $this->user_pass,
                'ApostCode' => $this->apost_code,
                'VoucherNumber' => $voucher_number,
            ];
            
            $response = $this->make_soap_request('GetTracking', $soap_request);
            
            return $response;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Error getting tracking details: %s', 'dmm-delivery-bridge'), $e->getMessage())
            ];
        }
    }
    
    /**
     * Make SOAP request to ELTA API
     * 
     * @param string $method SOAP method name
     * @param array $data Request data
     * @return array Response array with 'success', 'data', and optional 'message'
     */
    private function make_soap_request($method, $data) {
        try {
            // Check if SOAP extension is available
            if (!extension_loaded('soap')) {
                return [
                    'success' => false,
                    'message' => __('SOAP extension is not available. Please enable it in your PHP configuration.', 'dmm-delivery-bridge')
                ];
            }
            
            // Construct SOAP endpoint URL
            // Adjust based on actual ELTA API endpoint structure
            $soap_endpoint = rtrim($this->api_endpoint, '/') . '/WebServices.asmx?WSDL';
            
            // Create SOAP client
            $soap_options = [
                'soap_version' => SOAP_1_2,
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 30,
            ];
            
            $client = new SoapClient($soap_endpoint, $soap_options);
            
            // Call SOAP method
            $result = $client->__soapCall($method, [$data]);
            
            // Convert SOAP result to array
            $response_data = json_decode(json_encode($result), true);
            
            // Check for errors in response
            if (isset($response_data['ErrorCode']) && $response_data['ErrorCode'] != '0') {
                return [
                    'success' => false,
                    'message' => $response_data['ErrorMessage'] ?? __('ELTA API returned an error.', 'dmm-delivery-bridge'),
                    'data' => $response_data
                ];
            }
            
            return [
                'success' => true,
                'data' => $response_data,
                'message' => __('Request completed successfully.', 'dmm-delivery-bridge')
            ];
            
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'message' => sprintf(__('SOAP error: %s', 'dmm-delivery-bridge'), $e->getMessage()),
                'data' => [
                    'fault_code' => $e->getCode(),
                    'fault_string' => $e->getMessage()
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Request error: %s', 'dmm-delivery-bridge'), $e->getMessage()),
                'data' => []
            ];
        }
    }
    
    /**
     * Test API connection
     * 
     * @return array Result array with 'success' and 'message'
     */
    public function test_connection() {
        try {
            // Try to make a simple API call to test connection
            $test_request = [
                'UserCode' => $this->user_code,
                'UserPass' => $this->user_pass,
                'ApostCode' => $this->apost_code,
            ];
            
            $response = $this->make_soap_request('TestConnection', $test_request);
            
            return $response;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Connection test failed: %s', 'dmm-delivery-bridge'), $e->getMessage())
            ];
        }
    }
}


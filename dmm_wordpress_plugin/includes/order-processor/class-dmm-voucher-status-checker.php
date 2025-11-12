<?php
/**
 * Voucher Status Checker for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Voucher_Status_Checker
 * Handles checking voucher status from courier APIs
 */
class DMM_Voucher_Status_Checker {
    
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
     * Order data preparer instance (for getting courier/voucher info)
     *
     * @var DMM_Order_Data_Preparer
     */
    private $order_data_preparer;
    
    /**
     * Shipment status updater instance
     *
     * @var DMM_Shipment_Status_Updater
     */
    private $shipment_status_updater;
    
    /**
     * Constructor
     *
     * @param array                    $options Plugin options
     * @param DMM_Logger               $logger Logger instance
     * @param DMM_Order_Data_Preparer  $order_data_preparer Order data preparer instance
     * @param DMM_Shipment_Status_Updater $shipment_status_updater Shipment status updater instance
     */
    public function __construct($options, $logger, $order_data_preparer, $shipment_status_updater) {
        $this->options = $options;
        $this->logger = $logger;
        $this->order_data_preparer = $order_data_preparer;
        $this->shipment_status_updater = $shipment_status_updater;
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
    public function check_voucher_status_and_update_score($order, $response) {
        try {
            // Get voucher information from order
            $courier_voucher_info = $this->order_data_preparer->get_courier_voucher_from_order($order);
            
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
            if ($this->shipment_status_updater) {
                $this->shipment_status_updater->update_shipment_status($shipment_id, strtolower($status));
            }
            
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
}


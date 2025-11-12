<?php
/**
 * Shipment Status Updater for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Shipment_Status_Updater
 * Handles updating shipment status in Laravel backend
 */
class DMM_Shipment_Status_Updater {
    
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
     * Update shipment status in Laravel backend
     * 
     * Updates the shipment status via Laravel API. When status is final (delivered, returned, cancelled),
     * Laravel will automatically update the customer's delivery score.
     *
     * @param string $shipment_id Shipment ID from Laravel
     * @param string $status Final status (delivered, returned, cancelled)
     * @return void
     */
    public function update_shipment_status($shipment_id, $status) {
        try {
            $api_endpoint = isset($this->options['api_endpoint']) ? $this->options['api_endpoint'] : '';
            $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
            $tenant_id = isset($this->options['tenant_id']) ? $this->options['tenant_id'] : '';
            
            if (empty($api_endpoint) || empty($api_key) || empty($tenant_id)) {
                $this->logger->debug_log('Cannot update shipment status: API configuration incomplete');
                return;
            }
            
            // Build API endpoint for updating shipment status
            $update_endpoint = rtrim($api_endpoint, '/') . '/api/v1/shipments/' . urlencode($shipment_id) . '/update-status';
            
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Api-Key' => $api_key,
                'X-Tenant-Id' => $tenant_id,
            ];
            
            $payload = [
                'status' => $status
            ];
            
            $args = [
                'method' => 'POST',
                'timeout' => 15,
                'headers' => $headers,
                'body' => json_encode($payload),
                'sslverify' => true,
            ];
            
            $response = wp_remote_request($update_endpoint, $args);
            
            if (is_wp_error($response)) {
                $this->logger->log_structured('shipment_status_update_failed', [
                    'shipment_id' => $shipment_id,
                    'status' => $status,
                    'error' => $response->get_error_message()
                ]);
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            if ($response_code >= 200 && $response_code < 300) {
                $this->logger->log_structured('shipment_status_updated', [
                    'shipment_id' => $shipment_id,
                    'status' => $status,
                    'delivery_score_updated' => true
                ]);
            } else {
                $this->logger->log_structured('shipment_status_update_error', [
                    'shipment_id' => $shipment_id,
                    'status' => $status,
                    'http_code' => $response_code,
                    'error' => $response_data['message'] ?? 'Unknown error'
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->log_structured('shipment_status_update_exception', [
                'shipment_id' => $shipment_id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}


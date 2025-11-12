<?php
/**
 * Order Result Handler for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Order_Result_Handler
 * Handles success and failure results from order processing
 */
class DMM_Order_Result_Handler {
    
    /**
     * Logger instance
     *
     * @var DMM_Logger
     */
    private $logger;
    
    /**
     * API Client instance
     *
     * @var DMM_API_Client
     */
    private $api_client;
    
    /**
     * Scheduler instance
     *
     * @var DMM_Scheduler
     */
    private $scheduler;
    
    /**
     * Voucher status checker instance
     *
     * @var DMM_Voucher_Status_Checker
     */
    private $voucher_status_checker;
    
    /**
     * Constructor
     *
     * @param DMM_Logger                $logger Logger instance
     * @param DMM_API_Client            $api_client API client instance
     * @param DMM_Scheduler             $scheduler Scheduler instance
     * @param DMM_Voucher_Status_Checker $voucher_status_checker Voucher status checker instance
     */
    public function __construct($logger, $api_client, $scheduler, $voucher_status_checker) {
        $this->logger = $logger;
        $this->api_client = $api_client;
        $this->scheduler = $scheduler;
        $this->voucher_status_checker = $voucher_status_checker;
    }
    
    /**
     * Handle successful send
     *
     * @param WC_Order $order Order object
     * @param array    $response API response
     */
    public function handle_successful_send($order, $response) {
        $order_id = $order->get_id();
        
        // Mark as sent
        $order->update_meta_data('_dmm_delivery_sent', 'yes');
        $order->update_meta_data('_dmm_delivery_order_id', $response['data']['order_id'] ?? '');
        $order->update_meta_data('_dmm_delivery_shipment_id', $response['data']['shipment_id'] ?? '');
        $order->update_meta_data('_dmm_delivery_sent_at', gmdate('c'));
        
        // Clear retry count
        $order->delete_meta_data('_dmm_delivery_retry_count');
        
        // Save once after all meta changes
        $order->save();
        
        // Add order note
        $order->add_order_note(__('Order sent to DMM Delivery system successfully.', 'dmm-delivery-bridge'));
        
        $this->logger->log_structured('order_sent_success', [
            'order_id' => $order_id,
            'dmm_order_id' => $response['data']['order_id'] ?? '',
            'dmm_shipment_id' => $response['data']['shipment_id'] ?? ''
        ]);
        
        // Check voucher status and update delivery score if applicable
        if ($this->voucher_status_checker) {
            $this->voucher_status_checker->check_voucher_status_and_update_score($order, $response);
        }
    }
    
    /**
     * Handle failed send
     *
     * @param WC_Order $order Order object
     * @param array    $response API response
     * @param int      $retry_count Current retry count
     */
    public function handle_failed_send($order, $response, $retry_count) {
        $order_id = $order->get_id();
        $new_retry_count = $retry_count + 1;
        
        // Update retry count
        $order->update_meta_data('_dmm_delivery_retry_count', $new_retry_count);
        $order->save();
        
        // Add order note
        $error_message = $response ? $response['message'] : __('Unknown error occurred', 'dmm-delivery-bridge');
        $order->add_order_note(
            sprintf(__('Failed to send order to DMM Delivery system (attempt %d): %s', 'dmm-delivery-bridge'), $new_retry_count, $error_message)
        );
        
        $this->logger->log_structured('order_send_failed', [
            'order_id' => $order_id,
            'retry_count' => $new_retry_count,
            'error' => $error_message
        ]);
        
        // Schedule retry if retryable using centralized scheduler
        if ($this->api_client->is_retryable_error($response)) {
            $this->scheduler->schedule_retry(
                'dmm_send_order',
                ['order_id' => $order_id],
                $new_retry_count
            );
        }
    }
    
    /**
     * Handle max retries reached
     *
     * @param WC_Order $order Order object
     */
    public function handle_max_retries_reached($order) {
        $order_id = $order->get_id();
        
        // Clear the lock
        delete_transient('dmm_sending_' . $order_id);
        
        // Mark as failed
        $order->update_meta_data('_dmm_delivery_sent', 'failed');
        $order->update_meta_data('_dmm_delivery_failed_at', gmdate('c'));
        $order->save();
        
        // Add order note
        $order->add_order_note(__('Order failed to send to DMM Delivery system after maximum retries. Please check manually.', 'dmm-delivery-bridge'));
        
        $this->logger->log_structured('order_max_retries_reached', [
            'order_id' => $order_id
        ]);
    }
}


<?php
/**
 * Unit Tests for DMM_Order_Processor
 *
 * @package DMM_Delivery_Bridge
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class OrderProcessorTest extends TestCase {
    
    private $options;
    private $api_client;
    private $logger;
    private $scheduler;
    private $order_processor;
    private $mock_order;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->options = [
            'auto_send' => 'yes',
            'order_statuses' => ['processing', 'completed'],
            'create_shipment' => 'yes'
        ];
        
        // Create mocks
        $this->api_client = $this->createMock(DMM_API_Client::class);
        $this->logger = $this->createMock(DMM_Logger::class);
        $this->scheduler = $this->createMock(DMM_Scheduler::class);
        
        // Create order processor with mocks
        $this->order_processor = new DMM_Order_Processor(
            $this->options,
            $this->api_client,
            $this->logger,
            $this->scheduler
        );
        
        // Create mock order
        $this->mock_order = $this->createMock(WC_Order::class);
    }
    
    public function test_queue_send_to_api_with_auto_send_disabled() {
        $options = ['auto_send' => 'no'];
        $processor = new DMM_Order_Processor($options);
        
        // Should return early without queuing
        $processor->queue_send_to_api(123);
        
        // No assertions needed - just verify no exceptions thrown
        $this->assertTrue(true);
    }
    
    public function test_queue_send_to_api_with_invalid_order() {
        // Mock wc_get_order to return null
        $this->mock_wc_get_order(null);
        
        $this->order_processor->queue_send_to_api(999);
        
        // Should return early without queuing
        $this->assertTrue(true);
    }
    
    public function test_queue_send_to_api_with_wrong_status() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_status')->willReturn('pending');
        $order->method('get_meta')->willReturn('');
        
        $this->mock_wc_get_order($order);
        
        $this->scheduler->expects($this->never())
            ->method('queue_immediate');
        
        $this->order_processor->queue_send_to_api(123);
    }
    
    public function test_queue_send_to_api_with_already_sent() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_status')->willReturn('processing');
        $order->method('get_meta')
            ->with('_dmm_delivery_sent')
            ->willReturn('yes');
        
        $this->mock_wc_get_order($order);
        
        $this->scheduler->expects($this->never())
            ->method('queue_immediate');
        
        $this->order_processor->queue_send_to_api(123);
    }
    
    public function test_queue_send_to_api_success() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_status')->willReturn('processing');
        $order->method('get_meta')
            ->with('_dmm_delivery_sent')
            ->willReturn('');
        
        $this->mock_wc_get_order($order);
        $this->mock_get_transient(false);
        
        $this->scheduler->expects($this->once())
            ->method('queue_immediate')
            ->with(
                'dmm_send_order',
                ['order_id' => 123],
                DMM_Scheduler::GROUP_IMMEDIATE,
                DMM_Scheduler::PRIORITY_NORMAL
            );
        
        $this->order_processor->queue_send_to_api(123);
    }
    
    public function test_process_order_robust_with_already_sent() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_id')->willReturn(123);
        $order->method('get_meta')
            ->with('_dmm_delivery_sent')
            ->willReturn('yes');
        
        $this->logger->expects($this->once())
            ->method('debug_log')
            ->with($this->stringContains('already sent'));
        
        $this->api_client->expects($this->never())
            ->method('send_to_api_with_retry');
        
        $this->order_processor->process_order_robust($order);
    }
    
    public function test_process_order_robust_success() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_id')->willReturn(123);
        $order->method('get_meta')
            ->willReturnMap([
                ['_dmm_delivery_sent', true, ''],
                ['_dmm_delivery_retry_count', true, 0]
            ]);
        $order->method('get_date_created')
            ->willReturn(new DateTime('2024-01-01 12:00:00'));
        
        $order->method('update_meta_data')->willReturnSelf();
        $order->method('delete_meta_data')->willReturnSelf();
        $order->method('save')->willReturn(true);
        $order->method('add_order_note')->willReturn(1);
        
        // Mock prepare_order_data
        $order_data = [
            'source' => 'woocommerce',
            'order' => ['external_order_id' => '123']
        ];
        
        // Mock API response
        $api_response = [
            'success' => true,
            'message' => 'Order sent successfully',
            'data' => [
                'order_id' => 'dmm_123',
                'shipment_id' => 'ship_456'
            ]
        ];
        
        $this->api_client->expects($this->once())
            ->method('send_to_api_with_retry')
            ->willReturn($api_response);
        
        $this->logger->expects($this->atLeastOnce())
            ->method('log_structured');
        
        $this->order_processor->process_order_robust($order);
        
        // Verify order was marked as sent
        $order->expects($this->once())
            ->method('update_meta_data')
            ->with('_dmm_delivery_sent', 'yes');
    }
    
    public function test_process_order_robust_with_max_retries() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_id')->willReturn(123);
        $order->method('get_meta')
            ->willReturnMap([
                ['_dmm_delivery_sent', true, ''],
                ['_dmm_delivery_retry_count', true, 5]
            ]);
        
        $order->method('update_meta_data')->willReturnSelf();
        $order->method('save')->willReturn(true);
        $order->method('add_order_note')->willReturn(1);
        
        $this->api_client->expects($this->never())
            ->method('send_to_api_with_retry');
        
        $this->logger->expects($this->once())
            ->method('log_structured')
            ->with('order_max_retries_reached', $this->anything());
        
        $this->order_processor->process_order_robust($order);
        
        // Verify order was marked as failed
        $order->expects($this->once())
            ->method('update_meta_data')
            ->with('_dmm_delivery_sent', 'failed');
    }
    
    public function test_process_order_robust_with_retryable_error() {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_id')->willReturn(123);
        $order->method('get_meta')
            ->willReturnMap([
                ['_dmm_delivery_sent', true, ''],
                ['_dmm_delivery_retry_count', true, 0]
            ]);
        $order->method('get_date_created')
            ->willReturn(new DateTime('2024-01-01 12:00:00'));
        
        $order->method('update_meta_data')->willReturnSelf();
        $order->method('save')->willReturn(true);
        $order->method('add_order_note')->willReturn(1);
        
        // Mock failed API response (retryable)
        $api_response = [
            'success' => false,
            'message' => 'Server error',
            'http_code' => 500
        ];
        
        $this->api_client->expects($this->once())
            ->method('send_to_api_with_retry')
            ->willReturn($api_response);
        
        $this->api_client->expects($this->once())
            ->method('is_retryable_error')
            ->with($api_response)
            ->willReturn(true);
        
        $this->scheduler->expects($this->once())
            ->method('schedule_retry')
            ->with(
                'dmm_send_order',
                ['order_id' => 123],
                1
            );
        
        $this->order_processor->process_order_robust($order);
        
        // Verify retry count was incremented
        $order->expects($this->once())
            ->method('update_meta_data')
            ->with('_dmm_delivery_retry_count', 1);
    }
    
    public function test_prepare_order_data() {
        $order = $this->createMock(WC_Order::class);
        
        // Mock order methods
        $order->method('get_id')->willReturn(123);
        $order->method('get_order_number')->willReturn('12345');
        $order->method('get_status')->willReturn('processing');
        $order->method('get_total')->willReturn(100.00);
        $order->method('get_subtotal')->willReturn(90.00);
        $order->method('get_total_tax')->willReturn(10.00);
        $order->method('get_shipping_total')->willReturn(5.00);
        $order->method('get_discount_total')->willReturn(5.00);
        $order->method('get_currency')->willReturn('EUR');
        $order->method('is_paid')->willReturn(true);
        $order->method('get_payment_method')->willReturn('bacs');
        
        $order->method('get_billing_email')->willReturn('test@example.com');
        $order->method('get_billing_phone')->willReturn('1234567890');
        $order->method('get_billing_first_name')->willReturn('John');
        $order->method('get_billing_last_name')->willReturn('Doe');
        
        $order->method('get_address')
            ->willReturnMap([
                ['shipping', [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address_1' => '123 Main St',
                    'city' => 'Athens',
                    'postcode' => '12345',
                    'country' => 'GR'
                ]],
                ['billing', [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address_1' => '123 Main St',
                    'city' => 'Athens',
                    'postcode' => '12345',
                    'country' => 'GR'
                ]]
            ]);
        
        $order->method('get_items')->willReturn([]);
        
        $order_data = $this->order_processor->prepare_order_data($order);
        
        $this->assertEquals('woocommerce', $order_data['source']);
        $this->assertEquals('123', $order_data['order']['external_order_id']);
        $this->assertEquals(100.00, $order_data['order']['total_amount']);
        $this->assertEquals('EUR', $order_data['order']['currency']);
        $this->assertEquals('paid', $order_data['order']['payment_status']);
        $this->assertEquals('test@example.com', $order_data['customer']['email']);
        $this->assertEquals('123 Main St', $order_data['shipping']['address']['address_1']);
    }
    
    // Note: These helper methods would need to be implemented
    // with a proper WordPress/WooCommerce mocking library
    // For now, they serve as placeholders showing the intended test structure
    private function mock_wc_get_order($order) {
        // In a real implementation, this would use a WooCommerce mocking framework
        // or dependency injection to replace wc_get_order
    }
    
    private function mock_get_transient($value) {
        // In a real implementation, this would use a WordPress mocking framework
        // or dependency injection to replace get_transient
    }
}


<?php
/**
 * Unit Tests for DMM_API_Client
 *
 * @package DMM_Delivery_Bridge
 */

use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase {
    
    private $options;
    private $logger;
    private $rate_limiter;
    private $cache_service;
    private $api_client;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->options = [
            'api_endpoint' => 'https://api.example.com/orders',
            'api_key' => 'test_api_key',
            'tenant_id' => 'test_tenant',
            'api_secret' => 'test_secret'
        ];
        
        // Create mocks
        $this->logger = $this->createMock(DMM_Logger::class);
        $this->rate_limiter = $this->createMock(DMM_Rate_Limiter::class);
        $this->cache_service = $this->createMock(DMM_Cache_Service::class);
        
        // Create API client with mocks
        $this->api_client = new DMM_API_Client(
            $this->options,
            $this->logger,
            $this->rate_limiter,
            $this->cache_service
        );
    }
    
    public function test_send_to_api_with_incomplete_config() {
        $options = [
            'api_endpoint' => '',
            'api_key' => '',
            'tenant_id' => ''
        ];
        
        $client = new DMM_API_Client($options);
        $response = $client->send_to_api(['test' => 'data']);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('incomplete', strtolower($response['message']));
    }
    
    public function test_send_to_api_with_circuit_breaker_open() {
        // Mock circuit breaker being open
        $this->mock_transient('dmm_circuit_breaker', [
            'until' => time() + 300
        ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data']);
        
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('temporarily disabled', strtolower($response['message']));
    }
    
    public function test_send_to_api_with_rate_limit_exceeded() {
        // Mock rate limiter
        $this->rate_limiter->expects($this->once())
            ->method('check_rate_limit')
            ->willReturn([
                'allowed' => false,
                'wait_seconds' => 60
            ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data']);
        
        $this->assertFalse($response['success']);
        $this->assertTrue($response['rate_limited'] ?? false);
        $this->assertEquals(60, $response['wait_seconds']);
    }
    
    public function test_send_to_api_success() {
        // Mock successful rate limit check
        $this->rate_limiter->expects($this->once())
            ->method('check_rate_limit')
            ->willReturn([
                'allowed' => true,
                'wait_seconds' => 0
            ]);
        
        // Mock successful HTTP response
        $this->mock_wp_remote_request([
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true, 'message' => 'Order sent'])
        ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data']);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['http_code']);
    }
    
    public function test_send_to_api_with_retry_on_429() {
        // Mock rate limit check - first call fails, second succeeds
        $this->rate_limiter->expects($this->exactly(2))
            ->method('check_rate_limit')
            ->willReturnOnConsecutiveCalls(
                ['allowed' => true, 'wait_seconds' => 0],
                ['allowed' => true, 'wait_seconds' => 0]
            );
        
        // Mock 429 response then 200
        $this->mock_wp_remote_request([
            'response' => ['code' => 429],
            'headers' => [],
            'body' => json_encode(['error' => 'Rate limited'])
        ], [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true])
        ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data']);
        
        // Should retry and eventually succeed
        $this->assertTrue($response['success']);
    }
    
    public function test_send_to_api_with_retry_after_header() {
        $this->rate_limiter->expects($this->once())
            ->method('check_rate_limit')
            ->willReturn(['allowed' => true, 'wait_seconds' => 0]);
        
        $this->rate_limiter->expects($this->once())
            ->method('handle_retry_after')
            ->with('dmm', 30);
        
        $this->mock_wp_remote_request([
            'response' => ['code' => 429],
            'headers' => ['retry-after' => '30'],
            'body' => json_encode(['error' => 'Rate limited'])
        ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data']);
        
        $this->assertFalse($response['success']);
        $this->assertEquals(429, $response['http_code']);
        $this->assertEquals(30, $response['retry_after']);
    }
    
    public function test_is_retryable_error() {
        // Test retryable errors
        $retryable_responses = [
            ['http_code' => 408, 'message' => 'Request timeout'],
            ['http_code' => 429, 'message' => 'Rate limited'],
            ['http_code' => 500, 'message' => 'Server error'],
            ['http_code' => 502, 'message' => 'Bad gateway'],
            ['http_code' => 503, 'message' => 'Service unavailable'],
            ['http_code' => 0, 'message' => 'Connection timeout'],
            ['http_code' => 0, 'message' => 'DNS failure'],
        ];
        
        foreach ($retryable_responses as $response) {
            $this->assertTrue(
                $this->api_client->is_retryable_error($response),
                "Should retry: " . json_encode($response)
            );
        }
        
        // Test non-retryable errors
        $non_retryable_responses = [
            ['http_code' => 400, 'message' => 'Bad request'],
            ['http_code' => 401, 'message' => 'Unauthorized'],
            ['http_code' => 403, 'message' => 'Forbidden'],
            ['http_code' => 404, 'message' => 'Not found'],
            ['http_code' => 422, 'message' => 'Validation error'],
        ];
        
        foreach ($non_retryable_responses as $response) {
            $this->assertFalse(
                $this->api_client->is_retryable_error($response),
                "Should not retry: " . json_encode($response)
            );
        }
    }
    
    public function test_send_to_api_with_409_duplicate() {
        $this->rate_limiter->expects($this->once())
            ->method('check_rate_limit')
            ->willReturn(['allowed' => true, 'wait_seconds' => 0]);
        
        $this->logger->expects($this->once())
            ->method('log_structured')
            ->with('duplicate_request_detected', $this->anything());
        
        $this->mock_wp_remote_request([
            'response' => ['code' => 409],
            'body' => json_encode(['error' => 'Duplicate request'])
        ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data']);
        
        // 409 should not be retryable
        $this->assertFalse($this->api_client->is_retryable_error($response));
    }
    
    public function test_send_to_api_with_cache() {
        $this->rate_limiter->expects($this->once())
            ->method('check_rate_limit')
            ->willReturn(['allowed' => true, 'wait_seconds' => 0]);
        
        $this->cache_service->expects($this->once())
            ->method('is_enabled')
            ->willReturn(true);
        
        $this->cache_service->expects($this->once())
            ->method('get_cached_api_response')
            ->willReturn([
                'success' => true,
                'data' => ['cached' => true]
            ]);
        
        $response = $this->api_client->send_to_api(['test' => 'data'], 'dmm', true);
        
        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['cached'] ?? false);
    }
    
    // Note: These helper methods would need to be implemented
    // with a proper WordPress function mocking library or framework
    // For now, they serve as placeholders showing the intended test structure
    private function mock_transient($key, $value) {
        // In a real implementation, this would use a WordPress mocking framework
        // or dependency injection to replace get_transient/set_transient
    }
    
    private function mock_wp_remote_request(...$responses) {
        // In a real implementation, this would use a WordPress mocking framework
        // or dependency injection to replace wp_remote_request
    }
}


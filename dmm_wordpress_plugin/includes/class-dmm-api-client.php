<?php
/**
 * API Client class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_API_Client
 */
class DMM_API_Client {
    
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
     * Rate limiter instance
     *
     * @var DMM_Rate_Limiter
     */
    private $rate_limiter;
    
    /**
     * Cache service instance
     *
     * @var DMM_Cache_Service
     */
    private $cache_service;
    
    /**
     * Performance monitor instance
     *
     * @var DMM_Performance_Monitor
     */
    private $performance_monitor;
    
    /**
     * Constructor
     *
     * @param array                    $options Plugin options
     * @param DMM_Logger              $logger Logger instance
     * @param DMM_Rate_Limiter        $rate_limiter Rate limiter instance
     * @param DMM_Cache_Service       $cache_service Cache service instance
     * @param DMM_Performance_Monitor $performance_monitor Performance monitor instance
     */
    public function __construct($options = [], $logger = null, $rate_limiter = null, $cache_service = null, $performance_monitor = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->logger = $logger ?: new DMM_Logger($this->options);
        $this->rate_limiter = $rate_limiter ?: new DMM_Rate_Limiter($this->options, $this->logger);
        $this->cache_service = $cache_service ?: new DMM_Cache_Service($this->options, $this->logger);
        $this->performance_monitor = $performance_monitor;
    }
    
    /**
     * Send data to DMM Delivery API
     * 
     * This is the main method for communicating with the DMM Delivery API. It handles:
     * - API configuration validation
     * - Circuit breaker checks (prevents API calls during high error rates)
     * - Rate limiting (token bucket algorithm)
     * - Request signing (HMAC-SHA256 if secret is configured)
     * - HTTP method selection (POST for new orders, PUT for updates)
     * - Response caching (for GET requests)
     * - Retry logic for rate-limited requests (HTTP 429)
     * - Error handling and logging
     * 
     * The method automatically determines HTTP method based on the 'sync_update' flag
     * in the data array. POST is used for new orders, PUT for updates.
     *
     * @param array  $data Order data to send (must include 'order', 'customer', 'shipping' keys)
     * @param string $courier Courier identifier for rate limiting (default: 'dmm')
     * @param bool   $use_cache Whether to use cache for GET requests (default: true)
     * @return array Response array with keys:
     *               - 'success' (bool): Whether the request succeeded
     *               - 'message' (string): Human-readable message
     *               - 'data' (array|null): Response data from API
     *               - 'http_code' (int): HTTP status code
     *               - 'rate_limited' (bool, optional): True if rate limited
     *               - 'wait_seconds' (int, optional): Seconds to wait if rate limited
     * @since 1.0.0
     */
    public function send_to_api($data, $courier = 'dmm', $use_cache = true) {
        $api_endpoint = isset($this->options['api_endpoint']) ? $this->options['api_endpoint'] : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $tenant_id = isset($this->options['tenant_id']) ? $this->options['tenant_id'] : '';
        
        // Debug: Log the API endpoint being used (never log actual API keys)
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log('API Endpoint: ' . $api_endpoint);
            $this->logger->debug_log('API Key: ' . (empty($api_key) ? 'EMPTY' : 'SET'));
            $this->logger->debug_log('Tenant ID: ' . (empty($tenant_id) ? 'EMPTY' : 'SET'));
        }
        
        if (empty($api_endpoint) || empty($api_key) || empty($tenant_id)) {
            return [
                'success' => false,
                'message' => __('API configuration is incomplete.', 'dmm-delivery-bridge'),
                'data' => null
            ];
        }
        
        // Check circuit breaker
        if (!$this->check_circuit_breaker()) {
            return [
                'success' => false,
                'message' => __('API calls are temporarily disabled due to high error rate.', 'dmm-delivery-bridge'),
                'data' => null
            ];
        }
        
        // Check rate limit before making request
        $rate_check = $this->rate_limiter->check_rate_limit($courier, 1);
        if (!$rate_check['allowed']) {
            // Try to wait if wait time is reasonable (max 10 seconds)
            if ($rate_check['wait_seconds'] > 0 && $rate_check['wait_seconds'] <= 10) {
                $waited = $this->rate_limiter->wait_for_rate_limit($courier, 1);
                if (!$waited) {
                    return [
                        'success' => false,
                        'message' => sprintf(__('Rate limit exceeded. Please wait %d seconds before retrying.', 'dmm-delivery-bridge'), $rate_check['wait_seconds']),
                        'data' => null,
                        'rate_limited' => true,
                        'wait_seconds' => $rate_check['wait_seconds']
                    ];
                }
            } else {
                // Wait time too long, return error
                return [
                    'success' => false,
                    'message' => sprintf(__('Rate limit exceeded. Please wait %d seconds before retrying.', 'dmm-delivery-bridge'), $rate_check['wait_seconds']),
                    'data' => null,
                    'rate_limited' => true,
                    'wait_seconds' => $rate_check['wait_seconds']
                ];
            }
        }
        
        // Determine HTTP method based on sync flag
        $method = isset($data['sync_update']) && $data['sync_update'] ? 'PUT' : 'POST';
        
        // Check cache for GET requests (if applicable)
        if ($method === 'GET' && $use_cache && $this->cache_service->is_enabled()) {
            $cache_key = $this->generate_cache_key($api_endpoint, $data, $courier);
            $cached_response = $this->cache_service->get_cached_api_response($cache_key);
            
            if ($cached_response !== false) {
                if ($this->logger->is_debug_mode()) {
                    $this->logger->debug_log("Using cached API response for: {$cache_key}");
                }
                return $cached_response;
            }
        }
        
        // Add idempotency key if present
        $headers = [
            'Content-Type' => 'application/json',
            'X-Api-Key' => $api_key,
            'X-Tenant-Id' => $tenant_id,
        ];
        
        if (isset($data['idempotency_key'])) {
            $headers['X-Idempotency-Key'] = $data['idempotency_key'];
        }
        
        // Add payload signature for security
        $payload = json_encode($data);
        $signature = $this->sign_payload($payload);
        if ($signature) {
            $headers['X-Payload-Signature'] = $signature;
        }
        
        $args = [
            'method' => $method,
            'timeout' => 20, // Total timeout
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => $headers,
            'body' => $payload,
            'cookies' => [],
            'user-agent' => 'DMM-Delivery-Bridge/' . DMM_DELIVERY_BRIDGE_VERSION . ' (WordPress/' . get_bloginfo('version') . ')',
            'sslverify' => true,
        ];
        
        // Debug: Log the request details (sanitize headers to avoid exposing sensitive data)
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log('Making request to: ' . $api_endpoint);
            
            // Sanitize headers for logging (don't log actual API keys)
            $sanitized_headers = $args['headers'];
            if (isset($sanitized_headers['X-Api-Key'])) {
                $sanitized_headers['X-Api-Key'] = '***REDACTED***';
            }
            $this->logger->debug_data('Request headers', $sanitized_headers);
        }
        
        // Track API response time
        $start_time = microtime(true);
        $response = wp_remote_request($api_endpoint, $args);
        $response_time = microtime(true) - $start_time;
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->debug_log('wp_remote_request error: ' . $error_message);
            
            // Check for retryable errors and open circuit breaker if needed
            $this->check_and_open_circuit_breaker($error_message);
            
            return [
                'success' => false,
                'message' => $error_message,
                'data' => null
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_headers = wp_remote_retrieve_headers($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Track API performance
        if ($this->performance_monitor) {
            $this->performance_monitor->track_api_response(
                $api_endpoint,
                $response_time,
                $response_code,
                $courier,
                $response_code >= 200 && $response_code < 300
            );
        }
        
        // Debug: Log the response details
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log('Response Code: ' . $response_code);
            $this->logger->debug_log('Response Time: ' . number_format($response_time, 4) . 's');
            $this->logger->debug_data('Response Body', $response_data ?: $response_body);
        }
        
        // Handle rate limiting (HTTP 429) with Retry-After header support
        if ($response_code === 429) {
            // Check for Retry-After header
            $retry_after = 0;
            if (isset($response_headers['retry-after'])) {
                $retry_after = (int) $response_headers['retry-after'];
            } elseif (isset($response_headers['Retry-After'])) {
                $retry_after = (int) $response_headers['Retry-After'];
            }
            
            // Handle Retry-After header
            if ($retry_after > 0) {
                $this->rate_limiter->handle_retry_after($courier, $retry_after);
                
                return [
                    'success' => false,
                    'message' => sprintf(__('Rate limited. Please retry after %d seconds.', 'dmm-delivery-bridge'), $retry_after),
                    'data' => null,
                    'rate_limited' => true,
                    'retry_after' => $retry_after,
                    'http_code' => 429
                ];
            }
            
            // Fallback: Wait 5 seconds and retry once (if no Retry-After header)
            sleep(5);
            
            // Check rate limit again before retry
            $rate_check = $this->rate_limiter->check_rate_limit($courier, 1);
            if (!$rate_check['allowed'] && $rate_check['wait_seconds'] > 0) {
                return [
                    'success' => false,
                    'message' => sprintf(__('Rate limit exceeded. Please wait %d seconds before retrying.', 'dmm-delivery-bridge'), $rate_check['wait_seconds']),
                    'data' => null,
                    'rate_limited' => true,
                    'wait_seconds' => $rate_check['wait_seconds'],
                    'http_code' => 429
                ];
            }
            
            // Track retry API response time
            $retry_start_time = microtime(true);
            $retry_response = wp_remote_request($api_endpoint, $args);
            $retry_response_time = microtime(true) - $retry_start_time;
            
            if (is_wp_error($retry_response)) {
                return [
                    'success' => false,
                    'message' => $retry_response->get_error_message(),
                    'data' => null,
                    'http_code' => 429
                ];
            }
            
            $retry_code = wp_remote_retrieve_response_code($retry_response);
            $retry_body = wp_remote_retrieve_body($retry_response);
            $retry_data = json_decode($retry_body, true);
            
            // Track retry API performance
            if ($this->performance_monitor) {
                $this->performance_monitor->track_api_response(
                    $api_endpoint . ' (retry)',
                    $retry_response_time,
                    $retry_code,
                    $courier,
                    $retry_code >= 200 && $retry_code < 300
                );
            }
            
            if ($retry_code >= 200 && $retry_code < 300) {
                return [
                    'success' => true,
                    'message' => $retry_data['message'] ?? __('Order sent successfully (after retry).', 'dmm-delivery-bridge'),
                    'data' => $retry_data,
                    'http_code' => $retry_code
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $retry_data['message'] ?? sprintf(__('HTTP Error: %d (after retry)', 'dmm-delivery-bridge'), $retry_code),
                    'data' => $retry_data,
                    'http_code' => $retry_code
                ];
            }
        }
        
        // Check for server errors and open circuit breaker if needed
        if ($response_code >= 500) {
            $this->check_and_open_circuit_breaker("Server error: {$response_code}");
        }
        
        // Check for Retry-After header in successful responses (some APIs send it proactively)
        if (isset($response_headers['retry-after']) || isset($response_headers['Retry-After'])) {
            $retry_after = isset($response_headers['retry-after']) 
                ? (int) $response_headers['retry-after'] 
                : (int) $response_headers['Retry-After'];
            
            if ($retry_after > 0) {
                // Log proactive Retry-After header
                $this->logger->debug_log("Received Retry-After header: {$retry_after} seconds");
            }
        }
        
        if ($response_code >= 200 && $response_code < 300) {
            $result = [
                'success' => true,
                'message' => $response_data['message'] ?? __('Order sent successfully.', 'dmm-delivery-bridge'),
                'data' => $response_data,
                'http_code' => $response_code
            ];
            
            // Cache successful responses (only for GET requests or if explicitly cacheable)
            if ($method === 'GET' && $use_cache && $this->cache_service->is_enabled()) {
                $cache_key = $this->generate_cache_key($api_endpoint, $data, $courier);
                $this->cache_service->cache_api_response($cache_key, $result);
            }
            
            return $result;
        } else {
            // For server errors (500+), try to extract more detailed error information
            $error_message = sprintf(__('HTTP Error: %d', 'dmm-delivery-bridge'), $response_code);
            
            if ($response_code >= 500) {
                // Try to get detailed error from response body
                if (is_array($response_data)) {
                    if (isset($response_data['error'])) {
                        $error_message = $response_data['error'];
                    } elseif (isset($response_data['message'])) {
                        $error_message = $response_data['message'];
                    } elseif (isset($response_data['errors'])) {
                        // Laravel-style validation errors
                        if (is_array($response_data['errors'])) {
                            $error_message = __('Server Error: ', 'dmm-delivery-bridge') . wp_json_encode($response_data['errors'], JSON_UNESCAPED_UNICODE);
                        } else {
                            $error_message = $response_data['errors'];
                        }
                    }
                } elseif (!empty($response_body) && !is_array($response_data)) {
                    // If response body is not JSON, include it in the error
                    $error_message = sprintf(__('HTTP Error: %d - %s', 'dmm-delivery-bridge'), $response_code, substr(strip_tags($response_body), 0, 200));
                }
                
                // Log full response for debugging
                if ($this->logger) {
                    $this->logger->log("API Server Error {$response_code}: " . (is_array($response_data) ? wp_json_encode($response_data) : $response_body), 'error');
                }
            } elseif ($response_code >= 400 && $response_code < 500) {
                // Client errors - try to get validation errors
                if (is_array($response_data)) {
                    if (isset($response_data['message'])) {
                        $error_message = $response_data['message'];
                    } elseif (isset($response_data['error'])) {
                        $error_message = $response_data['error'];
                    }
                }
            }
            
            return [
                'success' => false,
                'message' => $error_message,
                'data' => $response_data,
                'http_code' => $response_code,
                'response_body' => $response_body // Include raw response body for debugging
            ];
        }
    }
    
    /**
     * Generate cache key for API request
     *
     * @param string $endpoint API endpoint
     * @param array  $data Request data
     * @param string $courier Courier identifier
     * @return string Cache key
     */
    private function generate_cache_key($endpoint, $data, $courier) {
        // Create a unique key based on endpoint, data, and courier
        $key_data = [
            'endpoint' => $endpoint,
            'data' => $data,
            'courier' => $courier
        ];
        
        // Remove idempotency key from cache key (it changes per request)
        if (isset($key_data['data']['idempotency_key'])) {
            unset($key_data['data']['idempotency_key']);
        }
        
        return md5(serialize($key_data));
    }
    
    /**
     * Send to API with retry logic
     * 
     * Wrapper around send_to_api() that handles retry scheduling for retryable errors.
     * If the request fails with a retryable error, it will be scheduled for retry
     * by the order processor using exponential backoff.
     * 
     * Retryable errors include:
     * - HTTP 408 (Request Timeout)
     * - HTTP 429 (Too Many Requests)
     * - HTTP 5xx (Server Errors)
     * - Network errors (timeouts, DNS failures, connection resets)
     * 
     * Non-retryable errors (400, 401, 403, 404, 422) are not retried.
     *
     * @param array $order_data Order data to send
     * @param int   $retry_count Current retry attempt number (0 = first attempt)
     * @return array Response array (same format as send_to_api())
     * @since 1.0.0
     */
    public function send_to_api_with_retry($order_data, $retry_count) {
        $response = $this->send_to_api($order_data);
        
        // If failed and retryable, schedule retry
        if (!$response['success'] && $this->is_retryable_error($response)) {
            // Retry will be scheduled by the order processor
        }
        
        return $response;
    }
    
    /**
     * Determine if an API error is retryable
     * 
     * Analyzes the response to determine if the error is transient and should be retried.
     * Uses HTTP status codes and error message patterns to make this determination.
     * 
     * Retryable errors:
     * - HTTP 408 (Request Timeout)
     * - HTTP 429 (Too Many Requests / Rate Limited)
     * - HTTP 5xx (Server Errors: 500, 502, 503, 504, etc.)
     * - Network errors: timeouts, DNS failures, connection resets
     * 
     * Non-retryable errors:
     * - HTTP 400 (Bad Request) - client error, won't succeed on retry
     * - HTTP 401 (Unauthorized) - authentication issue
     * - HTTP 403 (Forbidden) - permission issue
     * - HTTP 404 (Not Found) - resource doesn't exist
     * - HTTP 422 (Unprocessable Entity) - validation error
     * - HTTP 409 (Conflict) - duplicate request (handled specially)
     *
     * @param array $response Response array from send_to_api()
     * @return bool True if error is retryable, false otherwise
     * @since 1.0.0
     */
    public function is_retryable_error($response) {
        $http_code = $response['http_code'] ?? 0;
        $message = strtolower($response['message'] ?? '');
        
        // Retry on: 408/429/5xx, cURL timeouts, DNS failures, connection reset
        if (in_array($http_code, [408, 429]) || $http_code >= 500) {
            return true;
        }
        
        // Don't retry on: 400/401/403/404/422 (client errors)
        if (in_array($http_code, [400, 401, 403, 404, 422])) {
            return false;
        }
        
        // If 409 duplicate from server and idempotency key matches → mark as sent
        if ($http_code === 409) {
            $this->logger->log_structured('duplicate_request_detected', [
                'order_id' => $response['order_id'] ?? 'unknown',
                'http_code' => $http_code
            ]);
            return false; // Don't retry, but handle as success
        }
        
        // Check for retryable error patterns
        $retryable_patterns = [
            'timeout',
            'connection_error',
            'server_error',
            'rate_limited',
            'dns',
            'connection reset',
            'network unreachable'
        ];
        
        foreach ($retryable_patterns as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sign payload with HMAC
     *
     * @param string $payload JSON payload
     * @return string|null Signature or null if no secret
     */
    private function sign_payload($payload) {
        $secret = isset($this->options['api_secret']) ? $this->options['api_secret'] : '';
        if (empty($secret)) {
            return null;
        }
        
        return hash_hmac('sha256', $payload, $secret);
    }
    
    /**
     * Check circuit breaker status before making API calls
     * 
     * Implements a circuit breaker pattern to prevent cascading failures.
     * When the API error rate exceeds a threshold (10 errors in 5 minutes),
     * the circuit breaker opens and blocks all API calls for a period (default: 5-10 minutes).
     * 
     * This protects the system from:
     * - Overwhelming a failing API with requests
     * - Wasting resources on requests that will fail
     * - Creating excessive log entries
     * 
     * The circuit breaker automatically resets after the timeout period expires.
     *
     * @return bool True if circuit breaker is closed (calls allowed), false if open (calls blocked)
     * @since 1.0.0
     */
    private function check_circuit_breaker() {
        $circuit_breaker = get_transient('dmm_circuit_breaker');
        
        if ($circuit_breaker && isset($circuit_breaker['until'])) {
            if (time() < $circuit_breaker['until']) {
                return false; // Circuit breaker is open
            } else {
                // Circuit breaker timeout expired, reset it
                delete_transient('dmm_circuit_breaker');
            }
        }
        
        return true; // Circuit breaker is closed
    }
    
    /**
     * Open circuit breaker
     *
     * @param string $message Error message
     * @param int    $duration Duration in seconds
     */
    private function open_circuit_breaker($message, $duration = 300) {
        set_transient('dmm_circuit_breaker', [
            'message' => $message,
            'until' => time() + $duration
        ], $duration);
        
        $this->logger->debug_log("Circuit breaker opened - {$message}");
    }
    
    /**
     * Check error patterns and open circuit breaker if needed
     *
     * @param string $error Error message
     */
    private function check_and_open_circuit_breaker($error) {
        // Check for high error rate in last 5 minutes
        $error_count = $this->get_recent_error_count();
        
        if ($error_count >= 10) { // 10 errors in 5 minutes
            $this->open_circuit_breaker("High error rate detected: {$error_count} errors in 5 minutes", 600);
            return;
        }
        
        // Check for specific error patterns
        if (strpos($error, '429') !== false || strpos($error, 'rate limit') !== false) {
            $this->open_circuit_breaker("Rate limiting detected: {$error}", 300);
            return;
        }
        
        if (strpos($error, '500') !== false || strpos($error, '502') !== false || strpos($error, '503') !== false) {
            $this->open_circuit_breaker("Server errors detected: {$error}", 300);
            return;
        }
    }
    
    /**
     * Get recent error count for circuit breaker logic
     * 
     * Counts errors from the last 5 minutes in the delivery logs table.
     * Uses a 30-second cache to avoid hammering the database with frequent queries.
     * 
     * This count is used to determine if the circuit breaker should open.
     * Threshold: 10 errors in 5 minutes triggers circuit breaker.
     *
     * @return int Number of errors in the last 5 minutes
     * @since 1.0.0
     */
    private function get_recent_error_count() {
        // Cache for 30 seconds to avoid hammering the database
        $cache_key = 'dmm_recent_error_count';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return (int) $cached;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Use composite index (status, created_at) for optimal performance
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$table_name}
            WHERE status = 'error' 
            AND created_at >= %s
            LIMIT 1000
        ", date('Y-m-d H:i:s', time() - 300))); // Last 5 minutes
        
        $count = (int) $count;
        
        // Cache the result for 30 seconds
        set_transient($cache_key, $count, 30);
        
        return $count;
    }
}


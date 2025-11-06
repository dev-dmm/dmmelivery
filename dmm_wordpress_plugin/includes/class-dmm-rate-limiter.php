<?php
/**
 * Rate Limiter class for DMM Delivery Bridge
 * Implements token bucket algorithm for API rate limiting
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Rate_Limiter
 */
class DMM_Rate_Limiter {
    
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
     * Default rate limits per courier (requests per minute)
     *
     * @var array
     */
    private $default_limits = [
        'dmm' => 60,        // Main DMM API: 60 requests/minute
        'acs' => 30,        // ACS Courier: 30 requests/minute
        'geniki' => 20,     // Geniki: 20 requests/minute
        'elta' => 20,       // ELTA: 20 requests/minute
        'speedex' => 30,    // Speedex: 30 requests/minute
        'generic' => 30     // Generic: 30 requests/minute
    ];
    
    /**
     * Constructor
     *
     * @param array       $options Plugin options
     * @param DMM_Logger $logger Logger instance
     */
    public function __construct($options = [], $logger = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->logger = $logger ?: new DMM_Logger($this->options);
    }
    
    /**
     * Check if request is allowed using token bucket algorithm
     * 
     * Implements a token bucket rate limiting algorithm:
     * - Each courier has a bucket with a maximum capacity (rate limit)
     * - Tokens are refilled at a constant rate (1 token per second)
     * - Each API request consumes tokens (default: 1 token)
     * - If enough tokens are available, request is allowed
     * - If not enough tokens, request is denied with wait time
     * 
     * The bucket state is stored in WordPress transients and automatically
     * refills based on time elapsed since last check.
     * 
     * This prevents exceeding API rate limits while allowing bursts up to
     * the bucket capacity.
     *
     * @param string $courier Courier identifier (dmm, acs, geniki, elta, speedex, generic)
     * @param int    $tokens_required Number of tokens required for this request (default: 1)
     * @return array Response array with keys:
     *               - 'allowed' (bool): Whether request is allowed
     *               - 'wait_seconds' (int): Seconds to wait if not allowed (0 if allowed)
     *               - 'tokens_available' (int): Current tokens in bucket
     * @since 1.0.0
     */
    public function check_rate_limit($courier = 'dmm', $tokens_required = 1) {
        $rate_limit = $this->get_rate_limit($courier);
        $bucket_size = $rate_limit; // Bucket size equals rate limit
        
        // Get current bucket state
        $bucket_key = "dmm_rate_limit_{$courier}";
        $bucket = get_transient($bucket_key);
        
        if ($bucket === false) {
            // Initialize bucket with full tokens
            $bucket = [
                'tokens' => $bucket_size,
                'last_refill' => time()
            ];
        }
        
        $now = time();
        $time_passed = $now - $bucket['last_refill'];
        
        // Refill tokens based on time passed (1 token per second)
        if ($time_passed > 0) {
            $tokens_to_add = min($time_passed, $bucket_size - $bucket['tokens']);
            $bucket['tokens'] = min($bucket_size, $bucket['tokens'] + $tokens_to_add);
            $bucket['last_refill'] = $now;
        }
        
        // Check if we have enough tokens
        if ($bucket['tokens'] >= $tokens_required) {
            // Consume tokens
            $bucket['tokens'] -= $tokens_required;
            
            // Save updated bucket state (expires in 2 minutes to auto-cleanup)
            set_transient($bucket_key, $bucket, 120);
            
            return [
                'allowed' => true,
                'wait_seconds' => 0,
                'tokens_available' => $bucket['tokens']
            ];
        } else {
            // Calculate wait time (seconds until enough tokens are available)
            $tokens_needed = $tokens_required - $bucket['tokens'];
            $wait_seconds = $tokens_needed; // 1 token per second
            
            // Log rate limit hit
            $this->log_rate_limit_violation($courier, $wait_seconds, $bucket['tokens'], $tokens_required);
            
            return [
                'allowed' => false,
                'wait_seconds' => $wait_seconds,
                'tokens_available' => $bucket['tokens']
            ];
        }
    }
    
    /**
     * Wait for rate limit (blocking)
     *
     * @param string $courier Courier identifier
     * @param int    $tokens_required Number of tokens required
     * @return bool True if allowed after waiting, false if still not allowed
     */
    public function wait_for_rate_limit($courier = 'dmm', $tokens_required = 1) {
        $check = $this->check_rate_limit($courier, $tokens_required);
        
        if ($check['allowed']) {
            return true;
        }
        
        // Wait for the required time
        if ($check['wait_seconds'] > 0 && $check['wait_seconds'] <= 60) {
            // Only wait if it's reasonable (max 60 seconds)
            sleep($check['wait_seconds']);
            
            // Check again after waiting
            $check = $this->check_rate_limit($courier, $tokens_required);
            return $check['allowed'];
        }
        
        return false;
    }
    
    /**
     * Handle Retry-After header from API response
     *
     * @param string $courier Courier identifier
     * @param int    $retry_after_seconds Seconds to wait (from Retry-After header)
     * @return void
     */
    public function handle_retry_after($courier = 'dmm', $retry_after_seconds = 0) {
        if ($retry_after_seconds <= 0) {
            return;
        }
        
        // Consume all tokens and set last_refill to future time
        $bucket_key = "dmm_rate_limit_{$courier}";
        $rate_limit = $this->get_rate_limit($courier);
        
        $bucket = [
            'tokens' => 0,
            'last_refill' => time() + $retry_after_seconds
        ];
        
        // Save bucket state (expires after retry_after_seconds + buffer)
        set_transient($bucket_key, $bucket, $retry_after_seconds + 60);
        
        $this->logger->log_structured('rate_limit_retry_after', [
            'courier' => $courier,
            'retry_after_seconds' => $retry_after_seconds,
            'resume_at' => date('Y-m-d H:i:s', $bucket['last_refill'])
        ]);
    }
    
    /**
     * Get rate limit for a courier
     *
     * @param string $courier Courier identifier
     * @return int Requests per minute
     */
    public function get_rate_limit($courier = 'dmm') {
        // Check for custom rate limit in options
        $option_key = "rate_limit_{$courier}";
        if (isset($this->options[$option_key]) && is_numeric($this->options[$option_key])) {
            return max(1, (int) $this->options[$option_key]);
        }
        
        // Use default limits
        return isset($this->default_limits[$courier]) ? $this->default_limits[$courier] : 30;
    }
    
    /**
     * Set custom rate limit for a courier
     *
     * @param string $courier Courier identifier
     * @param int    $requests_per_minute Requests per minute
     * @return bool Success
     */
    public function set_rate_limit($courier, $requests_per_minute) {
        if (!is_numeric($requests_per_minute) || $requests_per_minute < 1) {
            return false;
        }
        
        $option_key = "rate_limit_{$courier}";
        $this->options[$option_key] = (int) $requests_per_minute;
        update_option('dmm_delivery_bridge_options', $this->options);
        
        // Reset bucket for this courier
        delete_transient("dmm_rate_limit_{$courier}");
        
        return true;
    }
    
    /**
     * Get current bucket state for monitoring
     *
     * @param string $courier Courier identifier
     * @return array Bucket state
     */
    public function get_bucket_state($courier = 'dmm') {
        $bucket_key = "dmm_rate_limit_{$courier}";
        $bucket = get_transient($bucket_key);
        $rate_limit = $this->get_rate_limit($courier);
        
        if ($bucket === false) {
            return [
                'tokens' => $rate_limit,
                'tokens_available' => $rate_limit,
                'rate_limit' => $rate_limit,
                'last_refill' => time(),
                'status' => 'ready'
            ];
        }
        
        // Calculate current tokens (accounting for time passed)
        $now = time();
        $time_passed = $now - $bucket['last_refill'];
        
        if ($time_passed > 0) {
            $tokens_to_add = min($time_passed, $rate_limit - $bucket['tokens']);
            $current_tokens = min($rate_limit, $bucket['tokens'] + $tokens_to_add);
        } else {
            $current_tokens = $bucket['tokens'];
        }
        
        return [
            'tokens' => $current_tokens,
            'tokens_available' => $current_tokens,
            'rate_limit' => $rate_limit,
            'last_refill' => $bucket['last_refill'],
            'status' => $current_tokens > 0 ? 'ready' : 'limited'
        ];
    }
    
    /**
     * Get rate limit statistics for all couriers
     *
     * @return array Statistics
     */
    public function get_statistics() {
        $stats = [];
        $couriers = array_keys($this->default_limits);
        
        foreach ($couriers as $courier) {
            $bucket_state = $this->get_bucket_state($courier);
            $stats[$courier] = $bucket_state;
        }
        
        return $stats;
    }
    
    /**
     * Log rate limit violation
     *
     * @param string $courier Courier identifier
     * @param int    $wait_seconds Seconds to wait
     * @param int    $tokens_available Available tokens
     * @param int    $tokens_required Required tokens
     * @return void
     */
    private function log_rate_limit_violation($courier, $wait_seconds, $tokens_available, $tokens_required) {
        $this->logger->log_structured('rate_limit_violation', [
            'courier' => $courier,
            'wait_seconds' => $wait_seconds,
            'tokens_available' => $tokens_available,
            'tokens_required' => $tokens_required,
            'rate_limit' => $this->get_rate_limit($courier)
        ]);
        
        // Track violations for alerting
        $violation_key = "dmm_rate_limit_violations_{$courier}";
        $violations = get_transient($violation_key) ?: [];
        $violations[] = [
            'timestamp' => time(),
            'wait_seconds' => $wait_seconds,
            'tokens_available' => $tokens_available
        ];
        
        // Keep only last 10 violations
        if (count($violations) > 10) {
            $violations = array_slice($violations, -10);
        }
        
        set_transient($violation_key, $violations, 3600); // Keep for 1 hour
        
        // Check if we should alert (more than 5 violations in last 5 minutes)
        $recent_violations = array_filter($violations, function($v) {
            return $v['timestamp'] > (time() - 300); // Last 5 minutes
        });
        
        if (count($recent_violations) >= 5) {
            $this->trigger_rate_limit_alert($courier, count($recent_violations));
        }
    }
    
    /**
     * Trigger rate limit alert
     *
     * @param string $courier Courier identifier
     * @param int    $violation_count Number of violations
     * @return void
     */
    private function trigger_rate_limit_alert($courier, $violation_count) {
        $alert_key = "dmm_rate_limit_alert_{$courier}";
        
        // Don't spam alerts - only alert once per 15 minutes
        if (get_transient($alert_key)) {
            return;
        }
        
        // Set alert flag
        set_transient($alert_key, true, 900); // 15 minutes
        
        // Store alert in options for admin display
        $alerts = get_option('dmm_rate_limit_alerts', []);
        $alerts[] = [
            'courier' => $courier,
            'violation_count' => $violation_count,
            'timestamp' => time(),
            'message' => sprintf(
                __('Rate limit violations detected for %s: %d violations in the last 5 minutes.', 'dmm-delivery-bridge'),
                strtoupper($courier),
                $violation_count
            )
        ];
        
        // Keep only last 20 alerts
        if (count($alerts) > 20) {
            $alerts = array_slice($alerts, -20);
        }
        
        update_option('dmm_rate_limit_alerts', $alerts);
        
        $this->logger->log_structured('rate_limit_alert', [
            'courier' => $courier,
            'violation_count' => $violation_count,
            'alert_message' => end($alerts)['message']
        ]);
    }
    
    /**
     * Get recent rate limit alerts
     *
     * @param int $limit Number of alerts to return
     * @return array Alerts
     */
    public function get_alerts($limit = 10) {
        $alerts = get_option('dmm_rate_limit_alerts', []);
        
        // Sort by timestamp (newest first)
        usort($alerts, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return array_slice($alerts, 0, $limit);
    }
    
    /**
     * Clear rate limit alerts
     *
     * @return void
     */
    public function clear_alerts() {
        delete_option('dmm_rate_limit_alerts');
    }
    
    /**
     * Reset rate limit bucket for a courier
     *
     * @param string $courier Courier identifier
     * @return void
     */
    public function reset_bucket($courier = 'dmm') {
        delete_transient("dmm_rate_limit_{$courier}");
    }
    
    /**
     * Reset all rate limit buckets
     *
     * @return void
     */
    public function reset_all_buckets() {
        $couriers = array_keys($this->default_limits);
        foreach ($couriers as $courier) {
            $this->reset_bucket($courier);
        }
    }
}


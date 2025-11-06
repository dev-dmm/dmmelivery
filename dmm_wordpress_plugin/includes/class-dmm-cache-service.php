<?php
/**
 * Cache Service class for DMM Delivery Bridge
 * Provides unified caching interface using WordPress transients and object cache
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Cache_Service
 */
class DMM_Cache_Service {
    
    /**
     * Cache group prefix
     */
    const CACHE_GROUP = 'dmm_delivery_bridge';
    
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
     * Default cache expiration times (in seconds)
     *
     * @var array
     */
    private $default_expirations = [
        'api_response' => 300,        // 5 minutes for API responses
        'order_status' => 60,          // 1 minute for order status
        'courier_data' => 600,         // 10 minutes for courier data
        'settings' => 3600,            // 1 hour for settings
        'statistics' => 300,           // 5 minutes for statistics
        'rate_limit' => 120            // 2 minutes for rate limit data
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
     * Get cached value
     * Uses object cache if available, falls back to transients
     *
     * @param string $key Cache key
     * @param string $type Cache type (for expiration lookup)
     * @return mixed|false Cached value or false if not found
     */
    public function get($key, $type = 'api_response') {
        $cache_key = $this->get_cache_key($key);
        $expiration = $this->get_expiration($type);
        
        // Try object cache first (if available)
        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($value !== false) {
                if ($this->logger->is_debug_mode()) {
                    $this->logger->debug_log("Cache hit (object cache): {$key}");
                }
                return $value;
            }
        }
        
        // Fall back to transients
        $value = get_transient($cache_key);
        if ($value !== false) {
            if ($this->logger->is_debug_mode()) {
                $this->logger->debug_log("Cache hit (transient): {$key}");
            }
            return $value;
        }
        
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log("Cache miss: {$key}");
        }
        
        return false;
    }
    
    /**
     * Set cached value
     * Uses object cache if available, falls back to transients
     *
     * @param string $key Cache key
     * @param mixed  $value Value to cache
     * @param string $type Cache type (for expiration lookup)
     * @param int    $expiration Optional custom expiration in seconds
     * @return bool Success
     */
    public function set($key, $value, $type = 'api_response', $expiration = null) {
        $cache_key = $this->get_cache_key($key);
        $expiration = $expiration !== null ? $expiration : $this->get_expiration($type);
        
        // Store in object cache if available
        if (wp_using_ext_object_cache()) {
            $result = wp_cache_set($cache_key, $value, self::CACHE_GROUP, $expiration);
        } else {
            $result = true; // Transients don't return false on success
        }
        
        // Always store in transients as fallback
        set_transient($cache_key, $value, $expiration);
        
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log("Cache set: {$key} (expires in {$expiration}s)");
        }
        
        return $result;
    }
    
    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        
        // Delete from object cache
        if (wp_using_ext_object_cache()) {
            wp_cache_delete($cache_key, self::CACHE_GROUP);
        }
        
        // Delete from transients
        delete_transient($cache_key);
        
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log("Cache deleted: {$key}");
        }
        
        return true;
    }
    
    /**
     * Delete multiple cache keys by pattern
     *
     * @param string $pattern Pattern to match (supports wildcards)
     * @return int Number of keys deleted
     */
    public function delete_pattern($pattern) {
        $deleted = 0;
        
        // For object cache, we can't easily pattern match, so we'll rely on transients
        // Get all transients with our prefix
        global $wpdb;
        
        $prefix = $this->get_cache_key($pattern);
        $prefix = str_replace('*', '%', $prefix);
        
        // Find matching transients
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE '_transient_%'",
            '_transient_' . $prefix
        ));
        
        foreach ($transients as $transient_name) {
            $key = str_replace(['_transient_', '_transient_timeout_'], '', $transient_name);
            $key = str_replace($this->get_cache_key(''), '', $key);
            
            if (fnmatch($this->get_cache_key($pattern), $this->get_cache_key($key))) {
                $this->delete($key);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Clear all cache for this plugin
     *
     * @return int Number of keys deleted
     */
    public function clear_all() {
        $deleted = 0;
        
        // Clear object cache group
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        // Clear transients with our prefix
        global $wpdb;
        
        $prefix = $this->get_cache_key('');
        $transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND (option_name LIKE '_transient_%' OR option_name LIKE '_transient_timeout_%')",
            '_transient%' . $prefix . '%'
        ));
        
        foreach ($transients as $transient_name) {
            $key = str_replace(['_transient_', '_transient_timeout_'], '', $transient_name);
            delete_transient($key);
            $deleted++;
        }
        
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log("Cleared all cache ({$deleted} keys)");
        }
        
        return $deleted;
    }
    
    /**
     * Cache API response
     *
     * @param string $cache_key Unique cache key (e.g., based on request parameters)
     * @param array  $response API response
     * @param int    $expiration Optional custom expiration
     * @return bool Success
     */
    public function cache_api_response($cache_key, $response, $expiration = null) {
        // Only cache successful responses
        if (isset($response['success']) && $response['success']) {
            return $this->set("api_response_{$cache_key}", $response, 'api_response', $expiration);
        }
        
        return false;
    }
    
    /**
     * Get cached API response
     *
     * @param string $cache_key Unique cache key
     * @return array|false Cached response or false
     */
    public function get_cached_api_response($cache_key) {
        return $this->get("api_response_{$cache_key}", 'api_response');
    }
    
    /**
     * Cache order status
     *
     * @param int $order_id Order ID
     * @param array $status_data Status data
     * @return bool Success
     */
    public function cache_order_status($order_id, $status_data) {
        return $this->set("order_status_{$order_id}", $status_data, 'order_status');
    }
    
    /**
     * Get cached order status
     *
     * @param int $order_id Order ID
     * @return array|false Cached status or false
     */
    public function get_cached_order_status($order_id) {
        return $this->get("order_status_{$order_id}", 'order_status');
    }
    
    /**
     * Invalidate order-related cache
     *
     * @param int $order_id Order ID
     * @return bool Success
     */
    public function invalidate_order_cache($order_id) {
        $deleted = 0;
        
        // Delete order status cache
        if ($this->delete("order_status_{$order_id}")) {
            $deleted++;
        }
        
        // Delete API response cache for this order
        $this->delete_pattern("api_response_*order_{$order_id}*");
        
        if ($this->logger->is_debug_mode()) {
            $this->logger->debug_log("Invalidated cache for order {$order_id}");
        }
        
        return $deleted > 0;
    }
    
    /**
     * Invalidate all API response cache
     *
     * @return int Number of keys deleted
     */
    public function invalidate_api_cache() {
        return $this->delete_pattern("api_response_*");
    }
    
    /**
     * Invalidate courier-specific cache
     *
     * @param string $courier Courier identifier
     * @return int Number of keys deleted
     */
    public function invalidate_courier_cache($courier) {
        return $this->delete_pattern("*courier_{$courier}*");
    }
    
    /**
     * Get cache statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $prefix = $this->get_cache_key('');
        $transients = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value, option_id 
             FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name LIKE '_transient_%'
             AND option_name NOT LIKE '_transient_timeout_%'",
            '_transient_' . $prefix . '%'
        ));
        
        $stats = [
            'total_keys' => count($transients),
            'object_cache_enabled' => wp_using_ext_object_cache(),
            'by_type' => []
        ];
        
        // Group by cache type
        foreach ($transients as $transient) {
            $key = str_replace('_transient_' . $prefix, '', $transient->option_name);
            
            if (strpos($key, 'api_response_') === 0) {
                $stats['by_type']['api_response'] = ($stats['by_type']['api_response'] ?? 0) + 1;
            } elseif (strpos($key, 'order_status_') === 0) {
                $stats['by_type']['order_status'] = ($stats['by_type']['order_status'] ?? 0) + 1;
            } elseif (strpos($key, 'courier_') === 0) {
                $stats['by_type']['courier_data'] = ($stats['by_type']['courier_data'] ?? 0) + 1;
            } else {
                $stats['by_type']['other'] = ($stats['by_type']['other'] ?? 0) + 1;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get cache key with prefix
     *
     * @param string $key Original key
     * @return string Prefixed key
     */
    private function get_cache_key($key) {
        return self::CACHE_GROUP . '_' . $key;
    }
    
    /**
     * Get expiration time for cache type
     *
     * @param string $type Cache type
     * @return int Expiration in seconds
     */
    private function get_expiration($type) {
        // Check for custom expiration in options
        $option_key = "cache_expiration_{$type}";
        if (isset($this->options[$option_key]) && is_numeric($this->options[$option_key])) {
            return max(0, (int) $this->options[$option_key]);
        }
        
        // Use default expiration
        return isset($this->default_expirations[$type]) 
            ? $this->default_expirations[$type] 
            : 300; // Default 5 minutes
    }
    
    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return !isset($this->options['cache_enabled']) || $this->options['cache_enabled'] === 'yes';
    }
    
    /**
     * Remember callback result (cache with callback)
     *
     * @param string   $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @param string   $type Cache type
     * @param int      $expiration Optional custom expiration
     * @return mixed Cached value or callback result
     */
    public function remember($key, $callback, $type = 'api_response', $expiration = null) {
        if (!$this->is_enabled()) {
            return call_user_func($callback);
        }
        
        $cached = $this->get($key, $type);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $value = call_user_func($callback);
        
        if ($value !== false && $value !== null) {
            $this->set($key, $value, $type, $expiration);
        }
        
        return $value;
    }
}


<?php
/**
 * Performance Monitor class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Performance_Monitor
 * 
 * Monitors and tracks performance metrics including:
 * - API response times
 * - Database query performance
 * - Overall system performance
 * - Performance degradation alerts
 */
class DMM_Performance_Monitor {
    
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
     * Performance metrics cache
     *
     * @var array
     */
    private $metrics_cache = [];
    
    /**
     * Constructor
     *
     * @param array       $options Plugin options
     * @param DMM_Logger $logger Logger instance
     */
    public function __construct($options = [], $logger = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->logger = $logger ?: new DMM_Logger($this->options);
        
        // Initialize database table
        $this->ensure_metrics_table_exists();
        
        // Hook into WordPress query logging if enabled
        if ($this->is_query_monitoring_enabled()) {
            $this->init_query_monitoring();
        }
    }
    
    /**
     * Check if query monitoring is enabled
     *
     * @return bool
     */
    private function is_query_monitoring_enabled() {
        return isset($this->options['performance_monitoring']) && 
               isset($this->options['performance_monitoring']['track_queries']) &&
               $this->options['performance_monitoring']['track_queries'] === 'yes';
    }
    
    /**
     * Initialize database query monitoring
     */
    private function init_query_monitoring() {
        // Hook into WordPress query execution
        // Note: This requires SAVEQUERIES to be enabled in wp-config.php
        add_action('shutdown', [$this, 'process_saved_queries'], 999);
    }
    
    /**
     * Process saved queries at shutdown
     * Requires SAVEQUERIES to be enabled in wp-config.php
     */
    public function process_saved_queries() {
        global $wpdb;
        
        // Only process if SAVEQUERIES is enabled
        if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
            return;
        }
        
        if (empty($wpdb->queries)) {
            return;
        }
        
        // Process queries from our plugin tables only
        foreach ($wpdb->queries as $query_info) {
            if (!is_array($query_info) || count($query_info) < 2) {
                continue;
            }
            
            $query = $query_info[0];
            $query_time = isset($query_info[1]) ? (float) $query_info[1] : 0;
            
            // Only track queries to our plugin tables
            if (strpos($query, $wpdb->prefix . 'dmm_') !== false) {
                // Extract table name from query
                $table_name = '';
                if (preg_match('/FROM\s+`?' . preg_quote($wpdb->prefix, '/') . 'dmm_([a-z0-9_]+)`?/i', $query, $matches)) {
                    $table_name = 'dmm_' . $matches[1];
                } elseif (preg_match('/INTO\s+`?' . preg_quote($wpdb->prefix, '/') . 'dmm_([a-z0-9_]+)`?/i', $query, $matches)) {
                    $table_name = 'dmm_' . $matches[1];
                } elseif (preg_match('/UPDATE\s+`?' . preg_quote($wpdb->prefix, '/') . 'dmm_([a-z0-9_]+)`?/i', $query, $matches)) {
                    $table_name = 'dmm_' . $matches[1];
                }
                
                if ($query_time > 0) {
                    $this->track_query($query, $query_time, $table_name);
                }
            }
        }
    }
    
    /**
     * Track API response time
     *
     * @param string $endpoint API endpoint
     * @param float  $response_time Response time in seconds
     * @param int    $http_code HTTP status code
     * @param string $courier Courier identifier (optional)
     * @param bool   $success Whether the request was successful
     * @return void
     */
    public function track_api_response($endpoint, $response_time, $http_code = 200, $courier = 'dmm', $success = true) {
        $metric = [
            'metric_type' => 'api_response',
            'metric_name' => $endpoint,
            'metric_value' => $response_time,
            'metadata' => json_encode([
                'http_code' => $http_code,
                'courier' => $courier,
                'success' => $success,
                'endpoint' => $endpoint
            ]),
            'created_at' => current_time('mysql')
        ];
        
        $this->save_metric($metric);
        
        // Check for performance degradation
        $this->check_api_performance($endpoint, $response_time, $courier);
    }
    
    /**
     * Track database query performance
     *
     * @param string $query SQL query (may be truncated for storage)
     * @param float  $query_time Query execution time in seconds
     * @param string $table_name Table name (optional)
     * @return void
     */
    public function track_query($query, $query_time, $table_name = '') {
        // Only track queries that exceed threshold (default: 0.1 seconds)
        $threshold = $this->get_query_threshold();
        
        if ($query_time < $threshold) {
            return; // Skip fast queries to reduce overhead
        }
        
        // Truncate query for storage (keep first 500 chars)
        $query_truncated = strlen($query) > 500 ? substr($query, 0, 500) . '...' : $query;
        
        $metric = [
            'metric_type' => 'database_query',
            'metric_name' => $table_name ?: 'unknown',
            'metric_value' => $query_time,
            'metadata' => json_encode([
                'query' => $query_truncated,
                'table_name' => $table_name,
                'query_length' => strlen($query)
            ]),
            'created_at' => current_time('mysql')
        ];
        
        $this->save_metric($metric);
        
        // Check for performance degradation
        $this->check_query_performance($table_name, $query_time);
    }
    
    /**
     * Track custom metric
     *
     * @param string $metric_type Metric type (e.g., 'order_processing', 'cache_hit')
     * @param string $metric_name Metric name
     * @param float  $metric_value Metric value
     * @param array  $metadata Additional metadata
     * @return void
     */
    public function track_metric($metric_type, $metric_name, $metric_value, $metadata = []) {
        $metric = [
            'metric_type' => $metric_type,
            'metric_name' => $metric_name,
            'metric_value' => $metric_value,
            'metadata' => json_encode($metadata),
            'created_at' => current_time('mysql')
        ];
        
        $this->save_metric($metric);
    }
    
    /**
     * Save metric to database
     *
     * @param array $metric Metric data
     * @return bool Success
     */
    private function save_metric($metric) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        
        // Ensure table exists
        $this->ensure_metrics_table_exists();
        
        // Insert metric
        $result = $wpdb->insert(
            $table_name,
            $metric,
            ['%s', '%s', '%f', '%s', '%s']
        );
        
        // Clean up old metrics periodically (every 100 inserts)
        if (rand(1, 100) === 1) {
            $this->cleanup_old_metrics();
        }
        
        return $result !== false;
    }
    
    /**
     * Check API performance and alert if degraded
     *
     * @param string $endpoint API endpoint
     * @param float  $response_time Response time
     * @param string $courier Courier identifier
     * @return void
     */
    private function check_api_performance($endpoint, $response_time, $courier) {
        $threshold = $this->get_api_threshold($courier);
        
        if ($response_time > $threshold) {
            $this->trigger_alert('api_slow', [
                'endpoint' => $endpoint,
                'response_time' => $response_time,
                'threshold' => $threshold,
                'courier' => $courier
            ]);
        }
        
        // Check for consecutive slow responses
        $recent_slow = $this->get_recent_slow_api_responses($endpoint, $courier, 5);
        if (count($recent_slow) >= 5) {
            $this->trigger_alert('api_degradation', [
                'endpoint' => $endpoint,
                'courier' => $courier,
                'slow_count' => count($recent_slow),
                'avg_response_time' => array_sum($recent_slow) / count($recent_slow)
            ]);
        }
    }
    
    /**
     * Check query performance and alert if degraded
     *
     * @param string $table_name Table name
     * @param float  $query_time Query execution time
     * @return void
     */
    private function check_query_performance($table_name, $query_time) {
        $threshold = $this->get_query_threshold();
        
        if ($query_time > $threshold * 2) {
            $this->trigger_alert('query_slow', [
                'table_name' => $table_name,
                'query_time' => $query_time,
                'threshold' => $threshold
            ]);
        }
    }
    
    /**
     * Trigger performance alert
     *
     * @param string $alert_type Alert type
     * @param array  $context Alert context
     * @return void
     */
    private function trigger_alert($alert_type, $context) {
        // Check if alerts are enabled
        if (!$this->is_alerting_enabled()) {
            return;
        }
        
        // Check if we've already alerted recently (prevent spam)
        $alert_key = 'dmm_perf_alert_' . $alert_type . '_' . md5(json_encode($context));
        $recent_alert = get_transient($alert_key);
        
        if ($recent_alert !== false) {
            return; // Already alerted recently
        }
        
        // Set transient to prevent duplicate alerts (5 minutes)
        set_transient($alert_key, true, 300);
        
        // Log alert
        $this->logger->log_structured('performance_alert', [
            'alert_type' => $alert_type,
            'context' => $context,
            'timestamp' => current_time('mysql')
        ]);
        
        // Send admin notification if enabled
        if ($this->is_admin_notification_enabled()) {
            $this->send_admin_notification($alert_type, $context);
        }
    }
    
    /**
     * Send admin notification
     *
     * @param string $alert_type Alert type
     * @param array  $context Alert context
     * @return void
     */
    private function send_admin_notification($alert_type, $context) {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }
        
        $subject = sprintf(
            '[DMM Delivery Bridge] Performance Alert: %s',
            ucfirst(str_replace('_', ' ', $alert_type))
        );
        
        $message = sprintf(
            "Performance degradation detected:\n\nType: %s\n\nContext:\n%s\n\nTime: %s",
            $alert_type,
            print_r($context, true),
            current_time('mysql')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get recent slow API responses
     *
     * @param string $endpoint API endpoint
     * @param string $courier Courier identifier
     * @param int    $limit Number of recent responses to check
     * @return array Array of response times
     */
    private function get_recent_slow_api_responses($endpoint, $courier, $limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        $threshold = $this->get_api_threshold($courier);
        
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT metric_value 
             FROM {$table_name}
             WHERE metric_type = 'api_response'
             AND metric_name = %s
             AND JSON_EXTRACT(metadata, '$.courier') = %s
             AND metric_value > %f
             ORDER BY created_at DESC
             LIMIT %d",
            $endpoint,
            $courier,
            $threshold,
            $limit
        ));
        
        return array_map('floatval', $results);
    }
    
    /**
     * Get API performance threshold (seconds)
     *
     * @param string $courier Courier identifier
     * @return float Threshold in seconds
     */
    private function get_api_threshold($courier = 'dmm') {
        $default_threshold = 2.0; // 2 seconds default
        
        if (isset($this->options['performance_monitoring']) &&
            isset($this->options['performance_monitoring']['api_thresholds']) &&
            isset($this->options['performance_monitoring']['api_thresholds'][$courier])) {
            return (float) $this->options['performance_monitoring']['api_thresholds'][$courier];
        }
        
        if (isset($this->options['performance_monitoring']) &&
            isset($this->options['performance_monitoring']['api_threshold'])) {
            return (float) $this->options['performance_monitoring']['api_threshold'];
        }
        
        return $default_threshold;
    }
    
    /**
     * Get database query threshold (seconds)
     *
     * @return float Threshold in seconds
     */
    private function get_query_threshold() {
        $default_threshold = 0.1; // 100ms default
        
        if (isset($this->options['performance_monitoring']) &&
            isset($this->options['performance_monitoring']['query_threshold'])) {
            return (float) $this->options['performance_monitoring']['query_threshold'];
        }
        
        return $default_threshold;
    }
    
    /**
     * Check if alerting is enabled
     *
     * @return bool
     */
    private function is_alerting_enabled() {
        if (isset($this->options['performance_monitoring']) &&
            isset($this->options['performance_monitoring']['alerts_enabled'])) {
            return $this->options['performance_monitoring']['alerts_enabled'] === 'yes';
        }
        
        return true; // Enabled by default
    }
    
    /**
     * Check if admin notifications are enabled
     *
     * @return bool
     */
    private function is_admin_notification_enabled() {
        if (isset($this->options['performance_monitoring']) &&
            isset($this->options['performance_monitoring']['admin_notifications'])) {
            return $this->options['performance_monitoring']['admin_notifications'] === 'yes';
        }
        
        return false; // Disabled by default
    }
    
    /**
     * Ensure metrics table exists
     *
     * @return void
     */
    private function ensure_metrics_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name;
        
        if (!$table_exists) {
            $this->create_metrics_table();
        }
    }
    
    /**
     * Create performance metrics table
     *
     * @return void
     */
    private function create_metrics_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_name varchar(255) NOT NULL,
            metric_value decimal(10,4) NOT NULL,
            metadata longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_metric_type (metric_type),
            KEY idx_metric_name (metric_name),
            KEY idx_created_at (created_at),
            KEY idx_type_name (metric_type, metric_name),
            KEY idx_type_created (metric_type, created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean up old metrics (keep last 30 days by default)
     *
     * @return int Number of deleted records
     */
    private function cleanup_old_metrics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        $retention_days = $this->get_retention_days();
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < %s",
            date('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS))
        ));
        
        return (int) $deleted;
    }
    
    /**
     * Get retention days for metrics
     *
     * @return int Number of days to retain metrics
     */
    private function get_retention_days() {
        if (isset($this->options['performance_monitoring']) &&
            isset($this->options['performance_monitoring']['retention_days'])) {
            return absint($this->options['performance_monitoring']['retention_days']);
        }
        
        return 30; // Default: 30 days
    }
    
    /**
     * Get performance statistics
     *
     * @param string $metric_type Metric type (optional)
     * @param string $metric_name Metric name (optional)
     * @param int    $hours Number of hours to look back (default: 24)
     * @return array Statistics
     */
    public function get_statistics($metric_type = null, $metric_name = null, $hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        $since = date('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
        
        $where = ["created_at >= %s"];
        $where_values = [$since];
        
        if ($metric_type) {
            $where[] = "metric_type = %s";
            $where_values[] = $metric_type;
        }
        
        if ($metric_name) {
            $where[] = "metric_name = %s";
            $where_values[] = $metric_name;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as count,
                AVG(metric_value) as avg_value,
                MIN(metric_value) as min_value,
                MAX(metric_value) as max_value,
                STDDEV(metric_value) as stddev_value
             FROM {$table_name}
             WHERE {$where_clause}",
            ...$where_values
        ), ARRAY_A);
        
        return $stats ?: [
            'count' => 0,
            'avg_value' => 0,
            'min_value' => 0,
            'max_value' => 0,
            'stddev_value' => 0
        ];
    }
    
    /**
     * Get API response time statistics
     *
     * @param string $endpoint API endpoint (optional)
     * @param string $courier Courier identifier (optional)
     * @param int    $hours Number of hours to look back (default: 24)
     * @return array Statistics
     */
    public function get_api_statistics($endpoint = null, $courier = null, $hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_performance_metrics';
        $since = date('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
        
        $where = [
            "metric_type = 'api_response'",
            "created_at >= %s"
        ];
        $where_values = [$since];
        
        if ($endpoint) {
            $where[] = "metric_name = %s";
            $where_values[] = $endpoint;
        }
        
        if ($courier) {
            $where[] = "JSON_EXTRACT(metadata, '$.courier') = %s";
            $where_values[] = $courier;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as count,
                AVG(metric_value) as avg_response_time,
                MIN(metric_value) as min_response_time,
                MAX(metric_value) as max_response_time,
                STDDEV(metric_value) as stddev_response_time
             FROM {$table_name}
             WHERE {$where_clause}",
            ...$where_values
        ), ARRAY_A);
        
        return $stats ?: [
            'count' => 0,
            'avg_response_time' => 0,
            'min_response_time' => 0,
            'max_response_time' => 0,
            'stddev_response_time' => 0
        ];
    }
    
    /**
     * Get database query statistics
     *
     * @param string $table_name Table name (optional)
     * @param int    $hours Number of hours to look back (default: 24)
     * @return array Statistics
     */
    public function get_query_statistics($table_name = null, $hours = 24) {
        global $wpdb;
        
        $table_name_metric = $wpdb->prefix . 'dmm_performance_metrics';
        $since = date('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
        
        $where = [
            "metric_type = 'database_query'",
            "created_at >= %s"
        ];
        $where_values = [$since];
        
        if ($table_name) {
            $where[] = "metric_name = %s";
            $where_values[] = $table_name;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as count,
                AVG(metric_value) as avg_query_time,
                MIN(metric_value) as min_query_time,
                MAX(metric_value) as max_query_time,
                STDDEV(metric_value) as stddev_query_time
             FROM {$table_name_metric}
             WHERE {$where_clause}",
            ...$where_values
        ), ARRAY_A);
        
        return $stats ?: [
            'count' => 0,
            'avg_query_time' => 0,
            'min_query_time' => 0,
            'max_query_time' => 0,
            'stddev_query_time' => 0
        ];
    }
    
}


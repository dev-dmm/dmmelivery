<?php
/**
 * Logger class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Logger
 */
class DMM_Logger {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct($options = []) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
    }
    
    /**
     * General logging method - always logs (unlike debug_log)
     *
     * @param string $message Log message
     * @param string $level Log level: 'info', 'warning', 'error', 'debug' (default: 'info')
     */
    public function log($message, $level = 'info') {
        $prefix = "DMM Delivery Bridge [{$level}]:";
        error_log("{$prefix} {$message}");
        
        // Also log to database if it's an error
        if ($level === 'error') {
            $this->log_to_database($message, $level);
        }
    }
    
    /**
     * Debug logging helper - only logs if debug mode is enabled
     *
     * @param string $message Log message
     */
    public function debug_log($message) {
        if ($this->is_debug_mode()) {
            error_log("DMM Delivery Bridge: {$message}");
        }
    }
    
    /**
     * Debug logging for structured data (arrays/objects)
     * Replaces print_r() and var_dump() with safe JSON encoding
     *
     * @param string $label Label/description for the debug data
     * @param mixed  $data Data to log (array, object, or any serializable value)
     */
    public function debug_data($label, $data) {
        if (!$this->is_debug_mode()) {
            return;
        }
        
        // Use wp_json_encode for safe, readable output
        // JSON_PRETTY_PRINT makes it readable, JSON_UNESCAPED_SLASHES keeps URLs clean
        $encoded = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if ($encoded === false) {
            // Fallback for non-serializable data
            $encoded = '[Non-serializable data]';
        }
        
        error_log("DMM Delivery Bridge - {$label}: {$encoded}");
    }
    
    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function is_debug_mode() {
        return isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes';
    }
    
    /**
     * Structured logging helper (GDPR compliant)
     * 
     * Logs structured events with context data. Automatically sanitizes PII (Personally
     * Identifiable Information) to ensure GDPR compliance:
     * - Email addresses are hashed (SHA-256)
     * - Phone numbers are hashed (SHA-256)
     * - Large payloads are truncated to 200 characters
     * 
     * Log format: JSON with timestamp, event name, and sanitized context.
     * This makes logs searchable and parseable while protecting user privacy.
     *
     * @param string $event Event name (e.g., 'order_sent_success', 'api_error')
     * @param array  $context Context data (will be sanitized for PII)
     * @return void
     * @since 1.0.0
     */
    public function log_structured($event, $context = []) {
        // Sanitize context to avoid PII
        $sanitized_context = $this->sanitize_log_context($context);
        
        $log_entry = [
            'timestamp' => gmdate('Y-m-d H:i:s'), // UTC timestamp
            'event' => $event,
            'context' => $sanitized_context
        ];
        
        error_log('DMM Delivery Bridge: ' . json_encode($log_entry));
    }
    
    /**
     * Sanitize log context to avoid PII
     *
     * @param array $context Context data
     * @return array Sanitized context
     */
    private function sanitize_log_context($context) {
        $sanitized = [];
        
        foreach ($context as $key => $value) {
            if (in_array($key, ['email', 'phone', 'billing_email', 'billing_phone'])) {
                // Hash PII fields
                $sanitized[$key] = hash('sha256', (string) $value);
            } elseif (in_array($key, ['order_data', 'payload', 'body'])) {
                // Truncate large payloads
                $sanitized[$key] = substr(json_encode($value), 0, 200) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Log request to database
     *
     * @param int   $order_id Order ID
     * @param array $request_data Request data
     * @param array $response Response data
     */
    public function log_request($order_id, $request_data, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Ensure table exists
        $this->ensure_log_table_exists();
        
        // Prepare data for logging
        // Include response body in error logs for better debugging
        $response_to_log = $response;
        if (!$response['success'] && isset($response['response_body'])) {
            $response_to_log['raw_response_body'] = $response['response_body'];
        }
        
        $log_data = [
            'order_id' => $order_id,
            'status' => $response['success'] ? 'success' : 'error',
            'request_data' => json_encode($request_data, JSON_PRETTY_PRINT),
            'response_data' => json_encode($response_to_log, JSON_PRETTY_PRINT),
            'error_message' => $response['success'] ? null : $response['message'],
            'created_at' => current_time('mysql')
        ];
        
        // Insert log entry
        $result = $wpdb->insert(
            $table_name,
            $log_data,
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        // Debug logging if enabled
        if ($this->is_debug_mode()) {
            if ($result === false) {
                error_log('DMM Delivery Bridge - Log insert failed: ' . $wpdb->last_error);
                error_log('DMM Delivery Bridge - Log data: ' . wp_json_encode($log_data, JSON_PRETTY_PRINT));
            } else {
                error_log('DMM Delivery Bridge - Log inserted successfully (ID: ' . $wpdb->insert_id . ')');
            }
        }
        
        // If insert failed, try to create table and retry
        if ($result === false) {
            error_log('DMM Delivery Bridge - Logging failed, attempting to recreate table');
            $this->create_log_table();
            
            // Retry insert
            $result = $wpdb->insert(
                $table_name,
                $log_data,
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );
            
            if ($result === false) {
                error_log('DMM Delivery Bridge - Log insert failed after table recreation: ' . $wpdb->last_error);
            }
        }
    }
    
    /**
     * Ensure log table exists
     */
    private function ensure_log_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name;
        
        if (!$table_exists) {
            $this->create_log_table();
        }
    }
    
    /**
     * Create log table with optimized indexes
     */
    private function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            request_data longtext,
            response_data longtext,
            error_message text,
            context varchar(50) DEFAULT 'api',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_order_id (order_id),
            KEY idx_created_at (created_at),
            KEY idx_context (context),
            KEY idx_status (status),
            KEY idx_status_created_at (status, created_at),
            KEY idx_order_status (order_id, status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Ensure composite indexes exist (for existing tables)
        $this->ensure_composite_indexes();
    }
    
    /**
     * Ensure composite indexes exist for query optimization
     * Adds indexes to existing tables for better query performance
     */
    private function ensure_composite_indexes() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )) === $table_name;
        
        if (!$table_exists) {
            return; // Table doesn't exist, indexes will be created with table
        }
        
        // Check if composite indexes exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table_name}`");
        
        $existing_indexes = [];
        if ($indexes) {
            $existing_indexes = array_unique(array_column($indexes, 'Key_name'));
        }
        
        // Add missing composite indexes (suppress errors if index already exists)
        if (!in_array('idx_status_created_at', $existing_indexes)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD INDEX idx_status_created_at (status, created_at)");
        }
        
        if (!in_array('idx_order_status', $existing_indexes)) {
            $wpdb->query("ALTER TABLE `{$table_name}` ADD INDEX idx_order_status (order_id, status)");
        }
    }
    
    /**
     * Get log retention days from options
     *
     * @return int Number of days to retain logs (default: 7)
     */
    public function get_log_retention_days() {
        $retention = isset($this->options['log_retention_days']) 
            ? absint($this->options['log_retention_days']) 
            : 7;
        
        // Ensure minimum of 1 day and maximum of 90 days
        return max(1, min(90, $retention));
    }
    
    /**
     * Get maximum log file size in bytes
     *
     * @return int Maximum size in bytes (default: 10MB)
     */
    public function get_max_log_file_size() {
        $size_mb = isset($this->options['max_log_file_size_mb']) 
            ? absint($this->options['max_log_file_size_mb']) 
            : 10;
        
        // Ensure minimum of 1MB and maximum of 100MB
        $size_mb = max(1, min(100, $size_mb));
        
        return $size_mb * 1024 * 1024; // Convert to bytes
    }
    
    /**
     * Rotate debug log file if it exceeds size limit
     *
     * @param string $log_file_path Path to log file
     * @return bool True if rotation occurred, false otherwise
     */
    public function rotate_debug_log($log_file_path = null) {
        // If no path provided, check plugin directory for debug log files
        if ($log_file_path === null) {
            $log_file_path = DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'debug (1).log';
        }
        
        // Check if file exists and exceeds size limit
        if (!file_exists($log_file_path)) {
            return false;
        }
        
        $file_size = filesize($log_file_path);
        $max_size = $this->get_max_log_file_size();
        
        if ($file_size < $max_size) {
            return false; // No rotation needed
        }
        
        // Create archive filename with timestamp
        $log_dir = dirname($log_file_path);
        $log_basename = basename($log_file_path, '.log');
        $archive_name = $log_basename . '_archive_' . date('Y-m-d_His') . '.log';
        $archive_path = $log_dir . '/' . $archive_name;
        
        // Rotate: rename current log to archive
        if (@rename($log_file_path, $archive_path)) {
            $this->debug_log("Log file rotated: {$log_file_path} -> {$archive_name} ({$file_size} bytes)");
            
            // Clean up old archives
            $this->cleanup_old_log_archives($log_dir, $log_basename);
            
            return true;
        } else {
            $this->debug_log("Failed to rotate log file: {$log_file_path}");
            return false;
        }
    }
    
    /**
     * Clean up old log archives based on retention policy
     *
     * @param string $log_dir Directory containing log files
     * @param string $log_basename Base name of log file (without extension)
     */
    public function cleanup_old_log_archives($log_dir, $log_basename = 'debug (1)') {
        $retention_days = $this->get_log_retention_days();
        $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);
        
        // Find all archive files matching pattern
        $pattern = $log_dir . '/' . preg_quote($log_basename, '/') . '_archive_*.log';
        $archives = glob($pattern);
        
        if (empty($archives)) {
            return;
        }
        
        $deleted_count = 0;
        foreach ($archives as $archive_file) {
            // Check file modification time
            $file_time = filemtime($archive_file);
            
            if ($file_time < $cutoff_time) {
                if (@unlink($archive_file)) {
                    $deleted_count++;
                }
            }
        }
        
        if ($deleted_count > 0) {
            $this->debug_log("Cleaned up {$deleted_count} old log archive(s) older than {$retention_days} days");
        }
    }
    
    /**
     * Clean up WordPress debug.log file if it exists
     *
     * @return bool True if cleanup occurred, false otherwise
     */
    public function cleanup_wordpress_debug_log() {
        // Check for WordPress debug.log in content directory
        if (!defined('WP_CONTENT_DIR')) {
            return false;
        }
        
        $wp_debug_log = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($wp_debug_log)) {
            return false;
        }
        
        // Check if file exceeds size limit
        $file_size = filesize($wp_debug_log);
        $max_size = $this->get_max_log_file_size();
        
        $retention_days = $this->get_log_retention_days();
        $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);
        
        // If file is small and recent, no cleanup needed
        if ($file_size < $max_size && filemtime($wp_debug_log) >= $cutoff_time) {
            return false;
        }
        
        // For large files, rotate instead of filtering (more efficient)
        if ($file_size >= $max_size) {
            $archive_name = 'debug_' . date('Y-m-d_His') . '.log';
            $archive_path = WP_CONTENT_DIR . '/' . $archive_name;
            
            if (@rename($wp_debug_log, $archive_path)) {
                $this->debug_log("Rotated WordPress debug.log: {$archive_name} ({$file_size} bytes)");
                
                // Clean up old archives
                $archives = glob(WP_CONTENT_DIR . '/debug_*.log');
                if (!empty($archives)) {
                    foreach ($archives as $archive) {
                        if (filemtime($archive) < $cutoff_time) {
                            @unlink($archive);
                        }
                    }
                }
                
                return true;
            }
        }
        
        // For files that are just old (but not too large), filter by date
        if (filemtime($wp_debug_log) < $cutoff_time) {
            // Read log file and filter by date
            $log_content = @file_get_contents($wp_debug_log);
            if ($log_content === false) {
                return false;
            }
            
            $lines = explode("\n", $log_content);
            $filtered_lines = [];
            $removed_count = 0;
            
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    $filtered_lines[] = $line;
                    continue;
                }
                
                // Try to extract timestamp from log line
                // WordPress debug.log format: [DD-MMM-YYYY HH:MM:SS UTC]
                if (preg_match('/\[(\d{2}-[A-Z]{3}-\d{4} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
                    $log_time = strtotime($matches[1]);
                    if ($log_time && $log_time >= $cutoff_time) {
                        $filtered_lines[] = $line;
                    } else {
                        $removed_count++;
                    }
                } else {
                    // If we can't parse timestamp, keep the line
                    $filtered_lines[] = $line;
                }
            }
            
            // If we removed lines, write back filtered content
            if ($removed_count > 0) {
                $new_content = implode("\n", $filtered_lines);
                @file_put_contents($wp_debug_log, $new_content);
                $this->debug_log("Cleaned up WordPress debug.log: removed {$removed_count} old entries");
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clean up all plugin-related log files
     * This method handles both plugin directory logs and WordPress debug.log
     *
     * @return array Statistics about cleanup operation
     */
    public function cleanup_all_log_files() {
        $stats = [
            'rotated' => 0,
            'archives_deleted' => 0,
            'wordpress_log_cleaned' => false
        ];
        
        // Rotate plugin debug log if needed
        $plugin_log = DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'debug (1).log';
        if ($this->rotate_debug_log($plugin_log)) {
            $stats['rotated']++;
        }
        
        // Clean up old archives
        $this->cleanup_old_log_archives(DMM_DELIVERY_BRIDGE_PLUGIN_DIR, 'debug (1)');
        
        // Clean up WordPress debug.log
        if ($this->cleanup_wordpress_debug_log()) {
            $stats['wordpress_log_cleaned'] = true;
        }
        
        // Also check for any other debug*.log files in plugin directory
        $log_files = glob(DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'debug*.log');
        if (!empty($log_files)) {
            $retention_days = $this->get_log_retention_days();
            $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);
            
            foreach ($log_files as $log_file) {
                // Skip if it's the main log file (we handle that separately)
                if (basename($log_file) === 'debug (1).log') {
                    continue;
                }
                
                // Delete old log files
                if (filemtime($log_file) < $cutoff_time) {
                    if (@unlink($log_file)) {
                        $stats['archives_deleted']++;
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Log message to database
     *
     * @param string $message Log message
     * @param string $level Log level
     */
    private function log_to_database($message, $level = 'error') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Ensure table exists
        $this->ensure_log_table_exists();
        
        // Extract order ID from message if present
        $order_id = 0;
        if (preg_match('/order[_\s]+(\d+)/i', $message, $matches)) {
            $order_id = absint($matches[1]);
        }
        
        $log_data = [
            'order_id' => $order_id,
            'status' => $level,
            'request_data' => null,
            'response_data' => null,
            'error_message' => $message,
            'context' => 'bulk_operation',
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $table_name,
            $log_data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
}


<?php
/**
 * Database operations class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Database
 */
class DMM_Database {
    
    /**
     * Create deduplication table
     */
    public function create_dedupe_table() {
        $current_version = get_option('dmm_db_version', '0.0.0');
        $target_version = '1.0.0';
        
        if (version_compare($current_version, $target_version, '<')) {
            $this->upgrade_database($target_version);
        }
    }
    
    /**
     * Upgrade database to target version
     *
     * @param string $target_version Target version
     */
    private function upgrade_database($target_version) {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // dbDelta-compatible SQL with proper formatting
        $sql = "CREATE TABLE {$wpdb->prefix}dmm_processed_vouchers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            courier VARCHAR(16) NOT NULL,
            voucher_hash CHAR(40) NOT NULL,
            processed_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_courier_hash (courier, voucher_hash),
            KEY order_id (order_id),
            KEY processed_at (processed_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update version (autoload = no)
        update_option('dmm_db_version', $target_version, false);
        
        // Initialize canary seed if not exists
        if (!get_option('dmm_canary_seed')) {
            update_option('dmm_canary_seed', wp_generate_password(16, false), false);
        }
        
        // Initialize instance ID if not exists
        if (!get_option('dmm_instance_id')) {
            update_option('dmm_instance_id', substr(sha1(DB_NAME . ':' . site_url()), 0, 8), false);
        }
        
        // Track plugin version for job compatibility
        $plugin_version = defined('DMM_DELIVERY_BRIDGE_VERSION') ? DMM_DELIVERY_BRIDGE_VERSION : '1.0.0';
        update_option('dmm_plugin_version', $plugin_version, false);
        
        // Mark all DMM options as autoload = no for performance
        $dmm_options = [
            'dmm_delivery_bridge_options',
            'dmm_log_retention_days',
            'dmm_stuck_jobs_count',
            'dmm_stuck_jobs_notice',
            'dmm_failed_jobs_count',
            'dmm_failed_jobs_notice',
            'dmm_canary_seed',
            'dmm_instance_id',
            'dmm_plugin_version'
        ];
        
        foreach ($dmm_options as $option) {
            $value = get_option($option);
            if ($value !== false) {
                update_option($option, $value, false); // autoload = no
            }
        }
    }
    
    /**
     * Create all required tables (called on activation)
     */
    public function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $logs = $wpdb->prefix . 'dmm_delivery_logs';
        $ship = $wpdb->prefix . 'dmm_shipments';
        $metrics = $wpdb->prefix . 'dmm_performance_metrics';
        $charset = $wpdb->get_charset_collate();

        // Create logs table with proper indexes
        dbDelta("
        CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            request_data LONGTEXT,
            response_data LONGTEXT,
            error_message TEXT,
            context VARCHAR(50) DEFAULT 'api',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_order_id (order_id),
            KEY idx_created_at (created_at),
            KEY idx_context (context),
            KEY idx_status (status),
            KEY idx_status_created_at (status, created_at),
            KEY idx_order_status (order_id, status)
        ) {$charset};
        ");

        // Create shipments table with proper indexes
        dbDelta("
        CREATE TABLE {$ship} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            tracking_number VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            next_check_at DATETIME NULL,
            retry_count INT(11) DEFAULT 0,
            last_error TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order (order_id),
            KEY idx_next_check_at (next_check_at),
            KEY idx_status (status),
            KEY idx_tracking (tracking_number)
        ) {$charset};
        ");
        
        // Create performance metrics table
        dbDelta("
        CREATE TABLE {$metrics} (
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
        ) {$charset};
        ");
    }
    
    /**
     * Ensure shipments table exists with proper indexes
     */
    public function ensure_shipments_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_shipments';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            tracking_number varchar(255) NOT NULL,
            status varchar(50) DEFAULT 'pending',
            next_check_at datetime DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            last_error text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_order (order_id),
            KEY idx_next_check_at (next_check_at),
            KEY idx_status (status),
            KEY idx_tracking (tracking_number)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Check if voucher has been processed (database deduplication)
     *
     * @param int    $order_id Order ID
     * @param string $courier Courier name
     * @param string $voucher Voucher number
     * @return bool|int False if not processed, order_id if already processed
     */
    public function has_processed_voucher($order_id, $courier, $voucher) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_processed_vouchers';
        $voucher_hash = sha1("$courier:$voucher");
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT order_id FROM $table_name 
             WHERE courier = %s AND voucher_hash = %s",
            $courier, $voucher_hash
        ));
        
        return $result ? (int) $result : false;
    }
    
    /**
     * Mark voucher as processed
     *
     * @param int    $order_id Order ID
     * @param string $courier Courier name
     * @param string $voucher Voucher number
     * @return bool Success
     */
    public function mark_voucher_processed($order_id, $courier, $voucher) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_processed_vouchers';
        $voucher_hash = sha1("$courier:$voucher");
        
        return $wpdb->insert(
            $table_name,
            [
                'order_id' => $order_id,
                'courier' => $courier,
                'voucher_hash' => $voucher_hash,
                'processed_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        ) !== false;
    }
}


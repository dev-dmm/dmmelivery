<?php
/**
 * WP-CLI Commands for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP-CLI is available
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * WP-CLI Commands class
 */
class DMM_CLI_Commands {
    
    /**
     * Plugin instance
     *
     * @var DMM_Delivery_Bridge
     */
    private $plugin;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin = DMM_Delivery_Bridge::getInstance();
    }
    
    /**
     * Test API connection
     *
     * ## EXAMPLES
     *
     *     wp dmm test-connection
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function test_connection($args, $assoc_args) {
        WP_CLI::line('Testing API connection...');
        
        try {
            $options = get_option('dmm_delivery_bridge_options', []);
            $api_endpoint = isset($options['api_endpoint']) ? $options['api_endpoint'] : '';
            $api_key = isset($options['api_key']) ? $options['api_key'] : '';
            $tenant_id = isset($options['tenant_id']) ? $options['tenant_id'] : '';
            
            WP_CLI::line("Configuration:");
            WP_CLI::line("  API Endpoint: " . ($api_endpoint ? $api_endpoint : 'Not set'));
            WP_CLI::line("  API Key: " . ($api_key ? 'Set (' . strlen($api_key) . ' chars)' : 'Not set'));
            WP_CLI::line("  Tenant ID: " . ($tenant_id ? $tenant_id : 'Not set'));
            WP_CLI::line("");
            
            if (!$api_endpoint) {
                WP_CLI::error("API endpoint not configured");
            }
            
            // Test with a simple request
            $test_data = [
                'order' => ['id' => 'test'],
                'customer' => ['name' => 'Test'],
                'shipping' => ['address' => 'Test']
            ];
            
            WP_CLI::line("Sending test request...");
            $start_time = microtime(true);
            $response = $this->plugin->api_client->send_to_api($test_data, 'dmm', false);
            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            
            WP_CLI::line("Response time: {$response_time}ms");
            WP_CLI::line("HTTP Code: " . (isset($response['http_code']) ? $response['http_code'] : 'N/A'));
            
            if ($response && isset($response['success'])) {
                if ($response['success']) {
                    WP_CLI::success("Connection test successful!");
                } else {
                    WP_CLI::warning("Connection test completed with errors: " . (isset($response['message']) ? $response['message'] : 'Unknown error'));
                }
            } else {
                WP_CLI::warning("Unexpected response format");
            }
        } catch (Exception $e) {
            WP_CLI::error("Test failed: " . $e->getMessage());
        }
    }
    
    /**
     * Sync orders to API
     *
     * ## OPTIONS
     *
     * [--order-id=<id>]
     * : Specific order ID to sync
     *
     * [--status=<status>]
     * : Sync orders with specific status (comma-separated)
     *
     * [--limit=<number>]
     * : Limit number of orders to sync (default: 100)
     *
     * [--dry-run]
     * : Show what would be synced without actually syncing
     *
     * ## EXAMPLES
     *
     *     wp dmm sync-orders
     *     wp dmm sync-orders --order-id=123
     *     wp dmm sync-orders --status=processing,completed --limit=50
     *     wp dmm sync-orders --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function sync_orders($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $order_id = isset($assoc_args['order-id']) ? intval($assoc_args['order-id']) : null;
        $status = isset($assoc_args['status']) ? explode(',', $assoc_args['status']) : ['processing', 'completed'];
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 100;
        
        if ($dry_run) {
            WP_CLI::line('DRY RUN MODE - No changes will be made');
            WP_CLI::line('');
        }
        
        try {
            if ($order_id) {
                // Sync specific order
                $order = wc_get_order($order_id);
                if (!$order) {
                    WP_CLI::error("Order #{$order_id} not found");
                }
                
                if ($dry_run) {
                    WP_CLI::line("Would sync order #{$order_id}");
                    WP_CLI::line("  Status: {$order->get_status()}");
                    WP_CLI::line("  Total: {$order->get_total()}");
                    WP_CLI::line("  Already sent: " . ($order->get_meta('_dmm_delivery_sent') === 'yes' ? 'Yes' : 'No'));
                    return;
                }
                
                WP_CLI::line("Syncing order #{$order_id}...");
                
                // Clear any existing locks
                delete_transient('dmm_sending_' . $order_id);
                
                // Process the order
                $this->plugin->order_processor->process_order_robust($order);
                
                // Check if it was successful
                if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
                    WP_CLI::success("Order #{$order_id} synced successfully");
                } else {
                    $error = $order->get_meta('_dmm_error');
                    if ($error) {
                        WP_CLI::warning("Order #{$order_id} sync failed: {$error}");
                    } else {
                        WP_CLI::warning("Order #{$order_id} sync status unknown");
                    }
                }
            } else {
                // Sync multiple orders
                $query_args = [
                    'limit' => $limit,
                    'status' => $status,
                    'return' => 'ids',
                ];
                
                $order_ids = wc_get_orders($query_args);
                $total = count($order_ids);
                
                if ($total === 0) {
                    WP_CLI::warning("No orders found matching criteria");
                    return;
                }
                
                if ($dry_run) {
                    WP_CLI::line("Would sync {$total} orders:");
                    foreach ($order_ids as $id) {
                        $order = wc_get_order($id);
                        WP_CLI::line("  #{$id} - {$order->get_status()} - {$order->get_total()}");
                    }
                    return;
                }
                
                WP_CLI::line("Syncing {$total} orders...");
                
                $progress = \WP_CLI\Utils\make_progress_bar('Syncing orders', $total);
                $success = 0;
                $failed = 0;
                
                foreach ($order_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        // Clear any existing locks
                        delete_transient('dmm_sending_' . $order_id);
                        
                        // Process the order
                        $this->plugin->order_processor->process_order_robust($order);
                        
                        // Check if it was successful
                        if ($order->get_meta('_dmm_delivery_sent') === 'yes') {
                            $success++;
                        } else {
                            $failed++;
                            $error = $order->get_meta('_dmm_error');
                            if ($error) {
                                WP_CLI::debug("Order #{$order_id} failed: {$error}");
                            }
                        }
                    } else {
                        $failed++;
                    }
                    $progress->tick();
                }
                
                $progress->finish();
                
                WP_CLI::line("");
                WP_CLI::success("Synced {$success} orders successfully");
                if ($failed > 0) {
                    WP_CLI::warning("{$failed} orders failed to sync");
                }
            }
        } catch (Exception $e) {
            WP_CLI::error("Sync failed: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up old logs
     *
     * ## OPTIONS
     *
     * [--days=<number>]
     * : Delete logs older than this many days (default: 7)
     *
     * [--type=<type>]
     * : Type of logs to clean: database, file, or all (default: all)
     *
     * [--dry-run]
     * : Show what would be deleted without actually deleting
     *
     * ## EXAMPLES
     *
     *     wp dmm cleanup-logs
     *     wp dmm cleanup-logs --days=30
     *     wp dmm cleanup-logs --type=database --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function cleanup_logs($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $days = isset($assoc_args['days']) ? intval($assoc_args['days']) : 7;
        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'all';
        
        if ($dry_run) {
            WP_CLI::line('DRY RUN MODE - No changes will be made');
            WP_CLI::line('');
        }
        
        try {
            global $wpdb;
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            if ($type === 'database' || $type === 'all') {
                $table_name = $wpdb->prefix . 'dmm_delivery_logs';
                
                // Count logs to be deleted
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE created_at < %s",
                    $cutoff_date
                ));
                
                if ($dry_run) {
                    WP_CLI::line("Would delete {$count} database log entries older than {$days} days");
                } else {
                    WP_CLI::line("Cleaning up database logs...");
                    
                    $deleted = 0;
                    $batch_size = 1000;
                    
                    do {
                        $result = $wpdb->query($wpdb->prepare(
                            "DELETE FROM {$table_name} WHERE created_at < %s ORDER BY created_at ASC LIMIT %d",
                            $cutoff_date,
                            $batch_size
                        ));
                        $deleted += $result;
                        WP_CLI::line("  Deleted {$deleted} entries...");
                    } while ($result > 0);
                    
                    WP_CLI::success("Deleted {$deleted} database log entries");
                }
            }
            
            if ($type === 'file' || $type === 'all') {
                if ($dry_run) {
                    // Count log files
                    $log_dir = DMM_DELIVERY_BRIDGE_PLUGIN_DIR;
                    $log_files = glob($log_dir . 'debug*.log');
                    $cutoff_time = time() - ($days * DAY_IN_SECONDS);
                    $old_files = array_filter($log_files, function($file) use ($cutoff_time) {
                        return filemtime($file) < $cutoff_time;
                    });
                    
                    WP_CLI::line("Would delete " . count($old_files) . " log files older than {$days} days");
                    foreach ($old_files as $file) {
                        WP_CLI::line("  " . basename($file) . " (" . size_format(filesize($file)) . ")");
                    }
                } else {
                    WP_CLI::line("Cleaning up log files...");
                    $stats = $this->plugin->logger->cleanup_all_log_files();
                    
                    WP_CLI::line("  Rotated: {$stats['rotated']} files");
                    WP_CLI::line("  Deleted archives: {$stats['archives_deleted']} files");
                    if ($stats['wordpress_log_cleaned']) {
                        WP_CLI::line("  WordPress debug.log cleaned");
                    }
                    
                    WP_CLI::success("Log file cleanup completed");
                }
            }
        } catch (Exception $e) {
            WP_CLI::error("Cleanup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Clear cache
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Type of cache to clear: transients, object, or all (default: all)
     *
     * ## EXAMPLES
     *
     *     wp dmm clear-cache
     *     wp dmm clear-cache --type=transients
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function clear_cache($args, $assoc_args) {
        $type = isset($assoc_args['type']) ? $assoc_args['type'] : 'all';
        
        try {
            if ($type === 'transients' || $type === 'all') {
                WP_CLI::line("Clearing transients...");
                global $wpdb;
                
                $deleted = $wpdb->query(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_dmm_%' 
                     OR option_name LIKE '_transient_timeout_dmm_%'"
                );
                
                WP_CLI::line("  Deleted {$deleted} transients");
            }
            
            if ($type === 'object' || $type === 'all') {
                if (function_exists('wp_cache_flush')) {
                    WP_CLI::line("Flushing object cache...");
                    wp_cache_flush();
                    WP_CLI::line("  Object cache flushed");
                } else {
                    WP_CLI::line("  Object cache not available");
                }
            }
            
            WP_CLI::success("Cache cleared successfully");
        } catch (Exception $e) {
            WP_CLI::error("Cache clear failed: " . $e->getMessage());
        }
    }
    
    /**
     * Show plugin status
     *
     * ## EXAMPLES
     *
     *     wp dmm status
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function status($args, $assoc_args) {
        try {
            $options = get_option('dmm_delivery_bridge_options', []);
            
            WP_CLI::line("DMM Delivery Bridge Status");
            WP_CLI::line("==========================");
            WP_CLI::line("");
            
            // Plugin settings
            WP_CLI::line("Settings:");
            WP_CLI::line("  Auto-send: " . (isset($options['auto_send']) && $options['auto_send'] === 'yes' ? 'Enabled' : 'Disabled'));
            WP_CLI::line("  Debug mode: " . (isset($options['debug_mode']) && $options['debug_mode'] === 'yes' ? 'Enabled' : 'Disabled'));
            WP_CLI::line("  API Endpoint: " . (isset($options['api_endpoint']) ? $options['api_endpoint'] : 'Not set'));
            WP_CLI::line("");
            
            // Database stats
            global $wpdb;
            $table_name = $wpdb->prefix . 'dmm_delivery_logs';
            $log_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            WP_CLI::line("Database:");
            WP_CLI::line("  Log entries: {$log_count}");
            
            // Order stats
            $pending_orders = wc_get_orders([
                'status' => ['processing', 'completed'],
                'limit' => -1,
                'return' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_dmm_delivery_sent',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);
            $pending_count = count($pending_orders);
            
            $failed_orders = wc_get_orders([
                'limit' => -1,
                'return' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_dmm_error',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            $failed_count = count($failed_orders);
            
            WP_CLI::line("Orders:");
            WP_CLI::line("  Pending sync: {$pending_count}");
            WP_CLI::line("  Failed: {$failed_count}");
            
            // Cache stats
            $cache_service = new DMM_Cache_Service();
            WP_CLI::line("Cache:");
            WP_CLI::line("  Status: " . ($cache_service->is_enabled() ? 'Enabled' : 'Disabled'));
            
            WP_CLI::line("");
        } catch (Exception $e) {
            WP_CLI::error("Status check failed: " . $e->getMessage());
        }
    }
    
    /**
     * Reset failed orders
     *
     * ## OPTIONS
     *
     * [--order-id=<id>]
     * : Reset specific order ID
     *
     * [--all]
     * : Reset all failed orders
     *
     * [--dry-run]
     * : Show what would be reset without actually resetting
     *
     * ## EXAMPLES
     *
     *     wp dmm reset-failed --order-id=123
     *     wp dmm reset-failed --all
     *     wp dmm reset-failed --all --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function reset_failed($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $order_id = isset($assoc_args['order-id']) ? intval($assoc_args['order-id']) : null;
        $all = isset($assoc_args['all']);
        
        if (!$order_id && !$all) {
            WP_CLI::error("Must specify either --order-id or --all");
        }
        
        if ($dry_run) {
            WP_CLI::line('DRY RUN MODE - No changes will be made');
            WP_CLI::line('');
        }
        
        try {
            if ($order_id) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    WP_CLI::error("Order #{$order_id} not found");
                }
                
                if ($dry_run) {
                    WP_CLI::line("Would reset order #{$order_id}");
                    WP_CLI::line("  Would delete: _dmm_error, _dmm_retry_count");
                    return;
                }
                
                $order->delete_meta_data('_dmm_error');
                $order->delete_meta_data('_dmm_retry_count');
                $order->save();
                
                WP_CLI::success("Order #{$order_id} reset successfully");
            } else {
                // Find all failed orders
                $failed_orders = wc_get_orders([
                    'limit' => -1,
                    'return' => 'ids',
                    'meta_query' => [
                        [
                            'key' => '_dmm_error',
                            'compare' => 'EXISTS'
                        ]
                    ]
                ]);
                
                $total = count($failed_orders);
                
                if ($total === 0) {
                    WP_CLI::warning("No failed orders found");
                    return;
                }
                
                if ($dry_run) {
                    WP_CLI::line("Would reset {$total} failed orders:");
                    foreach (array_slice($failed_orders, 0, 10) as $id) {
                        $order = wc_get_order($id);
                        $error = $order->get_meta('_dmm_error');
                        WP_CLI::line("  #{$id} - Error: {$error}");
                    }
                    if ($total > 10) {
                        WP_CLI::line("  ... and " . ($total - 10) . " more");
                    }
                    return;
                }
                
                WP_CLI::line("Resetting {$total} failed orders...");
                
                $progress = \WP_CLI\Utils\make_progress_bar('Resetting orders', $total);
                $reset = 0;
                
                foreach ($failed_orders as $id) {
                    $order = wc_get_order($id);
                    if ($order) {
                        $order->delete_meta_data('_dmm_error');
                        $order->delete_meta_data('_dmm_retry_count');
                        $order->save();
                        $reset++;
                    }
                    $progress->tick();
                }
                
                $progress->finish();
                
                WP_CLI::line("");
                WP_CLI::success("Reset {$reset} orders successfully");
            }
        } catch (Exception $e) {
            WP_CLI::error("Reset failed: " . $e->getMessage());
        }
    }
    
    /**
     * Optimize database tables
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Show what would be optimized without actually optimizing
     *
     * ## EXAMPLES
     *
     *     wp dmm optimize-db
     *     wp dmm optimize-db --dry-run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function optimize_db($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        
        if ($dry_run) {
            WP_CLI::line('DRY RUN MODE - No changes will be made');
            WP_CLI::line('');
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dmm_delivery_logs';
            
            // Check table size
            $table_size = $wpdb->get_var($wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                 FROM information_schema.TABLES 
                 WHERE table_schema = %s 
                 AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if ($dry_run) {
                WP_CLI::line("Would optimize table: {$table_name}");
                WP_CLI::line("  Current size: {$table_size} MB");
                return;
            }
            
            WP_CLI::line("Optimizing database table...");
            WP_CLI::line("  Table: {$table_name}");
            WP_CLI::line("  Current size: {$table_size} MB");
            
            $result = $wpdb->query("OPTIMIZE TABLE {$table_name}");
            
            if ($result !== false) {
                // Get new size
                $new_size = $wpdb->get_var($wpdb->prepare(
                    "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                     FROM information_schema.TABLES 
                     WHERE table_schema = %s 
                     AND table_name = %s",
                    DB_NAME,
                    $table_name
                ));
                
                WP_CLI::success("Table optimized successfully");
                WP_CLI::line("  New size: {$new_size} MB");
                WP_CLI::line("  Space saved: " . round($table_size - $new_size, 2) . " MB");
            } else {
                WP_CLI::error("Optimization failed");
            }
        } catch (Exception $e) {
            WP_CLI::error("Optimization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Export logs
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Export format: csv or json (default: csv)
     *
     * [--output=<file>]
     * : Output file path (default: dmm-logs-YYYY-MM-DD.csv)
     *
     * [--days=<number>]
     * : Export logs from last N days (default: 30)
     *
     * ## EXAMPLES
     *
     *     wp dmm export-logs
     *     wp dmm export-logs --format=json --output=/tmp/logs.json
     *     wp dmm export-logs --days=7
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function export_logs($args, $assoc_args) {
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'csv';
        $output = isset($assoc_args['output']) ? $assoc_args['output'] : null;
        $days = isset($assoc_args['days']) ? intval($assoc_args['days']) : 30;
        
        if (!in_array($format, ['csv', 'json'])) {
            WP_CLI::error("Invalid format. Must be 'csv' or 'json'");
        }
        
        if (!$output) {
            $output = 'dmm-logs-' . date('Y-m-d') . '.' . $format;
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'dmm_delivery_logs';
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            WP_CLI::line("Exporting logs from last {$days} days...");
            
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE created_at >= %s ORDER BY created_at DESC",
                $cutoff_date
            ), ARRAY_A);
            
            $total = count($logs);
            WP_CLI::line("Found {$total} log entries");
            
            if ($format === 'csv') {
                $fp = fopen($output, 'w');
                
                if ($total > 0) {
                    // Write header
                    fputcsv($fp, array_keys($logs[0]));
                    
                    // Write data
                    foreach ($logs as $log) {
                        fputcsv($fp, $log);
                    }
                }
                
                fclose($fp);
            } else {
                file_put_contents($output, wp_json_encode($logs, JSON_PRETTY_PRINT));
            }
            
            $file_size = size_format(filesize($output));
            WP_CLI::success("Exported {$total} log entries to {$output} ({$file_size})");
        } catch (Exception $e) {
            WP_CLI::error("Export failed: " . $e->getMessage());
        }
    }
    
    /**
     * Run scheduled maintenance tasks
     *
     * ## OPTIONS
     *
     * [--task=<task>]
     * : Specific task to run: cleanup-logs, check-stuck-jobs, or all (default: all)
     *
     * ## EXAMPLES
     *
     *     wp dmm maintenance
     *     wp dmm maintenance --task=cleanup-logs
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function maintenance($args, $assoc_args) {
        $task = isset($assoc_args['task']) ? $assoc_args['task'] : 'all';
        
        try {
            WP_CLI::line("Running maintenance tasks...");
            WP_CLI::line("");
            
            if ($task === 'cleanup-logs' || $task === 'all') {
                WP_CLI::line("Cleaning up old logs...");
                $this->plugin->scheduler->cleanup_old_logs();
                WP_CLI::success("Log cleanup completed");
                WP_CLI::line("");
            }
            
            if ($task === 'check-stuck-jobs' || $task === 'all') {
                WP_CLI::line("Checking for stuck jobs...");
                $this->plugin->scheduler->check_stuck_jobs();
                WP_CLI::success("Stuck job check completed");
                WP_CLI::line("");
            }
            
            WP_CLI::success("Maintenance tasks completed");
        } catch (Exception $e) {
            WP_CLI::error("Maintenance failed: " . $e->getMessage());
        }
    }
}

// Register WP-CLI commands
WP_CLI::add_command('dmm', 'DMM_CLI_Commands');


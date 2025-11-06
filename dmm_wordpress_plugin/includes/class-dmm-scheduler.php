<?php
/**
 * Enhanced Scheduler class for DMM Delivery Bridge
 * Implements job queuing with priorities, status monitoring, and retry logic
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Scheduler
 */
class DMM_Scheduler {
    
    /**
     * Job priorities
     */
    const PRIORITY_HIGH = 10;
    const PRIORITY_NORMAL = 20;
    const PRIORITY_LOW = 30;
    
    /**
     * Job groups
     */
    const GROUP_IMMEDIATE = 'dmm_immediate';
    const GROUP_SCHEDULED = 'dmm_scheduled';
    const GROUP_RETRY = 'dmm_retry';
    
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
     * Maximum retry attempts
     *
     * @var int
     */
    private $max_retries;
    
    /**
     * Constructor
     *
     * @param array       $options Plugin options
     * @param DMM_Logger $logger Logger instance
     */
    public function __construct($options = [], $logger = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->logger = $logger ?: new DMM_Logger($this->options);
        $this->max_retries = isset($this->options['max_retries']) ? (int) $this->options['max_retries'] : 5;
    }
    
    /**
     * Add custom cron interval for ACS sync
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_acs_sync_cron_interval($schedules) {
        // Adaptive sync - dispatch every minute to check due shipments
        $schedules['dmm_acs_dispatch_interval'] = [
            'interval' => MINUTE_IN_SECONDS, // Every minute
            'display' => __('Every Minute (ACS Dispatch)', 'dmm-delivery-bridge')
        ];
        
        // Legacy intervals for backward compatibility
        $schedules['dmm_acs_sync_interval'] = [
            'interval' => 4 * HOUR_IN_SECONDS, // Every 4 hours
            'display' => __('Every 4 Hours (ACS Sync)', 'dmm-delivery-bridge')
        ];
        
        $schedules['dmm_acs_sync_frequent'] = [
            'interval' => 2 * HOUR_IN_SECONDS, // Every 2 hours
            'display' => __('Every 2 Hours (ACS Sync)', 'dmm-delivery-bridge')
        ];
        
        $schedules['dmm_acs_sync_daily'] = [
            'interval' => 24 * HOUR_IN_SECONDS, // Daily
            'display' => __('Daily (ACS Sync)', 'dmm-delivery-bridge')
        ];
        
        return $schedules;
    }
    
    /**
     * Cleanup old logs (database and file logs)
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        // Clean up database logs
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        $retention_days = $this->logger->get_log_retention_days();
        
        if ($retention_days <= 0) {
            return; // Don't delete if retention is disabled
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Use index on created_at for optimal DELETE performance
        // Limit delete batch size to avoid long-running queries
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE created_at < %s 
             ORDER BY created_at ASC 
             LIMIT 1000",
            $cutoff_date
        ));
        
        if ($deleted > 0) {
            $this->logger->debug_log("Cleaned up {$deleted} old database log entries");
        }
        
        // Clean up file logs (rotation and archive cleanup)
        $file_stats = $this->logger->cleanup_all_log_files();
        
        if ($file_stats['rotated'] > 0 || $file_stats['archives_deleted'] > 0 || $file_stats['wordpress_log_cleaned']) {
            $this->logger->debug_log(sprintf(
                "File log cleanup: %d rotated, %d archives deleted, WordPress log cleaned: %s",
                $file_stats['rotated'],
                $file_stats['archives_deleted'],
                $file_stats['wordpress_log_cleaned'] ? 'yes' : 'no'
            ));
        }
    }
    
    /**
     * Check for stuck jobs and update admin notices
     * This method now uses the enhanced monitoring functionality
     */
    public function check_stuck_jobs() {
        // Use the enhanced monitoring method
        $health = $this->monitor_job_health(true);
        
        // Update admin notices based on health status
        $overall_stats = $health['statistics']['overall'] ?? [];
        
        // Update stuck jobs notice
        $stuck_count = $overall_stats['stuck'] ?? 0;
        if ($stuck_count > 0) {
            update_option('dmm_stuck_jobs_count', $stuck_count);
            update_option('dmm_stuck_jobs_notice', true);
        } else {
            delete_option('dmm_stuck_jobs_count');
            delete_option('dmm_stuck_jobs_notice');
        }
        
        // Update failed jobs notice
        $failed_count = $overall_stats['failed'] ?? 0;
        if ($failed_count > 0) {
            update_option('dmm_failed_jobs_count', $failed_count);
            update_option('dmm_failed_jobs_notice', true);
        } else {
            delete_option('dmm_failed_jobs_count');
            delete_option('dmm_failed_jobs_notice');
        }
        
        // Store overall health status
        update_option('dmm_job_health_status', [
            'healthy' => $health['healthy'],
            'issues' => $health['issues'],
            'checked_at' => time()
        ]);
        
        // Clean up old jobs (keep last 7 days)
        $this->cleanup_old_jobs(7);
    }
    
    /**
     * Schedule cron jobs on activation
     */
    public function schedule_cron_jobs() {
        // Schedule adaptive dispatch (if not already scheduled)
        if (!wp_next_scheduled('dmm_dispatch_due_shipments')) {
            wp_schedule_event(time(), 'dmm_acs_dispatch_interval', 'dmm_dispatch_due_shipments');
        }
        
        // Legacy sync for backward compatibility
        if (!wp_next_scheduled('dmm_acs_sync_shipments')) {
            wp_schedule_event(time(), 'dmm_acs_sync_interval', 'dmm_acs_sync_shipments');
        }
        
        // Cleanup tasks
        if (!wp_next_scheduled('dmm_cleanup_old_logs')) {
            wp_schedule_event(time(), 'daily', 'dmm_cleanup_old_logs');
        }
        
        // Log rotation check (runs more frequently to catch large files quickly)
        if (!wp_next_scheduled('dmm_rotate_logs')) {
            wp_schedule_event(time(), 'twicedaily', 'dmm_rotate_logs');
        }
        
        if (!wp_next_scheduled('dmm_check_stuck_jobs')) {
            wp_schedule_event(time(), 'hourly', 'dmm_check_stuck_jobs');
        }
    }
    
    /**
     * Rotate log files if they exceed size limits
     * This runs more frequently than full cleanup to catch large files quickly
     */
    public function rotate_logs() {
        $this->logger->cleanup_all_log_files();
    }
    
    /**
     * Queue an immediate job (async execution)
     *
     * @param string $hook Action hook name
     * @param array  $args Action arguments
     * @param string $group Action group (default: immediate)
     * @param int    $priority Job priority (lower = higher priority)
     * @return int|false Action ID on success, false on failure
     */
    public function queue_immediate($hook, $args = [], $group = self::GROUP_IMMEDIATE, $priority = self::PRIORITY_NORMAL) {
        if (!function_exists('as_enqueue_async_action')) {
            $this->logger->debug_log("Action Scheduler not available for immediate job: {$hook}");
            return false;
        }
        
        // Add priority to args for tracking
        $args['_priority'] = $priority;
        $args['_queued_at'] = time();
        
        $action_id = as_enqueue_async_action($hook, $args, $group);
        
        if ($action_id) {
            $this->logger->debug_log("Queued immediate job: {$hook} (ID: {$action_id}, Priority: {$priority})");
        } else {
            $this->logger->debug_log("Failed to queue immediate job: {$hook}");
        }
        
        return $action_id;
    }
    
    /**
     * Schedule a job for later execution
     *
     * @param int    $timestamp When to execute the job
     * @param string $hook Action hook name
     * @param array  $args Action arguments
     * @param string $group Action group (default: scheduled)
     * @param int    $priority Job priority (lower = higher priority)
     * @return int|false Action ID on success, false on failure
     */
    public function schedule($timestamp, $hook, $args = [], $group = self::GROUP_SCHEDULED, $priority = self::PRIORITY_NORMAL) {
        if (!function_exists('as_schedule_single_action')) {
            $this->logger->debug_log("Action Scheduler not available for scheduled job: {$hook}");
            return false;
        }
        
        // Add priority to args for tracking
        $args['_priority'] = $priority;
        $args['_queued_at'] = time();
        $args['_scheduled_for'] = $timestamp;
        
        $action_id = as_schedule_single_action($timestamp, $hook, $args, $group);
        
        if ($action_id) {
            $delay = $timestamp - time();
            $this->logger->debug_log(
                sprintf(
                    "Scheduled job: %s (ID: %d, Priority: %d, Delay: %d seconds)",
                    $hook,
                    $action_id,
                    $priority,
                    $delay
                )
            );
        } else {
            $this->logger->debug_log("Failed to schedule job: {$hook}");
        }
        
        return $action_id;
    }
    
    /**
     * Schedule a retry job with exponential backoff
     * 
     * Implements exponential backoff retry strategy to handle transient failures.
     * Each retry waits progressively longer before attempting again:
     * - Retry 1: 1 minute (60 seconds)
     * - Retry 2: 2 minutes (120 seconds)
     * - Retry 3: 4 minutes (240 seconds)
     * - Retry 4: 8 minutes (480 seconds)
     * - Retry 5: 16 minutes (960 seconds, maximum)
     * 
     * Formula: min(60 * 2^(retry_count - 1), 960) seconds
     * 
     * This prevents overwhelming a failing API while still retrying quickly enough
     * for transient issues to resolve. Retries are scheduled with low priority to
     * not interfere with new orders.
     *
     * @param string $hook Action hook name (e.g., 'dmm_send_order')
     * @param array  $args Action arguments (must include 'order_id' and will include 'retry_count')
     * @param int    $retry_count Current retry attempt number (1 = first retry)
     * @return int|false Action ID on success, false if max retries reached
     * @since 1.0.0
     */
    public function schedule_retry($hook, $args = [], $retry_count = 1) {
        if ($retry_count > $this->max_retries) {
            $this->logger->debug_log(
                sprintf(
                    "Max retries reached for job: %s (attempt %d/%d)",
                    $hook,
                    $retry_count,
                    $this->max_retries
                )
            );
            return false;
        }
        
        // Calculate exponential backoff delay
        // Formula: min(60 * 2^(retry_count - 1), 960) seconds
        // Results: 1min, 2min, 4min, 8min, 16min (max)
        $delay = min(60 * pow(2, $retry_count - 1), 960);
        $timestamp = time() + $delay;
        
        // Ensure retry_count is in args
        $args['retry_count'] = $retry_count;
        $args['_priority'] = self::PRIORITY_LOW; // Retries have lower priority
        $args['_queued_at'] = time();
        $args['_scheduled_for'] = $timestamp;
        $args['_is_retry'] = true;
        
        $action_id = $this->schedule($timestamp, $hook, $args, self::GROUP_RETRY, self::PRIORITY_LOW);
        
        if ($action_id) {
            $this->logger->log_structured('job_retry_scheduled', [
                'hook' => $hook,
                'action_id' => $action_id,
                'retry_count' => $retry_count,
                'delay_seconds' => $delay,
                'max_retries' => $this->max_retries
            ]);
        }
        
        return $action_id;
    }
    
    /**
     * Get job status information
     *
     * @param string $hook Action hook name (optional)
     * @param string $group Action group (optional)
     * @param string $status Job status (optional)
     * @return array Job status statistics
     */
    public function get_job_status($hook = '', $group = '', $status = '') {
        if (!class_exists('ActionScheduler')) {
            return [
                'available' => false,
                'message' => 'Action Scheduler not available'
            ];
        }
        
        $query_args = [
            'per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if ($hook) {
            $query_args['hook'] = $hook;
        }
        
        if ($group) {
            $query_args['group'] = $group;
        }
        
        if ($status) {
            $query_args['status'] = $status;
        }
        
        $stats = [
            'available' => true,
            'pending' => 0,
            'in_progress' => 0,
            'complete' => 0,
            'failed' => 0,
            'canceled' => 0,
            'total' => 0,
            'stuck' => 0,
            'recent_failures' => []
        ];
        
        // Get counts for each status
        $statuses = ['pending', 'in-progress', 'complete', 'failed', 'canceled'];
        foreach ($statuses as $status_type) {
            $query = $query_args;
            $query['status'] = $status_type;
            $actions = as_get_scheduled_actions($query);
            $count = count($actions);
            $stats[$status_type === 'in-progress' ? 'in_progress' : $status_type] = $count;
            $stats['total'] += $count;
        }
        
        // Check for stuck jobs (in-progress for more than 30 minutes)
        $stuck_threshold = 30 * MINUTE_IN_SECONDS;
        $in_progress_query = $query_args;
        $in_progress_query['status'] = 'in-progress';
        $in_progress_actions = as_get_scheduled_actions($in_progress_query);
        
        foreach ($in_progress_actions as $action) {
            $schedule = $action->get_schedule();
            if ($schedule && $schedule->get_date()) {
                $scheduled_time = $schedule->get_date()->getTimestamp();
                if ($scheduled_time < time() - $stuck_threshold) {
                    $stats['stuck']++;
                }
            }
        }
        
        // Get recent failures
        $failed_query = $query_args;
        $failed_query['status'] = 'failed';
        $failed_query['per_page'] = 10;
        $failed_actions = as_get_scheduled_actions($failed_query);
        
        foreach ($failed_actions as $action) {
            $stats['recent_failures'][] = [
                'id' => $action->get_id(),
                'hook' => $action->get_hook(),
                'group' => $action->get_group(),
                'args' => $action->get_args(),
                'last_attempt' => $action->get_date()->format('Y-m-d H:i:s')
            ];
        }
        
        return $stats;
    }
    
    /**
     * Monitor job health and log statistics
     *
     * @param bool $log_stats Whether to log statistics
     * @return array Health status
     */
    public function monitor_job_health($log_stats = true) {
        $health = [
            'healthy' => true,
            'issues' => [],
            'statistics' => []
        ];
        
        // Check all job groups
        $groups = [self::GROUP_IMMEDIATE, self::GROUP_SCHEDULED, self::GROUP_RETRY];
        
        foreach ($groups as $group) {
            $stats = $this->get_job_status('', $group);
            
            if (!$stats['available']) {
                continue;
            }
            
            $health['statistics'][$group] = $stats;
            
            // Check for issues
            if ($stats['stuck'] > 0) {
                $health['healthy'] = false;
                $health['issues'][] = sprintf(
                    '%d stuck job(s) in group: %s',
                    $stats['stuck'],
                    $group
                );
            }
            
            if ($stats['failed'] > 10) {
                $health['healthy'] = false;
                $health['issues'][] = sprintf(
                    '%d failed job(s) in group: %s (threshold: 10)',
                    $stats['failed'],
                    $group
                );
            }
            
            if ($stats['in_progress'] > 50) {
                $health['healthy'] = false;
                $health['issues'][] = sprintf(
                    '%d in-progress job(s) in group: %s (threshold: 50)',
                    $stats['in_progress'],
                    $group
                );
            }
        }
        
        // Overall statistics
        $overall_stats = $this->get_job_status();
        $health['statistics']['overall'] = $overall_stats;
        
        if ($log_stats) {
            $this->logger->log_structured('job_health_check', [
                'healthy' => $health['healthy'],
                'issues' => $health['issues'],
                'statistics' => $health['statistics']
            ]);
        }
        
        return $health;
    }
    
    /**
     * Cancel a scheduled job
     *
     * @param int $action_id Action ID
     * @return bool True on success, false on failure
     */
    public function cancel_job($action_id) {
        if (!function_exists('as_unschedule_action')) {
            return false;
        }
        
        $result = as_unschedule_action('', [], '', $action_id);
        
        if ($result) {
            $this->logger->debug_log("Canceled job ID: {$action_id}");
        }
        
        return $result !== false;
    }
    
    /**
     * Cancel all jobs for a specific hook and group
     *
     * @param string $hook Action hook name
     * @param array  $args Action arguments (optional)
     * @param string $group Action group (optional)
     * @return int Number of jobs canceled
     */
    public function cancel_jobs($hook, $args = [], $group = '') {
        if (!function_exists('as_unschedule_all_actions')) {
            return 0;
        }
        
        $canceled = as_unschedule_all_actions($hook, $args, $group);
        
        if ($canceled > 0) {
            $this->logger->debug_log(
                sprintf(
                    "Canceled %d job(s) for hook: %s (group: %s)",
                    $canceled,
                    $hook,
                    $group ?: 'all'
                )
            );
        }
        
        return $canceled;
    }
    
    /**
     * Get jobs by status with pagination
     *
     * @param string $status Job status
     * @param string $hook Action hook (optional)
     * @param string $group Action group (optional)
     * @param int    $per_page Number of jobs per page
     * @param int    $page Page number
     * @return array Jobs and pagination info
     */
    public function get_jobs($status = 'pending', $hook = '', $group = '', $per_page = 20, $page = 1) {
        if (!class_exists('ActionScheduler')) {
            return [
                'jobs' => [],
                'total' => 0,
                'pages' => 0,
                'page' => $page
            ];
        }
        
        $query_args = [
            'status' => $status,
            'per_page' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        if ($hook) {
            $query_args['hook'] = $hook;
        }
        
        if ($group) {
            $query_args['group'] = $group;
        }
        
        $actions = as_get_scheduled_actions($query_args);
        
        $jobs = [];
        foreach ($actions as $action) {
            $schedule = $action->get_schedule();
            $jobs[] = [
                'id' => $action->get_id(),
                'hook' => $action->get_hook(),
                'group' => $action->get_group(),
                'args' => $action->get_args(),
                'status' => $action->get_status(),
                'scheduled_date' => $schedule && $schedule->get_date() ? $schedule->get_date()->format('Y-m-d H:i:s') : null,
                'last_attempt' => $action->get_date() ? $action->get_date()->format('Y-m-d H:i:s') : null
            ];
        }
        
        // Get total count for pagination
        $count_query = $query_args;
        unset($count_query['offset']);
        $count_query['per_page'] = -1;
        $all_actions = as_get_scheduled_actions($count_query);
        $total = count($all_actions);
        
        return [
            'jobs' => $jobs,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'page' => $page
        ];
    }
    
    /**
     * Clean up old completed/failed jobs
     *
     * @param int $days_old Number of days to keep jobs
     * @return array Cleanup statistics
     */
    public function cleanup_old_jobs($days_old = 7) {
        if (!class_exists('ActionScheduler_Store')) {
            return [
                'cleaned' => 0,
                'message' => 'Action Scheduler not available'
            ];
        }
        
        $cutoff_date = time() - ($days_old * DAY_IN_SECONDS);
        $store = ActionScheduler_Store::instance();
        
        $cleaned = 0;
        $statuses = ['complete', 'failed', 'canceled'];
        
        foreach ($statuses as $status) {
            $query_args = [
                'status' => $status,
                'per_page' => 100,
                'date' => date('Y-m-d H:i:s', $cutoff_date),
                'date_compare' => '<'
            ];
            
            $actions = as_get_scheduled_actions($query_args);
            
            foreach ($actions as $action) {
                $action_date = $action->get_date();
                if ($action_date && $action_date->getTimestamp() < $cutoff_date) {
                    $store->delete_action($action->get_id());
                    $cleaned++;
                }
            }
        }
        
        if ($cleaned > 0) {
            $this->logger->debug_log("Cleaned up {$cleaned} old job(s) older than {$days_old} days");
        }
        
        return [
            'cleaned' => $cleaned,
            'days_old' => $days_old
        ];
    }
}


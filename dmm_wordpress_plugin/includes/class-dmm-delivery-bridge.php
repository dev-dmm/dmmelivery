<?php
/**
 * Main plugin class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main DMM Delivery Bridge class
 */
class DMM_Delivery_Bridge {
    
    /**
     * Single instance of the class
     *
     * @var DMM_Delivery_Bridge
     */
    private static $instance = null;
    
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
    public $logger;
    
    /**
     * API Client instance
     *
     * @var DMM_API_Client
     */
    public $api_client;
    
    /**
     * Order Processor instance
     *
     * @var DMM_Order_Processor
     */
    public $order_processor;
    
    /**
     * Database instance
     *
     * @var DMM_Database
     */
    public $database;
    
    /**
     * Scheduler instance
     *
     * @var DMM_Scheduler
     */
    public $scheduler;
    
    /**
     * Admin instance
     *
     * @var DMM_Admin
     */
    public $admin;
    
    /**
     * AJAX Handlers instance
     *
     * @var DMM_AJAX_Handlers
     */
    public $ajax_handlers;
    
    /**
     * Performance Monitor instance
     *
     * @var DMM_Performance_Monitor
     */
    public $performance_monitor;
    
    /**
     * Get single instance of the plugin (Singleton pattern)
     * 
     * Ensures only one instance of the plugin class exists throughout the request lifecycle.
     * This is critical for maintaining state consistency and preventing duplicate hook registrations.
     *
     * @return DMM_Delivery_Bridge The singleton instance of the plugin
     * @since 1.0.0
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor (Singleton pattern)
     * 
     * Initializes all plugin components including logger, API client, database,
     * scheduler, and order processor. Also sets up WordPress hooks for activation,
     * deactivation, and plugin initialization.
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->options = get_option('dmm_delivery_bridge_options', []);
        
        // Initialize components
        $this->logger = new DMM_Logger($this->options);
        $this->performance_monitor = new DMM_Performance_Monitor($this->options, $this->logger);
        $this->api_client = new DMM_API_Client($this->options, $this->logger, null, null, $this->performance_monitor);
        $this->database = new DMM_Database();
        $this->scheduler = new DMM_Scheduler($this->options, $this->logger);
        // Pass scheduler to order processor so it can use enhanced scheduling
        $this->order_processor = new DMM_Order_Processor($this->options, $this->api_client, $this->logger, $this->scheduler);
        
        // Initialize admin and AJAX handlers (will be loaded later)
        if (is_admin()) {
            // Admin class is already loaded in main plugin file
            require_once DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'includes/class-dmm-ajax-handlers.php';
            $this->admin = new DMM_Admin($this->options, $this);
            $this->ajax_handlers = new DMM_AJAX_Handlers($this->options, $this);
        }
        
        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init']);
        
        // Activation/Deactivation hooks
        register_activation_hook(DMM_DELIVERY_BRIDGE_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(DMM_DELIVERY_BRIDGE_PLUGIN_FILE, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin after WordPress and WooCommerce are loaded
     * 
     * This method performs critical initialization tasks:
     * - Verifies WooCommerce is active
     * - Registers error handlers to prevent plugin crashes
     * - Loads text domain for internationalization
     * - Registers courier providers
     * - Sets up WooCommerce order hooks
     * - Registers admin menus and settings
     * - Schedules cleanup tasks
     * 
     * Uses action flags to prevent duplicate hook registrations.
     *
     * @return void
     * @since 1.0.0
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        // CRITICAL: Add error handler to prevent plugin from crashing WooCommerce
        set_error_handler([$this, 'handle_plugin_errors']);
        register_shutdown_function([$this, 'handle_plugin_shutdown']);
        
        // Prevent double hook registration
        if (did_action('dmm_delivery_bridge_hooks_registered')) {
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('dmm-delivery-bridge', false, dirname(plugin_basename(DMM_DELIVERY_BRIDGE_PLUGIN_FILE)) . '/languages');
        
        // Register multi-courier providers
        \DMM\Courier\Registry::register(new \DMM\Courier\AcsProvider());
        \DMM\Courier\Registry::register(new \DMM\Courier\GenikiProvider());
        \DMM\Courier\Registry::register(new \DMM\Courier\EltaProvider());
        \DMM\Courier\Registry::register(new \DMM\Courier\SpeedexProvider());
        \DMM\Courier\Registry::register(new \DMM\Courier\GenericProvider());
        
        // Allow 3rd parties to register more providers
        do_action('dmm_register_courier_providers', \DMM\Courier\Registry::class);
        
        // Initialize admin
        if (is_admin() && $this->admin) {
            add_action('admin_menu', [$this->admin, 'add_admin_menu']);
            add_action('admin_init', [$this->admin, 'admin_init']);
        }
        
        // Hook into WooCommerce order processing - SAFE ASYNC TRIGGERS ONLY
        add_action('woocommerce_payment_complete', [$this->order_processor, 'queue_send_to_api'], 20, 1);
        add_action('woocommerce_order_status_changed', [$this->order_processor, 'maybe_queue_send_on_status'], 20, 4);
        add_action('woocommerce_order_status_processing', [$this->order_processor, 'queue_send_to_api'], 20);
        add_action('woocommerce_order_status_completed', [$this->order_processor, 'queue_send_to_api'], 20);
        
        // Subscriptions support
        add_action('wcs_renewal_order_created', [$this->order_processor, 'queue_send_to_api'], 10, 1);
        
        // Add custom order meta box
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
        
        // Add custom column to orders list (legacy)
        add_filter('manage_edit-shop_order_columns', [$this, 'add_courier_voucher_column'], 20);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_courier_voucher_column'], 10, 2);
        
        // HPOS support for custom column (always add, WooCommerce will handle if HPOS is enabled)
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_courier_voucher_column'], 20);
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'render_courier_voucher_column_hpos'], 10, 2);
        
        // Action Scheduler handlers
        add_action('dmm_send_order', [$this->order_processor, 'process_order_async'], 10, 1);
        add_action('dmm_update_order', [$this, 'process_order_update'], 10, 1);
        add_action('dmm_bulk_process_orders', [$this, 'process_bulk_orders'], 10, 2);
        
        // Database table creation
        add_action('init', [$this->database, 'create_dedupe_table']);
        
        // Cleanup tasks
        add_action('dmm_cleanup_old_logs', [$this->scheduler, 'cleanup_old_logs']);
        add_action('dmm_rotate_logs', [$this->scheduler, 'rotate_logs']);
        add_action('dmm_check_stuck_jobs', [$this->scheduler, 'check_stuck_jobs']);
        
        // Add custom cron interval
        add_filter('cron_schedules', [$this->scheduler, 'add_acs_sync_cron_interval']);
        
        // Mark hooks as registered
        do_action('dmm_delivery_bridge_hooks_registered');
    }
    
    /**
     * Handle plugin errors to prevent WooCommerce crashes
     *
     * @param int    $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int    $errline Error line
     * @return bool True to suppress error, false to let it propagate
     */
    public function handle_plugin_errors($errno, $errstr, $errfile, $errline) {
        // Check if error is from plugin directory
        $plugin_dir = dirname(DMM_DELIVERY_BRIDGE_PLUGIN_FILE);
        
        // Normalize paths for comparison (handle Windows/Unix path differences)
        $plugin_dir_normalized = str_replace('\\', '/', $plugin_dir);
        $errfile_normalized = str_replace('\\', '/', $errfile);
        
        // Check if error file is within plugin directory
        if (strpos($errfile_normalized, $plugin_dir_normalized) === false) {
            return false; // Let WordPress handle errors from other plugins/files
        }
        
        // Log with context using sprintf for better formatting
        $error_message = sprintf(
            'DMM Delivery Bridge - Error [%s]: %s in %s on line %d',
            $errno,
            $errstr,
            $errfile,
            $errline
        );
        
        // Use logger if available, otherwise fall back to error_log
        if (isset($this->logger)) {
            $this->logger->debug_log($error_message);
        } else {
            // Fallback for errors before logger is initialized
            error_log($error_message);
        }
        
        // Only suppress non-fatal errors
        // Fatal errors (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR) should propagate
        $non_fatal_errors = [
            E_WARNING,
            E_NOTICE,
            E_USER_WARNING,
            E_USER_NOTICE,
            E_DEPRECATED,
            E_USER_DEPRECATED,
            E_STRICT
        ];
        
        return in_array($errno, $non_fatal_errors);
    }
    
    /**
     * Handle plugin shutdown to catch fatal errors
     * 
     * Registered as a shutdown function to catch fatal errors that occur
     * during plugin execution. Only logs errors from the plugin's own files
     * to avoid interfering with other plugins or WordPress core.
     * 
     * This is critical for debugging production issues where fatal errors
     * would otherwise be silently ignored.
     *
     * @return void
     * @since 1.0.0
     */
    public function handle_plugin_shutdown() {
        $error = error_get_last();
        
        if (!$error) {
            return;
        }
        
        // Check if error is from plugin directory
        $plugin_dir = dirname(DMM_DELIVERY_BRIDGE_PLUGIN_FILE);
        
        // Normalize paths for comparison (handle Windows/Unix path differences)
        $plugin_dir_normalized = str_replace('\\', '/', $plugin_dir);
        $error_file_normalized = str_replace('\\', '/', $error['file']);
        
        // Check if error file is within plugin directory
        if (strpos($error_file_normalized, $plugin_dir_normalized) === false) {
            return; // Not our plugin's error
        }
        
        // Log fatal error with context using sprintf
        $error_message = sprintf(
            'DMM Delivery Bridge - Fatal Error [%s]: %s in %s on line %d',
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        // Use logger if available, otherwise fall back to error_log for fatal errors
        if (isset($this->logger)) {
            $this->logger->debug_log($error_message);
        } else {
            // Fallback for fatal errors before logger is initialized
            error_log($error_message);
        }
    }
    
    /**
     * Plugin activation handler
     * 
     * Called when the plugin is activated. Performs the following tasks:
     * - Sets default plugin options (merging with existing if present)
     * - Creates required database tables (logs, shipments, deduplication)
     * - Schedules cron jobs for cleanup and monitoring
     * 
     * This method is safe to call multiple times (idempotent).
     *
     * @return void
     * @since 1.0.0
     */
    public function activate() {
        // Use Settings API default options (ensures consistency)
        $default_options = DMM_Admin::get_default_options();
        
        if (!get_option('dmm_delivery_bridge_options')) {
            add_option('dmm_delivery_bridge_options', $default_options);
        } else {
            // Merge any new default options with existing ones
            $existing = get_option('dmm_delivery_bridge_options', []);
            $merged = array_merge($default_options, $existing);
            update_option('dmm_delivery_bridge_options', $merged);
        }
        
        // Create tables
        $this->database->create_tables();
        $this->database->create_dedupe_table();
        
        // Schedule cron jobs
        $this->scheduler->schedule_cron_jobs();
    }
    
    /**
     * Plugin deactivation handler
     * 
     * Called when the plugin is deactivated. Currently performs minimal cleanup
     * as most data should persist. Cron jobs are automatically unscheduled by WordPress.
     * 
     * Note: This does NOT delete plugin data or options. Use uninstall hook for that.
     *
     * @return void
     * @since 1.0.0
     */
    public function deactivate() {
        // Clean up if needed
    }
    
    /**
     * Display admin notice when WooCommerce is not active
     * 
     * Shows an error notice in the WordPress admin area informing users
     * that WooCommerce must be installed and activated for this plugin to function.
     *
     * @return void
     * @since 1.0.0
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('DMM Delivery Bridge requires WooCommerce to be installed and activated.', 'dmm-delivery-bridge'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Check if HPOS (High-Performance Order Storage) is enabled
     *
     * @return bool
     */
    private function is_hpos_enabled() {
        if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            return false;
        }
        
        return \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('custom_order_tables');
    }
    
    /**
     * Add order meta box to WooCommerce order edit screen
     * 
     * HPOS-compatible: Works with both traditional post-based orders and
     * High-Performance Order Storage (HPOS). Automatically detects which
     * system is in use and registers the meta box accordingly.
     * 
     * The meta box displays DMM Delivery status and tracking information
     * in the order edit screen sidebar.
     *
     * @return void
     * @since 1.0.0
     */
    public function add_order_meta_box() {
        // HPOS-compatible: register meta box for both traditional and HPOS screens
        if ($this->is_hpos_enabled()) {
            // HPOS: use the order screen ID
            $screen = wc_get_page_screen_id('shop-order');
        } else {
            // Traditional: use post type
            $screen = 'shop_order';
        }
        
        add_meta_box(
            'dmm-delivery-bridge',
            __('DMM Delivery Status', 'dmm-delivery-bridge'),
            [$this, 'order_meta_box_content'],
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Render order meta box content
     * 
     * HPOS-compatible: Accepts WP_Post (legacy), WC_Order (HPOS), or order ID.
     * Displays the current delivery status, DMM order ID, and shipment ID if available.
     * 
     * Status indicators:
     * - Green checkmark: Successfully sent to DMM Delivery
     * - Red X: Failed to send
     * - Plain text: Not yet sent
     *
     * @param \WP_Post|\WC_Order|int $post_or_order Post object (legacy), Order object (HPOS), or Order ID
     * @return void
     * @since 1.0.0
     */
    public function order_meta_box_content($post_or_order) {
        // HPOS-compatible: handle both WP_Post and WC_Order objects
        if ($post_or_order instanceof \WC_Order) {
            $order = $post_or_order;
        } elseif ($post_or_order instanceof \WP_Post) {
            // Legacy: get order from post ID
            $order = wc_get_order($post_or_order->ID);
        } elseif (is_numeric($post_or_order)) {
            // Direct order ID
            $order = wc_get_order($post_or_order);
        } else {
            return; // Invalid parameter
        }
        
        if (!$order) {
            return;
        }
        
        // Use HPOS-compatible order meta methods
        $sent_status = $order->get_meta('_dmm_delivery_sent');
        $dmm_order_id = $order->get_meta('_dmm_delivery_order_id');
        $dmm_shipment_id = $order->get_meta('_dmm_delivery_shipment_id');
        
        ?>
        <div class="dmm-delivery-status">
            <?php if ($sent_status === 'yes'): ?>
                <p><strong style="color: green;">✓ <?php _e('Sent to DMM Delivery', 'dmm-delivery-bridge'); ?></strong></p>
                <?php if ($dmm_order_id): ?>
                    <p><small><?php _e('Order ID:', 'dmm-delivery-bridge'); ?> <?php echo esc_html($dmm_order_id); ?></small></p>
                <?php endif; ?>
                <?php if ($dmm_shipment_id): ?>
                    <p><small><?php _e('Shipment ID:', 'dmm-delivery-bridge'); ?> <?php echo esc_html($dmm_shipment_id); ?></small></p>
                <?php endif; ?>
            <?php elseif ($sent_status === 'failed'): ?>
                <p><strong style="color: red;">✗ <?php _e('Failed to send', 'dmm-delivery-bridge'); ?></strong></p>
            <?php else: ?>
                <p><?php _e('Not sent yet', 'dmm-delivery-bridge'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add custom column to WooCommerce orders list
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_courier_voucher_column($columns) {
        // Insert the column before the "Total" column
        $new_columns = [];
        foreach ($columns as $key => $column) {
            if ($key === 'order_total') {
                $new_columns['dmm_courier_voucher'] = __('Courier Voucher', 'dmm-delivery-bridge');
            }
            $new_columns[$key] = $column;
        }
        
        // If "order_total" doesn't exist, append to the end
        if (!isset($columns['order_total'])) {
            $new_columns['dmm_courier_voucher'] = __('Courier Voucher', 'dmm-delivery-bridge');
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom column content for legacy WooCommerce orders
     * 
     * @param string $column Column name
     * @param int $post_id Post/Order ID
     */
    public function render_courier_voucher_column($column, $post_id) {
        if ($column !== 'dmm_courier_voucher') {
            return;
        }
        
        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }
        
        echo $this->get_courier_voucher_display($order);
    }
    
    /**
     * Render custom column content for HPOS WooCommerce orders
     * 
     * @param string $column Column name
     * @param \WC_Order|int $order Order object or order ID
     */
    public function render_courier_voucher_column_hpos($column, $order) {
        if ($column !== 'dmm_courier_voucher') {
            return;
        }
        
        // Handle both order object and order ID
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        
        if (!$order || !($order instanceof \WC_Order)) {
            return;
        }
        
        echo $this->get_courier_voucher_display($order);
    }
    
    /**
     * Get the courier voucher display text for an order
     * 
     * Checks the configured meta fields for each courier and returns
     * the courier name if a voucher is found, or "No voucher yet" if none.
     * 
     * @param \WC_Order $order WooCommerce order object
     * @return string Display text
     */
    private function get_courier_voucher_display($order) {
        if (!$order) {
            return '<span style="color: #999;">—</span>';
        }
        
        $options = get_option('dmm_delivery_bridge_options', []);
        
        // Define courier meta fields to check (in priority order)
        $courier_checks = [
            'ELTA' => isset($options['elta_voucher_meta_field']) && !empty($options['elta_voucher_meta_field']) 
                ? [$options['elta_voucher_meta_field']] 
                : ['_elta_voucher', 'elta_voucher', 'elta_tracking', '_elta_tracking', 'elta_reference', '_elta_reference'],
            'Geniki' => isset($options['geniki_voucher_meta_field']) && !empty($options['geniki_voucher_meta_field']) 
                ? [$options['geniki_voucher_meta_field']] 
                : ['_geniki_voucher', 'geniki_voucher', 'geniki_tracking', '_geniki_tracking', 'gtx_voucher', 'gtx_tracking'],
            'ACS' => isset($options['acs_voucher_meta_field']) && !empty($options['acs_voucher_meta_field']) 
                ? [$options['acs_voucher_meta_field']] 
                : ['_acs_voucher', 'acs_voucher', 'acs_tracking', '_appsbyb_acs_courier_gr_no_pod'],
        ];
        
        // Check each courier's meta fields
        foreach ($courier_checks as $courier_name => $meta_fields) {
            foreach ($meta_fields as $meta_field) {
                $value = $order->get_meta($meta_field);
                if (!empty($value) && trim($value) !== '') {
                    // Found a voucher for this courier
                    return '<span style="color: #2271b1; font-weight: 500;">' . esc_html($courier_name) . '</span>';
                }
            }
        }
        
        // No voucher found
        return '<span style="color: #999;">' . __('No voucher yet', 'dmm-delivery-bridge') . '</span>';
    }
    
    /**
     * Process order update via Action Scheduler
     * 
     * Called asynchronously to update an order that has already been sent to DMM Delivery.
     * Only processes orders that have been successfully sent previously (marked with
     * '_dmm_delivery_sent' meta). Updates are sent as PUT requests to the API.
     * 
     * This method is designed to be called by Action Scheduler, not directly.
     *
     * @param array $args Action arguments containing 'order_id'
     * @return void
     * @since 1.0.0
     */
    public function process_order_update($args) {
        $order_id = isset($args['order_id']) ? $args['order_id'] : 0;
        
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Only process if order was already sent
        if ($order->get_meta('_dmm_delivery_sent') !== 'yes') {
            $this->logger->debug_log("Order {$order_id} not sent yet, skipping update");
            return;
        }
        
        // Process update (delegate to order processor or handle here)
        // This is a simplified version - full implementation would be in order processor
        $order_data = $this->order_processor->prepare_order_data($order);
        $order_data['sync_update'] = true;
        
        $response = $this->api_client->send_to_api($order_data);
        
        if ($response && $response['success']) {
            $order->update_meta_data('_dmm_delivery_updated_at', gmdate('c'));
            $order->save();
            $order->add_order_note(__('Order updated in DMM Delivery system.', 'dmm-delivery-bridge'));
        }
    }
    
    /**
     * Process bulk orders via Action Scheduler
     * 
     * Called asynchronously to process multiple orders in bulk.
     * Processes orders in batches and updates progress in transient.
     * 
     * @param string $job_id Job ID for tracking progress
     * @param string $type Operation type (send, sync, resend)
     * @return void
     * @since 1.0.0
     */
    public function process_bulk_orders($job_id, $type) {
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        if (!$job_data || $job_data['status'] !== 'running') {
            return;
        }
        
        $order_ids = isset($job_data['order_ids']) ? $job_data['order_ids'] : [];
        $current = isset($job_data['current']) ? intval($job_data['current']) : 0;
        $total = isset($job_data['total']) ? intval($job_data['total']) : 0;
        $batch_size = 5; // Process 5 orders per batch
        
        // Get next batch of orders
        $remaining_ids = array_slice($order_ids, $current, $batch_size);
        
        if (empty($remaining_ids)) {
            // All orders processed
            $job_data['status'] = 'completed';
            $job_data['current'] = $total;
            set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            return;
        }
        
        // Process batch
        foreach ($remaining_ids as $order_id) {
            try {
                if ($type === 'send' || $type === 'resend') {
                    // Send order to API - use the order processor's robust method
                    if ($this->order_processor) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            // Skip refunds - they don't have address methods
                            if ($order instanceof \WC_Order_Refund) {
                                if ($this->logger) {
                                    $this->logger->log("Bulk operation: Order {$order_id} is a refund, skipping", 'warning');
                                }
                                $current++;
                                $job_data['current'] = $current;
                                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                                continue;
                            }
                            
                            // Use the robust processing method directly
                            $this->order_processor->process_order_robust($order);
                        } else {
                            // Order not found, skip it
                            if ($this->logger) {
                                $this->logger->log("Bulk operation: Order {$order_id} not found, skipping", 'warning');
                            }
                        }
                    }
                } elseif ($type === 'sync') {
                    // Sync order status
                    $order = wc_get_order($order_id);
                    if ($order && $order->get_meta('_dmm_delivery_sent') === 'yes') {
                        $this->process_order_update(['order_id' => $order_id]);
                    }
                }
                
                $current++;
                $job_data['current'] = $current;
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            } catch (Exception $e) {
                // Log error but continue
                if ($this->logger) {
                    $this->logger->log('Bulk operation error for order ' . $order_id . ': ' . $e->getMessage(), 'error');
                }
                // Still increment counter to avoid getting stuck
                $current++;
                $job_data['current'] = $current;
                set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            }
        }
        
        // Check if Action Scheduler is available and working
        $has_action_scheduler = function_exists('as_schedule_single_action');
        
        // Schedule next batch if there are more orders
        if ($current < $total) {
            if ($has_action_scheduler) {
                // Use Action Scheduler
                $scheduled = as_schedule_single_action(
                    time() + 1, // Schedule 1 second later
                    'dmm_bulk_process_orders',
                    [$job_id, $type],
                    'dmm_bulk_operations'
                );
                
                if (!$scheduled) {
                    // Action Scheduler failed, log and fall back to immediate processing
                    if ($this->logger) {
                        $this->logger->log("Bulk operation: Action Scheduler failed to schedule next batch for job {$job_id}, falling back to immediate processing", 'warning');
                    }
                    // Process remaining orders immediately
                    $remaining_orders = array_slice($order_ids, $current);
                    if (!empty($remaining_orders)) {
                        $this->process_bulk_orders_immediate_fallback($job_id, $type, $remaining_orders, $current);
                    }
                }
            } else {
                // No Action Scheduler, process remaining immediately
                $remaining_orders = array_slice($order_ids, $current);
                if (!empty($remaining_orders)) {
                    $this->process_bulk_orders_immediate_fallback($job_id, $type, $remaining_orders, $current);
                }
            }
        } else {
            // All done
            $job_data['status'] = 'completed';
            $job_data['current'] = $total;
            set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
            
            if ($this->logger) {
                $this->logger->log("Bulk operation completed: Job {$job_id} processed {$total} orders", 'info');
            }
        }
    }
    
    /**
     * Fallback method to process bulk orders immediately when Action Scheduler is not available
     * 
     * @param string $job_id Job ID
     * @param string $type Operation type
     * @param array $order_ids Array of order IDs to process
     * @param int $start_from Starting index
     */
    private function process_bulk_orders_immediate_fallback($job_id, $type, $order_ids, $start_from) {
        $job_data = get_transient('dmm_bulk_job_' . $job_id);
        if (!$job_data) {
            return;
        }
        
        $current = $start_from;
        $total = isset($job_data['total']) ? intval($job_data['total']) : 0;
        $batch_size = 5;
        
        foreach (array_chunk($order_ids, $batch_size) as $batch) {
            foreach ($batch as $order_id) {
                try {
                    if ($type === 'send' || $type === 'resend') {
                        if ($this->order_processor) {
                            $order = wc_get_order($order_id);
                            if ($order) {
                                // Skip refunds
                                if ($order instanceof \WC_Order_Refund) {
                                    $current++;
                                    $job_data['current'] = $current;
                                    set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                                    continue;
                                }
                                $this->order_processor->process_order_robust($order);
                            }
                        }
                    } elseif ($type === 'sync') {
                        $order = wc_get_order($order_id);
                        if ($order && $order->get_meta('_dmm_delivery_sent') === 'yes') {
                            $this->process_order_update(['order_id' => $order_id]);
                        }
                    }
                    
                    $current++;
                    $job_data['current'] = $current;
                    set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                } catch (Exception $e) {
                    if ($this->logger) {
                        $this->logger->log('Bulk operation error for order ' . $order_id . ': ' . $e->getMessage(), 'error');
                    }
                    $current++;
                    $job_data['current'] = $current;
                    set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
                }
            }
            
            // Small delay between batches
            usleep(200000); // 0.2 seconds
        }
        
        // Mark as completed
        $job_data['status'] = 'completed';
        $job_data['current'] = $total;
        set_transient('dmm_bulk_job_' . $job_id, $job_data, HOUR_IN_SECONDS);
    }
}


<?php
/**
 * Plugin Name: DMM Delivery Bridge
 * Plugin URI: https://dmm.gr
 * Description: Automatically sends WooCommerce orders to DMMDelivery tracking system
 * Version: 1.0.0
 * Author: DMM
 * Author URI: https://dmm.gr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dmm-delivery-bridge
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DMM_DELIVERY_BRIDGE_VERSION', '1.0.0');
define('DMM_DELIVERY_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DMM_DELIVERY_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DMM_DELIVERY_BRIDGE_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class DMM_Delivery_Bridge {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Plugin options
     */
    private $options;
    
    /**
     * Get single instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->options = get_option('dmm_delivery_bridge_options', []);
        
        // Initialize plugin
        add_action('plugins_loaded', [$this, 'init']);
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        // Load text domain
        load_plugin_textdomain('dmm-delivery-bridge', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'admin_init']);
        }
        
        // Hook into WooCommerce order processing
        add_action('woocommerce_order_status_processing', [$this, 'send_order_to_api']);
        add_action('woocommerce_order_status_completed', [$this, 'send_order_to_api']);
        
        // Add custom order meta box
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
        
        // AJAX handlers
        add_action('wp_ajax_dmm_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_dmm_resend_order', [$this, 'ajax_resend_order']);
        add_action('wp_ajax_dmm_sync_order', [$this, 'ajax_sync_order']);
        add_action('wp_ajax_dmm_refresh_meta_fields', [$this, 'ajax_refresh_meta_fields']);
        add_action('wp_ajax_dmm_check_logs', [$this, 'ajax_check_logs']);
        add_action('wp_ajax_dmm_create_log_table', [$this, 'ajax_create_log_table']);
        add_action('wp_ajax_dmm_bulk_send_orders', [$this, 'ajax_bulk_send_orders']);
        add_action('wp_ajax_dmm_bulk_sync_orders', [$this, 'ajax_bulk_sync_orders']);
        add_action('wp_ajax_dmm_get_bulk_progress', [$this, 'ajax_get_bulk_progress']);
        add_action('wp_ajax_dmm_cancel_bulk_send', [$this, 'ajax_cancel_bulk_send']);
        
        // Background processing
        add_action('wp_ajax_dmm_process_bulk_orders', [$this, 'ajax_process_bulk_orders']);
        add_action('wp_ajax_nopriv_dmm_process_bulk_orders', [$this, 'ajax_process_bulk_orders']);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = [
            'api_endpoint' => '',
            'api_key' => '',
            'tenant_id' => '',
            'courier_meta_field' => '',
            'auto_send' => 'yes',
            'order_statuses' => ['processing', 'completed'],
            'create_shipment' => 'yes',
            'debug_mode' => 'no'
        ];
        
        if (!get_option('dmm_delivery_bridge_options')) {
            add_option('dmm_delivery_bridge_options', $default_options);
        }
        
        // Create log table
        $this->create_log_table();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
    }
    
    /**
     * Create log table
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('DMM Delivery Bridge requires WooCommerce to be installed and activated.', 'dmm-delivery-bridge'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('DMM Delivery Bridge', 'dmm-delivery-bridge'),
            __('DMM Delivery', 'dmm-delivery-bridge'),
            'manage_woocommerce',
            'dmm-delivery-bridge',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        register_setting('dmm_delivery_bridge_settings', 'dmm_delivery_bridge_options');
        
        // Add settings sections
        add_settings_section(
            'dmm_delivery_bridge_api_section',
            __('API Configuration', 'dmm-delivery-bridge'),
            [$this, 'api_section_callback'],
            'dmm_delivery_bridge_settings'
        );
        
        add_settings_section(
            'dmm_delivery_bridge_behavior_section',
            __('Behavior Settings', 'dmm-delivery-bridge'),
            [$this, 'behavior_section_callback'],
            'dmm_delivery_bridge_settings'
        );
        
        // API Configuration Fields
        add_settings_field(
            'api_endpoint',
            __('API Endpoint', 'dmm-delivery-bridge'),
            [$this, 'api_endpoint_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'dmm-delivery-bridge'),
            [$this, 'api_key_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        add_settings_field(
            'tenant_id',
            __('Tenant ID', 'dmm-delivery-bridge'),
            [$this, 'tenant_id_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        add_settings_field(
            'courier_meta_field',
            __('Courier Meta Field', 'dmm-delivery-bridge'),
            [$this, 'courier_meta_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
            // Behavior Settings Fields
        add_settings_field(
            'auto_send',
            __('Auto Send Orders', 'dmm-delivery-bridge'),
            [$this, 'auto_send_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        add_settings_field(
            'order_statuses',
            __('Send on Order Status', 'dmm-delivery-bridge'),
            [$this, 'order_statuses_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        add_settings_field(
            'create_shipment',
            __('Create Shipment', 'dmm-delivery-bridge'),
            [$this, 'create_shipment_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'dmm-delivery-bridge'),
            [$this, 'debug_mode_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('DMM Delivery Bridge Settings', 'dmm-delivery-bridge'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Configure your DMM Delivery API settings to automatically send WooCommerce orders for tracking.', 'dmm-delivery-bridge'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('dmm_delivery_bridge_settings');
                do_settings_sections('dmm_delivery_bridge_settings');
                submit_button();
                ?>
            </form>
            
            <div class="dmm-test-section" style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
                <h3><?php _e('Test Connection', 'dmm-delivery-bridge'); ?></h3>
                <p><?php _e('Test your API connection with the current settings.', 'dmm-delivery-bridge'); ?></p>
                <button type="button" id="dmm-test-connection" class="button button-secondary">
                    <?php _e('Test Connection', 'dmm-delivery-bridge'); ?>
                </button>
                <div id="dmm-test-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="dmm-debug-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
                <h3><?php _e('Debug & Maintenance', 'dmm-delivery-bridge'); ?></h3>
                <p><?php _e('Debug logging and maintenance tools.', 'dmm-delivery-bridge'); ?></p>
                <button type="button" id="dmm-check-logs" class="button button-secondary">
                    <?php _e('Check Log Table', 'dmm-delivery-bridge'); ?>
                </button>
                <button type="button" id="dmm-create-log-table" class="button button-secondary">
                    <?php _e('Recreate Log Table', 'dmm-delivery-bridge'); ?>
                </button>
                <div id="dmm-debug-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="dmm-bulk-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f0f8ff;">
                <h3><?php _e('Bulk Order Processing', 'dmm-delivery-bridge'); ?></h3>
                <p><?php _e('Send all unsent orders to DMM Delivery system in the background. This process runs safely without timeouts.', 'dmm-delivery-bridge'); ?></p>
                
                <div id="dmm-bulk-controls">
                    <button type="button" id="dmm-start-bulk-send" class="button button-primary">
                        <?php _e('🚀 Start Bulk Send', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" id="dmm-start-bulk-sync" class="button button-secondary">
                        <?php _e('🔄 Sync Up All', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" id="dmm-cancel-bulk-send" class="button button-secondary" style="display: none;">
                        <?php _e('⏹️ Cancel', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
                
                <div id="dmm-bulk-progress" style="margin-top: 15px; display: none;">
                    <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span id="dmm-progress-text"><?php _e('Preparing...', 'dmm-delivery-bridge'); ?></span>
                            <span id="dmm-progress-percentage">0%</span>
                        </div>
                        <div style="background: #f0f0f0; border-radius: 10px; height: 20px; overflow: hidden;">
                            <div id="dmm-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                        <div id="dmm-progress-details" style="margin-top: 10px; font-size: 12px; color: #666;">
                            <div><?php _e('Total Orders:', 'dmm-delivery-bridge'); ?> <span id="dmm-total-orders">0</span></div>
                            <div><?php _e('Processed:', 'dmm-delivery-bridge'); ?> <span id="dmm-processed-orders">0</span></div>
                            <div><?php _e('Successful:', 'dmm-delivery-bridge'); ?> <span id="dmm-successful-orders">0</span></div>
                            <div><?php _e('Failed:', 'dmm-delivery-bridge'); ?> <span id="dmm-failed-orders">0</span></div>
                        </div>
                    </div>
                </div>
                
                <div id="dmm-bulk-result" style="margin-top: 10px;"></div>
            </div>
            
            <?php $this->render_logs_section(); ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#dmm-test-connection').on('click', function() {
                var button = $(this);
                var result = $('#dmm-test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_test_connection',
                        nonce: '<?php echo wp_create_nonce('dmm_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Connection test failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Connection', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-check-logs').on('click', function() {
                var button = $(this);
                var result = $('#dmm-debug-result');
                
                button.prop('disabled', true).text('<?php _e('Checking...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_check_logs',
                        nonce: '<?php echo wp_create_nonce('dmm_check_logs'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Check failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Check Log Table', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-create-log-table').on('click', function() {
                var button = $(this);
                var result = $('#dmm-debug-result');
                
                if (!confirm('<?php _e('Are you sure you want to recreate the log table? This will not delete existing logs.', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('Creating...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_create_log_table',
                        nonce: '<?php echo wp_create_nonce('dmm_create_log_table'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Table creation failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Recreate Log Table', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render logs section
     */
    private function render_logs_section() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 20");
        
        ?>
        <div class="dmm-logs-section" style="margin-top: 30px;">
            <h3><?php _e('Recent Logs', 'dmm-delivery-bridge'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order ID', 'dmm-delivery-bridge'); ?></th>
                        <th><?php _e('Customer', 'dmm-delivery-bridge'); ?></th>
                        <th><?php _e('Status', 'dmm-delivery-bridge'); ?></th>
                        <th><?php _e('Date', 'dmm-delivery-bridge'); ?></th>
                        <th><?php _e('Actions', 'dmm-delivery-bridge'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5"><?php _e('No logs found.', 'dmm-delivery-bridge'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            // Extract customer data from request_data
                            $request_data = json_decode($log->request_data, true);
                            $customer_email = isset($request_data['customer']['email']) ? $request_data['customer']['email'] : '';
                            $customer_phone = isset($request_data['customer']['phone']) ? $request_data['customer']['phone'] : '';
                            $customer_name = trim((isset($request_data['customer']['first_name']) ? $request_data['customer']['first_name'] : '') . ' ' . (isset($request_data['customer']['last_name']) ? $request_data['customer']['last_name'] : ''));
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $log->order_id . '&action=edit'); ?>">
                                        #<?php echo $log->order_id; ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: bold; margin-bottom: 2px;">
                                        <?php echo $customer_name ? esc_html($customer_name) : '—'; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo $customer_email ? esc_html($customer_email) : '—'; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo $customer_phone ? esc_html($customer_phone) : '—'; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-<?php echo $log->status; ?>">
                                        <?php echo ucfirst($log->status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log->created_at)); ?></td>
                                <td>
                                    <?php if ($log->status === 'error'): ?>
                                        <button type="button" class="button button-small dmm-resend-order" data-order-id="<?php echo $log->order_id; ?>">
                                            <?php _e('Resend', 'dmm-delivery-bridge'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small dmm-view-log" data-log='<?php echo esc_attr(json_encode($log)); ?>'>
                                        <?php _e('View Details', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.dmm-view-log').on('click', function() {
                var log = $(this).data('log');
                var content = '<h3>Log Details</h3>';
                content += '<p><strong>Order ID:</strong> ' + log.order_id + '</p>';
                content += '<p><strong>Status:</strong> ' + log.status + '</p>';
                content += '<p><strong>Date:</strong> ' + log.created_at + '</p>';
                
                // Extract and display customer information
                if (log.request_data) {
                    try {
                        var requestData = JSON.parse(log.request_data);
                        if (requestData.customer) {
                            content += '<h4>Customer Information:</h4>';
                            content += '<p><strong>Name:</strong> ' + (requestData.customer.first_name || '') + ' ' + (requestData.customer.last_name || '') + '</p>';
                            content += '<p><strong>Email:</strong> ' + (requestData.customer.email || 'Not provided') + '</p>';
                            content += '<p><strong>Phone:</strong> ' + (requestData.customer.phone || 'Not provided') + '</p>';
                        }
                    } catch (e) {
                        // JSON parse error, continue without customer info
                    }
                }
                
                if (log.error_message) {
                    content += '<p><strong>Error:</strong> ' + log.error_message + '</p>';
                }
                
                if (log.request_data) {
                    content += '<h4>Request Data:</h4>';
                    content += '<pre style="background: #f1f1f1; padding: 10px; overflow: auto;">' + log.request_data + '</pre>';
                }
                
                if (log.response_data) {
                    content += '<h4>Response Data:</h4>';
                    content += '<pre style="background: #f1f1f1; padding: 10px; overflow: auto;">' + log.response_data + '</pre>';
                }
                
                // Create modal
                $('<div id="dmm-log-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999999;">' +
                  '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; max-width: 80%; max-height: 80%; overflow: auto;">' +
                  content +
                  '<p><button type="button" id="dmm-close-modal" class="button">Close</button></p>' +
                  '</div></div>').appendTo('body');
            });
            
            $(document).on('click', '#dmm-close-modal', function() {
                $('#dmm-log-modal').remove();
            });
            
            $('.dmm-resend-order').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                
                if (!confirm('<?php _e('Are you sure you want to resend this order?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('Sending...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_resend_order',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('dmm_resend_order'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to resend order.', 'dmm-delivery-bridge'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Resend', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // Bulk processing functionality
            var bulkProgressInterval;
            var bulkProcessingInterval;
            var isBulkProcessing = false;
            
            $('#dmm-start-bulk-send').on('click', function() {
                if (isBulkProcessing) return;
                
                if (!confirm('<?php _e('This will send all unsent orders to DMM Delivery. This may take a long time for large numbers of orders. Continue?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('🚀 Starting...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_bulk_send_orders',
                        nonce: '<?php echo wp_create_nonce('dmm_bulk_send_orders'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            isBulkProcessing = true;
                            $('#dmm-bulk-progress').show();
                            $('#dmm-cancel-bulk-send').show();
                            button.hide();
                            
                            // Start processing batches via AJAX polling
                            startBulkProcessing();
                            
                            // Start progress monitoring
                            bulkProgressInterval = setInterval(updateBulkProgress, 1000);
                            updateBulkProgress();
                        } else {
                            alert(response.data.message);
                            button.prop('disabled', false).text('<?php _e('🚀 Start Bulk Send', 'dmm-delivery-bridge'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to start bulk processing.', 'dmm-delivery-bridge'); ?>');
                        button.prop('disabled', false).text('<?php _e('🚀 Start Bulk Send', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // Bulk sync handler
            $('#dmm-start-bulk-sync').on('click', function() {
                if (isBulkProcessing) return;
                
                if (!confirm('<?php _e('This will sync all sent orders with their latest data (images, status, etc.). This may take a long time for large numbers of orders. Continue?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('🔄 Starting...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_bulk_sync_orders',
                        nonce: '<?php echo wp_create_nonce('dmm_bulk_sync_orders'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            isBulkProcessing = true;
                            $('#dmm-bulk-progress').show();
                            $('#dmm-cancel-bulk-send').show();
                            button.hide();
                            
                            // Start processing batches via AJAX polling
                            startBulkProcessing();
                            
                            // Start progress monitoring
                            bulkProgressInterval = setInterval(updateBulkProgress, 1000);
                            updateBulkProgress();
                        } else {
                            alert(response.data.message);
                            button.prop('disabled', false).text('<?php _e('🔄 Sync Up All', 'dmm-delivery-bridge'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to start bulk sync.', 'dmm-delivery-bridge'); ?>');
                        button.prop('disabled', false).text('<?php _e('🔄 Sync Up All', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-cancel-bulk-send').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to cancel the bulk processing?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_cancel_bulk_send',
                        nonce: '<?php echo wp_create_nonce('dmm_cancel_bulk_send'); ?>'
                    },
                    success: function(response) {
                        stopBulkProcessing();
                        $('#dmm-bulk-result').html('<div class="notice notice-warning inline"><p><?php _e('Bulk processing cancelled.', 'dmm-delivery-bridge'); ?></p></div>');
                    }
                });
            });
            
            function updateBulkProgress() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_get_bulk_progress',
                        nonce: '<?php echo wp_create_nonce('dmm_get_bulk_progress'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            if (data.status === 'completed') {
                                stopBulkProcessing();
                                $('#dmm-bulk-result').html('<div class="notice notice-success inline"><p><?php _e('Bulk processing completed!', 'dmm-delivery-bridge'); ?> ' + data.message + '</p></div>');
                            } else if (data.status === 'error') {
                                stopBulkProcessing();
                                $('#dmm-bulk-result').html('<div class="notice notice-error inline"><p><?php _e('Bulk processing failed:', 'dmm-delivery-bridge'); ?> ' + data.message + '</p></div>');
                            } else if (data.status === 'processing') {
                                updateProgressBar(data);
                            }
                        }
                    },
                    error: function() {
                        // Continue trying even if this request fails
                    }
                });
            }
            
            function updateProgressBar(data) {
                var percentage = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 0;
                
                var defaultText = data.operation === 'sync' ? 
                    '<?php _e('Syncing orders...', 'dmm-delivery-bridge'); ?>' : 
                    '<?php _e('Processing orders...', 'dmm-delivery-bridge'); ?>';
                
                $('#dmm-progress-text').text(data.status_text || defaultText);
                $('#dmm-progress-percentage').text(percentage + '%');
                $('#dmm-progress-bar').css('width', percentage + '%');
                $('#dmm-total-orders').text(data.total || 0);
                $('#dmm-processed-orders').text(data.processed || 0);
                $('#dmm-successful-orders').text(data.successful || 0);
                $('#dmm-failed-orders').text(data.failed || 0);
            }
            
            function startBulkProcessing() {
                // Process batches every 3 seconds via AJAX
                bulkProcessingInterval = setInterval(function() {
                    if (!isBulkProcessing) {
                        clearInterval(bulkProcessingInterval);
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'dmm_process_bulk_orders',
                            nonce: '<?php echo wp_create_nonce('dmm_process_bulk_orders'); ?>'
                        },
                        success: function(response) {
                            // Processing continues automatically
                        },
                        error: function() {
                            // Continue trying even if this request fails
                        }
                    });
                }, 3000);
            }
            
            function stopBulkProcessing() {
                isBulkProcessing = false;
                if (bulkProgressInterval) {
                    clearInterval(bulkProgressInterval);
                    bulkProgressInterval = null;
                }
                if (bulkProcessingInterval) {
                    clearInterval(bulkProcessingInterval);
                    bulkProcessingInterval = null;
                }
                $('#dmm-start-bulk-send').prop('disabled', false).text('<?php _e('🚀 Start Bulk Send', 'dmm-delivery-bridge'); ?>').show();
                $('#dmm-start-bulk-sync').prop('disabled', false).text('<?php _e('🔄 Sync Up All', 'dmm-delivery-bridge'); ?>').show();
                $('#dmm-cancel-bulk-send').hide();
            }
        });
        </script>
        <?php
    }
    
    // Settings callbacks
    public function api_section_callback() {
        echo '<p>' . __('Enter your DMM Delivery API configuration details.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function behavior_section_callback() {
        echo '<p>' . __('Configure when and how orders are sent to the DMM Delivery system.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function api_endpoint_callback() {
        $value = isset($this->options['api_endpoint']) ? $this->options['api_endpoint'] : '';
        echo '<input type="url" name="dmm_delivery_bridge_options[api_endpoint]" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://oreksi.gr/api/woocommerce/order" />';
        echo '<p class="description">' . __('The full URL to your DMM Delivery API endpoint.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function api_key_callback() {
        $value = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your API key from the DMM Delivery system.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function tenant_id_callback() {
        $value = isset($this->options['tenant_id']) ? $this->options['tenant_id'] : '';
        echo '<input type="text" name="dmm_delivery_bridge_options[tenant_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your tenant ID from the DMM Delivery system.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function courier_meta_field_callback() {
        $value = isset($this->options['courier_meta_field']) ? $this->options['courier_meta_field'] : '';
        
        // Get all available meta fields
        $all_fields = $this->get_recent_order_meta_fields();
        
        echo '<select name="dmm_delivery_bridge_options[courier_meta_field]" class="regular-text" style="max-height: 30px;" size="1">';
        echo '<option value="">' . __('-- Select a meta field --', 'dmm-delivery-bridge') . '</option>';
        
        // Group fields by type
        $grouped_fields = [
            'Order Fields' => [],
            'Product Fields' => [],
            'Custom Fields' => [],
            'ACF Fields' => [],
            'Meta Box Fields' => [],
            'Toolset Fields' => [],
            'Other Fields' => []
        ];
        
        foreach ($all_fields as $meta_key => $display_name) {
            if (strpos($display_name, '[Order]') === 0) {
                $grouped_fields['Order Fields'][$meta_key] = str_replace('[Order] ', '', $display_name);
            } elseif (strpos($display_name, '[Product]') === 0) {
                $grouped_fields['Product Fields'][$meta_key] = str_replace('[Product] ', '', $display_name);
            } elseif (strpos($display_name, '[Custom]') === 0) {
                $grouped_fields['Custom Fields'][$meta_key] = str_replace('[Custom] ', '', $display_name);
            } elseif (strpos($display_name, '[ACF]') === 0) {
                $grouped_fields['ACF Fields'][$meta_key] = str_replace('[ACF] ', '', $display_name);
            } elseif (strpos($display_name, '[Meta Box]') === 0) {
                $grouped_fields['Meta Box Fields'][$meta_key] = str_replace('[Meta Box] ', '', $display_name);
            } elseif (strpos($display_name, '[Toolset]') === 0) {
                $grouped_fields['Toolset Fields'][$meta_key] = str_replace('[Toolset] ', '', $display_name);
            } else {
                $grouped_fields['Other Fields'][$meta_key] = $display_name;
            }
        }
        
        // Display grouped options
        foreach ($grouped_fields as $group_name => $fields) {
            if (!empty($fields)) {
                echo '<optgroup label="' . esc_attr($group_name) . '">';
                asort($fields);
                foreach ($fields as $meta_key => $display_name) {
                    $selected = selected($value, $meta_key, false);
                    echo '<option value="' . esc_attr($meta_key) . '"' . $selected . '>' . esc_html($display_name) . '</option>';
                }
                echo '</optgroup>';
            }
        }
        
        echo '</select>';
        
        // Add styling to make it look more like CTX Feed
        ?>
        <style>
        select[name="dmm_delivery_bridge_options[courier_meta_field]"] {
            width: 100%;
            max-width: 500px;
            height: 200px;
            font-family: monospace;
            font-size: 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        select[name="dmm_delivery_bridge_options[courier_meta_field]"] optgroup {
            font-weight: bold;
            background: #f0f0f0;
            color: #333;
            padding: 5px;
            margin: 2px 0;
        }
        select[name="dmm_delivery_bridge_options[courier_meta_field]"] option {
            padding: 3px 8px;
            color: #555;
            background: #fff;
        }
        select[name="dmm_delivery_bridge_options[courier_meta_field]"] option:hover {
            background: #e0e0e0;
        }
        .dmm-meta-field-info {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
        }
        .dmm-meta-field-info h4 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #333;
        }
        .dmm-meta-field-info ul {
            margin: 5px 0 5px 20px;
            list-style: disc;
        }
        .dmm-meta-field-info li {
            margin: 2px 0;
            color: #666;
        }
        </style>
        <?php
        
        echo '<div class="dmm-meta-field-info">';
        echo '<h4>' . __('Field Types Explained:', 'dmm-delivery-bridge') . '</h4>';
        echo '<ul>';
        echo '<li><strong>' . __('Order Fields:', 'dmm-delivery-bridge') . '</strong> ' . __('Meta fields attached to WooCommerce orders (billing, shipping, payment info)', 'dmm-delivery-bridge') . '</li>';
        echo '<li><strong>' . __('Product Fields:', 'dmm-delivery-bridge') . '</strong> ' . __('Meta fields attached to WooCommerce products (SKU, weight, dimensions, etc.)', 'dmm-delivery-bridge') . '</li>';
        echo '<li><strong>' . __('Custom Fields:', 'dmm-delivery-bridge') . '</strong> ' . __('Generic custom fields that might contain courier information', 'dmm-delivery-bridge') . '</li>';
        echo '<li><strong>' . __('ACF Fields:', 'dmm-delivery-bridge') . '</strong> ' . __('Advanced Custom Fields plugin fields', 'dmm-delivery-bridge') . '</li>';
        echo '<li><strong>' . __('Meta Box Fields:', 'dmm-delivery-bridge') . '</strong> ' . __('Meta Box plugin fields', 'dmm-delivery-bridge') . '</li>';
        echo '<li><strong>' . __('Toolset Fields:', 'dmm-delivery-bridge') . '</strong> ' . __('Toolset Types plugin fields', 'dmm-delivery-bridge') . '</li>';
        echo '</ul>';
        echo '<p><em>' . __('Select the field that contains courier information like "ACS", "Speedex", "ELTA", etc. The plugin will automatically map these to courier codes.', 'dmm-delivery-bridge') . '</em></p>';
        echo '</div>';
        
        // Add refresh button
        echo '<p><button type="button" id="refresh-meta-fields" class="button button-secondary">' . __('🔄 Refresh Meta Fields', 'dmm-delivery-bridge') . '</button></p>';
        
        // Add JavaScript for refresh functionality
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-meta-fields').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('🔄 <?php _e('Refreshing...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_refresh_meta_fields',
                        nonce: '<?php echo wp_create_nonce('dmm_refresh_meta_fields'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php _e('Failed to refresh meta fields.', 'dmm-delivery-bridge'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to refresh meta fields.', 'dmm-delivery-bridge'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🔄 <?php _e('Refresh Meta Fields', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function auto_send_callback() {
        $value = isset($this->options['auto_send']) ? $this->options['auto_send'] : 'yes';
        echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[auto_send]" value="yes" ' . checked($value, 'yes', false) . ' /> ' . __('Automatically send orders when status changes', 'dmm-delivery-bridge') . '</label>';
    }
    
    public function order_statuses_callback() {
        $value = isset($this->options['order_statuses']) ? $this->options['order_statuses'] : ['processing', 'completed'];
        $statuses = wc_get_order_statuses();
        
        echo '<fieldset>';
        foreach ($statuses as $status_key => $status_label) {
            $status_key = str_replace('wc-', '', $status_key);
            $checked = in_array($status_key, $value) ? 'checked' : '';
            echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[order_statuses][]" value="' . esc_attr($status_key) . '" ' . $checked . ' /> ' . esc_html($status_label) . '</label><br>';
        }
        echo '</fieldset>';
        echo '<p class="description">' . __('Send orders to DMM Delivery when they reach these statuses.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function create_shipment_callback() {
        $value = isset($this->options['create_shipment']) ? $this->options['create_shipment'] : 'yes';
        echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[create_shipment]" value="yes" ' . checked($value, 'yes', false) . ' /> ' . __('Automatically create shipment in DMM Delivery system', 'dmm-delivery-bridge') . '</label>';
    }
    
    public function debug_mode_callback() {
        $value = isset($this->options['debug_mode']) ? $this->options['debug_mode'] : 'no';
        echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[debug_mode]" value="yes" ' . checked($value, 'yes', false) . ' /> ' . __('Enable debug logging', 'dmm-delivery-bridge') . '</label>';
        echo '<p class="description">' . __('Log detailed information for troubleshooting.', 'dmm-delivery-bridge') . '</p>';
    }
    
    /**
     * Send order to API
     */
    public function send_order_to_api($order_id) {
        // Check if auto send is enabled
        if (isset($this->options['auto_send']) && $this->options['auto_send'] !== 'yes') {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if this status should trigger sending
        $order_statuses = isset($this->options['order_statuses']) ? $this->options['order_statuses'] : ['processing', 'completed'];
        if (!in_array($order->get_status(), $order_statuses)) {
            return;
        }
        
        // Check if already sent successfully
        if (get_post_meta($order_id, '_dmm_delivery_sent', true) === 'yes') {
            return;
        }
        
        $this->process_order($order);
    }
    
    /**
     * Process order and send to API
     */
    public function process_order($order) {
        $order_id = $order->get_id();
        $order_data = null;
        $response = null;
        
        try {
            // Prepare order data
            $order_data = $this->prepare_order_data($order);
            
            // Debug logging
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
                error_log('DMM Delivery Bridge - Processing Order ID: ' . $order_id);
                error_log('DMM Delivery Bridge - Order Data: ' . print_r($order_data, true));
                error_log('DMM Delivery Bridge - Customer Email: ' . $order->get_billing_email());
                error_log('DMM Delivery Bridge - Customer Phone: ' . $order->get_billing_phone());
                
                // Log product images for debugging
                if (isset($order_data['order']['items'])) {
                    foreach ($order_data['order']['items'] as $index => $item) {
                        error_log('DMM Delivery Bridge - Product ' . ($index + 1) . ' Image: ' . ($item['image_url'] ?? 'No image'));
                    }
                }
            }
            
            // Send to API
            $response = $this->send_to_api($order_data);
            
            // Debug API response
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
                error_log('DMM Delivery Bridge - API Response: ' . print_r($response, true));
            }
            
        } catch (Exception $e) {
            // Handle any exceptions during processing
            error_log('DMM Delivery Bridge - Exception during order processing: ' . $e->getMessage());
            
            $response = [
                'success' => false,
                'message' => 'Exception during processing: ' . $e->getMessage(),
                'data' => null
            ];
        }
        
        // Always log the result, even if there was an exception
        if ($order_data && $response) {
            $this->log_request($order_id, $order_data, $response);
        }
        
        // Update order meta
        if ($response && $response['success']) {
            update_post_meta($order_id, '_dmm_delivery_sent', 'yes');
            update_post_meta($order_id, '_dmm_delivery_order_id', $response['data']['order_id'] ?? '');
            update_post_meta($order_id, '_dmm_delivery_shipment_id', $response['data']['shipment_id'] ?? '');
            
            // Add order note
            $order->add_order_note(__('Order sent to DMM Delivery system successfully.', 'dmm-delivery-bridge'));
        } else {
            update_post_meta($order_id, '_dmm_delivery_sent', 'error');
            
            // Add order note with error
            $error_message = $response ? $response['message'] : 'Unknown error occurred';
            $order->add_order_note(
                sprintf(__('Failed to send order to DMM Delivery system: %s', 'dmm-delivery-bridge'), $error_message)
            );
        }
    }
    
    /**
     * Prepare order data for API
     */
    private function prepare_order_data($order) {
        $shipping_address = $order->get_address('shipping');
        if (empty($shipping_address['address_1'])) {
            $shipping_address = $order->get_address('billing');
        }
        
        // Debug customer data extraction
        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
            error_log('DMM Delivery Bridge - Debug Customer Data:');
            error_log('  Billing Email: ' . ($billing_email ?: 'EMPTY'));
            error_log('  Billing Phone: ' . ($billing_phone ?: 'EMPTY'));
            error_log('  Billing First Name: ' . ($billing_first_name ?: 'EMPTY'));
            error_log('  Billing Last Name: ' . ($billing_last_name ?: 'EMPTY'));
        }
        
        // Prepare order items
        $order_items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            // Get product image URL
            $image_url = '';
            if ($product) {
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'full');
                }
                
                // If no main image, try to get the first gallery image
                if (!$image_url) {
                    $gallery_ids = $product->get_gallery_image_ids();
                    if (!empty($gallery_ids)) {
                        $image_url = wp_get_attachment_image_url($gallery_ids[0], 'full');
                    }
                }
                
                // If still no image, try to get placeholder image
                if (!$image_url) {
                    $image_url = wc_placeholder_img_src('full');
                }
            }
            
            $order_items[] = [
                'sku' => $product ? $product->get_sku() : '',
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => (float) $item->get_total() / $item->get_quantity(), // Unit price
                'total' => (float) $item->get_total(),
                'weight' => $product && $product->has_weight() ? (float) $product->get_weight() : 0,
                'product_id' => $product ? $product->get_id() : 0,
                'variation_id' => $item->get_variation_id(),
                'image_url' => $image_url,
            ];
        }
        
        return [
            'source' => 'woocommerce',
            'order' => [
                'external_order_id' => (string) $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total_amount' => (float) $order->get_total(),
                'subtotal' => (float) $order->get_subtotal(),
                'tax_amount' => (float) $order->get_total_tax(),
                'shipping_cost' => (float) $order->get_shipping_total(),
                'discount_amount' => (float) $order->get_discount_total(),
                'currency' => $order->get_currency(),
                'payment_status' => $order->is_paid() ? 'paid' : 'pending',
                'payment_method' => $order->get_payment_method(),
                'items' => $order_items,
            ],
            'customer' => [
                'first_name' => $billing_first_name,
                'last_name' => $billing_last_name,
                'email' => $billing_email,
                'phone' => $billing_phone,
            ],
            'shipping' => [
                'address' => [
                    'first_name' => $shipping_address['first_name'] ?? $billing_first_name,
                    'last_name' => $shipping_address['last_name'] ?? $billing_last_name,
                    'company' => $shipping_address['company'] ?? '',
                    'address_1' => $shipping_address['address_1'] ?? '',
                    'address_2' => $shipping_address['address_2'] ?? '',
                    'city' => $shipping_address['city'] ?? '',
                    'postcode' => $shipping_address['postcode'] ?? '',
                    'country' => $shipping_address['country'] ?? 'GR',
                    'phone' => $shipping_address['phone'] ?? $billing_phone,
                    'email' => $billing_email,
                ],
                'weight' => $this->calculate_order_weight($order),
            ],
            'create_shipment' => isset($this->options['create_shipment']) && $this->options['create_shipment'] === 'yes',
            'preferred_courier' => $this->get_courier_from_order($order),
        ];
    }
    
    /**
     * Calculate order weight
     */
    private function calculate_order_weight($order) {
        $weight = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->has_weight()) {
                $weight += (float) $product->get_weight() * $item->get_quantity();
            }
        }
        return $weight;
    }
    
    /**
     * Send data to API
     */
    private function send_to_api($data) {
        $api_endpoint = isset($this->options['api_endpoint']) ? $this->options['api_endpoint'] : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $tenant_id = isset($this->options['tenant_id']) ? $this->options['tenant_id'] : '';
        
        if (empty($api_endpoint) || empty($api_key) || empty($tenant_id)) {
            return [
                'success' => false,
                'message' => __('API configuration is incomplete.', 'dmm-delivery-bridge'),
                'data' => null
            ];
        }
        
        // Determine HTTP method based on sync flag
        $method = isset($data['sync_update']) && $data['sync_update'] ? 'PUT' : 'POST';
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Api-Key' => $api_key,
                'X-Tenant-Id' => $tenant_id,
            ],
            'body' => json_encode($data),
        ];
        
        $response = wp_remote_request($api_endpoint, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'data' => null
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Handle rate limiting (HTTP 429) with retry
        if ($response_code === 429) {
            // Wait 5 seconds and retry once
            sleep(5);
            
            $retry_response = wp_remote_request($api_endpoint, $args);
            
            if (is_wp_error($retry_response)) {
                return [
                    'success' => false,
                    'message' => $retry_response->get_error_message(),
                    'data' => null
                ];
            }
            
            $retry_code = wp_remote_retrieve_response_code($retry_response);
            $retry_body = wp_remote_retrieve_body($retry_response);
            $retry_data = json_decode($retry_body, true);
            
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
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'message' => $response_data['message'] ?? __('Order sent successfully.', 'dmm-delivery-bridge'),
                'data' => $response_data,
                'http_code' => $response_code
            ];
        } else {
            return [
                'success' => false,
                'message' => $response_data['message'] ?? sprintf(__('HTTP Error: %d', 'dmm-delivery-bridge'), $response_code),
                'data' => $response_data,
                'http_code' => $response_code
            ];
        }
    }
    
    /**
     * Log request
     */
    private function log_request($order_id, $request_data, $response) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Ensure table exists
        $this->ensure_log_table_exists();
        
        // Prepare data for logging
        $log_data = [
            'order_id' => $order_id,
            'status' => $response['success'] ? 'success' : 'error',
            'request_data' => json_encode($request_data, JSON_PRETTY_PRINT),
            'response_data' => json_encode($response, JSON_PRETTY_PRINT),
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
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
            if ($result === false) {
                error_log('DMM Delivery Bridge - Log insert failed: ' . $wpdb->last_error);
                error_log('DMM Delivery Bridge - Log data: ' . print_r($log_data, true));
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
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            $this->create_log_table();
        }
    }
    
    /**
     * Add order meta box
     */
    public function add_order_meta_box() {
        add_meta_box(
            'dmm-delivery-bridge',
            __('DMM Delivery Status', 'dmm-delivery-bridge'),
            [$this, 'order_meta_box_content'],
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Order meta box content
     */
    public function order_meta_box_content($post) {
        $order = wc_get_order($post->ID);
        $sent_status = get_post_meta($post->ID, '_dmm_delivery_sent', true);
        $dmm_order_id = get_post_meta($post->ID, '_dmm_delivery_order_id', true);
        $dmm_shipment_id = get_post_meta($post->ID, '_dmm_delivery_shipment_id', true);
        
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
                <div style="margin-top: 10px;">
                    <button type="button" class="button button-small dmm-sync-order" data-order-id="<?php echo $post->ID; ?>">
                        🔄 <?php _e('Sync Up', 'dmm-delivery-bridge'); ?>
                    </button>
                    <p style="font-size: 11px; color: #666; margin: 5px 0 0 0;">
                        <?php _e('Update order with latest data (images, status, etc.)', 'dmm-delivery-bridge'); ?>
                    </p>
                </div>
            <?php elseif ($sent_status === 'error'): ?>
                <p><strong style="color: red;">✗ <?php _e('Failed to send', 'dmm-delivery-bridge'); ?></strong></p>
                <button type="button" class="button button-small dmm-resend-order" data-order-id="<?php echo $post->ID; ?>">
                    <?php _e('Retry', 'dmm-delivery-bridge'); ?>
                </button>
            <?php else: ?>
                <p><?php _e('Not sent yet', 'dmm-delivery-bridge'); ?></p>
                <button type="button" class="button button-small dmm-send-order" data-order-id="<?php echo $post->ID; ?>">
                    <?php _e('Send Now', 'dmm-delivery-bridge'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.dmm-send-order, .dmm-resend-order').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                
                button.prop('disabled', true).text('<?php _e('Sending...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_resend_order',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('dmm_resend_order'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to send order.', 'dmm-delivery-bridge'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Retry', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // Sync order handler
            $('.dmm-sync-order').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                
                if (!confirm('<?php _e('This will update the order in DMM Delivery with the latest data (images, status, etc.). Continue?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('🔄 <?php _e('Syncing...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_sync_order',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('dmm_sync_order'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Order synced successfully!', 'dmm-delivery-bridge'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Sync failed:', 'dmm-delivery-bridge'); ?> ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to sync order.', 'dmm-delivery-bridge'); ?>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🔄 <?php _e('Sync Up', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('dmm_test_connection', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Prepare test data
        $test_data = [
            'source' => 'woocommerce',
            'order' => [
                'external_order_id' => 'TEST-' . time(),
                'order_number' => 'TEST-' . time(),
                'status' => 'pending',
                'total_amount' => 12.34,
                'subtotal' => 10.0,
                'tax_amount' => 2.34,
                'shipping_cost' => 0.0,
                'currency' => 'EUR',
                'payment_status' => 'pending',
                'payment_method' => 'test',
            ],
            'customer' => [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'email' => 'test@example.com',
                'phone' => '1234567890',
            ],
            'shipping' => [
                'address' => [
                    'first_name' => 'Test',
                    'last_name' => 'Customer',
                    'address_1' => 'Test Address 1',
                    'city' => 'Test City',
                    'postcode' => '12345',
                    'country' => 'GR',
                    'phone' => '1234567890',
                    'email' => 'test@example.com',
                ],
            ],
            'create_shipment' => false, // Don't create shipment for test
        ];
        
        $response = $this->send_to_api($test_data);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Connection test successful! API is working correctly.', 'dmm-delivery-bridge')
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Connection test failed: %s', 'dmm-delivery-bridge'), $response['message'])
            ]);
        }
    }
    
    /**
     * AJAX: Resend order
     */
    public function ajax_resend_order() {
        check_ajax_referer('dmm_resend_order', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error([
                'message' => __('Order not found.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Reset the sent status
        delete_post_meta($order_id, '_dmm_delivery_sent');
        delete_post_meta($order_id, '_dmm_delivery_order_id');
        delete_post_meta($order_id, '_dmm_delivery_shipment_id');
        
        // Process the order
        $this->process_order($order);
        
        $sent_status = get_post_meta($order_id, '_dmm_delivery_sent', true);
        
        if ($sent_status === 'yes') {
            wp_send_json_success([
                'message' => __('Order sent successfully!', 'dmm-delivery-bridge')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to send order. Check the logs for details.', 'dmm-delivery-bridge')
            ]);
        }
    }
    
    /**
     * AJAX: Sync order (update existing order with latest data)
     */
    public function ajax_sync_order() {
        check_ajax_referer('dmm_sync_order', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error([
                'message' => __('Order not found.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Check if order was previously sent
        $dmm_order_id = get_post_meta($order_id, '_dmm_delivery_order_id', true);
        if (!$dmm_order_id) {
            wp_send_json_error([
                'message' => __('Order has not been sent to DMM Delivery yet. Use "Send Now" instead.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Prepare updated order data
        $order_data = $this->prepare_order_data($order);
        
        // Add sync flag to indicate this is an update
        $order_data['sync_update'] = true;
        $order_data['dmm_order_id'] = $dmm_order_id;
        
        // Send update to API
        $response = $this->send_to_api($order_data);
        
        // Log the sync attempt
        $this->log_request($order_id, $order_data, $response);
        
        if ($response && $response['success']) {
            // Update order note
            $order->add_order_note(__('Order synced with DMM Delivery system successfully.', 'dmm-delivery-bridge'));
            
            wp_send_json_success([
                'message' => __('Order synced successfully!', 'dmm-delivery-bridge')
            ]);
        } else {
            $error_message = $response ? $response['message'] : 'Unknown error occurred';
            $order->add_order_note(
                sprintf(__('Failed to sync order with DMM Delivery system: %s', 'dmm-delivery-bridge'), $error_message)
            );
            
            wp_send_json_error([
                'message' => sprintf(__('Sync failed: %s', 'dmm-delivery-bridge'), $error_message)
            ]);
        }
    }
    
    /**
     * Get all available meta fields (orders, products, and custom)
     */
    private function get_recent_order_meta_fields() {
        global $wpdb;
        
        $fields = [];
        
        // Get ALL meta keys from orders (not just recent ones)
        $order_meta_keys = $wpdb->get_results("
            SELECT DISTINCT pm.meta_key, COUNT(*) as usage_count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key NOT IN ('_edit_last', '_edit_lock', '_wp_old_slug', '_wp_old_date', '_wp_attachment_metadata')
            AND pm.meta_value != ''
            AND LENGTH(pm.meta_value) < 500
            GROUP BY pm.meta_key
            ORDER BY usage_count DESC, pm.meta_key ASC
            LIMIT 500
        ");
        
        // Get ALL meta keys from products
        $product_meta_keys = $wpdb->get_results("
            SELECT DISTINCT pm.meta_key, COUNT(*) as usage_count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type IN ('product', 'product_variation')
            AND pm.meta_key NOT IN ('_edit_last', '_edit_lock', '_wp_old_slug', '_wp_old_date', '_wp_attachment_metadata')
            AND pm.meta_value != ''
            AND LENGTH(pm.meta_value) < 500
            GROUP BY pm.meta_key
            ORDER BY usage_count DESC, pm.meta_key ASC
            LIMIT 500
        ");
        
        // Add comprehensive WooCommerce order fields
        $wc_order_fields = [
            // Billing fields
            '_billing_first_name' => '[Order] Billing First Name (_billing_first_name)',
            '_billing_last_name' => '[Order] Billing Last Name (_billing_last_name)',
            '_billing_company' => '[Order] Billing Company (_billing_company)',
            '_billing_address_1' => '[Order] Billing Address 1 (_billing_address_1)',
            '_billing_address_2' => '[Order] Billing Address 2 (_billing_address_2)',
            '_billing_city' => '[Order] Billing City (_billing_city)',
            '_billing_state' => '[Order] Billing State (_billing_state)',
            '_billing_postcode' => '[Order] Billing Postcode (_billing_postcode)',
            '_billing_country' => '[Order] Billing Country (_billing_country)',
            '_billing_email' => '[Order] Billing Email (_billing_email)',
            '_billing_phone' => '[Order] Billing Phone (_billing_phone)',
            
            // Shipping fields
            '_shipping_first_name' => '[Order] Shipping First Name (_shipping_first_name)',
            '_shipping_last_name' => '[Order] Shipping Last Name (_shipping_last_name)',
            '_shipping_company' => '[Order] Shipping Company (_shipping_company)',
            '_shipping_address_1' => '[Order] Shipping Address 1 (_shipping_address_1)',
            '_shipping_address_2' => '[Order] Shipping Address 2 (_shipping_address_2)',
            '_shipping_city' => '[Order] Shipping City (_shipping_city)',
            '_shipping_state' => '[Order] Shipping State (_shipping_state)',
            '_shipping_postcode' => '[Order] Shipping Postcode (_shipping_postcode)',
            '_shipping_country' => '[Order] Shipping Country (_shipping_country)',
            '_shipping_phone' => '[Order] Shipping Phone (_shipping_phone)',
            
            // Order details
            '_order_total' => '[Order] Order Total (_order_total)',
            '_order_tax' => '[Order] Order Tax (_order_tax)',
            '_order_shipping' => '[Order] Order Shipping (_order_shipping)',
            '_order_discount' => '[Order] Order Discount (_order_discount)',
            '_cart_discount' => '[Order] Cart Discount (_cart_discount)',
            '_order_currency' => '[Order] Order Currency (_order_currency)',
            '_payment_method' => '[Order] Payment Method (_payment_method)',
            '_payment_method_title' => '[Order] Payment Method Title (_payment_method_title)',
            '_transaction_id' => '[Order] Transaction ID (_transaction_id)',
            '_customer_user' => '[Order] Customer User ID (_customer_user)',
            '_order_key' => '[Order] Order Key (_order_key)',
            '_order_stock_reduced' => '[Order] Order Stock Reduced (_order_stock_reduced)',
            '_download_permissions_granted' => '[Order] Download Permissions Granted (_download_permissions_granted)',
            '_recorded_sales' => '[Order] Recorded Sales (_recorded_sales)',
            '_recorded_coupon_usage_counts' => '[Order] Recorded Coupon Usage (_recorded_coupon_usage_counts)',
            
            // Shipping methods
            '_shipping_method' => '[Order] Shipping Method (_shipping_method)',
            '_shipping_method_title' => '[Order] Shipping Method Title (_shipping_method_title)',
        ];
        
        // Add comprehensive WooCommerce product fields
        $wc_product_fields = [
            // Basic product info
            '_sku' => '[Product] SKU (_sku)',
            '_weight' => '[Product] Weight (_weight)',
            '_length' => '[Product] Length (_length)',
            '_width' => '[Product] Width (_width)',
            '_height' => '[Product] Height (_height)',
            '_price' => '[Product] Price (_price)',
            '_regular_price' => '[Product] Regular Price (_regular_price)',
            '_sale_price' => '[Product] Sale Price (_sale_price)',
            '_sale_price_dates_from' => '[Product] Sale Price From (_sale_price_dates_from)',
            '_sale_price_dates_to' => '[Product] Sale Price To (_sale_price_dates_to)',
            
            // Stock management
            '_stock_status' => '[Product] Stock Status (_stock_status)',
            '_manage_stock' => '[Product] Manage Stock (_manage_stock)',
            '_stock' => '[Product] Stock Quantity (_stock)',
            '_backorders' => '[Product] Backorders (_backorders)',
            '_low_stock_amount' => '[Product] Low Stock Amount (_low_stock_amount)',
            '_sold_individually' => '[Product] Sold Individually (_sold_individually)',
            
            // Product settings
            '_virtual' => '[Product] Virtual (_virtual)',
            '_downloadable' => '[Product] Downloadable (_downloadable)',
            '_featured' => '[Product] Featured (_featured)',
            '_visibility' => '[Product] Visibility (_visibility)',
            '_tax_status' => '[Product] Tax Status (_tax_status)',
            '_tax_class' => '[Product] Tax Class (_tax_class)',
            
            // Shipping
            '_shipping_class' => '[Product] Shipping Class (_shipping_class)',
            '_shipping_class_id' => '[Product] Shipping Class ID (_shipping_class_id)',
            
            // Images
            '_thumbnail_id' => '[Product] Thumbnail ID (_thumbnail_id)',
            '_product_image_gallery' => '[Product] Image Gallery (_product_image_gallery)',
            
            // Reviews
            '_wc_average_rating' => '[Product] Average Rating (_wc_average_rating)',
            '_wc_review_count' => '[Product] Review Count (_wc_review_count)',
            
            // Other
            '_product_version' => '[Product] Product Version (_product_version)',
            '_product_url' => '[Product] Product URL (_product_url)',
            '_button_text' => '[Product] Button Text (_button_text)',
        ];
        
        // Process order meta fields
        foreach ($order_meta_keys as $meta) {
            if (!isset($fields[$meta->meta_key])) {
                $display_name = ucwords(str_replace(['_', '-'], ' ', $meta->meta_key));
                $fields[$meta->meta_key] = '[Order] ' . $display_name . ' (' . $meta->meta_key . ')';
            }
        }
        
        // Process product meta fields
        foreach ($product_meta_keys as $meta) {
            if (!isset($fields[$meta->meta_key])) {
                $display_name = ucwords(str_replace(['_', '-'], ' ', $meta->meta_key));
                $fields[$meta->meta_key] = '[Product] ' . $display_name . ' (' . $meta->meta_key . ')';
            }
        }
        
        // Merge WooCommerce fields (they take precedence over auto-detected ones)
        $fields = array_merge($fields, $wc_order_fields, $wc_product_fields);
        
        // Add common custom fields that might contain courier info
        $custom_fields = [
            'courier' => '[Custom] Courier (courier)',
            'shipping_courier' => '[Custom] Shipping Courier (shipping_courier)',
            'delivery_method' => '[Custom] Delivery Method (delivery_method)',
            'courier_service' => '[Custom] Courier Service (courier_service)',
            'shipping_service' => '[Custom] Shipping Service (shipping_service)',
            'delivery_service' => '[Custom] Delivery Service (delivery_service)',
            'courier_company' => '[Custom] Courier Company (courier_company)',
            'shipping_company' => '[Custom] Shipping Company (shipping_company)',
            'transport_method' => '[Custom] Transport Method (transport_method)',
            'shipping_provider' => '[Custom] Shipping Provider (shipping_provider)',
        ];
        
        $fields = array_merge($fields, $custom_fields);
        
        // Get custom fields from various plugins
        $plugin_fields = $this->get_custom_fields();
        $fields = array_merge($fields, $plugin_fields);
        
        return $fields;
    }
    
    /**
     * Get custom fields from various plugins
     */
    private function get_custom_fields() {
        $custom_fields = [];
        
        // ACF Fields
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups();
            foreach ($field_groups as $group) {
                $fields = acf_get_fields($group['key']);
                if ($fields) {
                    foreach ($fields as $field) {
                        $custom_fields[$field['name']] = '[ACF] ' . $field['label'] . ' (' . $field['name'] . ')';
                    }
                }
            }
        }
        
        // Meta Box fields
        if (function_exists('rwmb_get_registry')) {
            $meta_boxes = rwmb_get_registry('meta_box')->all();
            foreach ($meta_boxes as $meta_box) {
                if (isset($meta_box->fields)) {
                    foreach ($meta_box->fields as $field) {
                        if (isset($field['id'])) {
                            $label = isset($field['name']) ? $field['name'] : ucwords(str_replace('_', ' ', $field['id']));
                            $custom_fields[$field['id']] = '[Meta Box] ' . $label . ' (' . $field['id'] . ')';
                        }
                    }
                }
            }
        }
        
        // Toolset Types fields
        if (function_exists('wpcf_admin_fields_get_fields')) {
            $toolset_fields = wpcf_admin_fields_get_fields();
            foreach ($toolset_fields as $field) {
                $custom_fields['wpcf-' . $field['slug']] = '[Toolset] ' . $field['name'] . ' (wpcf-' . $field['slug'] . ')';
            }
        }
        
        return $custom_fields;
    }
    
    /**
     * AJAX: Refresh meta fields
     */
    public function ajax_refresh_meta_fields() {
        check_ajax_referer('dmm_refresh_meta_fields', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Clear any cached meta fields (if you implement caching later)
        wp_send_json_success([
            'message' => __('Meta fields refreshed successfully.', 'dmm-delivery-bridge')
        ]);
    }
    
    /**
     * AJAX: Check logs
     */
    public function ajax_check_logs() {
        check_ajax_referer('dmm_check_logs', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dmm_delivery_logs';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            wp_send_json_error([
                'message' => __('Log table does not exist. Please recreate it.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Get log count
        $log_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get recent logs
        $recent_logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
        
        $message = sprintf(__('Log table exists. Total logs: %d. Recent logs:', 'dmm-delivery-bridge'), $log_count);
        
        if (!empty($recent_logs)) {
            $message .= '<ul>';
            foreach ($recent_logs as $log) {
                $message .= '<li>Order #' . $log->order_id . ' - ' . $log->status . ' - ' . $log->created_at . '</li>';
            }
            $message .= '</ul>';
        } else {
            $message .= ' ' . __('No recent logs found.', 'dmm-delivery-bridge');
        }
        
        wp_send_json_success([
            'message' => $message
        ]);
    }
    
    /**
     * AJAX: Create log table
     */
    public function ajax_create_log_table() {
        check_ajax_referer('dmm_create_log_table', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        try {
            $this->create_log_table();
            
            // Verify table was created
            global $wpdb;
            $table_name = $wpdb->prefix . 'dmm_delivery_logs';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            
            if ($table_exists) {
                wp_send_json_success([
                    'message' => __('Log table created successfully.', 'dmm-delivery-bridge')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to create log table.', 'dmm-delivery-bridge')
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => sprintf(__('Error creating log table: %s', 'dmm-delivery-bridge'), $e->getMessage())
            ]);
        }
    }
    
    /**
     * Get courier from order
     */
    private function get_courier_from_order($order) {
        $courier_meta_field = isset($this->options['courier_meta_field']) ? $this->options['courier_meta_field'] : '';
        
        if (empty($courier_meta_field)) {
            return null;
        }
        
        $courier_value = '';
        
        // First try to get from order meta
        $courier_value = get_post_meta($order->get_id(), $courier_meta_field, true);
        
        // If not found in order meta, try to get from product meta
        if (empty($courier_value)) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $product_courier_value = get_post_meta($product->get_id(), $courier_meta_field, true);
                    if (!empty($product_courier_value)) {
                        $courier_value = $product_courier_value;
                        break; // Use the first product's courier value
                    }
                }
            }
        }
        
        if (empty($courier_value)) {
            return null;
        }
        
        // Map common courier names to codes
        $courier_mapping = [
            'acs' => 'ACS',
            'acs courier' => 'ACS',
            'speedex' => 'SPX',
            'elta' => 'ELT',
            'elta courier' => 'ELT',
            'geniki taxydromiki' => 'GTX',
            'geniki' => 'GTX',
            'dhl' => 'DHL',
            'fedex' => 'FDX',
            'ups' => 'UPS',
        ];
        
        $courier_lower = strtolower(trim($courier_value));
        
        if (isset($courier_mapping[$courier_lower])) {
            return $courier_mapping[$courier_lower];
        }
        
        // Return the original value if no mapping found (first 3 letters, uppercase)
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $courier_value), 0, 3));
    }
    
    /**
     * AJAX: Start bulk order sending
     */
    public function ajax_bulk_send_orders() {
        check_ajax_referer('dmm_bulk_send_orders', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Get all orders that haven't been sent yet
        $unsent_orders = $this->get_unsent_orders();
        
        if (empty($unsent_orders)) {
            wp_send_json_success([
                'message' => __('No unsent orders found.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Initialize bulk processing
        $this->init_bulk_processing($unsent_orders);
        
        wp_send_json_success([
            'message' => sprintf(__('Started processing %d orders.', 'dmm-delivery-bridge'), count($unsent_orders)),
            'total_orders' => count($unsent_orders)
        ]);
    }
    
    /**
     * AJAX: Start bulk sync (update all sent orders)
     */
    public function ajax_bulk_sync_orders() {
        check_ajax_referer('dmm_bulk_sync_orders', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Get all orders that have been sent to DMM Delivery
        $sent_orders = $this->get_sent_orders();
        
        if (empty($sent_orders)) {
            wp_send_json_success([
                'message' => __('No sent orders found to sync.', 'dmm-delivery-bridge')
            ]);
        }
        
        // Initialize bulk sync processing
        $this->init_bulk_sync_processing($sent_orders);
        
        wp_send_json_success([
            'message' => sprintf(__('Started syncing %d orders.', 'dmm-delivery-bridge'), count($sent_orders)),
            'total_orders' => count($sent_orders)
        ]);
    }
    
    /**
     * AJAX: Get bulk processing progress
     */
    public function ajax_get_bulk_progress() {
        check_ajax_referer('dmm_get_bulk_progress', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $progress = get_option('dmm_bulk_progress', [
            'status' => 'idle',
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'current_batch' => 0,
            'total_batches' => 0
        ]);
        
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX: Cancel bulk processing
     */
    public function ajax_cancel_bulk_send() {
        check_ajax_referer('dmm_cancel_bulk_send', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        update_option('dmm_bulk_progress', [
            'status' => 'cancelled',
            'total' => 0,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'current_batch' => 0,
            'total_batches' => 0
        ]);
        
        wp_send_json_success([
            'message' => __('Bulk processing cancelled.', 'dmm-delivery-bridge')
        ]);
    }
    
    /**
     * AJAX: Process bulk orders (background processing)
     */
    public function ajax_process_bulk_orders() {
        check_ajax_referer('dmm_process_bulk_orders', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $this->process_bulk_batch();
        
        wp_die('OK');
    }
    
    /**
     * Get unsent orders
     */
    private function get_unsent_orders() {
        global $wpdb;
        
        $query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dmm_delivery_sent'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold')
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
            ORDER BY p.post_date ASC
        ";
        
        return $wpdb->get_col($query);
    }
    
    /**
     * Get all orders that have been sent to DMM Delivery
     */
    private function get_sent_orders() {
        global $wpdb;
        
        $query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dmm_delivery_sent'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-cancelled')
            AND pm.meta_value = 'yes'
            ORDER BY p.post_date ASC
        ";
        
        return $wpdb->get_col($query);
    }
    
    /**
     * Initialize bulk sync processing
     */
    private function init_bulk_sync_processing($order_ids) {
        $batch_size = 3; // Process 3 orders at a time for sync (smaller batches for updates)
        $batches = array_chunk($order_ids, $batch_size);
        
        // Initialize progress
        update_option('dmm_bulk_progress', [
            'status' => 'syncing',
            'total' => count($order_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'current_batch' => 0,
            'total_batches' => count($batches),
            'batches' => $batches,
            'operation' => 'sync'
        ]);
    }
    
    /**
     * Initialize bulk processing
     */
    private function init_bulk_processing($order_ids) {
        $batch_size = 5; // Process 5 orders at a time
        $batches = array_chunk($order_ids, $batch_size);
        
        // Initialize progress
        update_option('dmm_bulk_progress', [
            'status' => 'processing',
            'total' => count($order_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'current_batch' => 0,
            'total_batches' => count($batches),
            'batches' => $batches
        ]);
    }
    
    /**
     * Process a batch of orders
     */
    private function process_bulk_batch() {
        $progress = get_option('dmm_bulk_progress');
        
        if (!in_array($progress['status'], ['processing', 'syncing'])) {
            return;
        }
        
        $current_batch = $progress['current_batch'];
        $batches = $progress['batches'];
        
        if ($current_batch >= count($batches)) {
            // All batches completed
            $progress['status'] = 'completed';
            $progress['status_text'] = sprintf(
                __('Completed! Processed %d orders (%d successful, %d failed)', 'dmm-delivery-bridge'),
                $progress['processed'],
                $progress['successful'],
                $progress['failed']
            );
            update_option('dmm_bulk_progress', $progress);
            return;
        }
        
        $batch_orders = $batches[$current_batch];
        $batch_successful = 0;
        $batch_failed = 0;
        
        foreach ($batch_orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                $batch_failed++;
                continue;
            }
            
            try {
                if ($progress['status'] === 'syncing') {
                    // Sync existing order
                    $this->sync_order($order);
                    
                    // Check if sync was successful (order should still be marked as sent)
                    $sent_status = get_post_meta($order_id, '_dmm_delivery_sent', true);
                    if ($sent_status === 'yes') {
                        $batch_successful++;
                    } else {
                        $batch_failed++;
                    }
                } else {
                    // Process new order
                    $this->process_order($order);
                    
                    // Check if it was successful
                    $sent_status = get_post_meta($order_id, '_dmm_delivery_sent', true);
                    if ($sent_status === 'yes') {
                        $batch_successful++;
                    } else {
                        $batch_failed++;
                    }
                }
            } catch (Exception $e) {
                $batch_failed++;
                $operation = $progress['status'] === 'syncing' ? 'sync' : 'processing';
                error_log('DMM Delivery Bridge - Bulk ' . $operation . ' error for order ' . $order_id . ': ' . $e->getMessage());
            }
        }
        
        // Update progress
        $progress['processed'] += count($batch_orders);
        $progress['successful'] += $batch_successful;
        $progress['failed'] += $batch_failed;
        $progress['current_batch']++;
        
        $progress['status_text'] = sprintf(
            __('Processing batch %d of %d...', 'dmm-delivery-bridge'),
            $progress['current_batch'],
            $progress['total_batches']
        );
        
        update_option('dmm_bulk_progress', $progress);
    }
    
    /**
     * Sync a single order (used by bulk sync)
     */
    private function sync_order($order) {
        $order_id = $order->get_id();
        
        // Check if order was previously sent
        $dmm_order_id = get_post_meta($order_id, '_dmm_delivery_order_id', true);
        if (!$dmm_order_id) {
            throw new Exception('Order has not been sent to DMM Delivery yet');
        }
        
        // Prepare updated order data
        $order_data = $this->prepare_order_data($order);
        
        // Add sync flag to indicate this is an update
        $order_data['sync_update'] = true;
        $order_data['dmm_order_id'] = $dmm_order_id;
        
        // Send update to API
        $response = $this->send_to_api($order_data);
        
        // Log the sync attempt
        $this->log_request($order_id, $order_data, $response);
        
        if (!$response || !$response['success']) {
            $error_message = $response ? $response['message'] : 'Unknown error occurred';
            throw new Exception('Sync failed: ' . $error_message);
        }
        
        // Add order note
        $order->add_order_note(__('Order synced with DMM Delivery system successfully.', 'dmm-delivery-bridge'));
    }
}

// Initialize the plugin
DMM_Delivery_Bridge::getInstance();
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
        add_action('wp_ajax_dmm_diagnose_orders', [$this, 'ajax_diagnose_orders']);
        add_action('wp_ajax_dmm_force_resend_all', [$this, 'ajax_force_resend_all']);
        add_action('wp_ajax_dmm_test_force_resend', [$this, 'ajax_test_force_resend']);
        add_action('wp_ajax_dmm_send_all_orders_simple', [$this, 'ajax_send_all_orders_simple']);
        
        // ACS Courier AJAX handlers
        add_action('wp_ajax_dmm_acs_track_shipment', [$this, 'ajax_acs_track_shipment']);
        add_action('wp_ajax_dmm_acs_create_voucher', [$this, 'ajax_acs_create_voucher']);
        add_action('wp_ajax_dmm_acs_calculate_price', [$this, 'ajax_acs_calculate_price']);
        add_action('wp_ajax_dmm_acs_validate_address', [$this, 'ajax_acs_validate_address']);
        add_action('wp_ajax_dmm_acs_find_stations', [$this, 'ajax_acs_find_stations']);
        add_action('wp_ajax_dmm_acs_test_connection', [$this, 'ajax_acs_test_connection']);
        
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
            'debug_mode' => 'no',
            'performance_mode' => 'balanced',
            // ACS Courier settings
            'acs_api_endpoint' => 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest',
            'acs_api_key' => '',
            'acs_company_id' => '',
            'acs_company_password' => '',
            'acs_user_id' => '',
            'acs_user_password' => '',
            'acs_enabled' => 'no'
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
        
        add_settings_field(
            'performance_mode',
            __('Performance Mode', 'dmm-delivery-bridge'),
            [$this, 'performance_mode_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        // ACS Courier Settings Section
        add_settings_section(
            'dmm_delivery_bridge_acs_section',
            __('ACS Courier Integration', 'dmm-delivery-bridge'),
            [$this, 'acs_section_callback'],
            'dmm_delivery_bridge_settings'
        );
        
        add_settings_field(
            'acs_enabled',
            __('Enable ACS Courier', 'dmm-delivery-bridge'),
            [$this, 'acs_enabled_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
        );
        
        add_settings_field(
            'acs_api_endpoint',
            __('ACS API Endpoint', 'dmm-delivery-bridge'),
            [$this, 'acs_api_endpoint_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
        );
        
        add_settings_field(
            'acs_api_key',
            __('ACS API Key', 'dmm-delivery-bridge'),
            [$this, 'acs_api_key_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
        );
        
        add_settings_field(
            'acs_company_id',
            __('ACS Company ID', 'dmm-delivery-bridge'),
            [$this, 'acs_company_id_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
        );
        
        add_settings_field(
            'acs_company_password',
            __('ACS Company Password', 'dmm-delivery-bridge'),
            [$this, 'acs_company_password_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
        );
        
        add_settings_field(
            'acs_user_id',
            __('ACS User ID', 'dmm-delivery-bridge'),
            [$this, 'acs_user_id_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
        );
        
        add_settings_field(
            'acs_user_password',
            __('ACS User Password', 'dmm-delivery-bridge'),
            [$this, 'acs_user_password_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_acs_section'
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
                    <?php _e('Test DMM API Connection', 'dmm-delivery-bridge'); ?>
                </button>
                <button type="button" id="dmm-acs-test-connection" class="button button-secondary">
                    <?php _e('Test ACS Courier Connection', 'dmm-delivery-bridge'); ?>
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
                <button type="button" id="dmm-diagnose-orders" class="button button-secondary">
                    <?php _e('🔍 Diagnose Orders', 'dmm-delivery-bridge'); ?>
                </button>
                <div id="dmm-debug-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="dmm-acs-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #fff8dc;">
                <h3><?php _e('ACS Courier Integration', 'dmm-delivery-bridge'); ?></h3>
                <p><?php _e('Direct integration with ACS Courier API for tracking and shipment management.', 'dmm-delivery-bridge'); ?></p>
                <p style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; color: #856404;">
                    <strong><?php _e('Important:', 'dmm-delivery-bridge'); ?></strong> <?php _e('You need a valid ACS API Key to use this integration. The API key may be restricted to specific domains or IP addresses. Contact ACS to get your API key and ensure your WordPress site\'s domain/IP is whitelisted.', 'dmm-delivery-bridge'); ?>
                </p>
                
                <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('ACS Courier Tools', 'dmm-delivery-bridge'); ?></h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Track Shipment:', 'dmm-delivery-bridge'); ?></label>
                            <input type="text" id="acs-voucher-number" placeholder="<?php _e('Enter voucher number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                            <button type="button" id="dmm-acs-track" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                <?php _e('🔍 Track Shipment', 'dmm-delivery-bridge'); ?>
                            </button>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Find Stations:', 'dmm-delivery-bridge'); ?></label>
                            <input type="text" id="acs-zip-code" placeholder="<?php _e('Enter ZIP code', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                            <button type="button" id="dmm-acs-find-stations" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                <?php _e('📍 Find Stations', 'dmm-delivery-bridge'); ?>
                            </button>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <button type="button" id="dmm-acs-get-stations" class="button button-secondary">
                            <?php _e('🏢 Get All Stations', 'dmm-delivery-bridge'); ?>
                        </button>
                        <button type="button" id="dmm-acs-validate-address" class="button button-secondary">
                            <?php _e('✅ Validate Address', 'dmm-delivery-bridge'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="dmm-acs-result" style="margin-top: 10px;"></div>
            </div>
            
            <div class="dmm-bulk-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f0f8ff;">
                <h3><?php _e('Bulk Order Processing', 'dmm-delivery-bridge'); ?></h3>
                <p><?php _e('Send all unsent orders to DMM Delivery system in the background. This process runs safely without timeouts.', 'dmm-delivery-bridge'); ?></p>
                
                <div style="background: #fff; border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 8px 0; color: #333;"><?php _e('Performance Information', 'dmm-delivery-bridge'); ?></h4>
                    <div style="font-size: 12px; color: #666;">
                        <?php
                        $memory_limit = ini_get('memory_limit');
                        $batch_size = $this->get_optimal_batch_size();
                        $performance_mode = isset($this->options['performance_mode']) ? $this->options['performance_mode'] : 'balanced';
                        ?>
                        <strong><?php _e('Current Settings:', 'dmm-delivery-bridge'); ?></strong><br>
                        • <?php _e('Performance Mode:', 'dmm-delivery-bridge'); ?> <span style="color: #0073aa; font-weight: bold;"><?php echo ucfirst($performance_mode); ?></span><br>
                        • <?php _e('Batch Size:', 'dmm-delivery-bridge'); ?> <span style="color: #0073aa; font-weight: bold;"><?php echo $batch_size; ?> <?php _e('orders per batch', 'dmm-delivery-bridge'); ?></span><br>
                        • <?php _e('Server Memory:', 'dmm-delivery-bridge'); ?> <span style="color: #0073aa; font-weight: bold;"><?php echo $memory_limit; ?></span><br>
                        • <?php _e('Processing Speed:', 'dmm-delivery-bridge'); ?> <span style="color: #0073aa; font-weight: bold;"><?php echo round($batch_size / 1.5, 1); ?> <?php _e('orders per second', 'dmm-delivery-bridge'); ?></span>
                    </div>
                </div>
                
                <div id="dmm-bulk-controls">
                    <button type="button" id="dmm-start-bulk-send" class="button button-primary">
                        <?php _e('🚀 Start Bulk Send', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" id="dmm-start-bulk-sync" class="button button-secondary">
                        <?php _e('🔄 Sync Up All', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" id="dmm-force-resend-all" class="button button-secondary" style="background: #ff6b6b; color: white; border-color: #ff6b6b;">
                        <?php _e('⚠️ Force Resend All Orders', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" id="dmm-test-force-resend" class="button button-secondary" style="background: #28a745; color: white; border-color: #28a745;">
                        <?php _e('🧪 Test Force Resend', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" id="dmm-send-all-simple" class="button button-primary" style="background: #007cba; color: white; border-color: #007cba; font-weight: bold;">
                        <?php _e('📤 Send All Orders Now (Simple)', 'dmm-delivery-bridge'); ?>
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
            
            $('#dmm-diagnose-orders').on('click', function() {
                var button = $(this);
                var result = $('#dmm-debug-result');
                
                button.prop('disabled', true).text('<?php _e('🔍 Diagnosing...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_diagnose_orders',
                        nonce: '<?php echo wp_create_nonce('dmm_diagnose_orders'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Diagnosis failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🔍 Diagnose Orders', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // ACS Courier functionality
            $('#dmm-acs-test-connection').on('click', function() {
                var button = $(this);
                var result = $('#dmm-test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing ACS...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_acs_test_connection',
                        nonce: '<?php echo wp_create_nonce('dmm_acs_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('ACS connection test failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test ACS Courier Connection', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-acs-track').on('click', function() {
                var button = $(this);
                var result = $('#dmm-acs-result');
                var voucherNumber = $('#acs-voucher-number').val();
                
                if (!voucherNumber) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a voucher number.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('🔍 Tracking...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_acs_track_shipment',
                        voucher_number: voucherNumber,
                        nonce: '<?php echo wp_create_nonce('dmm_acs_track_shipment'); ?>'
                    },
                    success: function(response) {
                        console.log('Full AJAX Response:', response);
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p><strong><?php _e('Tracking Results:', 'dmm-delivery-bridge'); ?></strong></p>';
                            console.log('Events array:', response.data.events);
                            console.log('Events length:', response.data.events ? response.data.events.length : 'undefined');
                            if (response.data.events && response.data.events.length > 0) {
                                html += '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
                                html += '<thead><tr><th><?php _e('Date/Time', 'dmm-delivery-bridge'); ?></th><th><?php _e('Status', 'dmm-delivery-bridge'); ?></th><th><?php _e('Location', 'dmm-delivery-bridge'); ?></th><th><?php _e('Notes', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                response.data.events.forEach(function(event) {
                                    html += '<tr><td>' + event.datetime + '</td><td>' + event.action + '</td><td>' + event.location + '</td><td>' + event.notes + '</td></tr>';
                                });
                                html += '</tbody></table>';
                            } else {
                                html += '<p><?php _e('No tracking events found.', 'dmm-delivery-bridge'); ?></p>';
                                html += '<p><strong>Debug Info:</strong> Events array: ' + (response.data.events ? 'exists' : 'missing') + ', Length: ' + (response.data.events ? response.data.events.length : 'N/A') + '</p>';
                                
                                // Add debug information
                                html += '<details style="margin-top: 10px;"><summary>Debug Information (Click to see full response)</summary>';
                                html += '<pre style="background: #f1f1f1; padding: 10px; overflow: auto; max-height: 300px;">';
                                html += 'Full Response: ' + JSON.stringify(response, null, 2);
                                html += '</pre></details>';
                            }
                            html += '</div>';
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Tracking failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🔍 Track Shipment', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-acs-find-stations').on('click', function() {
                var button = $(this);
                var result = $('#dmm-acs-result');
                var zipCode = $('#acs-zip-code').val();
                
                if (!zipCode) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a ZIP code.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('📍 Finding...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_acs_find_stations',
                        zip_code: zipCode,
                        nonce: '<?php echo wp_create_nonce('dmm_acs_find_stations'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p><strong><?php _e('Stations Found:', 'dmm-delivery-bridge'); ?></strong></p>';
                            if (response.data.stations && response.data.stations.length > 0) {
                                html += '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
                                html += '<thead><tr><th><?php _e('Station Name', 'dmm-delivery-bridge'); ?></th><th><?php _e('Address', 'dmm-delivery-bridge'); ?></th><th><?php _e('Phone', 'dmm-delivery-bridge'); ?></th><th><?php _e('Hours', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                response.data.stations.forEach(function(station) {
                                    html += '<tr><td>' + (station.name || 'N/A') + '</td><td>' + (station.address || 'N/A') + '</td><td>' + (station.phone || 'N/A') + '</td><td>' + (station.hours || 'N/A') + '</td></tr>';
                                });
                                html += '</tbody></table>';
                            } else {
                                html += '<p><?php _e('No stations found for this ZIP code.', 'dmm-delivery-bridge'); ?></p>';
                            }
                            html += '</div>';
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Station search failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('📍 Find Stations', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-acs-get-stations').on('click', function() {
                var button = $(this);
                var result = $('#dmm-acs-result');
                
                button.prop('disabled', true).text('<?php _e('🏢 Loading...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_acs_get_stations',
                        nonce: '<?php echo wp_create_nonce('dmm_acs_get_stations'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p><strong><?php _e('All ACS Stations:', 'dmm-delivery-bridge'); ?></strong></p>';
                            if (response.data.stations && response.data.stations.length > 0) {
                                html += '<p><?php _e('Found', 'dmm-delivery-bridge'); ?> ' + response.data.stations.length + ' <?php _e('stations.', 'dmm-delivery-bridge'); ?></p>';
                                html += '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
                                html += '<thead><tr><th><?php _e('Station Name', 'dmm-delivery-bridge'); ?></th><th><?php _e('Address', 'dmm-delivery-bridge'); ?></th><th><?php _e('Phone', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                response.data.stations.slice(0, 20).forEach(function(station) {
                                    html += '<tr><td>' + (station.name || 'N/A') + '</td><td>' + (station.address || 'N/A') + '</td><td>' + (station.phone || 'N/A') + '</td></tr>';
                                });
                                html += '</tbody></table>';
                                if (response.data.stations.length > 20) {
                                    html += '<p><em><?php _e('Showing first 20 stations. Total:', 'dmm-delivery-bridge'); ?> ' + response.data.stations.length + '</em></p>';
                                }
                            } else {
                                html += '<p><?php _e('No stations found.', 'dmm-delivery-bridge'); ?></p>';
                            }
                            html += '</div>';
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Failed to get stations.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🏢 Get All Stations', 'dmm-delivery-bridge'); ?>');
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
            
            // Force resend handler
            $('#dmm-force-resend-all').on('click', function() {
                if (isBulkProcessing) return;
                
                if (!confirm('<?php _e('⚠️ WARNING: This will force resend ALL orders to DMM Delivery, even those already sent. This will update all orders with the latest data but may take a very long time. Are you sure you want to continue?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('⚠️ Starting...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_force_resend_all',
                        nonce: '<?php echo wp_create_nonce('dmm_force_resend_all'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Force resend response:', response);
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
                            button.prop('disabled', false).text('<?php _e('⚠️ Force Resend All Orders', 'dmm-delivery-bridge'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to start force resend.', 'dmm-delivery-bridge'); ?>');
                        button.prop('disabled', false).text('<?php _e('⚠️ Force Resend All Orders', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // Test force resend handler
            $('#dmm-test-force-resend').on('click', function() {
                var button = $(this);
                var result = $('#dmm-bulk-result');
                
                button.prop('disabled', true).text('<?php _e('🧪 Testing...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_test_force_resend',
                        nonce: '<?php echo wp_create_nonce('dmm_test_force_resend'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Test failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🧪 Test Force Resend', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // Simple send all orders handler
            $('#dmm-send-all-simple').on('click', function() {
                var button = $(this);
                var result = $('#dmm-bulk-result');
                
                if (!confirm('<?php _e('This will send ALL orders to DMM Delivery with the latest data (including product images). This may take several minutes. Continue?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('📤 Sending All Orders...', 'dmm-delivery-bridge'); ?>');
                result.html('<div class="notice notice-info inline"><p><?php _e('Sending all orders... This may take several minutes. Please wait.', 'dmm-delivery-bridge'); ?></p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_send_all_orders_simple',
                        nonce: '<?php echo wp_create_nonce('dmm_send_all_orders_simple'); ?>'
                    },
                    timeout: 300000, // 5 minutes timeout
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (status === 'timeout') {
                            result.html('<div class="notice notice-warning inline"><p><?php _e('Request timed out, but orders may still be processing. Check your logs for progress.', 'dmm-delivery-bridge'); ?></p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p><?php _e('Failed to send orders:', 'dmm-delivery-bridge'); ?> ' + error + '</p></div>');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('📤 Send All Orders Now (Simple)', 'dmm-delivery-bridge'); ?>');
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
                            console.log('Progress update:', data);
                            
                            if (data.status === 'completed') {
                                stopBulkProcessing();
                                $('#dmm-bulk-result').html('<div class="notice notice-success inline"><p><?php _e('Bulk processing completed!', 'dmm-delivery-bridge'); ?> ' + data.message + '</p></div>');
                            } else if (data.status === 'error') {
                                stopBulkProcessing();
                                $('#dmm-bulk-result').html('<div class="notice notice-error inline"><p><?php _e('Bulk processing failed:', 'dmm-delivery-bridge'); ?> ' + data.message + '</p></div>');
                            } else if (data.status === 'processing' || data.status === 'syncing' || data.status === 'force_resending') {
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
                
                var defaultText = '<?php _e('Processing orders...', 'dmm-delivery-bridge'); ?>';
                if (data.operation === 'sync') {
                    defaultText = '<?php _e('Syncing orders...', 'dmm-delivery-bridge'); ?>';
                } else if (data.operation === 'force_resend') {
                    defaultText = '<?php _e('Force resending orders...', 'dmm-delivery-bridge'); ?>';
                }
                
                $('#dmm-progress-text').text(data.status_text || defaultText);
                $('#dmm-progress-percentage').text(percentage + '%');
                $('#dmm-progress-bar').css('width', percentage + '%');
                $('#dmm-total-orders').text(data.total || 0);
                $('#dmm-processed-orders').text(data.processed || 0);
                $('#dmm-successful-orders').text(data.successful || 0);
                $('#dmm-failed-orders').text(data.failed || 0);
            }
            
            function startBulkProcessing() {
                // Process batches every 1.5 seconds via AJAX (faster processing)
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
                }, 1500); // Reduced from 3000ms to 1500ms
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
                $('#dmm-force-resend-all').prop('disabled', false).text('<?php _e('⚠️ Force Resend All Orders', 'dmm-delivery-bridge'); ?>').show();
                $('#dmm-test-force-resend').prop('disabled', false).text('<?php _e('🧪 Test Force Resend', 'dmm-delivery-bridge'); ?>').show();
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
    
    public function performance_mode_callback() {
        $value = isset($this->options['performance_mode']) ? $this->options['performance_mode'] : 'balanced';
        
        echo '<select name="dmm_delivery_bridge_options[performance_mode]">';
        echo '<option value="conservative" ' . selected($value, 'conservative', false) . '>' . __('Conservative (Slower, More Stable)', 'dmm-delivery-bridge') . '</option>';
        echo '<option value="balanced" ' . selected($value, 'balanced', false) . '>' . __('Balanced (Recommended)', 'dmm-delivery-bridge') . '</option>';
        echo '<option value="fast" ' . selected($value, 'fast', false) . '>' . __('Fast (Higher Server Load)', 'dmm-delivery-bridge') . '</option>';
        echo '</select>';
        
        echo '<p class="description">';
        echo __('<strong>Conservative:</strong> 3-7 orders per batch, longer delays<br>', 'dmm-delivery-bridge');
        echo __('<strong>Balanced:</strong> 7-15 orders per batch, optimized for most servers<br>', 'dmm-delivery-bridge');
        echo __('<strong>Fast:</strong> 15-25 orders per batch, requires powerful server', 'dmm-delivery-bridge');
        echo '</p>';
    }
    
    // ACS Courier Settings Callbacks
    public function acs_section_callback() {
        echo '<p>' . __('Configure ACS Courier API integration for direct courier services.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_enabled_callback() {
        $value = isset($this->options['acs_enabled']) ? $this->options['acs_enabled'] : 'no';
        echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[acs_enabled]" value="yes" ' . checked($value, 'yes', false) . ' /> ' . __('Enable ACS Courier integration', 'dmm-delivery-bridge') . '</label>';
        echo '<p class="description">' . __('Allow direct ACS Courier API calls from this WordPress site.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_api_endpoint_callback() {
        $value = isset($this->options['acs_api_endpoint']) ? $this->options['acs_api_endpoint'] : 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest';
        echo '<input type="url" name="dmm_delivery_bridge_options[acs_api_endpoint]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('ACS Courier API endpoint URL. Default: https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_api_key_callback() {
        $value = isset($this->options['acs_api_key']) ? $this->options['acs_api_key'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[acs_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ACS API key.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_company_id_callback() {
        $value = isset($this->options['acs_company_id']) ? $this->options['acs_company_id'] : '';
        echo '<input type="text" name="dmm_delivery_bridge_options[acs_company_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ACS Company ID.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_company_password_callback() {
        $value = isset($this->options['acs_company_password']) ? $this->options['acs_company_password'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[acs_company_password]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ACS Company Password.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_user_id_callback() {
        $value = isset($this->options['acs_user_id']) ? $this->options['acs_user_id'] : '';
        echo '<input type="text" name="dmm_delivery_bridge_options[acs_user_id]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ACS User ID.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function acs_user_password_callback() {
        $value = isset($this->options['acs_user_password']) ? $this->options['acs_user_password'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[acs_user_password]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ACS User Password.', 'dmm-delivery-bridge') . '</p>';
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
        
        // Debug logging
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
            error_log('DMM Delivery Bridge - ajax_bulk_sync_orders() called, found ' . count($sent_orders) . ' sent orders');
        }
        
        if (empty($sent_orders)) {
            wp_send_json_success([
                'message' => __('No sent orders found to sync. Make sure you have orders that have been successfully sent to DMM Delivery first.', 'dmm-delivery-bridge')
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
     * AJAX: Diagnose orders (debug helper)
     */
    public function ajax_diagnose_orders() {
        check_ajax_referer('dmm_diagnose_orders', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        global $wpdb;
        
        // Get total orders
        $total_orders = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order'
            AND post_status IN ('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-cancelled')
        ");
        
        // Get orders with _dmm_delivery_sent meta
        $orders_with_meta = $wpdb->get_results("
            SELECT p.ID, pm.meta_value, p.post_status
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dmm_delivery_sent'
            WHERE p.post_type = 'shop_order'
            ORDER BY p.post_date DESC
            LIMIT 20
        ");
        
        // Get sent orders count
        $sent_orders_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dmm_delivery_sent'
            WHERE p.post_type = 'shop_order'
            AND pm.meta_value = 'yes'
        ");
        
        // Get unsent orders count
        $unsent_orders_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dmm_delivery_sent'
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold')
            AND (pm.meta_value IS NULL OR pm.meta_value != 'yes')
        ");
        
        $message = sprintf(
            __('Diagnostic Results:<br>• Total orders: %d<br>• Sent orders: %d<br>• Unsent orders: %d<br><br>Recent orders with DMM meta:', 'dmm-delivery-bridge'),
            $total_orders,
            $sent_orders_count,
            $unsent_orders_count
        );
        
        if (!empty($orders_with_meta)) {
            $message .= '<ul>';
            foreach ($orders_with_meta as $order) {
                $message .= sprintf(
                    '<li>Order #%d (%s) - _dmm_delivery_sent = "%s"</li>',
                    $order->ID,
                    $order->post_status,
                    $order->meta_value
                );
            }
            $message .= '</ul>';
        } else {
            $message .= '<br>' . __('No orders found with _dmm_delivery_sent meta.', 'dmm-delivery-bridge');
        }
        
        wp_send_json_success([
            'message' => $message
        ]);
    }
    
    /**
     * AJAX: Force resend all orders (temporary function)
     */
    public function ajax_force_resend_all() {
        check_ajax_referer('dmm_force_resend_all', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Get ALL orders (both sent and unsent)
        $all_orders = $this->get_all_orders();
        
        // Debug logging
        error_log('DMM Delivery Bridge - ajax_force_resend_all() found ' . count($all_orders) . ' orders');
        
        if (empty($all_orders)) {
            // Let's also try a direct query to see what's happening
            global $wpdb;
            $total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'");
            error_log('DMM Delivery Bridge - Direct query found ' . $total_count . ' total orders');
            
            wp_send_json_success([
                'message' => sprintf(__('No orders found to resend. Direct query shows %d total orders in database.', 'dmm-delivery-bridge'), $total_count)
            ]);
        }
        
        // Initialize force resend processing
        $this->init_force_resend_processing($all_orders);
        
        // Verify the progress was set correctly
        $progress = get_option('dmm_bulk_progress');
        error_log('DMM Delivery Bridge - Progress after init: ' . print_r($progress, true));
        
        wp_send_json_success([
            'message' => sprintf(__('Started force resending %d orders. This will update all orders with latest data.', 'dmm-delivery-bridge'), count($all_orders)),
            'total_orders' => count($all_orders),
            'debug_progress' => $progress
        ]);
    }
    
    /**
     * AJAX: Test force resend (debug function)
     */
    public function ajax_test_force_resend() {
        check_ajax_referer('dmm_test_force_resend', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        global $wpdb;
        
        // Test different queries
        $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'");
        $orders_with_status = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-cancelled')");
        
        // Test the actual get_all_orders method
        $all_orders = $this->get_all_orders();
        
        $message = sprintf(
            __('Test Results:<br>• Total orders in database: %d<br>• Orders with specific statuses: %d<br>• Orders found by get_all_orders(): %d<br><br>Query used: SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = \'shop_order\' ORDER BY p.post_date ASC', 'dmm-delivery-bridge'),
            $total_orders,
            $orders_with_status,
            count($all_orders)
        );
        
        wp_send_json_success([
            'message' => $message
        ]);
    }
    
    /**
     * AJAX: Send all orders simple (no progress tracking)
     */
    public function ajax_send_all_orders_simple() {
        check_ajax_referer('dmm_send_all_orders_simple', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Get ALL orders
        $all_orders = $this->get_all_orders();
        
        if (empty($all_orders)) {
            wp_send_json_success([
                'message' => __('No orders found to send.', 'dmm-delivery-bridge')
            ]);
        }
        
        $successful = 0;
        $failed = 0;
        $total = count($all_orders);
        
        // Process orders in optimized batches
        $batch_size = $this->get_optimal_batch_size();
        $batches = array_chunk($all_orders, $batch_size);
        
        foreach ($batches as $batch) {
            // Process batch with parallel optimization
            $batch_results = $this->process_batch_optimized($batch);
            $successful += $batch_results['successful'];
            $failed += $batch_results['failed'];
            
            // Minimal delay between batches to prevent server overload
            usleep(50000); // 0.05 second delay (reduced from 1 second)
        }
        
        wp_send_json_success([
            'message' => sprintf(
                __('Completed! Sent %d orders successfully, %d failed out of %d total orders. All orders have been updated with latest data including product images.', 'dmm-delivery-bridge'),
                $successful,
                $failed,
                $total
            ),
            'successful' => $successful,
            'failed' => $failed,
            'total' => $total
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
            AND pm.meta_value = 'yes'
            ORDER BY p.post_date ASC
        ";
        
        $sent_orders = $wpdb->get_col($query);
        
        // Debug logging
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
            error_log('DMM Delivery Bridge - get_sent_orders() found ' . count($sent_orders) . ' sent orders');
            
            // Also check for any orders with _dmm_delivery_sent meta (regardless of value)
            $all_dmm_meta = $wpdb->get_results("
                SELECT p.ID, pm.meta_value 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_dmm_delivery_sent'
                WHERE p.post_type = 'shop_order'
                ORDER BY p.post_date DESC
                LIMIT 10
            ");
            
            error_log('DMM Delivery Bridge - Recent orders with _dmm_delivery_sent meta:');
            foreach ($all_dmm_meta as $meta) {
                error_log('  Order #' . $meta->ID . ' - _dmm_delivery_sent = ' . $meta->meta_value);
            }
        }
        
        return $sent_orders;
    }
    
    /**
     * Get ALL orders (for force resend)
     */
    private function get_all_orders() {
        global $wpdb;
        
        $query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'shop_order'
            ORDER BY p.post_date ASC
        ";
        
        $all_orders = $wpdb->get_col($query);
        
        // Debug logging
        if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
            error_log('DMM Delivery Bridge - get_all_orders() found ' . count($all_orders) . ' total orders');
        }
        
        return $all_orders;
    }
    
    /**
     * Initialize bulk sync processing
     */
    private function init_bulk_sync_processing($order_ids) {
        // Use optimized batch size for sync operations
        $batch_size = max(5, $this->get_optimal_batch_size() - 2); // Slightly smaller for updates
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
     * Initialize force resend processing
     */
    private function init_force_resend_processing($order_ids) {
        // Use optimized batch size for force resend
        $batch_size = $this->get_optimal_batch_size();
        $batches = array_chunk($order_ids, $batch_size);
        
        // Initialize progress
        update_option('dmm_bulk_progress', [
            'status' => 'force_resending',
            'total' => count($order_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'current_batch' => 0,
            'total_batches' => count($batches),
            'batches' => $batches,
            'operation' => 'force_resend'
        ]);
    }
    
    /**
     * Initialize bulk processing
     */
    private function init_bulk_processing($order_ids) {
        // Dynamic batch size based on server capabilities
        $batch_size = $this->get_optimal_batch_size();
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
        
        if (!in_array($progress['status'], ['processing', 'syncing', 'force_resending'])) {
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
                } elseif ($progress['status'] === 'force_resending') {
                    // Force resend - clear existing meta and resend
                    delete_post_meta($order_id, '_dmm_delivery_sent');
                    delete_post_meta($order_id, '_dmm_delivery_order_id');
                    delete_post_meta($order_id, '_dmm_delivery_shipment_id');
                    
                    // Process order as new
                    $this->process_order($order);
                    
                    // Check if it was successful
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
                $operation = $progress['status'] === 'syncing' ? 'sync' : ($progress['status'] === 'force_resending' ? 'force_resend' : 'processing');
                error_log('DMM Delivery Bridge - Bulk ' . $operation . ' error for order ' . $order_id . ': ' . $e->getMessage());
            }
        }
        
        // Update progress
        $progress['processed'] += count($batch_orders);
        $progress['successful'] += $batch_successful;
        $progress['failed'] += $batch_failed;
        $progress['current_batch']++;
        
        if ($progress['status'] === 'force_resending') {
            $progress['status_text'] = sprintf(
                __('Force resending batch %d of %d...', 'dmm-delivery-bridge'),
                $progress['current_batch'],
                $progress['total_batches']
            );
        } else {
            $progress['status_text'] = sprintf(
                __('Processing batch %d of %d...', 'dmm-delivery-bridge'),
                $progress['current_batch'],
                $progress['total_batches']
            );
        }
        
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
    
    /**
     * Get optimal batch size based on server capabilities
     */
    private function get_optimal_batch_size() {
        // Check server memory and capabilities
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->parse_memory_limit($memory_limit);
        
        // Base batch size on available memory
        if ($memory_bytes >= 512 * 1024 * 1024) { // 512MB+
            $base_size = 15;
        } elseif ($memory_bytes >= 256 * 1024 * 1024) { // 256MB+
            $base_size = 10;
        } else {
            $base_size = 7;
        }
        
        // Check if we have performance settings
        $performance_mode = isset($this->options['performance_mode']) ? $this->options['performance_mode'] : 'balanced';
        
        switch ($performance_mode) {
            case 'fast':
                return min($base_size * 2, 25); // Up to 25 orders per batch
            case 'conservative':
                return max($base_size / 2, 3); // Minimum 3 orders per batch
            case 'balanced':
            default:
                return $base_size;
        }
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parse_memory_limit($memory_limit) {
        if ($memory_limit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }
    
    /**
     * Process batch with optimizations
     */
    private function process_batch_optimized($order_ids) {
        $successful = 0;
        $failed = 0;
        
        // Pre-load all orders to reduce database queries
        $orders = [];
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders[$order_id] = $order;
            }
        }
        
        // Process orders with minimal delays
        foreach ($order_ids as $order_id) {
            if (!isset($orders[$order_id])) {
                $failed++;
                continue;
            }
            
            try {
                $order = $orders[$order_id];
                
                // Clear existing meta efficiently
                $this->clear_order_meta_efficiently($order_id);
                
                // Process order
                $this->process_order($order);
                
                // Check if successful
                $sent_status = get_post_meta($order_id, '_dmm_delivery_sent', true);
                if ($sent_status === 'yes') {
                    $successful++;
                } else {
                    $failed++;
                }
                
                // Minimal delay only if needed
                if (count($order_ids) > 10) {
                    usleep(10000); // 0.01 second delay for large batches
                }
                
            } catch (Exception $e) {
                $failed++;
                error_log('DMM Delivery Bridge - Batch processing error for order ' . $order_id . ': ' . $e->getMessage());
            }
        }
        
        return [
            'successful' => $successful,
            'failed' => $failed
        ];
    }
    
    /**
     * Clear order meta efficiently
     */
    private function clear_order_meta_efficiently($order_id) {
        global $wpdb;
        
        // Use single query to delete multiple meta keys
        $wpdb->delete(
            $wpdb->postmeta,
            [
                'post_id' => $order_id,
                'meta_key' => '_dmm_delivery_sent'
            ]
        );
        
        $wpdb->delete(
            $wpdb->postmeta,
            [
                'post_id' => $order_id,
                'meta_key' => '_dmm_delivery_order_id'
            ]
        );
        
        $wpdb->delete(
            $wpdb->postmeta,
            [
                'post_id' => $order_id,
                'meta_key' => '_dmm_delivery_shipment_id'
            ]
        );
    }
    
    /**
     * ACS Courier Service Class
     */
    private function get_acs_service() {
        return new DMM_ACS_Courier_Service($this->options);
    }
    
    /**
     * AJAX: Test ACS connection
     */
    public function ajax_acs_test_connection() {
        check_ajax_referer('dmm_acs_test_connection', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $acs_service = $this->get_acs_service();
        $response = $acs_service->test_connection();
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('ACS Courier connection test successful!', 'dmm-delivery-bridge')
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('ACS connection test failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Track ACS shipment
     */
    public function ajax_acs_track_shipment() {
        check_ajax_referer('dmm_acs_track_shipment', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $voucher_number = sanitize_text_field($_POST['voucher_number']);
        
        if (empty($voucher_number)) {
            wp_send_json_error([
                'message' => __('Voucher number is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $acs_service = $this->get_acs_service();
        $response = $acs_service->get_tracking_details($voucher_number);
        
        // Debug logging
        error_log('ACS Tracking Response: ' . print_r($response, true));
        
        if ($response['success']) {
            $events = $acs_service->parse_tracking_events($response['data']);
            error_log('Parsed Events: ' . print_r($events, true));
            
            // Fallback: If parsing fails, extract events directly from raw response
            if (empty($events) && isset($response['data']['ACSOutputResponce']['ACSTableOutput']['Table_Data'])) {
                $raw_events = $response['data']['ACSOutputResponce']['ACSTableOutput']['Table_Data'];
                $events = [];
                foreach ($raw_events as $event) {
                    if (isset($event['checkpoint_date_time'])) {
                        $events[] = [
                            'datetime' => $event['checkpoint_date_time'],
                            'action' => $event['checkpoint_action'] ?? '',
                            'location' => $event['checkpoint_location'] ?? '',
                            'notes' => $event['checkpoint_notes'] ?? '',
                            'status' => 'parsed',
                            'raw_data' => $event
                        ];
                    }
                }
                error_log('Fallback parsing found ' . count($events) . ' events');
            }
            
            wp_send_json_success([
                'message' => __('Tracking data retrieved successfully.', 'dmm-delivery-bridge'),
                'events' => $events,
                'raw_response' => $response['data'] // Include raw response for debugging
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Failed to track shipment: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Find ACS stations by ZIP code
     */
    public function ajax_acs_find_stations() {
        check_ajax_referer('dmm_acs_find_stations', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $zip_code = sanitize_text_field($_POST['zip_code']);
        
        if (empty($zip_code)) {
            wp_send_json_error([
                'message' => __('ZIP code is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $acs_service = $this->get_acs_service();
        $response = $acs_service->find_stations_by_zip_code($zip_code);
        
        if ($response['success']) {
            $stations = $acs_service->parse_stations($response['data']);
            wp_send_json_success([
                'message' => __('Stations found successfully.', 'dmm-delivery-bridge'),
                'stations' => $stations
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Failed to find stations: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Get all ACS stations
     */
    public function ajax_acs_get_stations() {
        check_ajax_referer('dmm_acs_get_stations', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $acs_service = $this->get_acs_service();
        $response = $acs_service->get_stations();
        
        if ($response['success']) {
            $stations = $acs_service->parse_stations($response['data']);
            wp_send_json_success([
                'message' => __('Stations retrieved successfully.', 'dmm-delivery-bridge'),
                'stations' => $stations
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Failed to get stations: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Validate address
     */
    public function ajax_acs_validate_address() {
        check_ajax_referer('dmm_acs_validate_address', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $address = sanitize_text_field($_POST['address']);
        
        if (empty($address)) {
            wp_send_json_error([
                'message' => __('Address is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $acs_service = $this->get_acs_service();
        $response = $acs_service->validate_address($address);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Address validation completed.', 'dmm-delivery-bridge'),
                'validation' => $response['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Address validation failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
}

/**
 * ACS Courier Service Class
 */
class DMM_ACS_Courier_Service {
    private $options;
    private $api_endpoint;
    private $api_key;
    private $company_id;
    private $company_password;
    private $user_id;
    private $user_password;
    
    public function __construct($options) {
        $this->options = $options;
        $this->api_endpoint = isset($options['acs_api_endpoint']) ? $options['acs_api_endpoint'] : 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest';
        $this->api_key = isset($options['acs_api_key']) ? $options['acs_api_key'] : '';
        $this->company_id = isset($options['acs_company_id']) ? $options['acs_company_id'] : '';
        $this->company_password = isset($options['acs_company_password']) ? $options['acs_company_password'] : '';
        $this->user_id = isset($options['acs_user_id']) ? $options['acs_user_id'] : '';
        $this->user_password = isset($options['acs_user_password']) ? $options['acs_user_password'] : '';
    }
    
    /**
     * Test ACS connection
     */
    public function test_connection() {
        // First check if API key is configured
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ACS API Key not configured. Please enter your ACS API Key in the plugin settings.',
                'data' => null
            ];
        }
        
        // Check if other credentials are configured
        if (empty($this->company_id) || empty($this->company_password) || empty($this->user_id) || empty($this->user_password)) {
            return [
                'success' => false,
                'error' => 'ACS credentials not fully configured. Please enter your Company ID, Company Password, User ID, and User Password.',
                'data' => null
            ];
        }
        
        // Try a simple tracking summary request with a test voucher number
        // Using a known working voucher number for connection testing
        $payload = [
            'ACSAlias' => 'ACS_Trackingsummary',
            'ACSInputParameters' => [
                'Company_ID' => $this->company_id,
                'Company_Password' => $this->company_password,
                'User_ID' => $this->user_id,
                'User_Password' => $this->user_password,
                'Language' => 'GR',
                'Voucher_No' => '9703411222' // Test voucher number that works with your setup
            ]
        ];
        
        $result = $this->make_api_call($payload);
        
        // If the test fails with 403, it's likely an API key issue
        if (!$result['success'] && strpos($result['error'], 'HTTP 403') !== false) {
            return [
                'success' => false,
                'error' => 'HTTP 403 Forbidden: Invalid or missing API Key. Please check your ACS API Key. The API key may be restricted to specific domains/IPs.',
                'data' => null
            ];
        }
        
        // If successful, provide more detailed information about the response
        if ($result['success']) {
            $result['message'] = 'ACS connection successful! API is responding correctly.';
            $result['debug_info'] = 'Response received from ACS API. Check the data field for detailed tracking information.';
        }
        
        return $result;
    }
    
    /**
     * Get tracking details
     */
    public function get_tracking_details($voucher_number) {
        $payload = [
            'ACSAlias' => 'ACS_TrackingDetails',
            'ACSInputParameters' => [
                'Company_ID' => $this->company_id,
                'Company_Password' => $this->company_password,
                'User_ID' => $this->user_id,
                'User_Password' => $this->user_password,
                'Language' => 'GR',
                'Voucher_No' => $voucher_number
            ]
        ];
        
        $result = $this->make_api_call($payload);
        
        // Store raw response for debugging
        if ($result['success'] && $result['data']) {
            $result['raw_response'] = $result['data'];
        }
        
        return $result;
    }
    
    /**
     * Find stations by ZIP code
     */
    public function find_stations_by_zip_code($zip_code) {
        $payload = [
            'ACSAlias' => 'ACS_Area_Find_By_Zip_Code',
            'ACSInputParameters' => [
                'Company_ID' => $this->company_id,
                'Company_Password' => $this->company_password,
                'User_ID' => $this->user_id,
                'User_Password' => $this->user_password,
                'Language' => 'GR',
                'Zip_Code' => $zip_code,
                'Show_Only_Inaccessible_Areas' => 0,
                'Country' => 'GR'
            ]
        ];
        
        return $this->make_api_call($payload);
    }
    
    /**
     * Get all stations
     */
    public function get_stations() {
        $payload = [
            'ACSAlias' => 'Acs_Stations',
            'ACSInputParameters' => [
                'Company_ID' => $this->company_id,
                'Company_Password' => $this->company_password,
                'User_ID' => $this->user_id,
                'User_Password' => $this->user_password,
                'Language' => 'GR',
            ]
        ];
        
        return $this->make_api_call($payload);
    }
    
    /**
     * Validate address
     */
    public function validate_address($address) {
        $payload = [
            'ACSAlias' => 'ACS_Address_Validation',
            'ACSInputParameters' => [
                'Company_ID' => $this->company_id,
                'Company_Password' => $this->company_password,
                'User_ID' => $this->user_id,
                'User_Password' => $this->user_password,
                'Language' => 'GR',
                'Address' => $address
            ]
        ];
        
        return $this->make_api_call($payload);
    }
    
    /**
     * Make API call to ACS
     */
    private function make_api_call($payload, $endpoint = null) {
        try {
            $api_endpoint = $endpoint ?: $this->api_endpoint;
            
            $args = [
                'method' => 'POST',
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'ACSApiKey' => $this->api_key
                ],
                'body' => json_encode($payload),
            ];
            
            $response = wp_remote_request($api_endpoint, $args);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message(),
                    'data' => null
                ];
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            if ($response_code >= 200 && $response_code < 300) {
                // Check for API execution errors
                if (isset($response_data['ACSExecution_HasError']) && $response_data['ACSExecution_HasError'] === true) {
                    $error_message = $response_data['ACSExecutionErrorMessage'] ?? 'API Error';
                    
                    // Provide more specific error messages
                    if (strpos($error_message, 'fill data error') !== false) {
                        $error_message = 'Data validation error. Please check your credentials and parameters.';
                    } elseif (strpos($error_message, 'Company_ID') !== false) {
                        $error_message = 'Invalid Company ID. Please check your ACS Company ID.';
                    } elseif (strpos($error_message, 'User_ID') !== false) {
                        $error_message = 'Invalid User ID. Please check your ACS User ID.';
                    } elseif (strpos($error_message, 'Password') !== false) {
                        $error_message = 'Invalid password. Please check your ACS Company Password or User Password.';
                    }
                    
                    return [
                        'success' => false,
                        'error' => $error_message,
                        'data' => $response_data
                    ];
                }
                
                return [
                    'success' => true,
                    'error' => null,
                    'data' => $response_data
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response_code}: {$response_body}",
                    'data' => null
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'ACS API request failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Parse tracking events from ACS response
     */
    public function parse_tracking_events($acs_response) {
        // Debug logging
        error_log('parse_tracking_events called with: ' . print_r($acs_response, true));
        
        // Check for both possible response key names (ACS has a typo: "ACSOutputResponce" instead of "ACSOutputResponse")
        $output_response = null;
        if (isset($acs_response['ACSOutputResponse'])) {
            $output_response = $acs_response['ACSOutputResponse'];
            error_log('Using ACSOutputResponse');
        } elseif (isset($acs_response['ACSOutputResponce'])) {
            $output_response = $acs_response['ACSOutputResponce'];
            error_log('Using ACSOutputResponce');
        }
        
        if (!$output_response) {
            error_log('No output response found');
            return [];
        }
        
        if (!isset($output_response['ACSValueOutput']['ACSTableOutput']['Table_Data'])) {
            error_log('Missing Table_Data structure: ' . print_r($output_response, true));
            return [];
        }
        
        $raw_events = $output_response['ACSValueOutput']['ACSTableOutput']['Table_Data'];
        error_log('Raw events count: ' . count($raw_events));
        
        if (!is_array($raw_events) || empty($raw_events)) {
            error_log('Raw events is not array or empty');
            return [];
        }
        
        $events = [];
        foreach ($raw_events as $event) {
            if (!isset($event['checkpoint_date_time'])) {
                error_log('Event missing checkpoint_date_time: ' . print_r($event, true));
                continue;
            }
            
            $events[] = [
                'datetime' => $event['checkpoint_date_time'],
                'action' => $event['checkpoint_action'] ?? '',
                'location' => $event['checkpoint_location'] ?? '',
                'notes' => $event['checkpoint_notes'] ?? '',
                'status' => $this->map_status_to_internal($event['checkpoint_action'] ?? ''),
                'raw_data' => $event
            ];
        }
        
        error_log('Parsed events count: ' . count($events));
        
        // Sort by datetime descending (newest first)
        usort($events, function($a, $b) {
            return strtotime($b['datetime']) - strtotime($a['datetime']);
        });
        
        return $events;
    }
    
    /**
     * Parse stations from ACS response
     */
    public function parse_stations($acs_response) {
        if (!isset($acs_response['ACSOutputResponse']['ACSValueOutput']['ACSTableOutput']['Table_Data'])) {
            return [];
        }
        
        $raw_stations = $acs_response['ACSOutputResponse']['ACSValueOutput']['ACSTableOutput']['Table_Data'];
        
        if (!is_array($raw_stations)) {
            return [];
        }
        
        $stations = [];
        foreach ($raw_stations as $station) {
            $stations[] = [
                'name' => $station['station_name'] ?? '',
                'address' => $station['station_address'] ?? '',
                'phone' => $station['station_phone'] ?? '',
                'hours' => $station['station_hours'] ?? '',
                'raw_data' => $station
            ];
        }
        
        return $stations;
    }
    
    /**
     * Map ACS status to internal status
     */
    private function map_status_to_internal($acs_status) {
        $status_map = [
            'Departure to destination' => 'in_transit',
            'Arrival-departure from HUB' => 'in_transit', 
            'Arrival to' => 'in_transit',
            'On delivery' => 'out_for_delivery',
            'Delivery to consignee' => 'delivered',
            'Delivery attempt failed' => 'failed',
            'Returned' => 'returned',
            'Picked up' => 'picked_up',
            'Scan' => 'picked_up',
        ];
        
        // Try to match partial strings
        foreach ($status_map as $acs_pattern => $internal_status) {
            if (stripos($acs_status, $acs_pattern) !== false) {
                return $internal_status;
            }
        }
        
        // Default to in_transit for unknown statuses
        return 'in_transit';
    }
}

// Initialize the plugin
DMM_Delivery_Bridge::getInstance();
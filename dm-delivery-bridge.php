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
        
        // ACS Voucher Detection
        add_action('wp_ajax_dmm_acs_track_shipment', [$this, 'ajax_acs_track_shipment']);
        add_action('wp_ajax_dmm_acs_create_voucher', [$this, 'ajax_acs_create_voucher']);
        add_action('wp_ajax_dmm_acs_calculate_price', [$this, 'ajax_acs_calculate_price']);
        add_action('wp_ajax_dmm_acs_validate_address', [$this, 'ajax_acs_validate_address']);
        add_action('wp_ajax_dmm_acs_find_stations', [$this, 'ajax_acs_find_stations']);
        add_action('wp_ajax_dmm_acs_test_connection', [$this, 'ajax_acs_test_connection']);
        add_action('wp_ajax_dmm_acs_sync_shipment', [$this, 'ajax_acs_sync_shipment']);
        
        // Geniki Taxidromiki AJAX handlers
        add_action('wp_ajax_dmm_geniki_test_connection', [$this, 'ajax_geniki_test_connection']);
        add_action('wp_ajax_dmm_geniki_track_shipment', [$this, 'ajax_geniki_track_shipment']);
        add_action('wp_ajax_dmm_geniki_get_shops', [$this, 'ajax_geniki_get_shops']);
        add_action('wp_ajax_dmm_geniki_create_voucher', [$this, 'ajax_geniki_create_voucher']);
        add_action('wp_ajax_dmm_geniki_get_pdf', [$this, 'ajax_geniki_get_pdf']);
        
        // ELTA Hellenic Post AJAX handlers
        add_action('wp_ajax_dmm_elta_test_connection', [$this, 'ajax_elta_test_connection']);
        add_action('wp_ajax_dmm_elta_track_shipment', [$this, 'ajax_elta_track_shipment']);
        add_action('wp_ajax_dmm_elta_create_tracking', [$this, 'ajax_elta_create_tracking']);
        add_action('wp_ajax_dmm_elta_update_tracking', [$this, 'ajax_elta_update_tracking']);
        add_action('wp_ajax_dmm_elta_delete_tracking', [$this, 'ajax_elta_delete_tracking']);
        
        // Meta field monitoring for ACS vouchers
        add_action('updated_post_meta', [$this, 'on_meta_updated'], 10, 4);
        add_action('added_post_meta', [$this, 'on_meta_added'], 10, 4);
        
        // ACS Status Sync Scheduling
        add_action('wp_ajax_dmm_acs_sync_all_shipments', [$this, 'ajax_acs_sync_all_shipments']);
        add_action('wp_ajax_dmm_acs_sync_shipment_status', [$this, 'ajax_acs_sync_shipment_status']);
        
        // Schedule automatic sync (if not already scheduled)
        if (!wp_next_scheduled('dmm_acs_sync_shipments')) {
            wp_schedule_event(time(), 'dmm_acs_sync_interval', 'dmm_acs_sync_shipments');
        }
        add_action('dmm_acs_sync_shipments', [$this, 'sync_acs_shipments']);
        
        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'add_acs_sync_cron_interval']);
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
        
        // Orders management AJAX handlers (simplified)
        add_action('wp_ajax_dmm_resend_order', [$this, 'ajax_resend_order']);
        add_action('wp_ajax_dmm_sync_order', [$this, 'ajax_sync_order']);
        add_action('wp_ajax_dmm_export_orders', [$this, 'ajax_export_orders']);
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
            // Geniki Taxidromiki settings
            'geniki_enabled' => 'no',
            'geniki_soap_endpoint' => 'https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL',
            'geniki_username' => '',
            'geniki_password' => '',
            'geniki_application_key' => '',
            // ELTA Hellenic Post settings
            'elta_enabled' => 'no',
            'elta_api_endpoint' => 'https://api.aftership.com/tracking/2024-04',
            'elta_api_key' => '',
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
        
        // Geniki Taxidromiki Settings Section
        add_settings_section(
            'dmm_delivery_bridge_geniki_section',
            __('Geniki Taxidromiki Integration', 'dmm-delivery-bridge'),
            [$this, 'geniki_section_callback'],
            'dmm_delivery_bridge_settings'
        );
        
        add_settings_field(
            'geniki_enabled',
            __('Enable Geniki Taxidromiki', 'dmm-delivery-bridge'),
            [$this, 'geniki_enabled_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_geniki_section'
        );
        
        add_settings_field(
            'geniki_soap_endpoint',
            __('Geniki SOAP Endpoint', 'dmm-delivery-bridge'),
            [$this, 'geniki_soap_endpoint_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_geniki_section'
        );
        
        add_settings_field(
            'geniki_username',
            __('Geniki Username', 'dmm-delivery-bridge'),
            [$this, 'geniki_username_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_geniki_section'
        );
        
        add_settings_field(
            'geniki_password',
            __('Geniki Password', 'dmm-delivery-bridge'),
            [$this, 'geniki_password_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_geniki_section'
        );
        
        add_settings_field(
            'geniki_application_key',
            __('Geniki Application Key', 'dmm-delivery-bridge'),
            [$this, 'geniki_application_key_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_geniki_section'
        );
        
        // ELTA Hellenic Post Settings Section
        add_settings_section(
            'dmm_delivery_bridge_elta_section',
            __('ELTA Hellenic Post Integration', 'dmm-delivery-bridge'),
            [$this, 'elta_section_callback'],
            'dmm_delivery_bridge_settings'
        );
        
        add_settings_field(
            'elta_enabled',
            __('Enable ELTA Hellenic Post', 'dmm-delivery-bridge'),
            [$this, 'elta_enabled_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_elta_section'
        );
        
        add_settings_field(
            'elta_api_endpoint',
            __('ELTA API Endpoint', 'dmm-delivery-bridge'),
            [$this, 'elta_api_endpoint_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_elta_section'
        );
        
        add_settings_field(
            'elta_api_key',
            __('ELTA API Key', 'dmm-delivery-bridge'),
            [$this, 'elta_api_key_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_elta_section'
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
            
            <!-- Tab Navigation -->
            <div class="dmm-tab-navigation">
                <nav class="nav-tab-wrapper">
                    <a href="#dmm-tab-settings" class="nav-tab nav-tab-active" data-tab="settings">
                        <?php _e('⚙️ API Configuration', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-acs" class="nav-tab" data-tab="acs">
                        <?php _e('🚚 ACS Courier', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-geniki" class="nav-tab" data-tab="geniki">
                        <?php _e('📮 Geniki Taxidromiki', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-elta" class="nav-tab" data-tab="elta">
                        <?php _e('📬 ELTA Hellenic Post', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-debug" class="nav-tab" data-tab="debug">
                        <?php _e('🔧 Debug & Testing', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-bulk" class="nav-tab" data-tab="bulk">
                        <?php _e('📦 Bulk Processing', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-logs" class="nav-tab" data-tab="logs">
                        <?php _e('📋 Logs & History', 'dmm-delivery-bridge'); ?>
                    </a>
                    <a href="#dmm-tab-orders" class="nav-tab" data-tab="orders">
                        <?php _e('📊 Orders Status', 'dmm-delivery-bridge'); ?>
                    </a>
                </nav>
            </div>
            
            <!-- Tab Content -->
            <div class="dmm-tab-content">
                <!-- API Configuration Tab -->
                <div id="dmm-tab-settings" class="dmm-tab-panel active">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('dmm_delivery_bridge_settings');
                        do_settings_sections('dmm_delivery_bridge_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <!-- Debug & Testing Tab -->
                <div id="dmm-tab-debug" class="dmm-tab-panel">
                    <div class="dmm-debug-section" style="padding: 20px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 4px;">
                        <h3><?php _e('Debug & Testing', 'dmm-delivery-bridge'); ?></h3>
                        <p><?php _e('Test connections and debug integration issues.', 'dmm-delivery-bridge'); ?></p>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Connection Tests', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <button type="button" id="dmm-test-connection" class="button button-primary" style="width: 100%;">
                                    <?php _e('🔗 Test DMM API Connection', 'dmm-delivery-bridge'); ?>
                                </button>
                                <button type="button" id="dmm-acs-test-connection" class="button button-secondary" style="width: 100%;">
                                    <?php _e('🚚 Test ACS Courier Connection', 'dmm-delivery-bridge'); ?>
                                </button>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <button type="button" id="dmm-geniki-test-connection" class="button button-secondary" style="width: 100%;">
                                    <?php _e('📮 Test Geniki Taxidromiki Connection', 'dmm-delivery-bridge'); ?>
                                </button>
                                <button type="button" id="dmm-elta-test-connection" class="button button-secondary" style="width: 100%;">
                                    <?php _e('📬 Test ELTA Hellenic Post Connection', 'dmm-delivery-bridge'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Log Management', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <button type="button" id="dmm-check-logs" class="button button-secondary" style="width: 100%;">
                                    <?php _e('📋 Check Recent Logs', 'dmm-delivery-bridge'); ?>
                                </button>
                                <button type="button" id="dmm-create-log-table" class="button button-secondary" style="width: 100%;">
                                    <?php _e('🔧 Create Log Table', 'dmm-delivery-bridge'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Order Management', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <button type="button" id="dmm-diagnose-orders" class="button button-secondary" style="width: 100%;">
                                    <?php _e('🔍 Diagnose Orders', 'dmm-delivery-bridge'); ?>
                                </button>
                                <button type="button" id="dmm-refresh-meta-fields" class="button button-secondary" style="width: 100%;">
                                    <?php _e('🔄 Refresh Meta Fields', 'dmm-delivery-bridge'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="dmm-test-result" style="margin-top: 15px;"></div>
                        <div id="dmm-logs-result" style="margin-top: 15px;"></div>
                        <div id="dmm-diagnose-result" style="margin-top: 15px;"></div>
                    </div>
                </div>
                
                <!-- ACS Integration Tab -->
                <div id="dmm-tab-acs" class="dmm-tab-panel">
                    <div class="dmm-acs-section" style="padding: 20px; border: 1px solid #ddd; background: #fff8dc; border-radius: 4px;">
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
                    
                    <div class="dmm-acs-sync-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 4px;">
                        <h3><?php _e('ACS Status Sync Management', 'dmm-delivery-bridge'); ?></h3>
                        <p><?php _e('Automatically sync ACS shipment statuses with your main application. Configure sync frequency and monitor sync performance.', 'dmm-delivery-bridge'); ?></p>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Sync Controls', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <button type="button" id="dmm-acs-sync-all" class="button button-primary">
                                    <?php _e('🔄 Sync All ACS Shipments', 'dmm-delivery-bridge'); ?>
                                </button>
                                <button type="button" id="dmm-acs-test-sync" class="button button-secondary">
                                    <?php _e('🧪 Test Sync (5 Orders)', 'dmm-delivery-bridge'); ?>
                                </button>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <button type="button" id="dmm-acs-view-sync-log" class="button button-secondary">
                                    <?php _e('📋 View Sync Log', 'dmm-delivery-bridge'); ?>
                                </button>
                                <button type="button" id="dmm-acs-clear-sync-log" class="button button-secondary" style="background: #dc3545; color: white; border-color: #dc3545;">
                                    <?php _e('🗑️ Clear Sync Log', 'dmm-delivery-bridge'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Sync Statistics', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; font-size: 14px;">
                                <div>
                                    <strong><?php _e('Last Sync:', 'dmm-delivery-bridge'); ?></strong><br>
                                    <span id="acs-last-sync-time"><?php _e('Never', 'dmm-delivery-bridge'); ?></span>
                                </div>
                                <div>
                                    <strong><?php _e('Orders with ACS Vouchers:', 'dmm-delivery-bridge'); ?></strong><br>
                                    <span id="acs-orders-count"><?php _e('Loading...', 'dmm-delivery-bridge'); ?></span>
                                </div>
                                <div>
                                    <strong><?php _e('Next Scheduled Sync:', 'dmm-delivery-bridge'); ?></strong><br>
                                    <span id="acs-next-sync-time"><?php _e('Loading...', 'dmm-delivery-bridge'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dmm-acs-sync-result" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div class="dmm-geniki-debug-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f0f8ff; border-radius: 4px;">
                        <h3><?php _e('Geniki Taxidromiki Debug Tools', 'dmm-delivery-bridge'); ?></h3>
                        <p><?php _e('Test and debug Geniki Taxidromiki API integration. Track shipments, create vouchers, and manage shops.', 'dmm-delivery-bridge'); ?></p>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Geniki Taxidromiki Tools', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Track Shipment:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="geniki-voucher-number" placeholder="<?php _e('Enter voucher number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <select id="geniki-language" style="width: 100%; padding: 5px; margin-top: 5px;">
                                        <option value="el"><?php _e('Greek (el)', 'dmm-delivery-bridge'); ?></option>
                                        <option value="en"><?php _e('English (en)', 'dmm-delivery-bridge'); ?></option>
                                    </select>
                                    <button type="button" id="dmm-geniki-track" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('🔍 Track Shipment', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Get Shops:', 'dmm-delivery-bridge'); ?></label>
                                    <button type="button" id="dmm-geniki-get-shops" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('🏢 Get Shops List', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Create Test Voucher:', 'dmm-delivery-bridge'); ?></label>
                                    <button type="button" id="dmm-geniki-create-test" class="button button-primary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('📦 Create Test Voucher', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Get Voucher PDF:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="geniki-pdf-voucher" placeholder="<?php _e('Enter voucher number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <select id="geniki-pdf-format" style="width: 100%; padding: 5px; margin-top: 5px;">
                                        <option value="Flyer"><?php _e('Flyer Format', 'dmm-delivery-bridge'); ?></option>
                                        <option value="Sticker"><?php _e('Sticker Format', 'dmm-delivery-bridge'); ?></option>
                                    </select>
                                    <button type="button" id="dmm-geniki-get-pdf" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('📄 Get PDF', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dmm-geniki-result" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div class="dmm-elta-debug-section" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f0f8ff; border-radius: 4px;">
                        <h3><?php _e('ELTA Hellenic Post Debug Tools', 'dmm-delivery-bridge'); ?></h3>
                        <p><?php _e('Test and debug ELTA Hellenic Post integration via AfterShip API. Track shipments, create tracking, and manage deliveries.', 'dmm-delivery-bridge'); ?></p>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('ELTA Hellenic Post Tools', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Track Shipment:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-tracking-number" placeholder="<?php _e('Enter tracking number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <button type="button" id="dmm-elta-track" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('🔍 Track Shipment', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Create Tracking:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-create-tracking-number" placeholder="<?php _e('Enter tracking number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <input type="text" id="elta-create-title" placeholder="<?php _e('Enter title', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px; margin-top: 5px;">
                                    <button type="button" id="dmm-elta-create" class="button button-primary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('📦 Create Tracking', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Update Tracking:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-update-tracking-id" placeholder="<?php _e('Enter tracking ID', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <input type="text" id="elta-update-title" placeholder="<?php _e('Enter new title', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px; margin-top: 5px;">
                                    <button type="button" id="dmm-elta-update" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('✏️ Update Tracking', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Delete Tracking:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-delete-tracking-id" placeholder="<?php _e('Enter tracking ID', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <button type="button" id="dmm-elta-delete" class="button button-secondary" style="margin-top: 5px; width: 100%; background: #dc3545; color: white; border-color: #dc3545;">
                                        <?php _e('🗑️ Delete Tracking', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dmm-elta-result" style="margin-top: 10px;"></div>
                    </div>
                </div>
                
                <!-- Geniki Taxidromiki Tab -->
                <div id="dmm-tab-geniki" class="dmm-tab-panel">
                    <div class="dmm-geniki-section" style="padding: 20px; border: 1px solid #ddd; background: #f0f8ff; border-radius: 4px;">
                        <h3><?php _e('Geniki Taxidromiki Integration', 'dmm-delivery-bridge'); ?></h3>
                        <p><?php _e('Configure and test Geniki Taxidromiki SOAP API integration for direct courier services.', 'dmm-delivery-bridge'); ?></p>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('Geniki Taxidromiki Tools', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Track Shipment:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="geniki-tracking-number" placeholder="<?php _e('Enter voucher number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <select id="geniki-language" style="width: 100%; padding: 5px; margin-top: 5px;">
                                        <option value="el"><?php _e('Greek (el)', 'dmm-delivery-bridge'); ?></option>
                                        <option value="en"><?php _e('English (en)', 'dmm-delivery-bridge'); ?></option>
                                    </select>
                                    <button type="button" id="dmm-geniki-track" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('🔍 Track Shipment', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Get Shops:', 'dmm-delivery-bridge'); ?></label>
                                    <button type="button" id="dmm-geniki-get-shops" class="button button-secondary" style="width: 100%;">
                                        <?php _e('🏪 Get Shops List', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Create Test Voucher:', 'dmm-delivery-bridge'); ?></label>
                                    <button type="button" id="dmm-geniki-create-test" class="button button-primary" style="width: 100%;">
                                        <?php _e('📦 Create Test Voucher', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Get Voucher PDF:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="geniki-voucher-number" placeholder="<?php _e('Enter voucher number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <select id="geniki-pdf-format" style="width: 100%; padding: 5px; margin-top: 5px;">
                                        <option value="Flyer"><?php _e('Flyer', 'dmm-delivery-bridge'); ?></option>
                                        <option value="Sticker"><?php _e('Sticker', 'dmm-delivery-bridge'); ?></option>
                                    </select>
                                    <button type="button" id="dmm-geniki-get-pdf" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('📄 Get PDF', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dmm-geniki-result" style="margin-top: 10px;"></div>
                    </div>
                </div>
                
                <!-- ELTA Hellenic Post Tab -->
                <div id="dmm-tab-elta" class="dmm-tab-panel">
                    <div class="dmm-elta-section" style="padding: 20px; border: 1px solid #ddd; background: #f0f8ff; border-radius: 4px;">
                        <h3><?php _e('ELTA Hellenic Post Integration', 'dmm-delivery-bridge'); ?></h3>
                        <p><?php _e('Configure and test ELTA Hellenic Post integration via AfterShip API for tracking services.', 'dmm-delivery-bridge'); ?></p>
                        
                        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0; color: #333;"><?php _e('ELTA Hellenic Post Tools', 'dmm-delivery-bridge'); ?></h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Track Shipment:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-tracking-number" placeholder="<?php _e('Enter tracking number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <button type="button" id="dmm-elta-track" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('🔍 Track Shipment', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Create Tracking:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-create-tracking-number" placeholder="<?php _e('Enter tracking number', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <input type="text" id="elta-create-title" placeholder="<?php _e('Enter title', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px; margin-top: 5px;">
                                    <button type="button" id="dmm-elta-create" class="button button-primary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('📦 Create Tracking', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Update Tracking:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-update-tracking-id" placeholder="<?php _e('Enter tracking ID', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <input type="text" id="elta-update-title" placeholder="<?php _e('Enter new title', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px; margin-top: 5px;">
                                    <button type="button" id="dmm-elta-update" class="button button-secondary" style="margin-top: 5px; width: 100%;">
                                        <?php _e('✏️ Update Tracking', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;"><?php _e('Delete Tracking:', 'dmm-delivery-bridge'); ?></label>
                                    <input type="text" id="elta-delete-tracking-id" placeholder="<?php _e('Enter tracking ID', 'dmm-delivery-bridge'); ?>" style="width: 100%; padding: 5px;">
                                    <button type="button" id="dmm-elta-delete" class="button button-secondary" style="margin-top: 5px; width: 100%; background: #dc3545; color: white; border-color: #dc3545;">
                                        <?php _e('🗑️ Delete Tracking', 'dmm-delivery-bridge'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dmm-elta-result" style="margin-top: 10px;"></div>
                    </div>
                </div>
                
                <!-- Bulk Processing Tab -->
                <div id="dmm-tab-bulk" class="dmm-tab-panel">
                    <div class="dmm-bulk-section" style="padding: 20px; border: 1px solid #ddd; background: #f0f8ff; border-radius: 4px;">
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
                </div>
                
                <!-- Logs Tab -->
                <div id="dmm-tab-logs" class="dmm-tab-panel">
                    <?php $this->render_logs_section(); ?>
                </div>
                
                <!-- Orders Tab -->
                <div id="dmm-tab-orders" class="dmm-tab-panel">
                    <?php $this->render_orders_section(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .dmm-tab-navigation {
            margin: 20px 0;
        }
        
        .dmm-tab-navigation .nav-tab-wrapper {
            border-bottom: 1px solid #ccd0d4;
            margin: 0;
        }
        
        .dmm-tab-navigation .nav-tab {
            background: #f1f1f1;
            border: 1px solid #ccd0d4;
            border-bottom: none;
            color: #555;
            text-decoration: none;
            padding: 8px 12px;
            margin-right: 0;
            border-radius: 4px 4px 0 0;
            transition: all 0.2s ease;
        }
        
        .dmm-tab-navigation .nav-tab:hover {
            background: #f9f9f9;
            color: #0073aa;
        }
        
        .dmm-tab-navigation .nav-tab.nav-tab-active {
            background: #fff;
            border-bottom: 1px solid #fff;
            color: #000;
            font-weight: 600;
        }
        
        .dmm-tab-content {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-top: none;
            padding: 0;
            margin-top: -1px;
        }
        
        .dmm-tab-panel {
            display: none;
            padding: 20px;
        }
        
        .dmm-tab-panel.active {
            display: block;
        }
        
        .dmm-tab-panel h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .dmm-tab-panel .notice {
            margin: 15px 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.dmm-tab-navigation .nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).data('tab');
                
                // Remove active class from all tabs and panels
                $('.dmm-tab-navigation .nav-tab').removeClass('nav-tab-active');
                $('.dmm-tab-panel').removeClass('active');
                
                // Add active class to clicked tab and corresponding panel
                $(this).addClass('nav-tab-active');
                $('#dmm-tab-' + targetTab).addClass('active');
            });
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
            
            // ACS Sync functionality
            $('#dmm-acs-sync-all').on('click', function() {
                if (!confirm('<?php _e('This will sync all ACS shipments. This may take several minutes. Continue?', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text('<?php _e('Syncing...', 'dmm-delivery-bridge'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_acs_sync_all_shipments',
                        nonce: '<?php echo wp_create_nonce('dmm_acs_sync_all_shipments'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var results = response.data.results;
                            var html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin-top: 10px;">';
                            html += '<h4 style="margin: 0 0 10px 0; color: #155724;"><?php _e('Sync Completed', 'dmm-delivery-bridge'); ?></h4>';
                            html += '<p><strong><?php _e('Processed:', 'dmm-delivery-bridge'); ?></strong> ' + results.processed + '</p>';
                            html += '<p><strong><?php _e('Updated:', 'dmm-delivery-bridge'); ?></strong> ' + results.updated + '</p>';
                            html += '<p><strong><?php _e('Skipped:', 'dmm-delivery-bridge'); ?></strong> ' + results.skipped + '</p>';
                            html += '<p><strong><?php _e('Errors:', 'dmm-delivery-bridge'); ?></strong> ' + results.errors + '</p>';
                            html += '</div>';
                            $('#dmm-acs-sync-result').html(html);
                        } else {
                            $('#dmm-acs-sync-result').html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; color: #721c24;">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#dmm-acs-sync-result').html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; color: #721c24;"><?php _e('Failed to sync shipments. Please try again.', 'dmm-delivery-bridge'); ?></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🔄 Sync All ACS Shipments', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            // Geniki Taxidromiki functionality
            $('#dmm-geniki-test-connection').on('click', function() {
                var button = $(this);
                var result = $('#dmm-test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing Geniki...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_geniki_test_connection',
                        nonce: '<?php echo wp_create_nonce('dmm_geniki_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Geniki connection test failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Geniki Taxidromiki Connection', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-geniki-track').on('click', function() {
                var button = $(this);
                var result = $('#dmm-geniki-result');
                var voucherNumber = $('#geniki-voucher-number').val();
                var language = $('#geniki-language').val();
                
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
                        action: 'dmm_geniki_track_shipment',
                        voucher_number: voucherNumber,
                        language: language,
                        nonce: '<?php echo wp_create_nonce('dmm_geniki_track_shipment'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
                            if (response.data.checkpoints && response.data.checkpoints.length > 0) {
                                html += '<h4><?php _e('Tracking History:', 'dmm-delivery-bridge'); ?></h4>';
                                html += '<table class="widefat"><thead><tr><th><?php _e('Date', 'dmm-delivery-bridge'); ?></th><th><?php _e('Status', 'dmm-delivery-bridge'); ?></th><th><?php _e('Location', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                response.data.checkpoints.forEach(function(checkpoint) {
                                    html += '<tr><td>' + checkpoint.StatusDate + '</td><td>' + checkpoint.Status + '</td><td>' + (checkpoint.Shop || '') + '</td></tr>';
                                });
                                html += '</tbody></table>';
                            }
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
            
            $('#dmm-geniki-get-shops').on('click', function() {
                var button = $(this);
                var result = $('#dmm-geniki-result');
                
                button.prop('disabled', true).text('<?php _e('🏢 Loading...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_geniki_get_shops',
                        nonce: '<?php echo wp_create_nonce('dmm_geniki_get_shops'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
                            if (response.data.shops && response.data.shops.length > 0) {
                                html += '<h4><?php _e('Available Shops:', 'dmm-delivery-bridge'); ?></h4>';
                                html += '<table class="widefat"><thead><tr><th><?php _e('Code', 'dmm-delivery-bridge'); ?></th><th><?php _e('Name', 'dmm-delivery-bridge'); ?></th><th><?php _e('City', 'dmm-delivery-bridge'); ?></th><th><?php _e('Address', 'dmm-delivery-bridge'); ?></th><th><?php _e('Phone', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                response.data.shops.forEach(function(shop) {
                                    html += '<tr><td>' + (shop.Code || '') + '</td><td>' + (shop.Name || '') + '</td><td>' + (shop.City || '') + '</td><td>' + (shop.Address || '') + '</td><td>' + (shop.Telephone || '') + '</td></tr>';
                                });
                                html += '</tbody></table>';
                            }
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Failed to get shops.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🏢 Get Shops List', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-geniki-create-test').on('click', function() {
                var button = $(this);
                var result = $('#dmm-geniki-result');
                
                button.prop('disabled', true).text('<?php _e('📦 Creating...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                // Create test order data
                var testOrderData = {
                    order_id: 'TEST-' + Date.now(),
                    recipient_name: 'Test Recipient',
                    recipient_address: 'Test Address 123',
                    recipient_city: 'Athens',
                    recipient_country: 'Greece',
                    recipient_email: 'test@example.com',
                    recipient_phone: '2101234567',
                    recipient_zip: '12345',
                    weight: 1.0,
                    pieces: 1,
                    comments: 'Test shipment from DMM Delivery Bridge',
                    services: '',
                    cod_amount: '',
                    insurance_amount: 0,
                    received_date: new Date().toISOString().split('T')[0]
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_geniki_create_voucher',
                        order_id: testOrderData.order_id,
                        recipient_name: testOrderData.recipient_name,
                        recipient_address: testOrderData.recipient_address,
                        recipient_city: testOrderData.recipient_city,
                        recipient_country: testOrderData.recipient_country,
                        recipient_email: testOrderData.recipient_email,
                        recipient_phone: testOrderData.recipient_phone,
                        recipient_zip: testOrderData.recipient_zip,
                        weight: testOrderData.weight,
                        pieces: testOrderData.pieces,
                        comments: testOrderData.comments,
                        services: testOrderData.services,
                        cod_amount: testOrderData.cod_amount,
                        insurance_amount: testOrderData.insurance_amount,
                        received_date: testOrderData.received_date,
                        nonce: '<?php echo wp_create_nonce('dmm_geniki_create_voucher'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
                            html += '<p><strong><?php _e('Voucher Number:', 'dmm-delivery-bridge'); ?></strong> ' + response.data.voucher_number + '</p>';
                            html += '<p><strong><?php _e('Job ID:', 'dmm-delivery-bridge'); ?></strong> ' + response.data.job_id + '</p>';
                            if (response.data.sub_vouchers && response.data.sub_vouchers.length > 0) {
                                html += '<p><strong><?php _e('Sub Vouchers:', 'dmm-delivery-bridge'); ?></strong> ' + response.data.sub_vouchers.length + '</p>';
                            }
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Voucher creation failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('📦 Create Test Voucher', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-geniki-get-pdf').on('click', function() {
                var button = $(this);
                var result = $('#dmm-geniki-result');
                var voucherNumber = $('#geniki-pdf-voucher').val();
                var format = $('#geniki-pdf-format').val();
                
                if (!voucherNumber) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a voucher number.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('📄 Generating...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                // Create a form to download the PDF
                var form = $('<form method="POST" action="' + ajaxurl + '" target="_blank">');
                form.append('<input type="hidden" name="action" value="dmm_geniki_get_pdf">');
                form.append('<input type="hidden" name="voucher_number" value="' + voucherNumber + '">');
                form.append('<input type="hidden" name="format" value="' + format + '">');
                form.append('<input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dmm_geniki_get_pdf'); ?>">');
                $('body').append(form);
                form.submit();
                form.remove();
                
                result.html('<div class="notice notice-info inline"><p><?php _e('PDF download started. Check your downloads folder.', 'dmm-delivery-bridge'); ?></p></div>');
                
                button.prop('disabled', false).text('<?php _e('📄 Get PDF', 'dmm-delivery-bridge'); ?>');
            });
            
            // ELTA Hellenic Post functionality
            $('#dmm-elta-test-connection').on('click', function() {
                var button = $(this);
                var result = $('#dmm-test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing ELTA...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_elta_test_connection',
                        nonce: '<?php echo wp_create_nonce('dmm_elta_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('ELTA connection test failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test ELTA Hellenic Post Connection', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-elta-track').on('click', function() {
                var button = $(this);
                var result = $('#dmm-elta-result');
                var trackingNumber = $('#elta-tracking-number').val();
                
                if (!trackingNumber) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a tracking number.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('🔍 Tracking...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_elta_track_shipment',
                        tracking_number: trackingNumber,
                        nonce: '<?php echo wp_create_nonce('dmm_elta_track_shipment'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
                            if (response.data.tracking) {
                                html += '<h4><?php _e('Tracking Information:', 'dmm-delivery-bridge'); ?></h4>';
                                html += '<table class="widefat"><thead><tr><th><?php _e('Field', 'dmm-delivery-bridge'); ?></th><th><?php _e('Value', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                html += '<tr><td><strong><?php _e('Tracking Number:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.tracking_number || '') + '</td></tr>';
                                html += '<tr><td><strong><?php _e('Status:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.tag || '') + '</td></tr>';
                                html += '<tr><td><strong><?php _e('Title:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.title || '') + '</td></tr>';
                                html += '<tr><td><strong><?php _e('Customer Name:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.customer_name || '') + '</td></tr>';
                                html += '<tr><td><strong><?php _e('Order ID:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.order_id || '') + '</td></tr>';
                                html += '<tr><td><strong><?php _e('Created At:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.created_at || '') + '</td></tr>';
                                html += '<tr><td><strong><?php _e('Last Updated:', 'dmm-delivery-bridge'); ?></strong></td><td>' + (response.data.tracking.last_updated_at || '') + '</td></tr>';
                                html += '</tbody></table>';
                                
                                if (response.data.tracking.checkpoints && response.data.tracking.checkpoints.length > 0) {
                                    html += '<h4><?php _e('Tracking History:', 'dmm-delivery-bridge'); ?></h4>';
                                    html += '<table class="widefat"><thead><tr><th><?php _e('Date', 'dmm-delivery-bridge'); ?></th><th><?php _e('Status', 'dmm-delivery-bridge'); ?></th><th><?php _e('Message', 'dmm-delivery-bridge'); ?></th><th><?php _e('Location', 'dmm-delivery-bridge'); ?></th></tr></thead><tbody>';
                                    response.data.tracking.checkpoints.forEach(function(checkpoint) {
                                        html += '<tr><td>' + (checkpoint.checkpoint_time || '') + '</td><td>' + (checkpoint.tag || '') + '</td><td>' + (checkpoint.message || '') + '</td><td>' + (checkpoint.location || '') + '</td></tr>';
                                    });
                                    html += '</tbody></table>';
                                }
                            }
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
            
            $('#dmm-elta-create').on('click', function() {
                var button = $(this);
                var result = $('#dmm-elta-result');
                var trackingNumber = $('#elta-create-tracking-number').val();
                var title = $('#elta-create-title').val();
                
                if (!trackingNumber) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a tracking number.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('📦 Creating...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_elta_create_tracking',
                        tracking_number: trackingNumber,
                        title: title || trackingNumber,
                        order_id: 'TEST-' + Date.now(),
                        customer_name: 'Test Customer',
                        language: 'en',
                        nonce: '<?php echo wp_create_nonce('dmm_elta_create_tracking'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
                            html += '<p><strong><?php _e('Tracking ID:', 'dmm-delivery-bridge'); ?></strong> ' + response.data.tracking_id + '</p>';
                            html += '<p><strong><?php _e('Tracking Number:', 'dmm-delivery-bridge'); ?></strong> ' + response.data.tracking_number + '</p>';
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Tracking creation failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('📦 Create Tracking', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-elta-update').on('click', function() {
                var button = $(this);
                var result = $('#dmm-elta-result');
                var trackingId = $('#elta-update-tracking-id').val();
                var title = $('#elta-update-title').val();
                
                if (!trackingId) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a tracking ID.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('✏️ Updating...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_elta_update_tracking',
                        tracking_id: trackingId,
                        title: title,
                        customer_name: 'Updated Customer',
                        language: 'en',
                        nonce: '<?php echo wp_create_nonce('dmm_elta_update_tracking'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>';
                            if (response.data.tracking) {
                                html += '<p><strong><?php _e('Updated Tracking:', 'dmm-delivery-bridge'); ?></strong> ' + response.data.tracking.title + '</p>';
                            }
                            result.html(html);
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Tracking update failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('✏️ Update Tracking', 'dmm-delivery-bridge'); ?>');
                    }
                });
            });
            
            $('#dmm-elta-delete').on('click', function() {
                var button = $(this);
                var result = $('#dmm-elta-result');
                var trackingId = $('#elta-delete-tracking-id').val();
                
                if (!trackingId) {
                    result.html('<div class="notice notice-error inline"><p><?php _e('Please enter a tracking ID.', 'dmm-delivery-bridge'); ?></p></div>');
                    return;
                }
                
                if (!confirm('<?php _e('Are you sure you want to delete this tracking? This action cannot be undone.', 'dmm-delivery-bridge'); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('🗑️ Deleting...', 'dmm-delivery-bridge'); ?>');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dmm_elta_delete_tracking',
                        tracking_id: trackingId,
                        nonce: '<?php echo wp_create_nonce('dmm_elta_delete_tracking'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        result.html('<div class="notice notice-error inline"><p><?php _e('Tracking deletion failed.', 'dmm-delivery-bridge'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('🗑️ Delete Tracking', 'dmm-delivery-bridge'); ?>');
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
    
    /**
     * Render orders section
     */
    private function render_orders_section() {
        // Get orders directly (no AJAX needed)
        $orders = $this->get_orders_data();
        ?>
        <div class="dmm-orders-section">
            <h3><?php _e('Orders Status Overview', 'dmm-delivery-bridge'); ?></h3>
            <p><?php _e('View and manage all orders and their DMM Delivery status.', 'dmm-delivery-bridge'); ?></p>
            
            <!-- Simple Refresh Button -->
            <div style="margin: 20px 0;">
                <button type="button" id="dmm-orders-refresh" class="button button-primary" onclick="location.reload();">
                    <?php _e('🔄 Refresh Page', 'dmm-delivery-bridge'); ?>
                </button>
                <button type="button" id="dmm-orders-export" class="button" onclick="exportOrders();">
                    <?php _e('📊 Export CSV', 'dmm-delivery-bridge'); ?>
                </button>
            </div>
            
            <!-- Orders Table -->
            <div id="dmm-orders-table-container">
                <table id="dmm-orders-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Order ID', 'dmm-delivery-bridge'); ?></th>
                            <th><?php _e('Customer', 'dmm-delivery-bridge'); ?></th>
                            <th><?php _e('Order Date', 'dmm-delivery-bridge'); ?></th>
                            <th><?php _e('Total', 'dmm-delivery-bridge'); ?></th>
                            <th><?php _e('DMM Status', 'dmm-delivery-bridge'); ?></th>
                            <th><?php _e('Last Attempt', 'dmm-delivery-bridge'); ?></th>
                            <th><?php _e('Actions', 'dmm-delivery-bridge'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #666; padding: 40px;">
                                    <?php _e('No orders found.', 'dmm-delivery-bridge'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $order['order_id'] . '&action=edit'); ?>" target="_blank">
                                            #<?php echo $order['order_id']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight: bold; margin-bottom: 2px;">
                                            <?php echo esc_html($order['customer_name'] ?: '—'); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo esc_html($order['customer_email'] ?: '—'); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo esc_html($order['customer_phone'] ?: '—'); ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                                    <td>€<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="dmm-status-<?php echo $order['dmm_status']; ?>">
                                            <?php 
                                            switch($order['dmm_status']) {
                                                case 'sent': echo __('Sent', 'dmm-delivery-bridge'); break;
                                                case 'error': echo __('Error', 'dmm-delivery-bridge'); break;
                                                case 'not_sent': echo __('Not Sent', 'dmm-delivery-bridge'); break;
                                                default: echo __('Unknown', 'dmm-delivery-bridge');
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $order['last_attempt'] ? date('Y-m-d H:i', strtotime($order['last_attempt'])) : '—'; ?></td>
                                    <td>
                                        <div class="dmm-orders-actions">
                                            <?php if ($order['dmm_status'] === 'error' || $order['dmm_status'] === 'not_sent'): ?>
                                                <button type="button" class="button button-small" onclick="resendOrder(<?php echo $order['order_id']; ?>)">
                                                    <?php _e('Resend', 'dmm-delivery-bridge'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['dmm_status'] === 'sent'): ?>
                                                <button type="button" class="button button-small" onclick="syncOrder(<?php echo $order['order_id']; ?>)">
                                                    <?php _e('Sync', 'dmm-delivery-bridge'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="button button-small" onclick="window.open('<?php echo admin_url('post.php?post=' . $order['order_id'] . '&action=edit'); ?>', '_blank')">
                                                <?php _e('Details', 'dmm-delivery-bridge'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .dmm-orders-section {
            margin-top: 20px;
        }
        
        #dmm-orders-table {
            margin-top: 20px;
        }
        
        .dmm-status-sent {
            color: #46b450;
            font-weight: bold;
        }
        
        .dmm-status-error {
            color: #dc3232;
            font-weight: bold;
        }
        
        .dmm-status-not-sent {
            color: #ffb900;
            font-weight: bold;
        }
        
        .dmm-orders-actions {
            display: flex;
            gap: 5px;
        }
        
        .dmm-orders-actions .button {
            font-size: 11px;
            padding: 4px 8px;
            height: auto;
            line-height: 1.2;
        }
        </style>
        
        <script>
        function resendOrder(orderId) {
            if (!confirm('<?php _e('Are you sure you want to resend this order?', 'dmm-delivery-bridge'); ?>')) {
                return;
            }
            
            // Simple form submission instead of AJAX
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'dmm_resend_order';
            form.appendChild(actionInput);
            
            const orderInput = document.createElement('input');
            orderInput.type = 'hidden';
            orderInput.name = 'order_id';
            orderInput.value = orderId;
            form.appendChild(orderInput);
            
            const nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'nonce';
            nonceInput.value = '<?php echo wp_create_nonce('dmm_resend_order'); ?>';
            form.appendChild(nonceInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function syncOrder(orderId) {
            // Simple form submission instead of AJAX
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'dmm_sync_order';
            form.appendChild(actionInput);
            
            const orderInput = document.createElement('input');
            orderInput.type = 'hidden';
            orderInput.name = 'order_id';
            orderInput.value = orderId;
            form.appendChild(orderInput);
            
            const nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'nonce';
            nonceInput.value = '<?php echo wp_create_nonce('dmm_sync_order'); ?>';
            form.appendChild(nonceInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function exportOrders() {
            window.open('<?php echo admin_url('admin-ajax.php'); ?>?action=dmm_export_orders', '_blank');
        }
        </script>
        <?php
    }
    
    /**
     * Get orders data directly (no AJAX)
     */
    private function get_orders_data() {
        global $wpdb;
        
        try {
            // Get recent orders (last 50)
            $query = "
                SELECT 
                    p.ID as order_id,
                    p.post_date as order_date
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'shop_order'
                ORDER BY p.ID DESC
                LIMIT 50
            ";
            
            $orders = $wpdb->get_results($query);
            $processed_orders = [];
            
            foreach ($orders as $order) {
                $order_id = $order->order_id;
                
                // Get customer data
                $customer_name = get_post_meta($order_id, '_billing_first_name', true) . ' ' . get_post_meta($order_id, '_billing_last_name', true);
                $customer_email = get_post_meta($order_id, '_billing_email', true);
                $customer_phone = get_post_meta($order_id, '_billing_phone', true);
                $total_amount = get_post_meta($order_id, '_order_total', true);
                
                // Get DMM status
                $dmm_status = get_post_meta($order_id, '_dmm_delivery_sent', true);
                if (empty($dmm_status)) {
                    $dmm_status = 'not_sent';
                }
                
                // Get last attempt
                $last_attempt = get_post_meta($order_id, '_dmm_delivery_last_attempt', true);
                
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'order_date' => $order->order_date,
                    'customer_name' => trim($customer_name),
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                    'total_amount' => $total_amount ?: 0,
                    'dmm_status' => $dmm_status,
                    'last_attempt' => $last_attempt
                ];
            }
            
            return $processed_orders;
            
        } catch (Exception $e) {
            error_log('DMM Orders Data Error: ' . $e->getMessage());
            return [];
        }
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
    
    public function geniki_section_callback() {
        echo '<p>' . __('Configure Geniki Taxidromiki SOAP API integration for direct courier services.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function geniki_enabled_callback() {
        $value = isset($this->options['geniki_enabled']) ? $this->options['geniki_enabled'] : 'no';
        echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[geniki_enabled]" value="yes" ' . checked($value, 'yes', false) . ' /> ' . __('Enable Geniki Taxidromiki integration', 'dmm-delivery-bridge') . '</label>';
        echo '<p class="description">' . __('Allow direct Geniki Taxidromiki API calls from this WordPress site.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function geniki_soap_endpoint_callback() {
        $value = isset($this->options['geniki_soap_endpoint']) ? $this->options['geniki_soap_endpoint'] : 'https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL';
        echo '<input type="url" name="dmm_delivery_bridge_options[geniki_soap_endpoint]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Geniki Taxidromiki SOAP endpoint URL. Use test endpoint for testing: https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function geniki_username_callback() {
        $value = isset($this->options['geniki_username']) ? $this->options['geniki_username'] : '';
        echo '<input type="text" name="dmm_delivery_bridge_options[geniki_username]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your Geniki Taxidromiki username.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function geniki_password_callback() {
        $value = isset($this->options['geniki_password']) ? $this->options['geniki_password'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[geniki_password]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your Geniki Taxidromiki password.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function geniki_application_key_callback() {
        $value = isset($this->options['geniki_application_key']) ? $this->options['geniki_application_key'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[geniki_application_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your Geniki Taxidromiki application key.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function elta_section_callback() {
        echo '<p>' . __('Configure ELTA Hellenic Post integration via AfterShip API for tracking services.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function elta_enabled_callback() {
        $value = isset($this->options['elta_enabled']) ? $this->options['elta_enabled'] : 'no';
        echo '<label><input type="checkbox" name="dmm_delivery_bridge_options[elta_enabled]" value="yes" ' . checked($value, 'yes', false) . ' /> ' . __('Enable ELTA Hellenic Post integration', 'dmm-delivery-bridge') . '</label>';
        echo '<p class="description">' . __('Allow ELTA tracking via AfterShip API from this WordPress site.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function elta_api_endpoint_callback() {
        $value = isset($this->options['elta_api_endpoint']) ? $this->options['elta_api_endpoint'] : 'https://api.aftership.com/tracking/2024-04';
        echo '<input type="url" name="dmm_delivery_bridge_options[elta_api_endpoint]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('AfterShip API endpoint URL. Default: https://api.aftership.com/tracking/2024-04', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function elta_api_key_callback() {
        $value = isset($this->options['elta_api_key']) ? $this->options['elta_api_key'] : '';
        echo '<input type="password" name="dmm_delivery_bridge_options[elta_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your AfterShip API key for ELTA tracking.', 'dmm-delivery-bridge') . '</p>';
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
     * Process order and send to API (with duplicate check)
     */
    public function process_order($order) {
        $order_id = $order->get_id();
        
        // Check if already sent successfully
        if (get_post_meta($order_id, '_dmm_delivery_sent', true) === 'yes') {
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
                error_log('DMM Delivery Bridge - Order ' . $order_id . ' already sent, skipping');
            }
            return;
        }
        
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
     * Process order and send to API (force mode - bypasses duplicate check)
     */
    public function process_order_force($order) {
        $order_id = $order->get_id();
        $order_data = null;
        $response = null;
        
        try {
            // Prepare order data
            $order_data = $this->prepare_order_data($order);
            
            // Debug logging
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
                error_log('DMM Delivery Bridge - Force Processing Order ID: ' . $order_id);
                error_log('DMM Delivery Bridge - Order Data: ' . print_r($order_data, true));
            }
            
            // Send to API
            $response = $this->send_to_api($order_data);
            
            // Debug API response
            if (isset($this->options['debug_mode']) && $this->options['debug_mode'] === 'yes') {
                error_log('DMM Delivery Bridge - Force API Response: ' . print_r($response, true));
            }
            
        } catch (Exception $e) {
            // Handle any exceptions during processing
            error_log('DMM Delivery Bridge - Exception during force order processing: ' . $e->getMessage());
            
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
            $order->add_order_note(__('Order force-sent to DMM Delivery system successfully.', 'dmm-delivery-bridge'));
        } else {
            update_post_meta($order_id, '_dmm_delivery_sent', 'error');
            
            // Add order note with error
            $error_message = $response ? $response['message'] : 'Unknown error occurred';
            $order->add_order_note(
                sprintf(__('Failed to force-send order to DMM Delivery system: %s', 'dmm-delivery-bridge'), $error_message)
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
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'dmm_resend_order')) {
            wp_die(__('Security check failed.', 'dmm-delivery-bridge'));
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error([
                'message' => __('Invalid order ID.', 'dmm-delivery-bridge')
            ]);
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error([
                'message' => __('Order not found.', 'dmm-delivery-bridge')
            ]);
        }
        
        try {
            // Reset the sent status
            delete_post_meta($order_id, '_dmm_delivery_sent');
            delete_post_meta($order_id, '_dmm_delivery_order_id');
            delete_post_meta($order_id, '_dmm_delivery_shipment_id');
            
            // Process the order using force method to bypass duplicate check
            $this->process_order_force($order);
            
            $sent_status = get_post_meta($order_id, '_dmm_delivery_sent', true);
            
            if ($sent_status === 'yes') {
                wp_send_json_success([
                    'message' => __('Order resent successfully!', 'dmm-delivery-bridge')
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to resend order. Check the logs for details.', 'dmm-delivery-bridge')
                ]);
            }
            
        } catch (Exception $e) {
            error_log('DMM Resend Order Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Error: ' . $e->getMessage(), 'dmm-delivery-bridge')
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
            'tracking_number' => '[Custom] Tracking Number (tracking_number)',
            'acs_voucher' => '[Custom] ACS Voucher (acs_voucher)',
            'acs_tracking' => '[Custom] ACS Tracking (acs_tracking)',
            'voucher_number' => '[Custom] Voucher Number (voucher_number)',
            'shipment_id' => '[Custom] Shipment ID (shipment_id)',
            '_dmm_delivery_shipment_id' => '[DMM] DMM Shipment ID (_dmm_delivery_shipment_id)',
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
                    
                    // Process order as new (bypass duplicate check for force resend)
                    $this->process_order_force($order);
                    
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
     * Monitor meta field updates for ACS vouchers
     */
    public function on_meta_updated($meta_id, $post_id, $meta_key, $meta_value) {
        $this->check_for_acs_voucher($post_id, $meta_key, $meta_value);
    }
    
    /**
     * Monitor meta field additions for ACS vouchers
     */
    public function on_meta_added($meta_id, $post_id, $meta_key, $meta_value) {
        $this->check_for_acs_voucher($post_id, $meta_key, $meta_value);
    }
    
    /**
     * Check if meta field contains ACS voucher and create shipment
     */
    private function check_for_acs_voucher($post_id, $meta_key, $meta_value) {
        // Only process WooCommerce orders
        if (get_post_type($post_id) !== 'shop_order') {
            return;
        }
        
        // Check if this is an ACS-related meta field
        $acs_meta_fields = [
            'acs_voucher',
            'acs_tracking', 
            'tracking_number',
            'voucher_number',
            'shipment_id',
            '_dmm_delivery_shipment_id',
            'courier',
            'shipping_courier',
            'delivery_method',
            'courier_service'
        ];
        
        if (!in_array($meta_key, $acs_meta_fields)) {
            return;
        }
        
        // Check if the value looks like an ACS voucher (numeric, 10+ digits)
        if (!$this->is_acs_voucher($meta_value)) {
            return;
        }
        
        // Get the order
        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }
        
        // Check if we already processed this voucher
        $processed_vouchers = get_post_meta($post_id, '_dmm_acs_processed_vouchers', true) ?: [];
        if (in_array($meta_value, $processed_vouchers)) {
            return;
        }
        
        // Mark as processed
        $processed_vouchers[] = $meta_value;
        update_post_meta($post_id, '_dmm_acs_processed_vouchers', $processed_vouchers);
        
        // Create shipment and sync to main application
        $this->create_acs_shipment($order, $meta_value);
    }
    
    /**
     * Check if value looks like an ACS voucher
     */
    private function is_acs_voucher($value) {
        if (empty($value) || !is_string($value)) {
            return false;
        }
        
        // ACS vouchers are typically 10+ digit numbers
        $clean_value = preg_replace('/[^0-9]/', '', $value);
        
        // Must be at least 8 digits and not too long
        if (strlen($clean_value) < 8 || strlen($clean_value) > 15) {
            return false;
        }
        
        // Check if it starts with common ACS patterns
        $acs_patterns = ['9', '7', '8']; // Common ACS voucher prefixes
        return in_array(substr($clean_value, 0, 1), $acs_patterns);
    }
    
    /**
     * Create ACS shipment and sync to main application
     */
    private function create_acs_shipment($order, $voucher_number) {
        try {
            // Get ACS tracking data
            $acs_service = $this->get_acs_service();
            $tracking_response = $acs_service->get_tracking_details($voucher_number);
            
            if (!$tracking_response['success']) {
                error_log("DMM ACS: Failed to get tracking data for voucher {$voucher_number}: " . $tracking_response['error']);
                return;
            }
            
            // Parse tracking events
            $events = $acs_service->parse_tracking_events($tracking_response['data']);
            
            // Prepare data for main application using existing models
            $shipment_data = [
                'external_order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'courier' => 'ACS',
                'tracking_number' => $voucher_number,
                'courier_tracking_id' => $voucher_number,
                'status' => $this->map_acs_status($events),
                'weight' => $this->get_order_weight($order),
                'shipping_address' => $order->get_formatted_shipping_address(),
                'billing_address' => $order->get_formatted_billing_address(),
                'shipping_cost' => $order->get_shipping_total(),
                'courier_response' => $tracking_response['data'], // Store full ACS response
                'tracking_events' => $events,
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'order_total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'voucher_source' => 'acs_auto_detection',
                'acs_voucher_number' => $voucher_number
            ];
            
            // Send to main application to create/update Order and Shipment
            $this->send_acs_shipment_to_main_app($shipment_data);
            
            // Log success
            error_log("DMM ACS: Successfully created shipment for voucher {$voucher_number} on order {$order->get_id()}");
            
        } catch (Exception $e) {
            error_log("DMM ACS: Error creating shipment for voucher {$voucher_number}: " . $e->getMessage());
        }
    }
    
    /**
     * Get order weight for shipment
     */
    private function get_order_weight($order) {
        $weight = 0;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->has_weight()) {
                $weight += $product->get_weight() * $item->get_quantity();
            }
        }
        return $weight ?: 0.5; // Default minimum weight
    }
    
    /**
     * Map ACS status to internal status
     */
    private function map_acs_status($events) {
        if (empty($events)) {
            return 'pending';
        }
        
        $latest_event = $events[0]; // Events are sorted by newest first
        $action = strtolower($latest_event['action'] ?? '');
        
        if (strpos($action, 'παραδόθηκε') !== false || strpos($action, 'delivered') !== false) {
            return 'delivered';
        } elseif (strpos($action, 'παραλαβή') !== false || strpos($action, 'picked up') !== false) {
            return 'shipped';
        } elseif (strpos($action, 'κέντρο') !== false || strpos($action, 'hub') !== false) {
            return 'in_transit';
        } else {
            return 'processing';
        }
    }
    
    /**
     * Send ACS shipment data to main application
     */
    private function send_acs_shipment_to_main_app($shipment_data) {
        $api_url = $this->options['api_url'] ?? '';
        $api_key = $this->options['api_key'] ?? '';
        
        if (empty($api_url) || empty($api_key)) {
            error_log('DMM ACS: API URL or key not configured');
            return;
        }
        
        // Prepare data for existing Order and Shipment models
        $order_data = [
            'external_order_id' => $shipment_data['external_order_id'],
            'order_number' => $shipment_data['order_number'],
            'status' => $this->map_order_status_from_shipment($shipment_data['status']),
            'customer_name' => $shipment_data['customer_name'],
            'customer_email' => $shipment_data['customer_email'],
            'customer_phone' => $shipment_data['customer_phone'],
            'shipping_address' => $shipment_data['shipping_address'],
            'billing_address' => $shipment_data['billing_address'],
            'total_amount' => $shipment_data['order_total'],
            'currency' => $shipment_data['currency'],
            'order_date' => $shipment_data['order_date'],
            'additional_data' => [
                'acs_voucher_number' => $shipment_data['acs_voucher_number'],
                'voucher_source' => $shipment_data['voucher_source'],
                'tracking_events' => $shipment_data['tracking_events']
            ]
        ];
        
        $shipment_data_for_api = [
            'tracking_number' => $shipment_data['tracking_number'],
            'courier_tracking_id' => $shipment_data['courier_tracking_id'],
            'status' => $shipment_data['status'],
            'weight' => $shipment_data['weight'],
            'shipping_address' => $shipment_data['shipping_address'],
            'billing_address' => $shipment_data['billing_address'],
            'shipping_cost' => $shipment_data['shipping_cost'],
            'courier_response' => $shipment_data['courier_response'],
            'tracking_events' => $shipment_data['tracking_events']
        ];
        
        $payload = [
            'order' => $order_data,
            'shipment' => $shipment_data_for_api,
            'courier' => 'ACS',
            'source' => 'wordpress_acs_auto_detection'
        ];
        
        $response = wp_remote_post($api_url . '/api/woocommerce/acs-shipment', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'X-ACS-Source' => 'wordpress-plugin'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('DMM ACS: Failed to send shipment to main app: ' . $response->get_error_message());
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code >= 200 && $response_code < 300) {
                error_log('DMM ACS: Successfully sent shipment to main application');
            } else {
                $response_body = wp_remote_retrieve_body($response);
                error_log('DMM ACS: Main app returned error: ' . $response_code . ' - ' . $response_body);
            }
        }
    }
    
    /**
     * Map shipment status to order status
     */
    private function map_order_status_from_shipment($shipment_status) {
        return match($shipment_status) {
            'delivered' => 'delivered',
            'shipped' => 'shipped',
            'in_transit' => 'shipped',
            'processing' => 'processing',
            default => 'processing'
        };
    }
    
    /**
     * AJAX: Sync ACS shipment to main application
     */
    public function ajax_acs_sync_shipment() {
        check_ajax_referer('dmm_acs_sync_shipment', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $order_id = intval($_POST['order_id']);
        $voucher_number = sanitize_text_field($_POST['voucher_number']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'dmm-delivery-bridge')]);
        }
        
        $this->create_acs_shipment($order, $voucher_number);
        
        wp_send_json_success(['message' => __('ACS shipment synced successfully.', 'dmm-delivery-bridge')]);
    }
    
    /**
     * AJAX: Sync all ACS shipments
     */
    public function ajax_acs_sync_all_shipments() {
        check_ajax_referer('dmm_acs_sync_all_shipments', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $results = $this->sync_acs_shipments();
        
        wp_send_json_success([
            'message' => __('ACS shipments sync completed.', 'dmm-delivery-bridge'),
            'results' => $results
        ]);
    }
    
    /**
     * AJAX: Sync specific shipment status
     */
    public function ajax_acs_sync_shipment_status() {
        check_ajax_referer('dmm_acs_sync_shipment_status', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'dmm-delivery-bridge')]);
        }
        
        $result = $this->sync_single_acs_shipment($order);
        
        wp_send_json_success([
            'message' => __('ACS shipment status synced.', 'dmm-delivery-bridge'),
            'result' => $result
        ]);
    }
    
    /**
     * Sync all ACS shipments (scheduled task)
     */
    public function sync_acs_shipments() {
        $results = [
            'processed' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        // Get all orders with ACS vouchers
        $orders = $this->get_orders_with_acs_vouchers();
        
        foreach ($orders as $order) {
            try {
                $result = $this->sync_single_acs_shipment($order);
                
                if ($result['success']) {
                    $results['processed']++;
                    if ($result['updated']) {
                        $results['updated']++;
                    } else {
                        $results['skipped']++;
                    }
                } else {
                    $results['errors']++;
                }
            } catch (Exception $e) {
                $results['errors']++;
                error_log("ACS Sync Error for Order {$order->get_id()}: " . $e->getMessage());
            }
        }
        
        error_log("ACS Sync Completed: " . json_encode($results));
        return $results;
    }
    
    /**
     * Get orders with ACS vouchers that need syncing
     */
    private function get_orders_with_acs_vouchers() {
        global $wpdb;
        
        // Get orders with ACS vouchers that haven't been synced recently
        $orders = $wpdb->get_results("
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-shipped', 'wc-completed')
            AND pm.meta_key IN ('acs_voucher', 'acs_tracking', 'tracking_number', 'voucher_number')
            AND pm.meta_value REGEXP '^[0-9]{8,15}$'
            AND (
                NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm2 
                    WHERE pm2.post_id = p.ID 
                    AND pm2.meta_key = '_dmm_acs_last_sync'
                    AND pm2.meta_value > DATE_SUB(NOW(), INTERVAL 2 HOUR)
                )
                OR NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm3 
                    WHERE pm3.post_id = p.ID 
                    AND pm3.meta_key = '_dmm_acs_last_sync'
                )
            )
            ORDER BY p.post_date DESC
            LIMIT 50
        ");
        
        $order_objects = [];
        foreach ($orders as $order_data) {
            $order = wc_get_order($order_data->ID);
            if ($order) {
                $order_objects[] = $order;
            }
        }
        
        return $order_objects;
    }
    
    /**
     * Sync single ACS shipment
     */
    private function sync_single_acs_shipment($order) {
        $voucher_number = $this->get_acs_voucher_from_order($order);
        
        if (!$voucher_number) {
            return ['success' => false, 'error' => 'No ACS voucher found'];
        }
        
        // Check if we should sync this order
        if (!$this->should_sync_order($order, $voucher_number)) {
            return ['success' => true, 'updated' => false, 'reason' => 'Skip conditions met'];
        }
        
        // Get ACS tracking data
        $acs_service = $this->get_acs_service();
        $tracking_response = $acs_service->get_tracking_details($voucher_number);
        
        if (!$tracking_response['success']) {
            return ['success' => false, 'error' => $tracking_response['error']];
        }
        
        // Parse tracking events
        $events = $acs_service->parse_tracking_events($tracking_response['data']);
        
        // Send to main application
        $this->send_acs_shipment_to_main_app([
            'external_order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'courier' => 'ACS',
            'tracking_number' => $voucher_number,
            'courier_tracking_id' => $voucher_number,
            'status' => $this->map_acs_status($events),
            'weight' => $this->get_order_weight($order),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'billing_address' => $order->get_formatted_billing_address(),
            'shipping_cost' => $order->get_shipping_total(),
            'courier_response' => $tracking_response['data'],
            'tracking_events' => $events,
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'order_total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'voucher_source' => 'acs_auto_sync',
            'acs_voucher_number' => $voucher_number
        ]);
        
        // Update last sync time
        update_post_meta($order->get_id(), '_dmm_acs_last_sync', current_time('mysql'));
        
        return ['success' => true, 'updated' => true, 'events_count' => count($events)];
    }
    
    /**
     * Get ACS voucher from order
     */
    private function get_acs_voucher_from_order($order) {
        $acs_meta_fields = [
            'acs_voucher', 'acs_tracking', 'tracking_number', 
            'voucher_number', 'shipment_id', '_dmm_delivery_shipment_id'
        ];
        
        foreach ($acs_meta_fields as $field) {
            $value = get_post_meta($order->get_id(), $field, true);
            if ($this->is_acs_voucher($value)) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Check if order should be synced
     */
    private function should_sync_order($order, $voucher_number) {
        // Don't sync if already delivered and synced recently
        if ($order->get_status() === 'completed') {
            $last_sync = get_post_meta($order->get_id(), '_dmm_acs_last_sync', true);
            if ($last_sync && strtotime($last_sync) > strtotime('-24 hours')) {
                return false;
            }
        }
        
        // Don't sync if order is too old (more than 30 days)
        if (strtotime($order->get_date_created()) < strtotime('-30 days')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Add custom cron interval for ACS sync
     */
    public function add_acs_sync_cron_interval($schedules) {
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
     * ACS Courier Service Class
     */
    private function get_acs_service() {
        return new DMM_ACS_Courier_Service($this->options);
    }
    
    /**
     * Geniki Taxidromiki Service Class
     */
    private function get_geniki_service() {
        return new DMM_Geniki_Courier_Service($this->options);
    }
    
    /**
     * ELTA Hellenic Post Service Class
     */
    private function get_elta_service() {
        return new DMM_ELTA_Courier_Service($this->options);
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
    
    /**
     * AJAX: Test Geniki connection
     */
    public function ajax_geniki_test_connection() {
        check_ajax_referer('dmm_geniki_test_connection', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $geniki_service = $this->get_geniki_service();
        $response = $geniki_service->test_connection();
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Geniki Taxidromiki connection test successful!', 'dmm-delivery-bridge')
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Geniki connection test failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Track Geniki shipment
     */
    public function ajax_geniki_track_shipment() {
        check_ajax_referer('dmm_geniki_track_shipment', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $voucher_number = sanitize_text_field($_POST['voucher_number']);
        $language = sanitize_text_field($_POST['language']) ?: 'el';
        
        if (empty($voucher_number)) {
            wp_send_json_error([
                'message' => __('Voucher number is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $geniki_service = $this->get_geniki_service();
        $response = $geniki_service->track_voucher($voucher_number, $language);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Shipment tracking successful.', 'dmm-delivery-bridge'),
                'checkpoints' => $response['checkpoints'],
                'status' => $response['status'],
                'delivery_date' => $response['delivery_date'],
                'consignee' => $response['consignee']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Shipment tracking failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Get Geniki shops
     */
    public function ajax_geniki_get_shops() {
        check_ajax_referer('dmm_geniki_get_shops', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $geniki_service = $this->get_geniki_service();
        $response = $geniki_service->get_shops();
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Shops retrieved successfully.', 'dmm-delivery-bridge'),
                'shops' => $response['shops']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Failed to get shops: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Create Geniki voucher
     */
    public function ajax_geniki_create_voucher() {
        check_ajax_referer('dmm_geniki_create_voucher', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $order_data = [
            'order_id' => sanitize_text_field($_POST['order_id']),
            'recipient_name' => sanitize_text_field($_POST['recipient_name']),
            'recipient_address' => sanitize_text_field($_POST['recipient_address']),
            'recipient_city' => sanitize_text_field($_POST['recipient_city']),
            'recipient_country' => sanitize_text_field($_POST['recipient_country']),
            'recipient_email' => sanitize_email($_POST['recipient_email']),
            'recipient_phone' => sanitize_text_field($_POST['recipient_phone']),
            'recipient_zip' => sanitize_text_field($_POST['recipient_zip']),
            'weight' => floatval($_POST['weight']),
            'pieces' => intval($_POST['pieces']),
            'comments' => sanitize_textarea_field($_POST['comments']),
            'services' => sanitize_text_field($_POST['services']),
            'cod_amount' => sanitize_text_field($_POST['cod_amount']),
            'insurance_amount' => floatval($_POST['insurance_amount']),
            'received_date' => sanitize_text_field($_POST['received_date'])
        ];
        
        $geniki_service = $this->get_geniki_service();
        $response = $geniki_service->create_voucher($order_data);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Voucher created successfully.', 'dmm-delivery-bridge'),
                'voucher_number' => $response['voucher_number'],
                'job_id' => $response['job_id'],
                'sub_vouchers' => $response['sub_vouchers']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Voucher creation failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Get Geniki voucher PDF
     */
    public function ajax_geniki_get_pdf() {
        check_ajax_referer('dmm_geniki_get_pdf', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $voucher_number = sanitize_text_field($_POST['voucher_number']);
        $format = sanitize_text_field($_POST['format']) ?: 'Flyer';
        
        if (empty($voucher_number)) {
            wp_die(__('Voucher number is required.', 'dmm-delivery-bridge'));
        }
        
        $geniki_service = $this->get_geniki_service();
        $response = $geniki_service->get_voucher_pdf($voucher_number, $format);
        
        if ($response['success']) {
            // Set headers for PDF download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="geniki_voucher_' . $voucher_number . '.pdf"');
            header('Content-Length: ' . strlen($response['pdf_content']));
            
            echo $response['pdf_content'];
            exit;
        } else {
            wp_die($response['error']);
        }
    }
    
    /**
     * AJAX: Test ELTA connection
     */
    public function ajax_elta_test_connection() {
        check_ajax_referer('dmm_elta_test_connection', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $elta_service = $this->get_elta_service();
        $response = $elta_service->test_connection();
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('ELTA Hellenic Post connection test successful!', 'dmm-delivery-bridge')
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('ELTA connection test failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Track ELTA shipment
     */
    public function ajax_elta_track_shipment() {
        check_ajax_referer('dmm_elta_track_shipment', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $tracking_number = sanitize_text_field($_POST['tracking_number']);
        
        if (empty($tracking_number)) {
            wp_send_json_error([
                'message' => __('Tracking number is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $elta_service = $this->get_elta_service();
        $response = $elta_service->get_tracking_details($tracking_number);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Shipment tracking successful.', 'dmm-delivery-bridge'),
                'tracking' => $response['tracking'],
                'data' => $response['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Shipment tracking failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Create ELTA tracking
     */
    public function ajax_elta_create_tracking() {
        check_ajax_referer('dmm_elta_create_tracking', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $tracking_data = [
            'tracking_number' => sanitize_text_field($_POST['tracking_number']),
            'title' => sanitize_text_field($_POST['title']),
            'order_id' => sanitize_text_field($_POST['order_id']),
            'order_promised_delivery_date' => sanitize_text_field($_POST['order_promised_delivery_date']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'emails' => isset($_POST['emails']) ? array_map('sanitize_email', $_POST['emails']) : [],
            'smses' => isset($_POST['smses']) ? array_map('sanitize_text_field', $_POST['smses']) : [],
            'language' => sanitize_text_field($_POST['language']) ?: 'en',
            'origin_city' => sanitize_text_field($_POST['origin_city']),
            'origin_state' => sanitize_text_field($_POST['origin_state']),
            'origin_country_iso3' => sanitize_text_field($_POST['origin_country_iso3']),
            'origin_postal_code' => sanitize_text_field($_POST['origin_postal_code']),
            'destination_city' => sanitize_text_field($_POST['destination_city']),
            'destination_state' => sanitize_text_field($_POST['destination_state']),
            'destination_country_iso3' => sanitize_text_field($_POST['destination_country_iso3']),
            'destination_postal_code' => sanitize_text_field($_POST['destination_postal_code']),
            'custom_fields' => isset($_POST['custom_fields']) ? $_POST['custom_fields'] : null
        ];
        
        $elta_service = $this->get_elta_service();
        $response = $elta_service->create_tracking($tracking_data);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Tracking created successfully.', 'dmm-delivery-bridge'),
                'tracking_id' => $response['tracking_id'],
                'tracking_number' => $response['tracking_number'],
                'data' => $response['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Tracking creation failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Update ELTA tracking
     */
    public function ajax_elta_update_tracking() {
        check_ajax_referer('dmm_elta_update_tracking', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $tracking_id = sanitize_text_field($_POST['tracking_id']);
        $update_data = [
            'title' => sanitize_text_field($_POST['title']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'emails' => isset($_POST['emails']) ? array_map('sanitize_email', $_POST['emails']) : [],
            'smses' => isset($_POST['smses']) ? array_map('sanitize_text_field', $_POST['smses']) : [],
            'language' => sanitize_text_field($_POST['language']) ?: 'en'
        ];
        
        $elta_service = $this->get_elta_service();
        $response = $elta_service->update_tracking($tracking_id, $update_data);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Tracking updated successfully.', 'dmm-delivery-bridge'),
                'tracking' => $response['tracking'],
                'data' => $response['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Tracking update failed: %s', 'dmm-delivery-bridge'), $response['error'])
            ]);
        }
    }
    
    /**
     * AJAX: Delete ELTA tracking
     */
    public function ajax_elta_delete_tracking() {
        check_ajax_referer('dmm_elta_delete_tracking', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions.', 'dmm-delivery-bridge'));
        }
        
        $tracking_id = sanitize_text_field($_POST['tracking_id']);
        
        if (empty($tracking_id)) {
            wp_send_json_error([
                'message' => __('Tracking ID is required.', 'dmm-delivery-bridge')
            ]);
        }
        
        $elta_service = $this->get_elta_service();
        $response = $elta_service->delete_tracking($tracking_id);
        
        if ($response['success']) {
            wp_send_json_success([
                'message' => __('Tracking deleted successfully.', 'dmm-delivery-bridge'),
                'data' => $response['data']
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(__('Tracking deletion failed: %s', 'dmm-delivery-bridge'), $response['error'])
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

/**
 * Geniki Taxidromiki Courier Service
 * Handles integration with Geniki Taxidromiki SOAP API
 */
class DMM_Geniki_Courier_Service {
    private $options;
    private $soap_endpoint;
    private $soap_client;
    private $username;
    private $password;
    private $application_key;
    private $auth_key;
    private $auth_expires;
    
    public function __construct($options) {
        $this->options = $options;
        $this->soap_endpoint = isset($options['geniki_soap_endpoint']) ? $options['geniki_soap_endpoint'] : 'https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL';
        $this->username = isset($options['geniki_username']) ? $options['geniki_username'] : '';
        $this->password = isset($options['geniki_password']) ? $options['geniki_password'] : '';
        $this->application_key = isset($options['geniki_application_key']) ? $options['geniki_application_key'] : '';
        $this->auth_key = null;
        $this->auth_expires = null;
    }
    
    /**
     * Initialize SOAP client
     */
    private function init_soap_client() {
        if (!$this->soap_client) {
            try {
                $this->soap_client = new SoapClient($this->soap_endpoint, [
                    'soap_version' => SOAP_1_2,
                    'exceptions' => true,
                    'trace' => true,
                    'connection_timeout' => 30,
                    'cache_wsdl' => WSDL_CACHE_NONE
                ]);
            } catch (SoapFault $e) {
                return [
                    'success' => false,
                    'error' => 'Failed to initialize SOAP client: ' . $e->getMessage(),
                    'data' => null
                ];
            }
        }
        return ['success' => true];
    }
    
    /**
     * Authenticate with Geniki API
     */
    private function authenticate() {
        // Check if we have a valid auth key
        if ($this->auth_key && $this->auth_expires && time() < $this->auth_expires) {
            return ['success' => true, 'auth_key' => $this->auth_key];
        }
        
        $init_result = $this->init_soap_client();
        if (!$init_result['success']) {
            return $init_result;
        }
        
        try {
            $result = $this->soap_client->Authenticate([
                'sUsrName' => $this->username,
                'sUsrPwd' => $this->password,
                'applicationKey' => $this->application_key
            ]);
            
            if ($result->AuthenticateResult->Result == 0) {
                $this->auth_key = $result->AuthenticateResult->Key;
                // Set expiration to 1 hour from now (Geniki keys typically last longer)
                $this->auth_expires = time() + 3600;
                
                return [
                    'success' => true,
                    'auth_key' => $this->auth_key,
                    'data' => $result->AuthenticateResult
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Authentication failed. Result code: ' . $result->AuthenticateResult->Result,
                    'data' => $result->AuthenticateResult
                ];
            }
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'error' => 'SOAP authentication error: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Test Geniki connection
     */
    public function test_connection() {
        if (empty($this->username) || empty($this->password) || empty($this->application_key)) {
            return [
                'success' => false,
                'error' => 'Geniki credentials not configured. Please enter Username, Password, and Application Key.',
                'data' => null
            ];
        }
        
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        return [
            'success' => true,
            'message' => 'Geniki connection successful! Authentication key obtained.',
            'data' => $auth_result['data']
        ];
    }
    
    /**
     * Create a voucher/shipment
     */
    public function create_voucher($order_data) {
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        try {
            // Prepare voucher record
            $voucher = [
                'OrderId' => $order_data['order_id'],
                'Name' => $order_data['recipient_name'],
                'Address' => $order_data['recipient_address'],
                'City' => $order_data['recipient_city'],
                'Country' => isset($order_data['recipient_country']) ? $order_data['recipient_country'] : '',
                'Email' => isset($order_data['recipient_email']) ? $order_data['recipient_email'] : '',
                'Telephone' => isset($order_data['recipient_phone']) ? $order_data['recipient_phone'] : '',
                'Zip' => isset($order_data['recipient_zip']) ? $order_data['recipient_zip'] : '',
                'Weight' => isset($order_data['weight']) ? (float)$order_data['weight'] : 1.0,
                'Pieces' => isset($order_data['pieces']) ? (int)$order_data['pieces'] : 1,
                'Comments' => isset($order_data['comments']) ? $order_data['comments'] : '',
                'Services' => isset($order_data['services']) ? $order_data['services'] : '',
                'CodAmount' => isset($order_data['cod_amount']) ? $order_data['cod_amount'] : '',
                'InsAmount' => isset($order_data['insurance_amount']) ? (float)$order_data['insurance_amount'] : 0,
                'VoucherNo' => '',
                'SubCode' => '',
                'BelongsTo' => '',
                'DeliverTo' => '',
                'ReceivedDate' => isset($order_data['received_date']) ? $order_data['received_date'] : date('Y-m-d')
            ];
            
            $result = $this->soap_client->CreateJob([
                'sAuthKey' => $this->auth_key,
                'oVoucher' => $voucher,
                'eType' => 'Voucher'
            ]);
            
            if ($result->CreateJobResult->Result == 0) {
                return [
                    'success' => true,
                    'voucher_number' => $result->CreateJobResult->Voucher,
                    'job_id' => $result->CreateJobResult->JobId,
                    'sub_vouchers' => isset($result->CreateJobResult->SubVouchers) ? $result->CreateJobResult->SubVouchers : [],
                    'data' => $result->CreateJobResult
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create voucher. Result code: ' . $result->CreateJobResult->Result,
                    'data' => $result->CreateJobResult
                ];
            }
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'error' => 'SOAP error creating voucher: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Track and trace a voucher
     */
    public function track_voucher($voucher_number, $language = 'el') {
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        try {
            $result = $this->soap_client->TrackAndTrace([
                'authKey' => $this->auth_key,
                'voucherNo' => $voucher_number,
                'language' => $language
            ]);
            
            if ($result->TrackAndTraceResult->Result == 0) {
                return [
                    'success' => true,
                    'checkpoints' => isset($result->TrackAndTraceResult->Checkpoints) ? $result->TrackAndTraceResult->Checkpoints : [],
                    'status' => isset($result->TrackAndTraceResult->Status) ? $result->TrackAndTraceResult->Status : '',
                    'delivery_date' => isset($result->TrackAndTraceResult->DeliveryDate) ? $result->TrackAndTraceResult->DeliveryDate : null,
                    'consignee' => isset($result->TrackAndTraceResult->Consignee) ? $result->TrackAndTraceResult->Consignee : '',
                    'data' => $result->TrackAndTraceResult
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to track voucher. Result code: ' . $result->TrackAndTraceResult->Result,
                    'data' => $result->TrackAndTraceResult
                ];
            }
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'error' => 'SOAP error tracking voucher: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Get voucher PDF
     */
    public function get_voucher_pdf($voucher_number, $format = 'Flyer') {
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        try {
            // Use HTTP GET request for PDF
            $url = str_replace('?WSDL', '', $this->soap_endpoint);
            $url = str_replace('.asmx?WSDL', '.asmx/GetVoucherPdf', $url);
            $url .= '?authKey=' . urlencode($this->auth_key);
            $url .= '&voucherNo=' . urlencode($voucher_number);
            $url .= '&format=' . urlencode($format);
            $url .= '&extraInfoFormat=None';
            
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/pdf'
                ]
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => 'Failed to get PDF: ' . $response->get_error_message(),
                    'data' => null
                ];
            }
            
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (strpos($content_type, 'application/pdf') !== false) {
                return [
                    'success' => true,
                    'pdf_content' => wp_remote_retrieve_body($response),
                    'content_type' => $content_type
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Expected PDF but got: ' . $content_type,
                    'data' => wp_remote_retrieve_body($response)
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error getting PDF: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Cancel a job/voucher
     */
    public function cancel_job($job_id, $cancel = true) {
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        try {
            $result = $this->soap_client->CancelJob([
                'sAuthKey' => $this->auth_key,
                'nJobId' => $job_id,
                'bCancel' => $cancel
            ]);
            
            if ($result == 0) {
                return [
                    'success' => true,
                    'message' => $cancel ? 'Job canceled successfully' : 'Job reactivated successfully',
                    'data' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to cancel job. Result code: ' . $result,
                    'data' => $result
                ];
            }
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'error' => 'SOAP error canceling job: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Close pending jobs
     */
    public function close_pending_jobs() {
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        try {
            $result = $this->soap_client->ClosePendingJobs([
                'sAuthKey' => $this->auth_key
            ]);
            
            if ($result == 0) {
                return [
                    'success' => true,
                    'message' => 'Pending jobs closed successfully',
                    'data' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to close pending jobs. Result code: ' . $result,
                    'data' => $result
                ];
            }
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'error' => 'SOAP error closing jobs: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Get shops list
     */
    public function get_shops() {
        $auth_result = $this->authenticate();
        if (!$auth_result['success']) {
            return $auth_result;
        }
        
        try {
            $result = $this->soap_client->GetShopsList([
                'sAuthKey' => $this->auth_key
            ]);
            
            if ($result->GetShopsResult->Result == 0) {
                return [
                    'success' => true,
                    'shops' => isset($result->GetShopsResult->Shops) ? $result->GetShopsResult->Shops : [],
                    'data' => $result->GetShopsResult
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to get shops. Result code: ' . $result->GetShopsResult->Result,
                    'data' => $result->GetShopsResult
                ];
            }
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'error' => 'SOAP error getting shops: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Map Geniki status to internal status
     */
    public function map_status_to_internal($geniki_status) {
        $status_map = [
            'DELIVERED' => 'delivered',
            'IN_TRANSIT' => 'in_transit',
            'IN_RETURN' => 'returned',
            'PENDING' => 'pending',
            'CANCELED' => 'canceled'
        ];
        
        return isset($status_map[$geniki_status]) ? $status_map[$geniki_status] : 'unknown';
    }
}

/**
 * ELTA Hellenic Post Courier Service
 * Handles integration with ELTA via AfterShip API
 */
class DMM_ELTA_Courier_Service {
    private $options;
    private $api_endpoint;
    private $api_key;
    
    public function __construct($options) {
        $this->options = $options;
        $this->api_endpoint = isset($options['elta_api_endpoint']) ? $options['elta_api_endpoint'] : 'https://api.aftership.com/tracking/2024-04';
        $this->api_key = isset($options['elta_api_key']) ? $options['elta_api_key'] : '';
    }
    
    /**
     * Test ELTA connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured. Please enter your AfterShip API Key.',
                'data' => null
            ];
        }
        
        // Test connection by trying to get tracking info with a test tracking number
        $test_tracking_number = '123456789';
        $response = $this->get_tracking_details($test_tracking_number);
        
        // If we get a response (even if tracking not found), the connection works
        if (isset($response['success'])) {
            return [
                'success' => true,
                'message' => 'ELTA connection successful! AfterShip API is responding correctly.',
                'data' => $response
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to connect to AfterShip API. Please check your API key and endpoint.',
            'data' => null
        ];
    }
    
    /**
     * Create a tracking
     */
    public function create_tracking($tracking_data) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $payload = [
            'tracking' => [
                'tracking_number' => $tracking_data['tracking_number'],
                'slug' => 'elta-courier',
                'title' => isset($tracking_data['title']) ? $tracking_data['title'] : $tracking_data['tracking_number'],
                'order_id' => isset($tracking_data['order_id']) ? $tracking_data['order_id'] : '',
                'order_promised_delivery_date' => isset($tracking_data['order_promised_delivery_date']) ? $tracking_data['order_promised_delivery_date'] : '',
                'customer_name' => isset($tracking_data['customer_name']) ? $tracking_data['customer_name'] : '',
                'emails' => isset($tracking_data['emails']) ? $tracking_data['emails'] : [],
                'smses' => isset($tracking_data['smses']) ? $tracking_data['smses'] : [],
                'language' => isset($tracking_data['language']) ? $tracking_data['language'] : 'en',
                'origin_city' => isset($tracking_data['origin_city']) ? $tracking_data['origin_city'] : '',
                'origin_state' => isset($tracking_data['origin_state']) ? $tracking_data['origin_state'] : '',
                'origin_country_iso3' => isset($tracking_data['origin_country_iso3']) ? $tracking_data['origin_country_iso3'] : '',
                'origin_postal_code' => isset($tracking_data['origin_postal_code']) ? $tracking_data['origin_postal_code'] : '',
                'destination_city' => isset($tracking_data['destination_city']) ? $tracking_data['destination_city'] : '',
                'destination_state' => isset($tracking_data['destination_state']) ? $tracking_data['destination_state'] : '',
                'destination_country_iso3' => isset($tracking_data['destination_country_iso3']) ? $tracking_data['destination_country_iso3'] : '',
                'destination_postal_code' => isset($tracking_data['destination_postal_code']) ? $tracking_data['destination_postal_code'] : '',
                'custom_fields' => isset($tracking_data['custom_fields']) ? $tracking_data['custom_fields'] : null
            ]
        ];
        
        $result = $this->make_api_call('POST', '/trackings', $payload);
        
        if ($result['success']) {
            return [
                'success' => true,
                'tracking_id' => $result['data']['tracking']['id'],
                'tracking_number' => $result['data']['tracking']['tracking_number'],
                'data' => $result['data']['tracking']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Get tracking details
     */
    public function get_tracking_details($tracking_number) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $result = $this->make_api_call('GET', '/trackings/elta-courier/' . urlencode($tracking_number));
        
        if ($result['success']) {
            return [
                'success' => true,
                'tracking' => $result['data']['tracking'],
                'data' => $result['data']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Update tracking
     */
    public function update_tracking($tracking_id, $update_data) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $payload = [
            'tracking' => $update_data
        ];
        
        $result = $this->make_api_call('PUT', '/trackings/' . $tracking_id, $payload);
        
        if ($result['success']) {
            return [
                'success' => true,
                'tracking' => $result['data']['tracking'],
                'data' => $result['data']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Delete tracking
     */
    public function delete_tracking($tracking_id) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $result = $this->make_api_call('DELETE', '/trackings/' . $tracking_id);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Tracking deleted successfully',
                'data' => $result['data']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Get last checkpoint
     */
    public function get_last_checkpoint($tracking_id) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $result = $this->make_api_call('GET', '/last_checkpoint/' . $tracking_id);
        
        if ($result['success']) {
            return [
                'success' => true,
                'checkpoint' => $result['data']['checkpoint'],
                'data' => $result['data']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Mark tracking as completed
     */
    public function mark_tracking_completed($tracking_id, $reason) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $payload = [
            'reason' => $reason
        ];
        
        $result = $this->make_api_call('POST', '/trackings/' . $tracking_id . '/mark-as-completed', $payload);
        
        if ($result['success']) {
            return [
                'success' => true,
                'tracking' => $result['data']['tracking'],
                'data' => $result['data']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Retrack expired tracking
     */
    public function retrack_tracking($tracking_id) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error' => 'ELTA API Key not configured.',
                'data' => null
            ];
        }
        
        $result = $this->make_api_call('POST', '/trackings/' . $tracking_id . '/retrack');
        
        if ($result['success']) {
            return [
                'success' => true,
                'tracking' => $result['data']['tracking'],
                'data' => $result['data']
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error'],
                'data' => $result['data']
            ];
        }
    }
    
    /**
     * Make API call to AfterShip
     */
    private function make_api_call($method, $endpoint, $data = null) {
        $url = rtrim($this->api_endpoint, '/') . $endpoint;
        
        $headers = [
            'Content-Type' => 'application/json',
            'aftership-api-key' => $this->api_key
        ];
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        ];
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'API request failed: ' . $response->get_error_message(),
                'data' => null
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return [
                'success' => true,
                'data' => $decoded_body,
                'status_code' => $status_code
            ];
        } else {
            $error_message = 'API request failed with status ' . $status_code;
            if (isset($decoded_body['meta']['message'])) {
                $error_message .= ': ' . $decoded_body['meta']['message'];
            }
            
            return [
                'success' => false,
                'error' => $error_message,
                'data' => $decoded_body,
                'status_code' => $status_code
            ];
        }
    }
    
    /**
     * Map ELTA status to internal status
     */
    public function map_status_to_internal($elta_status) {
        $status_map = [
            'Pending' => 'pending',
            'InfoReceived' => 'info_received',
            'InTransit' => 'in_transit',
            'OutForDelivery' => 'out_for_delivery',
            'Delivered' => 'delivered',
            'AvailableForPickup' => 'available_for_pickup',
            'Exception' => 'exception',
            'Expired' => 'expired'
        ];
        
        return isset($status_map[$elta_status]) ? $status_map[$elta_status] : 'unknown';
    }
    
    
    
    /**
     * AJAX handler to sync a single order
     */
    public function ajax_sync_order() {
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'dmm-delivery-bridge')]);
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => __('Order not found.', 'dmm-delivery-bridge')]);
        }
        
        // Sync order
        $this->sync_order($order);
        
        wp_send_json_success(['message' => __('Order synced successfully.', 'dmm-delivery-bridge')]);
    }
    
    /**
     * AJAX handler to export orders as CSV
     */
    public function ajax_export_orders() {
        global $wpdb;
        
        $search = sanitize_text_field($_GET['search'] ?? '');
        $status_filter = sanitize_text_field($_GET['status_filter'] ?? '');
        $date_filter = sanitize_text_field($_GET['date_filter'] ?? '');
        
        // Build query (similar to ajax_get_orders but without pagination)
        $where_conditions = ["p.post_type = 'shop_order'"];
        $where_params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(p.ID LIKE %s OR pm_customer.meta_value LIKE %s OR pm_email.meta_value LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_params[] = $search_term;
            $where_params[] = $search_term;
            $where_params[] = $search_term;
        }
        
        if (!empty($date_filter)) {
            switch ($date_filter) {
                case 'today':
                    $where_conditions[] = "DATE(p.post_date) = CURDATE()";
                    break;
                case 'week':
                    $where_conditions[] = "p.post_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $where_conditions[] = "p.post_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
            }
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Build status filter
        $status_join = '';
        $status_where = '';
        if (!empty($status_filter)) {
            $status_join = "LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_dmm_delivery_sent'";
            
            switch ($status_filter) {
                case 'sent':
                    $status_where = "AND pm_status.meta_value = 'yes'";
                    break;
                case 'error':
                    $status_where = "AND pm_status.meta_value = 'error'";
                    break;
                case 'not_sent':
                    $status_where = "AND (pm_status.meta_value IS NULL OR pm_status.meta_value NOT IN ('yes', 'error'))";
                    break;
            }
        }
        
        $export_query = "
            SELECT 
                p.ID as order_id,
                p.post_date as order_date,
                pm_customer.meta_value as customer_name,
                pm_email.meta_value as customer_email,
                pm_phone.meta_value as customer_phone,
                pm_total.meta_value as total_amount,
                pm_status.meta_value as dmm_status,
                pm_last_attempt.meta_value as last_attempt
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
            LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} pm_last_attempt ON p.ID = pm_last_attempt.post_id AND pm_last_attempt.meta_key = '_dmm_delivery_last_attempt'
            {$status_join}
            {$where_clause}
            {$status_where}
            ORDER BY p.ID DESC
        ";
        
        if (!empty($where_params)) {
            $orders = $wpdb->get_results($wpdb->prepare($export_query, $where_params));
        } else {
            $orders = $wpdb->get_results($export_query);
        }
        
        // Generate CSV
        $filename = 'dmm-orders-export-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Order ID',
            'Order Date',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Total Amount',
            'DMM Status',
            'Last Attempt'
        ]);
        
        // CSV data
        foreach ($orders as $order) {
            $dmm_status = $order->dmm_status;
            if (empty($dmm_status)) {
                $dmm_status = 'not_sent';
            }
            
            fputcsv($output, [
                $order->order_id,
                $order->order_date,
                $order->customer_name,
                $order->customer_email,
                $order->customer_phone,
                $order->total_amount,
                $dmm_status,
                $order->last_attempt
            ]);
        }
        
        fclose($output);
        exit;
    }
    
}

// Initialize the plugin
DMM_Delivery_Bridge::getInstance();
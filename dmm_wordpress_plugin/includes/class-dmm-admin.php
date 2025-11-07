<?php
/**
 * Admin class for DMM Delivery Bridge
 *
 * @package DMM_Delivery_Bridge
 * 
 * NOTE: This is a placeholder class. The actual admin methods
 * need to be extracted from the original dm-delivery-bridge.php file.
 * This class structure is provided as a starting point.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DMM_Admin
 */
class DMM_Admin {
    
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;
    
    /**
     * Main plugin instance
     *
     * @var DMM_Delivery_Bridge
     */
    private $plugin;
    
    /**
     * Views directory path
     *
     * @var string
     */
    private $views_dir;
    
    /**
     * Constructor
     *
     * @param array                $options Plugin options
     * @param DMM_Delivery_Bridge $plugin Main plugin instance
     */
    public function __construct($options = [], $plugin = null) {
        $this->options = $options ?: get_option('dmm_delivery_bridge_options', []);
        $this->plugin = $plugin;
        $this->views_dir = DMM_DELIVERY_BRIDGE_PLUGIN_DIR . 'admin/views/';
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'dmm-delivery-bridge') === false) {
            return;
        }
        
        // Enqueue WordPress React (from Gutenberg)
        wp_enqueue_script('wp-element');
        wp_enqueue_script('wp-i18n');
        
        // Enqueue our admin JavaScript
        wp_enqueue_script(
            'dmm-admin-js',
            DMM_DELIVERY_BRIDGE_PLUGIN_URL . 'admin/js/admin.js',
            ['wp-element', 'wp-i18n', 'jquery'],
            DMM_DELIVERY_BRIDGE_VERSION,
            true
        );
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'dmm-admin-css',
            DMM_DELIVERY_BRIDGE_PLUGIN_URL . 'admin/css/admin.css',
            [],
            DMM_DELIVERY_BRIDGE_VERSION
        );
        
        // Localize script with AJAX data
        wp_localize_script('dmm-admin-js', 'dmmAdminData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dmm_admin_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'dmm-delivery-bridge'),
                'error' => __('An error occurred', 'dmm-delivery-bridge'),
                'success' => __('Operation completed successfully', 'dmm-delivery-bridge'),
                'confirmCancel' => __('Are you sure you want to cancel this operation?', 'dmm-delivery-bridge'),
            ]
        ]);
        
        // Make nonce available globally for AJAX handlers
        wp_add_inline_script('dmm-admin-js', 'window.dmmAdminNonce = "' . wp_create_nonce('dmm_admin_nonce') . '";', 'before');
    }
    
    /**
     * Load a view file
     *
     * @param string $view_name View file name (without .php extension)
     * @param array  $data Data to pass to the view
     */
    private function load_view($view_name, $data = []) {
        $view_file = $this->views_dir . $view_name . '.php';
        
        if (file_exists($view_file)) {
            // Extract data array to variables
            extract($data);
            
            // Include the view file
            include $view_file;
        } else {
            // Fallback if view doesn't exist
            echo '<div class="notice notice-error"><p>';
            echo sprintf(
                __('View file not found: %s', 'dmm-delivery-bridge'),
                esc_html($view_name . '.php')
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main DMM Delivery Bridge page (top-level menu)
        add_menu_page(
            __('DMM Delivery Bridge', 'dmm-delivery-bridge'),
            __('DMM Delivery', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge',
            [$this, 'admin_page'],
            'dashicons-truck',
            30
        );
        
        // ACS Courier subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('ACS Courier Integration', 'dmm-delivery-bridge'),
            __('ACS Courier', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-acs',
            [$this, 'acs_admin_page']
        );
        
        // Geniki Taxidromiki subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('Geniki Taxidromiki Integration', 'dmm-delivery-bridge'),
            __('Geniki Taxidromiki', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-geniki',
            [$this, 'geniki_admin_page']
        );
        
        // ELTA Hellenic Post subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('ELTA Hellenic Post Integration', 'dmm-delivery-bridge'),
            __('ELTA Hellenic Post', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-elta',
            [$this, 'elta_admin_page']
        );
        
        // Bulk Processing subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('Bulk Processing', 'dmm-delivery-bridge'),
            __('Bulk Processing', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-bulk',
            [$this, 'bulk_admin_page']
        );
        
        // Error Logs subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('Error Logs', 'dmm-delivery-bridge'),
            __('Error Logs', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-logs',
            [$this, 'logs_admin_page']
        );
        
        // Orders Management subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('Orders Management', 'dmm-delivery-bridge'),
            __('Orders Management', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-orders',
            [$this, 'orders_admin_page']
        );
        
        // Monitoring subpage
        add_submenu_page(
            'dmm-delivery-bridge',
            __('Monitoring', 'dmm-delivery-bridge'),
            __('Monitoring', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-monitoring',
            [$this, 'monitoring_admin_page']
        );
        
        // Log Details subpage (hidden from menu)
        add_submenu_page(
            null, // Hidden from menu
            __('Log Details', 'dmm-delivery-bridge'),
            __('Log Details', 'dmm-delivery-bridge'),
            'manage_options',
            'dmm-delivery-bridge-log-details',
            [$this, 'log_details_admin_page']
        );
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Register setting with proper Settings API usage
        register_setting('dmm_delivery_bridge_settings', 'dmm_delivery_bridge_options', [
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default' => self::get_default_options()
        ]);
        
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
        
        add_settings_section(
            'dmm_delivery_bridge_log_section',
            __('Log Management', 'dmm-delivery-bridge'),
            [$this, 'log_section_callback'],
            'dmm_delivery_bridge_settings'
        );
        
        // Log retention settings
        add_settings_field(
            'log_retention_days',
            __('Log Retention (Days)', 'dmm-delivery-bridge'),
            [$this, 'log_retention_days_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_log_section'
        );
        
        add_settings_field(
            'max_log_file_size_mb',
            __('Max Log File Size (MB)', 'dmm-delivery-bridge'),
            [$this, 'max_log_file_size_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_log_section'
        );
        
        // API Configuration fields
        add_settings_field(
            'api_endpoint',
            __('API Endpoint', 'dmm-delivery-bridge'),
            [$this, 'api_endpoint_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'dmm-delivery-bridge'),
            [$this, 'api_key_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        add_settings_field(
            'api_secret',
            __('API Secret', 'dmm-delivery-bridge'),
            [$this, 'api_secret_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        add_settings_field(
            'tenant_id',
            __('Tenant ID', 'dmm-delivery-bridge'),
            [$this, 'tenant_id_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_api_section'
        );
        
        // Behavior Settings fields
        add_settings_field(
            'auto_send',
            __('Auto Send Orders', 'dmm-delivery-bridge'),
            [$this, 'auto_send_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        add_settings_field(
            'order_statuses',
            __('Send on Order Status', 'dmm-delivery-bridge'),
            [$this, 'order_statuses_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        add_settings_field(
            'create_shipment',
            __('Create Shipment', 'dmm-delivery-bridge'),
            [$this, 'create_shipment_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'dmm-delivery-bridge'),
            [$this, 'debug_mode_field_callback'],
            'dmm_delivery_bridge_settings',
            'dmm_delivery_bridge_behavior_section'
        );
    }
    
    // Placeholder admin page methods - these need to be extracted from original file
    
    public function admin_page() {
        $this->load_view('settings-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function acs_admin_page() {
        $this->load_view('acs-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function geniki_admin_page() {
        $this->load_view('geniki-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function elta_admin_page() {
        $this->load_view('elta-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function bulk_admin_page() {
        $this->load_view('bulk-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function logs_admin_page() {
        $this->load_view('logs-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function orders_admin_page() {
        $this->load_view('orders-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function monitoring_admin_page() {
        $this->load_view('monitoring-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    public function log_details_admin_page() {
        $this->load_view('log-details-page', [
            'options' => $this->options,
            'plugin' => $this->plugin
        ]);
    }
    
    // Settings callbacks
    public function api_section_callback() {
        echo '<p>' . __('Configure your DMM Delivery API settings.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function behavior_section_callback() {
        echo '<p>' . __('Configure plugin behavior settings.', 'dmm-delivery-bridge') . '</p>';
    }
    
    public function log_section_callback() {
        echo '<p>' . __('Configure log file management settings. Logs are automatically rotated and cleaned up based on these settings.', 'dmm-delivery-bridge') . '</p>';
    }
    
    /**
     * Log retention days field callback
     */
    public function log_retention_days_field_callback() {
        $value = isset($this->options['log_retention_days']) ? absint($this->options['log_retention_days']) : 7;
        ?>
        <input type="number" 
               name="dmm_delivery_bridge_options[log_retention_days]" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               max="90" 
               class="small-text" />
        <p class="description">
            <?php _e('Number of days to keep log files. Logs older than this will be automatically deleted. (Default: 7 days, Range: 1-90 days)', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Max log file size field callback
     */
    public function max_log_file_size_field_callback() {
        $value = isset($this->options['max_log_file_size_mb']) ? absint($this->options['max_log_file_size_mb']) : 10;
        ?>
        <input type="number" 
               name="dmm_delivery_bridge_options[max_log_file_size_mb]" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               max="100" 
               class="small-text" />
        <p class="description">
            <?php _e('Maximum size of log files in MB. When a log file exceeds this size, it will be automatically rotated. (Default: 10 MB, Range: 1-100 MB)', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * API Endpoint field callback
     */
    public function api_endpoint_field_callback() {
        $value = isset($this->options['api_endpoint']) ? esc_attr($this->options['api_endpoint']) : '';
        ?>
        <input type="url" 
               name="dmm_delivery_bridge_options[api_endpoint]" 
               value="<?php echo $value; ?>" 
               class="regular-text" 
               placeholder="https://oreksi.gr/api/woocommerce/order" />
        <p class="description">
            <?php _e('Your DMM Delivery API endpoint URL. This is where orders will be sent for tracking.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * API Key field callback
     */
    public function api_key_field_callback() {
        $value = isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : '';
        ?>
        <input type="text" 
               name="dmm_delivery_bridge_options[api_key]" 
               value="<?php echo $value; ?>" 
               class="regular-text" 
               autocomplete="off" />
        <p class="description">
            <?php _e('Your API key from the DMM Delivery system. This is required for the bridge to work.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * API Secret field callback
     */
    public function api_secret_field_callback() {
        $value = isset($this->options['api_secret']) ? esc_attr($this->options['api_secret']) : '';
        ?>
        <input type="password" 
               name="dmm_delivery_bridge_options[api_secret]" 
               value="<?php echo $value; ?>" 
               class="regular-text" 
               autocomplete="off" />
        <p class="description">
            <?php _e('Your API secret from the DMM Delivery system. This is required for secure authentication.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Tenant ID field callback
     */
    public function tenant_id_field_callback() {
        $value = isset($this->options['tenant_id']) ? esc_attr($this->options['tenant_id']) : '';
        ?>
        <input type="text" 
               name="dmm_delivery_bridge_options[tenant_id]" 
               value="<?php echo $value; ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Your tenant ID from the DMM Delivery system. This identifies your account.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Auto Send field callback
     */
    public function auto_send_field_callback() {
        $value = isset($this->options['auto_send']) ? $this->options['auto_send'] : 'yes';
        ?>
        <label>
            <input type="checkbox" 
                   name="dmm_delivery_bridge_options[auto_send]" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?> />
            <?php _e('Automatically send orders to DMM Delivery when order status changes', 'dmm-delivery-bridge'); ?>
        </label>
        <p class="description">
            <?php _e('If enabled, orders will be automatically sent when they reach the selected order statuses.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Order Statuses field callback
     */
    public function order_statuses_field_callback() {
        $selected = isset($this->options['order_statuses']) && is_array($this->options['order_statuses']) 
            ? $this->options['order_statuses'] 
            : ['processing', 'completed'];
        
        $statuses = [
            'pending' => __('Pending Payment', 'dmm-delivery-bridge'),
            'processing' => __('Processing', 'dmm-delivery-bridge'),
            'on-hold' => __('On Hold', 'dmm-delivery-bridge'),
            'completed' => __('Completed', 'dmm-delivery-bridge'),
            'cancelled' => __('Cancelled', 'dmm-delivery-bridge'),
            'refunded' => __('Refunded', 'dmm-delivery-bridge'),
            'failed' => __('Failed', 'dmm-delivery-bridge'),
        ];
        ?>
        <fieldset>
            <?php foreach ($statuses as $status => $label): ?>
                <label>
                    <input type="checkbox" 
                           name="dmm_delivery_bridge_options[order_statuses][]" 
                           value="<?php echo esc_attr($status); ?>" 
                           <?php checked(in_array($status, $selected)); ?> />
                    <?php echo esc_html($label); ?>
                </label><br>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php _e('Select which order statuses should trigger automatic sending to DMM Delivery.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Create Shipment field callback
     */
    public function create_shipment_field_callback() {
        $value = isset($this->options['create_shipment']) ? $this->options['create_shipment'] : 'yes';
        ?>
        <label>
            <input type="checkbox" 
                   name="dmm_delivery_bridge_options[create_shipment]" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?> />
            <?php _e('Create shipment in DMM Delivery system', 'dmm-delivery-bridge'); ?>
        </label>
        <p class="description">
            <?php _e('If enabled, a shipment will be created in the DMM Delivery system when sending orders.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Debug Mode field callback
     */
    public function debug_mode_field_callback() {
        $value = isset($this->options['debug_mode']) ? $this->options['debug_mode'] : 'no';
        ?>
        <label>
            <input type="checkbox" 
                   name="dmm_delivery_bridge_options[debug_mode]" 
                   value="yes" 
                   <?php checked($value, 'yes'); ?> />
            <?php _e('Enable debug mode (logs detailed information)', 'dmm-delivery-bridge'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, detailed debug information will be logged. Only enable this for troubleshooting.', 'dmm-delivery-bridge'); ?>
        </p>
        <?php
    }
    
    /**
     * Get default plugin options
     * Static method so it can be called from anywhere (e.g., during activation)
     *
     * @return array Default options
     */
    public static function get_default_options() {
        return [
            'api_endpoint' => '',
            'api_key' => '',
            'api_secret' => '',
            'tenant_id' => '',
            'acs_courier_meta_field' => '_acs_voucher',
            'auto_send' => 'yes',
            'order_statuses' => ['processing', 'completed'],
            'create_shipment' => 'yes',
            'debug_mode' => 'no',
            'performance_mode' => 'balanced',
            'max_retries' => 5,
            // ACS Courier settings
            'acs_enabled' => 'no',
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
            'elta_api_endpoint' => 'https://customers.elta-courier.gr',
            'elta_user_code' => '',
            'elta_user_pass' => '',
            'elta_apost_code' => '',
            // Log management settings
            'log_retention_days' => 7,
            'max_log_file_size_mb' => 10,
            // Rate limiting settings (requests per minute)
            'rate_limit_dmm' => 60,
            'rate_limit_acs' => 30,
            'rate_limit_geniki' => 20,
            'rate_limit_elta' => 20,
            'rate_limit_speedex' => 30,
            'rate_limit_generic' => 30,
            // Caching settings
            'cache_enabled' => 'yes',
            'cache_expiration_api_response' => 300,  // 5 minutes
            'cache_expiration_order_status' => 60,   // 1 minute
            'cache_expiration_courier_data' => 600   // 10 minutes
        ];
    }
    
    /**
     * Sanitize plugin options
     * Properly sanitizes all option fields according to their type
     *
     * @param array $input Input options
     * @return array Sanitized options
     */
    public function sanitize_options($input) {
        if (!is_array($input)) {
            return self::get_default_options();
        }
        
        $sanitized = [];
        $defaults = self::get_default_options();
        
        // Sanitize API settings
        if (isset($input['api_endpoint'])) {
            $sanitized['api_endpoint'] = esc_url_raw(trim($input['api_endpoint']));
        }
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['api_secret'])) {
            $sanitized['api_secret'] = sanitize_text_field($input['api_secret']);
        }
        
        if (isset($input['tenant_id'])) {
            $sanitized['tenant_id'] = sanitize_text_field($input['tenant_id']);
        }
        
        // Sanitize behavior settings (checkboxes - unchecked means 'no')
        $sanitized['auto_send'] = isset($input['auto_send']) && $input['auto_send'] === 'yes' ? 'yes' : 'no';
        $sanitized['create_shipment'] = isset($input['create_shipment']) && $input['create_shipment'] === 'yes' ? 'yes' : 'no';
        $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] === 'yes' ? 'yes' : 'no';
        
        if (isset($input['performance_mode'])) {
            $allowed_modes = ['balanced', 'fast', 'thorough'];
            $sanitized['performance_mode'] = in_array($input['performance_mode'], $allowed_modes) ? $input['performance_mode'] : $defaults['performance_mode'];
        }
        
        if (isset($input['max_retries'])) {
            $sanitized['max_retries'] = max(1, min(10, absint($input['max_retries'])));
        }
        
        // Sanitize order statuses array
        if (isset($input['order_statuses']) && is_array($input['order_statuses'])) {
            $allowed_statuses = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'];
            $sanitized['order_statuses'] = array_intersect($input['order_statuses'], $allowed_statuses);
            // Ensure at least one status is selected
            if (empty($sanitized['order_statuses'])) {
                $sanitized['order_statuses'] = $defaults['order_statuses'];
            }
        }
        
        // Sanitize ACS Courier settings (checkboxes - unchecked means 'no')
        $sanitized['acs_enabled'] = isset($input['acs_enabled']) && $input['acs_enabled'] === 'yes' ? 'yes' : 'no';
        
        if (isset($input['acs_api_endpoint'])) {
            $sanitized['acs_api_endpoint'] = esc_url_raw(trim($input['acs_api_endpoint']));
        }
        
        if (isset($input['acs_api_key'])) {
            $sanitized['acs_api_key'] = sanitize_text_field($input['acs_api_key']);
        }
        
        if (isset($input['acs_company_id'])) {
            $sanitized['acs_company_id'] = sanitize_text_field($input['acs_company_id']);
        }
        
        if (isset($input['acs_company_password'])) {
            $sanitized['acs_company_password'] = sanitize_text_field($input['acs_company_password']);
        }
        
        if (isset($input['acs_user_id'])) {
            $sanitized['acs_user_id'] = sanitize_text_field($input['acs_user_id']);
        }
        
        if (isset($input['acs_user_password'])) {
            $sanitized['acs_user_password'] = sanitize_text_field($input['acs_user_password']);
        }
        
        // Sanitize Geniki Taxidromiki settings (checkboxes - unchecked means 'no')
        $sanitized['geniki_enabled'] = isset($input['geniki_enabled']) && $input['geniki_enabled'] === 'yes' ? 'yes' : 'no';
        
        if (isset($input['geniki_soap_endpoint'])) {
            $sanitized['geniki_soap_endpoint'] = esc_url_raw(trim($input['geniki_soap_endpoint']));
        }
        
        if (isset($input['geniki_username'])) {
            $sanitized['geniki_username'] = sanitize_text_field($input['geniki_username']);
        }
        
        if (isset($input['geniki_password'])) {
            $sanitized['geniki_password'] = sanitize_text_field($input['geniki_password']);
        }
        
        if (isset($input['geniki_application_key'])) {
            $sanitized['geniki_application_key'] = sanitize_text_field($input['geniki_application_key']);
        }
        
        // Sanitize ELTA Hellenic Post settings (checkboxes - unchecked means 'no')
        $sanitized['elta_enabled'] = isset($input['elta_enabled']) && $input['elta_enabled'] === 'yes' ? 'yes' : 'no';
        
        if (isset($input['elta_api_endpoint'])) {
            $sanitized['elta_api_endpoint'] = esc_url_raw(trim($input['elta_api_endpoint']));
        }
        
        if (isset($input['elta_user_code'])) {
            $sanitized['elta_user_code'] = sanitize_text_field($input['elta_user_code']);
        }
        
        if (isset($input['elta_user_pass'])) {
            $sanitized['elta_user_pass'] = sanitize_text_field($input['elta_user_pass']);
        }
        
        if (isset($input['elta_apost_code'])) {
            $sanitized['elta_apost_code'] = sanitize_text_field($input['elta_apost_code']);
        }
        
        // Sanitize log management settings
        if (isset($input['log_retention_days'])) {
            $sanitized['log_retention_days'] = max(1, min(90, absint($input['log_retention_days'])));
        }
        
        if (isset($input['max_log_file_size_mb'])) {
            $sanitized['max_log_file_size_mb'] = max(1, min(100, absint($input['max_log_file_size_mb'])));
        }
        
        // Sanitize meta field name
        if (isset($input['acs_courier_meta_field'])) {
            $sanitized['acs_courier_meta_field'] = sanitize_key($input['acs_courier_meta_field']);
        }
        
        // Sanitize rate limit settings
        $rate_limit_couriers = ['dmm', 'acs', 'geniki', 'elta', 'speedex', 'generic'];
        foreach ($rate_limit_couriers as $courier) {
            $option_key = "rate_limit_{$courier}";
            if (isset($input[$option_key])) {
                $sanitized[$option_key] = max(1, min(1000, absint($input[$option_key])));
            }
        }
        
        // Sanitize cache settings
        if (isset($input['cache_enabled'])) {
            $sanitized['cache_enabled'] = in_array($input['cache_enabled'], ['yes', 'no']) ? $input['cache_enabled'] : $defaults['cache_enabled'];
        }
        
        $cache_types = ['api_response', 'order_status', 'courier_data'];
        foreach ($cache_types as $type) {
            $option_key = "cache_expiration_{$type}";
            if (isset($input[$option_key])) {
                $sanitized[$option_key] = max(0, min(86400, absint($input[$option_key]))); // Max 24 hours
            }
        }
        
        // Merge with existing options to preserve settings not in current form submission
        $existing = get_option('dmm_delivery_bridge_options', self::get_default_options());
        
        // Merge: defaults -> existing -> sanitized input
        return array_merge($defaults, $existing, $sanitized);
    }
    
    // TODO: Add all settings field callbacks from original file
    // This is a placeholder - extract all callback methods from original
}


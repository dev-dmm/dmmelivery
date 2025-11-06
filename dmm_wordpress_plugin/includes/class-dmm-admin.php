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
        
        // TODO: Add all settings fields from original file
        // This is a placeholder - extract all add_settings_field calls from original
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
        
        // Sanitize behavior settings
        if (isset($input['auto_send'])) {
            $sanitized['auto_send'] = in_array($input['auto_send'], ['yes', 'no']) ? $input['auto_send'] : $defaults['auto_send'];
        }
        
        if (isset($input['create_shipment'])) {
            $sanitized['create_shipment'] = in_array($input['create_shipment'], ['yes', 'no']) ? $input['create_shipment'] : $defaults['create_shipment'];
        }
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = in_array($input['debug_mode'], ['yes', 'no']) ? $input['debug_mode'] : $defaults['debug_mode'];
        }
        
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
        
        // Sanitize ACS Courier settings
        if (isset($input['acs_enabled'])) {
            $sanitized['acs_enabled'] = in_array($input['acs_enabled'], ['yes', 'no']) ? $input['acs_enabled'] : $defaults['acs_enabled'];
        }
        
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
        
        // Sanitize Geniki Taxidromiki settings
        if (isset($input['geniki_enabled'])) {
            $sanitized['geniki_enabled'] = in_array($input['geniki_enabled'], ['yes', 'no']) ? $input['geniki_enabled'] : $defaults['geniki_enabled'];
        }
        
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
        
        // Sanitize ELTA Hellenic Post settings
        if (isset($input['elta_enabled'])) {
            $sanitized['elta_enabled'] = in_array($input['elta_enabled'], ['yes', 'no']) ? $input['elta_enabled'] : $defaults['elta_enabled'];
        }
        
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


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
 * HPOS: yes
 */

// File tracking probe (only in debug mode)
if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    $options = get_option('dmm_delivery_bridge_options', []);
    if (isset($options['debug_mode']) && $options['debug_mode'] === 'yes') {
        error_log('DMM LIVE FILE: ' . __FILE__ . ' mtime=' . @filemtime(__FILE__));
    }
}

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DMM_DELIVERY_BRIDGE_VERSION', '1.0.0');
define('DMM_DELIVERY_BRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DMM_DELIVERY_BRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DMM_DELIVERY_BRIDGE_PLUGIN_FILE', __FILE__);

// PSR-4 Autoloader for DMM namespace
spl_autoload_register(function ($class) {
    $prefix = 'DMM\\';
    $base_dir = __DIR__ . '/includes/';
    
    // Check if the class uses the DMM namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Not a DMM class, let other autoloaders handle it
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Load core classes
require_once __DIR__ . '/includes/class-dmm-logger.php';
require_once __DIR__ . '/includes/class-dmm-database.php';
require_once __DIR__ . '/includes/class-dmm-cache-service.php';
require_once __DIR__ . '/includes/class-dmm-rate-limiter.php';
require_once __DIR__ . '/includes/class-dmm-performance-monitor.php';
require_once __DIR__ . '/includes/class-dmm-api-client.php';
require_once __DIR__ . '/includes/class-dmm-order-processor.php';
require_once __DIR__ . '/includes/class-dmm-scheduler.php';
require_once __DIR__ . '/includes/class-dmm-admin.php'; // Required for default options during activation
require_once __DIR__ . '/includes/class-dmm-delivery-bridge.php';

// Load WP-CLI commands (only if WP-CLI is available)
if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/class-dmm-cli.php';
}

// HPOS compatibility declaration
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\Features::class)) {
        \Automattic\WooCommerce\Utilities\Features::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Multi-courier helper functions
if (!function_exists('dmm_voucher_meta_map')) {
    /**
     * Get voucher meta field mapping
     *
     * @return array
     */
    function dmm_voucher_meta_map(): array {
        $map = [
            'acs' => [
                '_acs_voucher',
                'acs_voucher', 
                'acs_tracking',
                '_appsbyb_acs_courier_gr_no_pod',
                '_appsbyb_acs_courier_gr_no_pod_pieces'
            ],
            'speedex' => [
                'obs_speedex_courier',
                'obs_speedex_courier_pieces'
            ],
            'generic' => [
                'voucher_number',
                'tracking_number',
                'shipment_id',
                '_dmm_delivery_shipment_id',
                'courier',
                'shipping_courier',
                'courier_service',
                'courier_company',
                'shipping_provider',
                'delivery_service',
                'shipping_service',
                'transport_method'
            ]
        ];
        return apply_filters('dmm_voucher_meta_keys', $map);
    }
}

if (!function_exists('dmm_courier_priority')) {
    /**
     * Get courier priority order
     *
     * @return array
     */
    function dmm_courier_priority(): array {
        $csv = get_option('dmm_courier_priority', 'acs,geniki,elta,speedex,generic');
        $order = array_values(array_filter(array_map('trim', explode(',', $csv))));
        return $order ?: ['acs', 'geniki', 'elta', 'speedex', 'generic'];
    }
}

// Initialize the plugin
DMM_Delivery_Bridge::getInstance();

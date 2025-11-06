<?php
/**
 * PHPUnit Bootstrap for DMM Delivery Bridge Plugin Tests
 *
 * @package DMM_Delivery_Bridge
 */

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (!defined('DMM_DELIVERY_BRIDGE_VERSION')) {
    define('DMM_DELIVERY_BRIDGE_VERSION', '1.0.0');
}

// Load plugin files
require_once dirname(__FILE__) . '/../includes/class-dmm-api-client.php';
require_once dirname(__FILE__) . '/../includes/class-dmm-order-processor.php';
require_once dirname(__FILE__) . '/../includes/class-dmm-database.php';
require_once dirname(__FILE__) . '/../includes/class-dmm-logger.php';
require_once dirname(__FILE__) . '/../includes/class-dmm-scheduler.php';
require_once dirname(__FILE__) . '/../includes/class-dmm-rate-limiter.php';
require_once dirname(__FILE__) . '/../includes/class-dmm-cache-service.php';
require_once dirname(__FILE__) . '/../includes/Courier/Provider.php';
require_once dirname(__FILE__) . '/../includes/Courier/Registry.php';
require_once dirname(__FILE__) . '/../includes/Courier/GenericProvider.php';
require_once dirname(__FILE__) . '/../includes/Courier/AcsProvider.php';
require_once dirname(__FILE__) . '/../includes/Courier/GenikiProvider.php';
require_once dirname(__FILE__) . '/../includes/Courier/EltaProvider.php';
require_once dirname(__FILE__) . '/../includes/Courier/SpeedexProvider.php';

// Mock WordPress functions if not available
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        return new WP_Error('not_implemented', 'wp_remote_request not mocked');
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return '';
    }
}

if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response) {
        return [];
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url($blog_id = null, $path = '', $scheme = null) {
        return 'https://example.com';
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (empty($code)) {
                return;
            }
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            return '';
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            if (empty($codes)) {
                return '';
            }
            return $codes[0];
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('sprintf')) {
    function sprintf($format, ...$args) {
        return vsprintf($format, $args);
    }
}


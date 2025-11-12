<?php
/**
 * Trait for AJAX request verification
 *
 * @package DMM_Delivery_Bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait DMM_AJAX_Verification
 * Provides standardized nonce verification for all AJAX handlers
 */
trait DMM_AJAX_Verification {
    
    /**
     * Verify AJAX request with nonce and capabilities
     * Standardized nonce verification for all AJAX handlers
     *
     * @param string $action The nonce action name
     * @param string $capability The required capability: 'manage_options', 'manage_woocommerce', or 'both' (default: 'both')
     */
    protected function verify_ajax_request($action, $capability = 'both') {
        // Standardize on check_ajax_referer() for all AJAX handlers
        // Use a generic admin nonce for all admin operations
        check_ajax_referer('dmm_admin_nonce', 'nonce');
        
        // Check capabilities based on parameter
        $has_cap = false;
        
        if ($capability === 'both') {
            // Default: allow either manage_options OR manage_woocommerce
            $has_cap = current_user_can('manage_options') || current_user_can('manage_woocommerce');
        } elseif ($capability === 'manage_woocommerce') {
            $has_cap = current_user_can('manage_woocommerce');
        } else {
            // Default to manage_options
            $has_cap = current_user_can('manage_options');
        }
        
        if (!$has_cap) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dmm-delivery-bridge')], 403);
        }
    }
}


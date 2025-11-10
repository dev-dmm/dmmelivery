<?php
/**
 * Error logs page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap dmm-admin-page">
    <h1><?php _e('Error Logs', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('View and manage error logs from the DMM Delivery Bridge operations.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-logs';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <div class="dmm-logs-container">
        <p><?php _e('Log viewing functionality will be implemented here. This will display error logs from API operations, courier integrations, and system events.', 'dmm-delivery-bridge'); ?></p>
        
        <p class="description">
            <?php _e('Logs are automatically managed based on your log retention settings configured in the main settings page.', 'dmm-delivery-bridge'); ?>
        </p>
    </div>
</div>


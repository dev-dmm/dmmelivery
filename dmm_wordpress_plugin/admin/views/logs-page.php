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

<div class="wrap">
    <h1><?php _e('Error Logs', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('View and manage error logs from the DMM Delivery Bridge operations.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <div class="dmm-navigation" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge'); ?>" class="button button-secondary">
                <?php _e('⚙️ API Configuration', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-logs'); ?>" class="button button-primary">
                <?php _e('📋 Error Logs', 'dmm-delivery-bridge'); ?>
            </a>
        </div>
    </div>
    
    <div class="dmm-logs-container">
        <p><?php _e('Log viewing functionality will be implemented here. This will display error logs from API operations, courier integrations, and system events.', 'dmm-delivery-bridge'); ?></p>
        
        <p class="description">
            <?php _e('Logs are automatically managed based on your log retention settings configured in the main settings page.', 'dmm-delivery-bridge'); ?>
        </p>
    </div>
</div>


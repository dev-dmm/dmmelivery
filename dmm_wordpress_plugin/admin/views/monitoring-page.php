<?php
/**
 * Monitoring page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Monitoring', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Monitor system performance, API health, and integration status.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <div class="dmm-navigation" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge'); ?>" class="button button-secondary">
                <?php _e('⚙️ API Configuration', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-monitoring'); ?>" class="button button-primary">
                <?php _e('📊 Monitoring', 'dmm-delivery-bridge'); ?>
            </a>
        </div>
    </div>
    
    <div class="dmm-monitoring-container">
        <p><?php _e('Monitoring functionality will be implemented here. This will display system health, API status, and performance metrics.', 'dmm-delivery-bridge'); ?></p>
        
        <p class="description">
            <?php _e('Monitor API response times, success rates, error rates, and system resource usage.', 'dmm-delivery-bridge'); ?>
        </p>
    </div>
</div>


<?php
/**
 * Log details page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get log ID from query string
$log_id = isset($_GET['log_id']) ? absint($_GET['log_id']) : 0;
?>

<div class="wrap">
    <h1><?php _e('Log Details', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('View detailed information about a specific log entry.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <div class="dmm-log-details-container">
        <?php if ($log_id > 0): ?>
            <p><?php printf(__('Viewing details for log entry #%d', 'dmm-delivery-bridge'), $log_id); ?></p>
            <p class="description">
                <?php _e('Detailed log information will be displayed here.', 'dmm-delivery-bridge'); ?>
            </p>
        <?php else: ?>
            <div class="notice notice-error">
                <p><?php _e('Invalid log ID. Please select a log entry from the logs page.', 'dmm-delivery-bridge'); ?></p>
            </div>
        <?php endif; ?>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-logs'); ?>" class="button">
                <?php _e('← Back to Logs', 'dmm-delivery-bridge'); ?>
            </a>
        </p>
    </div>
</div>


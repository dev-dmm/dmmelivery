<?php
/**
 * Bulk Processing page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap dmm-admin-page">
    <h1><?php _e('Bulk Processing', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Process multiple orders in bulk. Operations run in the background with real-time progress tracking.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <div class="dmm-navigation">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge'); ?>" class="button button-secondary">
                <?php _e('⚙️ API Configuration', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-acs'); ?>" class="button button-secondary">
                <?php _e('🚚 ACS Courier', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-geniki'); ?>" class="button button-secondary">
                <?php _e('📮 Geniki Taxidromiki', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-elta'); ?>" class="button button-secondary">
                <?php _e('📬 ELTA Hellenic Post', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-bulk'); ?>" class="button button-primary">
                <?php _e('📤 Bulk Processing', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-logs'); ?>" class="button button-secondary">
                <?php _e('📋 Error Logs', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-orders'); ?>" class="button button-secondary">
                <?php _e('📦 Orders Management', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-monitoring'); ?>" class="button button-secondary">
                <?php _e('📊 Monitoring', 'dmm-delivery-bridge'); ?>
            </a>
        </div>
    </div>
    
    <!-- React Container for Bulk Operations -->
    <div id="dmm-bulk-operations-container"></div>
    
    <!-- Instructions Card -->
    <div class="dmm-card">
        <div class="dmm-card-header">
            <h2 class="dmm-card-title"><?php _e('How to Use Bulk Processing', 'dmm-delivery-bridge'); ?></h2>
        </div>
        <div class="dmm-card-content">
            <ul style="list-style: disc; padding-left: 2rem; line-height: 1.8;">
                <li><?php _e('Select an action from the dropdown menu', 'dmm-delivery-bridge'); ?></li>
                <li><?php _e('Click "Start Bulk Operation" to begin processing', 'dmm-delivery-bridge'); ?></li>
                <li><?php _e('Monitor progress in real-time using the progress bar', 'dmm-delivery-bridge'); ?></li>
                <li><?php _e('You can cancel the operation at any time', 'dmm-delivery-bridge'); ?></li>
                <li><?php _e('Operations run in the background, so you can navigate away', 'dmm-delivery-bridge'); ?></li>
            </ul>
        </div>
    </div>
    
    <!-- Available Actions Card -->
    <div class="dmm-card">
        <div class="dmm-card-header">
            <h2 class="dmm-card-title"><?php _e('Available Actions', 'dmm-delivery-bridge'); ?></h2>
        </div>
        <div class="dmm-card-content">
            <table class="dmm-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('Action', 'dmm-delivery-bridge'); ?></th>
                        <th><?php _e('Description', 'dmm-delivery-bridge'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php _e('Send Orders', 'dmm-delivery-bridge'); ?></strong></td>
                        <td><?php _e('Send pending orders to the DMM Delivery API for tracking', 'dmm-delivery-bridge'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Sync Orders', 'dmm-delivery-bridge'); ?></strong></td>
                        <td><?php _e('Synchronize order statuses with the DMM Delivery API', 'dmm-delivery-bridge'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Resend Failed Orders', 'dmm-delivery-bridge'); ?></strong></td>
                        <td><?php _e('Retry sending orders that previously failed', 'dmm-delivery-bridge'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


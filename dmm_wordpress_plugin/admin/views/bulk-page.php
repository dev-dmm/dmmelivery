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
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-bulk';
    DMM_Admin::render_navigation($current_page);
    ?>
    
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


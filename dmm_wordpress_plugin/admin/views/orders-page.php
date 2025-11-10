<?php
/**
 * Orders management page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Orders Management', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Manage and monitor WooCommerce orders sent to DMM Delivery system.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-orders';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <div class="dmm-orders-container">
        <p><?php _e('Orders management functionality will be implemented here. This will display a list of WooCommerce orders and their DMM Delivery status.', 'dmm-delivery-bridge'); ?></p>
        
        <p class="description">
            <?php _e('You can view order details, resend orders to DMM Delivery, and check tracking information.', 'dmm-delivery-bridge'); ?>
        </p>
    </div>
</div>


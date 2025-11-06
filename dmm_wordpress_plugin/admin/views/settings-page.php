<?php
/**
 * Settings page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('DMM Delivery Bridge Settings', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your DMM Delivery API settings to automatically send WooCommerce orders for tracking.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <div class="dmm-navigation" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge'); ?>" class="button button-primary">
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
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-bulk'); ?>" class="button button-secondary">
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
    
    <form method="post" action="options.php">
        <?php
        settings_fields('dmm_delivery_bridge_settings');
        do_settings_sections('dmm_delivery_bridge_settings');
        submit_button();
        ?>
    </form>
    
    <div class="notice notice-warning">
        <p><strong><?php _e('Note:', 'dmm-delivery-bridge'); ?></strong> <?php _e('The admin views have been refactored. The actual HTML content from the original admin pages needs to be extracted and placed in the admin/views/ directory. This is a placeholder template.', 'dmm-delivery-bridge'); ?></p>
    </div>
</div>


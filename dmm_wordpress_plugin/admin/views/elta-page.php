<?php
/**
 * ELTA Hellenic Post integration page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('ELTA Hellenic Post Integration', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your ELTA Hellenic Post API credentials to enable automatic shipment creation.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <div class="dmm-navigation" style="margin-bottom: 20px;">
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
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-elta'); ?>" class="button button-primary">
                <?php _e('📬 ELTA Hellenic Post', 'dmm-delivery-bridge'); ?>
            </a>
        </div>
    </div>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('dmm_delivery_bridge_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="elta_enabled"><?php _e('Enable ELTA', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="elta_enabled"
                               name="dmm_delivery_bridge_options[elta_enabled]" 
                               value="yes" 
                               <?php checked(isset($options['elta_enabled']) ? $options['elta_enabled'] : 'no', 'yes'); ?> />
                        <?php _e('Enable ELTA Hellenic Post integration', 'dmm-delivery-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_api_endpoint"><?php _e('API Endpoint', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="elta_api_endpoint"
                           name="dmm_delivery_bridge_options[elta_api_endpoint]" 
                           value="<?php echo esc_attr(isset($options['elta_api_endpoint']) ? $options['elta_api_endpoint'] : 'https://customers.elta-courier.gr'); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('ELTA Hellenic Post API endpoint URL', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_user_code"><?php _e('User Code', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="elta_user_code"
                           name="dmm_delivery_bridge_options[elta_user_code]" 
                           value="<?php echo esc_attr(isset($options['elta_user_code']) ? $options['elta_user_code'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ELTA user code', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_user_pass"><?php _e('User Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="elta_user_pass"
                           name="dmm_delivery_bridge_options[elta_user_pass]" 
                           value="<?php echo esc_attr(isset($options['elta_user_pass']) ? $options['elta_user_pass'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ELTA user password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="elta_apost_code"><?php _e('Apost Code', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="elta_apost_code"
                           name="dmm_delivery_bridge_options[elta_apost_code]" 
                           value="<?php echo esc_attr(isset($options['elta_apost_code']) ? $options['elta_apost_code'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ELTA apost code', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Changes', 'dmm-delivery-bridge')); ?>
    </form>
</div>


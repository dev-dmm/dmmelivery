<?php
/**
 * ACS Courier integration page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('ACS Courier Integration', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your ACS Courier API credentials to enable automatic shipment creation.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <div class="dmm-navigation" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge'); ?>" class="button button-secondary">
                <?php _e('⚙️ API Configuration', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-acs'); ?>" class="button button-primary">
                <?php _e('🚚 ACS Courier', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-geniki'); ?>" class="button button-secondary">
                <?php _e('📮 Geniki Taxidromiki', 'dmm-delivery-bridge'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-elta'); ?>" class="button button-secondary">
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
                    <label for="acs_enabled"><?php _e('Enable ACS Courier', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="acs_enabled"
                               name="dmm_delivery_bridge_options[acs_enabled]" 
                               value="yes" 
                               <?php checked(isset($options['acs_enabled']) ? $options['acs_enabled'] : 'no', 'yes'); ?> />
                        <?php _e('Enable ACS Courier integration', 'dmm-delivery-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_api_endpoint"><?php _e('API Endpoint', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="acs_api_endpoint"
                           name="dmm_delivery_bridge_options[acs_api_endpoint]" 
                           value="<?php echo esc_attr(isset($options['acs_api_endpoint']) ? $options['acs_api_endpoint'] : 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest'); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('ACS Courier API endpoint URL', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_api_key"><?php _e('API Key', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="acs_api_key"
                           name="dmm_delivery_bridge_options[acs_api_key]" 
                           value="<?php echo esc_attr(isset($options['acs_api_key']) ? $options['acs_api_key'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ACS Courier API key', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_company_id"><?php _e('Company ID', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="acs_company_id"
                           name="dmm_delivery_bridge_options[acs_company_id]" 
                           value="<?php echo esc_attr(isset($options['acs_company_id']) ? $options['acs_company_id'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ACS Courier company ID', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_company_password"><?php _e('Company Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="acs_company_password"
                           name="dmm_delivery_bridge_options[acs_company_password]" 
                           value="<?php echo esc_attr(isset($options['acs_company_password']) ? $options['acs_company_password'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ACS Courier company password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_user_id"><?php _e('User ID', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="acs_user_id"
                           name="dmm_delivery_bridge_options[acs_user_id]" 
                           value="<?php echo esc_attr(isset($options['acs_user_id']) ? $options['acs_user_id'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your ACS Courier user ID', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="acs_user_password"><?php _e('User Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="acs_user_password"
                           name="dmm_delivery_bridge_options[acs_user_password]" 
                           value="<?php echo esc_attr(isset($options['acs_user_password']) ? $options['acs_user_password'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your ACS Courier user password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Changes', 'dmm-delivery-bridge')); ?>
    </form>
</div>


<?php
/**
 * Geniki Taxidromiki integration page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Geniki Taxidromiki Integration', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('Configure your Geniki Taxidromiki API credentials to enable automatic shipment creation.', 'dmm-delivery-bridge'); ?></p>
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
            <a href="<?php echo admin_url('admin.php?page=dmm-delivery-bridge-geniki'); ?>" class="button button-primary">
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
                    <label for="geniki_enabled"><?php _e('Enable Geniki', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="geniki_enabled"
                               name="dmm_delivery_bridge_options[geniki_enabled]" 
                               value="yes" 
                               <?php checked(isset($options['geniki_enabled']) ? $options['geniki_enabled'] : 'no', 'yes'); ?> />
                        <?php _e('Enable Geniki Taxidromiki integration', 'dmm-delivery-bridge'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_soap_endpoint"><?php _e('SOAP Endpoint', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="url" 
                           id="geniki_soap_endpoint"
                           name="dmm_delivery_bridge_options[geniki_soap_endpoint]" 
                           value="<?php echo esc_attr(isset($options['geniki_soap_endpoint']) ? $options['geniki_soap_endpoint'] : 'https://testvoucher.taxydromiki.gr/JobServicesV2.asmx?WSDL'); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Geniki Taxidromiki SOAP endpoint URL', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_username"><?php _e('Username', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="geniki_username"
                           name="dmm_delivery_bridge_options[geniki_username]" 
                           value="<?php echo esc_attr(isset($options['geniki_username']) ? $options['geniki_username'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your Geniki Taxidromiki username', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_password"><?php _e('Password', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="geniki_password"
                           name="dmm_delivery_bridge_options[geniki_password]" 
                           value="<?php echo esc_attr(isset($options['geniki_password']) ? $options['geniki_password'] : ''); ?>" 
                           class="regular-text" 
                           autocomplete="off" />
                    <p class="description"><?php _e('Your Geniki Taxidromiki password', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="geniki_application_key"><?php _e('Application Key', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="geniki_application_key"
                           name="dmm_delivery_bridge_options[geniki_application_key]" 
                           value="<?php echo esc_attr(isset($options['geniki_application_key']) ? $options['geniki_application_key'] : ''); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Your Geniki Taxidromiki application key', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Changes', 'dmm-delivery-bridge')); ?>
    </form>
</div>


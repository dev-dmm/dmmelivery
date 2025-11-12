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
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-elta';
    DMM_Admin::render_navigation($current_page);
    ?>
    
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
            
            <tr>
                <th scope="row">
                    <label for="elta_voucher_meta_field"><?php _e('Voucher Meta Field', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <select 
                           id="elta_voucher_meta_field"
                           name="dmm_delivery_bridge_options[elta_voucher_meta_field]" 
                           class="regular-text">
                        <option value=""><?php _e('-- Select Meta Field --', 'dmm-delivery-bridge'); ?></option>
                    </select>
                    <p class="description"><?php _e('Select which order meta field contains the voucher number', 'dmm-delivery-bridge'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Save Changes', 'dmm-delivery-bridge')); ?>
    </form>
    
    <!-- Test Voucher Section -->
    <div class="card" style="margin-top: 20px;">
        <h2><?php _e('Test Voucher Creation', 'dmm-delivery-bridge'); ?></h2>
        <p class="description">
            <?php _e('Test the ELTA API connection by creating a test voucher. This will verify your credentials and API endpoint configuration.', 'dmm-delivery-bridge'); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="test_sender_name"><?php _e('Sender Name', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_sender_name"
                           value="Test Sender" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_sender_address"><?php _e('Sender Address', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_sender_address"
                           value="Test Address 123" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_sender_city"><?php _e('Sender City', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_sender_city"
                           value="Athens" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_sender_postcode"><?php _e('Sender Postcode', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_sender_postcode"
                           value="10431" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_recipient_name"><?php _e('Recipient Name', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_recipient_name"
                           value="Test Recipient" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_recipient_address"><?php _e('Recipient Address', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_recipient_address"
                           value="Test Address 456" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_recipient_city"><?php _e('Recipient City', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_recipient_city"
                           value="Thessaloniki" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_recipient_postcode"><?php _e('Recipient Postcode', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_recipient_postcode"
                           value="54625" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="test_recipient_phone"><?php _e('Recipient Phone', 'dmm-delivery-bridge'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="test_recipient_phone"
                           value="2101234567" 
                           class="regular-text" />
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="button" 
                    id="elta_test_voucher_btn" 
                    class="button button-primary">
                <?php _e('Create Test Voucher', 'dmm-delivery-bridge'); ?>
            </button>
            <span class="spinner" id="elta_test_voucher_spinner" style="float: none; margin-left: 10px;"></span>
        </p>
        
        <div id="elta_test_voucher_result" style="margin-top: 15px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load meta fields for voucher dropdown
    function loadMetaFields() {
        var $select = $('#elta_voucher_meta_field');
        var savedValue = '<?php echo esc_js(isset($options['elta_voucher_meta_field']) ? $options['elta_voucher_meta_field'] : ''); ?>';
        
        $.ajax({
            url: dmmAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dmm_get_order_meta_fields',
                nonce: dmmAdminData.nonce
            },
            success: function(response) {
                if (response.success && response.data.meta_fields) {
                    $select.empty();
                    $select.append('<option value=""><?php echo esc_js(__('-- Select Meta Field --', 'dmm-delivery-bridge')); ?></option>');
                    
                    var foundSavedValue = false;
                    
                    // Add all meta fields from the response
                    $.each(response.data.meta_fields, function(index, metaField) {
                        var isSelected = (metaField === savedValue);
                        if (isSelected) {
                            foundSavedValue = true;
                        }
                        $select.append('<option value="' + escapeHtml(metaField) + '" ' + (isSelected ? 'selected' : '') + '>' + escapeHtml(metaField) + '</option>');
                    });
                    
                    // If saved value is not in the list, add it anyway (in case it was deleted from orders)
                    if (savedValue && !foundSavedValue) {
                        $select.append('<option value="' + escapeHtml(savedValue) + '" selected>' + escapeHtml(savedValue) + '</option>');
                    }
                    
                    // Ensure the saved value is selected
                    if (savedValue) {
                        $select.val(savedValue);
                    }
                }
            },
            error: function() {
                console.error('Failed to load meta fields');
                // If AJAX fails, try to set the saved value if it exists
                if (savedValue) {
                    $select.append('<option value="' + escapeHtml(savedValue) + '" selected>' + escapeHtml(savedValue) + '</option>');
                }
            }
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    loadMetaFields();
    
    // Test Voucher functionality
    $('#elta_test_voucher_btn').on('click', function() {
        var $btn = $(this);
        var $spinner = $('#elta_test_voucher_spinner');
        var $result = $('#elta_test_voucher_result');
        
        // Disable button and show spinner
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.html('');
        
        // Collect form data
        var testData = {
            sender_name: $('#test_sender_name').val(),
            sender_address: $('#test_sender_address').val(),
            sender_city: $('#test_sender_city').val(),
            sender_postcode: $('#test_sender_postcode').val(),
            recipient_name: $('#test_recipient_name').val(),
            recipient_address: $('#test_recipient_address').val(),
            recipient_city: $('#test_recipient_city').val(),
            recipient_postcode: $('#test_recipient_postcode').val(),
            recipient_phone: $('#test_recipient_phone').val()
        };
        
        // Make AJAX request
        $.ajax({
            url: dmmAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'dmm_elta_create_test_voucher',
                nonce: dmmAdminData.nonce,
                test_data: testData
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                
                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong>' + 
                               escapeHtml('<?php echo esc_js(__('Success!', 'dmm-delivery-bridge')); ?>') + '</strong><br>';
                    
                    if (response.data.voucher_number) {
                        html += escapeHtml('<?php echo esc_js(__('Voucher Number:', 'dmm-delivery-bridge')); ?>') + ' <strong>' + 
                                escapeHtml(response.data.voucher_number) + '</strong><br>';
                    }
                    
                    if (response.data.message) {
                        html += escapeHtml(response.data.message);
                    }
                    
                    if (response.data.details) {
                        html += '<pre style="margin-top: 10px; background: #f5f5f5; padding: 10px; overflow-x: auto;">' + 
                                escapeHtml(JSON.stringify(response.data.details, null, 2)) + '</pre>';
                    }
                    
                    html += '</p></div>';
                    $result.html(html);
                } else {
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : '<?php echo esc_js(__('An error occurred while creating the test voucher.', 'dmm-delivery-bridge')); ?>';
                    
                    $result.html('<div class="notice notice-error"><p><strong>' + 
                                escapeHtml('<?php echo esc_js(__('Error:', 'dmm-delivery-bridge')); ?>') + '</strong> ' + 
                                escapeHtml(errorMsg) + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                
                $result.html('<div class="notice notice-error"><p><strong>' + 
                            escapeHtml('<?php echo esc_js(__('Error:', 'dmm-delivery-bridge')); ?>') + '</strong> ' + 
                            escapeHtml('<?php echo esc_js(__('AJAX request failed. Please check your connection and try again.', 'dmm-delivery-bridge')); ?>') + '</p></div>');
            }
        });
    });
});
</script>


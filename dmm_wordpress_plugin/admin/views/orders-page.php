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

<div class="wrap dmm-admin-page">
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
        <div class="dmm-card">
            <div class="dmm-card-header">
                <h2 class="dmm-card-title"><?php _e('WooCommerce Orders', 'dmm-delivery-bridge'); ?></h2>
                <div>
                    <button type="button" class="button button-secondary" id="dmm-refresh-orders">
                        <?php _e('🔄 Refresh', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
            </div>
            <div class="dmm-card-content">
                <div style="margin-bottom: 1rem;">
                    <label for="dmm-order-status-filter" style="margin-right: 1rem;">
                        <?php _e('Order Status:', 'dmm-delivery-bridge'); ?>
                    </label>
                    <select id="dmm-order-status-filter" style="margin-right: 1rem;">
                        <option value=""><?php _e('All Statuses', 'dmm-delivery-bridge'); ?></option>
                        <option value="pending"><?php _e('Pending', 'dmm-delivery-bridge'); ?></option>
                        <option value="processing"><?php _e('Processing', 'dmm-delivery-bridge'); ?></option>
                        <option value="on-hold"><?php _e('On Hold', 'dmm-delivery-bridge'); ?></option>
                        <option value="completed"><?php _e('Completed', 'dmm-delivery-bridge'); ?></option>
                        <option value="cancelled"><?php _e('Cancelled', 'dmm-delivery-bridge'); ?></option>
                        <option value="refunded"><?php _e('Refunded', 'dmm-delivery-bridge'); ?></option>
                        <option value="failed"><?php _e('Failed', 'dmm-delivery-bridge'); ?></option>
                    </select>
                    
                    <label for="dmm-order-sent-filter" style="margin-right: 1rem; margin-left: 1rem;">
                        <?php _e('DMM Status:', 'dmm-delivery-bridge'); ?>
                    </label>
                    <select id="dmm-order-sent-filter" style="margin-right: 1rem;">
                        <option value=""><?php _e('All', 'dmm-delivery-bridge'); ?></option>
                        <option value="sent"><?php _e('Sent to DMM', 'dmm-delivery-bridge'); ?></option>
                        <option value="not_sent"><?php _e('Not Sent', 'dmm-delivery-bridge'); ?></option>
                    </select>
                    
                    <input type="number" id="dmm-order-id-filter" placeholder="<?php _e('Order ID', 'dmm-delivery-bridge'); ?>" style="width: 120px; margin-right: 1rem;">
                    <button type="button" class="button button-primary" id="dmm-apply-order-filters">
                        <?php _e('Apply Filters', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
                
                <div id="dmm-orders-loading" class="dmm-loading-container dmm-spinner-small" style="display: none;">
                    <div class="dmm-spinner"></div>
                    <p class="dmm-loading-message"><?php _e('Loading orders...', 'dmm-delivery-bridge'); ?></p>
                </div>
                
                <div id="dmm-orders-table-container">
                    <table class="dmm-admin-table" id="dmm-orders-table" style="display: none;">
                        <thead>
                            <tr>
                                <th><?php _e('Order #', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Date', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Status', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Customer', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Total', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('DMM Status', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('DMM Order ID', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Actions', 'dmm-delivery-bridge'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="dmm-orders-tbody">
                        </tbody>
                    </table>
                    <div id="dmm-orders-empty" style="display: none; padding: 2rem; text-align: center; color: #646970;">
                        <p><?php _e('No orders found.', 'dmm-delivery-bridge'); ?></p>
                    </div>
                </div>
                
                <div id="dmm-orders-pagination" style="margin-top: 1rem; display: none;">
                    <button type="button" class="button button-secondary" id="dmm-orders-prev" disabled>
                        <?php _e('← Previous', 'dmm-delivery-bridge'); ?>
                    </button>
                    <span id="dmm-orders-page-info" style="margin: 0 1rem;"></span>
                    <button type="button" class="button button-secondary" id="dmm-orders-next">
                        <?php _e('Next →', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const nonce = window.dmmAdminNonce || '<?php echo wp_create_nonce('dmm_admin_nonce'); ?>';
        
        let currentPage = 0;
        const pageSize = 50;
        let currentFilters = { status: '', sent: '', order_id: 0 };
        
        function loadOrders(page = 0) {
            const loadingEl = document.getElementById('dmm-orders-loading');
            const tableEl = document.getElementById('dmm-orders-table');
            const tbodyEl = document.getElementById('dmm-orders-tbody');
            const emptyEl = document.getElementById('dmm-orders-empty');
            const paginationEl = document.getElementById('dmm-orders-pagination');
            
            loadingEl.style.display = 'flex';
            tableEl.style.display = 'none';
            emptyEl.style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'dmm_get_orders_list');
            formData.append('nonce', nonce);
            formData.append('limit', pageSize);
            formData.append('offset', page * pageSize);
            if (currentFilters.status) {
                formData.append('status', currentFilters.status);
            }
            if (currentFilters.sent) {
                formData.append('sent', currentFilters.sent);
            }
            if (currentFilters.order_id) {
                formData.append('order_id', currentFilters.order_id);
            }
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingEl.style.display = 'none';
                
                if (data.success && data.data.orders && data.data.orders.length > 0) {
                    tbodyEl.innerHTML = '';
                    data.data.orders.forEach(order => {
                        const row = document.createElement('tr');
                        const statusClass = 'status-' + order.status;
                        const dmmStatus = order.dmm_sent 
                            ? '<span class="dmm-status-badge dmm-status-badge-success">✓ Sent</span>' 
                            : '<span class="dmm-status-badge dmm-status-badge-warning">Not Sent</span>';
                        
                        const actions = [];
                        if (!order.dmm_sent) {
                            actions.push(`<button type="button" class="button button-small dmm-resend-order" data-order-id="${order.id}"><?php _e('Send', 'dmm-delivery-bridge'); ?></button>`);
                        } else {
                            actions.push(`<button type="button" class="button button-small dmm-sync-order" data-order-id="${order.id}"><?php _e('Sync', 'dmm-delivery-bridge'); ?></button>`);
                        }
                        actions.push(`<a href="${order.edit_url}" class="button button-small" target="_blank"><?php _e('View', 'dmm-delivery-bridge'); ?></a>`);
                        
                        row.innerHTML = `
                            <td><strong>#${order.order_number}</strong></td>
                            <td>${new Date(order.date).toLocaleString()}</td>
                            <td><span class="dmm-status-badge ${statusClass}">${order.status}</span></td>
                            <td>${order.customer_name || '-'}<br><small>${order.customer_email || ''}</small></td>
                            <td>${order.total} ${order.currency}</td>
                            <td>${dmmStatus}</td>
                            <td>${order.dmm_order_id || '-'}</td>
                            <td>${actions.join(' ')}</td>
                        `;
                        tbodyEl.appendChild(row);
                    });
                    tableEl.style.display = 'table';
                    
                    // Update pagination
                    const totalPages = Math.ceil(data.data.total / pageSize);
                    document.getElementById('dmm-orders-page-info').textContent = 
                        `Page ${page + 1} of ${totalPages} (${data.data.total} total)`;
                    document.getElementById('dmm-orders-prev').disabled = page === 0;
                    document.getElementById('dmm-orders-next').disabled = page >= totalPages - 1;
                    paginationEl.style.display = 'flex';
                    
                    // Attach event listeners to action buttons
                    attachActionListeners();
                } else {
                    emptyEl.style.display = 'block';
                    paginationEl.style.display = 'none';
                }
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                console.error('Error loading orders:', error);
                emptyEl.innerHTML = '<p style="color: #d63638;"><?php _e('Error loading orders. Please try again.', 'dmm-delivery-bridge'); ?></p>';
                emptyEl.style.display = 'block';
            });
        }
        
        function attachActionListeners() {
            // Resend/Send buttons
            document.querySelectorAll('.dmm-resend-order').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    if (confirm('<?php _e('Are you sure you want to send this order to DMM Delivery?', 'dmm-delivery-bridge'); ?>')) {
                        sendOrder(orderId, this);
                    }
                });
            });
            
            // Sync buttons
            document.querySelectorAll('.dmm-sync-order').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    syncOrder(orderId, this);
                });
            });
        }
        
        function sendOrder(orderId, buttonEl) {
            const originalText = buttonEl.textContent;
            buttonEl.disabled = true;
            buttonEl.textContent = '<?php _e('Sending...', 'dmm-delivery-bridge'); ?>';
            
            const formData = new FormData();
            formData.append('action', 'dmm_resend_order');
            formData.append('nonce', nonce);
            formData.append('order_id', orderId);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message || '<?php _e('Order sent successfully!', 'dmm-delivery-bridge'); ?>');
                    loadOrders(currentPage); // Reload to update status
                } else {
                    alert(data.data?.message || '<?php _e('Failed to send order.', 'dmm-delivery-bridge'); ?>');
                    buttonEl.disabled = false;
                    buttonEl.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error sending order:', error);
                alert('<?php _e('Error sending order. Please try again.', 'dmm-delivery-bridge'); ?>');
                buttonEl.disabled = false;
                buttonEl.textContent = originalText;
            });
        }
        
        function syncOrder(orderId, buttonEl) {
            const originalText = buttonEl.textContent;
            buttonEl.disabled = true;
            buttonEl.textContent = '<?php _e('Syncing...', 'dmm-delivery-bridge'); ?>';
            
            const formData = new FormData();
            formData.append('action', 'dmm_sync_order');
            formData.append('nonce', nonce);
            formData.append('order_id', orderId);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.data.message || '<?php _e('Order synced successfully!', 'dmm-delivery-bridge'); ?>');
                    loadOrders(currentPage); // Reload to update status
                } else {
                    alert(data.data?.message || '<?php _e('Failed to sync order.', 'dmm-delivery-bridge'); ?>');
                    buttonEl.disabled = false;
                    buttonEl.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error syncing order:', error);
                alert('<?php _e('Error syncing order. Please try again.', 'dmm-delivery-bridge'); ?>');
                buttonEl.disabled = false;
                buttonEl.textContent = originalText;
            });
        }
        
        // Event listeners
        document.getElementById('dmm-refresh-orders').addEventListener('click', () => loadOrders(currentPage));
        document.getElementById('dmm-apply-order-filters').addEventListener('click', () => {
            currentFilters.status = document.getElementById('dmm-order-status-filter').value;
            currentFilters.sent = document.getElementById('dmm-order-sent-filter').value;
            currentFilters.order_id = parseInt(document.getElementById('dmm-order-id-filter').value) || 0;
            currentPage = 0;
            loadOrders(0);
        });
        document.getElementById('dmm-orders-prev').addEventListener('click', () => {
            if (currentPage > 0) {
                currentPage--;
                loadOrders(currentPage);
            }
        });
        document.getElementById('dmm-orders-next').addEventListener('click', () => {
            currentPage++;
            loadOrders(currentPage);
        });
        
        // Load orders on page load
        loadOrders(0);
    })();
    </script>
</div>

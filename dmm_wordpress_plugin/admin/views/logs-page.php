<?php
/**
 * Error logs page view
 *
 * @var array $options Plugin options
 * @var DMM_Delivery_Bridge $plugin Main plugin instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap dmm-admin-page">
    <h1><?php _e('Error Logs', 'dmm-delivery-bridge'); ?></h1>
    
    <div class="notice notice-info">
        <p><?php _e('View and manage error logs from the DMM Delivery Bridge operations.', 'dmm-delivery-bridge'); ?></p>
    </div>
    
    <!-- Navigation Links -->
    <?php 
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'dmm-delivery-bridge-logs';
    DMM_Admin::render_navigation($current_page);
    ?>
    
    <div class="dmm-logs-container">
        <div class="dmm-card">
            <div class="dmm-card-header">
                <h2 class="dmm-card-title"><?php _e('Error Logs', 'dmm-delivery-bridge'); ?></h2>
                <div>
                    <button type="button" class="button button-secondary" id="dmm-refresh-logs">
                        <?php _e('🔄 Refresh', 'dmm-delivery-bridge'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="dmm-clear-logs">
                        <?php _e('🗑️ Clear Logs', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
            </div>
            <div class="dmm-card-content">
                <div style="margin-bottom: 1rem;">
                    <label for="dmm-log-status-filter" style="margin-right: 1rem;">
                        <?php _e('Filter by Status:', 'dmm-delivery-bridge'); ?>
                    </label>
                    <select id="dmm-log-status-filter" style="margin-right: 1rem;">
                        <option value=""><?php _e('All', 'dmm-delivery-bridge'); ?></option>
                        <option value="error"><?php _e('Errors', 'dmm-delivery-bridge'); ?></option>
                        <option value="warning"><?php _e('Warnings', 'dmm-delivery-bridge'); ?></option>
                        <option value="info"><?php _e('Info', 'dmm-delivery-bridge'); ?></option>
                        <option value="success"><?php _e('Success', 'dmm-delivery-bridge'); ?></option>
                    </select>
                    <input type="number" id="dmm-log-order-filter" placeholder="<?php _e('Order ID', 'dmm-delivery-bridge'); ?>" style="width: 120px; margin-right: 1rem;">
                    <button type="button" class="button button-primary" id="dmm-apply-filters">
                        <?php _e('Apply Filters', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
                
                <div id="dmm-logs-loading" class="dmm-loading-container dmm-spinner-small" style="display: none;">
                    <div class="dmm-spinner"></div>
                    <p class="dmm-loading-message"><?php _e('Loading logs...', 'dmm-delivery-bridge'); ?></p>
                </div>
                
                <div id="dmm-logs-table-container">
                    <table class="dmm-admin-table" id="dmm-logs-table" style="display: none;">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Order ID', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Status', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Context', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Message', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Date', 'dmm-delivery-bridge'); ?></th>
                                <th><?php _e('Actions', 'dmm-delivery-bridge'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="dmm-logs-tbody">
                        </tbody>
                    </table>
                    <div id="dmm-logs-empty" style="display: none; padding: 2rem; text-align: center; color: #646970;">
                        <p><?php _e('No logs found.', 'dmm-delivery-bridge'); ?></p>
                    </div>
                </div>
                
                <div id="dmm-logs-pagination" style="margin-top: 1rem; display: none;">
                    <button type="button" class="button button-secondary" id="dmm-logs-prev" disabled>
                        <?php _e('← Previous', 'dmm-delivery-bridge'); ?>
                    </button>
                    <span id="dmm-logs-page-info" style="margin: 0 1rem;"></span>
                    <button type="button" class="button button-secondary" id="dmm-logs-next">
                        <?php _e('Next →', 'dmm-delivery-bridge'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        // Get nonce from localized script or create it
        const nonce = window.dmmAdminNonce || '<?php echo wp_create_nonce('dmm_admin_nonce'); ?>';
        
        let currentPage = 0;
        const pageSize = 50;
        let currentFilters = { status: '', order_id: 0 };
        
        function loadLogs(page = 0) {
            const loadingEl = document.getElementById('dmm-logs-loading');
            const tableEl = document.getElementById('dmm-logs-table');
            const tbodyEl = document.getElementById('dmm-logs-tbody');
            const emptyEl = document.getElementById('dmm-logs-empty');
            const paginationEl = document.getElementById('dmm-logs-pagination');
            
            loadingEl.style.display = 'flex';
            tableEl.style.display = 'none';
            emptyEl.style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'dmm_get_logs_table');
            formData.append('nonce', nonce);
            formData.append('limit', pageSize);
            formData.append('offset', page * pageSize);
            if (currentFilters.status) {
                formData.append('status', currentFilters.status);
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
                
                if (data.success && data.data.logs && data.data.logs.length > 0) {
                    tbodyEl.innerHTML = '';
                    data.data.logs.forEach(log => {
                        const row = document.createElement('tr');
                        const statusClass = 'dmm-status-badge-' + (log.status === 'error' ? 'error' : log.status === 'warning' ? 'warning' : log.status === 'success' ? 'success' : 'info');
                        row.innerHTML = `
                            <td>${log.id}</td>
                            <td>${log.order_id || '-'}</td>
                            <td><span class="dmm-status-badge ${statusClass}">${log.status}</span></td>
                            <td>${log.context || 'api'}</td>
                            <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;">${log.error_message || '-'}</td>
                            <td>${new Date(log.created_at).toLocaleString()}</td>
                            <td>
                                <a href="${ajaxurl.replace('admin-ajax.php', 'admin.php')}?page=dmm-delivery-bridge-log-details&log_id=${log.id}" class="button button-small">
                                    <?php _e('View', 'dmm-delivery-bridge'); ?>
                                </a>
                            </td>
                        `;
                        tbodyEl.appendChild(row);
                    });
                    tableEl.style.display = 'table';
                    
                    // Update pagination
                    const totalPages = Math.ceil(data.data.total / pageSize);
                    document.getElementById('dmm-logs-page-info').textContent = 
                        `Page ${page + 1} of ${totalPages} (${data.data.total} total)`;
                    document.getElementById('dmm-logs-prev').disabled = page === 0;
                    document.getElementById('dmm-logs-next').disabled = page >= totalPages - 1;
                    paginationEl.style.display = 'flex';
                } else {
                    emptyEl.style.display = 'block';
                    paginationEl.style.display = 'none';
                }
            })
            .catch(error => {
                loadingEl.style.display = 'none';
                console.error('Error loading logs:', error);
                emptyEl.innerHTML = '<p style="color: #d63638;"><?php _e('Error loading logs. Please try again.', 'dmm-delivery-bridge'); ?></p>';
                emptyEl.style.display = 'block';
            });
        }
        
        document.getElementById('dmm-refresh-logs').addEventListener('click', () => loadLogs(currentPage));
        document.getElementById('dmm-apply-filters').addEventListener('click', () => {
            currentFilters.status = document.getElementById('dmm-log-status-filter').value;
            currentFilters.order_id = parseInt(document.getElementById('dmm-log-order-filter').value) || 0;
            currentPage = 0;
            loadLogs(0);
        });
        document.getElementById('dmm-logs-prev').addEventListener('click', () => {
            if (currentPage > 0) {
                currentPage--;
                loadLogs(currentPage);
            }
        });
        document.getElementById('dmm-logs-next').addEventListener('click', () => {
            currentPage++;
            loadLogs(currentPage);
        });
        
        // Load logs on page load
        loadLogs(0);
    })();
    </script>
</div>


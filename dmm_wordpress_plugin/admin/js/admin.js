/**
 * DMM Delivery Bridge Admin JavaScript
 * 
 * Modern React-based admin interface with loading states,
 * progress indicators, and improved error messaging.
 */

(function() {
    'use strict';

    const { createElement: el, useState, useEffect, useRef } = wp.element;
    const { __ } = wp.i18n;

    /**
     * Loading Spinner Component
     */
    const LoadingSpinner = ({ size = 'medium', message = '' }) => {
        const sizeClass = size === 'small' ? 'dmm-spinner-small' : size === 'large' ? 'dmm-spinner-large' : '';
        return el('div', { className: `dmm-loading-container ${sizeClass}` },
            el('div', { className: 'dmm-spinner' }),
            message && el('p', { className: 'dmm-loading-message' }, message)
        );
    };

    /**
     * Progress Bar Component
     */
    const ProgressBar = ({ progress = 0, total = 0, current = 0, label = '', showPercentage = true }) => {
        const percentage = total > 0 ? Math.round((progress / total) * 100) : 0;
        
        return el('div', { className: 'dmm-progress-container' },
            label && el('div', { className: 'dmm-progress-header' },
                el('span', { className: 'dmm-progress-label' }, label),
                showPercentage && el('span', { className: 'dmm-progress-percentage' }, `${percentage}%`)
            ),
            el('div', { className: 'dmm-progress-bar-wrapper' },
                el('div', {
                    className: 'dmm-progress-bar',
                    style: { width: `${percentage}%` }
                })
            ),
            total > 0 && el('div', { className: 'dmm-progress-stats' },
                el('span', null, `${current} / ${total} ${__('completed', 'dmm-delivery-bridge')}`)
            )
        );
    };

    /**
     * Error Message Component
     */
    const ErrorMessage = ({ message, details = '', onDismiss = null, type = 'error' }) => {
        const [isVisible, setIsVisible] = useState(true);
        
        if (!isVisible) return null;
        
        const handleDismiss = () => {
            setIsVisible(false);
            if (onDismiss) onDismiss();
        };
        
        return el('div', {
            className: `dmm-message dmm-message-${type} ${onDismiss ? 'dmm-message-dismissible' : ''}`
        },
            el('div', { className: 'dmm-message-content' },
                el('strong', { className: 'dmm-message-title' },
                    type === 'error' ? __('Error', 'dmm-delivery-bridge') :
                    type === 'warning' ? __('Warning', 'dmm-delivery-bridge') :
                    type === 'success' ? __('Success', 'dmm-delivery-bridge') :
                    __('Notice', 'dmm-delivery-bridge')
                ),
                el('p', { className: 'dmm-message-text' }, message),
                details && el('details', { className: 'dmm-message-details' },
                    el('summary', null, __('Show Details', 'dmm-delivery-bridge')),
                    el('pre', null, details)
                )
            ),
            onDismiss && el('button', {
                type: 'button',
                className: 'dmm-message-dismiss',
                onClick: handleDismiss,
                'aria-label': __('Dismiss', 'dmm-delivery-bridge')
            }, '×')
        );
    };

    /**
     * Success Message Component
     */
    const SuccessMessage = ({ message, onDismiss = null }) => {
        return el(ErrorMessage, { message, onDismiss, type: 'success' });
    };

    /**
     * Bulk Operations Component
     */
    const BulkOperations = ({ onStart, onCancel, isRunning = false, progress = null }) => {
        const [selectedAction, setSelectedAction] = useState('');
        const [actionParams, setActionParams] = useState({});
        
        const actions = [
            { value: 'send', label: __('Send Orders', 'dmm-delivery-bridge') },
            { value: 'sync', label: __('Sync Orders', 'dmm-delivery-bridge') },
            { value: 'resend', label: __('Resend Failed Orders', 'dmm-delivery-bridge') }
        ];
        
        const handleStart = () => {
            if (selectedAction && onStart) {
                onStart(selectedAction, actionParams);
            }
        };
        
        return el('div', { className: 'dmm-bulk-operations' },
            !isRunning && el('div', { className: 'dmm-bulk-controls' },
                el('select', {
                    className: 'dmm-bulk-action-select',
                    value: selectedAction,
                    onChange: (e) => setSelectedAction(e.target.value)
                },
                    el('option', { value: '' }, __('Select Action...', 'dmm-delivery-bridge')),
                    ...actions.map(action =>
                        el('option', { key: action.value, value: action.value }, action.label)
                    )
                ),
                el('button', {
                    type: 'button',
                    className: 'button button-primary',
                    onClick: handleStart,
                    disabled: !selectedAction
                }, __('Start Bulk Operation', 'dmm-delivery-bridge'))
            ),
            isRunning && el('div', { className: 'dmm-bulk-progress' },
                progress && el(ProgressBar, {
                    progress: progress.current || 0,
                    total: progress.total || 0,
                    current: progress.current || 0,
                    label: progress.label || __('Processing...', 'dmm-delivery-bridge'),
                    showPercentage: true
                }),
                el('button', {
                    type: 'button',
                    className: 'button button-secondary',
                    onClick: onCancel
                }, __('Cancel', 'dmm-delivery-bridge'))
            )
        );
    };

    /**
     * Initialize admin interface
     */
    const initAdminInterface = () => {
        // Make components available globally
        window.DMMAdmin = {
            LoadingSpinner,
            ProgressBar,
            ErrorMessage,
            SuccessMessage,
            BulkOperations
        };
        
        // Initialize bulk operations on bulk page
        const bulkPage = document.getElementById('dmm-bulk-operations-container');
        if (bulkPage) {
            const { render } = wp.element;
            const { ajaxurl } = window;
            
            const BulkOperationsPage = () => {
                const [isRunning, setIsRunning] = useState(false);
                const [progress, setProgress] = useState(null);
                const [error, setError] = useState(null);
                const [success, setSuccess] = useState(null);
                const intervalRef = useRef(null);
                
                const [currentJobId, setCurrentJobId] = useState(null);
                
                const startBulkOperation = async (action, params) => {
                    setIsRunning(true);
                    setProgress({ current: 0, total: 0, label: __('Initializing...', 'dmm-delivery-bridge') });
                    setError(null);
                    setSuccess(null);
                    
                    try {
                        const formData = new FormData();
                        // Map action names to AJAX handlers
                        const actionMap = {
                            'send': 'dmm_bulk_send_orders',
                            'sync': 'dmm_bulk_sync_orders',
                            'resend': 'dmm_bulk_send_orders' // Resend uses same handler
                        };
                        formData.append('action', actionMap[action] || `dmm_bulk_${action}_orders`);
                        formData.append('nonce', window.dmmAdminNonce);
                        formData.append('params', JSON.stringify(params));
                        
                        const response = await fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (!data.success) {
                            throw new Error(data.data?.message || __('Operation failed', 'dmm-delivery-bridge'));
                        }
                        
                        // Start polling for progress
                        const jobId = data.data?.job_id;
                        if (jobId) {
                            setCurrentJobId(jobId);
                            startProgressPolling(jobId, action);
                        }
                    } catch (err) {
                        setError(err.message);
                        setIsRunning(false);
                        setProgress(null);
                    }
                };
                
                const startProgressPolling = (jobId, action) => {
                    if (intervalRef.current) {
                        clearInterval(intervalRef.current);
                    }
                    
                    intervalRef.current = setInterval(async () => {
                        try {
                            const formData = new FormData();
                            formData.append('action', 'dmm_get_bulk_progress');
                            formData.append('nonce', window.dmmAdminNonce);
                            formData.append('job_id', jobId);
                            
                            const response = await fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            });
                            
                            const data = await response.json();
                            
                            if (data.success && data.data) {
                                const progressData = data.data;
                                setProgress({
                                    current: progressData.current || 0,
                                    total: progressData.total || 0,
                                    label: progressData.label || __('Processing...', 'dmm-delivery-bridge')
                                });
                                
                                if (progressData.status === 'completed') {
                                    clearInterval(intervalRef.current);
                                    setIsRunning(false);
                                    setSuccess(__('Bulk operation completed successfully!', 'dmm-delivery-bridge'));
                                    setProgress(null);
                                    setCurrentJobId(null);
                                } else if (progressData.status === 'failed' || progressData.status === 'cancelled') {
                                    clearInterval(intervalRef.current);
                                    setIsRunning(false);
                                    if (progressData.status === 'cancelled') {
                                        setError(__('Operation was cancelled.', 'dmm-delivery-bridge'));
                                    } else {
                                        setError(progressData.error || __('Operation failed', 'dmm-delivery-bridge'));
                                    }
                                    setProgress(null);
                                    setCurrentJobId(null);
                                }
                            } else if (!data.success && data.data?.status === 'not_found') {
                                // Job expired or not found
                                clearInterval(intervalRef.current);
                                setIsRunning(false);
                                setError(__('Job expired or not found.', 'dmm-delivery-bridge'));
                                setProgress(null);
                                setCurrentJobId(null);
                            }
                        } catch (err) {
                            console.error('Progress polling error:', err);
                        }
                    }, 1000); // Poll every second
                };
                
                const cancelBulkOperation = async () => {
                    if (intervalRef.current) {
                        clearInterval(intervalRef.current);
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'dmm_cancel_bulk_send');
                        formData.append('nonce', window.dmmAdminNonce);
                        if (currentJobId) {
                            formData.append('job_id', currentJobId);
                        }
                        
                        await fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        });
                    } catch (err) {
                        console.error('Cancel error:', err);
                    }
                    
                    setIsRunning(false);
                    setProgress(null);
                    setCurrentJobId(null);
                };
                
                
                useEffect(() => {
                    return () => {
                        if (intervalRef.current) {
                            clearInterval(intervalRef.current);
                        }
                    };
                }, []);
                
                return el('div', { className: 'dmm-bulk-page' },
                    error && el(ErrorMessage, {
                        message: error,
                        onDismiss: () => setError(null)
                    }),
                    success && el(SuccessMessage, {
                        message: success,
                        onDismiss: () => setSuccess(null)
                    }),
                    el(BulkOperations, {
                        onStart: startBulkOperation,
                        onCancel: cancelBulkOperation,
                        isRunning: isRunning,
                        progress: progress
                    })
                );
            };
            
            render(el(BulkOperationsPage), bulkPage);
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminInterface);
    } else {
        initAdminInterface();
    }
})();


import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import { DocumentArrowUpIcon, CloudArrowUpIcon, DocumentCheckIcon, 
         ExclamationTriangleIcon, CheckCircleIcon, XCircleIcon, 
         ArrowPathIcon, TrashIcon, EyeIcon, ArrowDownTrayIcon,
         ClockIcon, ChartBarIcon } from '@heroicons/react/24/outline';
import { Disclosure } from '@headlessui/react';
import { ChevronUpIcon } from '@heroicons/react/20/solid';

export default function ImportIndex({ auth, recentImports, stats, supportedFormats, maxFileSize }) {
    const [selectedFile, setSelectedFile] = useState(null);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [isUploading, setIsUploading] = useState(false);
    const [activeImports, setActiveImports] = useState([]);
    const [pollingStopped, setPollingStopped] = useState(false);
    const fileInputRef = useRef(null);
    const pollIntervalRef = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        create_missing_customers: true,
        update_existing_orders: false,
        send_notifications: false,
        auto_create_shipments: false,
        default_status: 'pending',
        field_mapping: {},
        notes: ''
    });

    // Poll for active imports status
    useEffect(() => {
        const activeIds = recentImports
            .filter(imp => ['pending', 'processing'].includes(imp.status))
            .map(imp => imp.id);

        if (activeIds.length > 0 && !pollingStopped) {
            pollIntervalRef.current = setInterval(() => {
                pollActiveImports(activeIds);
            }, 2000);
        }

        return () => {
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
            }
        };
    }, [recentImports, pollingStopped]);

    const pollActiveImports = async (importIds) => {
        try {
            const responses = await Promise.all(
                importIds.map(id => 
                    fetch(route('orders.import.status', id))
                        .then(res => res.json())
                )
            );

            const updates = responses
                .filter(resp => resp.success)
                .map(resp => resp.import);

            if (updates.length > 0) {
                setActiveImports(prev => {
                    const updated = [...prev];
                    updates.forEach(update => {
                        const index = updated.findIndex(imp => imp.id === update.id);
                        if (index >= 0) {
                            updated[index] = update;
                        } else {
                            updated.push(update);
                        }
                    });
                    return updated;
                });

                // Stop polling if all imports are finished
                const allFinished = updates.every(imp => 
                    ['completed', 'failed', 'partial', 'cancelled'].includes(imp.status)
                );
                if (allFinished) {
                    setPollingStopped(true);
                }
            }
        } catch (error) {
            console.error('Failed to poll import status:', error);
        }
    };

    const handleFileSelect = (e) => {
        const file = e.target.files[0];
        if (file) {
            setSelectedFile(file);
            setData('file', file);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file) {
            setSelectedFile(file);
            setData('file', file);
        }
    };

    const handleDragOver = (e) => {
        e.preventDefault();
    };

    const handleUpload = () => {
        if (!selectedFile) return;

        setIsUploading(true);
        setUploadProgress(0);

        // Create FormData
        const formData = new FormData();
        formData.append('file', data.file);
        formData.append('create_missing_customers', data.create_missing_customers);
        formData.append('update_existing_orders', data.update_existing_orders);
        formData.append('send_notifications', data.send_notifications);
        formData.append('auto_create_shipments', data.auto_create_shipments);
        formData.append('default_status', data.default_status);
        formData.append('notes', data.notes);

        // Use XMLHttpRequest for upload progress
        const xhr = new XMLHttpRequest();

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const progress = (e.loaded / e.total) * 100;
                setUploadProgress(progress);
            }
        };

        xhr.onload = () => {
            setIsUploading(false);
            const response = JSON.parse(xhr.responseText);
            
            if (response.success) {
                alert('File uploaded successfully! Processing will begin shortly.');
                setSelectedFile(null);
                reset();
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
                // Refresh page after short delay
                setTimeout(() => window.location.reload(), 1000);
            } else {
                alert('Upload failed: ' + response.message);
            }
        };

        xhr.onerror = () => {
            setIsUploading(false);
            alert('Upload failed. Please try again.');
        };

        xhr.open('POST', route('orders.import.upload'));
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        xhr.send(formData);
    };

    const formatFileSize = (bytes) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const getStatusBadge = (status, statusColor) => {
        const colors = {
            pending: 'bg-gray-100 text-gray-800',
            processing: 'bg-blue-100 text-blue-800',
            completed: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
            partial: 'bg-yellow-100 text-yellow-800',
            cancelled: 'bg-gray-100 text-gray-800'
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[status] || colors.pending}`}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    const getProgressBar = (import_) => {
        if (!['processing', 'completed', 'partial'].includes(import_.status)) {
            return null;
        }

        return (
            <div className="mt-2">
                <div className="flex justify-between text-xs text-gray-600 mb-1">
                    <span>Progress</span>
                    <span>{import_.progress}%</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                    <div 
                        className={`h-2 rounded-full transition-all duration-300 ${
                            import_.status === 'completed' ? 'bg-green-500' :
                            import_.status === 'partial' ? 'bg-yellow-500' :
                            'bg-blue-500'
                        }`}
                        style={{ width: `${import_.progress}%` }}
                    ></div>
                </div>
            </div>
        );
    };

    const handleAction = async (action, importId) => {
        try {
            let url, method = 'POST';
            
            switch (action) {
                case 'cancel':
                    url = route('orders.import.cancel', importId);
                    break;
                case 'retry':
                    url = route('orders.import.retry', importId);
                    break;
                case 'delete':
                    url = route('orders.import.delete', importId);
                    method = 'DELETE';
                    break;
                default:
                    return;
            }

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const result = await response.json();
            
            if (result.success) {
                alert(result.message);
                window.location.reload();
            } else {
                alert('Action failed: ' + result.message);
            }
        } catch (error) {
            alert('Action failed. Please try again.');
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Order Import</h2>}
        >
            <Head title="Order Import" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <DocumentCheckIcon className="h-8 w-8 text-blue-500" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Total Imports</dt>
                                        <dd className="text-lg font-medium text-gray-900">{stats.total_imports}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <CheckCircleIcon className="h-8 w-8 text-green-500" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Successful</dt>
                                        <dd className="text-lg font-medium text-gray-900">{stats.successful_imports}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <XCircleIcon className="h-8 w-8 text-red-500" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Failed</dt>
                                        <dd className="text-lg font-medium text-gray-900">{stats.failed_imports}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <ClockIcon className="h-8 w-8 text-yellow-500" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">In Progress</dt>
                                        <dd className="text-lg font-medium text-gray-900">{stats.in_progress_imports}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <ChartBarIcon className="h-8 w-8 text-purple-500" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="text-sm font-medium text-gray-500 truncate">Orders Imported</dt>
                                        <dd className="text-lg font-medium text-gray-900">{stats.total_orders_imported}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* File Upload Section */}
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Import New Orders</h3>
                            
                            {/* Drag & Drop Area */}
                            <div 
                                className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors ${
                                    selectedFile 
                                        ? 'border-green-300 bg-green-50' 
                                        : 'border-gray-300 hover:border-gray-400'
                                }`}
                                onDrop={handleDrop}
                                onDragOver={handleDragOver}
                            >
                                {selectedFile ? (
                                    <div className="space-y-2">
                                        <DocumentCheckIcon className="mx-auto h-12 w-12 text-green-500" />
                                        <div className="text-sm font-medium text-gray-900">{selectedFile.name}</div>
                                        <div className="text-sm text-gray-500">{formatFileSize(selectedFile.size)}</div>
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        <CloudArrowUpIcon className="mx-auto h-12 w-12 text-gray-400" />
                                        <div className="text-sm font-medium text-gray-900">
                                            Drop your file here, or{' '}
                                            <button
                                                type="button"
                                                onClick={() => fileInputRef.current?.click()}
                                                className="text-blue-600 hover:text-blue-500"
                                            >
                                                browse
                                            </button>
                                        </div>
                                        <div className="text-sm text-gray-500">
                                            Supports: {Object.keys(supportedFormats).join(', ')} • Max size: {maxFileSize}
                                        </div>
                                    </div>
                                )}

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    className="hidden"
                                    accept={Object.values(supportedFormats).flat().map(ext => `.${ext}`).join(',')}
                                    onChange={handleFileSelect}
                                />
                            </div>

                            {errors.file && <InputError message={errors.file} className="mt-2" />}

                            {/* Upload Progress */}
                            {isUploading && (
                                <div className="mt-4">
                                    <div className="flex justify-between text-sm text-gray-600 mb-1">
                                        <span>Uploading...</span>
                                        <span>{Math.round(uploadProgress)}%</span>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                            style={{ width: `${uploadProgress}%` }}
                                        ></div>
                                    </div>
                                </div>
                            )}

                            {/* Import Options */}
                            <Disclosure as="div" className="mt-6">
                                {({ open }) => (
                                    <>
                                        <Disclosure.Button className="flex justify-between w-full px-4 py-2 text-sm font-medium text-left text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:outline-none focus-visible:ring focus-visible:ring-gray-500 focus-visible:ring-opacity-75">
                                            <span>Import Options</span>
                                            <ChevronUpIcon
                                                className={`${open ? 'transform rotate-180' : ''} w-5 h-5 text-gray-500`}
                                            />
                                        </Disclosure.Button>
                                        <Disclosure.Panel className="px-4 pt-4 pb-2 text-sm text-gray-500">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.create_missing_customers}
                                                        onChange={(e) => setData('create_missing_customers', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2">Create missing customers</span>
                                                </label>

                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.update_existing_orders}
                                                        onChange={(e) => setData('update_existing_orders', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2">Update existing orders</span>
                                                </label>

                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.send_notifications}
                                                        onChange={(e) => setData('send_notifications', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2">Send notifications</span>
                                                </label>

                                                <label className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.auto_create_shipments}
                                                        onChange={(e) => setData('auto_create_shipments', e.target.checked)}
                                                        className="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                    />
                                                    <span className="ml-2">Auto-create shipments</span>
                                                </label>
                                            </div>

                                            <div className="mt-4">
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Default Order Status
                                                </label>
                                                <select
                                                    value={data.default_status}
                                                    onChange={(e) => setData('default_status', e.target.value)}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                >
                                                    <option value="pending">Pending</option>
                                                    <option value="processing">Processing</option>
                                                    <option value="ready_to_ship">Ready to Ship</option>
                                                </select>
                                            </div>

                                            <div className="mt-4">
                                                <label className="block text-sm font-medium text-gray-700">
                                                    Notes
                                                </label>
                                                <textarea
                                                    value={data.notes}
                                                    onChange={(e) => setData('notes', e.target.value)}
                                                    rows={3}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                                    placeholder="Optional notes about this import..."
                                                />
                                            </div>
                                        </Disclosure.Panel>
                                    </>
                                )}
                            </Disclosure>

                            {/* Action Buttons */}
                            <div className="flex justify-between items-center mt-6">
                                <div className="flex space-x-3">
                                    {Object.keys(supportedFormats).map((format) => (
                                        <a
                                            key={format}
                                            href={route('orders.import.template', { format: format.toLowerCase() })}
                                            className="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            <ArrowDownTrayIcon className="h-4 w-4 mr-1" />
                                            {format} Template
                                        </a>
                                    ))}
                                </div>

                                <div className="flex space-x-3">
                                    <SecondaryButton
                                        onClick={() => {
                                            setSelectedFile(null);
                                            reset();
                                            if (fileInputRef.current) {
                                                fileInputRef.current.value = '';
                                            }
                                        }}
                                        disabled={!selectedFile || isUploading}
                                    >
                                        Clear
                                    </SecondaryButton>

                                    <PrimaryButton
                                        onClick={handleUpload}
                                        disabled={!selectedFile || isUploading}
                                    >
                                        {isUploading ? (
                                            <>
                                                <ArrowPathIcon className="animate-spin -ml-1 mr-2 h-4 w-4" />
                                                Uploading...
                                            </>
                                        ) : (
                                            <>
                                                <DocumentArrowUpIcon className="-ml-1 mr-2 h-4 w-4" />
                                                Start Import
                                            </>
                                        )}
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Recent Imports */}
                    <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900">Recent Imports</h3>
                        </div>
                        
                        <div className="divide-y divide-gray-200">
                            {recentImports.length === 0 ? (
                                <div className="p-6 text-center text-gray-500">
                                    No imports yet. Upload your first file to get started!
                                </div>
                            ) : (
                                recentImports.map((import_) => {
                                    // Use real-time data if available
                                    const activeImport = activeImports.find(ai => ai.id === import_.id);
                                    const displayImport = activeImport || import_;

                                    return (
                                        <div key={import_.id} className="p-6">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center space-x-3">
                                                    <span className="text-2xl">{import_.type_icon}</span>
                                                    <div>
                                                        <h4 className="text-sm font-medium text-gray-900">
                                                            {import_.file_name || 'API Import'}
                                                        </h4>
                                                        <div className="flex items-center space-x-2 text-sm text-gray-500">
                                                            <span>{import_.created_at}</span>
                                                            <span>•</span>
                                                            <span>{import_.total_rows} rows</span>
                                                            {import_.processing_time !== 'N/A' && (
                                                                <>
                                                                    <span>•</span>
                                                                    <span>{import_.processing_time}</span>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="flex items-center space-x-3">
                                                    {getStatusBadge(displayImport.status)}
                                                    
                                                    {/* Action Buttons */}
                                                    <div className="flex space-x-1">
                                                        {displayImport.status === 'failed' && (
                                                            <button
                                                                onClick={() => handleAction('retry', import_.id)}
                                                                className="p-1.5 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded"
                                                                title="Retry Import"
                                                            >
                                                                <ArrowPathIcon className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                        
                                                        {['pending', 'processing'].includes(displayImport.status) && (
                                                            <button
                                                                onClick={() => handleAction('cancel', import_.id)}
                                                                className="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-100 rounded"
                                                                title="Cancel Import"
                                                            >
                                                                <XCircleIcon className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                        
                                                        {['completed', 'failed', 'partial', 'cancelled'].includes(displayImport.status) && (
                                                            <button
                                                                onClick={() => handleAction('delete', import_.id)}
                                                                className="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-100 rounded"
                                                                title="Delete Import"
                                                            >
                                                                <TrashIcon className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {getProgressBar(displayImport)}

                                            {/* Results Summary */}
                                            {['completed', 'partial', 'failed'].includes(displayImport.status) && (
                                                <div className="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                                    <div>
                                                        <span className="text-gray-500">Orders Created:</span>
                                                        <span className="ml-1 font-medium text-green-600">
                                                            {import_.orders_created}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-gray-500">Orders Updated:</span>
                                                        <span className="ml-1 font-medium text-blue-600">
                                                            {import_.orders_updated}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-gray-500">Success Rate:</span>
                                                        <span className="ml-1 font-medium">
                                                            {import_.success_rate}%
                                                        </span>
                                                    </div>
                                                    {import_.has_errors && (
                                                        <div>
                                                            <span className="text-red-600 font-medium">Has Errors</span>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 
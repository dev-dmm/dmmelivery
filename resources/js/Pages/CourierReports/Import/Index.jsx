import React, { useState, useEffect, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import InputError from '@/Components/InputError';
import {
  DocumentArrowUpIcon, CloudArrowUpIcon, DocumentCheckIcon,
  CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon,
  ArrowPathIcon, TrashIcon, ArrowDownTrayIcon,
  ClockIcon, ChartBarIcon, EyeIcon, XMarkIcon
} from '@heroicons/react/24/outline';
import { Disclosure } from '@headlessui/react';
import { ChevronUpIcon } from '@heroicons/react/20/solid';

export default function CourierReportImportIndex({ auth, recentImports, stats, supportedFormats, maxFileSize }) {
  const [selectedFile, setSelectedFile] = useState(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isUploading, setIsUploading] = useState(false);
  const [activeImports, setActiveImports] = useState([]);
  const [pollingStopped, setPollingStopped] = useState(false);
  const [showDetails, setShowDetails] = useState({});
  const fileInputRef = useRef(null);
  const pollIntervalRef = useRef(null);

  const { data, setData, reset, errors, processing } = useForm({
    file: null,
    notes: ''
  });

  // Poll for active imports status
  useEffect(() => {
    const activeIds = recentImports
      .filter(imp => ['pending', 'processing'].includes(imp.status))
      .map(imp => imp.uuid);

    if (activeIds.length > 0 && !pollingStopped) {
      pollIntervalRef.current = setInterval(() => {
        pollActiveImports(activeIds);
      }, 2000);
    }

    return () => {
      if (pollIntervalRef.current) clearInterval(pollIntervalRef.current);
    };
  }, [recentImports, pollingStopped]);

  const pollActiveImports = async (importUuids) => {
    try {
      const responses = await Promise.all(
        importUuids.map(uuid =>
          fetch(route('courier-reports.import.status', { uuid }))
            .then(res => res.json())
        )
      );

      const updates = responses
        .filter(resp => resp.success !== false)
        .map(resp => resp);

      if (updates.length > 0) {
        setActiveImports(prev => {
          const updated = [...prev];
          updates.forEach(update => {
            const index = updated.findIndex(imp => imp.uuid === update.uuid);
            if (index >= 0) updated[index] = update;
            else updated.push(update);
          });
          return updated;
        });

        const allFinished = updates.every(imp =>
          ['completed', 'failed', 'partial', 'cancelled'].includes(imp.status)
        );
        if (allFinished) setPollingStopped(true);
      }
    } catch (error) {
      console.error('Failed to poll import status:', error);
    }
  };

  const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
      // Check file extension
      const extension = file.name.split('.').pop().toLowerCase();
      if (extension !== 'csv') {
        alert('Please select a CSV file');
        e.target.value = ''; // Clear the input
        return;
      }
      
      setSelectedFile(file);
      setData('file', file);
    }
  };

  const handleDrop = (e) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
      // Check file extension
      const extension = file.name.split('.').pop().toLowerCase();
      if (extension !== 'csv') {
        alert('Please select a CSV file');
        return;
      }
      
      setSelectedFile(file);
      setData('file', file);
    }
  };

  const handleDragOver = (e) => {
    e.preventDefault();
  };

  const handleUpload = async (e) => {
    e.preventDefault();
    
    if (!selectedFile) return;

    setIsUploading(true);
    setUploadProgress(0);

    try {
      const formData = new FormData();
      formData.append('file', selectedFile);
      formData.append('notes', data.notes);

      console.log('Uploading file:', {
        name: selectedFile.name,
        size: selectedFile.size,
        type: selectedFile.type,
        extension: selectedFile.name.split('.').pop(),
        notes: data.notes
      });

      const response = await fetch(route('courier-reports.import.upload'), {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
      });

      const result = await response.json();

      if (result.success) {
        setSelectedFile(null);
        reset();
        setUploadProgress(100);
        
        // Refresh the page to show the new import
        window.location.reload();
      } else {
        console.error('Upload failed:', result);
        let errorMessage = result.message || 'Upload failed';
        
        // Show validation errors if available
        if (result.errors) {
          const errorDetails = Object.values(result.errors).flat().join('\n');
          errorMessage += '\n\nDetails:\n' + errorDetails;
        }
        
        alert(errorMessage);
      }
    } catch (error) {
      console.error('Upload error:', error);
      alert('Upload failed: ' + error.message);
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
    }
  };

  const handleCancelImport = async (uuid) => {
    if (!confirm('Are you sure you want to cancel this import?')) return;

    try {
      const response = await fetch(route('courier-reports.import.cancel', { uuid }), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
      });

      const result = await response.json();
      if (result.success) {
        window.location.reload();
      }
    } catch (error) {
      console.error('Cancel failed:', error);
    }
  };

  const handleDeleteImport = async (uuid) => {
    if (!confirm('Are you sure you want to delete this import? This action cannot be undone.')) return;

    try {
      const response = await fetch(route('courier-reports.import.delete', { uuid }), {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
      });

      const result = await response.json();
      if (result.success) {
        window.location.reload();
      }
    } catch (error) {
      console.error('Delete failed:', error);
    }
  };

  const handleViewDetails = async (uuid) => {
    try {
      const response = await fetch(route('courier-reports.import.details', { uuid }));
      const result = await response.json();
      
      if (result.import) {
        setShowDetails(prev => ({
          ...prev,
          [uuid]: result.import
        }));
      }
    } catch (error) {
      console.error('Failed to load details:', error);
    }
  };

  const getStatusBadge = (status) => {
    const statusConfig = {
      'pending': { color: 'bg-yellow-100 text-yellow-800', icon: '⏳' },
      'processing': { color: 'bg-blue-100 text-blue-800', icon: '⚙️' },
      'completed': { color: 'bg-green-100 text-green-800', icon: '✅' },
      'failed': { color: 'bg-red-100 text-red-800', icon: '❌' },
      'partial': { color: 'bg-orange-100 text-orange-800', icon: '⚠️' },
      'cancelled': { color: 'bg-gray-100 text-gray-800', icon: '🚫' }
    };
    
    const config = statusConfig[status] || statusConfig['pending'];
    return (
      <span className={`inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full ${config.color}`}>
        {config.icon} {status.toUpperCase()}
      </span>
    );
  };

  const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold leading-tight text-gray-800">
            📊 Courier Reports Import
          </h2>
        </div>
      }
    >
      <Head title="Courier Reports Import" />

      <div className="py-12">
        <div className="mx-auto max-w-7xl space-y-6">
          
          {/* Statistics Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <ChartBarIcon className="h-8 w-8 text-blue-600" />
                  </div>
                  <div className="ml-5 w-0 flex-1">
                    <dl>
                      <dt className="text-sm font-medium text-gray-500 truncate">Total Imports</dt>
                      <dd className="text-lg font-medium text-gray-900">{stats.total_imports}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <CheckCircleIcon className="h-8 w-8 text-green-600" />
                  </div>
                  <div className="ml-5 w-0 flex-1">
                    <dl>
                      <dt className="text-sm font-medium text-gray-500 truncate">Completed</dt>
                      <dd className="text-lg font-medium text-gray-900">{stats.completed_imports}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <XCircleIcon className="h-8 w-8 text-red-600" />
                  </div>
                  <div className="ml-5 w-0 flex-1">
                    <dl>
                      <dt className="text-sm font-medium text-gray-500 truncate">Failed</dt>
                      <dd className="text-lg font-medium text-gray-900">{stats.failed_imports}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>

            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div className="p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0">
                    <ClockIcon className="h-8 w-8 text-yellow-600" />
                  </div>
                  <div className="ml-5 w-0 flex-1">
                    <dl>
                      <dt className="text-sm font-medium text-gray-500 truncate">In Progress</dt>
                      <dd className="text-lg font-medium text-gray-900">{stats.pending_imports}</dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Upload Section */}
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Upload Courier Report</h3>
              
              <form onSubmit={handleUpload} className="space-y-6">
                {/* File Upload Area */}
                <div
                  className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors"
                  onDrop={handleDrop}
                  onDragOver={handleDragOver}
                >
                  <DocumentArrowUpIcon className="mx-auto h-12 w-12 text-gray-400" />
                  <div className="mt-4">
                    <label htmlFor="file-upload" className="cursor-pointer">
                      <span className="mt-2 block text-sm font-medium text-gray-900">
                        {selectedFile ? selectedFile.name : 'Drop your CSV file here or click to browse'}
                      </span>
                      <input
                        ref={fileInputRef}
                        id="file-upload"
                        name="file-upload"
                        type="file"
                        className="sr-only"
                        accept=".csv"
                        onChange={handleFileSelect}
                      />
                    </label>
                    <p className="mt-1 text-xs text-gray-500">
                      CSV files up to {formatFileSize(maxFileSize * 1024)}
                    </p>
                  </div>
                </div>

                {errors.file && <InputError message={errors.file} className="mt-2" />}

                {/* Notes */}
                <div>
                  <label htmlFor="notes" className="block text-sm font-medium text-gray-700">
                    Notes (Optional)
                  </label>
                  <textarea
                    id="notes"
                    name="notes"
                    rows={3}
                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                    placeholder="Add any notes about this import..."
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                  />
                </div>

                {/* Upload Button */}
                <div className="flex justify-end">
                  <PrimaryButton
                    type="submit"
                    disabled={!selectedFile || isUploading}
                    className="flex items-center"
                  >
                    {isUploading ? (
                      <>
                        <ArrowPathIcon className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" />
                        Uploading...
                      </>
                    ) : (
                      <>
                        <CloudArrowUpIcon className="-ml-1 mr-3 h-5 w-5 text-white" />
                        Upload & Process
                      </>
                    )}
                  </PrimaryButton>
                </div>
              </form>
            </div>
          </div>

          {/* Recent Imports */}
          <div className="bg-white shadow-sm sm:rounded-lg">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-medium text-gray-900">Recent Imports</h3>
            </div>
            
            {recentImports.length === 0 ? (
              <div className="text-center py-8">
                <DocumentCheckIcon className="mx-auto h-12 w-12 text-gray-400" />
                <h3 className="mt-2 text-sm font-medium text-gray-900">No imports yet</h3>
                <p className="mt-1 text-sm text-gray-500">Upload your first courier report to get started.</p>
              </div>
            ) : (
              <div className="divide-y divide-gray-200">
                {recentImports.map((importItem) => (
                  <div key={importItem.id} className="p-6">
                    <div className="flex items-center justify-between">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center space-x-3">
                          <div className="flex-shrink-0">
                            {getStatusBadge(importItem.status)}
                          </div>
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">
                              {importItem.file_name}
                            </p>
                            <p className="text-sm text-gray-500">
                              {importItem.created_at} • {importItem.total_rows} rows
                            </p>
                          </div>
                        </div>
                        
                        {/* Progress Bar */}
                        {['pending', 'processing'].includes(importItem.status) && (
                          <div className="mt-2">
                            <div className="flex justify-between text-sm text-gray-600 mb-1">
                              <span>Processing...</span>
                              <span>{importItem.progress}%</span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2">
                              <div
                                className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                style={{ width: `${importItem.progress}%` }}
                              ></div>
                            </div>
                          </div>
                        )}

                        {/* Results Summary */}
                        {importItem.status === 'completed' && (
                          <div className="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div className="text-center">
                              <div className="text-lg font-semibold text-green-600">{importItem.matched_rows}</div>
                              <div className="text-gray-500">Matched</div>
                            </div>
                            <div className="text-center">
                              <div className="text-lg font-semibold text-red-600">{importItem.unmatched_rows}</div>
                              <div className="text-gray-500">Unmatched</div>
                            </div>
                            <div className="text-center">
                              <div className="text-lg font-semibold text-orange-600">{importItem.price_mismatch_rows}</div>
                              <div className="text-gray-500">Price Mismatch</div>
                            </div>
                            <div className="text-center">
                              <div className="text-lg font-semibold text-blue-600">{importItem.match_rate}%</div>
                              <div className="text-gray-500">Match Rate</div>
                            </div>
                          </div>
                        )}
                      </div>

                      <div className="flex items-center space-x-2">
                        <button
                          onClick={() => handleViewDetails(importItem.uuid)}
                          className="text-gray-400 hover:text-gray-600"
                          title="View Details"
                        >
                          <EyeIcon className="h-5 w-5" />
                        </button>
                        
                        {['pending', 'processing'].includes(importItem.status) && (
                          <button
                            onClick={() => handleCancelImport(importItem.uuid)}
                            className="text-yellow-600 hover:text-yellow-800"
                            title="Cancel Import"
                          >
                            <XMarkIcon className="h-5 w-5" />
                          </button>
                        )}
                        
                        <button
                          onClick={() => handleDeleteImport(importItem.uuid)}
                          className="text-red-400 hover:text-red-600"
                          title="Delete Import"
                        >
                          <TrashIcon className="h-5 w-5" />
                        </button>
                      </div>
                    </div>

                    {/* Details Panel */}
                    {showDetails[importItem.uuid] && (
                      <div className="mt-4 p-4 bg-gray-50 rounded-lg">
                        <div className="flex justify-between items-start mb-2">
                          <h4 className="text-sm font-medium text-gray-900">Import Details</h4>
                          <button
                            onClick={() => setShowDetails(prev => ({ ...prev, [importItem.uuid]: false }))}
                            className="text-gray-400 hover:text-gray-600"
                          >
                            <XMarkIcon className="h-4 w-4" />
                          </button>
                        </div>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                          <div>
                            <span className="font-medium text-gray-700">File Size:</span>
                            <span className="ml-2 text-gray-600">{formatFileSize(importItem.file_size || 0)}</span>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Processing Time:</span>
                            <span className="ml-2 text-gray-600">{importItem.processing_time || 'N/A'}</span>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Match Rate:</span>
                            <span className="ml-2 text-gray-600">{importItem.match_rate}%</span>
                          </div>
                          <div>
                            <span className="font-medium text-gray-700">Price Match Rate:</span>
                            <span className="ml-2 text-gray-600">{importItem.price_match_rate}%</span>
                          </div>
                        </div>
                        
                        {importItem.errors && importItem.errors.length > 0 && (
                          <div className="mt-3">
                            <h5 className="text-sm font-medium text-red-700 mb-1">Errors:</h5>
                            <div className="text-xs text-red-600 space-y-1">
                              {importItem.errors.slice(0, 3).map((error, index) => (
                                <div key={index}>Row {error.row}: {error.message}</div>
                              ))}
                              {importItem.errors.length > 3 && (
                                <div>... and {importItem.errors.length - 3} more errors</div>
                              )}
                            </div>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Help Section */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 className="text-lg font-medium text-blue-900 mb-2">How it works</h3>
            <div className="text-sm text-blue-800 space-y-2">
              <p>1. Upload a CSV file with courier report data (tracking numbers, prices, dates, customer info)</p>
              <p>2. The system will automatically match tracking numbers with your existing orders and shipments</p>
              <p>3. Compare prices between the courier report and your order totals</p>
              <p>4. View detailed results showing matches, mismatches, and price discrepancies</p>
            </div>
            <div className="mt-4">
              <a
                href={route('courier-reports.import.template', { format: 'csv' })}
                className="inline-flex items-center text-sm text-blue-600 hover:text-blue-800"
              >
                <ArrowDownTrayIcon className="h-4 w-4 mr-1" />
                Download CSV Template
              </a>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

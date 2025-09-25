import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

export default function CourierApi({ sampleShipments = [], courierInfo = null }) {
    const [testResult, setTestResult] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        tracking_number: '',
    });


    const handleSubmit = (e) => {
        e.preventDefault();
        setIsLoading(true);
        setTestResult(null);

        // Make API call to test endpoint (no CSRF needed for API routes)
        fetch('/api/test/courier-api', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(async (response) => {
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || result.error || `HTTP ${response.status}: ${response.statusText}`);
            }
            
            return result;
        })
        .then(result => {
            setTestResult(result);
            setIsLoading(false);
        })
        .catch(error => {
            console.error('Error:', error);
            setTestResult({
                error: error.message || 'Network error occurred'
            });
            setIsLoading(false);
        });
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString('el-GR');
    };

    const getStatusBadgeColor = (status) => {
        const colors = {
            pending: 'bg-yellow-100 text-yellow-800',
            picked_up: 'bg-blue-100 text-blue-800',
            in_transit: 'bg-indigo-100 text-indigo-800',
            out_for_delivery: 'bg-purple-100 text-purple-800',
            delivered: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
            returned: 'bg-gray-100 text-gray-800',
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    return (
        <AuthenticatedLayout>
            <Head title="Courier API Test" />

            <div className="py-6 space-y-8">
                {/* Header */}
                <div className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg p-6">
                    <h1 className="text-3xl font-bold">🧪 Courier API Integration Test</h1>
                    <p className="text-blue-100 mt-2">
                        Test real-time courier API integration and status fetching
                    </p>
                </div>

                {/* Active Courier & Credentials Info */}
                {courierInfo && (
                    <div className="bg-white rounded-lg shadow-sm border">
                        <div className="p-6 border-b border-gray-200">
                            <h2 className="text-xl font-semibold">🔧 Active Courier Configuration</h2>
                            <p className="text-sm text-gray-600 mt-1">
                                Current courier setup and credentials for testing
                            </p>
                        </div>
                        
                        <div className="p-6 space-y-6">
                            {/* Active Couriers */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-3">🚚 Active Couriers</h3>
                                {courierInfo.active_couriers && courierInfo.active_couriers.length > 0 ? (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {courierInfo.active_couriers.map((courier) => (
                                            <div key={courier.id} className="bg-gray-50 rounded-lg p-4 border">
                                                <div className="flex items-center justify-between mb-2">
                                                    <h4 className="font-medium text-gray-900">{courier.name}</h4>
                                                    <div className="flex space-x-2">
                                                        {courier.is_default && (
                                                            <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                                Default
                                                            </span>
                                                        )}
                                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                            courier.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                                        }`}>
                                                            {courier.is_active ? 'Active' : 'Inactive'}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="space-y-1 text-sm text-gray-600">
                                                    <div className="flex justify-between">
                                                        <span>Code:</span>
                                                        <span className="font-mono">{courier.code}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span>API Endpoint:</span>
                                                        <span className={courier.has_api_endpoint ? 'text-green-600' : 'text-red-600'}>
                                                            {courier.has_api_endpoint ? '✓ Configured' : '✗ Not configured'}
                                                        </span>
                                                    </div>
                                                    {courier.api_endpoint && (
                                                        <div className="text-xs text-gray-500 truncate" title={courier.api_endpoint}>
                                                            {courier.api_endpoint}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div className="flex">
                                            <div className="flex-shrink-0">
                                                <span className="text-yellow-400 text-xl">⚠️</span>
                                            </div>
                                            <div className="ml-3">
                                                <h3 className="text-sm font-medium text-yellow-800">
                                                    No Active Couriers
                                                </h3>
                                                <p className="text-sm text-yellow-700 mt-1">
                                                    You need to configure at least one active courier to test API integration.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Credentials Status */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-3">🔑 API Credentials Status</h3>
                                <div className="bg-gray-50 rounded-lg p-4">
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium text-gray-700">ACS Credentials:</span>
                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                courierInfo.credentials.has_acs_credentials 
                                                    ? 'bg-green-100 text-green-800' 
                                                    : 'bg-red-100 text-red-800'
                                            }`}>
                                                {courierInfo.credentials.has_acs_credentials ? '✓ Configured' : '✗ Not configured'}
                                            </span>
                                        </div>
                                        
                                        {courierInfo.credentials.has_acs_credentials && (
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">Company ID:</span>
                                                    <span className="font-mono text-blue-600">{courierInfo.credentials.acs_company_id}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">User ID:</span>
                                                    <span className="font-mono text-blue-600">{courierInfo.credentials.acs_user_id}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">API Key:</span>
                                                    <span className="font-mono text-gray-500">{courierInfo.credentials.acs_api_key_masked || 'Not set'}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">Company Password:</span>
                                                    <span className="font-mono text-gray-500">{courierInfo.credentials.acs_company_password_masked || 'Not set'}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">User Password:</span>
                                                    <span className="font-mono text-gray-500">{courierInfo.credentials.acs_user_password_masked || 'Not set'}</span>
                                                </div>
                                            </div>
                                        )}
                                        
                                        {!courierInfo.credentials.has_acs_credentials && (
                                            <div className="bg-amber-50 border border-amber-200 rounded p-3">
                                                <p className="text-sm text-amber-800">
                                                    <strong>Note:</strong> ACS credentials are not configured. 
                                                    The test will use demo credentials ('demo' for all fields) which won't return real tracking data.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Test Form */}
                <div className="bg-white rounded-lg shadow-sm border p-6">
                    <h2 className="text-xl font-semibold mb-4">📦 Test Shipment Tracking</h2>
                    
                    {/* Test Configuration Info */}
                    {courierInfo && (
                        <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <h3 className="text-sm font-medium text-blue-900 mb-2">🔧 Test Configuration</h3>
                            <div className="text-sm text-blue-800 space-y-1">
                                <p>
                                    <strong>Active Couriers:</strong> {courierInfo.active_couriers?.length || 0} configured
                                    {courierInfo.active_couriers?.length > 0 && (
                                        <span className="ml-2">
                                            ({courierInfo.active_couriers.map(c => c.name).join(', ')})
                                        </span>
                                    )}
                                </p>
                                <p>
                                    <strong>Credentials:</strong> {courierInfo.credentials.has_acs_credentials ? 'Real ACS credentials' : 'Demo credentials (demo/demo/demo/demo)'}
                                </p>
                                <p>
                                    <strong>Test Mode:</strong> {courierInfo.credentials.has_acs_credentials ? 'Live API calls' : 'Demo mode (no real data)'}
                                </p>
                            </div>
                        </div>
                    )}
                    
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <InputLabel htmlFor="tracking_number" value="Tracking Number" />
                            <TextInput
                                id="tracking_number"
                                type="text"
                                className="mt-1 block w-full"
                                placeholder="e.g. UH123456, 7227889174"
                                value={data.tracking_number}
                                onChange={(e) => setData('tracking_number', e.target.value)}
                                required
                                disabled={isLoading}
                            />
                            <InputError message={errors.tracking_number} className="mt-2" />
                            <p className="text-sm text-gray-500 mt-1">
                                Enter a tracking number from your database to test the API integration
                            </p>
                        </div>

                        <div className="flex space-x-4">
                            <PrimaryButton disabled={isLoading || !data.tracking_number.trim()}>
                                {isLoading ? (
                                    <>
                                        <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Testing API...
                                    </>
                                ) : (
                                    '🚀 Test API Integration'
                                )}
                            </PrimaryButton>

                            {testResult && (
                                <SecondaryButton 
                                    type="button" 
                                    onClick={() => setTestResult(null)}
                                >
                                    Clear Results
                                </SecondaryButton>
                            )}
                        </div>
                    </form>
                </div>

                {/* Sample Tracking Numbers */}
                {sampleShipments && sampleShipments.length > 0 && (
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h2 className="text-xl font-semibold mb-4">📋 Sample Tracking Numbers (Click to Test)</h2>
                        <p className="text-sm text-gray-600 mb-4">
                            These are existing shipments in your database. Click any tracking number to test the API integration:
                        </p>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {sampleShipments.map((shipment) => (
                                <div 
                                    key={shipment.id}
                                    onClick={() => setData('tracking_number', shipment.tracking_number)}
                                    className="cursor-pointer p-3 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-colors"
                                >
                                    <div className="font-mono text-sm font-medium text-blue-600">
                                        {shipment.tracking_number}
                                    </div>
                                    <div className="text-xs text-gray-500 mt-1">
                                        {shipment.courier?.name || 'Unknown'} ({shipment.courier?.code || 'N/A'})
                                    </div>
                                    <div className="mt-2">
                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(shipment.status)}`}>
                                            {shipment.status.replace('_', ' ').toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Test Results */}
                {testResult && (
                    <div className="bg-white rounded-lg shadow-sm border">
                        <div className="p-6 border-b border-gray-200">
                            <h3 className="text-lg font-semibold">📊 Test Results</h3>
                        </div>

                        <div className="p-6">
                            {testResult.error ? (
                                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <span className="text-red-400 text-xl">❌</span>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-sm font-medium text-red-800">
                                                API Test Failed
                                            </h3>
                                            <p className="text-sm text-red-700 mt-2">
                                                {testResult.error}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ) : testResult.success ? (
                                <div className="space-y-6">
                                    {/* Success Message */}
                                    <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <div className="flex">
                                            <div className="flex-shrink-0">
                                                <span className="text-green-400 text-xl">✅</span>
                                            </div>
                                            <div className="ml-3">
                                                <h3 className="text-sm font-medium text-green-800">
                                                    API Integration Successful!
                                                </h3>
                                                <p className="text-sm text-green-700 mt-2">
                                                    {testResult.message}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Shipment Info */}
                                    {testResult.shipment && (
                                        <>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div className="bg-gray-50 rounded-lg p-4">
                                                    <h4 className="font-medium text-gray-900 mb-3">📦 Shipment Details</h4>
                                                    <div className="space-y-2 text-sm">
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Tracking Number:</span>
                                                            <span className="font-mono">{testResult.shipment.tracking_number}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Courier ID:</span>
                                                            <span className="font-mono">{testResult.shipment.courier_tracking_id || '-'}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Status:</span>
                                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(testResult.shipment.status)}`}>
                                                                {testResult.shipment.status.replace('_', ' ').toUpperCase()}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Created:</span>
                                                            <span>{formatDate(testResult.shipment.created_at)}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Updated:</span>
                                                            <span>{formatDate(testResult.shipment.updated_at)}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="bg-gray-50 rounded-lg p-4">
                                                    <h4 className="font-medium text-gray-900 mb-3">🚚 Courier Details</h4>
                                                    <div className="space-y-2 text-sm">
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Company:</span>
                                                            <span>{testResult.shipment.courier.name}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Code:</span>
                                                            <span className="font-mono">{testResult.shipment.courier.code}</span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">API Endpoint:</span>
                                                            <span className="text-xs text-blue-600 truncate" title={testResult.shipment.courier.api_endpoint}>
                                                                {testResult.shipment.courier.api_endpoint ? 'Configured ✓' : 'Not configured ✗'}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-gray-600">Active:</span>
                                                            <span className={testResult.shipment.courier.is_active ? 'text-green-600' : 'text-red-600'}>
                                                                {testResult.shipment.courier.is_active ? 'Yes ✓' : 'No ✗'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Status History */}
                                            {testResult.shipment.status_history && testResult.shipment.status_history.length > 0 && (
                                                <div>
                                                    <h4 className="font-medium text-gray-900 mb-4">📋 Recent Status History</h4>
                                                    <div className="bg-gray-50 rounded-lg p-4">
                                                        <div className="space-y-3">
                                                            {testResult.shipment.status_history.slice(0, 5).map((history, index) => (
                                                                <div key={history.id} className="flex items-start space-x-3 p-3 bg-white rounded border-l-4 border-blue-200">
                                                                    <div className="flex-shrink-0 w-2 h-2 bg-blue-400 rounded-full mt-2"></div>
                                                                    <div className="flex-1 min-w-0">
                                                                        <div className="flex items-center justify-between">
                                                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(history.status)}`}>
                                                                                {history.status.replace('_', ' ').toUpperCase()}
                                                                            </span>
                                                                            <span className="text-xs text-gray-500">
                                                                                {formatDate(history.happened_at)}
                                                                            </span>
                                                                        </div>
                                                                        <p className="text-sm text-gray-900 mt-1">
                                                                            {history.description}
                                                                        </p>
                                                                        {history.location && (
                                                                            <p className="text-xs text-gray-500 mt-1">
                                                                                📍 {history.location}
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            ) : null}
                        </div>
                    </div>
                )}

                {/* Instructions */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 className="text-lg font-medium text-blue-900 mb-3">💡 How to use this test</h3>
                    <ol className="list-decimal list-inside space-y-2 text-sm text-blue-800">
                        <li><strong>Review the courier configuration above</strong> to see which couriers are active and what credentials are configured</li>
                        <li><strong>Click any tracking number above</strong> to auto-fill the input field</li>
                        <li>Or manually enter a tracking number from your database</li>
                        <li>Click "🚀 Test API Integration" to fetch real-time status from the courier API</li>
                        <li>Check the logs at <code className="bg-blue-100 px-1 rounded">storage/logs/laravel.log</code> for detailed API responses</li>
                        <li>Status history will show both existing and newly fetched tracking events</li>
                        <li>The shipment status will be automatically updated based on the latest courier response</li>
                    </ol>
                    
                    {courierInfo && !courierInfo.credentials.has_acs_credentials && (
                        <div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded">
                            <p className="text-sm text-amber-800">
                                <strong>📝 Note:</strong> Using demo ACS credentials ('demo' for all fields). 
                                These won't return real tracking data but will test your API integration logic.
                                For real data, configure your actual ACS credentials in the Settings page.
                            </p>
                        </div>
                    )}
                    
                    {courierInfo && courierInfo.credentials.has_acs_credentials && (
                        <div className="mt-4 p-3 bg-green-50 border border-green-200 rounded">
                            <p className="text-sm text-green-800">
                                <strong>✅ Ready for Live Testing:</strong> Your ACS credentials are configured. 
                                This test will make real API calls to fetch actual tracking data.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
} 
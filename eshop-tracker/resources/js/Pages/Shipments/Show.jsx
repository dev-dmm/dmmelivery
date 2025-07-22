import { Link, Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ shipment }) {
  const getStatusBadgeColor = (status) => {
    const colors = {
      pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      picked_up: 'bg-blue-100 text-blue-800 border-blue-200',
      in_transit: 'bg-indigo-100 text-indigo-800 border-indigo-200',
      out_for_delivery: 'bg-purple-100 text-purple-800 border-purple-200',
      delivered: 'bg-green-100 text-green-800 border-green-200',
      failed: 'bg-red-100 text-red-800 border-red-200',
      returned: 'bg-gray-100 text-gray-800 border-gray-200',
      cancelled: 'bg-red-100 text-red-800 border-red-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800 border-gray-200';
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString('el-GR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getStatusIcon = (status) => {
    const icons = {
      pending: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
      picked_up: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
      in_transit: "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
      out_for_delivery: "M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l1.68 2.36a2 2 0 001.63.64H19M7 13v4a2 2 0 002 2h2m3-2v4m-6-6h.01",
      delivered: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
      failed: "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z",
      returned: "M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6",
    };
    return icons[status] || icons.pending;
  };

  return (
    <AuthenticatedLayout>
        <Head title={`Tracking ${shipment.tracking_number}`} />
        
        <div className="py-6 space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <Link 
                        href={route('shipments.index')} 
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2"
                    >
                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Shipments
                    </Link>
                    <h1 className="text-3xl font-bold text-gray-800">
                        📦 Tracking #{shipment.tracking_number}
                    </h1>
                    <p className="text-gray-600">Order: {shipment.order_id}</p>
                </div>
                <div className="text-right">
                    <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full border ${getStatusBadgeColor(shipment.status)}`}>
                        {shipment.status.replace('_', ' ').toUpperCase()}
                    </span>
                    <p className="text-sm text-gray-500 mt-1">
                        Last updated: {formatDate(shipment.updated_at)}
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Information */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Shipment Details Card */}
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h3 className="text-lg font-semibold mb-4">📋 Shipment Details</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500">Tracking Number</label>
                                <p className="text-lg font-mono">{shipment.tracking_number}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Courier Tracking ID</label>
                                <p className="text-lg font-mono">{shipment.courier_tracking_id || '-'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Weight</label>
                                <p>{shipment.weight ? `${shipment.weight} kg` : '-'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Shipping Cost</label>
                                <p>{shipment.shipping_cost ? `€${shipment.shipping_cost}` : '-'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Estimated Delivery</label>
                                <p>{formatDate(shipment.estimated_delivery)}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Actual Delivery</label>
                                <p className={shipment.actual_delivery ? 'text-green-600 font-medium' : 'text-gray-400'}>
                                    {shipment.actual_delivery ? formatDate(shipment.actual_delivery) : 'Pending'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Status History Timeline */}
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h3 className="text-lg font-semibold mb-4">📈 Tracking Timeline</h3>
                        {shipment.status_history && shipment.status_history.length > 0 ? (
                            <div className="space-y-4">
                                {shipment.status_history.map((historyItem, index) => (
                                    <div key={index} className="flex items-start space-x-3">
                                        <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
                                            index === 0 ? 'bg-blue-100' : 'bg-gray-100'
                                        }`}>
                                            <svg className={`w-4 h-4 ${index === 0 ? 'text-blue-600' : 'text-gray-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={getStatusIcon(historyItem.status)} />
                                            </svg>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between">
                                                <p className="text-sm font-medium text-gray-900">
                                                    {historyItem.status.replace('_', ' ').toUpperCase()}
                                                </p>
                                                <p className="text-sm text-gray-500">
                                                    {formatDate(historyItem.happened_at || historyItem.at)}
                                                </p>
                                            </div>
                                            <p className="text-sm text-gray-600">
                                                {historyItem.description || `Package ${historyItem.status.replace('_', ' ')}`}
                                            </p>
                                            {historyItem.location && (
                                                <p className="text-xs text-gray-500 mt-1">
                                                    📍 {historyItem.location}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <svg className="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p>No tracking history available yet</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Customer Information */}
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h3 className="text-lg font-semibold mb-4">👤 Customer</h3>
                        <div className="space-y-3">
                            <div>
                                <label className="text-sm font-medium text-gray-500">Name</label>
                                <p className="font-medium">{shipment?.customer?.name || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Email</label>
                                <p className="text-sm">{shipment?.customer?.email || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Phone</label>
                                <p className="text-sm">{shipment?.customer?.phone || 'N/A'}</p>
                            </div>
                        </div>
                    </div>

                    {/* Courier Information */}
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h3 className="text-lg font-semibold mb-4">🚚 Courier</h3>
                        <div className="space-y-3">
                            <div>
                                <label className="text-sm font-medium text-gray-500">Company</label>
                                <p className="font-medium">{shipment?.courier?.name || 'N/A'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-500">Code</label>
                                <p className="text-sm font-mono">{shipment?.courier?.code || 'N/A'}</p>
                            </div>
                            {shipment?.courier?.tracking_url_template && (
                                <div>
                                    <a 
                                        href={shipment.courier.tracking_url_template.replace('{tracking_number}', shipment.tracking_number)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm"
                                    >
                                        <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                        Track on courier site
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Addresses */}
                    <div className="bg-white rounded-lg shadow-sm border p-6">
                        <h3 className="text-lg font-semibold mb-4">📍 Addresses</h3>
                        <div className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-gray-500">Shipping Address</label>
                                <p className="text-sm whitespace-pre-line">{shipment.shipping_address || 'N/A'}</p>
                            </div>
                            {shipment.billing_address && (
                                <div>
                                    <label className="text-sm font-medium text-gray-500">Billing Address</label>
                                    <p className="text-sm whitespace-pre-line">{shipment.billing_address}</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
  );
}
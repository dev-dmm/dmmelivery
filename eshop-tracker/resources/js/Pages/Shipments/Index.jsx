import { useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ shipments = [], filters = {} }) {
  const { data, setData, get, processing } = useForm({
    'filter[tracking_number]': filters['filter.tracking_number'] || '',
    'filter[status]': filters['filter.status'] || '',
    'filter[courier]': filters['filter.courier'] || '',
    'filter[customer]': filters['filter.customer'] || '',
  });

  const submit = (e) => {
    e.preventDefault();
    
    // Clean empty filters
    const cleanFilters = {};
    Object.keys(data).forEach(key => {
      if (data[key] && data[key].trim() !== '') {
        cleanFilters[key] = data[key];
      }
    });
    
    get(route('shipments.index'), {
      data: cleanFilters,
      preserveState: true,
      preserveScroll: true,
    });
  };

  const clearFilters = () => {
    get(route('shipments.index'), {
      preserveState: true,
    });
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
      cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('el-GR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const hasActiveFilters = Object.values(data).some(value => value && value.trim() !== '');

  return (
    <AuthenticatedLayout>
        <Head title="Shipments Dashboard" />
        <div className="py-6 space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-gray-800">📦 Shipments Dashboard</h1>
                    <p className="text-sm text-gray-500 mt-1">Track and manage all your eShop deliveries in real time.</p>
                </div>
                <div className="text-sm text-gray-500">
                    {hasActiveFilters && (
                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 mr-2">
                            <svg className="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clipRule="evenodd" />
                            </svg>
                            Filtered
                        </span>
                    )}
                    Total: <span className="font-semibold">{shipments?.total || 0}</span> shipments
                </div>
            </div>

            {/* Advanced Search Form */}
            <form onSubmit={submit} className="bg-white p-6 rounded-lg shadow-sm border">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-medium text-gray-900">🔍 Search & Filter</h3>
                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="text-sm text-gray-500 hover:text-gray-700 underline"
                        >
                            Clear all filters
                        </button>
                    )}
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                        <input
                            type="text"
                            value={data['filter[tracking_number]']}
                            onChange={(e) => setData('filter[tracking_number]', e.target.value)}
                            placeholder="e.g. UH434846"
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        />
                    </div>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select
                            value={data['filter[status]']}
                            onChange={(e) => setData('filter[status]', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="picked_up">Picked Up</option>
                            <option value="in_transit">In Transit</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="failed">Failed</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Courier</label>
                        <input
                            type="text"
                            value={data['filter[courier]']}
                            onChange={(e) => setData('filter[courier]', e.target.value)}
                            placeholder="ACS, ELTA, Speedex..."
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        />
                    </div>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <input
                            type="text"
                            value={data['filter[customer]']}
                            onChange={(e) => setData('filter[customer]', e.target.value)}
                            placeholder="Name or email..."
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                        />
                    </div>
                </div>
                
                <div className="flex items-center justify-end space-x-3">
                    <button 
                        type="submit" 
                        disabled={processing}
                        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                    >
                        {processing ? (
                            <>
                                <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Searching...
                            </>
                        ) : (
                            <>
                                <svg className="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Search
                            </>
                        )}
                    </button>
                </div>
            </form>

            {/* Results Table */}
            <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-200">
                    <h3 className="text-lg font-medium text-gray-900">
                        Search Results {shipments.data && `(${shipments.data.length} of ${shipments.total})`}
                    </h3>
                </div>
                
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ETA</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courier</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {shipments.data && shipments.data.length > 0 ? (
                                shipments.data.map((shipment) => (
                                    <tr key={shipment.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{shipment.tracking_number}</div>
                                            <div className="text-sm text-gray-500">{shipment.order_id}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(shipment.status)}`}>
                                                {shipment.status.replace('_', ' ').toUpperCase()}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{shipment?.customer?.name || '-'}</div>
                                            <div className="text-sm text-gray-500">{shipment?.customer?.email || ''}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {formatDate(shipment.estimated_delivery)}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">{shipment?.courier?.name || '-'}</div>
                                            <div className="text-sm text-gray-500">{shipment?.courier?.code || ''}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link 
                                                href={route('shipments.show', shipment.id)}
                                                className="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                            >
                                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                View Details
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td className="px-6 py-12 text-center text-gray-500" colSpan="6">
                                        <div className="flex flex-col items-center">
                                            <svg className="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                            <p className="text-lg font-medium">No shipments found</p>
                                            <p className="text-sm">Try adjusting your search criteria or clear filters</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {shipments.links && (
                    <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center">
                                <p className="text-sm text-gray-700">
                                    Showing <span className="font-medium">{shipments.from || 0}</span> to{' '}
                                    <span className="font-medium">{shipments.to || 0}</span> of{' '}
                                    <span className="font-medium">{shipments.total || 0}</span> results
                                </p>
                            </div>
                            <div className="flex items-center space-x-2">
                                {shipments.links.map((link, index) => (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`px-3 py-1 text-sm rounded-md ${
                                            link.active
                                                ? 'bg-blue-500 text-white'
                                                : link.url
                                                ? 'bg-white text-gray-500 hover:text-gray-700 border'
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    </AuthenticatedLayout>
  );
}

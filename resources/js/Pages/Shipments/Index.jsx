import { useForm, Link, Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { route } from 'ziggy-js';

export default function Index({
  shipments = { data: [], links: [], total: 0, from: 0, to: 0 },
  filters = {},
}) {
  const { data, setData, get, processing } = useForm({
    'filter[tracking_number]': filters['filter.tracking_number'] || '',
    'filter[status]':          filters['filter.status'] || '',
    'filter[courier]':         filters['filter.courier'] || '',
    'filter[customer]':        filters['filter.customer'] || '',
  });

  const submit = (e) => {
    e.preventDefault();
    const cleanFilters = {};
    Object.keys(data).forEach((k) => {
      if (data[k] && data[k].trim() !== '') cleanFilters[k] = data[k];
    });

    get(route('shipments.index'), {
      data: cleanFilters,
      preserveState: true,
      preserveScroll: true,
    });
  };

  const clearFilters = () => {
    get(route('shipments.index'), { preserveState: true });
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
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  };

  const formatStatus = (status) =>
    typeof status === 'string' ? status.replaceAll('_', ' ').toUpperCase() : '—';

  const hasActiveFilters = Object.values(data).some((v) => v && v.trim() !== '');

  return (
    <AuthenticatedLayout>
      <Head title="Shipments Dashboard" />

      <div className="py-4 lg:py-6 space-y-4 lg:space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div className="flex-1 min-w-0">
            <h1 className="text-2xl lg:text-3xl font-bold text-gray-800">📦 Shipments Dashboard</h1>
            <p className="text-xs lg:text-sm text-gray-500 mt-1 hidden sm:block">Track and manage all your eShop deliveries in real time.</p>
          </div>
          <div className="text-xs lg:text-sm text-gray-500 flex-shrink-0">
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

        {/* Filters */}
        <form onSubmit={submit} className="bg-white p-4 lg:p-6 rounded-lg shadow-sm border">
          <div className="flex items-center justify-between mb-3 lg:mb-4">
            <h3 className="text-base lg:text-lg font-medium text-gray-900">🔍 Search & Filter</h3>
            {hasActiveFilters && (
              <button type="button" onClick={clearFilters} className="text-xs lg:text-sm text-gray-500 hover:text-gray-700 underline">
                Clear all filters
              </button>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-3 lg:mb-4">
            <div>
              <label className="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
              <input
                type="text"
                value={data['filter[tracking_number]']}
                onChange={(e) => setData('filter[tracking_number]', e.target.value)}
                placeholder="e.g. UH434846"
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs lg:text-sm"
              />
            </div>

            <div>
              <label className="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Status</label>
              <select
                value={data['filter[status]']}
                onChange={(e) => setData('filter[status]', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs lg:text-sm"
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
              <label className="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Courier</label>
              <input
                type="text"
                value={data['filter[courier]']}
                onChange={(e) => setData('filter[courier]', e.target.value)}
                placeholder="ACS, ELTA, Speedex..."
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs lg:text-sm"
              />
            </div>

            <div>
              <label className="block text-xs lg:text-sm font-medium text-gray-700 mb-1">Customer</label>
              <input
                type="text"
                value={data['filter[customer]']}
                onChange={(e) => setData('filter[customer]', e.target.value)}
                placeholder="Name or email..."
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs lg:text-sm"
              />
            </div>
          </div>

          <div className="flex items-center justify-end space-x-2 lg:space-x-3">
            <button
              type="submit"
              disabled={processing}
              className="inline-flex items-center px-3 lg:px-4 py-2 border border-transparent text-xs lg:text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50"
            >
              {processing ? (
                <>
                  <svg className="animate-spin -ml-1 mr-1 lg:mr-2 h-3 lg:h-4 w-3 lg:w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Searching...
                </>
              ) : (
                <>
                  <svg className="-ml-1 mr-1 lg:mr-2 h-3 lg:h-4 w-3 lg:w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  Search
                </>
              )}
            </button>
          </div>
        </form>

        {/* Table */}
        <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
          <div className="px-4 lg:px-6 py-3 lg:py-4 border-b border-gray-200">
            <h3 className="text-base lg:text-lg font-medium text-gray-900">
              Search Results {shipments.data && `(${shipments.data.length} of ${shipments.total})`}
            </h3>
          </div>

          {/* Desktop Table View */}
          <div className="hidden lg:block overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking</th>
                  <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                  <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ETA</th>
                  <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courier</th>
                  <th className="px-4 lg:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {shipments.data && shipments.data.length > 0 ? (
                  shipments.data.map((shipment) => {
                    // Extract the actual shipment data from the data property
                    const shipmentData = shipment?.data || shipment;
                    return (
                    <tr key={shipmentData.id ?? `row-${Math.random()}`} className="hover:bg-gray-50">
                      <td className="px-4 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                        <div className="text-xs lg:text-sm font-medium text-gray-900">{shipmentData.tracking_number || 'No tracking number'}</div>
                        <div className="text-xs text-gray-500">{shipmentData.order_id || 'No order ID'}</div>
                      </td>
                      <td className="px-4 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(shipmentData.status)}`}>
                          {formatStatus(shipmentData.status)}
                        </span>
                      </td>
                      <td className="px-4 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                        <div className="text-xs lg:text-sm font-medium text-gray-900 truncate">{shipmentData?.customer?.name || '-'}</div>
                        <div className="text-xs text-gray-500 truncate">{shipmentData?.customer?.email || ''}</div>
                      </td>
                      <td className="px-4 lg:px-6 py-3 lg:py-4 whitespace-nowrap text-xs lg:text-sm text-gray-900">
                        {formatDate(shipmentData.estimated_delivery)}
                      </td>
                      <td className="px-4 lg:px-6 py-3 lg:py-4 whitespace-nowrap">
                        <div className="text-xs lg:text-sm font-medium text-gray-900 truncate">{shipmentData?.courier?.name || '-'}</div>
                        <div className="text-xs text-gray-500">{shipmentData?.courier?.code || ''}</div>
                      </td>
                      <td className="px-4 lg:px-6 py-3 lg:py-4 whitespace-nowrap text-xs lg:text-sm font-medium">
                        {shipmentData?.id ? (
                          <Link
                            href={route('shipments.show', shipmentData.id)}
                            className="inline-flex items-center px-2 lg:px-3 py-1 border border-transparent text-xs lg:text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200"
                          >
                            <svg className="w-3 lg:w-4 h-3 lg:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span className="hidden lg:inline">View Details</span>
                            <span className="lg:hidden">View</span>
                          </Link>
                        ) : (
                          <span className="text-gray-400">—</span>
                        )}
                      </td>
                    </tr>
                    );
                  })
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

          {/* Mobile Card View */}
          <div className="lg:hidden">
            {shipments.data && shipments.data.length > 0 ? (
              <div className="divide-y divide-gray-200">
                {shipments.data.map((shipment) => {
                  // Extract the actual shipment data from the data property
                  const shipmentData = shipment?.data || shipment;
                  return (
                  <div key={shipmentData.id ?? `row-${Math.random()}`} className="p-4 hover:bg-gray-50">
                    <div className="flex items-start justify-between mb-3">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center mb-2">
                          <div className="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                            <span className="text-xs font-mono">{shipmentData.tracking_number?.slice(-4) || 'N/A'}</span>
                          </div>
                          <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-gray-900 truncate">{shipmentData.tracking_number || 'No tracking number'}</p>
                            <p className="text-xs text-gray-500">{shipmentData.order_id || 'No order ID'}</p>
                          </div>
                        </div>
                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeColor(shipmentData.status)}`}>
                          {formatStatus(shipmentData.status)}
                        </span>
                      </div>
                      {shipmentData?.id && (
                        <Link
                          href={route('shipments.show', shipmentData.id)}
                          className="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 flex-shrink-0"
                        >
                          <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                          View
                        </Link>
                      )}
                    </div>
                    
                    <div className="grid grid-cols-2 gap-3 text-sm">
                      <div>
                        <p className="text-xs text-gray-500 mb-1">Customer</p>
                        <p className="font-medium text-gray-900 truncate">{shipmentData?.customer?.name || '-'}</p>
                        <p className="text-xs text-gray-500 truncate">{shipmentData?.customer?.email || ''}</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500 mb-1">Courier</p>
                        <p className="font-medium text-gray-900 truncate">{shipmentData?.courier?.name || '-'}</p>
                        <p className="text-xs text-gray-500">{shipmentData?.courier?.code || ''}</p>
                      </div>
                    </div>
                    
                    <div className="mt-3 pt-3 border-t border-gray-100">
                      <p className="text-xs text-gray-500 mb-1">ETA</p>
                      <p className="text-sm text-gray-900">{formatDate(shipmentData.estimated_delivery)}</p>
                    </div>
                  </div>
                  );
                })}
              </div>
            ) : (
              <div className="px-6 py-12 text-center text-gray-500">
                <div className="flex flex-col items-center">
                  <svg className="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <p className="text-lg font-medium">No shipments found</p>
                  <p className="text-sm">Try adjusting your search criteria or clear filters</p>
                </div>
              </div>
            )}
          </div>

          {/* Pagination */}
          {Array.isArray(shipments.links) && shipments.links.length > 0 && (
            <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center">
                  <p className="text-sm text-gray-700">
                    Showing <span className="font-medium">{shipments.from || 0}</span> to{' '}
                    <span className="font-medium">{shipments.to || 0}</span> of{' '}
                    <span className="font-medium">{shipments.total || 0}</span> results
                  </p>
                </div>
                <div className="flex items-center justify-center sm:justify-end">
                  <div className="flex items-center space-x-1 sm:space-x-2 overflow-x-auto">
                    {shipments.links.map((link, index) => (
                      <Link
                        key={index}
                        href={link.url || '#'}
                        className={`px-2 sm:px-3 py-1 text-xs sm:text-sm rounded-md whitespace-nowrap flex-shrink-0 ${
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
            </div>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

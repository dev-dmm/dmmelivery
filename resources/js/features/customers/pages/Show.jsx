import { Link, Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeliveryScoreBadge from '@/Components/DeliveryScoreBadge';

export default function Show({ customer, stats, globalStats, recentShipments, scoreHistory }) {
  const customerData = customer?.data || customer;

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

  const formatStatus = (status) =>
    typeof status === 'string' ? status.replaceAll('_', ' ').toUpperCase() : '—';

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

  return (
    <AuthenticatedLayout>
      <Head title={`Πελάτης: ${customerData?.name || 'N/A'}`} />

      <div className="py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <Link href={route('shipments.index')} className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
              <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
              Επιστροφή στις Αποστολές
            </Link>
            <h1 className="text-3xl font-bold text-gray-800">👤 Πελάτης: {customerData?.name || 'N/A'}</h1>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Content */}
          <div className="lg:col-span-2 space-y-6">
            {/* Customer Information Card */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">📋 Πληροφορίες Πελάτη</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium text-gray-500">Όνομα</label>
                  <p className="font-medium">{customerData?.name || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Email</label>
                  <p className="text-sm">{customerData?.email || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Τηλέφωνο</label>
                  <p className="text-sm">{customerData?.phone || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Πόλη</label>
                  <p className="text-sm">{customerData?.city || 'N/A'}</p>
                </div>
                {customerData?.address && (
                  <div className="md:col-span-2">
                    <label className="text-sm font-medium text-gray-500">Διεύθυνση</label>
                    <p className="text-sm">{customerData.address}</p>
                  </div>
                )}
                {customerData?.notes && (
                  <div className="md:col-span-2">
                    <label className="text-sm font-medium text-gray-500">Σημειώσεις</label>
                    <p className="text-sm text-gray-600">{customerData.notes}</p>
                  </div>
                )}
              </div>
            </div>

            {/* Statistics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="bg-white rounded-lg shadow-sm border p-6">
                <h4 className="text-sm font-medium text-gray-500 mb-2">Συνολικές Αποστολές</h4>
                <p className="text-3xl font-bold text-gray-900">{stats.total_shipments}</p>
              </div>
              <div className="bg-white rounded-lg shadow-sm border p-6">
                <h4 className="text-sm font-medium text-gray-500 mb-2">Ολοκληρωμένες</h4>
                <p className="text-3xl font-bold text-blue-600">{stats.completed_shipments}</p>
              </div>
              <div className="bg-white rounded-lg shadow-sm border p-6">
                <h4 className="text-sm font-medium text-gray-500 mb-2">Παραδομένες</h4>
                <p className="text-3xl font-bold text-green-600">{stats.delivered_shipments}</p>
              </div>
              <div className="bg-white rounded-lg shadow-sm border p-6">
                <h4 className="text-sm font-medium text-gray-500 mb-2">Επιστροφές</h4>
                <p className="text-3xl font-bold text-red-600">{stats.returned_shipments}</p>
              </div>
            </div>

            {/* Recent Shipments */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">📦 Πρόσφατες Αποστολές</h3>
              {recentShipments && recentShipments.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Κατάσταση</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Μεταφορέας</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ημερομηνία</th>
                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ενέργειες</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                      {recentShipments.map((shipment) => (
                        <tr key={shipment.id}>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <span className="font-mono text-sm">{shipment.tracking_number}</span>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap">
                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full border ${getStatusBadgeColor(shipment.status)}`}>
                              {formatStatus(shipment.status)}
                            </span>
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                            {shipment.courier?.name || '-'}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            {formatDate(shipment.created_at)}
                          </td>
                          <td className="px-4 py-3 whitespace-nowrap text-sm">
                            <Link
                              href={route('shipments.show', shipment.id)}
                              className="text-blue-600 hover:text-blue-800"
                            >
                              Προβολή
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-gray-500 text-center py-8">Δεν υπάρχουν αποστολές</p>
              )}
            </div>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Delivery Score Card */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">⭐ Delivery Score</h3>
              <div className="space-y-4">
                <div className="flex justify-center">
                  <DeliveryScoreBadge
                    score={customerData?.delivery_score || 0}
                    size="lg"
                    showLabel={true}
                    successRateRange={customerData?.success_rate_range}
                    showSuccessRate={true}
                    globalScore={globalStats?.enabled ? {
                      score: globalStats.score,
                      has_enough_data: globalStats.completed >= 3,
                      score_status: globalStats.score_status,
                      success_percentage: globalStats.success_percentage,
                      completed_shipments: globalStats.completed
                    } : null}
                    canViewGlobalScores={globalStats?.enabled || false}
                  />
                </div>
                <div className="pt-4 border-t space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-gray-500">Κατάσταση:</span>
                    <span className="font-medium">{customerData?.score_status?.label || 'N/A'}</span>
                  </div>
                  {customerData?.is_risky && (
                    <div className="bg-red-50 border border-red-200 rounded p-2">
                      <p className="text-xs text-red-800">⚠️ Ο πελάτης θεωρείται ριψοκίνδυνος</p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Global Delivery Score Card - Only shown if enabled */}
            {globalStats?.enabled ? (
              <div className="bg-white rounded-lg shadow-sm border p-6">
                <h3 className="text-lg font-semibold mb-4">🌐 Global Delivery Score</h3>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-500">Ολοκληρωμένες:</span>
                    <span className="font-medium">{globalStats.completed}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Παραδομένες:</span>
                    <span className="font-medium">{globalStats.delivered}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Επιτυχία:</span>
                    <span 
                      className="font-medium"
                      title={globalStats.completed < 3 ? "Χρειάζονται ≥3 ολοκληρωμένες αποστολές για global score" : undefined}
                    >
                      {globalStats.success_percentage != null 
                        ? `${globalStats.success_percentage.toFixed(1)}%` 
                        : 'N/A'}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Score:</span>
                    <span className="font-medium">{globalStats.score ?? 'N/A'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Κατάσταση:</span>
                    <span className="font-medium">{globalStats.score_status?.label ?? '—'}</span>
                  </div>
                  {globalStats.is_risky && (
                    <div className="mt-3 bg-red-50 border border-red-200 rounded p-2">
                      <p className="text-xs text-red-800">⚠️ Υψηλός κίνδυνος σε όλα τα καταστήματα</p>
                    </div>
                  )}
                </div>
              </div>
            ) : null}

            {/* Quick Stats */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">📊 Στατιστικά</h3>
              <div className="space-y-3">
                <div className="flex justify-between">
                  <span className="text-sm text-gray-500">Εκκρεμείς:</span>
                  <span className="text-sm font-medium">{stats.pending_shipments}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-gray-500">Αποτυχημένες:</span>
                  <span className="text-sm font-medium">{stats.failed_shipments}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-sm text-gray-500">Ακυρωμένες:</span>
                  <span className="text-sm font-medium">{stats.cancelled_shipments}</span>
                </div>
                <div className="pt-3 border-t">
                  <div className="flex justify-between">
                    <span className="text-sm font-medium text-gray-700">Επιτυχία:</span>
                    <span className="text-sm font-bold text-green-600">
                      {stats.success_percentage !== null 
                        ? `${stats.success_percentage.toFixed(1)}%` 
                        : 'N/A'}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}


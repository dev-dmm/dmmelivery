import { Link, Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ shipment, statusHistory }) {
  // Extract the actual shipment data from the data property
  const shipmentData = shipment?.data || shipment;
  
  // Check if shipment is undefined
  if (!shipmentData) {
    return <div>Error: Shipment not found</div>;
  }
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
      day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
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

  const formatStatus = (status) =>
    typeof status === 'string' ? status.replaceAll('_', ' ').toUpperCase() : '—';

  return (
    <AuthenticatedLayout>
      <Head title={`Παρακολούθηση ${shipmentData.tracking_number}`} />

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
            <h1 className="text-3xl font-bold text-gray-800">📦 Παρακολούθηση #{shipmentData.tracking_number}</h1>
            <p className="text-gray-600">Παραγγελία: {shipmentData.order_id}</p>
          </div>
          <div className="text-right">
            <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full border ${getStatusBadgeColor(shipmentData.status)}`}>
              {formatStatus(shipmentData.status)}
            </span>
            <p className="text-sm text-gray-500 mt-1">Τελευταία ενημέρωση: {formatDate(shipmentData.updated_at)}</p>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main */}
          <div className="lg:col-span-2 space-y-6">
            {/* Details */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">📋 Λεπτομέρειες Αποστολής</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium text-gray-500">Αριθμός Παρακολούθησης</label>
                  <p className="text-lg font-mono">{shipmentData.tracking_number}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">ID Παρακολούθησης Μεταφορέα</label>
                  <p className="text-lg font-mono">{shipmentData.courier_tracking_id || '-'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Βάρος</label>
                  <p>{shipmentData.weight ? `${shipmentData.weight} kg` : '-'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Κόστος Αποστολής</label>
                  <p>{shipmentData.shipping_cost ? `€${shipmentData.shipping_cost}` : '-'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Εκτιμώμενη Παράδοση</label>
                  <p>{formatDate(shipmentData.estimated_delivery)}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Πραγματική Παράδοση</label>
                  <p className={shipmentData.actual_delivery ? 'text-green-600 font-medium' : 'text-gray-400'}>
                    {shipmentData.actual_delivery ? formatDate(shipmentData.actual_delivery) : 'Εκκρεμεί'}
                  </p>
                </div>
              </div>
            </div>

            {/* Timeline */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">📈 Χρονοδιάγραμμα Παρακολούθησης</h3>
              {Array.isArray(statusHistory) && statusHistory.length > 0 ? (
                <div className="space-y-4">
                  {statusHistory.map((item, idx) => (
                    <div key={idx} className="flex items-start space-x-3">
                      <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${idx === 0 ? 'bg-blue-100' : 'bg-gray-100'}`}>
                        <svg className={`w-4 h-4 ${idx === 0 ? 'text-blue-600' : 'text-gray-400'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={getStatusIcon(item.status)} />
                        </svg>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-medium text-gray-900">{formatStatus(item.status)}</p>
                          <p className="text-sm text-gray-500">{formatDate(item.happened_at || item.at)}</p>
                        </div>
                        <p className="text-sm text-gray-600">{item.notes ?? `Package ${item.status?.replace('_', ' ')}`}</p>
                        {item.location && <p className="text-xs text-gray-500 mt-1">📍 {item.location}</p>}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-gray-500">
                  <svg className="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  <p>Δεν υπάρχει ακόμα ιστορικό παρακολούθησης</p>
                </div>
              )}
            </div>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Customer */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">👤 Πελάτης</h3>
              <div className="space-y-3">
                <div>
                  <label className="text-sm font-medium text-gray-500">Όνομα</label>
                  <p className="font-medium">{shipmentData?.customer?.name || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Email</label>
                  <p className="text-sm">{shipmentData?.customer?.email || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Τηλέφωνο</label>
                  <p className="text-sm">{shipmentData?.customer?.phone || 'N/A'}</p>
                </div>
              </div>
            </div>

            {/* Courier */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">🚚 Μεταφορέας</h3>
              <div className="space-y-3">
                <div>
                  <label className="text-sm font-medium text-gray-500">Εταιρεία</label>
                  <p className="font-medium">{shipmentData?.courier?.name || 'N/A'}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Code</label>
                  <p className="text-sm font-mono">{shipmentData?.courier?.code || 'N/A'}</p>
                </div>
                {shipmentData?.courier?.tracking_url_template && (
                  <div>
                    <a
                      href={shipmentData.courier.tracking_url_template.replace('{tracking_number}', shipmentData.tracking_number)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm"
                    >
                      <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                      </svg>
                      Παρακολούθηση στον ιστότοπο του μεταφορέα
                    </a>
                  </div>
                )}
              </div>
            </div>

            {/* Addresses */}
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h3 className="text-lg font-semibold mb-4">📍 Διευθύνσεις</h3>
              <div className="space-y-4">
                <div>
                  <label className="text-sm font-medium text-gray-500">Διεύθυνση Αποστολής</label>
                  <p className="text-sm whitespace-pre-line">{shipmentData.shipping_address || 'N/A'}</p>
                </div>
                {shipmentData.billing_address && (
                  <div>
                    <label className="text-sm font-medium text-gray-500">Διεύθυνση Χρέωσης</label>
                    <p className="text-sm whitespace-pre-line">{shipmentData.billing_address}</p>
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

import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { 
  Search, 
  ArrowLeft,
  Package,
  Truck,
  Clock,
  CheckCircle,
  AlertTriangle,
  Eye,
  Filter,
  X
} from 'lucide-react';

export default function ShipmentSearch({ searchQuery, searchResults }) {
  const [query, setQuery] = useState(searchQuery || '');
  const [filters, setFilters] = useState({
    status: '',
    courier: '',
    dateFrom: '',
    dateTo: ''
  });
  const [showFilters, setShowFilters] = useState(false);

  const handleSearch = (e) => {
    e.preventDefault();
    router.get('/shipments/search', { q: query, ...filters }, {
      preserveState: true,
      replace: true
    });
  };

  const clearFilters = () => {
    setFilters({
      status: '',
      courier: '',
      dateFrom: '',
      dateTo: ''
    });
    router.get('/shipments/search', { q: query }, {
      preserveState: true,
      replace: true
    });
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'delivered':
        return <CheckCircle className="w-4 h-4 text-green-600" />;
      case 'in_transit':
        return <Truck className="w-4 h-4 text-blue-600" />;
      case 'pending':
        return <Clock className="w-4 h-4 text-yellow-600" />;
      case 'failed':
        return <AlertTriangle className="w-4 h-4 text-red-600" />;
      default:
        return <Package className="w-4 h-4 text-gray-600" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'delivered':
        return 'bg-green-100 text-green-800';
      case 'in_transit':
        return 'bg-blue-100 text-blue-800';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'failed':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const statusOptions = [
    { value: '', label: 'Όλες οι καταστάσεις' },
    { value: 'pending', label: 'Εκκρεμής' },
    { value: 'picked_up', label: 'Παραλήφθηκε' },
    { value: 'in_transit', label: 'Σε Μεταφορά' },
    { value: 'out_for_delivery', label: 'Σε Παράδοση' },
    { value: 'delivered', label: 'Παραδομένη' },
    { value: 'failed', label: 'Αποτυχημένη' }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Αναζήτηση Αποστολών" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="bg-blue-100 rounded-full p-3 mr-4">
                <Search className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Αναζήτηση Αποστολών</h1>
                <p className="text-gray-600 mt-1">Βρείτε μια συγκεκριμένη αποστολή</p>
              </div>
            </div>
            <a
              href="/shipments"
              className="flex items-center px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Επιστροφή
            </a>
          </div>
        </div>

        {/* Search Form */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <form onSubmit={handleSearch} className="space-y-4">
            <div className="flex gap-4">
              <div className="flex-1">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <input
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Αναζήτηση με αριθμό παρακολούθησης, ID, όνομα πελάτη..."
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>
              <button
                type="submit"
                className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                Αναζήτηση
              </button>
              <button
                type="button"
                onClick={() => setShowFilters(!showFilters)}
                className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors flex items-center"
              >
                <Filter className="w-4 h-4 mr-2" />
                Φίλτρα
              </button>
            </div>

            {/* Advanced Filters */}
            {showFilters && (
              <div className="border-t pt-4 mt-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Κατάσταση
                    </label>
                    <select
                      value={filters.status}
                      onChange={(e) => setFilters({...filters, status: e.target.value})}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      {statusOptions.map(option => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Courier
                    </label>
                    <input
                      type="text"
                      value={filters.courier}
                      onChange={(e) => setFilters({...filters, courier: e.target.value})}
                      placeholder="Όνομα courier"
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Από Ημερομηνία
                    </label>
                    <input
                      type="date"
                      value={filters.dateFrom}
                      onChange={(e) => setFilters({...filters, dateFrom: e.target.value})}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Έως Ημερομηνία
                    </label>
                    <input
                      type="date"
                      value={filters.dateTo}
                      onChange={(e) => setFilters({...filters, dateTo: e.target.value})}
                      className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
                <div className="flex justify-end mt-4 space-x-2">
                  <button
                    type="button"
                    onClick={clearFilters}
                    className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors flex items-center"
                  >
                    <X className="w-4 h-4 mr-2" />
                    Καθαρισμός
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                  >
                    Εφαρμογή Φίλτρων
                  </button>
                </div>
              </div>
            )}
          </form>
        </div>

        {/* Search Results */}
        {searchQuery && (
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900">
                Αποτελέσματα Αναζήτησης
              </h2>
              <span className="text-sm text-gray-500">
                {searchResults.length} αποτελέσματα για "{searchQuery}"
              </span>
            </div>

            {searchResults.length > 0 ? (
              <div className="space-y-4">
                {searchResults.map((shipment) => (
                  <div key={shipment.id} className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-4">
                        {getStatusIcon(shipment.status)}
                        <div>
                          <div className="flex items-center space-x-2">
                            <span className="font-medium text-gray-900">
                              #{shipment.tracking_number || shipment.id}
                            </span>
                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(shipment.status)}`}>
                              {shipment.status}
                            </span>
                          </div>
                          <div className="text-sm text-gray-600 mt-1">
                            {shipment.customer?.name && (
                              <span>Πελάτης: {shipment.customer.name}</span>
                            )}
                            {shipment.courier?.name && (
                              <span className="ml-4">Courier: {shipment.courier.name}</span>
                            )}
                          </div>
                          <div className="text-xs text-gray-500 mt-1">
                            Δημιουργήθηκε: {new Date(shipment.created_at).toLocaleDateString('el-GR')}
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Link
                          href={`/shipments/${shipment.id}`}
                          className="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center text-sm"
                        >
                          <Eye className="w-4 h-4 mr-1" />
                          Προβολή
                        </Link>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-8">
                <Package className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                  Δεν βρέθηκαν αποτελέσματα
                </h3>
                <p className="text-gray-600">
                  Δοκιμάστε να αλλάξετε τα κριτήρια αναζήτησης ή τα φίλτρα
                </p>
              </div>
            )}
          </div>
        )}

        {/* Search Tips */}
        {!searchQuery && (
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 className="text-lg font-semibold text-blue-800 mb-3">💡 Συμβουλές Αναζήτησης</h3>
            <ul className="space-y-2 text-blue-700">
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε τον αριθμό παρακολούθησης για γρήγορη εύρεση</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Αναζητήστε με το όνομα ή email του πελάτη</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Χρησιμοποιήστε φίλτρα για να περιορίσετε τα αποτελέσματα</span>
              </li>
              <li className="flex items-start">
                <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                <span>Αναζητήστε με το ID της αποστολής ή της παραγγελίας</span>
              </li>
            </ul>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
}

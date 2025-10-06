import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  Download, 
  ArrowLeft,
  FileText,
  BarChart3,
  Calendar,
  Filter,
  CheckCircle,
  AlertCircle,
  Clock,
  TrendingUp
} from 'lucide-react';

export default function Export({ analytics, filters }) {
  const [exportFormat, setExportFormat] = useState('excel');
  const [dateRange, setDateRange] = useState({
    startDate: filters.start_date || '',
    endDate: filters.end_date || ''
  });
  const [selectedMetrics, setSelectedMetrics] = useState([
    'shipments',
    'performance',
    'couriers',
    'customers'
  ]);

  const exportFormats = [
    { value: 'excel', label: 'Excel (.xlsx)', icon: <FileText className="w-4 h-4" /> },
    { value: 'csv', label: 'CSV (.csv)', icon: <FileText className="w-4 h-4" /> },
    { value: 'pdf', label: 'PDF (.pdf)', icon: <FileText className="w-4 h-4" /> }
  ];

  const metricsOptions = [
    { value: 'shipments', label: 'Στατιστικά Αποστολών', icon: <BarChart3 className="w-4 h-4" /> },
    { value: 'performance', label: 'Απόδοση', icon: <TrendingUp className="w-4 h-4" /> },
    { value: 'couriers', label: 'Απόδοση Courier', icon: <BarChart3 className="w-4 h-4" /> },
    { value: 'customers', label: 'Ανάλυση Πελατών', icon: <BarChart3 className="w-4 h-4" /> },
    { value: 'geographic', label: 'Γεωγραφική Ανάλυση', icon: <BarChart3 className="w-4 h-4" /> },
    { value: 'trends', label: 'Χρονικές Τάσεις', icon: <TrendingUp className="w-4 h-4" /> }
  ];

  const handleExport = () => {
    // This would trigger the actual export
    console.log('Exporting with:', {
      format: exportFormat,
      dateRange,
      metrics: selectedMetrics
    });
    
    // Show success message
    alert('Η εξαγωγή ξεκίνησε! Θα λάβετε email όταν ολοκληρωθεί.');
  };

  const toggleMetric = (metric) => {
    setSelectedMetrics(prev => 
      prev.includes(metric) 
        ? prev.filter(m => m !== metric)
        : [...prev, metric]
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Εξαγωγή Αναφορών" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="bg-purple-100 rounded-full p-3 mr-4">
                <Download className="w-6 h-6 text-purple-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Εξαγωγή Αναφορών</h1>
                <p className="text-gray-600 mt-1">Εξάγετε δεδομένα και αναφορές</p>
              </div>
            </div>
            <a
              href="/analytics"
              className="flex items-center px-4 py-2 text-gray-600 hover:text-gray-900 transition-colors"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Επιστροφή
            </a>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Export Configuration */}
          <div className="lg:col-span-2 space-y-6">
            {/* Format Selection */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Μορφή Αρχείου</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {exportFormats.map((format) => (
                  <label
                    key={format.value}
                    className={`relative flex items-center p-4 border rounded-lg cursor-pointer transition-colors ${
                      exportFormat === format.value
                        ? 'border-purple-500 bg-purple-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <input
                      type="radio"
                      name="format"
                      value={format.value}
                      checked={exportFormat === format.value}
                      onChange={(e) => setExportFormat(e.target.value)}
                      className="sr-only"
                    />
                    <div className="flex items-center">
                      <div className="mr-3 text-purple-600">
                        {format.icon}
                      </div>
                      <span className="font-medium text-gray-900">{format.label}</span>
                    </div>
                    {exportFormat === format.value && (
                      <CheckCircle className="absolute top-2 right-2 w-5 h-5 text-purple-600" />
                    )}
                  </label>
                ))}
              </div>
            </div>

            {/* Date Range */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Χρονική Περίοδος</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Από Ημερομηνία
                  </label>
                  <input
                    type="date"
                    value={dateRange.startDate}
                    onChange={(e) => setDateRange({...dateRange, startDate: e.target.value})}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Έως Ημερομηνία
                  </label>
                  <input
                    type="date"
                    value={dateRange.endDate}
                    onChange={(e) => setDateRange({...dateRange, endDate: e.target.value})}
                    className="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                  />
                </div>
              </div>
            </div>

            {/* Metrics Selection */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Μετρικά για Εξαγωγή</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {metricsOptions.map((metric) => (
                  <label
                    key={metric.value}
                    className={`relative flex items-center p-4 border rounded-lg cursor-pointer transition-colors ${
                      selectedMetrics.includes(metric.value)
                        ? 'border-purple-500 bg-purple-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <input
                      type="checkbox"
                      checked={selectedMetrics.includes(metric.value)}
                      onChange={() => toggleMetric(metric.value)}
                      className="sr-only"
                    />
                    <div className="flex items-center">
                      <div className="mr-3 text-purple-600">
                        {metric.icon}
                      </div>
                      <span className="font-medium text-gray-900">{metric.label}</span>
                    </div>
                    {selectedMetrics.includes(metric.value) && (
                      <CheckCircle className="absolute top-2 right-2 w-5 h-5 text-purple-600" />
                    )}
                  </label>
                ))}
              </div>
            </div>

            {/* Export Button */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <button
                onClick={handleExport}
                className="w-full px-6 py-3 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors flex items-center justify-center"
              >
                <Download className="w-5 h-5 mr-2" />
                Εξαγωγή Αναφορών
              </button>
            </div>
          </div>

          {/* Sidebar */}
          <div className="space-y-6">
            {/* Export History */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Ιστορικό Εξαγωγών</h3>
              <div className="space-y-3">
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div className="flex items-center">
                    <FileText className="w-4 h-4 text-gray-600 mr-2" />
                    <div>
                      <p className="text-sm font-medium text-gray-900">Αναφορά Αποστολών</p>
                      <p className="text-xs text-gray-500">Excel • 2 MB</p>
                    </div>
                  </div>
                  <div className="text-xs text-gray-500">
                    {new Date().toLocaleDateString('el-GR')}
                  </div>
                </div>
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div className="flex items-center">
                    <FileText className="w-4 h-4 text-gray-600 mr-2" />
                    <div>
                      <p className="text-sm font-medium text-gray-900">Ανάλυση Courier</p>
                      <p className="text-xs text-gray-500">PDF • 1.5 MB</p>
                    </div>
                  </div>
                  <div className="text-xs text-gray-500">
                    {new Date(Date.now() - 86400000).toLocaleDateString('el-GR')}
                  </div>
                </div>
              </div>
            </div>

            {/* Quick Actions */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Γρήγορες Ενέργειες</h3>
              <div className="space-y-2">
                <Link
                  href="/analytics"
                  className="flex items-center p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                >
                  <BarChart3 className="w-4 h-4 mr-3" />
                  Προβολή Αναλυτικών
                </Link>
                <Link
                  href="/analytics/advanced"
                  className="flex items-center p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                >
                  <TrendingUp className="w-4 h-4 mr-3" />
                  Προηγμένα Αναλυτικά
                </Link>
                <Link
                  href="/help/analytics"
                  className="flex items-center p-3 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors"
                >
                  <FileText className="w-4 h-4 mr-3" />
                  Βοήθεια Αναλυτικών
                </Link>
              </div>
            </div>

            {/* Tips */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
              <h3 className="text-lg font-semibold text-blue-800 mb-3">💡 Συμβουλές Εξαγωγής</h3>
              <ul className="space-y-2 text-blue-700">
                <li className="flex items-start">
                  <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                  <span>Επιλέξτε τη σωστή μορφή αρχείου για τις ανάγκες σας</span>
                </li>
                <li className="flex items-start">
                  <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                  <span>Χρησιμοποιήστε φίλτρα ημερομηνίας για καλύτερη απόδοση</span>
                </li>
                <li className="flex items-start">
                  <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                  <span>Επιλέξτε μόνο τα μετρικά που χρειάζεστε</span>
                </li>
                <li className="flex items-start">
                  <CheckCircle className="w-4 h-4 text-blue-600 mr-2 mt-0.5 flex-shrink-0" />
                  <span>Θα λάβετε email όταν ολοκληρωθεί η εξαγωγή</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

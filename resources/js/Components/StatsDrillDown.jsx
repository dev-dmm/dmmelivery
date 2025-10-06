import React, { useState, useEffect } from 'react';
import { X, Download, Filter, Search, Calendar, TrendingUp, TrendingDown } from 'lucide-react';
import { Bar, Line } from 'react-chartjs-2';

export default function StatsDrillDown({ 
  isOpen, 
  onClose, 
  statType, 
  data = {}, 
  onExport = null,
  onFilter = null 
}) {
  const [filterPeriod, setFilterPeriod] = useState('7_days');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedData, setSelectedData] = useState([]);

  useEffect(() => {
    if (isOpen && data) {
      // Process data based on stat type
      setSelectedData(data.items || []);
    }
  }, [isOpen, data, statType]);

  const getChartConfig = () => {
    const baseConfig = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true
        }
      }
    };

    switch (statType) {
      case 'shipments':
        return {
          ...baseConfig,
          type: 'bar',
          data: {
            labels: selectedData.map(item => item.date || item.status),
            datasets: [{
              label: 'Αποστολές',
              data: selectedData.map(item => item.count),
              backgroundColor: 'rgba(59, 130, 246, 0.5)',
              borderColor: 'rgba(59, 130, 246, 1)',
              borderWidth: 1
            }]
          }
        };
      case 'couriers':
        return {
          ...baseConfig,
          type: 'bar',
          data: {
            labels: selectedData.map(item => item.name),
            datasets: [{
              label: 'Αποστολές',
              data: selectedData.map(item => item.total_shipments),
              backgroundColor: 'rgba(16, 185, 129, 0.5)',
              borderColor: 'rgba(16, 185, 129, 1)',
              borderWidth: 1
            }]
          }
        };
      default:
        return null;
    }
  };

  const handleExport = () => {
    if (onExport) {
      onExport(statType, selectedData);
    }
  };

  const handleFilter = () => {
    if (onFilter) {
      onFilter(statType, filterPeriod, searchTerm);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">
              {getStatTitle(statType)} - Λεπτομερή Στοιχεία
            </h2>
            <p className="text-sm text-gray-500 mt-1">
              Κλικ σε οποιοδήποτε στοιχείο για περισσότερες πληροφορίες
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Filters */}
        <div className="p-6 border-b border-gray-200 bg-gray-50">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Περίοδος
              </label>
              <select
                value={filterPeriod}
                onChange={(e) => setFilterPeriod(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="7_days">Τελευταίες 7 ημέρες</option>
                <option value="30_days">Τελευταίες 30 ημέρες</option>
                <option value="90_days">Τελευταίες 90 ημέρες</option>
                <option value="custom">Προσαρμοσμένη</option>
              </select>
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Αναζήτηση
              </label>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Αναζήτηση..."
                  className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>
            <div className="flex items-end">
              <button
                onClick={handleFilter}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center"
              >
                <Filter className="w-4 h-4 mr-2" />
                Φίλτρο
              </button>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-6 overflow-y-auto max-h-[60vh]">
          {/* Chart */}
          {getChartConfig() && (
            <div className="mb-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">Γράφημα Τάσεων</h3>
              <div className="h-64">
                <Bar {...getChartConfig()} />
              </div>
            </div>
          )}

          {/* Data Table */}
          <div className="mb-6">
            <h3 className="text-lg font-medium text-gray-900 mb-4">Λεπτομερή Στοιχεία</h3>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    {getTableHeaders(statType).map((header, index) => (
                      <th key={index} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {header}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {selectedData.map((item, index) => (
                    <tr key={index} className="hover:bg-gray-50 cursor-pointer">
                      {getTableRow(item, statType).map((cell, cellIndex) => (
                        <td key={cellIndex} className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {cell}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Summary Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {getSummaryStats(selectedData, statType).map((stat, index) => (
              <div key={index} className="bg-gray-50 rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-gray-600">{stat.label}</span>
                  <span className="text-lg font-semibold text-gray-900">{stat.value}</span>
                </div>
                {stat.trend && (
                  <div className={`flex items-center mt-1 text-xs ${
                    stat.trend === 'up' ? 'text-green-600' : 
                    stat.trend === 'down' ? 'text-red-600' : 'text-gray-500'
                  }`}>
                    {stat.trend === 'up' ? <TrendingUp className="w-3 h-3 mr-1" /> : 
                     stat.trend === 'down' ? <TrendingDown className="w-3 h-3 mr-1" /> : null}
                    {stat.change}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50">
          <div className="text-sm text-gray-500">
            {selectedData.length} στοιχεία εμφανίζονται
          </div>
          <div className="flex space-x-3">
            <button
              onClick={handleExport}
              className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center"
            >
              <Download className="w-4 h-4 mr-2" />
              Εξαγωγή
            </button>
            <button
              onClick={onClose}
              className="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors"
            >
              Κλείσιμο
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// Helper functions
function getStatTitle(statType) {
  const titles = {
    shipments: 'Αποστολές',
    couriers: 'Courier',
    customers: 'Πελάτες',
    alerts: 'Ειδοποιήσεις'
  };
  return titles[statType] || 'Στατιστικά';
}

function getTableHeaders(statType) {
  const headers = {
    shipments: ['Ημερομηνία', 'Αριθμός', 'Πελάτης', 'Κατάσταση', 'Courier'],
    couriers: ['Όνομα', 'Αποστολές', 'Παραδοτέα', 'Εκρεμότητα', 'Επιτυχία'],
    customers: ['Όνομα', 'Αποστολές', 'Τελευταία', 'Κατάσταση', 'Ενεργός'],
    alerts: ['Ώρα', 'Τύπος', 'Περιγραφή', 'Σοβαρότητα', 'Κατάσταση']
  };
  return headers[statType] || [];
}

function getTableRow(item, statType) {
  switch (statType) {
    case 'shipments':
      return [
        item.date || '-',
        item.tracking_number || '-',
        item.customer?.name || '-',
        item.status || '-',
        item.courier?.name || '-'
      ];
    case 'couriers':
      return [
        item.name || '-',
        item.total_shipments || 0,
        item.delivered_shipments || 0,
        item.pending_shipments || 0,
        `${item.success_rate || 0}%`
      ];
    case 'customers':
      return [
        item.name || '-',
        item.total_shipments || 0,
        item.last_shipment || '-',
        item.status || '-',
        item.is_active ? 'Ναι' : 'Όχι'
      ];
    case 'alerts':
      return [
        item.triggered_at || '-',
        item.alert_type || '-',
        item.description || '-',
        item.severity_level || '-',
        item.status || '-'
      ];
    default:
      return [];
  }
}

function getSummaryStats(data, statType) {
  if (!data || data.length === 0) return [];

  switch (statType) {
    case 'shipments':
      return [
        {
          label: 'Σύνολο Αποστολών',
          value: data.length,
          trend: 'up',
          change: '+12%'
        },
        {
          label: 'Επιτυχία',
          value: `${Math.round((data.filter(s => s.status === 'delivered').length / data.length) * 100)}%`,
          trend: 'up',
          change: '+5%'
        },
        {
          label: 'Μ.Ο. Χρόνος',
          value: '2.3 ημέρες',
          trend: 'down',
          change: '-0.5η'
        }
      ];
    case 'couriers':
      return [
        {
          label: 'Ενεργοί Courier',
          value: data.length,
          trend: 'up',
          change: '+2'
        },
        {
          label: 'Καλύτερος',
          value: data[0]?.name || '-',
          trend: null,
          change: null
        },
        {
          label: 'Μ.Ο. Απόδοση',
          value: `${Math.round(data.reduce((acc, c) => acc + (c.success_rate || 0), 0) / data.length)}%`,
          trend: 'up',
          change: '+3%'
        }
      ];
    default:
      return [];
  }
}

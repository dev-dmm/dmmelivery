import React from 'react';
import { Clock, AlertTriangle, CheckCircle, Truck, Package, Users, TrendingUp } from 'lucide-react';

export default function SnapshotOverview({ 
  todayStats = {}, 
  weekStats = {}, 
  criticalAlerts = [],
  onViewDetails = null 
}) {
  const getStatusColor = (status) => {
    const colors = {
      critical: 'bg-red-100 text-red-800 border-red-200',
      warning: 'bg-yellow-100 text-yellow-800 border-yellow-200',
      info: 'bg-blue-100 text-blue-800 border-blue-200',
      success: 'bg-green-100 text-green-800 border-green-200'
    };
    return colors[status] || colors.info;
  };

  const getAlertIcon = (type) => {
    const icons = {
      delay: <Clock className="w-4 h-4" />,
      failure: <AlertTriangle className="w-4 h-4" />,
      delivery: <CheckCircle className="w-4 h-4" />,
      pickup: <Truck className="w-4 h-4" />
    };
    return icons[type] || <AlertTriangle className="w-4 h-4" />;
  };

  return (
    <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-semibold text-gray-900 flex items-center">
          <TrendingUp className="w-5 h-5 mr-2 text-blue-600" />
          Σύνοψη Σήμερα & Αυτή την Εβδομάδα
        </h2>
        <div className="text-sm text-gray-500">
          {new Date().toLocaleDateString('el-GR', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
          })}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {/* Today's Stats */}
        <div className="bg-white rounded-lg p-4 border border-gray-200">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-medium text-gray-600">Σήμερα</h3>
            <Package className="w-4 h-4 text-blue-500" />
          </div>
          <div className="space-y-1">
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Αποστολές:</span>
              <span className="text-sm font-semibold">{todayStats.shipments || 0}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Παραδοτέα:</span>
              <span className="text-sm font-semibold text-green-600">{todayStats.delivered || 0}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Σε εξέλιξη:</span>
              <span className="text-sm font-semibold text-blue-600">{todayStats.inProgress || 0}</span>
            </div>
          </div>
        </div>

        {/* Week's Stats */}
        <div className="bg-white rounded-lg p-4 border border-gray-200">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-medium text-gray-600">Αυτή την Εβδομάδα</h3>
            <TrendingUp className="w-4 h-4 text-green-500" />
          </div>
          <div className="space-y-1">
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Σύνολο:</span>
              <span className="text-sm font-semibold">{weekStats.total || 0}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Επιτυχία:</span>
              <span className="text-sm font-semibold text-green-600">{weekStats.successRate || 0}%</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Μ.Ο. Χρόνος:</span>
              <span className="text-sm font-semibold text-blue-600">{weekStats.avgDeliveryTime || 0}ημ</span>
            </div>
          </div>
        </div>

        {/* Active Alerts */}
        <div className="bg-white rounded-lg p-4 border border-gray-200">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-medium text-gray-600">Ενεργές Ειδοποιήσεις</h3>
            <AlertTriangle className="w-4 h-4 text-red-500" />
          </div>
          <div className="space-y-1">
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Κρίσιμες:</span>
              <span className="text-sm font-semibold text-red-600">{criticalAlerts.filter(a => a.severity === 'critical').length}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Προειδοποιήσεις:</span>
              <span className="text-sm font-semibold text-yellow-600">{criticalAlerts.filter(a => a.severity === 'warning').length}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Σύνολο:</span>
              <span className="text-sm font-semibold">{criticalAlerts.length}</span>
            </div>
          </div>
        </div>

        {/* Performance Indicator */}
        <div className="bg-white rounded-lg p-4 border border-gray-200">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-medium text-gray-600">Απόδοση</h3>
            <CheckCircle className="w-4 h-4 text-green-500" />
          </div>
          <div className="space-y-1">
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Σκορ:</span>
              <span className="text-sm font-semibold text-green-600">{todayStats.performanceScore || 0}/100</span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Τάση:</span>
              <span className="text-sm font-semibold text-blue-600">
                {todayStats.trend === 'up' ? '↗️' : todayStats.trend === 'down' ? '↘️' : '→'}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-xs text-gray-500">Σύγκριση:</span>
              <span className="text-sm font-semibold text-gray-600">vs χθες</span>
            </div>
          </div>
        </div>
      </div>

      {/* Critical Alerts Banner */}
      {criticalAlerts.length > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <AlertTriangle className="w-5 h-5 text-red-500 mr-2" />
              <h3 className="text-sm font-medium text-red-800">
                Κρίσιμες Ειδοποιήσεις ({criticalAlerts.length})
              </h3>
            </div>
            {onViewDetails && (
              <button
                onClick={() => onViewDetails('alerts')}
                className="text-sm text-red-600 hover:text-red-800 font-medium"
              >
                Προβολή →
              </button>
            )}
          </div>
          <div className="mt-2 space-y-1">
            {criticalAlerts.slice(0, 3).map((alert, index) => (
              <div key={index} className="flex items-center text-sm">
                {getAlertIcon(alert.type)}
                <span className="ml-2 text-red-700">{alert.message}</span>
                <span className="ml-auto text-xs text-red-500">{alert.time}</span>
              </div>
            ))}
            {criticalAlerts.length > 3 && (
              <div className="text-xs text-red-500">
                +{criticalAlerts.length - 3} περισσότερες...
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

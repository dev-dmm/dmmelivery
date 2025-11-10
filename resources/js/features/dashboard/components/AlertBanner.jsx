import React, { useState, useEffect } from 'react';
import { 
  AlertTriangle, 
  X, 
  Bell, 
  BellOff, 
  Clock, 
  CheckCircle,
  AlertCircle,
  Info,
  Zap
} from 'lucide-react';

export default function AlertBanner({ 
  alerts = [], 
  onDismiss = null,
  onAcknowledge = null,
  onViewAll = null,
  maxVisible = 3,
  autoDismiss = false,
  dismissDelay = 5000 
}) {
  const [visibleAlerts, setVisibleAlerts] = useState([]);
  const [dismissedAlerts, setDismissedAlerts] = useState(new Set());
  const [notificationCount, setNotificationCount] = useState(0);

  useEffect(() => {
    // Filter out dismissed alerts and limit visible ones
    const filtered = alerts
      .filter(alert => !dismissedAlerts.has(alert.id))
      .slice(0, maxVisible);
    
    setVisibleAlerts(filtered);
    setNotificationCount(alerts.length);
  }, [alerts, dismissedAlerts, maxVisible]);

  useEffect(() => {
    if (autoDismiss && visibleAlerts.length > 0) {
      const timer = setTimeout(() => {
        const alertToDismiss = visibleAlerts[0];
        if (alertToDismiss) {
          handleDismiss(alertToDismiss.id);
        }
      }, dismissDelay);

      return () => clearTimeout(timer);
    }
  }, [visibleAlerts, autoDismiss, dismissDelay]);

  const handleDismiss = (alertId) => {
    setDismissedAlerts(prev => new Set([...prev, alertId]));
    if (onDismiss) {
      onDismiss(alertId);
    }
  };

  const handleAcknowledge = (alertId) => {
    if (onAcknowledge) {
      onAcknowledge(alertId);
    }
    handleDismiss(alertId);
  };

  const getAlertIcon = (severity) => {
    const icons = {
      critical: <AlertCircle className="w-5 h-5 text-red-500" />,
      warning: <AlertTriangle className="w-5 h-5 text-yellow-500" />,
      info: <Info className="w-5 h-5 text-blue-500" />,
      success: <CheckCircle className="w-5 h-5 text-green-500" />
    };
    return icons[severity] || icons.info;
  };

  const getAlertColor = (severity) => {
    const colors = {
      critical: 'bg-red-50 border-red-200 text-red-800',
      warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
      info: 'bg-blue-50 border-blue-200 text-blue-800',
      success: 'bg-green-50 border-green-200 text-green-800'
    };
    return colors[severity] || colors.info;
  };

  const getSeverityLabel = (severity) => {
    const labels = {
      critical: 'ΚΡΙΣΙΜΟ',
      warning: 'ΠΡΟΕΙΔΟΠΟΙΗΣΗ',
      info: 'ΠΛΗΡΟΦΟΡΙΑ',
      success: 'ΕΠΙΤΥΧΙΑ'
    };
    return labels[severity] || 'ΕΙΔΟΠΟΙΗΣΗ';
  };

  const formatTime = (timestamp) => {
    const now = new Date();
    const alertTime = new Date(timestamp);
    const diffMs = now - alertTime;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Τώρα';
    if (diffMins < 60) return `${diffMins} λεπτά πριν`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)} ώρες πριν`;
    return alertTime.toLocaleDateString('el-GR');
  };

  if (visibleAlerts.length === 0) return null;

  return (
    <div className="fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200 shadow-lg">
      {/* Notification Counter */}
      {notificationCount > 0 && (
        <div className="bg-red-600 text-white text-center py-1 text-sm font-medium">
          <Bell className="w-4 h-4 inline mr-2" />
          {notificationCount} νέες ειδοποιήσεις
        </div>
      )}

      {/* Alerts */}
      <div>
        <div className="space-y-3">
          {visibleAlerts.map((alert) => (
            <div
              key={alert.id}
              className={`flex items-center justify-between p-4 rounded-lg border ${getAlertColor(alert.severity)}`}
            >
              <div className="flex items-center space-x-3">
                {getAlertIcon(alert.severity)}
                <div className="flex-1">
                  <div className="flex items-center space-x-2">
                    <span className="text-xs font-bold uppercase tracking-wide">
                      {getSeverityLabel(alert.severity)}
                    </span>
                    <span className="text-xs opacity-75">
                      {formatTime(alert.triggered_at || alert.created_at)}
                    </span>
                  </div>
                  <p className="text-sm font-medium mt-1">{alert.title}</p>
                  {alert.description && (
                    <p className="text-xs opacity-90 mt-1">{alert.description}</p>
                  )}
                </div>
              </div>

              <div className="flex items-center space-x-2">
                {alert.severity === 'critical' && (
                  <div className="flex items-center text-red-600">
                    <Zap className="w-4 h-4 mr-1" />
                    <span className="text-xs font-bold">ΕΠΕΙΓΟΝ</span>
                  </div>
                )}
                
                <button
                  onClick={() => handleAcknowledge(alert.id)}
                  className="px-3 py-1 text-xs font-medium bg-white bg-opacity-50 rounded-md hover:bg-opacity-75 transition-colors"
                >
                  Επιβεβαίωση
                </button>
                
                <button
                  onClick={() => handleDismiss(alert.id)}
                  className="p-1 hover:bg-white hover:bg-opacity-25 rounded-md transition-colors"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>
          ))}

          {/* View All Button */}
          {alerts.length > maxVisible && (
            <div className="text-center pt-2">
              <button
                onClick={onViewAll}
                className="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center justify-center mx-auto"
              >
                <Bell className="w-4 h-4 mr-2" />
                Προβολή όλων των ειδοποιήσεων ({alerts.length})
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Persistent Alert Counter */}
      {notificationCount > 0 && (
        <div className="fixed top-4 right-4 z-50">
          <div className="bg-red-600 text-white rounded-full px-3 py-1 text-sm font-bold shadow-lg animate-pulse">
            {notificationCount}
          </div>
        </div>
      )}
    </div>
  );
}

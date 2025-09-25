import React, { useState, useEffect, useCallback } from 'react';
import useWebSocket from '@/hooks/useWebSocket';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { 
  Wifi, 
  WifiOff, 
  Package, 
  Truck, 
  CheckCircle, 
  AlertTriangle,
  Bell,
  Activity
} from 'lucide-react';

export default function RealTimeDashboard({ tenantId, userId, initialStats }) {
  const [stats, setStats] = useState(initialStats || {});
  const [recentUpdates, setRecentUpdates] = useState([]);
  const [notifications, setNotifications] = useState([]);
  
  const {
    isConnected,
    subscribeToShipmentUpdates,
    subscribeToNewShipments,
    subscribeToShipmentDelivered,
    subscribeToAlerts,
    subscribeToDashboardUpdates,
    subscribeToSystemNotifications,
  } = useWebSocket(tenantId, userId);

  // Handle shipment updates
  const handleShipmentUpdate = useCallback((data) => {
    console.log('Shipment updated:', data);
    setRecentUpdates(prev => [{
      id: Date.now(),
      type: 'shipment_update',
      message: `Shipment ${data.tracking_number} status updated to ${data.status}`,
      timestamp: new Date(),
      data
    }, ...prev.slice(0, 9)]);
  }, []);

  // Handle new shipments
  const handleNewShipment = useCallback((data) => {
    console.log('New shipment:', data);
    setRecentUpdates(prev => [{
      id: Date.now(),
      type: 'new_shipment',
      message: `New shipment ${data.tracking_number} created`,
      timestamp: new Date(),
      data
    }, ...prev.slice(0, 9)]);
    
    // Update stats
    setStats(prev => ({
      ...prev,
      total_shipments: (prev.total_shipments || 0) + 1
    }));
  }, []);

  // Handle delivered shipments
  const handleShipmentDelivered = useCallback((data) => {
    console.log('Shipment delivered:', data);
    setRecentUpdates(prev => [{
      id: Date.now(),
      type: 'delivered',
      message: `Shipment ${data.tracking_number} delivered!`,
      timestamp: new Date(),
      data
    }, ...prev.slice(0, 9)]);
    
    // Update stats
    setStats(prev => ({
      ...prev,
      delivered_shipments: (prev.delivered_shipments || 0) + 1,
      in_transit_shipments: Math.max(0, (prev.in_transit_shipments || 0) - 1)
    }));
  }, []);

  // Handle alerts
  const handleAlert = useCallback((data) => {
    console.log('Alert triggered:', data);
    setNotifications(prev => [{
      id: data.alert_id,
      type: 'alert',
      title: data.title,
      message: data.description,
      severity: data.severity_level,
      timestamp: new Date(data.triggered_at),
      data
    }, ...prev.slice(0, 4)]);
  }, []);

  // Handle dashboard updates
  const handleDashboardUpdate = useCallback((data) => {
    console.log('Dashboard updated:', data);
    setStats(prev => ({ ...prev, ...data }));
  }, []);

  // Handle system notifications
  const handleSystemNotification = useCallback((data) => {
    console.log('System notification:', data);
    setNotifications(prev => [{
      id: Date.now(),
      type: 'system',
      title: 'System Notification',
      message: data.message,
      severity: data.type,
      timestamp: new Date(data.timestamp),
      data
    }, ...prev.slice(0, 4)]);
  }, []);

  // Subscribe to events
  useEffect(() => {
    if (tenantId && userId && isConnected) {
      subscribeToShipmentUpdates(handleShipmentUpdate);
      subscribeToNewShipments(handleNewShipment);
      subscribeToShipmentDelivered(handleShipmentDelivered);
      subscribeToAlerts(handleAlert);
      subscribeToDashboardUpdates(handleDashboardUpdate);
      subscribeToSystemNotifications(handleSystemNotification);
    }
  }, [
    tenantId, userId, isConnected,
    subscribeToShipmentUpdates, handleShipmentUpdate,
    subscribeToNewShipments, handleNewShipment,
    subscribeToShipmentDelivered, handleShipmentDelivered,
    subscribeToAlerts, handleAlert,
    subscribeToDashboardUpdates, handleDashboardUpdate,
    subscribeToSystemNotifications, handleSystemNotification
  ]);

  const getSeverityColor = (severity) => {
    switch (severity) {
      case 'critical': return 'bg-red-100 text-red-800 border-red-200';
      case 'high': return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'low': return 'bg-blue-100 text-blue-800 border-blue-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getUpdateIcon = (type) => {
    switch (type) {
      case 'shipment_update': return <Truck className="w-4 h-4" />;
      case 'new_shipment': return <Package className="w-4 h-4" />;
      case 'delivered': return <CheckCircle className="w-4 h-4" />;
      default: return <Activity className="w-4 h-4" />;
    }
  };

  return (
    <div className="space-y-6">
      {/* Connection Status */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center space-x-2">
            {isConnected ? (
              <Wifi className="w-5 h-5 text-green-600" />
            ) : (
              <WifiOff className="w-5 h-5 text-red-600" />
            )}
            <span>Real-time Connection</span>
            <Badge variant={isConnected ? "default" : "destructive"}>
              {isConnected ? 'Connected' : 'Disconnected'}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-gray-600">
            {isConnected 
              ? 'Receiving real-time updates for shipments, alerts, and dashboard changes.'
              : 'WebSocket connection not available. Real-time features are disabled. Please configure Pusher settings to enable live updates.'
            }
          </p>
          {!isConnected && (
            <div className="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p className="text-sm text-yellow-800">
                <strong>Note:</strong> To enable real-time features, please configure Pusher settings in your environment variables:
                <br />• PUSHER_APP_KEY
                <br />• PUSHER_APP_SECRET  
                <br />• PUSHER_APP_ID
                <br />• PUSHER_APP_CLUSTER
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Live Statistics */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <Package className="w-5 h-5 text-blue-600" />
              <div>
                <p className="text-sm font-medium text-gray-600">Total Shipments</p>
                <p className="text-2xl font-bold">{stats.total_shipments || 0}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <CheckCircle className="w-5 h-5 text-green-600" />
              <div>
                <p className="text-sm font-medium text-gray-600">Delivered</p>
                <p className="text-2xl font-bold">{stats.delivered_shipments || 0}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <Truck className="w-5 h-5 text-orange-600" />
              <div>
                <p className="text-sm font-medium text-gray-600">In Transit</p>
                <p className="text-2xl font-bold">{stats.in_transit_shipments || 0}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <div className="flex items-center space-x-2">
              <AlertTriangle className="w-5 h-5 text-red-600" />
              <div>
                <p className="text-sm font-medium text-gray-600">Active Alerts</p>
                <p className="text-2xl font-bold">{notifications.length}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Updates */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Activity className="w-5 h-5" />
              <span>Recent Updates</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3 max-h-64 overflow-y-auto">
              {recentUpdates.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-4">
                  No recent updates
                </p>
              ) : (
                recentUpdates.map((update) => (
                  <div key={update.id} className="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                    <div className="flex-shrink-0">
                      {getUpdateIcon(update.type)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium text-gray-900">
                        {update.message}
                      </p>
                      <p className="text-xs text-gray-500">
                        {update.timestamp.toLocaleTimeString()}
                      </p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>

        {/* Notifications */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center space-x-2">
              <Bell className="w-5 h-5" />
              <span>Notifications</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3 max-h-64 overflow-y-auto">
              {notifications.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-4">
                  No notifications
                </p>
              ) : (
                notifications.map((notification) => (
                  <div key={notification.id} className="p-3 border rounded-lg">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <p className="text-sm font-medium text-gray-900">
                          {notification.title}
                        </p>
                        <p className="text-sm text-gray-600 mt-1">
                          {notification.message}
                        </p>
                        <p className="text-xs text-gray-500 mt-1">
                          {notification.timestamp.toLocaleString()}
                        </p>
                      </div>
                      <Badge className={`ml-2 ${getSeverityColor(notification.severity)}`}>
                        {notification.severity}
                      </Badge>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

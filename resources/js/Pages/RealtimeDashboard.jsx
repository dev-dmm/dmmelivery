import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import RealTimeDashboard from '@/Components/RealTimeDashboard';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { 
  Wifi, 
  WifiOff, 
  RefreshCw,
  ArrowLeft,
  Activity
} from 'lucide-react';

export default function RealtimeDashboardPage({ tenantId, userId, initialStats, auth }) {
  const [isConnected, setIsConnected] = useState(false);
  const [lastUpdate, setLastUpdate] = useState(new Date());

  useEffect(() => {
    // Simulate connection status updates
    const interval = setInterval(() => {
      setIsConnected(Math.random() > 0.1); // 90% connection rate
      setLastUpdate(new Date());
    }, 5000);

    return () => clearInterval(interval);
  }, []);

  return (
    <AuthenticatedLayout user={auth.user}>
      <Head title="Real-time Dashboard" />
      
      <div className="py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Header */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div className="flex items-center space-x-4">
              <Button
                onClick={() => window.history.back()}
                variant="outline"
                size="sm"
              >
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back
              </Button>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Real-time Dashboard</h1>
                <p className="text-sm text-gray-600 mt-1">
                  Live shipment tracking and updates
                </p>
              </div>
            </div>
            
            <div className="flex items-center space-x-4 mt-4 sm:mt-0">
              <div className="flex items-center space-x-2">
                {isConnected ? (
                  <Wifi className="w-5 h-5 text-green-600" />
                ) : (
                  <WifiOff className="w-5 h-5 text-red-600" />
                )}
                <Badge variant={isConnected ? "default" : "destructive"}>
                  {isConnected ? 'Connected' : 'Disconnected'}
                </Badge>
              </div>
              
              <Button
                onClick={() => window.location.reload()}
                variant="outline"
                size="sm"
              >
                <RefreshCw className="w-4 h-4 mr-2" />
                Refresh
              </Button>
            </div>
          </div>

          {/* Connection Status Card */}
          <Card className="mb-6">
            <CardHeader>
              <CardTitle className="flex items-center space-x-2">
                <Activity className="w-5 h-5" />
                <span>Connection Status</span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600">
                    {isConnected 
                      ? 'Receiving real-time updates for shipments, alerts, and dashboard changes.'
                      : 'Connection lost. Attempting to reconnect...'
                    }
                  </p>
                  <p className="text-xs text-gray-500 mt-1">
                    Last update: {lastUpdate.toLocaleTimeString()}
                  </p>
                </div>
                <div className="flex items-center space-x-2">
                  <div className={`w-3 h-3 rounded-full ${isConnected ? 'bg-green-500' : 'bg-red-500'} animate-pulse`}></div>
                  <span className="text-sm font-medium">
                    {isConnected ? 'Live' : 'Offline'}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Real-time Dashboard Component */}
          <RealTimeDashboard 
            tenantId={tenantId}
            userId={userId}
            initialStats={initialStats}
          />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

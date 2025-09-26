import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { 
  AlertTriangle, 
  Clock, 
  CheckCircle, 
  XCircle,
  RefreshCw,
  Eye,
  Settings,
  Bell
} from 'lucide-react';

const AlertsIndex = ({ alerts, stats, filters }) => {
  const [isChecking, setIsChecking] = useState(false);
  const [isAcknowledging, setIsAcknowledging] = useState({});

  const handleCheckAlerts = async () => {
    setIsChecking(true);
    try {
      const response = await fetch('/alerts/check', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });
      
      if (response.ok) {
        const result = await response.json();
        alert(`✅ Checked ${result.alerts_checked || 0} shipments. Found ${result.new_alerts || 0} new alerts.`);
        window.location.reload();
      } else {
        throw new Error('Failed to check alerts');
      }
    } catch (error) {
      console.error('Error checking alerts:', error);
      alert('❌ Error checking alerts. Please try again.');
    } finally {
      setIsChecking(false);
    }
  };

  const handleAcknowledgeAlert = async (alertId) => {
    setIsAcknowledging(prev => ({ ...prev, [alertId]: true }));
    try {
      const response = await fetch(`/alerts/${alertId}/acknowledge`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });
      
      if (response.ok) {
        alert('✅ Alert acknowledged successfully!');
        window.location.reload();
      } else {
        throw new Error('Failed to acknowledge alert');
      }
    } catch (error) {
      console.error('Error acknowledging alert:', error);
      alert('❌ Error acknowledging alert. Please try again.');
    } finally {
      setIsAcknowledging(prev => ({ ...prev, [alertId]: false }));
    }
  };

  const handleResolveAlert = async (alertId) => {
    setIsAcknowledging(prev => ({ ...prev, [alertId]: true }));
    try {
      const response = await fetch(`/alerts/${alertId}/resolve`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });
      
      if (response.ok) {
        alert('✅ Alert resolved successfully!');
        window.location.reload();
      } else {
        throw new Error('Failed to resolve alert');
      }
    } catch (error) {
      console.error('Error resolving alert:', error);
      alert('❌ Error resolving alert. Please try again.');
    } finally {
      setIsAcknowledging(prev => ({ ...prev, [alertId]: false }));
    }
  };

  const getSeverityColor = (level) => {
    switch (level) {
      case 'low': return 'bg-blue-100 text-blue-800';
      case 'medium': return 'bg-yellow-100 text-yellow-800';
      case 'high': return 'bg-orange-100 text-orange-800';
      case 'critical': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getSeverityIcon = (level) => {
    switch (level) {
      case 'low': return 'ℹ️';
      case 'medium': return '⚠️';
      case 'high': return '🚨';
      case 'critical': return '🔴';
      default: return '❓';
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active': return 'bg-red-100 text-red-800';
      case 'acknowledged': return 'bg-yellow-100 text-yellow-800';
      case 'resolved': return 'bg-green-100 text-green-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'active': return '🔴';
      case 'acknowledged': return '⚠️';
      case 'resolved': return '✅';
      default: return '❓';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('el-GR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return (
    <AuthenticatedLayout>
      <Head title="Σύστημα Ειδοποιήσεων" />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-gray-900 flex items-center">
                  <Bell className="w-8 h-8 mr-3 text-red-600" />
                  Σύστημα Ειδοποιήσεων
                </h1>
                <p className="mt-2 text-gray-600">
                  Αυτόματη ανίχνευση προβλημάτων και διαχείριση ειδοποιήσεων
                </p>
              </div>
              <div className="flex items-center space-x-3">
                <Button 
                  onClick={handleCheckAlerts}
                  disabled={isChecking}
                  variant="outline"
                  className="flex items-center"
                >
                  <RefreshCw className={`w-4 h-4 mr-2 ${isChecking ? 'animate-spin' : ''}`} />
                  {isChecking ? 'Έλεγχος...' : 'Έλεγχος Ειδοποιήσεων'}
                </Button>
                <Link
                  href="/alerts/rules"
                  className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  <Settings className="w-4 h-4 mr-2" />
                  Διαχείριση Κανόνων
                </Link>
              </div>
            </div>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Σύνολο Ειδοποιήσεων</CardTitle>
                <Bell className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.total_alerts}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Ενεργές</CardTitle>
                <AlertTriangle className="h-4 w-4 text-red-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-red-600">{stats.active_alerts}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Αναγνωρισμένες</CardTitle>
                <Clock className="h-4 w-4 text-yellow-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-yellow-600">{stats.acknowledged_alerts}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Επιλυμένες</CardTitle>
                <CheckCircle className="h-4 w-4 text-green-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-600">{stats.resolved_alerts}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Κρίσιμες</CardTitle>
                <XCircle className="h-4 w-4 text-red-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-red-600">{stats.critical_alerts}</div>
              </CardContent>
            </Card>
          </div>

          {/* Alerts List */}
          <Card>
            <CardHeader>
              <CardTitle>Πρόσφατες Ειδοποιήσεις</CardTitle>
              <CardDescription>
                Αυτόματες ειδοποιήσεις για προβλήματα και καθυστερήσεις αποστολών
              </CardDescription>
            </CardHeader>
            <CardContent>
              {alerts.data.length > 0 ? (
                <div className="space-y-4">
                  {alerts.data.map((alert) => (
                    <div key={alert.id} className="border rounded-lg p-4 hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-4">
                            <div>
                              <p className="font-medium text-gray-900">
                                {alert.title}
                              </p>
                              <p className="text-sm text-gray-500">
                                {alert.shipment?.tracking_number || 'Unknown'} - {alert.shipment?.customer?.name || 'Unknown Customer'}
                              </p>
                            </div>
                            <div className="flex items-center space-x-2">
                              <Badge className={getSeverityColor(alert.severity_level)}>
                                {getSeverityIcon(alert.severity_level)} {alert.severity_level}
                              </Badge>
                              <Badge className={getStatusColor(alert.status)}>
                                {getStatusIcon(alert.status)} {alert.status}
                              </Badge>
                            </div>
                          </div>
                          
                          <div className="mt-3">
                            <p className="text-sm text-gray-700 mb-2">
                              {alert.description}
                            </p>
                            
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                              <div>
                                <p className="text-xs text-gray-500 mb-1">Ενεργοποιήθηκε</p>
                                <p className="text-sm text-gray-900">
                                  {formatDate(alert.triggered_at)}
                                </p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-500 mb-1">Κανόνας</p>
                                <p className="text-sm text-gray-900">
                                  {alert.rule?.name || 'Unknown Rule'}
                                </p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-500 mb-1">Εκτόνωση</p>
                                <p className="text-sm text-gray-900">
                                  Level {alert.escalation_level || 0}
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                          {alert.status === 'active' && (
                            <Button
                              onClick={() => handleAcknowledgeAlert(alert.id)}
                              disabled={isAcknowledging[alert.id]}
                              variant="outline"
                              size="sm"
                              className="text-yellow-600 border-yellow-300 hover:bg-yellow-50"
                            >
                              <Clock className="w-4 h-4 mr-1" />
                              {isAcknowledging[alert.id] ? 'Αναγνώριση...' : 'Αναγνώριση'}
                            </Button>
                          )}
                          
                          {alert.status === 'acknowledged' && (
                            <Button
                              onClick={() => handleResolveAlert(alert.id)}
                              disabled={isAcknowledging[alert.id]}
                              variant="outline"
                              size="sm"
                              className="text-green-600 border-green-300 hover:bg-green-50"
                            >
                              <CheckCircle className="w-4 h-4 mr-1" />
                              {isAcknowledging[alert.id] ? 'Επίλυση...' : 'Επίλυση'}
                            </Button>
                          )}
                          
                          <Link
                            href={`/alerts/${alert.id}`}
                            className="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                          >
                            <Eye className="w-4 h-4 mr-1" />
                            Προβολή Λεπτομερειών
                          </Link>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12">
                  <Bell className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                  <p className="text-lg font-medium text-gray-900 mb-2">Δεν Υπάρχουν Ειδοποιήσεις</p>
                  <p className="text-gray-500 mb-4">
                    Οι αποστολές σας λειτουργούν ομαλά χωρίς προβλήματα
                  </p>
                  <Button onClick={handleCheckAlerts} disabled={isChecking}>
                    <RefreshCw className={`w-4 h-4 mr-2 ${isChecking ? 'animate-spin' : ''}`} />
                    Έλεγχος Ειδοποιήσεων
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
};

export default AlertsIndex;

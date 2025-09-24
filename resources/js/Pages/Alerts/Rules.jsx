import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { 
  Settings, 
  Plus, 
  Edit, 
  Trash2, 
  AlertTriangle,
  Clock,
  CheckCircle,
  XCircle,
  Bell,
  Save,
  X
} from 'lucide-react';

const AlertRulesIndex = ({ rules, stats }) => {
  const [isCreating, setIsCreating] = useState(false);
  const [isDeleting, setIsDeleting] = useState({});
  const [isToggling, setIsToggling] = useState({});

  const handleCreateRule = async () => {
    setIsCreating(true);
    try {
      const response = await fetch('/alerts/rules', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          name: 'New Alert Rule',
          description: 'Automatically created rule',
          trigger_conditions: {
            status: 'stuck',
            duration_hours: 2
          },
          alert_type: 'delay',
          severity_level: 'medium',
          is_active: true
        })
      });
      
      if (response.ok) {
        alert('✅ New alert rule created!');
        window.location.reload();
      } else {
        throw new Error('Failed to create rule');
      }
    } catch (error) {
      console.error('Error creating rule:', error);
      alert('❌ Error creating rule. Please try again.');
    } finally {
      setIsCreating(false);
    }
  };

  const handleDeleteRule = async (ruleId) => {
    if (!confirm('Are you sure you want to delete this alert rule?')) return;
    
    setIsDeleting(prev => ({ ...prev, [ruleId]: true }));
    try {
      const response = await fetch(`/alerts/rules/${ruleId}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
      });
      
      if (response.ok) {
        alert('✅ Alert rule deleted successfully!');
        window.location.reload();
      } else {
        throw new Error('Failed to delete rule');
      }
    } catch (error) {
      console.error('Error deleting rule:', error);
      alert('❌ Error deleting rule. Please try again.');
    } finally {
      setIsDeleting(prev => ({ ...prev, [ruleId]: false }));
    }
  };

  const handleToggleRule = async (ruleId, isActive) => {
    setIsToggling(prev => ({ ...prev, [ruleId]: true }));
    try {
      const response = await fetch(`/alerts/rules/${ruleId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          is_active: !isActive
        })
      });
      
      if (response.ok) {
        alert(`✅ Alert rule ${!isActive ? 'activated' : 'deactivated'} successfully!`);
        window.location.reload();
      } else {
        throw new Error('Failed to toggle rule');
      }
    } catch (error) {
      console.error('Error toggling rule:', error);
      alert('❌ Error toggling rule. Please try again.');
    } finally {
      setIsToggling(prev => ({ ...prev, [ruleId]: false }));
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

  const getAlertTypeIcon = (type) => {
    switch (type) {
      case 'delay': return '⏰';
      case 'stuck': return '🚫';
      case 'deviation': return '📍';
      case 'customs': return '🏛️';
      default: return '🔔';
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
      <Head title="Alert Rules" />
      
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-3xl font-bold text-gray-900 flex items-center">
                  <Settings className="w-8 h-8 mr-3 text-blue-600" />
                  Alert Rules
                </h1>
                <p className="mt-2 text-gray-600">
                  Configure automated alert conditions and triggers
                </p>
              </div>
              <div className="flex items-center space-x-3">
                <Button 
                  onClick={handleCreateRule}
                  disabled={isCreating}
                  className="flex items-center"
                >
                  <Plus className="w-4 h-4 mr-2" />
                  {isCreating ? 'Creating...' : 'Create Rule'}
                </Button>
                <Link
                  href="/alerts"
                  className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                >
                  <Bell className="w-4 h-4 mr-2" />
                  Back to Alerts
                </Link>
              </div>
            </div>
          </div>

          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Rules</CardTitle>
                <Settings className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.total_rules}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Active</CardTitle>
                <CheckCircle className="h-4 w-4 text-green-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-600">{stats.active_rules}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Inactive</CardTitle>
                <XCircle className="h-4 w-4 text-gray-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-gray-600">{stats.inactive_rules}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Alerts Triggered</CardTitle>
                <AlertTriangle className="h-4 w-4 text-orange-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-orange-600">{stats.alerts_triggered}</div>
              </CardContent>
            </Card>
          </div>

          {/* Rules List */}
          <Card>
            <CardHeader>
              <CardTitle>Alert Rules</CardTitle>
              <CardDescription>
                Configure conditions that trigger automated alerts
              </CardDescription>
            </CardHeader>
            <CardContent>
              {rules.data.length > 0 ? (
                <div className="space-y-4">
                  {rules.data.map((rule) => (
                    <div key={rule.id} className="border rounded-lg p-4 hover:bg-gray-50">
                      <div className="flex items-center justify-between">
                        <div className="flex-1">
                          <div className="flex items-center space-x-4">
                            <div>
                              <p className="font-medium text-gray-900">
                                {rule.name}
                              </p>
                              <p className="text-sm text-gray-500">
                                {rule.description}
                              </p>
                            </div>
                            <div className="flex items-center space-x-2">
                              <Badge className={getSeverityColor(rule.severity_level)}>
                                {getSeverityIcon(rule.severity_level)} {rule.severity_level}
                              </Badge>
                              <Badge className={rule.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
                                {rule.is_active ? '✅ Active' : '❌ Inactive'}
                              </Badge>
                            </div>
                          </div>
                          
                          <div className="mt-3">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                              <div>
                                <p className="text-xs text-gray-500 mb-1">Alert Type</p>
                                <p className="text-sm text-gray-900 flex items-center">
                                  {getAlertTypeIcon(rule.alert_type)} {rule.alert_type}
                                </p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-500 mb-1">Created</p>
                                <p className="text-sm text-gray-900">
                                  {formatDate(rule.created_at)}
                                </p>
                              </div>
                              <div>
                                <p className="text-xs text-gray-500 mb-1">Last Updated</p>
                                <p className="text-sm text-gray-900">
                                  {formatDate(rule.updated_at)}
                                </p>
                              </div>
                            </div>
                            
                            {rule.trigger_conditions && (
                              <div className="mt-3">
                                <p className="text-xs text-gray-500 mb-2">Trigger Conditions:</p>
                                <div className="bg-gray-50 rounded p-2">
                                  <pre className="text-xs text-gray-700">
                                    {JSON.stringify(rule.trigger_conditions, null, 2)}
                                  </pre>
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                        
                        <div className="flex items-center space-x-2">
                          <Button
                            onClick={() => handleToggleRule(rule.id, rule.is_active)}
                            disabled={isToggling[rule.id]}
                            variant="outline"
                            size="sm"
                            className={rule.is_active ? 'text-orange-600 border-orange-300 hover:bg-orange-50' : 'text-green-600 border-green-300 hover:bg-green-50'}
                          >
                            {rule.is_active ? (
                              <>
                                <X className="w-4 h-4 mr-1" />
                                {isToggling[rule.id] ? 'Deactivating...' : 'Deactivate'}
                              </>
                            ) : (
                              <>
                                <CheckCircle className="w-4 h-4 mr-1" />
                                {isToggling[rule.id] ? 'Activating...' : 'Activate'}
                              </>
                            )}
                          </Button>
                          
                          <Link
                            href={`/alerts/rules/${rule.id}/edit`}
                            className="inline-flex items-center px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                          >
                            <Edit className="w-4 h-4 mr-1" />
                            Edit
                          </Link>
                          
                          <Button
                            onClick={() => handleDeleteRule(rule.id)}
                            disabled={isDeleting[rule.id]}
                            variant="outline"
                            size="sm"
                            className="text-red-600 border-red-300 hover:bg-red-50"
                          >
                            <Trash2 className="w-4 h-4 mr-1" />
                            {isDeleting[rule.id] ? 'Deleting...' : 'Delete'}
                          </Button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12">
                  <Settings className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                  <p className="text-lg font-medium text-gray-900 mb-2">No Alert Rules</p>
                  <p className="text-gray-500 mb-4">
                    Create your first alert rule to start monitoring shipments
                  </p>
                  <Button onClick={handleCreateRule} disabled={isCreating}>
                    <Plus className="w-4 h-4 mr-2" />
                    {isCreating ? 'Creating...' : 'Create First Rule'}
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

export default AlertRulesIndex;

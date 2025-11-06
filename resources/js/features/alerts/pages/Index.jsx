import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { 
  AlertTriangle, 
  ArrowLeft,
  Plus,
  Settings,
  Bell,
  Mail,
  Phone,
  MessageCircle,
  CheckCircle,
  Clock,
  Edit,
  Trash2,
  ToggleLeft,
  ToggleRight
} from 'lucide-react';

export default function Alerts() {
  const [activeTab, setActiveTab] = useState('rules');

  // Mock data - in real app this would come from props
  const alertRules = [
    {
      id: 1,
      name: 'Καθυστέρηση Αποστολής',
      description: 'Ειδοποίηση όταν μια αποστολή καθυστερεί περισσότερο από 2 ημέρες',
      type: 'delay',
      priority: 'high',
      enabled: true,
      channels: ['email', 'sms'],
      conditions: [
        { field: 'status', operator: 'equals', value: 'in_transit' },
        { field: 'days_in_transit', operator: 'greater_than', value: '2' }
      ]
    },
    {
      id: 2,
      name: 'Αποτυχημένη Παράδοση',
      description: 'Ειδοποίηση όταν μια αποστολή αποτυγχάνει',
      type: 'failure',
      priority: 'high',
      enabled: true,
      channels: ['email', 'dashboard'],
      conditions: [
        { field: 'status', operator: 'equals', value: 'failed' }
      ]
    },
    {
      id: 3,
      name: 'Επιτυχής Παράδοση',
      description: 'Ειδοποίηση όταν μια αποστολή παραδίδεται επιτυχώς',
      type: 'success',
      priority: 'medium',
      enabled: true,
      channels: ['email'],
      conditions: [
        { field: 'status', operator: 'equals', value: 'delivered' }
      ]
    },
    {
      id: 4,
      name: 'Πρόβλημα με Courier',
      description: 'Ειδοποίηση όταν υπάρχει πρόβλημα με τον courier',
      type: 'courier_issue',
      priority: 'high',
      enabled: false,
      channels: ['email', 'sms', 'dashboard'],
      conditions: [
        { field: 'courier_status', operator: 'equals', value: 'error' }
      ]
    }
  ];

  const recentAlerts = [
    {
      id: 1,
      rule: 'Καθυστέρηση Αποστολής',
      message: 'Η αποστολή #12345 καθυστερεί περισσότερο από 2 ημέρες',
      timestamp: new Date(),
      status: 'active',
      priority: 'high'
    },
    {
      id: 2,
      rule: 'Επιτυχής Παράδοση',
      message: 'Η αποστολή #12344 παραδόθηκε επιτυχώς',
      timestamp: new Date(Date.now() - 3600000),
      status: 'resolved',
      priority: 'medium'
    },
    {
      id: 3,
      rule: 'Πρόβλημα με Courier',
      message: 'Ανιχνεύθηκε πρόβλημα με τον courier ACS',
      timestamp: new Date(Date.now() - 7200000),
      status: 'acknowledged',
      priority: 'high'
    }
  ];

  const getPriorityColor = (priority) => {
    switch (priority) {
      case 'high':
        return 'text-red-600 bg-red-100';
      case 'medium':
        return 'text-yellow-600 bg-yellow-100';
      case 'low':
        return 'text-blue-600 bg-blue-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'active':
        return 'text-red-600 bg-red-100';
      case 'acknowledged':
        return 'text-yellow-600 bg-yellow-100';
      case 'resolved':
        return 'text-green-600 bg-green-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const tabs = [
    { id: 'rules', label: 'Κανόνες Ειδοποιήσεων', count: alertRules.length },
    { id: 'alerts', label: 'Πρόσφατες Ειδοποιήσεις', count: recentAlerts.length },
    { id: 'settings', label: 'Ρυθμίσεις', count: 0 }
  ];

  return (
    <AuthenticatedLayout>
      <Head title="Ρυθμίσεις Ειδοποιήσεων" />

      <div className="py-6">
        {/* Header */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <div className="bg-orange-100 rounded-full p-3 mr-4">
                <AlertTriangle className="w-6 h-6 text-orange-600" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Ρυθμίσεις Ειδοποιήσεων</h1>
                <p className="text-gray-600 mt-1">Διαμορφώστε τις ειδοποιήσεις σας</p>
              </div>
            </div>
            <div className="flex items-center space-x-2">
              <button className="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors flex items-center">
                <Plus className="w-4 h-4 mr-2" />
                Νέος Κανόνας
              </button>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
          <div className="border-b border-gray-200">
            <nav className="flex space-x-8 px-6">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`py-4 px-1 border-b-2 font-medium text-sm ${
                    activeTab === tab.id
                      ? 'border-orange-500 text-orange-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  {tab.label}
                  {tab.count > 0 && (
                    <span className="ml-2 bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs">
                      {tab.count}
                    </span>
                  )}
                </button>
              ))}
            </nav>
          </div>
        </div>

        {/* Tab Content */}
        {activeTab === 'rules' && (
          <div className="space-y-4">
            {alertRules.map((rule) => (
              <div key={rule.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center space-x-3 mb-2">
                      <h3 className="text-lg font-semibold text-gray-900">{rule.name}</h3>
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${getPriorityColor(rule.priority)}`}>
                        {rule.priority}
                      </span>
                      <div className="flex items-center">
                        {rule.enabled ? (
                          <ToggleRight className="w-5 h-5 text-green-600" />
                        ) : (
                          <ToggleLeft className="w-5 h-5 text-gray-400" />
                        )}
                      </div>
                    </div>
                    <p className="text-gray-600 mb-3">{rule.description}</p>
                    <div className="flex items-center space-x-4">
                      <div className="flex items-center space-x-2">
                        <span className="text-sm text-gray-500">Κανάλια:</span>
                        {rule.channels.map((channel) => (
                          <span key={channel} className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                            {channel}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <button className="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                      <Edit className="w-4 h-4" />
                    </button>
                    <button className="p-2 text-gray-400 hover:text-red-600 transition-colors">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {activeTab === 'alerts' && (
          <div className="space-y-4">
            {recentAlerts.map((alert) => (
              <div key={alert.id} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center space-x-3 mb-2">
                      <h3 className="text-lg font-semibold text-gray-900">{alert.rule}</h3>
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${getPriorityColor(alert.priority)}`}>
                        {alert.priority}
                      </span>
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(alert.status)}`}>
                        {alert.status}
                      </span>
                    </div>
                    <p className="text-gray-600 mb-3">{alert.message}</p>
                    <div className="flex items-center space-x-4">
                      <span className="text-sm text-gray-500">
                        {alert.timestamp.toLocaleDateString('el-GR')} {alert.timestamp.toLocaleTimeString('el-GR')}
                      </span>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <button className="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                      Προβολή
                    </button>
                    <button className="px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors text-sm">
                      Επίλυση
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {activeTab === 'settings' && (
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-6">Γενικές Ρυθμίσεις</h3>
            
            <div className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 className="text-md font-medium text-gray-900 mb-4">Email Ειδοποιήσεις</h4>
                  <div className="space-y-3">
                    <label className="flex items-center">
                      <input type="checkbox" defaultChecked className="mr-3" />
                      <span className="text-sm text-gray-700">Ενημερώσεις κατάστασης αποστολής</span>
                    </label>
                    <label className="flex items-center">
                      <input type="checkbox" defaultChecked className="mr-3" />
                      <span className="text-sm text-gray-700">Ειδοποιήσεις καθυστέρησης</span>
                    </label>
                    <label className="flex items-center">
                      <input type="checkbox" className="mr-3" />
                      <span className="text-sm text-gray-700">Αναφορές απόδοσης</span>
                    </label>
                  </div>
                </div>

                <div>
                  <h4 className="text-md font-medium text-gray-900 mb-4">SMS Ειδοποιήσεις</h4>
                  <div className="space-y-3">
                    <label className="flex items-center">
                      <input type="checkbox" defaultChecked className="mr-3" />
                      <span className="text-sm text-gray-700">Κρίσιμες ειδοποιήσεις</span>
                    </label>
                    <label className="flex items-center">
                      <input type="checkbox" className="mr-3" />
                      <span className="text-sm text-gray-700">Ενημερώσεις παράδοσης</span>
                    </label>
                    <label className="flex items-center">
                      <input type="checkbox" className="mr-3" />
                      <span className="text-sm text-gray-700">Ειδοποιήσεις καθυστέρησης</span>
                    </label>
                  </div>
                </div>
              </div>

              <div className="pt-6 border-t border-gray-200">
                <h4 className="text-md font-medium text-gray-900 mb-4">Συχνότητα Ειδοποιήσεων</h4>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <label className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="frequency" value="immediate" defaultChecked className="mr-3" />
                    <div>
                      <div className="font-medium text-gray-900">Άμεσες</div>
                      <div className="text-sm text-gray-500">Ειδοποιήσεις αμέσως</div>
                    </div>
                  </label>
                  <label className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="frequency" value="hourly" className="mr-3" />
                    <div>
                      <div className="font-medium text-gray-900">Ωριαίες</div>
                      <div className="text-sm text-gray-500">Συγκεντρωμένες αναφορές</div>
                    </div>
                  </label>
                  <label className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="radio" name="frequency" value="daily" className="mr-3" />
                    <div>
                      <div className="font-medium text-gray-900">Ημερήσιες</div>
                      <div className="text-sm text-gray-500">Μία φορά την ημέρα</div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <div className="mt-6 flex justify-end">
              <button className="px-6 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                Αποθήκευση Ρυθμίσεων
              </button>
            </div>
          </div>
        )}

        {/* Quick Actions */}
        <div className="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Γρήγορες Ενέργειες</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Link
              href="/notifications"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Bell className="w-5 h-5 text-orange-600 mr-3" />
              <div>
                <h4 className="font-medium text-gray-900">Προβολή Ειδοποιήσεων</h4>
                <p className="text-sm text-gray-500">Δείτε όλες τις ειδοποιήσεις</p>
              </div>
            </Link>
            <Link
              href="/help/notifications"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Settings className="w-5 h-5 text-blue-600 mr-3" />
              <div>
                <h4 className="font-medium text-gray-900">Βοήθεια</h4>
                <p className="text-sm text-gray-500">Μάθετε περισσότερα</p>
              </div>
            </Link>
            <Link
              href="/help/notifications/setup"
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <MessageCircle className="w-5 h-5 text-green-600 mr-3" />
              <div>
                <h4 className="font-medium text-gray-900">Οδηγός Ρύθμισης</h4>
                <p className="text-sm text-gray-500">Βήμα-βήμα οδηγίες</p>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}